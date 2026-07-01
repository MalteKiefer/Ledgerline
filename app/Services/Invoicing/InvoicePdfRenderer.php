<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

/**
 * Renders an invoice to a plain PDF (bytes) via dompdf, in the invoice's own
 * language.
 */
class InvoicePdfRenderer
{
    public function render(Invoice $invoice, CompanyProfile $company): string
    {
        $locale = array_key_exists($invoice->language, config('finance.languages')) ? $invoice->language : 'de';

        $original = App::getLocale();
        App::setLocale($locale);

        try {
            $html = View::make('invoices.pdf', ['invoice' => $invoice, 'company' => $company])->render();
        } finally {
            App::setLocale($original);
        }

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
