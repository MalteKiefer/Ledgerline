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
use Throwable;

final readonly class PublishDocumentRevision
{
    public function __construct(
        private DocumentRevisionRepository $revisions,
        private DocumentRenderer $renderer,
        private DocumentStorage $storage,
    ) {}

    public function handle(DocumentRevisionId $id): PublishedRevision
    {
        $stored = null;

        try {
            return $this->revisions->publish(
                $id,
                function (string $seriesUuid, array $snapshot) use (&$stored): StoredDocument {
                    $bytes = $this->renderer->render($snapshot);
                    $stored = $this->storage->putPdf($seriesUuid, $bytes);

                    if (! hash_equals(hash('sha256', $bytes), $stored->sha256)) {
                        throw new LogicException('Stored PDF hash does not match the rendered bytes.');
                    }

                    return $stored;
                },
            );
        } catch (Throwable $exception) {
            if ($stored instanceof StoredDocument) {
                $this->storage->delete($stored->path);
            }

            throw $exception;
        }
    }
}
