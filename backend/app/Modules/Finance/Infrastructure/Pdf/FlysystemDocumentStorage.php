<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Application\Ports\DocumentStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;
use LogicException;

final readonly class FlysystemDocumentStorage implements DocumentStorage
{
    public function __construct(private Filesystem $disk) {}

    public function putPdf(string $seriesUuid, string $bytes, string $ownershipToken): StoredDocument
    {
        if (! str_starts_with($bytes, '%PDF-')) {
            throw new InvalidArgumentException('Document storage accepts only PDF bytes.');
        }

        $path = $this->pathFor($ownershipToken);

        if ($this->disk->exists($path)) {
            throw new LogicException('The document capability is already in use.');
        }

        // `files.disk` is private by configuration. Do not send object ACLs:
        // private S3-compatible stores such as R2 deliberately reject them.
        if (! $this->disk->put($path, $bytes)) {
            throw new LogicException('The PDF could not be stored.');
        }

        return new StoredDocument($path, hash('sha256', $bytes));
    }

    public function delete(string $ownershipToken): void
    {
        if (! self::isOwnershipToken($ownershipToken)) {
            return;
        }

        $this->disk->delete($this->pathFor($ownershipToken));
    }

    private function pathFor(string $ownershipToken): string
    {
        if (! self::isOwnershipToken($ownershipToken)) {
            throw new InvalidArgumentException('A document ownership token must be lowercase 256-bit hex.');
        }

        return sprintf(
            'finance/revisions/%s/%s.pdf',
            substr($ownershipToken, 0, 2),
            $ownershipToken,
        );
    }

    private static function isOwnershipToken(string $ownershipToken): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/D', $ownershipToken) === 1;
    }
}
