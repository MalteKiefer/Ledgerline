<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invoice;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceNumberGenerator;
use Illuminate\Http\RedirectResponse;

/**
 * Finalises a draft invoice: assigns a gapless number, sets the due date, locks
 * the record, and marks any pulled time entries and expenses as billed.
 */
class FinalizeController extends Controller
{
    public function store(Invoice $invoice, InvoiceNumberGenerator $generator): RedirectResponse
    {
        abort_unless($invoice->isDraft(), 403, 'Only draft invoices can be finalised.');

        if ($invoice->lines()->count() === 0) {
            return back()->with('error', __('flash.finalise_needs_line'));
        }

        $generator->assign($invoice);
        $invoice->finalized_at = now();
        $invoice->status = InvoiceStatus::SENT;

        if ($invoice->due_date === null) {
            $invoice->due_date = $invoice->issue_date->copy()->addDays($invoice->payment_terms_days);
        }

        $invoice->save();

        foreach ($invoice->lines()->whereNotNull('source_id')->with('source')->get() as $line) {
            $line->source?->forceFill(['billed' => true])->save();
        }

        return redirect()
            ->route('finance.invoices.show', $invoice)
            ->with('status', __('flash.invoice_finalised', ['number' => $invoice->number]));
    }
}
