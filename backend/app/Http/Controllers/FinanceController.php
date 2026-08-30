<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Console\Commands\FetchExchangeRates;
use App\Http\Controllers\Concerns\HandlesFinanceBlobs;
use App\Http\Controllers\Concerns\OptimisticUpdates;
use App\Mail\QuoteMail;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\FileEntry;
use App\Models\FinanceCategory;
use App\Models\FinancePartner;
use App\Models\FinancePartnerNote;
use App\Models\FinanceProduct;
use App\Models\FinanceProject;
use App\Models\FinanceQuote;
use App\Models\FinanceReceipt;
use App\Models\GalleryPhoto;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\UserSetting;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyInvoiceReadProjection;
use App\Modules\Finance\Infrastructure\Mail\CompanySmtpMailer;
use App\Support\DocumentNumber;
use App\Support\FinanceScope;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plaintext-relational Finance (pivot). Invoices + business partners + payment
 * methods + bank transactions + projects + categories as owner-scoped rows
 * (OwnsUserData). Sensitive columns (IBAN/card numbers, customer PII, booking
 * details, line items) carry an `encrypted` cast; money + numbering columns stay
 * plaintext so the server can drive GoBD numbering and VAT/revenue stats.
 *
 * Invoice PDFs and receipt files live plaintext on the file disk at invoices/{uuid}.
 * Every write is a single-row INSERT/UPDATE in a transaction with optimistic
 * `version` concurrency — no whole-blob re-serialize, so the opaque
 * last-writer-wins loss class cannot occur.
 *
 * The rendered/printed invoice, ZUGFeRD/receipt parsing and money math stay
 * client-side; the server persists what the client computes plus the numbering
 * it must own for GoBD gaplessness.
 */
class FinanceController extends Controller
{
    use HandlesFinanceBlobs;
    use OptimisticUpdates;

    public function __construct(
        private readonly CompanySmtpMailer $companySmtp,
        private readonly LegacyInvoiceReadProjection $financeV2Invoices = new LegacyInvoiceReadProjection,
    ) {}

    // ---- Storage helpers ----

    /** Filesystem-safe download filename (strips path separators + control chars). */

    // ---- Page / snapshot ----

    public function page(Request $request): View
    {
        $this->requireUser($request);

        // Render the existing Finance shell (the client still drives from its own
        // fetches during the frontend-switch window); inline the relational data
        // for the eventual server-hydrated page.
        return view('spa', $this->snapshot());
    }

    public function index(Request $request): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($this->snapshot());
    }

    /**
     * The active (non-trashed) finance data for the current user.
     *
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'invoices' => $this->allInvoices(),
            'partners' => FinancePartner::query()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
            'projects' => FinanceProject::query()->orderBy('name')->get(),
            'financeCategories' => FinanceCategory::query()->orderBy('name')->get(),
            'products' => FinanceProduct::query()->orderBy('name')->get(),
            'quotes' => FinanceQuote::query()->orderByDesc('issue_date')->orderByDesc('id')->get(),
            // effective_scope travels with the row so no client has to re-derive
            // the inheritance rule (booking -> account) and drift from it.
            'transactions' => BankTransaction::query()->with('paymentMethod')->orderByDesc('date')->orderByDesc('id')->get()
                ->each(fn (BankTransaction $tx) => $tx->setAttribute('effective_scope', FinanceScope::ofTransaction($tx)))
                ->each(fn (BankTransaction $tx) => $tx->unsetRelation('paymentMethod')),
            'standaloneReceipts' => FinanceReceipt::query()->with('bankTransaction.paymentMethod')->orderByDesc('created_at')->orderByDesc('id')->get()
                ->each(fn (FinanceReceipt $r) => $r->setAttribute('effective_scope', FinanceScope::ofReceipt($r)))
                ->each(fn (FinanceReceipt $r) => $r->unsetRelation('bankTransaction')),
            // Foreign-currency receipts are matched against euro bookings, so the
            // client needs today's rates. finance:fetch-fx refreshes this cache
            // daily; the config values are the fallback until it first succeeds.
            'fxRates' => $this->fxRates(),
        ];
    }

    /**
     * Every invoice of the current owner: historical legacy rows plus every
     * finance-v2 invoice created since the Task 17 cutover, projected into
     * the same legacy shape the Home screen already expects. Both sources,
     * newest first — a home dashboard that only showed one after the cutover
     * would quietly stop reflecting new work.
     *
     * @return Collection<int, Invoice>
     */
    private function allInvoices(): Collection
    {
        $legacy = Invoice::query()->get();
        $userId = auth()->id();
        $financeV2 = is_int($userId) ? $this->financeV2Invoices->asInvoiceModels($userId) : collect();

        return $legacy->concat($financeV2)
            ->sortByDesc(fn (Invoice $i): string => (string) $i->issue_date?->format('Y-m-d'))
            ->values();
    }

    /**
     * X -> EUR rates for the client's receipt matching.
     *
     * @return array<string, float>
     */
    private function fxRates(): array
    {
        $cached = Cache::get(FetchExchangeRates::CACHE_KEY);
        $rates = is_array($cached) && $cached !== [] ? $cached : config('finance.fx_default', []);
        $out = [];
        foreach (is_array($rates) ? $rates : [] as $code => $rate) {
            if (is_string($code) && is_numeric($rate)) {
                $out[strtoupper($code)] = (float) $rate;
            }
        }

        return $out === [] ? ['EUR' => 1.0] : $out;
    }

    public function trash(): JsonResponse
    {
        return response()->json([
            'invoices' => Invoice::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'partners' => FinancePartner::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'paymentMethods' => PaymentMethod::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'projects' => FinanceProject::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'products' => FinanceProduct::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'quotes' => FinanceQuote::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'transactions' => BankTransaction::onlyTrashed()->orderByDesc('deleted_at')->get(),
        ]);
    }

    // ---- Partners ----

    public function storePartner(Request $request): JsonResponse
    {
        $request->validate($this->partnerRules(true));
        $uid = (int) $this->requireUser($request)->id;

        $partner = DB::transaction(function () use ($request, $uid): FinancePartner {
            $patch = $this->partnerPatch($request, true);
            $given = $request->input('customer_number');
            $patch['customer_number'] = is_string($given) && trim($given) !== ''
                ? trim($given)
                : $this->nextCustomerNumber($uid, is_string($patch['kind'] ?? null) ? $patch['kind'] : null);

            $partner = new FinancePartner;
            $partner->fill($patch);
            // Server-owned like every other number in this module.
            $partner->forceFill(['customer_number' => $patch['customer_number']]);
            $partner->save();

            return $partner;
        });

        return response()->json(['partner' => $partner->fresh()], 201);
    }

    public function updatePartner(Request $request, FinancePartner $partner): JsonResponse
    {
        $request->validate($this->partnerRules(false) + ['version' => ['sometimes', 'integer', 'min:0']]);
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(FinancePartner::class, $partner->id, $this->partnerPatch($request, false), $expected);

        return $this->optimisticJson($result, FinancePartner::class, $partner->id, 'partner');
    }

    public function destroyPartner(FinancePartner $partner): JsonResponse
    {
        $partner->delete();

        return response()->json(['ok' => true]);
    }

    public function restorePartner(int $id): JsonResponse
    {
        $partner = FinancePartner::onlyTrashed()->findOrFail($id);
        $partner->restore();

        return response()->json(['partner' => $partner]);
    }

    public function forceDeletePartner(int $id): JsonResponse
    {
        FinancePartner::withTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerRules(bool $creating = true): array
    {
        return [
            // Partial on update, like every other module here: `partnerPatch`
            // only touches fields the request actually sent, so demanding the
            // name on every update would contradict what the patch does.
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:300'],
            'category' => ['nullable', 'string', 'max:120'],
            // What the partner is to us. The column existed unused since the
            // pivot; it means this now.
            'kind' => ['nullable', Rule::in(FinancePartner::KINDS)],
            'customer_number' => ['nullable', 'string', 'max:32'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'string', 'max:2000'],
            // logo is a data: URI (fetched favicon/BIMI) — a few KB; the column is TEXT.
            'logo' => ['nullable', 'string', 'max:2000000'],
            'note' => ['nullable', 'string', 'max:100000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'max:320'],
            'invoice_email' => ['nullable', 'string', 'email:rfc', 'max:320'],
            'phone' => ['nullable', 'string', 'max:100'],
            'vat_id' => ['nullable', 'string', 'max:64'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['nullable', 'string', 'max:8'],
            'contacts' => ['nullable', 'array', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerPatch(Request $request, bool $create): array
    {
        $patch = [];
        foreach (['name', 'category', 'kind', 'url', 'logo', 'note', 'address', 'delivery_address', 'email', 'invoice_email', 'phone', 'vat_id'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->string($field)->value() : ($field === 'name' ? '' : null);
            }
        }
        if ($create || $request->has('contacts')) {
            $patch['contacts'] = $request->filled('contacts') ? $request->array('contacts') : null;
        }
        if ($create || $request->has('hourly_rate')) {
            $patch['hourly_rate'] = $request->filled('hourly_rate') ? $request->float('hourly_rate') : null;
        }
        if ($create || $request->has('currency')) {
            $patch['currency'] = $request->filled('currency') ? $request->string('currency')->value() : null;
        }
        if ($create || $request->has('payment_terms_days')) {
            $patch['payment_terms_days'] = $request->filled('payment_terms_days') ? $request->integer('payment_terms_days') : null;
        }
        if ($create || $request->has('discount_percent')) {
            $patch['discount_percent'] = $request->filled('discount_percent') ? $request->float('discount_percent') : null;
        }

        return $patch;
    }

    /**
     * The next customer number, from the same template machinery as the document
     * numbers — so a configured format behaves the same everywhere.
     *
     * Assigned only when the caller did not supply one, and only for a party we
     * actually sell to: a supplier rarely carries our customer number.
     */
    private function nextCustomerNumber(int $userId, ?string $kind): ?string
    {
        if ($kind !== null && ! in_array($kind, ['customer', 'both', 'lead'], true)) {
            return null;
        }
        $settings = UserSetting::for($userId);
        $fmt = is_string($settings->customer_number_format) && $settings->customer_number_format !== ''
            ? $settings->customer_number_format
            : 'K-NNNN';
        $floorRaw = $settings->customer_next_number;
        $floor = is_numeric($floorRaw) ? (int) $floorRaw : 1;

        // Binned partners count: reusing a number a customer has seen on a
        // document would make two parties share an identifier.
        $rows = FinancePartner::withTrashed()->whereNotNull('customer_number')->get(['customer_number']);
        $maxSeq = 0;
        foreach ($rows as $row) {
            $derived = is_string($row->customer_number)
                ? DocumentNumber::sequenceFrom($fmt, $row->customer_number, null)
                : null;
            if ($derived !== null) {
                $maxSeq = max($maxSeq, $derived);
            }
        }

        return DocumentNumber::format($fmt, max($floor, $maxSeq + 1), null);
    }

    /**
     * Hide a partner from the pickers without deleting it.
     *
     * Its documents keep pointing at it — that is the whole reason this is not a
     * delete.
     */
    public function archivePartner(Request $request, FinancePartner $partner): JsonResponse
    {
        $request->validate(['archived' => ['required', 'boolean']]);
        $partner->forceFill([
            'archived_at' => $request->boolean('archived') ? Carbon::now() : null,
            'version' => (int) $partner->version + 1,
        ])->save();

        return response()->json(['partner' => $partner->fresh()]);
    }

    /** A partner's contact log, newest first. */
    public function partnerNotes(FinancePartner $partner): JsonResponse
    {
        return response()->json([
            'notes' => FinancePartnerNote::query()
                ->where('finance_partner_id', $partner->getKey())
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(500)
                ->get(),
        ]);
    }

    public function storePartnerNote(Request $request, FinancePartner $partner): JsonResponse
    {
        $request->validate([
            'kind' => ['nullable', Rule::in(FinancePartnerNote::KINDS)],
            'body' => ['required', 'string', 'max:20000'],
            // When it happened, which is not when it was typed.
            'occurred_at' => ['nullable', 'date'],
        ]);

        $at = $request->input('occurred_at');
        $kind = $request->input('kind');
        $note = new FinancePartnerNote;
        $note->fill([
            'finance_partner_id' => (int) $partner->id,
            'kind' => is_string($kind) && $kind !== '' ? $kind : 'note',
            'body' => $request->string('body')->value(),
            'occurred_at' => is_string($at) && $at !== '' ? Carbon::parse($at) : Carbon::now(),
        ]);
        $note->save();

        return response()->json(['note' => $note->fresh()], 201);
    }

    public function destroyPartnerNote(FinancePartner $partner, int $note): JsonResponse
    {
        // Scoped through the partner as well as the owner: a note id alone must
        // not reach into another partner's log.
        FinancePartnerNote::query()
            ->where('finance_partner_id', $partner->getKey())
            ->whereKey($note)
            ->firstOrFail()
            ->delete();

        return response()->json(['ok' => true]);
    }

    // ---- Payment methods ----

    public function storePaymentMethod(Request $request): JsonResponse
    {
        $request->validate($this->paymentMethodRules(true));
        $method = DB::transaction(fn (): PaymentMethod => PaymentMethod::create($this->paymentMethodPatch($request, true)));

        return response()->json(['payment_method' => $method], 201);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $request->validate($this->paymentMethodRules(false) + ['version' => ['sometimes', 'integer', 'min:0']]);
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(PaymentMethod::class, $paymentMethod->id, $this->paymentMethodPatch($request, false), $expected);

        return $this->optimisticJson($result, PaymentMethod::class, $paymentMethod->id, 'payment_method');
    }

    public function destroyPaymentMethod(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->delete();

        return response()->json(['ok' => true]);
    }

    public function restorePaymentMethod(int $id): JsonResponse
    {
        $method = PaymentMethod::onlyTrashed()->findOrFail($id);
        $method->restore();

        return response()->json(['payment_method' => $method]);
    }

    public function forceDeletePaymentMethod(int $id): JsonResponse
    {
        PaymentMethod::withTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMethodRules(bool $create): array
    {
        return [
            'type' => [$create ? 'required' : 'sometimes', 'string', Rule::in(['bank', 'card', 'paypal', 'cash', 'other'])],
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:200'],
            // An account always states its scope; its bookings inherit it.
            'scope' => ['sometimes', 'string', Rule::in(FinanceScope::ALL)],
            'holder' => ['nullable', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:20000'],
            'business' => ['sometimes', 'boolean'],
            'url' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:200000'],
            'iban' => ['nullable', 'string', 'max:64'],
            'bic' => ['nullable', 'string', 'max:32'],
            'bank' => ['nullable', 'string', 'max:200'],
            'account_no' => ['nullable', 'string', 'max:64'],
            'card_number' => ['nullable', 'string', 'max:40'],
            'card_network' => ['nullable', 'string', 'max:40'],
            'card_expiry' => ['nullable', 'string', 'max:16'],
            'paypal_email' => ['nullable', 'string', 'max:320'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMethodPatch(Request $request, bool $create): array
    {
        $patch = [];
        foreach (['type', 'name', 'holder', 'url', 'icon', 'iban', 'bic', 'bank', 'account_no', 'card_number', 'card_network', 'card_expiry', 'paypal_email', 'note'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->string($field)->value() : null;
            }
        }
        if ($create || $request->has('business')) {
            $patch['business'] = $request->boolean('business');
        }
        if ($create || $request->has('scope')) {
            $patch['scope'] = FinanceScope::normalise($request->string('scope')->value());
        }

        return $patch;
    }

    // ---- Projects ----

    public function storeProject(Request $request): JsonResponse
    {
        $request->validate($this->projectRules($request, true));
        $project = DB::transaction(fn (): FinanceProject => FinanceProject::create($this->projectPatch($request, true)));

        return response()->json(['project' => $project], 201);
    }

    public function updateProject(Request $request, FinanceProject $project): JsonResponse
    {
        $request->validate($this->projectRules($request, false) + ['version' => ['sometimes', 'integer', 'min:0']]);
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(FinanceProject::class, $project->id, $this->projectPatch($request, false), $expected);

        return $this->optimisticJson($result, FinanceProject::class, $project->id, 'project');
    }

    public function moveProject(Request $request, FinanceProject $project): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['parent_id' => ['nullable', 'integer', Rule::exists('finance_projects', 'id')->where('user_id', $uid)->whereNull('deleted_at')]]);
        $parentId = $request->filled('parent_id') ? $request->integer('parent_id') : null;
        if ($parentId !== null && $this->wouldCycle($project->id, $parentId)) {
            return response()->json(['error' => 'cycle'], 422);
        }
        $project->update(['parent_id' => $parentId]);

        return response()->json(['project' => $project]);
    }

    /**
     * Everything else filed against a project: files/documents from the Files
     * module and photos from the Gallery. Read-only and owner-scoped (both models
     * carry the global owner scope); linking/unlinking happens through those
     * modules' own update endpoints, which is where their validation lives.
     *
     * Deliberately its own endpoint rather than part of /finance/data: a library
     * can hold tens of thousands of files, and only the open project's handful
     * are ever wanted.
     */
    public function projectAttachments(FinanceProject $project): JsonResponse
    {
        $files = FileEntry::query()
            ->where('finance_project_id', $project->id)
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get(['id', 'name', 'mime', 'size', 'file_folder_id', 'version', 'created_at', 'updated_at']);

        $photos = GalleryPhoto::query()
            ->where('finance_project_id', $project->id)
            ->orderByDesc(DB::raw('COALESCE(taken_at, created_at)'))
            ->limit(500)
            ->get(['id', 'name', 'mime', 'size', 'width', 'height', 'taken_at', 'media_type', 'version', 'created_at']);

        return response()->json(['files' => $files, 'photos' => $photos]);
    }

    private function wouldCycle(int $projectId, int $newParentId): bool
    {
        $cursor = $newParentId;
        $guard = 0;
        while ($cursor !== null && $guard++ < 1000) {
            if ($cursor === $projectId) {
                return true;
            }
            $parent = FinanceProject::query()->whereKey($cursor)->value('parent_id');
            $cursor = is_numeric($parent) ? (int) $parent : null;
        }

        return false;
    }

    /** Soft-delete a project; its children keep their parent_id (surface as roots). */
    public function destroyProject(FinanceProject $project): JsonResponse
    {
        $project->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreProject(int $id): JsonResponse
    {
        $project = FinanceProject::onlyTrashed()->findOrFail($id);
        $project->restore();

        return response()->json(['project' => $project]);
    }

    public function forceDeleteProject(int $id): JsonResponse
    {
        FinanceProject::withTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectRules(Request $request, bool $create): array
    {
        $uid = (int) $this->requireUser($request)->id;

        return [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:300'],
            'parent_id' => ['nullable', 'integer', Rule::exists('finance_projects', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'kind' => ['sometimes', 'string', Rule::in(['business', 'private'])],
            // Planning fields. A project remains usable with none of them set.
            'status' => ['nullable', Rule::in(FinanceProject::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'budget_net' => ['nullable', 'numeric', 'min:-100000000', 'max:100000000'],
            'partner_id' => ['nullable', 'integer', Rule::exists('finance_partners', 'id')->where('user_id', request()->user()?->id)],
            'note' => ['nullable', 'string', 'max:100000'],
            'expenses' => ['nullable', 'array', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPatch(Request $request, bool $create): array
    {
        $patch = [];
        if ($create || $request->has('name')) {
            $patch['name'] = $request->filled('name') ? $request->string('name')->value() : '';
        }
        if ($create || $request->has('parent_id')) {
            $patch['parent_id'] = $request->filled('parent_id') ? $request->integer('parent_id') : null;
        }
        if ($create || $request->has('kind')) {
            $patch['kind'] = $request->filled('kind') ? $request->string('kind')->value() : 'business';
        }
        if ($create || $request->has('note')) {
            $patch['note'] = $request->filled('note') ? $request->string('note')->value() : null;
        }
        if ($create || $request->has('expenses')) {
            $patch['expenses'] = $request->filled('expenses') ? $request->array('expenses') : null;
        }
        if ($create || $request->has('status')) {
            $patch['status'] = $request->filled('status') ? $request->string('status')->value() : 'planned';
        }
        foreach (['starts_on', 'due_on'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->string($field)->value() : null;
            }
        }
        if ($create || $request->has('budget_net')) {
            $patch['budget_net'] = $request->filled('budget_net') ? $request->float('budget_net') : null;
        }
        if ($create || $request->has('partner_id')) {
            $patch['partner_id'] = $request->filled('partner_id') ? $request->integer('partner_id') : null;
        }

        return $patch;
    }

    // ---- Categories (hard-deleted lookup list) ----

    /**
     * Monochrome icon names a custom category may carry — each exists in
     * resources/views/components/icon.blade.php (a blank name would render invisible).
     * Kept in sync with `catIconOptions` (invoices.js) and `_category_icon.blade.php`.
     *
     * @var list<string>
     */
    private const CATEGORY_ICONS = [
        'hashtag', 'tag', 'banknotes', 'credit-card', 'wallet', 'currency-euro',
        'currency-dollar', 'currency-pound', 'currency-yen', 'currency-rupee', 'receipt-percent', 'receipt-refund',
        'calculator', 'building-library', 'building-office', 'building-office-2', 'building-storefront', 'briefcase',
        'chart-bar', 'chart-bar-square', 'chart-pie', 'presentation-chart-line', 'presentation-chart-bar', 'arrow-trending-up',
        'arrow-trending-down', 'table-cells', 'list-bullet', 'queue-list', 'document', 'document-text',
        'document-check', 'document-duplicate', 'document-magnifying-glass', 'document-currency-euro', 'document-currency-dollar', 'document-plus',
        'document-minus', 'clipboard-document', 'clipboard-document-check', 'clipboard-document-list', 'newspaper', 'book-open',
        'folder', 'folder-open', 'archive-box', 'archive-box-arrow-down', 'inbox', 'inbox-stack',
        'rectangle-stack', 'square-3-stack-3d', 'rectangle-group', 'server', 'server-stack', 'cpu-chip',
        'shopping-cart', 'shopping-bag', 'gift', 'gift-top', 'truck', 'cube',
        'cube-transparent', 'wrench', 'wrench-screwdriver', 'cog-6-tooth', 'cog-8-tooth', 'bolt',
        'fire', 'light-bulb', 'command-line', 'beaker', 'scale', 'swatch',
        'paint-brush', 'pencil-square', 'scissors', 'envelope', 'at-symbol', 'phone',
        'phone-arrow-up-right', 'chat-bubble-left', 'chat-bubble-left-right', 'chat-bubble-oval-left', 'megaphone', 'video-camera',
        'microphone', 'musical-note', 'speaker-wave', 'signal', 'rss', 'cloud',
        'cloud-arrow-up', 'cloud-arrow-down', 'globe', 'globe-alt', 'globe-europe-africa', 'globe-americas',
        'globe-asia-australia', 'map', 'map-pin', 'route', 'home', 'home-modern',
        'camera', 'photo', 'film', 'printer', 'device-tablet', 'users',
        'user-group', 'academic-cap', 'hand-thumb-up', 'hand-thumb-down', 'hand-raised', 'trophy',
        'flag', 'ticket', 'bell', 'bell-alert', 'bookmark', 'star',
        'heart', 'sparkles', 'calendar', 'calendar-days', 'calendar-date-range', 'clock',
        'sun', 'moon', 'plus-circle', 'minus-circle', 'check-badge', 'exclamation-circle',
        'question-mark-circle', 'adjustments-horizontal', 'adjustments-vertical', 'funnel', 'bars-arrow-down', 'bars-arrow-up',
        'eye-slash', 'key', 'lock-closed', 'shield', 'shield-check', 'wifi',
        'paper-clip', 'backspace', 'battery-100', 'thermometer', 'cake',
    ];

    public function storeCategory(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate($this->categoryRules($request, $uid, null));
        $category = DB::transaction(fn (): FinanceCategory => FinanceCategory::create($this->categoryPatch($request)));

        return response()->json(['category' => $category], 201);
    }

    public function updateCategory(Request $request, FinanceCategory $category): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate($this->categoryRules($request, $uid, $category->id));
        $category->update($this->categoryPatch($request));

        return response()->json(['category' => $category]);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryRules(Request $request, int $uid, ?int $ignoreId): array
    {
        $unique = Rule::unique('finance_categories', 'name')->where('user_id', $uid);
        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId);
        }

        return [
            'name' => ['required', 'string', 'max:160', $unique],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:40', Rule::in(self::CATEGORY_ICONS)],
            // Sachkonto/account number (e.g. a SKR03/04 chart-of-accounts code) — free
            // text the owner enters to match their own accountant's chart of accounts;
            // the app never assigns or validates a specific number itself.
            'account_no' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function categoryPatch(Request $request): array
    {
        return [
            'name' => $request->string('name')->value(),
            'color' => $request->filled('color') ? $request->string('color')->value() : null,
            'icon' => $request->filled('icon') ? $request->string('icon')->value() : null,
            'account_no' => $request->filled('account_no') ? $request->string('account_no')->value() : null,
        ];
    }

    public function destroyCategory(FinanceCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(['ok' => true]);
    }

    // ---- Invoices ----
    //
    // Every invoice CRUD/finalize/storno/email/dun/PDF method that used to
    // live here was removed in the Task 17 cutover: invoice creation and
    // lifecycle management moved to the finance-v2 module
    // (App\Modules\Finance\Http\Controllers\Invoices\*, canonical routes at
    // /api/v1/finance/invoices/*). The legacy invoices table and model stay
    // solely as historical record -- LegacyInvoiceReadProjection still reads
    // it for Home/reports, and it is never written to again.

    /**
     * Mail a quote to the customer.
     *
     * Lives here rather than in the quote controller because the runtime company
     * mailer and its teardown live here: a second copy of that plumbing is the
     * kind of thing that drifts, and under Octane a mailer left configured leaks
     * one user's SMTP password into the next request.
     */
    public function emailQuote(Request $request, FinanceQuote $quote): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['to' => ['nullable', 'email:rfc']]);

        if (! is_string($quote->number) || $quote->number === '') {
            // Nothing has been issued, so there is nothing to send.
            return response()->json(['error' => 'quote_not_sent'], 422);
        }

        $path = $this->safeBlobPath($quote->pdf_path);
        if ($path === null || ! $this->fs()->exists($path)) {
            return response()->json(['error' => 'no_pdf'], 422);
        }

        $customer = is_array($quote->customer) ? $quote->customer : [];
        $to = $request->filled('to')
            ? $request->string('to')->value()
            : (is_string($customer['email'] ?? null) && $customer['email'] !== '' ? $customer['email'] : null);
        if ($to === null || $to === '') {
            return response()->json(['error' => 'no_recipient'], 422);
        }

        if (! $this->companySmtp->configured($uid)) {
            return response()->json(['error' => 'no_smtp'], 422);
        }

        $this->companySmtp->send($uid, $to, new QuoteMail($quote));

        $sentAt = Carbon::now();
        $quote->forceFill(['sent_at' => $sentAt])->saveQuietly();
        // Secret-free: the recipient domain only, never the full address.
        AuditLog::record('quote.emailed', $quote, ['to_domain' => Str::after($to, '@')]);

        return response()->json(['ok' => true, 'sent_at' => $sentAt->toIso8601String()]);
    }

    // ---- Bank transactions ----

    public function storeTransaction(Request $request): JsonResponse
    {
        $request->validate($this->transactionRules($request, true));
        $tx = DB::transaction(fn (): BankTransaction => BankTransaction::create($this->transactionPatch($request, true)));

        return response()->json(['transaction' => $tx], 201);
    }

    public function updateTransaction(Request $request, BankTransaction $transaction): JsonResponse
    {
        $request->validate($this->transactionRules($request, false) + ['version' => ['sometimes', 'integer', 'min:0']]);
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(BankTransaction::class, $transaction->id, $this->transactionPatch($request, false, $transaction), $expected);

        return $this->optimisticJson($result, BankTransaction::class, $transaction->id, 'transaction');
    }

    /**
     * Bulk import parsed bank-statement lines for an account, deduplicating by
     * `sig` (against existing rows AND within the batch). Parsing stays client-side.
     */
    public function bulkTransactions(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'transactions' => ['required', 'array', 'max:5000'],
            'transactions.*.date' => ['required', 'date'],
            'transactions.*.amount' => ['required', 'numeric'],
            'transactions.*.sig' => ['nullable', 'string', 'max:80'],
            'transactions.*.vat_cat' => ['nullable', 'string', 'max:16'],
            'transactions.*.counterparty' => ['nullable', 'string', 'max:2000'],
            'transactions.*.counterparty_iban' => ['nullable', 'string', 'max:64'],
            'transactions.*.bic' => ['nullable', 'string', 'max:32'],
            'transactions.*.purpose' => ['nullable', 'string', 'max:4000'],
            'transactions.*.booking_text' => ['nullable', 'string', 'max:2000'],
            'transactions.*.eref' => ['nullable', 'string', 'max:200'],
        ]);

        $pmId = $request->integer('payment_method_id');
        $rows = $request->array('transactions');

        $existing = [];
        foreach (BankTransaction::query()->whereNotNull('sig')->pluck('sig') as $s) {
            if (is_string($s) && $s !== '') {
                $existing[$s] = true;
            }
        }

        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($rows, $pmId, &$existing, &$created, &$skipped): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $skipped++;

                    continue;
                }
                $sig = isset($row['sig']) && is_string($row['sig']) && $row['sig'] !== '' ? $row['sig'] : null;
                if ($sig !== null && isset($existing[$sig])) {
                    $skipped++;

                    continue;
                }

                BankTransaction::create([
                    'payment_method_id' => $pmId,
                    'date' => $this->rowString($row, 'date'),
                    'amount' => isset($row['amount']) && is_numeric($row['amount']) ? (float) $row['amount'] : 0,
                    'vat_cat' => $this->rowString($row, 'vat_cat'),
                    'sig' => $sig,
                    'counterparty' => $this->rowString($row, 'counterparty'),
                    'counterparty_iban' => $this->rowString($row, 'counterparty_iban'),
                    'bic' => $this->rowString($row, 'bic'),
                    'purpose' => $this->rowString($row, 'purpose'),
                    'booking_text' => $this->rowString($row, 'booking_text'),
                    'eref' => $this->rowString($row, 'eref'),
                ]);
                if ($sig !== null) {
                    $existing[$sig] = true;
                }
                $created++;
            }
        });

        return response()->json(['created' => $created, 'skipped' => $skipped], 201);
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private function rowString(array $row, string $key): ?string
    {
        return isset($row[$key]) && is_scalar($row[$key]) && (string) $row[$key] !== '' ? (string) $row[$key] : null;
    }

    public function destroyTransaction(BankTransaction $transaction): JsonResponse
    {
        $transaction->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreTransaction(int $id): JsonResponse
    {
        $tx = BankTransaction::onlyTrashed()->findOrFail($id);
        $tx->restore();

        return response()->json(['transaction' => $tx]);
    }

    public function forceDeleteTransaction(int $id): JsonResponse
    {
        $tx = BankTransaction::withTrashed()->findOrFail($id);
        DB::transaction(function () use ($tx): void {
            foreach ($this->receiptPaths($tx) as $path) {
                $this->fs()->delete($path);
            }
            $tx->forceDelete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionRules(Request $request, bool $create): array
    {
        $uid = (int) $this->requireUser($request)->id;

        return [
            'payment_method_id' => [$create ? 'required' : 'sometimes', 'integer', Rule::exists('payment_methods', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'date' => [$create ? 'required' : 'sometimes', 'date'],
            'amount' => [$create ? 'required' : 'sometimes', 'numeric'],
            'vat_cat' => ['nullable', 'string', 'max:16'],
            // null/absent = follow the account; an explicit value overrides it.
            'scope' => ['nullable', 'string', Rule::in(FinanceScope::ALL)],
            'sig' => ['nullable', 'string', 'max:80'],
            'invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'invoice_number' => ['nullable', 'string', 'max:64'],
            'finance_project_id' => ['nullable', 'integer', Rule::exists('finance_projects', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'counterparty' => ['nullable', 'string', 'max:2000'],
            'counterparty_iban' => ['nullable', 'string', 'max:64'],
            'bic' => ['nullable', 'string', 'max:32'],
            'purpose' => ['nullable', 'string', 'max:4000'],
            'booking_text' => ['nullable', 'string', 'max:2000'],
            'eref' => ['nullable', 'string', 'max:200'],
            'receipts' => ['nullable', 'array', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /** @return array<string, mixed> */
    private function transactionPatch(Request $request, bool $create, ?BankTransaction $existing = null): array
    {
        $patch = [];
        if ($create || $request->has('payment_method_id')) {
            $patch['payment_method_id'] = $request->integer('payment_method_id');
        }
        if ($create || $request->has('date')) {
            $patch['date'] = $request->date('date');
        }
        if ($create || $request->has('amount')) {
            $patch['amount'] = $request->float('amount');
        }
        foreach (['vat_cat', 'scope', 'sig', 'invoice_number', 'counterparty', 'counterparty_iban', 'bic', 'purpose', 'booking_text', 'eref'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->string($field)->value() : null;
            }
        }
        foreach (['invoice_id', 'finance_project_id'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->integer($field) : null;
            }
        }
        if ($create || $request->has('receipts')) {
            // SECURITY: never trust a client-supplied blob_path (arbitrary-file-read /
            // IDOR). File receipts are only created via attachReceipt; here we accept
            // metadata edits + fileless (invoice-link) entries, but the on-disk path
            // and id of any file receipt are taken from the stored row, never the request.
            $incoming = ($create || ! $request->filled('receipts')) ? [] : $request->array('receipts');
            $patch['receipts'] = $this->sanitizeReceipts($incoming, $create ? null : $existing) ?: null;
        }

        return $patch;
    }

    /**
     * Merge client receipt entries against the stored row: a file receipt keeps the
     * SERVER's blob_path/id (matched by id); an entry with no matching stored id is
     * allowed only if it carries no blob_path (a fileless invoice-link/eigenbeleg-meta
     * entry) — any client-supplied blob_path is dropped. Prevents path injection.
     *
     * @param  array<array-key, mixed>  $incoming
     * @return list<array<array-key, mixed>>
     */
    private function sanitizeReceipts(array $incoming, ?BankTransaction $existing): array
    {
        $storedById = [];
        foreach ($existing?->receipts ?? [] as $r) {
            if (is_array($r) && isset($r['id']) && is_scalar($r['id'])) {
                $storedById[(string) $r['id']] = $r;
            }
        }
        $out = [];
        foreach ($incoming as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $id = isset($entry['id']) && is_scalar($entry['id']) ? (string) $entry['id'] : null;
            unset($entry['blob_path']); // never from the client
            if ($id !== null && isset($storedById[$id])) {
                $stored = $storedById[$id];
                $entry['id'] = $stored['id'];
                if (isset($stored['blob_path']) && is_string($stored['blob_path'])) {
                    $entry['blob_path'] = $stored['blob_path']; // server-owned path
                }
            } else {
                // New entry via PUT is only allowed fileless (real files use attachReceipt).
                unset($entry['id']);
            }
            $out[] = $entry;
        }

        return $out;
    }

    /** Guard: a stored receipt/PDF path must live under the finance blob prefix. */

    // ---- Receipts (files embedded in a transaction's receipts[] json) ----

    public function attachReceipt(Request $request, BankTransaction $transaction): JsonResponse
    {
        $uid = $this->requireUser($request)->id;
        $request->validate([
            // A receipt is a PDF or a raster scan/photo. Explicitly exclude svg/html
            // (stored-XSS vectors) — defense-in-depth on top of the serve-time sandbox CSP.
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,gif', 'max:'.$this->maxUploadKb()],
            'name' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', 'string', 'max:24'],
            'category' => ['nullable', 'string', 'max:160'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'contact_id' => ['nullable', 'string', 'max:64'],
            'partner_id' => ['nullable', 'integer', Rule::exists('finance_partners', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'vat' => ['nullable', 'string', 'max:16'],
            // Content signature, so a document already filed at a booking is
            // recognised by the receipt inbox instead of being uploaded twice.
            'sig' => ['nullable', 'string', 'max:80'],
        ]);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        $path = 'invoices/'.Str::uuid()->toString();
        $this->fs()->putFileAs('invoices', $upload, basename($path));

        $name = $request->filled('name') ? $request->string('name')->value() : $upload->getClientOriginalName();
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        /** @var list<string> $tags */
        $tags = array_values(array_filter($request->array('tags'), static fn ($t): bool => is_string($t)));

        $entry = [
            'id' => Str::uuid()->toString(),
            'blob_path' => $path,
            'name' => $name !== '' ? $name : 'receipt',
            'mime' => $mime !== '' ? $mime : null,
            'kind' => $request->filled('kind') ? $request->string('kind')->value() : 'receipt',
            'category' => $request->filled('category') ? $request->string('category')->value() : null,
            'tags' => $tags,
            'contactId' => $request->filled('contact_id') ? $request->string('contact_id')->value() : null,
            'partnerId' => $request->filled('partner_id') ? $request->integer('partner_id') : null,
            'vat' => $request->filled('vat') ? $request->string('vat')->value() : null,
            'sig' => $request->filled('sig') ? $request->string('sig')->value() : null,
            'locked' => false,
            'trashed' => false,
        ];

        try {
            $fresh = DB::transaction(function () use ($transaction, $entry): BankTransaction {
                $current = BankTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
                $receipts = is_array($current->receipts) ? $current->receipts : [];
                $receipts[] = $entry;
                $current->receipts = $receipts;
                $current->version = $current->version + 1;
                $current->save();

                return $current;
            });
        } catch (\Throwable $e) {
            // Blob written before the row lock; unlink it if the txn failed (e.g. the
            // transaction was deleted between binding and the lock → 404) so it isn't
            // orphaned on the shared `invoices/` disk.
            $this->fs()->delete($path);

            throw $e;
        }

        return response()->json(['transaction' => $fresh], 201);
    }

    public function receiptRaw(Request $request, BankTransaction $transaction, string $receipt): StreamedResponse
    {
        $entry = $this->findReceipt($transaction, $receipt);
        $path = $this->safeBlobPath(is_array($entry) ? ($entry['blob_path'] ?? null) : null);
        if ($path === null || ! $this->fs()->exists($path)) {
            abort(404);
        }
        $name = is_array($entry) && isset($entry['name']) && is_string($entry['name']) ? $entry['name'] : 'receipt';
        $mime = is_array($entry) && isset($entry['mime']) && is_string($entry['mime']) ? $entry['mime'] : 'application/octet-stream';

        return $this->fs()->response($path, $this->safeName($name), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    public function destroyReceipt(BankTransaction $transaction, string $receipt): JsonResponse
    {
        $fresh = DB::transaction(function () use ($transaction, $receipt): BankTransaction {
            $current = BankTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $receipts = is_array($current->receipts) ? $current->receipts : [];
            $kept = [];
            $removed = null;
            foreach ($receipts as $r) {
                if (is_array($r) && isset($r['id']) && $r['id'] === $receipt) {
                    $removed = $this->safeBlobPath($r['blob_path'] ?? null);

                    continue;
                }
                $kept[] = $r;
            }
            if ($removed !== null && $removed !== '') {
                $this->fs()->delete($removed);
            }
            $current->receipts = $kept;
            $current->version = $current->version + 1;
            $current->save();

            return $current;
        });

        return response()->json(['transaction' => $fresh]);
    }

    /**
     * Find a receipt entry (by its id) inside a transaction's receipts[] list.
     *
     * @return array<string, mixed>|null
     */
    private function findReceipt(BankTransaction $transaction, string $receiptId): ?array
    {
        $receipts = is_array($transaction->receipts) ? $transaction->receipts : [];
        foreach ($receipts as $r) {
            if (is_array($r) && isset($r['id']) && $r['id'] === $receiptId) {
                return $r;
            }
        }

        return null;
    }

    /**
     * Every stored receipt blob_path on a transaction (for force-delete cleanup).
     *
     * @return list<string>
     */
    private function receiptPaths(BankTransaction $transaction): array
    {
        $paths = [];
        $receipts = is_array($transaction->receipts) ? $transaction->receipts : [];
        foreach ($receipts as $r) {
            $safe = $this->safeBlobPath(is_array($r) ? ($r['blob_path'] ?? null) : null);
            if ($safe !== null) {
                $paths[] = $safe;
            }
        }

        return $paths;
    }

    // ---- Standalone receipts ("Fremdbelege") — a receipt WITHOUT a bank transaction ----

    /**
     * Upload a standalone receipt (documentation not tied to a booking). Bytes are
     * stored plaintext under invoices/{uuid}; blob_path/size/sig are server-set.
     * An optional bank_transaction_id links it to a booking when one exists.
     */
    public function storeReceipt(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,gif', 'max:'.$this->maxUploadKb()],
            'name' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', 'string', 'max:24'],
            // Business/private; null = follow the linked booking, else business.
            'scope' => ['nullable', 'string', Rule::in(FinanceScope::ALL)],
            'category' => ['nullable', 'string', 'max:160'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'vat' => ['nullable', 'string', 'max:16'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'currency' => ['nullable', 'string', 'max:8'],
            'date' => ['nullable', 'date'],
            'order_ref' => ['nullable', 'string', 'max:64'],
            'doc_number' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
            'ocr' => ['nullable', 'string', 'max:200000'],
            'sig' => ['nullable', 'string', 'max:128'],
            'partner_id' => ['nullable', 'integer', Rule::exists('finance_partners', 'id')->where('user_id', $user->id)->whereNull('deleted_at')],
            'bank_transaction_id' => ['nullable', 'integer', Rule::exists('bank_transactions', 'id')->where('user_id', $user->id)->whereNull('deleted_at')],
            'finance_project_id' => ['nullable', 'integer', Rule::exists('finance_projects', 'id')->where('user_id', $user->id)->whereNull('deleted_at')],
        ]);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        // Content-dedup: the same byte-identical document uploaded twice (e.g. a
        // user re-drops a whole batch to retry files a rate limit failed, silently
        // duplicating the ones that had already succeeded) returns the EXISTING
        // row instead of creating a second one — no new blob is ever written for
        // it. Client-computed sig (sha256 of the file), so this only fires when
        // the client sends one; nothing changes for a request without it.
        if ($request->filled('sig')) {
            $sig = $request->string('sig')->value();
            $existing = FinanceReceipt::query()->where('sig', $sig)->first();
            if ($existing instanceof FinanceReceipt) {
                return response()->json(['receipt' => $existing, 'duplicate' => true]);
            }
        }

        $path = 'invoices/'.Str::uuid()->toString();
        $this->fs()->putFileAs('invoices', $upload, basename($path));

        $name = $request->filled('name') ? $request->string('name')->value() : $upload->getClientOriginalName();
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        /** @var list<string> $tags */
        $tags = array_values(array_filter($request->array('tags'), static fn ($t): bool => is_string($t)));

        try {
            $receipt = new FinanceReceipt;
            $receipt->fill([
                'kind' => $request->filled('kind') ? $request->string('kind')->value() : 'receipt',
                // Left null on purpose when unstated: FinanceScope then follows the
                // linked booking, so a receipt does not have to repeat its account.
                'scope' => $request->filled('scope') ? FinanceScope::normalise($request->string('scope')->value()) : null,
                'category' => $request->filled('category') ? $request->string('category')->value() : null,
                'tags' => $tags,
                'vat' => $request->filled('vat') ? $request->string('vat')->value() : null,
                'amount' => $request->filled('amount') ? $request->input('amount') : null,
                'currency' => $request->filled('currency') ? $request->string('currency')->upper()->value() : null,
                'date' => $request->filled('date') ? $request->string('date')->value() : null,
                'order_ref' => $request->filled('order_ref') ? $request->string('order_ref')->value() : null,
                'doc_number' => $request->filled('doc_number') ? $request->string('doc_number')->value() : null,
                'note' => $request->filled('note') ? $request->string('note')->value() : null,
                'ocr' => $request->filled('ocr') ? $request->string('ocr')->value() : null,
                'name' => $name !== '' ? $name : 'receipt',
                'partner_id' => $request->filled('partner_id') ? $request->integer('partner_id') : null,
                'bank_transaction_id' => $request->filled('bank_transaction_id') ? $request->integer('bank_transaction_id') : null,
                'finance_project_id' => $request->filled('finance_project_id') ? $request->integer('finance_project_id') : null,
            ]);
            // Server-owned columns (never from the client).
            $receipt->forceFill([
                'user_id' => $user->id,
                'blob_path' => $path,
                'mime' => $mime !== '' ? $mime : null,
                'size' => $upload->getSize() ?: 0,
                'sig' => $request->filled('sig') ? $request->string('sig')->value() : null,
            ]);
            $receipt->save();
        } catch (\Throwable $e) {
            $this->fs()->delete($path); // no orphan on the shared invoices/ disk
            throw $e;
        }

        return response()->json(['receipt' => $receipt], 201);
    }

    public function updateReceipt(Request $request, FinanceReceipt $receipt): JsonResponse
    {
        $uid = $this->requireUser($request)->id;
        $request->validate([
            'name' => ['sometimes', 'string', 'max:500'],
            'category' => ['sometimes', 'nullable', 'string', 'max:160'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'vat' => ['sometimes', 'nullable', 'string', 'max:16'],
            // null = follow the linked booking (which follows its account).
            'scope' => ['sometimes', 'nullable', 'string', Rule::in(FinanceScope::ALL)],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:8'],
            'date' => ['sometimes', 'nullable', 'date'],
            'order_ref' => ['sometimes', 'nullable', 'string', 'max:64'],
            'doc_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'partner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('finance_partners', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'bank_transaction_id' => ['sometimes', 'nullable', 'integer', Rule::exists('bank_transactions', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            // A split-payment link (one receipt settled by several separate charges,
            // e.g. a vendor that bills domain transfers and a registration on the
            // same invoice but debits them two days apart) — see FinanceReceipt.
            'linked_transaction_ids' => ['sometimes', 'nullable', 'array', 'max:8'],
            'linked_transaction_ids.*' => ['integer', Rule::exists('bank_transactions', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'finance_project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('finance_projects', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $patch = [];
        foreach (['name', 'category', 'scope', 'vat', 'amount', 'currency', 'date', 'order_ref', 'doc_number', 'note', 'partner_id', 'bank_transaction_id', 'linked_transaction_ids', 'finance_project_id', 'tags'] as $f) {
            if ($request->has($f)) {
                $patch[$f] = $request->input($f);
            }
        }
        if (array_key_exists('currency', $patch) && is_string($patch['currency'])) {
            $patch['currency'] = mb_strtoupper($patch['currency']) ?: null;
        }
        // Mutually exclusive: a split-payment link replaces the single link, and vice
        // versa — never let a stale value from the other field linger alongside it.
        if (array_key_exists('linked_transaction_ids', $patch) && is_array($patch['linked_transaction_ids']) && $patch['linked_transaction_ids'] !== []) {
            $patch['bank_transaction_id'] = null;
        } elseif (array_key_exists('bank_transaction_id', $patch) && $patch['bank_transaction_id'] !== null) {
            $patch['linked_transaction_ids'] = null;
        }
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(FinanceReceipt::class, $receipt->id, $patch, $expected);

        return $this->optimisticJson($result, FinanceReceipt::class, $receipt->id, 'receipt');
    }

    public function destroyStandaloneReceipt(FinanceReceipt $receipt): JsonResponse
    {
        $receipt->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreStandaloneReceipt(int $id): JsonResponse
    {
        $receipt = FinanceReceipt::withTrashed()->findOrFail($id);
        $receipt->restore();

        return response()->json(['receipt' => $receipt]);
    }

    public function forceDeleteStandaloneReceipt(int $id): JsonResponse
    {
        $receipt = FinanceReceipt::withTrashed()->findOrFail($id);
        $path = $this->safeBlobPath($receipt->blob_path);
        if ($path !== null && $this->fs()->exists($path)) {
            $this->fs()->delete($path);
        }
        $receipt->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function receiptFile(Request $request, FinanceReceipt $receipt): StreamedResponse
    {
        $path = $this->safeBlobPath($receipt->blob_path);
        if ($path === null || ! $this->fs()->exists($path)) {
            abort(404);
        }
        $mime = is_string($receipt->mime) && $receipt->mime !== '' ? $receipt->mime : 'application/octet-stream';

        return $this->fs()->response($path, $this->safeName($receipt->name), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }
}
