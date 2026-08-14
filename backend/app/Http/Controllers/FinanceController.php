<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Mail\InvoiceReminderMail;
use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\FinanceCategory;
use App\Models\FinancePartner;
use App\Models\FinanceProject;
use App\Models\FinanceReceipt;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\UserSetting;
use App\Support\OutboundUrl;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
    // ---- Storage helpers ----

    private function disk(): string
    {
        $d = config('files.disk');

        return is_string($d) ? $d : 'files';
    }

    private function fs(): Filesystem
    {
        return Storage::disk($this->disk());
    }

    private function maxUploadKb(): int
    {
        $mb = config('files.max_upload_mb', 2048);

        return (is_numeric($mb) ? (int) $mb : 2048) * 1024;
    }

    /** Filesystem-safe download filename (strips path separators + control chars). */
    private function safeName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'file' : $clean;
    }

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
            'invoices' => Invoice::query()->orderByDesc('issue_date')->orderByDesc('id')->get(),
            'partners' => FinancePartner::query()->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
            'projects' => FinanceProject::query()->orderBy('name')->get(),
            'financeCategories' => FinanceCategory::query()->orderBy('name')->get(),
            'transactions' => BankTransaction::query()->orderByDesc('date')->orderByDesc('id')->get(),
            'standaloneReceipts' => FinanceReceipt::query()->orderByDesc('created_at')->orderByDesc('id')->get(),
        ];
    }

    public function trash(): JsonResponse
    {
        return response()->json([
            'invoices' => Invoice::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'partners' => FinancePartner::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'paymentMethods' => PaymentMethod::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'projects' => FinanceProject::onlyTrashed()->orderByDesc('deleted_at')->get(),
            'transactions' => BankTransaction::onlyTrashed()->orderByDesc('deleted_at')->get(),
        ]);
    }

    // ---- Generic optimistic per-row update ----

    /**
     * Optimistic per-row update inside a transaction. Returns the fresh model,
     * false on version conflict, or null when the row is gone.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $patch
     */
    private function optimistic(string $modelClass, int $id, array $patch, ?int $expected): Model|false|null
    {
        return DB::transaction(function () use ($modelClass, $id, $patch, $expected): Model|false|null {
            $fresh = $modelClass::query()->lockForUpdate()->find($id);
            if (! $fresh instanceof Model) {
                return null;
            }
            $raw = $fresh->getAttribute('version');
            $ver = is_int($raw) ? $raw : 0;
            if ($expected !== null && $ver !== $expected) {
                return false;
            }
            $fresh->fill($patch);
            $fresh->setAttribute('version', $ver + 1);
            $fresh->save();

            return $fresh;
        });
    }

    /**
     * Turn an {@see optimistic()} result into a JSON response (404 / 409 / 200).
     *
     * @param  class-string<Model>  $modelClass
     */
    private function optimisticJson(Model|false|null $result, string $modelClass, int $id, string $key): JsonResponse
    {
        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = $modelClass::query()->find($id);
            $v = $current instanceof Model ? $current->getAttribute('version') : null;

            return response()->json(['error' => 'version_conflict', 'version' => is_int($v) ? $v : 0], 409);
        }

        return response()->json([$key => $result]);
    }

    // ---- Partners ----

    public function storePartner(Request $request): JsonResponse
    {
        $request->validate($this->partnerRules());
        $partner = DB::transaction(fn (): FinancePartner => FinancePartner::create($this->partnerPatch($request, true)));

        return response()->json(['partner' => $partner], 201);
    }

    public function updatePartner(Request $request, FinancePartner $partner): JsonResponse
    {
        $request->validate($this->partnerRules() + ['version' => ['sometimes', 'integer', 'min:0']]);
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
    private function partnerRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:300'],
            'category' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'string', 'max:16'],
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
        foreach (['name', 'category', 'kind', 'url', 'logo', 'note', 'address', 'email', 'invoice_email', 'phone', 'vat_id'] as $field) {
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

        return $patch;
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

    public function storeInvoice(Request $request): JsonResponse
    {
        $request->validate($this->invoiceRules($request, true));
        $invoice = DB::transaction(fn (): Invoice => Invoice::create($this->invoicePatch($request, true)));

        return response()->json(['invoice' => $invoice], 201);
    }

    public function updateInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate($this->invoiceRules($request, false) + ['version' => ['sometimes', 'integer', 'min:0']]);
        // GoBD: a numbered (issued) invoice can never revert to draft — the number is
        // permanent. Reject a status→draft on a numbered invoice server-side (the client
        // guards too, but the server is authoritative for the gapless numbering trail).
        if ($request->filled('status') && $request->string('status')->value() === 'draft'
            && is_string($invoice->number) && $invoice->number !== '') {
            return response()->json(['error' => 'status_draft_blocked'], 422);
        }
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(Invoice::class, $invoice->id, $this->invoicePatch($request, false, $invoice), $expected);

        return $this->optimisticJson($result, Invoice::class, $invoice->id, 'invoice');
    }

    /**
     * Assign a gapless, unique per-year invoice number (GoBD). Server-authoritative:
     * inside a transaction it locks the user's numbered rows for the target year,
     * takes max(seq)+1 (never below the configured floor) and writes it atomically —
     * so two finalisations can never mint the same number. Idempotent: an invoice
     * that already carries a number is returned unchanged.
     */
    public function finalizeInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $settings = UserSetting::for($uid);
        $fmt = is_string($settings->invoice_number_format) ? $settings->invoice_number_format : null;
        $floor = max(1, (int) $settings->invoice_next_number);

        $fresh = $this->withNumberRetry(fn (): Invoice => DB::transaction(function () use ($invoice, $fmt, $floor): Invoice {
            $current = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (is_string($current->number) && $current->number !== '') {
                return $current; // already numbered → idempotent
            }

            $year = $current->year ?? ($current->issue_date instanceof Carbon ? (int) $current->issue_date->format('Y') : (int) Carbon::now()->format('Y'));
            $seq = $this->nextSeqForYear($year, $floor);

            $current->forceFill([
                'number' => $this->formatNumber($fmt, $seq, $current->issue_date),
                'seq' => $seq,
                'year' => $year,
                // Finalising ISSUES the invoice (number, immutable, counts as revenue) →
                // status 'final' (Open). It does NOT mean "sent"; only an actual send does.
                'status' => $current->status === 'draft' ? 'final' : $current->status,
            ]);
            $current->version = $current->version + 1;
            $current->save();

            return $current;
        }));

        if ($fresh === false) {
            return response()->json(['error' => 'number_conflict'], 409);
        }

        return response()->json(['invoice' => $fresh]);
    }

    /**
     * The next gapless per-year sequence number (never below the floor). Counts
     * SOFT-DELETED numbered invoices too (withTrashed) so a trashed invoice's
     * number can NEVER be reused — GoBD forbids reusing a burned number even
     * across a soft delete. The partial unique index deliberately excludes
     * trashed rows (a hard-deleted row leaves no trace), so this numbering path
     * is the authoritative reuse guard. Locks the year's numbered rows to
     * serialise concurrent finalisations.
     */
    private function nextSeqForYear(int $year, int $floor): int
    {
        $seqs = Invoice::withTrashed()->where('year', $year)->whereNotNull('seq')->lockForUpdate()->pluck('seq');
        $maxSeq = 0;
        foreach ($seqs as $s) {
            if (is_numeric($s)) {
                $maxSeq = max($maxSeq, (int) $s);
            }
        }

        return max($floor, $maxSeq + 1);
    }

    /**
     * Run a numbering transaction, retrying on the unique-number constraint. The
     * per-year lock only covers rows that already match (FOR UPDATE takes no
     * predicate/gap lock at READ COMMITTED), so the FIRST invoice of a new year
     * can still race two concurrent finalisations onto the same number; the
     * partial unique index catches the loser. Rather than surfacing that as a raw
     * 500, retry a bounded number of times (each attempt re-reads max(seq)+1),
     * then return false so the caller responds 409 (retriable).
     *
     * @param  \Closure(): Invoice  $fn
     */
    private function withNumberRetry(\Closure $fn): Invoice|false
    {
        for ($attempt = 0; $attempt <= 4; $attempt++) {
            try {
                return $fn();
            } catch (QueryException $e) {
                if (! $this->isUniqueNumberViolation($e)) {
                    throw $e;
                }
            }
        }

        return false;
    }

    /** Whether a QueryException is a unique-constraint violation (pgsql 23505 / sqlite 23000). */
    private function isUniqueNumberViolation(QueryException $e): bool
    {
        $sqlState = is_string($e->getCode()) ? $e->getCode() : '';

        return in_array($sqlState, ['23505', '23000'], true);
    }

    /**
     * Cancel a finalized invoice with a credit note (Storno / Gutschrift). Creates
     * a NEW invoice with type='credit_note', cancels_invoice_id=original, the same
     * customer/partner, the original lines with NEGATED amounts and the same
     * discount terms — so it exactly reverses the original's net/VAT/gross. The
     * credit note is a real numbered document: it runs the SAME GoBD numbering path
     * (locked per-year max(seq)+1) so it takes its own slot in the sequence.
     *
     * The original invoice is NEVER edited or deleted (GoBD immutability); its
     * "cancelled" state is DERIVED (a credit note referencing it exists). Only a
     * finalized (sent|paid or numbered), non-credit-note, not-already-cancelled
     * invoice can be cancelled. Owner-scoped via the route-model binding + gate;
     * type/cancels_invoice_id/number/seq/year are server-set via forceFill.
     */
    public function stornoInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        if ($invoice->type === 'credit_note') {
            return response()->json(['error' => 'already_credit_note'], 422);
        }
        $finalized = in_array($invoice->status, ['sent', 'paid'], true)
            || (is_string($invoice->number) && $invoice->number !== '');
        if (! $finalized) {
            return response()->json(['error' => 'not_finalized'], 422);
        }
        if (Invoice::query()->where('cancels_invoice_id', $invoice->id)->exists()) {
            return response()->json(['error' => 'already_cancelled'], 422);
        }

        // Negate the line amounts (net = qty * unitPrice → flip unitPrice sign).
        $lines = [];
        foreach (is_array($invoice->lines) ? $invoice->lines : [] as $l) {
            if (! is_array($l)) {
                continue;
            }
            $up = is_numeric($l['unitPrice'] ?? null) ? -(float) $l['unitPrice'] : 0.0;
            $lines[] = array_merge($l, ['unitPrice' => $up]);
        }

        $settings = UserSetting::for($uid);
        $fmt = is_string($settings->invoice_number_format) ? $settings->invoice_number_format : null;
        $floor = max(1, (int) $settings->invoice_next_number);
        $issueDate = Carbon::today();

        $credit = $this->withNumberRetry(fn (): Invoice => DB::transaction(function () use ($invoice, $lines, $fmt, $floor, $issueDate): Invoice {
            $year = (int) $issueDate->format('Y');
            $seq = $this->nextSeqForYear($year, $floor);

            $credit = Invoice::create([
                'status' => 'sent',
                'issue_date' => $issueDate,
                'currency' => $invoice->currency,
                'imported' => false,
                'customer' => is_array($invoice->customer) ? $invoice->customer : null,
                'partner_id' => $invoice->partner_id,
                'lines' => $lines,
                'note' => $invoice->note,
                // A credit note reverses the original including its discount, so it
                // carries the same discount terms over the negated lines.
                'discount_type' => $invoice->discount_type,
                'discount_value' => $invoice->discount_value,
            ]);

            // Money columns = the exact reverse of the original (already discounted).
            $credit->forceFill([
                'type' => 'credit_note',
                'cancels_invoice_id' => $invoice->id,
                'number' => $this->formatNumber($fmt, $seq, $issueDate),
                'seq' => $seq,
                'year' => $year,
                'gross' => is_numeric($invoice->gross) ? -(float) $invoice->gross : null,
                'net' => is_numeric($invoice->net) ? -(float) $invoice->net : null,
                'vat' => is_numeric($invoice->vat) ? -(float) $invoice->vat : null,
            ]);
            $credit->version = $credit->version + 1;
            $credit->save();

            return $credit;
        }));

        if ($credit === false) {
            return response()->json(['error' => 'number_conflict'], 409);
        }

        AuditLog::record('invoice.storno', $credit, ['cancels' => $invoice->id]);

        return response()->json(['invoice' => $credit], 201);
    }

    /**
     * Email a finalized invoice's stored PDF to the customer. Owner-scoped (route
     * model binding sits behind the owner global scope + module:finance gate).
     *
     * Only finalized invoices (status sent|paid OR already numbered) may be sent.
     * The recipient is the validated `to` field or the customer snapshot email
     * (422 if neither). The stored PDF (server-owned pdf_path) is attached (422 if
     * missing). Refuses (422) when SMTP is not configured (mirrors the
     * ChannelNotifier::mailTo gate). Stamps sent_at via forceFill/saveQuietly so it
     * does NOT bump the optimistic `version`, then writes a secret-free audit row.
     */
    public function emailInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['to' => ['nullable', 'email:rfc']]);

        $finalized = in_array($invoice->status, ['sent', 'paid'], true)
            || (is_string($invoice->number) && $invoice->number !== '');
        if (! $finalized) {
            return response()->json(['error' => 'not_finalized'], 422);
        }

        $path = $this->safeBlobPath($invoice->pdf_path);
        if ($path === null || ! $this->fs()->exists($path)) {
            return response()->json(['error' => 'no_pdf'], 422);
        }

        $to = $request->filled('to') ? $request->string('to')->value() : $this->customerEmail($invoice);
        if ($to === null || $to === '') {
            return response()->json(['error' => 'no_recipient'], 422);
        }

        // Invoices go out over the user's OWN company SMTP (settings.company),
        // deliberately independent of the workspace notification SMTP.
        $mailer = $this->companyMailer($uid);
        if ($mailer === null) {
            return response()->json(['error' => 'no_smtp'], 422);
        }

        try {
            Mail::mailer($mailer)->to($to)->send(new InvoiceMail($invoice));
        } finally {
            $this->forgetCompanyMailer();
        }

        $sentAt = Carbon::now();
        $invoice->forceFill(['sent_at' => $sentAt])->saveQuietly();
        // Secret-free: the recipient domain only, never the full address.
        AuditLog::record('invoice.emailed', $invoice, ['to_domain' => Str::after($to, '@')]);

        return response()->json(['ok' => true, 'sent_at' => $sentAt->toIso8601String()]);
    }

    /**
     * Send a customer-facing payment reminder (Mahnung) for an OVERDUE invoice.
     * Distinct from the owner-facing `invoices:remind` command — this is a manual,
     * customer-directed dunning email over the user's OWN company SMTP.
     *
     * Only overdue invoices (status='sent' AND due_date < today) with a recipient +
     * stored PDF may be dunned. The reminder level (Mahnstufe) reuses reminder_count
     * (incremented) and reminded_at is stamped via forceFill/saveQuietly so it does
     * NOT bump the optimistic `version`. 422 codes mirror emailInvoice
     * (not_overdue / no_pdf / no_recipient / no_smtp). Writes a secret-free audit row.
     */
    public function dunInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['to' => ['nullable', 'email:rfc']]);

        $overdue = $invoice->status === 'sent'
            && $invoice->due_date instanceof Carbon
            && $invoice->due_date->lt(Carbon::today());
        if (! $overdue) {
            return response()->json(['error' => 'not_overdue'], 422);
        }

        $path = $this->safeBlobPath($invoice->pdf_path);
        if ($path === null || ! $this->fs()->exists($path)) {
            return response()->json(['error' => 'no_pdf'], 422);
        }

        $to = $request->filled('to') ? $request->string('to')->value() : $this->customerEmail($invoice);
        if ($to === null || $to === '') {
            return response()->json(['error' => 'no_recipient'], 422);
        }

        $mailer = $this->companyMailer($uid);
        if ($mailer === null) {
            return response()->json(['error' => 'no_smtp'], 422);
        }

        $level = (int) $invoice->reminder_count + 1;
        try {
            Mail::mailer($mailer)->to($to)->send(new InvoiceReminderMail($invoice, $level));
        } finally {
            $this->forgetCompanyMailer();
        }

        $now = Carbon::now();
        $invoice->forceFill(['reminded_at' => $now, 'reminder_count' => $level])->saveQuietly();
        AuditLog::record('invoice.dunned', $invoice, ['to_domain' => Str::after($to, '@'), 'level' => $level]);

        return response()->json(['ok' => true, 'level' => $level, 'reminded_at' => $now->toIso8601String()]);
    }

    /**
     * The recipient for a finalized invoice: prefer the customer snapshot's
     * dedicated invoice email (Rechnungs-E-Mail) if it is a valid-looking address,
     * else fall back to the general customer email.
     */
    private function customerEmail(Invoice $invoice): ?string
    {
        $customer = is_array($invoice->customer) ? $invoice->customer : [];

        return $this->validEmail($customer['invoiceEmail'] ?? null)
            ?? $this->validEmail($customer['email'] ?? null);
    }

    /** A trimmed address if it looks like an email, else null. */
    private function validEmail(mixed $email): ?string
    {
        return is_string($email) && str_contains($email, '@') && trim($email) !== '' ? trim($email) : null;
    }

    /**
     * Configure a runtime SMTP mailer from the user's OWN company SMTP settings
     * and return its name, or null if company SMTP isn't fully configured.
     * Deliberately separate from the AppSettings notification SMTP so invoices go
     * out under the business's own mail identity.
     */
    private function companyMailer(int $userId): ?string
    {
        $s = UserSetting::for($userId);
        $host = is_string($s->company_smtp_host) ? trim($s->company_smtp_host) : '';
        $from = is_string($s->company_smtp_from_address) ? trim($s->company_smtp_from_address) : '';
        if (! $s->company_smtp_enabled || $host === '' || $from === '') {
            return null;
        }

        // Egress-guard the per-user SMTP host (mirrors ChannelNotifier::mailTo,
        // ntfy/webhook/backup): refuse the cloud-metadata surface (169.254.169.254)
        // and, in hardened mode, private/loopback ranges — a blind SMTP-SSRF /
        // internal port-probe primitive otherwise reachable by any finance user.
        // Fails closed (→ null → 'no_smtp' 422 at the call site).
        if (! OutboundUrl::hostAllowed($host)) {
            return null;
        }

        $enc = is_string($s->company_smtp_encryption) && $s->company_smtp_encryption !== ''
            ? $s->company_smtp_encryption
            : null;
        config([
            'mail.mailers.company_smtp' => [
                'transport' => 'smtp',
                'host' => $host,
                'port' => $s->company_smtp_port ?: 587,
                'encryption' => $enc,
                'username' => is_string($s->company_smtp_username) && $s->company_smtp_username !== '' ? $s->company_smtp_username : null,
                'password' => is_string($s->company_smtp_password) && $s->company_smtp_password !== '' ? $s->company_smtp_password : null,
                'timeout' => 15,
            ],
            'mail.from.company_smtp' => [
                'address' => $from,
                'name' => is_string($s->company_smtp_from_name) && $s->company_smtp_from_name !== '' ? $s->company_smtp_from_name : ($s->company_name ?: $from),
            ],
        ]);

        return 'company_smtp';
    }

    /**
     * Tear the per-user runtime company mailer back out of the merged config after
     * a send (mirrors MailSender's `finally`). Under classic FPM this just keeps the
     * SMTP password from lingering in-process; under Octane's persistent worker it is
     * REQUIRED so one finance user's SMTP creds never survive into the next request.
     */
    private function forgetCompanyMailer(): void
    {
        config(['mail.mailers.company_smtp' => null, 'mail.from.company_smtp' => null]);
    }

    /** Render a number template (YYYY/YY/MM/DD + a run of N's → zero-padded seq). */
    private function formatNumber(?string $fmt, int $seq, ?Carbon $issueDate): string
    {
        $d = $issueDate ?? Carbon::now();
        $out = ($fmt !== null && $fmt !== '') ? $fmt : 'YYYY-NNNN';
        $out = str_replace(
            ['YYYY', 'YY', 'MM', 'DD'],
            [$d->format('Y'), $d->format('y'), $d->format('m'), $d->format('d')],
            $out,
        );
        $out = preg_replace_callback('/N+/', static fn (array $m): string => str_pad((string) $seq, strlen((string) $m[0]), '0', STR_PAD_LEFT), $out);

        return is_string($out) && $out !== '' ? $out : (string) $seq;
    }

    public function destroyInvoice(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreInvoice(int $id): JsonResponse
    {
        $invoice = Invoice::onlyTrashed()->findOrFail($id);

        // Restoring re-enters the row into the partial unique index
        // (user_id, year, number) WHERE number IS NOT NULL AND deleted_at IS NULL.
        // If the number was reused by a live invoice while this one was trashed
        // (legacy data from before the withTrashed() numbering fix), the restore
        // would violate the index and 500. Refuse gracefully with a 422 instead.
        if (is_string($invoice->number) && $invoice->number !== '' && $invoice->year !== null
            && Invoice::query()->where('year', $invoice->year)->where('number', $invoice->number)->exists()) {
            return response()->json(['error' => 'number_conflict'], 422);
        }

        $invoice->restore();

        return response()->json(['invoice' => $invoice]);
    }

    public function forceDeleteInvoice(int $id): JsonResponse
    {
        $invoice = Invoice::withTrashed()->findOrFail($id);
        DB::transaction(function () use ($invoice): void {
            foreach ($this->invoiceBlobPaths($invoice) as $path) {
                $this->fs()->delete($path);
            }
            $invoice->forceDelete();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRules(Request $request, bool $create): array
    {
        $uid = (int) $this->requireUser($request)->id;

        return [
            'number' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'final', 'sent', 'paid'])],
            'type' => ['sometimes', 'string', Rule::in(['invoice', 'credit_note'])],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'vat_rate' => ['nullable', 'numeric'],
            'gross' => ['nullable', 'numeric'],
            'net' => ['nullable', 'numeric'],
            'vat' => ['nullable', 'numeric'],
            'discount_type' => ['nullable', 'string', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'skonto_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'skonto_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'imported' => ['sometimes', 'boolean'],
            'paid_at' => ['nullable', 'date'],
            'payment_account' => ['nullable', 'string', 'max:200'],
            'partner_id' => ['nullable', 'integer', Rule::exists('finance_partners', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'customer' => ['nullable', 'array'],
            'lines' => ['nullable', 'array', 'max:1000'],
            'note' => ['nullable', 'string', 'max:100000'],
            'versions' => ['nullable', 'array', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePatch(Request $request, bool $create, ?Invoice $existing = null): array
    {
        $patch = [];
        if ($create || $request->has('payment_account')) {
            $patch['payment_account'] = $request->filled('payment_account') ? $request->string('payment_account')->value() : null;
        }
        // GoBD: `number` is server-authoritative — finalizeInvoice owns the gapless
        // per-year sequence. Accept a client-supplied number ONLY when CREATING an
        // imported (historical) invoice; never on a normal create or on any update,
        // so the finalize path stays the sole numbering authority.
        if ($create && $request->boolean('imported')) {
            $patch['number'] = $request->filled('number') ? $request->string('number')->value() : null;
        }
        if ($create || $request->has('status')) {
            $patch['status'] = $request->filled('status') ? $request->string('status')->value() : 'draft';
        }
        if ($create || $request->has('type')) {
            // cancels_invoice_id is never mass-assigned; the Storno action sets both.
            $patch['type'] = $request->filled('type') ? $request->string('type')->value() : 'invoice';
        }
        if ($create || $request->has('discount_type')) {
            $patch['discount_type'] = $request->filled('discount_type') ? $request->string('discount_type')->value() : null;
        }
        foreach (['discount_value', 'skonto_percent'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->float($field) : null;
            }
        }
        if ($create || $request->has('skonto_days')) {
            $patch['skonto_days'] = $request->filled('skonto_days') ? $request->integer('skonto_days') : null;
        }
        if ($create || $request->has('currency')) {
            $patch['currency'] = $request->filled('currency') ? $request->string('currency')->value() : 'EUR';
        }
        foreach (['issue_date', 'due_date', 'paid_at'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->date($field) : null;
            }
        }
        // Populate `year` on create from the issue date. Imported invoices arrive
        // with a number + issue_date but no year; without this the (user_id, year,
        // number) unique index does NOT catch duplicate imports, because Postgres
        // treats each NULL year as distinct — so the same invoice could be imported
        // twice. Finalisation may still overwrite year for self-issued invoices.
        if ($create && ($patch['issue_date'] ?? null) instanceof Carbon) {
            $patch['year'] = (int) $patch['issue_date']->format('Y');
        }
        foreach (['vat_rate', 'gross', 'net', 'vat'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->float($field) : null;
            }
        }
        if ($create || $request->has('imported')) {
            $patch['imported'] = $request->boolean('imported');
        }
        if ($create || $request->has('partner_id')) {
            $patch['partner_id'] = $request->filled('partner_id') ? $request->integer('partner_id') : null;
        }
        if ($create || $request->has('note')) {
            $patch['note'] = $request->filled('note') ? $request->string('note')->value() : null;
        }
        foreach (['customer', 'lines'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->array($field) : null;
            }
        }
        if ($create || $request->has('versions')) {
            // SECURITY (mirrors sanitizeReceipts): the per-version `pdf` blob path is
            // SERVER-owned — only uploadInvoicePdf ever assigns it (matched by seq).
            // A client-supplied `pdf` is always dropped + restored from the stored row
            // by seq, so a version can't point at another invoice/user's blob (an
            // arbitrary-file read via invoicePdf and a destructive delete via
            // forceDeleteInvoice on the shared, non-per-user `invoices/` namespace).
            $incoming = $request->filled('versions') ? $request->array('versions') : [];
            $patch['versions'] = $this->sanitizeVersions($incoming, $create ? null : $existing) ?: null;
        }

        return $patch;
    }

    /**
     * Merge client version entries against the stored invoice: a version's `pdf`
     * blob path is SERVER-owned (only ever set by uploadInvoicePdf, matched by
     * seq). Any client-supplied `pdf` is dropped and restored from the stored row
     * by seq; all other version metadata (seq/label/reason/…) passes through.
     * Prevents pointing a version at a blob outside this invoice.
     *
     * @param  array<array-key, mixed>  $incoming
     * @return list<array<array-key, mixed>>
     */
    private function sanitizeVersions(array $incoming, ?Invoice $existing): array
    {
        $storedPdfBySeq = [];
        foreach (is_array($existing?->versions) ? $existing->versions : [] as $v) {
            if (is_array($v) && isset($v['seq']) && is_numeric($v['seq'])) {
                $safe = $this->safeBlobPath($v['pdf'] ?? null);
                if ($safe !== null) {
                    $storedPdfBySeq[(int) $v['seq']] = $safe;
                }
            }
        }
        $out = [];
        foreach ($incoming as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            unset($entry['pdf']); // never from the client
            if (isset($entry['seq']) && is_numeric($entry['seq'])) {
                $seq = (int) $entry['seq'];
                if (isset($storedPdfBySeq[$seq])) {
                    $entry['pdf'] = $storedPdfBySeq[$seq]; // server-owned path
                }
            }
            $out[] = $entry;
        }

        return $out;
    }

    // ---- Invoice PDF (plaintext blob on disk) ----

    public function uploadInvoicePdf(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate([
            // Invoice document is always a PDF. Extension allowlist (no svg/html) is
            // defense-in-depth on top of the sandbox CSP applied when the blob is served.
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.$this->maxUploadKb()],
            'version_seq' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }
        $versionSeq = $request->filled('version_seq') ? $request->integer('version_seq') : null;

        $path = 'invoices/'.Str::uuid()->toString();
        $this->fs()->putFileAs('invoices', $upload, basename($path));

        try {
            $fresh = DB::transaction(function () use ($invoice, $path, $versionSeq): Invoice {
                $current = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

                // Per-version PDF: attach the uploaded document to the matching versions[]
                // entry (GoBD correction trail — each version keeps its own PDF). The shared
                // pdf_path is left untouched so historical versions never clobber each other.
                if ($versionSeq !== null) {
                    $versions = is_array($current->versions) ? $current->versions : [];
                    $matched = false;
                    foreach ($versions as &$entry) {
                        if (is_array($entry) && isset($entry['seq']) && is_numeric($entry['seq']) && (int) $entry['seq'] === $versionSeq) {
                            $old = $this->safeBlobPath($entry['pdf'] ?? null);
                            if ($old !== null && $old !== $path) {
                                $this->fs()->delete($old);
                            }
                            $entry['pdf'] = $path;
                            $matched = true;
                            break;
                        }
                    }
                    unset($entry);
                    if (! $matched) {
                        // No such version yet → nothing to attach; drop the orphan blob.
                        $this->fs()->delete($path);

                        return $current;
                    }
                    $current->versions = $versions;
                    $current->save();

                    return $current;
                }

                // No version_seq → the invoice's current/original PDF (unchanged behaviour).
                if (is_string($current->pdf_path) && $current->pdf_path !== '' && $current->pdf_path !== $path) {
                    $this->fs()->delete($current->pdf_path);
                }
                $current->forceFill(['pdf_path' => $path]);
                $current->save();

                return $current;
            });
        } catch (\Throwable $e) {
            // The blob was written before the row lock; if the txn failed (e.g. the
            // invoice was deleted between binding and the lock → 404) unlink it so it
            // is not orphaned on the shared `invoices/` disk with no sweep.
            $this->fs()->delete($path);

            throw $e;
        }

        return response()->json(['invoice' => $fresh]);
    }

    public function invoicePdf(Request $request, Invoice $invoice): StreamedResponse
    {
        $versionSeq = $request->filled('version_seq') ? $request->integer('version_seq') : null;

        // A version_seq streams that version's own stored PDF; otherwise the invoice's
        // current/original PDF. The version path comes from the (client-writable)
        // versions[] json, so it is prefix-guarded via safeBlobPath — never a raw path.
        if ($versionSeq !== null) {
            $path = $this->safeBlobPath($this->versionPdfPath($invoice, $versionSeq));
            $label = $this->versionLabel($invoice, $versionSeq) ?? ($invoice->number ?? 'invoice');
        } else {
            $path = $this->safeBlobPath($invoice->pdf_path);
            $label = $invoice->number ?? 'invoice';
        }
        if ($path === null || ! $this->fs()->exists($path)) {
            abort(404);
        }
        $filename = $this->safeName($label.'.pdf');

        return $this->fs()->response($path, $filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    /** The stored blob path of a version entry's own PDF (or null if none / no such version). */
    private function versionPdfPath(Invoice $invoice, int $seq): ?string
    {
        foreach (is_array($invoice->versions) ? $invoice->versions : [] as $entry) {
            if (is_array($entry) && isset($entry['seq']) && is_numeric($entry['seq']) && (int) $entry['seq'] === $seq) {
                $pdf = $entry['pdf'] ?? null;

                return is_string($pdf) ? $pdf : null;
            }
        }

        return null;
    }

    /** The display label of a version entry (for the download filename). */
    private function versionLabel(Invoice $invoice, int $seq): ?string
    {
        foreach (is_array($invoice->versions) ? $invoice->versions : [] as $entry) {
            if (is_array($entry) && isset($entry['seq']) && is_numeric($entry['seq']) && (int) $entry['seq'] === $seq) {
                $label = $entry['label'] ?? null;

                return is_string($label) && $label !== '' ? $label : null;
            }
        }

        return null;
    }

    /**
     * Every stored PDF blob path owned by an invoice (pdf_path + per-version pdfs).
     *
     * @return list<string>
     */
    private function invoiceBlobPaths(Invoice $invoice): array
    {
        $paths = [];
        $main = $this->safeBlobPath($invoice->pdf_path);
        if ($main !== null) {
            $paths[] = $main;
        }
        foreach (is_array($invoice->versions) ? $invoice->versions : [] as $entry) {
            $safe = $this->safeBlobPath(is_array($entry) ? ($entry['pdf'] ?? null) : null);
            if ($safe !== null) {
                $paths[] = $safe;
            }
        }

        return array_values(array_unique($paths));
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
        foreach (['vat_cat', 'sig', 'invoice_number', 'counterparty', 'counterparty_iban', 'bic', 'purpose', 'booking_text', 'eref'] as $field) {
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
    private function safeBlobPath(mixed $path): ?string
    {
        // Confine to the invoices/ prefix; reject traversal, NUL bytes and any
        // absolute path. All real blob paths are server-generated (invoices/{uuid})
        // and client-supplied paths are already dropped upstream — this is a
        // defence-in-depth guard on every stream/delete site.
        return is_string($path)
            && str_starts_with($path, 'invoices/')
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0")
            ? $path
            : null;
    }

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
            'category' => ['nullable', 'string', 'max:160'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'vat' => ['nullable', 'string', 'max:16'],
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
                'category' => $request->filled('category') ? $request->string('category')->value() : null,
                'tags' => $tags,
                'vat' => $request->filled('vat') ? $request->string('vat')->value() : null,
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
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'partner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('finance_partners', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'bank_transaction_id' => ['sometimes', 'nullable', 'integer', Rule::exists('bank_transactions', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'finance_project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('finance_projects', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $patch = [];
        foreach (['name', 'category', 'vat', 'note', 'partner_id', 'bank_transaction_id', 'finance_project_id', 'tags'] as $f) {
            if ($request->has($f)) {
                $patch[$f] = $request->input($f);
            }
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
