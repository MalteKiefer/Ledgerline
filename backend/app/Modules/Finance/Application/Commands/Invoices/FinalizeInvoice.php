<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use App\Modules\Finance\Application\Ports\InventoryMovementPort;
use App\Modules\Finance\Application\Ports\InvoiceNumberAllocator;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use DateTimeImmutable;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class FinalizeInvoice
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceNumberAllocator $numbers,
        private InventoryMovementPort $inventory,
        private DocumentRenderer $renderer,
        private DocumentStorage $storage,
        private LoggerInterface $logger,
    ) {}

    public function handle(InvoiceId $id, IdempotencyKey $key): FinalizedInvoice
    {
        return $this->finalize($id, $key, false);
    }

    public function finalizeCancellation(InvoiceId $id): FinalizedInvoice
    {
        return $this->finalize(
            $id,
            new IdempotencyKey('invoice.cancel.finalize.credit.'.$id->value),
            true,
        );
    }

    private function finalize(
        InvoiceId $id,
        IdempotencyKey $key,
        bool $cancellation,
    ): FinalizedInvoice {
        $write = null;
        $storageAttempted = false;
        $stored = null;

        try {
            $finalize = $cancellation
                ? $this->invoices->finalizeCancellationAtomically(...)
                : $this->invoices->finalizeAtomically(...);

            return $finalize(
                $id,
                $key,
                fn (int $ownerId, string $issueDate): array => $this->numbers->allocate($ownerId, $issueDate),
                function (string $seriesUuid, array $snapshot) use (
                    &$write,
                    &$storageAttempted,
                    &$stored,
                ): StoredDocument {
                    $bytes = $this->renderer->render($snapshot);
                    $write = new DocumentStorageWrite(
                        bin2hex(random_bytes(32)),
                        bin2hex(random_bytes(32)),
                        hash('sha256', $bytes),
                    );
                    $storageAttempted = true;
                    $stored = $this->storage->putPdf($seriesUuid, $bytes, $write);
                    if (! hash_equals($write->sha256, $stored->sha256)) {
                        throw new LogicException('Stored invoice PDF hash does not match the rendered bytes.');
                    }

                    return $stored;
                },
                function (
                    int $ownerId,
                    string $invoiceUuid,
                    array $quantityScaledByProduct,
                    DateTimeImmutable $occurredAt,
                ): void {
                    $this->inventory->recordInvoiceSale(
                        $ownerId,
                        $invoiceUuid,
                        $quantityScaledByProduct,
                        $occurredAt,
                    );
                },
            );
        } catch (Throwable $exception) {
            if ($storageAttempted && $write instanceof DocumentStorageWrite) {
                try {
                    $this->storage->delete($write);
                } catch (Throwable $cleanupException) {
                    try {
                        $this->logger->error('Invoice PDF cleanup failed after finalization error.', [
                            'exception' => $cleanupException,
                            'primary_exception' => $exception,
                            'path' => $stored?->path,
                        ]);
                    } catch (Throwable) {
                        // Cleanup and logging failures must not replace the finalization failure.
                    }
                }
            }

            throw $exception;
        }
    }
}
