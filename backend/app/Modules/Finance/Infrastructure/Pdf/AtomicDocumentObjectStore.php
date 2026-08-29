<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use DateTimeInterface;

interface AtomicDocumentObjectStore
{
    public function create(string $path, string $bytes, DocumentStorageWrite $write): void;

    public function deleteIfOwned(string $path, DocumentStorageWrite $write): void;

    /** @return iterable<array{path: string, write: DocumentStorageWrite}> */
    public function ownedBefore(DateTimeInterface $cutoff): iterable;
}
