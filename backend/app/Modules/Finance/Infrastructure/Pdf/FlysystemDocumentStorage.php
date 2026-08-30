<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use InvalidArgumentException;

final readonly class FlysystemDocumentStorage implements DocumentStorage
{
    public function __construct(private AtomicDocumentObjectStore $objects) {}

    public function putPdf(
        string $seriesUuid,
        string $bytes,
        DocumentStorageWrite $write,
    ): StoredDocument {
        if (! str_starts_with($bytes, '%PDF-')) {
            throw new InvalidArgumentException('Document storage accepts only PDF bytes.');
        }
        if (! hash_equals($write->sha256, hash('sha256', $bytes))) {
            throw new InvalidArgumentException('Document bytes do not match the write digest.');
        }

        $path = $this->pathFor($write->ownershipToken);
        $this->objects->create($path, $bytes, $write);

        return new StoredDocument($path, $write->sha256);
    }

    public function delete(DocumentStorageWrite $write): void
    {
        $this->objects->deleteIfOwned($this->pathFor($write->ownershipToken), $write);
    }

    private function pathFor(string $ownershipToken): string
    {
        return sprintf(
            'finance/revisions/%s/%s.pdf',
            substr($ownershipToken, 0, 2),
            $ownershipToken,
        );
    }
}
