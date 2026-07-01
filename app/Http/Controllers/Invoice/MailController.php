<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a finalised invoice (Factur-X PDF attached) to the customer.
 */
class MailController extends Controller
{
    public function store(Request $request, Invoice $invoice, InvoiceDocument $document): RedirectResponse
    {
        abort_unless($invoice->isFinalized(), 403, 'Only finalised invoices can be emailed.');

        $validated = $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $to = $validated['email'] ?? $invoice->customer?->email;

        if (blank($to)) {
            return back()->with('error', __('flash.no_recipient'));
        }

        $invoice->load(['customer', 'lines']);
        $pdf = $document->facturx($invoice, CompanyProfile::current());

        Mail::to($to)->send(new InvoiceMail($invoice, $pdf));

        return redirect()
            ->route('finance.invoices.show', $invoice)
            ->with('status', __('flash.invoice_emailed', ['email' => $to]));
    }
}
