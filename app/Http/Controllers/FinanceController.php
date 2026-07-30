<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\FinanceCategory;
use App\Models\FinancePartner;
use App\Models\FinanceProject;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\UserSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
        return view('invoices.index', $this->snapshot());
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
            'logo' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:100000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'max:320'],
            'phone' => ['nullable', 'string', 'max:100'],
            'vat_id' => ['nullable', 'string', 'max:64'],
            'contacts' => ['nullable', 'array', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerPatch(Request $request, bool $create): array
    {
        $patch = [];
        foreach (['name', 'category', 'kind', 'url', 'logo', 'note', 'address', 'email', 'phone', 'vat_id'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->string($field)->value() : ($field === 'name' ? '' : null);
            }
        }
        if ($create || $request->has('contacts')) {
            $patch['contacts'] = $request->filled('contacts') ? $request->array('contacts') : null;
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
        foreach (['type', 'name', 'url', 'icon', 'iban', 'bic', 'bank', 'account_no', 'card_number', 'card_network', 'card_expiry', 'paypal_email'] as $field) {
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

    public function storeCategory(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('finance_categories', 'name')->where('user_id', $uid)],
        ]);
        $category = DB::transaction(fn (): FinanceCategory => FinanceCategory::create(['name' => $request->string('name')->value()]));

        return response()->json(['category' => $category], 201);
    }

    public function updateCategory(Request $request, FinanceCategory $category): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('finance_categories', 'name')->where('user_id', $uid)->ignore($category->id)],
        ]);
        $category->update(['name' => $request->string('name')->value()]);

        return response()->json(['category' => $category]);
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
        $expected = $request->has('version') ? $request->integer('version') : null;
        $result = $this->optimistic(Invoice::class, $invoice->id, $this->invoicePatch($request, false), $expected);

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

        $fresh = DB::transaction(function () use ($invoice, $fmt, $floor): Invoice {
            $current = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (is_string($current->number) && $current->number !== '') {
                return $current; // already numbered → idempotent
            }

            $year = $current->year ?? ($current->issue_date instanceof Carbon ? (int) $current->issue_date->format('Y') : (int) Carbon::now()->format('Y'));

            // Lock the user's numbered rows for this year to serialise concurrent finalises.
            $seqs = Invoice::query()->where('year', $year)->whereNotNull('seq')->lockForUpdate()->pluck('seq');
            $maxSeq = 0;
            foreach ($seqs as $s) {
                if (is_numeric($s)) {
                    $maxSeq = max($maxSeq, (int) $s);
                }
            }
            $seq = max($floor, $maxSeq + 1);

            $current->forceFill([
                'number' => $this->formatNumber($fmt, $seq, $current->issue_date),
                'seq' => $seq,
                'year' => $year,
                'status' => $current->status === 'draft' ? 'sent' : $current->status,
            ]);
            $current->version = $current->version + 1;
            $current->save();

            return $current;
        });

        return response()->json(['invoice' => $fresh]);
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
        $invoice->restore();

        return response()->json(['invoice' => $invoice]);
    }

    public function forceDeleteInvoice(int $id): JsonResponse
    {
        $invoice = Invoice::withTrashed()->findOrFail($id);
        DB::transaction(function () use ($invoice): void {
            if (is_string($invoice->pdf_path) && $invoice->pdf_path !== '') {
                $this->fs()->delete($invoice->pdf_path);
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
            'status' => ['sometimes', 'string', Rule::in(['draft', 'sent', 'paid'])],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'vat_rate' => ['nullable', 'numeric'],
            'gross' => ['nullable', 'numeric'],
            'net' => ['nullable', 'numeric'],
            'vat' => ['nullable', 'numeric'],
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
    private function invoicePatch(Request $request, bool $create): array
    {
        $patch = [];
        foreach (['number', 'payment_account'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->string($field)->value() : null;
            }
        }
        if ($create || $request->has('status')) {
            $patch['status'] = $request->filled('status') ? $request->string('status')->value() : 'draft';
        }
        if ($create || $request->has('currency')) {
            $patch['currency'] = $request->filled('currency') ? $request->string('currency')->value() : 'EUR';
        }
        foreach (['issue_date', 'due_date', 'paid_at'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->date($field) : null;
            }
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
        foreach (['customer', 'lines', 'versions'] as $field) {
            if ($create || $request->has($field)) {
                $patch[$field] = $request->filled($field) ? $request->array($field) : null;
            }
        }

        return $patch;
    }

    // ---- Invoice PDF (plaintext blob on disk) ----

    public function uploadInvoicePdf(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.$this->maxUploadKb()]]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        $path = 'invoices/'.Str::uuid()->toString();
        $this->fs()->putFileAs('invoices', $upload, basename($path));

        $fresh = DB::transaction(function () use ($invoice, $path): Invoice {
            $current = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (is_string($current->pdf_path) && $current->pdf_path !== '' && $current->pdf_path !== $path) {
                $this->fs()->delete($current->pdf_path);
            }
            $current->forceFill(['pdf_path' => $path]);
            $current->save();

            return $current;
        });

        return response()->json(['invoice' => $fresh]);
    }

    public function invoicePdf(Request $request, Invoice $invoice): StreamedResponse
    {
        if (! is_string($invoice->pdf_path) || $invoice->pdf_path === '' || ! $this->fs()->exists($invoice->pdf_path)) {
            abort(404);
        }
        $filename = $this->safeName(($invoice->number ?? 'invoice').'.pdf');

        return $this->fs()->response($invoice->pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
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
        return is_string($path) && str_starts_with($path, 'invoices/') && ! str_contains($path, '..')
            ? $path
            : null;
    }

    // ---- Receipts (files embedded in a transaction's receipts[] json) ----

    public function attachReceipt(Request $request, BankTransaction $transaction): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.$this->maxUploadKb()],
            'name' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', 'string', 'max:24'],
            'category' => ['nullable', 'string', 'max:160'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'contact_id' => ['nullable', 'string', 'max:64'],
            'partner_id' => ['nullable', 'integer'],
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

        $fresh = DB::transaction(function () use ($transaction, $entry): BankTransaction {
            $current = BankTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $receipts = is_array($current->receipts) ? $current->receipts : [];
            $receipts[] = $entry;
            $current->receipts = $receipts;
            $current->version = $current->version + 1;
            $current->save();

            return $current;
        });

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
}
