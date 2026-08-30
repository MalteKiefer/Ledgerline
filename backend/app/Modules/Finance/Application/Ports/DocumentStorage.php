<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\DocumentStorageWrite;
use App\Modules\Finance\Application\DTOs\StoredDocument;

interface DocumentStorage
{
    /**
     * Creates a new, non-deduplicated object owned exclusively by the supplied
     * write intent. Creation must be atomic and must never overwrite or attach
     * the intent to an existing object. The intent is known before I/O so an
     * ambiguous acknowledgement can still be cleaned up conditionally.
     */
    public function putPdf(
        string $seriesUuid,
        string $bytes,
        DocumentStorageWrite $write,
    ): StoredDocument;

    /**
     * Deletes only the object whose capability, cleanup proof, digest, and
     * generation still match this write. Missing or superseded generations are
     * a no-op so stale compensation can never remove a newer object.
     */
    public function delete(DocumentStorageWrite $write): void;
}
