<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\DTOs\PublishedRevision;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentDocumentRevisionRepository implements DocumentRevisionRepository
{
    private const int SEQUENCE_RETRY_ATTEMPTS = 3;

    public function create(
        CreateRevisionData $data,
        DocumentTotals $totals,
        array $canonicalSnapshot,
        string $snapshotSha256,
    ): DocumentRevisionId {
        $ownerId = $this->authenticatedOwnerId();

        for ($attempt = 1; $attempt <= self::SEQUENCE_RETRY_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use (
                    $data,
                    $totals,
                    $canonicalSnapshot,
                    $snapshotSha256,
                    $ownerId,
                ): DocumentRevisionId {
                    $series = DocumentSeriesRecord::query()
                        ->where('uuid', $data->seriesUuid)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $previous = $series->revisions()
                        ->orderByDesc('revision_number')
                        ->lockForUpdate()
                        ->first();
                    $revisionNumber = ($previous?->revision_number ?? 0) + 1;
                    $revision = $series->revisions()->make([
                        'previous_revision_id' => $previous?->id,
                        'status' => 'draft',
                        'snapshot' => $canonicalSnapshot,
                        'net_minor' => $totals->net->minor(),
                        'vat_minor' => $totals->vat->minor(),
                        'gross_minor' => $totals->gross->minor(),
                        'currency' => $totals->net->currency(),
                        'change_reason' => $data->changeReason,
                    ]);
                    $revision->forceFill([
                        'revision_number' => $revisionNumber,
                        'created_by' => $ownerId,
                    ])->save();
                    $series->activities()->create([
                        'document_revision_id' => $revision->id,
                        'type' => 'revision.created',
                        'payload' => ['snapshot_sha256' => $snapshotSha256],
                        'created_by' => $ownerId,
                    ]);

                    return new DocumentRevisionId((int) $revision->id);
                }, 1);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isRevisionSequenceCollision($exception)
                    || $attempt === self::SEQUENCE_RETRY_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('The document revision sequence retry loop ended unexpectedly.');
    }

    public function publish(DocumentRevisionId $id, Closure $storePdf): PublishedRevision
    {
        $ownerId = $this->authenticatedOwnerId();

        return DB::transaction(function () use ($id, $storePdf, $ownerId): PublishedRevision {
            $revisionLocator = DocumentRevisionRecord::query()
                ->whereKey($id->value)
                ->firstOrFail(['document_series_id']);
            $series = DocumentSeriesRecord::query()
                ->whereKey($revisionLocator->document_series_id)
                ->lockForUpdate()
                ->firstOrFail();
            $revision = $series->revisions()
                ->whereKey($id->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ($revision->published_at !== null) {
                return $this->publishedRevision($revision);
            }

            $stored = $storePdf((string) $series->uuid, $this->snapshot($revision));
            $publishedAt = now()->toImmutable();
            $updated = DB::table('finance_document_revisions')
                ->where('id', $revision->id)
                ->where('user_id', $ownerId)
                ->where('document_series_id', $series->id)
                ->where('status', 'draft')
                ->whereNull('published_at')
                ->update([
                    'status' => 'published',
                    'pdf_path' => $stored->path,
                    'pdf_sha256' => $stored->sha256,
                    'published_at' => $publishedAt,
                ]);

            if ($updated !== 1) {
                throw new LogicException('The locked draft revision could not be published.');
            }

            $series->activities()->create([
                'document_revision_id' => $revision->id,
                'type' => 'revision.published',
                'payload' => [
                    'path' => $stored->path,
                    'pdf_sha256' => $stored->sha256,
                ],
                'created_by' => $ownerId,
            ]);

            return $this->publishedRevision($revision->refresh());
        }, 1);
    }

    private function authenticatedOwnerId(): int
    {
        $ownerId = Auth::id();

        if (! is_numeric($ownerId) || (int) $ownerId < 1) {
            throw new LogicException('Document revisions require an authenticated owner.');
        }

        return (int) $ownerId;
    }

    private function isRevisionSequenceCollision(UniqueConstraintViolationException $exception): bool
    {
        return $exception->index === 'finance_document_revisions_series_number_unique'
            || $exception->columns === ['document_series_id', 'revision_number'];
    }

    private function publishedRevision(DocumentRevisionRecord $revision): PublishedRevision
    {
        $publishedAt = $revision->getAttribute('published_at');

        if (! is_string($revision->pdf_path)
            || ! is_string($revision->pdf_sha256)
            || ! $publishedAt instanceof DateTimeInterface) {
            throw new LogicException('Published revision metadata is incomplete.');
        }

        return new PublishedRevision(
            new DocumentRevisionId((int) $revision->id),
            (int) $revision->revision_number,
            $revision->pdf_path,
            $revision->pdf_sha256,
            DateTimeImmutable::createFromInterface($publishedAt),
        );
    }

    /** @return array<array-key, mixed> */
    private function snapshot(DocumentRevisionRecord $revision): array
    {
        $snapshot = $revision->getAttribute('snapshot');

        if (! is_array($snapshot)) {
            throw new LogicException('A document revision snapshot must be a JSON object or array.');
        }

        return $snapshot;
    }
}
