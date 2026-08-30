<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs;

use InvalidArgumentException;

final readonly class DocumentStorageWrite
{
    public function __construct(
        public string $ownershipToken,
        public string $cleanupProof,
        public string $sha256,
    ) {
        if (! self::isSha256($ownershipToken)) {
            throw new InvalidArgumentException('A document ownership token must be lowercase 256-bit hex.');
        }
        if (! self::isSha256($cleanupProof)) {
            throw new InvalidArgumentException('A document cleanup proof must be lowercase 256-bit hex.');
        }
        if (! self::isSha256($sha256)) {
            throw new InvalidArgumentException('A document write digest must be lowercase SHA-256 hex.');
        }
    }

    public function generation(): string
    {
        return hash('sha256', 'ledgerline-document-generation:'.$this->cleanupProof);
    }

    private static function isSha256(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}
