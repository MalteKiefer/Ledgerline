<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;

/**
 * Produces the downloadable invoice PDF.
 *
 * Finalised invoices get a Factur-X / ZUGFeRD PDF (the EN16931 XML embedded);
 * drafts get a plain preview PDF.
 */
class InvoiceDocument
{
    public function __construct(
        private readonly InvoicePdfRenderer $renderer,
        private readonly ZugferdInvoiceBuilder $zugferd,
    ) {}

    /**
     * A Factur-X PDF (visual PDF + embedded e-invoice XML).
     */
    public function facturx(Invoice $invoice, CompanyProfile $company): string
    {
        $pdf = $this->renderer->render($invoice, $company);
        $document = $this->zugferd->build($invoice, $company);

        $builder = ZugferdDocumentPdfBuilder::fromPdfString($document, $pdf);
        $builder->generateDocument();

        return $builder->downloadString();
    }

    /**
     * A plain PDF with no embedded XML (for drafts).
     */
    public function plain(Invoice $invoice, CompanyProfile $company): string
    {
        return $this->renderer->render($invoice, $company);
    }
}
