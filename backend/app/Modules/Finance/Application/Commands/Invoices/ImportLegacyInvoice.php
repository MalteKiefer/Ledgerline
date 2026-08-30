<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use App\Modules\Finance\Application\DTOs\Invoices\LegacyInvoiceFinalization;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InvoiceRepository;

/**
 * The single migration entry point into the invoice aggregate (mirrors
 * CreateInvoiceDraftFromSource: the invoice module owns document creation,
 * a migration adapter owns legacy row selection/mapping only). Idempotent
 * via the source-type/source-key uniqueness already enforced for every
 * InvoiceDraftSource, plus (for finalized rows) the same idempotency-key
 * mechanism FinalizeInvoice uses — so running the migration command twice
 * over the same legacy row never creates a second series, revision, PDF,
 * or activity entry.
 */
final readonly class ImportLegacyInvoice
{
    public function __construct(
        private InvoiceRepository $invoices,
        private DocumentStorage $storage,
    ) {}

    public function handle(
        InvoiceDraftSource $source,
        IdempotencyKey $draftKey,
        ?LegacyInvoiceFinalization $finalization,
        ?IdempotencyKey $finalizeKey = null,
    ): InvoiceView {
        $draft = $this->invoices->createDraftFromSource($source, $draftKey);
        if ($finalization === null) {
            return $draft;
        }

        $key = $finalizeKey ?? new IdempotencyKey('legacy-invoice-finalize:'.$source->sourceKey);
        $finalized = $this->invoices->importFinalized(
            $draft->id,
            $key,
            $finalization,
            function (string $seriesUuid, array $snapshot) use ($finalization): StoredDocument {
                unset($snapshot);
                $write = new DocumentStorageWrite(
                    bin2hex(random_bytes(32)),
                    bin2hex(random_bytes(32)),
                    hash('sha256', $finalization->pdfBytes),
                );
                $stored = $this->storage->putPdf($seriesUuid, $finalization->pdfBytes, $write);
                if (! hash_equals($write->sha256, $stored->sha256)) {
                    throw new \LogicException('Stored legacy invoice PDF hash does not match the copied bytes.');
                }

                return $stored;
            },
        );

        return $this->view($finalized);
    }

    private function view(FinalizedInvoice $finalized): InvoiceView
    {
        return $finalized->invoice;
    }
}
