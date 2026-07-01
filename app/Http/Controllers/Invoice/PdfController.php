<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceDocument;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams an invoice PDF. Finalised invoices are served as Factur-X (with the
 * embedded e-invoice XML); drafts as a plain preview.
 */
class PdfController extends Controller
{
    public function __invoke(Invoice $invoice, InvoiceDocument $document): Response
    {
        $invoice->load(['customer', 'lines']);
        $company = CompanyProfile::current();

        $bytes = $invoice->isFinalized()
            ? $document->facturx($invoice, $company)
            : $document->plain($invoice, $company);

        $name = ($invoice->number ?? 'draft-'.$invoice->id).'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
