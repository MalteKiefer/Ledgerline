<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFinanceBlobs;
use App\Http\Controllers\Concerns\OptimisticUpdates;
use App\Models\FinanceProduct;
use App\Models\FinanceQuote;
use App\Models\UserSetting;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyQuoteInvoiceSource;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quotes (Angebote): what a job would cost, before it is a job.
 *
 * Two rules shape everything here. A quote is editable only while it is a draft
 * — once it has a number and has left the house, the customer holds a document
 * that says something, and changing what it says under the same number would
 * make the two disagree. And its lines share the invoice line shape, so
 * accepting a quote produces an invoice by copying rather than translating.
 */
class FinanceQuoteController extends Controller
{
    use HandlesFinanceBlobs;
    use OptimisticUpdates;

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $creating): array
    {
        return [
            'title' => ['nullable', 'string', 'max:300'],
            'partner_id' => ['nullable', 'integer', Rule::exists('finance_partners', 'id')->where('user_id', request()->user()?->id)],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string', 'max:300'],
            'customer.attn' => ['nullable', 'string', 'max:300'],
            'customer.address' => ['nullable', 'string', 'max:2000'],
            'customer.email' => ['nullable', 'string', 'max:320'],
            'customer.vatId' => ['nullable', 'string', 'max:64'],
            'issue_date' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:8'],
            'lines' => ['nullable', 'array', 'max:200'],
            'lines.*.desc' => ['nullable', 'string', 'max:2000'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:-100000', 'max:100000'],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
            'lines.*.unitPrice' => ['nullable', 'numeric', 'min:-1000000', 'max:1000000'],
            'lines.*.vatRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.kind' => ['nullable', Rule::in(['service', 'hardware'])],
            'lines.*.productId' => ['nullable', 'integer', Rule::exists('finance_products', 'id')->where('user_id', request()->user()?->id)],
            'discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'numeric', 'min:-1000000', 'max:1000000'],
            'net' => ['nullable', 'numeric'],
            'vat' => ['nullable', 'numeric'],
            'gross' => ['nullable', 'numeric'],
            'intro_text' => ['nullable', 'string', 'max:5000'],
            'outro_text' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'version' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function patch(Request $request): array
    {
        $patch = [];
        foreach (['title', 'currency', 'discount_type', 'intro_text', 'outro_text', 'note'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $patch[$field] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }
        foreach (['issue_date', 'valid_until'] as $field) {
            if ($request->has($field)) {
                $raw = $request->input($field);
                $patch[$field] = is_string($raw) && $raw !== '' ? $raw : null;
            }
        }
        foreach (['discount_value', 'net', 'vat', 'gross'] as $field) {
            if ($request->has($field)) {
                $raw = $request->input($field);
                $patch[$field] = is_numeric($raw) ? (float) $raw : null;
            }
        }
        if ($request->has('partner_id')) {
            $raw = $request->input('partner_id');
            $patch['partner_id'] = is_numeric($raw) ? (int) $raw : null;
        }
        if ($request->has('customer')) {
            $c = $request->input('customer');
            $patch['customer'] = is_array($c) ? $c : null;
        }
        if ($request->has('lines')) {
            $patch['lines'] = $this->sanitizeLines($request->input('lines'));
        }

        return $patch;
    }

    /**
     * Keep only the fields a line may carry.
     *
     * A quote line is copied verbatim into an invoice later, so anything a
     * client smuggles in here would travel with it. The `productId` is kept
     * because it is what lets an accepted quote move stock; it is validated
     * against the caller's own catalogue by the rules above.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $desc = $line['desc'] ?? null;
            $unit = $line['unit'] ?? null;
            $kind = $line['kind'] ?? null;
            $productId = $line['productId'] ?? null;
            $out[] = [
                'desc' => is_string($desc) ? $desc : '',
                'qty' => is_numeric($line['qty'] ?? null) ? (float) $line['qty'] : 1,
                'unit' => is_string($unit) && $unit !== '' ? $unit : null,
                'unitPrice' => is_numeric($line['unitPrice'] ?? null) ? (float) $line['unitPrice'] : 0,
                'vatRate' => is_numeric($line['vatRate'] ?? null) ? (float) $line['vatRate'] : 0,
                'kind' => in_array($kind, ['service', 'hardware'], true) ? $kind : null,
                'productId' => is_numeric($productId) ? (int) $productId : null,
            ];
        }

        return $out;
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->rules(true));

        $quote = new FinanceQuote;
        $quote->fill($this->patch($request));
        if ($quote->issue_date === null) {
            $quote->issue_date = Carbon::today();
        }
        if ($quote->valid_until === null) {
            // Default the validity from the company profile, so the usual case
            // needs no thought and an unusual one is still a free field.
            $days = UserSetting::for((int) $this->requireUser($request)->id)->quote_valid_days;
            $quote->valid_until = Carbon::today()->addDays(is_numeric($days) && (int) $days > 0 ? (int) $days : 30);
        }
        $quote->save();

        return response()->json(['quote' => $quote->fresh()], 201);
    }

    public function update(Request $request, FinanceQuote $quote): JsonResponse
    {
        $request->validate($this->rules(false));

        // A sent quote is a statement someone else is holding. Editing it under
        // the same number would make the two disagree; a changed price is a new
        // quote, which is why there is a duplicate action instead.
        if ($quote->isLocked()) {
            return response()->json(['error' => 'quote_locked'], 422);
        }

        $id = (int) $quote->id;
        $result = $this->optimistic(
            FinanceQuote::class,
            $id,
            $this->patch($request),
            $request->has('version') ? $request->integer('version') : null,
        );

        return $this->optimisticJson($result, FinanceQuote::class, $id, 'quote');
    }

    /**
     * Give the quote its number and mark it as out.
     *
     * The number comes from the same template machinery as an invoice number, so
     * a configured format behaves identically for both. Unlike an invoice this
     * is not a GoBD-gapless sequence — but binned rows still count, because a
     * customer holding AN-2026-0007 must never receive a different one.
     */
    public function send(Request $request, FinanceQuote $quote): JsonResponse
    {
        if ($quote->number !== null && $quote->number !== '') {
            // Already numbered: sending twice is a mail action, not a numbering
            // one. Idempotent by design.
            if ($quote->status === 'draft') {
                $quote->forceFill(['status' => 'sent', 'sent_at' => Carbon::now()])->save();
            }

            return response()->json(['quote' => $quote->fresh()]);
        }

        $settings = UserSetting::for((int) $this->requireUser($request)->id);
        $fmt = is_string($settings->quote_number_format) && $settings->quote_number_format !== ''
            ? $settings->quote_number_format
            : 'AN-YYYY-NNNN';
        $floorRaw = $settings->quote_next_number;
        $floor = is_numeric($floorRaw) ? (int) $floorRaw : 1;

        $year = (int) ($quote->issue_date?->format('Y') ?? Carbon::now()->format('Y'));

        // Retry on the unique index: the per-year lock takes no gap lock, so the
        // first quote of a new year can still race two concurrent sends onto the
        // same number. Each attempt re-reads max(seq)+1.
        for ($attempt = 0; $attempt <= 4; $attempt++) {
            try {
                $fresh = DB::transaction(function () use ($quote, $year, $fmt, $floor): FinanceQuote {
                    $rows = FinanceQuote::withTrashed()->where('year', $year)->lockForUpdate()->get(['seq', 'number', 'issue_date']);
                    $maxSeq = 0;
                    foreach ($rows as $row) {
                        if (is_numeric($row->seq)) {
                            $maxSeq = max($maxSeq, (int) $row->seq);

                            continue;
                        }
                        if (is_string($row->number)) {
                            $derived = DocumentNumber::sequenceFrom($fmt, $row->number, $row->issue_date);
                            if ($derived !== null) {
                                $maxSeq = max($maxSeq, $derived);
                            }
                        }
                    }
                    $seq = max($floor, $maxSeq + 1);

                    $target = FinanceQuote::query()->lockForUpdate()->find($quote->getKey());
                    if (! $target instanceof FinanceQuote) {
                        // Deleted between reading it and locking it.
                        abort(404);
                    }
                    $target->forceFill([
                        'number' => DocumentNumber::format($fmt, $seq, $target->issue_date),
                        'seq' => $seq,
                        'year' => $year,
                        'status' => 'sent',
                        'sent_at' => Carbon::now(),
                        'version' => (int) $target->version + 1,
                    ])->save();

                    return $target;
                });

                return response()->json(['quote' => $fresh->fresh()]);
            } catch (QueryException $e) {
                $sqlState = is_string($e->getCode()) ? $e->getCode() : '';
                if (! in_array($sqlState, ['23505', '23000'], true)) {
                    throw $e;
                }
            }
        }

        // Retriable: the caller can simply send again.
        return response()->json(['error' => 'number_taken'], 409);
    }

    /** Record the customer's answer. */
    public function decide(Request $request, FinanceQuote $quote): JsonResponse
    {
        $request->validate(['decision' => ['required', Rule::in(['accepted', 'declined'])]]);
        $decision = $request->string('decision')->value();

        if ($quote->number === null || $quote->number === '') {
            // Nothing was sent, so there is nothing to answer.
            return response()->json(['error' => 'quote_not_sent'], 422);
        }

        $quote->forceFill([
            'status' => $decision,
            'accepted_at' => $decision === 'accepted' ? Carbon::now() : null,
            'declined_at' => $decision === 'declined' ? Carbon::now() : null,
            'version' => (int) $quote->version + 1,
        ])->save();

        return response()->json(['quote' => $quote->fresh()]);
    }

    /**
     * Turn a quote into a draft invoice.
     *
     * A copy, not a translation: the lines share their shape, so what the
     * customer agreed to is what gets billed, down to the discount terms. The
     * invoice starts as a draft and takes its own number when it is finalised —
     * a quote number is not an invoice number.
     *
     * Hardware does NOT move stock here. Goods leave when the invoice is
     * finalised, not when someone accepts a price.
     */
    public function convertToInvoice(Request $request, FinanceQuote $quote): JsonResponse
    {
        if ($quote->converted_finance_invoice_id !== null) {
            $existing = $this->findFinanceInvoice((int) $quote->converted_finance_invoice_id);
            if ($existing !== null) {
                // Idempotent: a second click reopens the invoice it already made
                // rather than billing the same work twice.
                return response()->json(['invoice' => $this->invoiceJson($existing), 'quote' => $quote, 'already' => true]);
            }
        }
        if ($quote->number === null || $quote->number === '') {
            return response()->json(['error' => 'quote_not_sent'], 422);
        }

        $userSettings = UserSetting::for((int) $this->requireUser($request)->id);
        $termsRaw = $userSettings->invoice_payment_terms_days;
        $terms = is_numeric($termsRaw) && (int) $termsRaw > 0 ? (int) $termsRaw : 14;

        $view = DB::transaction(function () use ($quote, $terms): InvoiceView {
            $locked = FinanceQuote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->converted_finance_invoice_id !== null) {
                $quote->forceFill($locked->getAttributes());

                return app(InvoiceRepository::class)->get(new InvoiceId((int) $locked->converted_finance_invoice_id));
            }

            $view = app(LegacyQuoteInvoiceSource::class)->convert((int) $locked->user_id, $locked, $terms);

            $locked->forceFill([
                'converted_finance_invoice_id' => $view->id->value,
                // Accepting is implied by billing it; recording it makes the
                // quote list honest without a second click.
                'status' => 'accepted',
                'accepted_at' => $locked->accepted_at ?? Carbon::now(),
                'version' => (int) $locked->version + 1,
            ])->save();
            $quote->forceFill($locked->getAttributes());

            return $view;
        });

        return response()->json(['invoice' => $this->invoiceJson($view), 'quote' => $quote->fresh()], 201);
    }

    private function findFinanceInvoice(int $invoiceId): ?InvoiceView
    {
        try {
            return app(InvoiceRepository::class)->get(new InvoiceId($invoiceId));
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function invoiceJson(InvoiceView $view): array
    {
        $snapshot = $view->snapshot;
        $customer = is_array($snapshot['customer'] ?? null) ? $snapshot['customer'] : [];
        $rawLines = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
        $lines = array_map(static function (mixed $line): array {
            $line = is_array($line) ? $line : [];

            return [
                'desc' => is_string($line['description'] ?? null) ? $line['description'] : '',
                'qty' => is_numeric($line['quantity'] ?? null) ? (float) $line['quantity'] : 0.0,
                'unitPrice' => is_int($line['unit_price_minor'] ?? null) ? $line['unit_price_minor'] / 100 : 0.0,
                'vatRate' => is_int($line['tax_rate_basis_points'] ?? null) ? $line['tax_rate_basis_points'] / 100 : 0.0,
                'unit' => is_string($line['unit'] ?? null) ? $line['unit'] : null,
                'kind' => is_string($line['kind'] ?? null) ? $line['kind'] : null,
                'productId' => is_int($line['product_id'] ?? null) ? $line['product_id'] : null,
            ];
        }, $rawLines);

        return [
            'id' => $view->id->value,
            'number' => $view->number,
            'year' => (int) $view->issueDate->format('Y'),
            'status' => $view->status === 'draft' ? 'draft' : ($view->status === 'finalized' ? 'final' : $view->status),
            'type' => 'invoice',
            'issue_date' => $view->issueDate->format('Y-m-d'),
            'due_date' => $view->dueDate->format('Y-m-d'),
            'currency' => $view->currency,
            'vat_rate' => null,
            'gross' => $this->minorDecimal($view->grossMinor),
            'net' => $this->minorDecimal($view->netMinor),
            'vat' => $this->minorDecimal($view->vatMinor),
            'imported' => false,
            'partner_id' => $view->partnerId,
            'customer' => $customer,
            'lines' => $lines,
            'note' => null,
            'paid_at' => null,
            'payment_account' => null,
            'version' => $view->version,
            'discount_type' => null,
            'discount_value' => null,
            'skonto_percent' => null,
            'skonto_days' => null,
            'pdf_path' => null,
            'created_at' => $view->createdAt->format(DATE_ATOM),
        ];
    }

    private function minorDecimal(int $minor): string
    {
        $negative = $minor < 0;
        $digits = str_pad(ltrim((string) abs($minor), '-'), 3, '0', STR_PAD_LEFT);
        $decimal = substr($digits, 0, -2).'.'.substr($digits, -2);

        return $negative ? '-'.$decimal : $decimal;
    }

    /**
     * Copy a quote into a fresh draft.
     *
     * The way to change a sent quote: the original stays as it was sent, the
     * copy is editable and takes its own number when it goes out.
     */
    public function duplicate(FinanceQuote $quote): JsonResponse
    {
        $copy = new FinanceQuote;
        $copy->fill([
            'status' => 'draft',
            'partner_id' => $quote->partner_id,
            'customer' => $quote->customer,
            'title' => $quote->title,
            'issue_date' => Carbon::today(),
            'valid_until' => Carbon::today()->addDays(30),
            'currency' => $quote->currency,
            'lines' => $quote->lines,
            'discount_type' => $quote->discount_type,
            'discount_value' => $quote->discount_value,
            'net' => $quote->net,
            'vat' => $quote->vat,
            'gross' => $quote->gross,
            'intro_text' => $quote->intro_text,
            'outro_text' => $quote->outro_text,
            'note' => $quote->note,
        ]);
        $copy->save();

        return response()->json(['quote' => $copy->fresh()], 201);
    }

    /**
     * Store the rendered quote PDF.
     *
     * The document is rasterised in the browser from the same print templates the
     * invoice uses, then kept here so it can be re-opened and mailed without
     * re-rendering — and so what the customer received stays retrievable even
     * after the quote is edited into a new version.
     */
    public function uploadPdf(Request $request, FinanceQuote $quote): JsonResponse
    {
        $request->validate([
            // Always a PDF. The extension allowlist is defence in depth on top of
            // the sandbox CSP applied when the blob is served.
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.$this->maxUploadKb()],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        $path = 'invoices/'.Str::uuid()->toString();
        $this->fs()->putFileAs('invoices', $upload, basename($path));

        $fresh = DB::transaction(function () use ($quote, $path): FinanceQuote {
            $current = FinanceQuote::query()->lockForUpdate()->find($quote->getKey());
            if (! $current instanceof FinanceQuote) {
                abort(404);
            }
            $old = $this->safeBlobPath($current->pdf_path);
            // Server-owned path, so the client can never point this at a file it
            // does not own.
            $current->forceFill(['pdf_path' => $path])->save();
            if ($old !== null && $old !== $path) {
                $this->fs()->delete($old);
            }

            return $current;
        });

        return response()->json(['quote' => $fresh->fresh()]);
    }

    /** Stream the stored PDF: inline for a preview, attachment with `?download=1`. */
    public function pdf(Request $request, FinanceQuote $quote): StreamedResponse
    {
        $path = $this->safeBlobPath($quote->pdf_path);
        if ($path === null || ! $this->fs()->exists($path)) {
            abort(404);
        }

        return $this->fs()->response($path, $this->safeName(($quote->number ?? 'quote').'.pdf'), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            // Same sandbox as every other blob this app serves.
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    public function destroy(FinanceQuote $quote): JsonResponse
    {
        $quote->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $quote = FinanceQuote::onlyTrashed()->findOrFail($id);
        $quote->restore();

        return response()->json(['quote' => $quote->fresh()]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $quote = FinanceQuote::onlyTrashed()->findOrFail($id);
        // Take the document with it: a blob nothing references is a file nobody
        // can find and nobody can delete.
        $path = $this->safeBlobPath($quote->pdf_path);
        $quote->forceDelete();
        if ($path !== null) {
            $this->fs()->delete($path);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * The catalogue as a quote line.
     *
     * One place decides how an article becomes a line, so a line added by the
     * picker and one added by a future import cannot differ in shape.
     */
    public function lineFromProduct(FinanceProduct $product): JsonResponse
    {
        return response()->json([
            'line' => [
                'desc' => trim($product->name.($product->description !== null && $product->description !== '' ? "\n".$product->description : '')),
                'qty' => 1,
                'unit' => $product->unit,
                'unitPrice' => (float) $product->price_net,
                'vatRate' => $product->vat_rate !== null ? (float) $product->vat_rate : null,
                'kind' => $product->kind,
                'productId' => (int) $product->id,
            ],
        ]);
    }
}
