<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Models\FinancePartner;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\DTOs\Quotes\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuotePage;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Domain\Quotes\QuoteRevisionState;
use App\Modules\Finance\Domain\Quotes\QuoteStatus;
use App\Modules\Finance\Domain\Quotes\QuoteWorkflow;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentActivityRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteConversionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteDeliveryRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteDraftRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteOperationRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteSeriesRecord;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class EloquentQuoteRepository implements QuoteRepository
{
    private const int NUMBER_RETRY_ATTEMPTS = 3;

    public function __construct(
        private readonly Clock $clock,
        private readonly QuoteSettings $settings,
    ) {}

    public function get(QuoteId $id): QuoteView
    {
        return $this->viewForUuid($id);
    }

    public function page(array $filters, int $page, int $perPage): QuotePage
    {
        $ownerId = $filters['owner_id'] ?? null;

        if (! is_int($ownerId) || $ownerId < 1) {
            throw new InvalidArgumentException('Quote pages require a positive owner_id filter.');
        }

        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Quote pagination must use page >= 1 and perPage between 1 and 100.');
        }

        $query = QuoteSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->select('finance_quote_series.*')
            ->where('finance_quote_series.user_id', $ownerId)
            ->join('finance_document_series as quote_document_series', function (JoinClause $join) use ($ownerId): void {
                $join->on('quote_document_series.id', '=', 'finance_quote_series.document_series_id')
                    ->where('quote_document_series.user_id', '=', $ownerId)
                    ->where('quote_document_series.document_type', '=', 'quote');
            })
            ->leftJoin('finance_quote_drafts as quote_draft_search', function (JoinClause $join) use ($ownerId): void {
                $join->on('quote_draft_search.document_series_id', '=', 'finance_quote_series.document_series_id')
                    ->where('quote_draft_search.user_id', '=', $ownerId);
            })
            ->leftJoin('finance_document_revisions as quote_current_revision', function (JoinClause $join) use ($ownerId): void {
                $join->on('quote_current_revision.id', '=', 'finance_quote_series.current_revision_id')
                    ->whereColumn('quote_current_revision.document_series_id', 'finance_quote_series.document_series_id')
                    ->where('quote_current_revision.user_id', '=', $ownerId);
            });

        $search = $filters['q'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], strtolower(trim($search)));
            $needle = '%'.$escapedSearch.'%';
            $query->where(static function (Builder $query) use ($needle): void {
                $query->whereRaw("LOWER(finance_quote_series.number) LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("LOWER(CAST(quote_document_series.uuid AS TEXT)) LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("LOWER(CAST(quote_draft_search.payload AS TEXT)) LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("LOWER(CAST(quote_current_revision.snapshot AS TEXT)) LIKE ? ESCAPE '!'", [$needle]);
            });
        }

        if (isset($filters['status'])) {
            $query->where('quote_document_series.status', $filters['status']);
        }

        $this->applyEffectiveStatusFilter($query, $filters, $ownerId);
        $this->applyPublishedDateFilters($query, $filters, $ownerId);

        $total = (clone $query)->count('finance_quote_series.document_series_id');
        $quotes = $query
            ->with(['series', 'draft', 'currentRevision'])
            ->orderByRaw('CASE WHEN finance_quote_series.published_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('finance_quote_series.published_at')
            ->orderByDesc('finance_quote_series.document_series_id')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get()
            ->map(fn (QuoteSeriesRecord $quote): QuoteView => $this->viewFromRecord($ownerId, $quote));

        return new QuotePage(
            array_values($quotes->all()),
            $page,
            $perPage,
            $total,
        );
    }

    public function revisions(QuoteId $id): array
    {
        $series = $this->seriesForUuid($id);
        QuoteSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_series.user_id', $id->ownerId)
            ->where('document_series_id', $series->id)
            ->firstOrFail(['document_series_id']);

        return array_values(DocumentRevisionRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_document_revisions.user_id', $id->ownerId)
            ->where('document_series_id', $series->id)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DocumentRevisionRecord $revision): QuoteRevisionRef => $this->revisionRef($revision))
            ->all());
    }

    public function createDraft(
        int $ownerId,
        array $payload,
        DocumentTotals $totals,
        ?int $partnerId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): QuoteView {
        if ($ownerId < 1) {
            throw new LogicException('Quote drafts require a positive owner ID.');
        }
        if (($sourceType === null) !== ($sourceId === null)
            || ($sourceType !== null && (trim($sourceType) === '' || strlen($sourceType) > 64))
            || ($sourceId !== null && $sourceId < 1)) {
            throw new LogicException('Quote draft provenance must be a valid source pair.');
        }

        return DB::transaction(function () use (
            $ownerId,
            $payload,
            $totals,
            $partnerId,
            $sourceType,
            $sourceId,
        ): QuoteView {
            $this->assertOwnedPartner($ownerId, $partnerId);
            $this->assertOwnedDraftSource($ownerId, $sourceType, $sourceId);
            $createdAt = $this->clock->now();

            $series = new DocumentSeriesRecord;
            $series->forceFill([
                'user_id' => $ownerId,
                'uuid' => (string) Str::uuid(),
                'document_type' => 'quote',
                'status' => 'draft',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'created_by' => $ownerId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $series->save();

            $quote = new QuoteSeriesRecord;
            $quote->forceFill([
                'document_series_id' => $series->id,
                'user_id' => $ownerId,
                'document_type' => 'quote',
                'partner_id' => $partnerId,
                'current_revision_id' => null,
                'number' => null,
                'sequence_year' => null,
                'sequence_number' => null,
                'version' => 0,
                'published_at' => null,
                'accepted_at' => null,
                'declined_at' => null,
                'converted_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $quote->save();

            $draft = new QuoteDraftRecord;
            $draft->forceFill([
                'document_series_id' => $series->id,
                'user_id' => $ownerId,
                'based_on_revision_id' => null,
                ...$this->draftValues($payload, $totals),
                'updated_by' => $ownerId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $draft->save();
            $this->appendActivity($ownerId, (int) $series->id, 'quote.created', 0, $createdAt);

            return $this->viewForUuid(new QuoteId($ownerId, (string) $series->uuid));
        }, 1);
    }

    public function createDraftIdempotently(
        int $ownerId,
        string $idempotencyKey,
        string $requestSha256,
        callable $draft,
    ): QuoteView {
        $this->validateCreateOperation($ownerId, $idempotencyKey, $requestSha256);

        return DB::transaction(function () use (
            $ownerId,
            $idempotencyKey,
            $requestSha256,
            $draft,
        ): QuoteView {
            $operation = $this->reserveCreateOperation(
                $ownerId,
                $idempotencyKey,
                $requestSha256,
            );

            if (! hash_equals((string) $operation->request_sha256, $requestSha256)) {
                throw new DomainException('idempotency_key_reused');
            }

            if ((string) $operation->state === 'succeeded') {
                return $this->replayCreatedQuote($ownerId, $operation);
            }

            if ($operation->document_series_id !== null) {
                $quote = $this->viewForSeriesId($ownerId, (int) $operation->document_series_id);
            } else {
                $built = $draft();
                $quote = $this->createDraft(
                    $ownerId,
                    $built['payload'],
                    $built['totals'],
                    $built['partner_id'],
                );
            }

            $series = $this->seriesForUuid($quote->id);
            $operation->forceFill([
                'document_series_id' => $series->id,
                'state' => 'succeeded',
                'result' => ['quote_uuid' => $quote->id->uuid],
                'error_code' => null,
                'completed_at' => $this->clock->now(),
            ]);
            $operation->save();

            return $quote;
        }, 1);
    }

    public function updateDraft(
        QuoteId $id,
        int $expectedVersion,
        array $payload,
        DocumentTotals $totals,
        ?int $partnerId = null,
    ): QuoteView {
        return DB::transaction(function () use ($id, $expectedVersion, $payload, $totals, $partnerId): QuoteView {
            $series = DocumentSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_series.user_id', $id->ownerId)
                ->where('uuid', $id->uuid)
                ->where('document_type', 'quote')
                ->lockForUpdate()
                ->firstOrFail();
            $quote = QuoteSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_series.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quote->current_revision_id !== null) {
                DocumentRevisionRecord::query()
                    ->withoutGlobalScope('owner')
                    ->where('finance_document_revisions.user_id', $id->ownerId)
                    ->where('document_series_id', $series->id)
                    ->whereKey($quote->current_revision_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $draft = QuoteDraftRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_drafts.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $quote->version !== $expectedVersion) {
                return $this->viewForUuid($id);
            }

            $this->assertNoActivePublication($id->ownerId, (int) $series->id);

            $this->assertOwnedPartner($id->ownerId, $partnerId);
            $updatedAt = $this->clock->now();

            $updated = DB::table('finance_quote_series')
                ->where('user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('version', $expectedVersion)
                ->update([
                    'partner_id' => $partnerId,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $updatedAt,
                ]);

            if ($updated === 1) {
                DB::table('finance_document_series')
                    ->where('id', $series->id)
                    ->where('user_id', $id->ownerId)
                    ->where('document_type', 'quote')
                    ->update(['updated_at' => $updatedAt]);
                $draft->forceFill([
                    ...$this->draftValues($payload, $totals),
                    'updated_by' => $id->ownerId,
                ])->save();
                $this->appendActivity(
                    $id->ownerId,
                    (int) $series->id,
                    'quote.draft.updated',
                    $expectedVersion + 1,
                    $updatedAt,
                );
            }

            return $this->viewForUuid($id);
        }, 1);
    }

    public function discardDraft(QuoteId $id, int $expectedVersion): QuoteView
    {
        return DB::transaction(function () use ($id, $expectedVersion): QuoteView {
            $series = DocumentSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_series.user_id', $id->ownerId)
                ->where('uuid', $id->uuid)
                ->where('document_type', 'quote')
                ->lockForUpdate()
                ->firstOrFail();
            $quote = QuoteSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_series.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quote->current_revision_id !== null) {
                DocumentRevisionRecord::query()
                    ->withoutGlobalScope('owner')
                    ->where('finance_document_revisions.user_id', $id->ownerId)
                    ->where('document_series_id', $series->id)
                    ->whereKey($quote->current_revision_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ((int) $quote->version !== $expectedVersion) {
                return $this->viewForUuid($id);
            }

            $draft = QuoteDraftRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_drafts.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertNoActivePublication($id->ownerId, (int) $series->id);

            if ($draft->based_on_revision_id === null) {
                throw new InvalidQuoteAction('initial_draft_cannot_be_discarded');
            }

            $updatedAt = $this->clock->now();
            $updated = DB::table('finance_quote_series')
                ->where('user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('version', $expectedVersion)
                ->update([
                    'version' => $expectedVersion + 1,
                    'updated_at' => $updatedAt,
                ]);

            if ($updated === 1) {
                DB::table('finance_document_series')
                    ->where('id', $series->id)
                    ->where('user_id', $id->ownerId)
                    ->where('document_type', 'quote')
                    ->update(['updated_at' => $updatedAt]);
                $draft->delete();
                $this->appendActivity(
                    $id->ownerId,
                    (int) $series->id,
                    'quote.draft.discarded',
                    $expectedVersion + 1,
                    $updatedAt,
                );
            }

            return $this->viewForUuid($id);
        }, 1);
    }

    public function startVersion(QuoteId $id, int $expectedVersion): QuoteView
    {
        return DB::transaction(function () use ($id, $expectedVersion): QuoteView {
            $series = DocumentSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_series.user_id', $id->ownerId)
                ->where('uuid', $id->uuid)
                ->where('document_type', 'quote')
                ->lockForUpdate()
                ->firstOrFail();
            $quote = QuoteSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_series.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();
            $currentRevision = $quote->current_revision_id !== null
                ? DocumentRevisionRecord::query()
                    ->withoutGlobalScope('owner')
                    ->where('finance_document_revisions.user_id', $id->ownerId)
                    ->where('document_series_id', $series->id)
                    ->whereKey($quote->current_revision_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;
            $draft = QuoteDraftRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_drafts.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->first();

            if ((int) $quote->version !== $expectedVersion) {
                return $this->viewForUuid($id);
            }
            $this->assertNoActivePublication($id->ownerId, (int) $series->id);
            if ((string) $series->status !== 'sent'
                || $currentRevision === null
                || (string) $currentRevision->status !== 'published'
                || $currentRevision->published_at === null) {
                throw new InvalidQuoteAction('quote_version_not_allowed');
            }
            if ($draft !== null) {
                throw new InvalidQuoteAction('quote_draft_pending');
            }

            $snapshot = $currentRevision->getAttribute('snapshot');
            if (! is_array($snapshot)) {
                throw new LogicException('Quote revision snapshot must be an array.');
            }

            $updatedAt = $this->clock->now();
            $newDraft = new QuoteDraftRecord;
            $newDraft->forceFill([
                'document_series_id' => $series->id,
                'user_id' => $id->ownerId,
                'based_on_revision_id' => $currentRevision->id,
                'payload' => $snapshot,
                'net_minor' => $currentRevision->net_minor,
                'vat_minor' => $currentRevision->vat_minor,
                'gross_minor' => $currentRevision->gross_minor,
                'currency' => $currentRevision->currency,
                'updated_by' => $id->ownerId,
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]);
            $newDraft->save();

            DB::table('finance_quote_series')
                ->where('user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('version', $expectedVersion)
                ->update([
                    'version' => $expectedVersion + 1,
                    'updated_at' => $updatedAt,
                ]);
            DB::table('finance_document_series')
                ->where('id', $series->id)
                ->where('user_id', $id->ownerId)
                ->where('document_type', 'quote')
                ->update(['updated_at' => $updatedAt]);
            $this->appendActivity(
                $id->ownerId,
                (int) $series->id,
                'quote.version.started',
                $expectedVersion + 1,
                $updatedAt,
                ['based_on_revision_id' => (int) $currentRevision->id],
            );

            return $this->viewForUuid($id);
        }, 1);
    }

    public function preparePublication(
        QuoteId $id,
        int $expectedVersion,
        int $operationId,
        callable $allocateNumber,
    ): QuoteView {
        for ($attempt = 1; $attempt <= self::NUMBER_RETRY_ATTEMPTS; $attempt++) {
            try {
                return $this->preparePublicationTransaction(
                    $id,
                    $expectedVersion,
                    $operationId,
                    $allocateNumber,
                );
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isQuoteNumberCollision($exception)
                    || $attempt === self::NUMBER_RETRY_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('The quote number allocation retry loop ended unexpectedly.');
    }

    /**
     * @param  callable(string): array{number: string, year: int, sequence: int}  $allocateNumber
     */
    private function preparePublicationTransaction(
        QuoteId $id,
        int $expectedVersion,
        int $operationId,
        callable $allocateNumber,
    ): QuoteView {
        return DB::transaction(function () use (
            $id,
            $expectedVersion,
            $operationId,
            $allocateNumber,
        ): QuoteView {
            $series = DocumentSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_series.user_id', $id->ownerId)
                ->where('uuid', $id->uuid)
                ->where('document_type', 'quote')
                ->lockForUpdate()
                ->firstOrFail();
            $quote = QuoteSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_series.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentRevision = $quote->current_revision_id !== null
                ? DocumentRevisionRecord::query()
                    ->withoutGlobalScope('owner')
                    ->where('finance_document_revisions.user_id', $id->ownerId)
                    ->where('document_series_id', $series->id)
                    ->whereKey($quote->current_revision_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;

            $draft = QuoteDraftRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_drafts.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();
            $operation = QuoteOperationRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_operations.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('operation', 'publish')
                ->whereKey($operationId)
                ->lockForUpdate()
                ->firstOrFail();
            $winningOperationId = QuoteOperationRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_operations.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('operation', 'publish')
                ->whereIn('state', ['reserved', 'running'])
                ->orderBy('id')
                ->lockForUpdate()
                ->value('id');

            if (! is_int($winningOperationId)
                && (! is_string($winningOperationId) || ! ctype_digit($winningOperationId))) {
                throw new LogicException('A quote publication operation could not be selected.');
            }
            $winningOperationId = is_int($winningOperationId)
                ? $winningOperationId
                : (int) $winningOperationId;

            if ($winningOperationId !== (int) $operation->id) {
                throw new InvalidQuoteAction('operation_in_progress');
            }
            if ((int) $quote->version !== $expectedVersion) {
                throw new InvalidQuoteAction('version_conflict');
            }
            if (! in_array((string) $series->status, ['draft', 'sent'], true)) {
                throw new InvalidQuoteAction('quote_publish_not_allowed');
            }
            if ((string) $series->status === 'sent'
                && ($currentRevision === null
                    || (string) $currentRevision->status !== 'published'
                    || $currentRevision->published_at === null)) {
                throw new InvalidQuoteAction('quote_publish_not_allowed');
            }
            if ((string) $series->status === 'sent'
                && $quote->current_revision_id !== null
                && (int) $draft->based_on_revision_id !== (int) $quote->current_revision_id) {
                throw new InvalidQuoteAction('quote_draft_base_mismatch');
            }

            if ($quote->number === null) {
                $payload = $draft->getAttribute('payload');
                $issueDate = is_array($payload) ? ($payload['issue_date'] ?? null) : null;
                if (! is_string($issueDate)) {
                    throw new LogicException('Quote draft issue date is missing.');
                }
                $allocation = $allocateNumber($issueDate);
                $updatedAt = $this->clock->now();
                DB::table('finance_quote_series')
                    ->where('user_id', $id->ownerId)
                    ->where('document_series_id', $series->id)
                    ->whereNull('number')
                    ->update([
                        'number' => $allocation['number'],
                        'sequence_year' => $allocation['year'],
                        'sequence_number' => $allocation['sequence'],
                        'updated_at' => $updatedAt,
                    ]);
                DB::table('finance_document_series')
                    ->where('id', $series->id)
                    ->where('user_id', $id->ownerId)
                    ->where('document_type', 'quote')
                    ->update(['updated_at' => $updatedAt]);
            }

            return $this->viewForUuid($id);
        }, 1);
    }

    public function finalizePublication(
        QuoteId $id,
        int $expectedVersion,
        DocumentRevisionId $revisionId,
    ): QuoteView {
        return DB::transaction(function () use ($id, $expectedVersion, $revisionId): QuoteView {
            $series = DocumentSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_series.user_id', $id->ownerId)
                ->where('uuid', $id->uuid)
                ->where('document_type', 'quote')
                ->lockForUpdate()
                ->firstOrFail();
            $quote = QuoteSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_series.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();
            $previous = $quote->current_revision_id !== null
                ? DocumentRevisionRecord::query()
                    ->withoutGlobalScope('owner')
                    ->where('finance_document_revisions.user_id', $id->ownerId)
                    ->where('document_series_id', $series->id)
                    ->whereKey($quote->current_revision_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;
            $draft = QuoteDraftRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_drafts.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->first();

            if ((int) $quote->current_revision_id === $revisionId->value
                && (string) $series->status === 'sent'
                && $draft === null) {
                return $this->viewForUuid($id);
            }
            if ((int) $quote->version !== $expectedVersion) {
                throw new InvalidQuoteAction('version_conflict');
            }
            if ($draft === null) {
                throw new InvalidQuoteAction('quote_draft_missing');
            }

            $revision = DocumentRevisionRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_revisions.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->whereKey($revisionId->value)
                ->lockForUpdate()
                ->firstOrFail();
            if ($revision->published_at === null || (string) $revision->status !== 'published') {
                throw new InvalidQuoteAction('quote_revision_not_published');
            }

            $previousId = $previous !== null ? (int) $previous->id : null;
            if (($revision->previous_revision_id !== null ? (int) $revision->previous_revision_id : null) !== $previousId
                || ($draft->based_on_revision_id !== null ? (int) $draft->based_on_revision_id : null) !== $previousId) {
                throw new InvalidQuoteAction('quote_revision_base_mismatch');
            }

            $updatedAt = $this->clock->now();
            $publishedAt = $revision->getAttribute('published_at');
            DB::table('finance_quote_series')
                ->where('user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('version', $expectedVersion)
                ->update([
                    'current_revision_id' => $revision->id,
                    'version' => $expectedVersion + 1,
                    'published_at' => $publishedAt,
                    'updated_at' => $updatedAt,
                ]);
            DB::table('finance_document_series')
                ->where('id', $series->id)
                ->where('user_id', $id->ownerId)
                ->where('document_type', 'quote')
                ->update([
                    'status' => 'sent',
                    'updated_at' => $updatedAt,
                ]);
            $draft->delete();
            $this->appendActivity(
                $id->ownerId,
                (int) $series->id,
                'quote.published',
                $expectedVersion + 1,
                $updatedAt,
                ['revision_id' => (int) $revision->id],
            );
            if ($previousId !== null) {
                $this->appendActivity(
                    $id->ownerId,
                    (int) $series->id,
                    'quote.revision.superseded',
                    $expectedVersion + 1,
                    $updatedAt,
                    [
                        'previous_revision_id' => $previousId,
                        'current_revision_id' => (int) $revision->id,
                    ],
                );
            }

            return $this->viewForUuid($id);
        }, 1);
    }

    public function queueDelivery(
        QuoteId $id,
        int $revisionId,
        int $operationId,
        string $recipient,
        string $deliveryUuid,
        string $messageId,
    ): int {
        return DB::transaction(function () use (
            $id,
            $revisionId,
            $operationId,
            $recipient,
            $deliveryUuid,
            $messageId,
        ): int {
            $series = DocumentSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_series.user_id', $id->ownerId)
                ->where('uuid', $id->uuid)
                ->where('document_type', 'quote')
                ->lockForUpdate()
                ->firstOrFail();
            $quote = QuoteSeriesRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_series.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();
            $revision = DocumentRevisionRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_revisions.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->whereKey($revisionId)
                ->lockForUpdate()
                ->firstOrFail();
            QuoteOperationRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_operations.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('operation', 'send')
                ->whereKey($operationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $quote->current_revision_id !== $revisionId
                || (string) $series->status !== 'sent'
                || (string) $revision->status !== 'published'
                || $revision->published_at === null) {
                throw new InvalidQuoteAction('quote_revision_stale');
            }

            $delivery = QuoteDeliveryRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_deliveries.user_id', $id->ownerId)
                ->where('uuid', $deliveryUuid)
                ->lockForUpdate()
                ->first();
            if ($delivery instanceof QuoteDeliveryRecord) {
                if ((int) $delivery->document_series_id !== (int) $series->id
                    || (int) $delivery->document_revision_id !== $revisionId
                    || ! hash_equals((string) $delivery->recipient, $recipient)) {
                    throw new LogicException('Quote delivery replay identity is inconsistent.');
                }

                return (int) $delivery->id;
            }

            $domain = strrchr($recipient, '@');
            if (! is_string($domain) || strlen($domain) < 2) {
                throw new InvalidArgumentException('Quote delivery recipient has no domain.');
            }
            $queuedAt = $this->clock->now();
            $deliveryId = (int) DB::table('finance_quote_deliveries')->insertGetId([
                'user_id' => $id->ownerId,
                'uuid' => $deliveryUuid,
                'document_series_id' => $series->id,
                'document_revision_id' => $revisionId,
                'recipient' => $recipient,
                'recipient_domain' => strtolower(substr($domain, 1)),
                'message_id' => $messageId,
                'state' => 'queued',
                'attempts' => 0,
                'last_error_code' => null,
                'queued_at' => $queuedAt,
                'sent_at' => null,
                'failed_at' => null,
            ]);
            $activity = new DocumentActivityRecord;
            $activity->forceFill([
                'user_id' => $id->ownerId,
                'document_series_id' => $series->id,
                'document_revision_id' => $revisionId,
                'type' => 'quote.mail.queued',
                'payload' => [
                    'delivery_id' => $deliveryId,
                    'recipient_domain' => strtolower(substr($domain, 1)),
                ],
                'created_by' => $id->ownerId,
                'created_at' => $queuedAt,
            ]);
            $activity->save();

            return $deliveryId;
        }, 1);
    }

    public function decide(
        QuoteId $id,
        int $expectedVersion,
        int $expectedRevisionId,
        string $decision,
        int $operationId,
    ): QuoteView {
        return DB::transaction(function () use (
            $id,
            $expectedVersion,
            $expectedRevisionId,
            $decision,
            $operationId,
        ): QuoteView {
            [$series, $quote, $revision, $draft] = $this->lockedAggregate($id);
            $operation = $this->lockedOperation($id, (int) $series->id, $operationId, $decision === 'accepted' ? 'accept' : 'decline');
            if ((string) $operation->state === 'succeeded') {
                return $this->viewForUuid($id);
            }
            if ((int) $quote->version !== $expectedVersion) {
                throw new InvalidQuoteAction('version_conflict');
            }
            $this->assertNoActivePublication($id->ownerId, (int) $series->id);
            $this->assertActionableRevision(
                $id,
                $series,
                $revision,
                $draft !== null,
                $expectedRevisionId,
                $decision,
            );

            $updatedAt = $this->clock->now();
            $timestampColumn = $decision === 'accepted' ? 'accepted_at' : 'declined_at';
            $updated = DB::table('finance_quote_series')
                ->where('user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('version', $expectedVersion)
                ->update([
                    $timestampColumn => $updatedAt,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $updatedAt,
                ]);
            if ($updated !== 1) {
                throw new LogicException('Quote decision compare-and-swap failed after locking.');
            }
            $seriesUpdated = DB::table('finance_document_series')
                ->where('user_id', $id->ownerId)
                ->where('id', $series->id)
                ->where('document_type', 'quote')
                ->update(['status' => $decision, 'updated_at' => $updatedAt]);
            if ($seriesUpdated !== 1) {
                throw new LogicException('Quote decision status update failed after locking.');
            }
            $this->appendActivity(
                $id->ownerId,
                (int) $series->id,
                'quote.'.$decision,
                $expectedVersion + 1,
                $updatedAt,
                ['revision_id' => $expectedRevisionId],
                $expectedRevisionId,
            );
            $this->completeOperation($operation, [
                'quote_uuid' => $id->uuid,
                'revision_id' => $expectedRevisionId,
                'status' => $decision,
            ], $updatedAt);

            return $this->viewForUuid($id);
        }, 1);
    }

    public function duplicate(
        QuoteId $sourceId,
        int $expectedVersion,
        ?int $sourceRevisionId,
        int $operationId,
        callable $draft,
    ): QuoteView {
        return DB::transaction(function () use (
            $sourceId,
            $expectedVersion,
            $sourceRevisionId,
            $operationId,
            $draft,
        ): QuoteView {
            [$series, $quote, $revision, $pendingDraft] = $this->lockedAggregate($sourceId);
            $operation = $this->lockedOperation($sourceId, (int) $series->id, $operationId, 'duplicate');
            if ((string) $operation->state === 'succeeded') {
                return $this->replayDuplicatedQuote($sourceId->ownerId, $operation);
            }
            if ((int) $quote->version !== $expectedVersion) {
                throw new InvalidQuoteAction('version_conflict');
            }

            if ($sourceRevisionId === null) {
                if ((string) $series->status !== 'draft' || $revision !== null || $pendingDraft === null) {
                    throw new InvalidQuoteAction('quote_revision_stale');
                }
                $sourcePayload = $pendingDraft->getAttribute('payload');
            } else {
                if ($revision === null || (int) $revision->id !== $sourceRevisionId) {
                    $this->throwRevisionMismatch($sourceId, (int) $series->id, $sourceRevisionId);
                }
                $sourcePayload = $revision->getAttribute('snapshot');
            }
            if (! is_array($sourcePayload)) {
                throw new LogicException('Quote duplication source must be an array.');
            }
            $sourcePartnerId = $sourcePayload['partner_id'] ?? null;
            if ($sourcePartnerId !== null && (! is_int($sourcePartnerId) || $sourcePartnerId < 1)) {
                throw new LogicException('Quote duplication source partner is invalid.');
            }

            $built = $draft($sourcePayload, $sourcePartnerId);
            $copy = $this->createDraft(
                $sourceId->ownerId,
                $built['payload'],
                $built['totals'],
                $built['partner_id'],
                'quote_duplicate_operation',
                $operationId,
            );
            $completedAt = $this->clock->now();
            $this->appendActivity(
                $sourceId->ownerId,
                (int) $series->id,
                'quote.duplicated',
                (int) $quote->version,
                $completedAt,
                [
                    'source_revision_id' => $sourceRevisionId,
                    'target_quote_uuid' => $copy->id->uuid,
                ],
                $sourceRevisionId,
            );
            $this->completeOperation($operation, [
                'quote_uuid' => $copy->id->uuid,
                'source_revision_id' => $sourceRevisionId,
            ], $completedAt);

            return $copy;
        }, 1);
    }

    public function convertToInvoice(
        QuoteId $id,
        int $expectedVersion,
        int $expectedRevisionId,
        int $operationId,
        callable $createTarget,
    ): InvoiceDraftTarget {
        return DB::transaction(function () use (
            $id,
            $expectedVersion,
            $expectedRevisionId,
            $operationId,
            $createTarget,
        ): InvoiceDraftTarget {
            [$series, $quote, $revision, $draft] = $this->lockedAggregate($id);
            $operation = $this->lockedOperation($id, (int) $series->id, $operationId, 'convert_invoice');
            if ((string) $operation->state === 'succeeded') {
                return $this->conversionTargetFromOperation($operation);
            }

            $existing = QuoteConversionRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_conversions.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('target_type', 'invoice')
                ->lockForUpdate()
                ->first();
            if ($existing instanceof QuoteConversionRecord) {
                if ((int) $existing->source_revision_id !== $expectedRevisionId) {
                    throw new InvalidQuoteAction('quote_revision_stale');
                }
                $target = new InvoiceDraftTarget(
                    (string) $existing->target_reference,
                    $existing->target_id !== null ? (int) $existing->target_id : null,
                );
                $this->completeOperation($operation, $this->conversionResult($target, $expectedRevisionId), $this->clock->now());

                return $target;
            }
            if ((int) $quote->version !== $expectedVersion) {
                throw new InvalidQuoteAction('version_conflict');
            }
            $this->assertNoActivePublication($id->ownerId, (int) $series->id);
            $this->assertActionableRevision(
                $id,
                $series,
                $revision,
                $draft !== null,
                $expectedRevisionId,
                'converted',
            );
            if (! $revision instanceof DocumentRevisionRecord) {
                throw new LogicException('Accepted quote has no current revision.');
            }
            $snapshot = $revision->getAttribute('snapshot');
            if (! is_array($snapshot)) {
                throw new LogicException('Quote conversion snapshot must be an array.');
            }
            $source = $this->revisionRef($revision);
            $target = $createTarget($source, $snapshot);
            if (! $target instanceof InvoiceDraftTarget) {
                throw new LogicException('Quote conversion target port returned an invalid result.');
            }

            $convertedAt = $this->clock->now();
            $conversion = new QuoteConversionRecord;
            $conversion->forceFill([
                'user_id' => $id->ownerId,
                'document_series_id' => $series->id,
                'source_revision_id' => $revision->id,
                'target_type' => 'invoice',
                'target_reference' => $target->targetReference,
                'target_id' => $target->targetId,
                'created_at' => $convertedAt,
            ])->save();
            $updated = DB::table('finance_quote_series')
                ->where('user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('version', $expectedVersion)
                ->update([
                    'converted_at' => $convertedAt,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $convertedAt,
                ]);
            if ($updated !== 1) {
                throw new LogicException('Quote conversion compare-and-swap failed after locking.');
            }
            $seriesUpdated = DB::table('finance_document_series')
                ->where('user_id', $id->ownerId)
                ->where('id', $series->id)
                ->where('document_type', 'quote')
                ->update(['status' => 'converted', 'updated_at' => $convertedAt]);
            if ($seriesUpdated !== 1) {
                throw new LogicException('Quote conversion status update failed after locking.');
            }
            $this->appendActivity(
                $id->ownerId,
                (int) $series->id,
                'quote.converted',
                $expectedVersion + 1,
                $convertedAt,
                [
                    'revision_id' => $expectedRevisionId,
                    'target_reference' => $target->targetReference,
                ],
                $expectedRevisionId,
            );
            $this->completeOperation($operation, $this->conversionResult($target, $expectedRevisionId), $convertedAt);

            return $target;
        }, 1);
    }

    /**
     * @return array{DocumentSeriesRecord, QuoteSeriesRecord, DocumentRevisionRecord|null, QuoteDraftRecord|null}
     */
    private function lockedAggregate(QuoteId $id): array
    {
        $series = DocumentSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_document_series.user_id', $id->ownerId)
            ->where('uuid', $id->uuid)
            ->where('document_type', 'quote')
            ->lockForUpdate()
            ->firstOrFail();
        $quote = QuoteSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_series.user_id', $id->ownerId)
            ->where('document_series_id', $series->id)
            ->lockForUpdate()
            ->firstOrFail();
        $revision = $quote->current_revision_id !== null
            ? DocumentRevisionRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_revisions.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->whereKey($quote->current_revision_id)
                ->lockForUpdate()
                ->firstOrFail()
            : null;
        $draft = QuoteDraftRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_drafts.user_id', $id->ownerId)
            ->where('document_series_id', $series->id)
            ->lockForUpdate()
            ->first();

        return [$series, $quote, $revision, $draft];
    }

    private function lockedOperation(
        QuoteId $id,
        int $seriesId,
        int $operationId,
        string $operation,
    ): QuoteOperationRecord {
        $record = QuoteOperationRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_operations.user_id', $id->ownerId)
            ->where('document_series_id', $seriesId)
            ->where('operation', $operation)
            ->whereKey($operationId)
            ->lockForUpdate()
            ->firstOrFail();
        if ((string) $record->state === 'failed') {
            throw new InvalidQuoteAction(is_string($record->error_code) ? $record->error_code : 'quote_operation_failed');
        }

        return $record;
    }

    private function assertActionableRevision(
        QuoteId $id,
        DocumentSeriesRecord $series,
        ?DocumentRevisionRecord $revision,
        bool $hasPendingDraft,
        int $expectedRevisionId,
        string $action,
    ): void {
        $status = QuoteStatus::from((string) $series->status);
        if ($status === QuoteStatus::Draft || $revision === null) {
            throw new InvalidQuoteAction('quote_not_published');
        }
        $state = QuoteRevisionState::Current;
        if ((int) $revision->id !== $expectedRevisionId) {
            $replaced = DocumentRevisionRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_document_revisions.user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->whereKey($expectedRevisionId)
                ->lockForUpdate()
                ->first(['id']);
            $state = $replaced !== null ? QuoteRevisionState::Replaced : QuoteRevisionState::Current;
        }
        $snapshot = $revision?->getAttribute('snapshot');
        $validUntil = is_array($snapshot) ? ($snapshot['valid_until'] ?? null) : null;
        if (! is_string($validUntil)) {
            throw new LogicException('Quote revision validity is missing.');
        }
        $timezone = new DateTimeZone($this->settings->ownerTimezone($id->ownerId));
        $validity = DateTimeImmutable::createFromFormat('!Y-m-d', $validUntil, $timezone);
        if (! $validity instanceof DateTimeImmutable || $validity->format('Y-m-d') !== $validUntil) {
            throw new LogicException('Quote revision validity is invalid.');
        }
        $workflow = new QuoteWorkflow;
        $now = $this->clock->now()->setTimezone($timezone);
        if ($action === 'converted') {
            $workflow->assertCurrentRevisionMayBeConverted(
                $status,
                $expectedRevisionId,
                (int) $revision->id,
                $validity,
                $now,
                $hasPendingDraft,
                $state,
            );

            return;
        }
        $workflow->assertCurrentRevisionMayBeDecided(
            $status,
            QuoteStatus::from($action),
            $expectedRevisionId,
            (int) $revision->id,
            $validity,
            $now,
            $hasPendingDraft,
            $state,
        );
    }

    private function throwRevisionMismatch(QuoteId $id, int $seriesId, int $expectedRevisionId): never
    {
        $replaced = DocumentRevisionRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_document_revisions.user_id', $id->ownerId)
            ->where('document_series_id', $seriesId)
            ->whereKey($expectedRevisionId)
            ->lockForUpdate()
            ->first(['id']);

        throw new InvalidQuoteAction($replaced !== null ? 'quote_revision_replaced' : 'quote_revision_stale');
    }

    private function replayDuplicatedQuote(int $ownerId, QuoteOperationRecord $operation): QuoteView
    {
        $result = $operation->getAttribute('result');
        $uuid = is_array($result) ? ($result['quote_uuid'] ?? null) : null;
        if (! is_string($uuid)) {
            throw new LogicException('Completed quote duplication has no replay identity.');
        }

        return $this->viewForUuid(new QuoteId($ownerId, $uuid));
    }

    private function conversionTargetFromOperation(QuoteOperationRecord $operation): InvoiceDraftTarget
    {
        $result = $operation->getAttribute('result');
        $reference = is_array($result) ? ($result['target_reference'] ?? null) : null;
        $targetId = is_array($result) ? ($result['target_id'] ?? null) : null;
        if (! is_string($reference) || ($targetId !== null && ! is_int($targetId))) {
            throw new LogicException('Completed quote conversion has no replay target.');
        }

        return new InvoiceDraftTarget($reference, $targetId);
    }

    /** @return array{target_reference: string, target_id: int|null, source_revision_id: int} */
    private function conversionResult(InvoiceDraftTarget $target, int $sourceRevisionId): array
    {
        return [
            'target_reference' => $target->targetReference,
            'target_id' => $target->targetId,
            'source_revision_id' => $sourceRevisionId,
        ];
    }

    /** @param array<string, mixed> $result */
    private function completeOperation(
        QuoteOperationRecord $operation,
        array $result,
        DateTimeInterface $completedAt,
    ): void {
        $operation->forceFill([
            'state' => 'succeeded',
            'result' => $result,
            'error_code' => null,
            'completed_at' => $completedAt,
        ])->save();
    }

    private function assertOwnedPartner(int $ownerId, ?int $partnerId): void
    {
        if ($partnerId === null) {
            return;
        }

        FinancePartner::query()
            ->withoutGlobalScope('owner')
            ->where('finance_partners.user_id', $ownerId)
            ->whereKey($partnerId)
            ->lockForUpdate()
            ->firstOrFail(['id']);
    }

    private function assertOwnedDraftSource(int $ownerId, ?string $sourceType, ?int $sourceId): void
    {
        if ($sourceType === null || $sourceId === null) {
            return;
        }
        if ($sourceType !== 'quote_duplicate_operation') {
            throw new LogicException('Quote draft provenance type is unsupported.');
        }

        QuoteOperationRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_operations.user_id', $ownerId)
            ->whereKey($sourceId)
            ->where('operation', 'duplicate')
            ->lockForUpdate()
            ->firstOrFail(['id']);
    }

    private function assertNoActivePublication(int $ownerId, int $seriesId): void
    {
        $active = QuoteOperationRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_operations.user_id', $ownerId)
            ->where('document_series_id', $seriesId)
            ->where('operation', 'publish')
            ->whereIn('state', ['reserved', 'running'])
            ->lockForUpdate()
            ->first(['id']);

        if ($active !== null) {
            throw new InvalidQuoteAction('quote_publication_in_progress');
        }
    }

    private function validateCreateOperation(
        int $ownerId,
        string $idempotencyKey,
        string $requestSha256,
    ): void {
        if ($ownerId < 1) {
            throw new InvalidArgumentException('Quote operation owner ID must be positive.');
        }

        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 255) {
            throw new InvalidArgumentException('Quote idempotency key must contain between 1 and 255 bytes.');
        }

        if (preg_match('/\A[0-9a-f]{64}\z/D', $requestSha256) !== 1) {
            throw new InvalidArgumentException('Quote request hash must be canonical lowercase SHA-256 hex.');
        }
    }

    private function isQuoteNumberCollision(UniqueConstraintViolationException $exception): bool
    {
        return in_array($exception->index, [
            'finance_quote_series_owner_number_unique',
            'finance_quote_series_owner_sequence_unique',
        ], true)
            || in_array($exception->columns, [
                ['user_id', 'number'],
                ['user_id', 'sequence_year', 'sequence_number'],
            ], true);
    }

    private function reserveCreateOperation(
        int $ownerId,
        string $idempotencyKey,
        string $requestSha256,
    ): QuoteOperationRecord {
        try {
            $recordId = DB::transaction(fn (): int => (int) DB::table('finance_quote_operations')
                ->insertGetId([
                    'user_id' => $ownerId,
                    'document_series_id' => null,
                    'operation' => 'create',
                    'idempotency_key' => $idempotencyKey,
                    'request_sha256' => $requestSha256,
                    'state' => 'reserved',
                    'result' => null,
                    'error_code' => null,
                    'started_at' => $this->clock->now(),
                    'completed_at' => null,
                ]), 1);

            return QuoteOperationRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_operations.user_id', $ownerId)
                ->lockForUpdate()
                ->findOrFail($recordId);
        } catch (UniqueConstraintViolationException $exception) {
            $operation = QuoteOperationRecord::query()
                ->withoutGlobalScope('owner')
                ->where('finance_quote_operations.user_id', $ownerId)
                ->where('operation', 'create')
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if (! $operation instanceof QuoteOperationRecord) {
                throw $exception;
            }

            return $operation;
        }
    }

    private function replayCreatedQuote(int $ownerId, QuoteOperationRecord $operation): QuoteView
    {
        $result = $operation->getAttribute('result');
        $uuid = is_array($result) ? ($result['quote_uuid'] ?? null) : null;

        if (! is_string($uuid) || $operation->document_series_id === null) {
            throw new LogicException('Completed quote creation has no replay identity.');
        }

        $quote = $this->viewForUuid(new QuoteId($ownerId, $uuid));
        $series = $this->seriesForUuid($quote->id);

        if ((int) $operation->document_series_id !== (int) $series->id) {
            throw new LogicException('Completed quote creation has an inconsistent replay identity.');
        }

        return $quote;
    }

    private function viewForSeriesId(int $ownerId, int $seriesId): QuoteView
    {
        $series = DocumentSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_document_series.user_id', $ownerId)
            ->where('document_type', 'quote')
            ->whereKey($seriesId)
            ->lockForUpdate()
            ->firstOrFail();

        return $this->viewForUuid(new QuoteId($ownerId, (string) $series->uuid));
    }

    /** @param array<string, mixed> $payload */
    private function appendActivity(
        int $ownerId,
        int $seriesId,
        string $type,
        int $version,
        DateTimeInterface $createdAt,
        array $payload = [],
        ?int $revisionId = null,
    ): void {
        $activity = new DocumentActivityRecord;
        $activity->forceFill([
            'user_id' => $ownerId,
            'document_series_id' => $seriesId,
            'document_revision_id' => $revisionId,
            'type' => $type,
            'payload' => ['version' => $version, ...$payload],
            'created_by' => $ownerId,
            'created_at' => $createdAt,
        ]);
        $activity->save();
    }

    private function seriesForUuid(QuoteId $id): DocumentSeriesRecord
    {
        return DocumentSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_document_series.user_id', $id->ownerId)
            ->where('uuid', $id->uuid)
            ->where('document_type', 'quote')
            ->firstOrFail();
    }

    private function viewForUuid(QuoteId $id): QuoteView
    {
        $series = $this->seriesForUuid($id);
        $quote = QuoteSeriesRecord::query()
            ->withoutGlobalScope('owner')
            ->where('finance_quote_series.user_id', $id->ownerId)
            ->with(['draft', 'currentRevision'])
            ->where('document_series_id', $series->id)
            ->firstOrFail();

        return $this->viewFromRecord($id->ownerId, $quote);
    }

    private function viewFromRecord(int $ownerId, QuoteSeriesRecord $quote): QuoteView
    {
        $series = $quote->series;

        if (! $series instanceof DocumentSeriesRecord
            || (int) $series->user_id !== $ownerId
            || $series->document_type !== 'quote') {
            throw new LogicException('Quote series ownership is inconsistent.');
        }

        $draft = $quote->draft;
        $currentRevision = $quote->currentRevision;
        $payload = $draft?->getAttribute('payload');
        $draftPayload = is_array($payload) ? $payload : null;
        $totalsSource = $draft ?? $currentRevision;

        if ($totalsSource === null) {
            throw new LogicException('Quote aggregate has neither a draft nor a current revision.');
        }

        return new QuoteView(
            new QuoteId($ownerId, (string) $series->uuid),
            (string) $series->status,
            $this->effectiveStatus($ownerId, (string) $series->status, $currentRevision),
            $quote->partner_id !== null ? (int) $quote->partner_id : null,
            is_string($quote->number) ? $quote->number : null,
            (int) $quote->version,
            $currentRevision !== null ? $this->revisionRef($currentRevision) : null,
            $draftPayload,
            (int) $totalsSource->net_minor,
            (int) $totalsSource->vat_minor,
            (int) $totalsSource->gross_minor,
            (string) $totalsSource->currency,
            $this->date($quote->published_at),
            $this->date($quote->accepted_at),
            $this->date($quote->declined_at),
            $this->date($quote->converted_at),
            $this->requiredDate($series->created_at),
            $this->requiredDate($series->updated_at),
        );
    }

    /**
     * @param  Builder<QuoteSeriesRecord>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyEffectiveStatusFilter(Builder $query, array $filters, int $ownerId): void
    {
        $effectiveStatus = $filters['effective_status'] ?? null;
        if (! is_string($effectiveStatus)) {
            return;
        }

        $validUntil = DB::getDriverName() === 'pgsql'
            ? "quote_current_revision.snapshot ->> 'valid_until'"
            : "json_extract(quote_current_revision.snapshot, '$.valid_until')";
        $today = $this->clock->now()
            ->setTimezone(new DateTimeZone($this->settings->ownerTimezone($ownerId)))
            ->format('Y-m-d');

        if ($effectiveStatus === 'expired') {
            $query->whereIn('quote_document_series.status', ['sent', 'accepted'])
                ->whereNotNull('quote_current_revision.id')
                ->whereRaw("{$validUntil} IS NOT NULL")
                ->whereRaw("{$validUntil} < ?", [$today]);

            return;
        }

        if ($effectiveStatus === 'sent') {
            $query->where('quote_document_series.status', 'sent')
                ->where(static function (Builder $query) use ($validUntil, $today): void {
                    $query->whereNull('quote_current_revision.id')
                        ->orWhereRaw("{$validUntil} IS NULL")
                        ->orWhereRaw("{$validUntil} >= ?", [$today]);
                });

            return;
        }

        if ($effectiveStatus === 'accepted') {
            $query->where('quote_document_series.status', 'accepted')
                ->where(static function (Builder $query) use ($validUntil, $today): void {
                    $query->whereNull('quote_current_revision.id')
                        ->orWhereRaw("{$validUntil} IS NULL")
                        ->orWhereRaw("{$validUntil} >= ?", [$today]);
                });

            return;
        }

        $query->where('quote_document_series.status', $effectiveStatus);
    }

    /**
     * @param  Builder<QuoteSeriesRecord>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyPublishedDateFilters(Builder $query, array $filters, int $ownerId): void
    {
        $timezone = new DateTimeZone($this->settings->ownerTimezone($ownerId));
        $from = $filters['published_from'] ?? null;
        if (is_string($from)) {
            $fromUtc = (new DateTimeImmutable($from.' 00:00:00', $timezone))
                ->setTimezone(new DateTimeZone('UTC'));
            $query->where('finance_quote_series.published_at', '>=', $fromUtc);
        }

        $to = $filters['published_to'] ?? null;
        if (is_string($to)) {
            $afterToUtc = (new DateTimeImmutable($to.' 00:00:00', $timezone))
                ->modify('+1 day')
                ->setTimezone(new DateTimeZone('UTC'));
            $query->where('finance_quote_series.published_at', '<', $afterToUtc);
        }
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array{payload: array<array-key, mixed>, net_minor: int, vat_minor: int, gross_minor: int, currency: string}
     */
    private function draftValues(array $payload, DocumentTotals $totals): array
    {
        $currency = $totals->net->currency();

        if ($totals->vat->currency() !== $currency || $totals->gross->currency() !== $currency) {
            throw new LogicException('Quote totals must use one currency.');
        }

        return [
            'payload' => $payload,
            'net_minor' => $totals->net->minor(),
            'vat_minor' => $totals->vat->minor(),
            'gross_minor' => $totals->gross->minor(),
            'currency' => $currency,
        ];
    }

    private function revisionRef(DocumentRevisionRecord $revision): QuoteRevisionRef
    {
        $snapshot = $revision->getAttribute('snapshot');

        if (! is_array($snapshot)) {
            throw new LogicException('Quote revision snapshot must be an array.');
        }

        return new QuoteRevisionRef(
            (int) $revision->id,
            (int) $revision->revision_number,
            $revision->previous_revision_id !== null ? (int) $revision->previous_revision_id : null,
            (string) $revision->status,
            $snapshot,
            (int) $revision->net_minor,
            (int) $revision->vat_minor,
            (int) $revision->gross_minor,
            (string) $revision->currency,
            is_string($revision->pdf_path) ? $revision->pdf_path : null,
            is_string($revision->pdf_sha256) ? $revision->pdf_sha256 : null,
            $this->date($revision->published_at),
            $this->requiredDate($revision->created_at),
        );
    }

    private function effectiveStatus(
        int $ownerId,
        string $status,
        ?DocumentRevisionRecord $revision,
    ): string {
        if (! in_array($status, ['sent', 'accepted'], true) || $revision === null) {
            return $status;
        }

        $snapshot = $revision->getAttribute('snapshot');
        $validUntil = is_array($snapshot) ? ($snapshot['valid_until'] ?? null) : null;

        if (is_string($validUntil)) {
            $timezone = new DateTimeZone($this->settings->ownerTimezone($ownerId));
            $validThrough = new DateTimeImmutable($validUntil, $timezone)
                ->setTime(23, 59, 59, 999_999);
            $now = $this->clock->now()->setTimezone($timezone);

            if ($now > $validThrough) {
                return 'expired';
            }
        }

        return $status;
    }

    private function requiredDate(mixed $value): DateTimeImmutable
    {
        return $this->date($value)
            ?? throw new LogicException('Quote timestamp is missing.');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : null;
    }
}
