<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands;

use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\DTOs\PublishedRevision;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentRenderer;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class PublishDocumentRevision
{
    public function __construct(
        private DocumentRevisionRepository $revisions,
        private DocumentRenderer $renderer,
        private DocumentStorage $storage,
        private LoggerInterface $logger,
    ) {}

    public function handle(DocumentRevisionId $id): PublishedRevision
    {
        $stored = null;
        $storageAttempted = false;
        $ownershipToken = bin2hex(random_bytes(32));

        try {
            return $this->revisions->publish(
                $id,
                function (string $seriesUuid, array $snapshot) use (
                    &$stored,
                    &$storageAttempted,
                    $ownershipToken,
                ): StoredDocument {
                    $bytes = $this->renderer->render($snapshot);
                    $storageAttempted = true;
                    $stored = $this->storage->putPdf($seriesUuid, $bytes, $ownershipToken);

                    if (! hash_equals(hash('sha256', $bytes), $stored->sha256)) {
                        throw new LogicException('Stored PDF hash does not match the rendered bytes.');
                    }

                    return $stored;
                },
            );
        } catch (Throwable $exception) {
            if ($storageAttempted) {
                try {
                    $this->storage->delete($ownershipToken);
                } catch (Throwable $cleanupException) {
                    try {
                        $this->logger->error(
                            'Document PDF cleanup failed after publication error.',
                            [
                                'exception' => $cleanupException,
                                'primary_exception' => $exception,
                                'path' => $stored?->path,
                            ],
                        );
                    } catch (Throwable) {
                        // Cleanup and logging failures must never replace the publication failure.
                    }
                }
            }

            throw $exception;
        }
    }
}
