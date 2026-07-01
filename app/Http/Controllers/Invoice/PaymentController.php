<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invoice;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Records a (possibly partial) payment against a finalised invoice.
 */
class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->isFinalized(), 403, 'Only finalised invoices can take payments.');
        abort_if($invoice->status === InvoiceStatus::CANCELLED, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'paid_on' => ['nullable', 'date'],
        ]);

        $invoice->paid_cents += (int) round(((float) $validated['amount']) * 100);
        $invoice->paid_on = $validated['paid_on'] ?? now()->toDateString();

        if ($invoice->paid_cents >= $invoice->gross_cents) {
            $invoice->status = InvoiceStatus::PAID;
        }

        $invoice->save();

        return redirect()->route('finance.invoices.show', $invoice)->with('status', __('flash.payment_recorded'));
    }
}
