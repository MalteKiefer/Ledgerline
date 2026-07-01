<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\TaxMode;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\TimeEntry;
use App\Services\Invoicing\InvoiceCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD for invoices (money in). Invoices are global (not team-scoped). Drafts
 * are freely editable; finalising assigns a number and locks the record.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function index(Request $request): View
    {
        $status = InvoiceStatus::tryFrom((string) $request->query('status'));
        [$sort, $dir] = $this->sortFor($request, ['issue_date', 'number', 'gross_cents', 'status'], 'issue_date');

        if ($request->query('sort') === null && $request->query('dir') === null) {
            $dir = 'desc';
        }

        $base = Invoice::query()
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($request->query('q'), function ($q, $term): void {
                $like = '%'.mb_strtolower((string) $term).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(number) LIKE ?', [$like])
                    ->orWhereHas('customer', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$like])));
            });

        $totals = (clone $base)
            ->selectRaw('currency, SUM(gross_cents) AS gross, SUM(gross_cents - paid_cents) AS outstanding')
            ->groupBy('currency')
            ->get();

        $invoices = $base->with('customer')
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'totals' => $totals,
            'statuses' => InvoiceStatus::options(),
            'activeStatus' => $status?->value,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        $company = CompanyProfile::current();

        $invoice = new Invoice([
            'issue_date' => now()->toDateString(),
            'language' => $company->default_language,
            'currency' => $company->default_currency,
            'tax_mode' => $company->small_business ? TaxMode::SMALL_BUSINESS : TaxMode::STANDARD,
            'payment_terms_days' => $company->payment_terms_days,
        ]);

        return view('invoices.create', $this->formData($invoice));
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = new Invoice;
        $invoice->created_by = $request->user()->id;
        $this->fillDraft($invoice, $request->validated());

        return redirect()->route('finance.invoices.show', $invoice)->with('status', 'Invoice draft created.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['customer', 'lines.source', 'parent', 'creditNotes', 'files']);

        return view('invoices.show', [
            'invoice' => $invoice,
            'company' => CompanyProfile::current(),
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        abort_unless($invoice->isDraft(), 403, 'Only draft invoices can be edited.');

        $invoice->load('lines');

        return view('invoices.edit', $this->formData($invoice));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->isDraft(), 403, 'Only draft invoices can be edited.');

        $this->fillDraft($invoice, $request->validated());

        return redirect()->route('finance.invoices.show', $invoice)->with('status', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->isDraft(), 403, 'Only draft invoices can be deleted.');

        $invoice->delete();

        return redirect()->route('finance.invoices.index')->with('status', 'Invoice deleted.');
    }

    /**
     * Create or refresh a draft invoice and its lines from validated input.
     *
     * @param  array<string, mixed>  $data
     */
    private function fillDraft(Invoice $invoice, array $data): void
    {
        $mode = TaxMode::from($data['tax_mode']);
        $definitions = $this->lineDefinitions($data);
        $result = $this->calculator->compute(
            array_map(fn (array $d): array => [
                'quantity' => $d['quantity'],
                'unit_price_cents' => $d['unit_price_cents'],
                'tax_rate' => $d['tax_rate'],
            ], $definitions),
            $mode,
            (int) round(((float) ($data['discount'] ?? 0)) * 100),
        );

        $invoice->fill([
            'type' => InvoiceType::INVOICE->value,
            'status' => InvoiceStatus::DRAFT->value,
            'customer_id' => $this->scopedCustomerId($data['customer_id']),
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'] ?? null,
            'language' => $data['language'],
            'currency' => $data['currency'],
            'tax_mode' => $mode->value,
            'discount_cents' => $result['discount_cents'],
            'intro_text' => $data['intro_text'] ?? null,
            'closing_text' => $data['closing_text'] ?? null,
            'payment_terms_days' => $data['payment_terms_days'] ?? 14,
        ]);
        $invoice->net_cents = $result['net_cents'];
        $invoice->tax_cents = $result['tax_cents'];
        $invoice->gross_cents = $result['gross_cents'];
        $invoice->save();

        $invoice->lines()->delete();

        foreach ($definitions as $i => $def) {
            $line = $invoice->lines()->make([
                'position' => $i + 1,
                'description' => $def['description'],
                'quantity' => $def['quantity'],
                'unit' => $def['unit'],
                'unit_price_cents' => $def['unit_price_cents'],
                'tax_rate' => $def['tax_rate'],
                'line_net_cents' => $result['lines'][$i]['line_net_cents'],
                'line_tax_cents' => $result['lines'][$i]['line_tax_cents'],
            ]);

            if ($def['source'] !== null) {
                $line->source()->associate($def['source']);
            }

            $line->save();
        }
    }

    /**
     * Build normalised line definitions from manual lines and imported sources.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{description: string, quantity: float, unit: ?string, unit_price_cents: int, tax_rate: int, source: ?Model}>
     */
    private function lineDefinitions(array $data): array
    {
        $definitions = [];

        foreach ($data['lines'] ?? [] as $line) {
            if (blank($line['description'] ?? null) && blank($line['unit_price'] ?? null)) {
                continue;
            }

            $definitions[] = [
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit' => $line['unit'] ?? null,
                'unit_price_cents' => (int) round(((float) ($line['unit_price'] ?? 0)) * 100),
                'tax_rate' => (int) ($line['tax_rate'] ?? 0),
                'source' => null,
            ];
        }

        $defaultRate = (int) CompanyProfile::current()->default_tax_rate;

        foreach ($data['import'] ?? [] as $token) {
            [$type, $id] = array_pad(explode(':', (string) $token, 2), 2, null);

            if ($type === 'time' && ($entry = TimeEntry::query()->where('billable', true)->where('billed', false)->find($id)) !== null) {
                $definitions[] = [
                    'description' => $entry->description,
                    'quantity' => round($entry->hours(), 2),
                    'unit' => 'h',
                    'unit_price_cents' => $entry->rate_cents,
                    'tax_rate' => $defaultRate,
                    'source' => $entry,
                ];
            }

            if ($type === 'expense' && ($expense = Expense::query()->where('billable', true)->where('billed', false)->find($id)) !== null) {
                $definitions[] = [
                    'description' => $expense->description,
                    'quantity' => 1.0,
                    'unit' => null,
                    'unit_price_cents' => $expense->net()->cents,
                    'tax_rate' => $expense->tax_rate,
                    'source' => $expense,
                ];
            }
        }

        return $definitions;
    }

    private function scopedCustomerId(int|string|null $id): ?int
    {
        return $id === null ? null : Customer::query()->whereKey($id)->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Invoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'languages' => config('finance.languages'),
            'currencies' => config('finance.currencies'),
            'taxModes' => TaxMode::options(),
            'importableTime' => TimeEntry::query()->where('billable', true)->where('billed', false)->with(['customer', 'project'])->orderBy('date')->get(),
            'importableExpenses' => Expense::query()->where('billable', true)->where('billed', false)->with(['customer', 'project'])->orderBy('date')->get(),
        ];
    }
}
