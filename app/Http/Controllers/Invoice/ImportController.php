<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invoice;

use App\Enums\FileType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\TaxMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportedInvoiceRequest;
use App\Models\Customer;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Unit;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Invoicing\InvoiceNumberSequencer;
use App\Services\Invoicing\PdfInvoiceParser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfTextParser;

/**
 * Imports historical invoice PDFs: extracts and parses their data for review,
 * then stores the original PDF plus a finalised historical invoice and advances
 * the internal number counter to match.
 */
class ImportController extends Controller
{
    public function create(): View
    {
        return view('invoices.import.create');
    }

    /**
     * Store the uploaded PDF, extract and parse it, and show the review form.
     */
    public function parse(Request $request, PdfInvoiceParser $parser): View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $upload = $request->file('file');

        $text = '';
        try {
            $text = (new PdfTextParser)->parseFile($upload->getRealPath())->getText();
        } catch (\Throwable) {
            // Unreadable PDF: fall through with empty text so the user fills it in.
        }

        $path = Storage::disk(config('files.disk'))->putFile('files', $upload);
        $file = new File([
            'name' => $upload->getClientOriginalName(),
            'disk_path' => $path,
            'mime_type' => 'application/pdf',
            'type' => FileType::fromMime('application/pdf'),
            'size' => $upload->getSize(),
            'checksum' => hash_file('sha256', $upload->getRealPath()) ?: null,
            'is_encrypted' => false,
        ]);
        $file->uploaded_by = $request->user()->id;
        $file->save();

        $parsed = $parser->parse($text);

        return view('invoices.import.review', [
            'file' => $file,
            'parsed' => $parsed,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->orderBy('code')->get(),
            'matchedCustomerId' => $this->matchCustomer($parsed['customer']['name']),
            'statuses' => InvoiceStatus::options(),
            'taxModes' => TaxMode::options(),
            'currencies' => config('finance.currencies'),
        ]);
    }

    public function store(StoreImportedInvoiceRequest $request, InvoiceCalculator $calculator, InvoiceNumberSequencer $sequencer): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        $invoice = DB::transaction(function () use ($data, $userId, $calculator, $sequencer): Invoice {
            $customerId = $data['customer_mode'] === 'new'
                ? Customer::create([
                    'name' => $data['new_customer_name'],
                    'street' => $data['new_customer_street'] ?? null,
                    'postal_code' => $data['new_customer_postal_code'] ?? null,
                    'city' => $data['new_customer_city'] ?? null,
                    'vat_id' => $data['new_customer_vat_id'] ?? null,
                ])->id
                : (int) $data['customer_id'];

            $lines = array_map(fn (array $l): array => [
                'quantity' => (float) $l['quantity'],
                'unit_price_cents' => (int) round(((float) $l['unit_price']) * 100),
                'tax_rate' => (int) $l['tax_rate'],
            ], $data['lines']);

            $mode = TaxMode::from($data['tax_mode']);
            $result = $calculator->compute($lines, $mode, 0);

            $status = InvoiceStatus::from($data['status']);
            $paid = $status === InvoiceStatus::PAID;

            $invoice = new Invoice([
                'type' => InvoiceType::INVOICE->value,
                'status' => $status->value,
                'customer_id' => $customerId,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'language' => 'de',
                'currency' => $data['currency'],
                'tax_mode' => $mode->value,
                'discount_cents' => 0,
                'payment_terms_days' => 0,
            ]);
            $invoice->number = $data['number'];
            $invoice->year = (int) date('Y', strtotime($data['issue_date']));
            $invoice->net_cents = $result['net_cents'];
            $invoice->tax_cents = $result['tax_cents'];
            $invoice->gross_cents = $result['gross_cents'];
            $invoice->paid_cents = $paid ? $result['gross_cents'] : 0;
            $invoice->paid_on = $paid ? $data['issue_date'] : null;
            $invoice->finalized_at = now();
            $invoice->created_by = $userId;
            $invoice->save();

            foreach ($data['lines'] as $i => $line) {
                $invoice->lines()->create([
                    'position' => $i + 1,
                    'description' => $line['description'],
                    'quantity' => (float) $line['quantity'],
                    'unit' => $line['unit'] ?? null,
                    'unit_price_cents' => (int) round(((float) $line['unit_price']) * 100),
                    'tax_rate' => (int) $line['tax_rate'],
                    'line_net_cents' => $result['lines'][$i]['line_net_cents'],
                    'line_tax_cents' => $result['lines'][$i]['line_tax_cents'],
                ]);
            }

            // Attach the original PDF to the invoice. Only a not-yet-attached
            // file may be linked, so an existing file cannot be re-parented.
            File::query()
                ->whereKey($data['file_id'])
                ->whereNull('attachable_id')
                ->update([
                    'attachable_type' => Invoice::class,
                    'attachable_id' => $invoice->id,
                ]);

            // Advance the internal counter to continue this series.
            $sequencer->syncFromImported($invoice->number, $invoice->year);

            return $invoice;
        });

        return redirect()
            ->route('finance.invoices.show', $invoice)
            ->with('status', "Imported invoice {$invoice->number}.");
    }

    /**
     * Fuzzy-match a parsed buyer name to an existing customer.
     */
    private function matchCustomer(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        $needle = $this->normalise($name);
        $best = null;
        $bestScore = 0.0;

        foreach (Customer::query()->get(['id', 'name']) as $customer) {
            similar_text($needle, $this->normalise($customer->name), $percent);

            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $customer->id;
            }
        }

        return $bestScore >= 65.0 ? $best : null;
    }

    private function normalise(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/\b(gmbh|ug|ag|kg|ohg|e\.?k\.?|mbh|co|und|&)\b/u', '', $name) ?? $name;

        return trim(preg_replace('/[^a-z0-9]+/', '', $name) ?? $name);
    }
}
