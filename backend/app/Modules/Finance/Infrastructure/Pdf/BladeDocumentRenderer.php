<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use App\Modules\Finance\Application\Ports\DocumentRenderer;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory;
use LogicException;

final readonly class BladeDocumentRenderer implements DocumentRenderer
{
    public function __construct(private Factory $views) {}

    /** @param array<array-key, mixed> $snapshot */
    public function render(array $snapshot): string
    {
        [$view, $viewModel] = match ($snapshot['document_type'] ?? null) {
            'quote' => ['finance.quotes.pdf', QuotePdfViewModel::fromSnapshot($snapshot)],
            'invoice' => ['finance.invoices.pdf', InvoicePdfViewModel::fromSnapshot($snapshot)],
            default => throw new \InvalidArgumentException('The PDF renderer accepts only allowlisted snapshots.'),
        };
        $html = $this->views->make($view, $viewModel->viewData())->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setAllowedRemoteHosts([]);
        $options->setChroot(resource_path('views/finance'));
        $options->setDefaultFont('DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->setPaper('a4');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        // Dompdf otherwise embeds wall-clock metadata, which would make a retry
        // of an identical immutable snapshot produce different bytes.
        $fixedDate = 'D:20000101000000+00\'00\'';
        $pdf->addInfo('CreationDate', $fixedDate);
        $pdf->addInfo('ModDate', $fixedDate);
        $pdf->addInfo('Title', $viewModel->documentTitle());

        $canvas = $pdf->getCanvas();
        if (! $canvas instanceof CPDF) {
            throw new LogicException('The deterministic PDF renderer requires Dompdf CPDF.');
        }
        $canvas->get_cpdf()->fileIdentifier = md5($html);

        return $pdf->output();
    }
}
