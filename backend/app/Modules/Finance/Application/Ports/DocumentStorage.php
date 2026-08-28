<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\StoredDocument;

interface DocumentStorage
{
    /**
     * Creates a new, non-deduplicated object owned exclusively by the supplied
     * 256-bit random capability. An implementation must bind the capability to
     * the new object before a write can become externally visible, must never
     * attach it to an existing object, and must leave `delete($ownershipToken)`
     * able to remove a partial write even when this method throws.
     */
    public function putPdf(string $seriesUuid, string $bytes, string $ownershipToken): StoredDocument;

    /**
     * Deletes only the object currently owned by this capability. A missing or
     * superseded capability is a no-op so a reused path can never lose its new
     * object.
     */
    public function delete(string $ownershipToken): void;
}
