<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;

interface AtomicDocumentObjectStore
{
    public function create(string $path, string $bytes, DocumentStorageWrite $write): void;

    public function deleteIfOwned(string $path, DocumentStorageWrite $write): void;
}
