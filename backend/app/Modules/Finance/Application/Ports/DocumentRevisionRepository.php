<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\DTOs\PublishedRevision;
use App\Modules\Finance\Application\DTOs\StoredDocument;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use Closure;

interface DocumentRevisionRepository
{
    /** @param array<array-key, mixed> $canonicalSnapshot */
    public function create(
        CreateRevisionData $data,
        DocumentTotals $totals,
        array $canonicalSnapshot,
        string $snapshotSha256,
    ): DocumentRevisionId;

    /** @param array<array-key, mixed> $canonicalSnapshot */
    public function createIdempotently(
        CreateRevisionData $data,
        DocumentTotals $totals,
        array $canonicalSnapshot,
        string $snapshotSha256,
        string $creationKey,
    ): DocumentRevisionId;

    /**
     * @param  Closure(string, array<array-key, mixed>): StoredDocument  $storePdf
     */
    public function publish(DocumentRevisionId $id, Closure $storePdf): PublishedRevision;
}
