<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invoice;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Creates a credit note (Storno) from a finalised invoice: a draft with negated
 * lines and totals, linked to the original, which is then cancelled.
 */
class CreditNoteController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->isFinalized(), 403, 'Only finalised invoices can be credited.');
        abort_if($invoice->type === InvoiceType::CREDIT_NOTE, 403, 'A credit note cannot be credited.');

        $credit = DB::transaction(function () use ($request, $invoice): Invoice {
            $credit = new Invoice([
                'type' => InvoiceType::CREDIT_NOTE->value,
                'status' => InvoiceStatus::DRAFT->value,
                'customer_id' => $invoice->customer_id,
                'issue_date' => now()->toDateString(),
                'language' => $invoice->language,
                'currency' => $invoice->currency,
                'tax_mode' => $invoice->tax_mode->value,
                'discount_cents' => 0,
                'intro_text' => "Credit note for invoice {$invoice->number}.",
                'payment_terms_days' => 0,
            ]);
            $credit->parent_invoice_id = $invoice->id;
            $credit->created_by = $request->user()->id;
            $credit->net_cents = -$invoice->net_cents;
            $credit->tax_cents = -$invoice->tax_cents;
            $credit->gross_cents = -$invoice->gross_cents;
            $credit->save();

            foreach ($invoice->lines as $line) {
                $credit->lines()->create([
                    'position' => $line->position,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'unit_price_cents' => -$line->unit_price_cents,
                    'tax_rate' => $line->tax_rate,
                    'line_net_cents' => -$line->line_net_cents,
                    'line_tax_cents' => -$line->line_tax_cents,
                ]);
            }

            $invoice->status = InvoiceStatus::CANCELLED;
            $invoice->save();

            return $credit;
        });

        return redirect()
            ->route('finance.invoices.show', $credit)
            ->with('status', __('flash.credit_note_created'));
    }
}
