<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Models\User;
use App\Modules\Finance\Application\DTOs\Projects\AppendDocumentNoteData;
use App\Modules\Finance\Application\DTOs\Projects\AppendProjectNoteData;
use App\Modules\Finance\Application\DTOs\Projects\HistoryItemView;
use App\Modules\Finance\Application\DTOs\Projects\HistoryPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectNoteFilter;
use App\Modules\Finance\Application\Ports\Projects\ProjectHistoryRepository;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentNoteRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectNoteRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class EloquentProjectHistoryRepository implements ProjectHistoryRepository
{
    private const ACTIVITY_PAYLOAD_KEYS = [
        'amount_minor', 'availability', 'category_reference', 'changes', 'claim_reference',
        'currency', 'direction', 'document_label', 'document_type', 'due_date', 'entry_count',
        'error_code', 'from_status', 'gross_minor', 'invoice_uuid', 'link_id',
        'metadata', 'net_minor', 'note_id', 'number', 'operation', 'parent_uuid', 'payment_reference',
        'pdf_sha256', 'previous', 'project_uuid', 'quantity_scaled', 'reason_code', 'revision_id',
        'revision_number', 'role', 'sha256', 'snapshot_sha256', 'source_reference', 'source_type',
        'status', 'time_entry_ids', 'to_status', 'type', 'vat_minor', 'version', 'visibility',
    ];

    public function appendProjectNote(AppendProjectNoteData $data): HistoryItemView
    {
        $this->assertCurrentActor($data->projectId->ownerId, $data->actorId);

        return DB::transaction(function () use ($data): HistoryItemView {
            $projectId = $this->projectRecordId($data->projectId, true);
            if ($data->supersedesNoteId !== null) {
                $this->projectNoteRecord($data->projectId->ownerId, $projectId, $data->supersedesNoteId, true);
            }
            $timestamp = $this->timestamp($data->occurredAt);
            $noteId = (int) DB::table('finance_project_notes')->insertGetId([
                'user_id' => $data->projectId->ownerId,
                'project_id' => $projectId,
                'type' => $data->type,
                'visibility' => $data->visibility,
                'body' => $data->body,
                'supersedes_note_id' => $data->supersedesNoteId,
                'created_by' => $data->actorId,
                'created_at' => $timestamp,
            ]);
            DB::table('finance_project_activities')->insert([
                'user_id' => $data->projectId->ownerId,
                'project_id' => $projectId,
                'type' => 'project.note_added',
                'subject_type' => 'project_note',
                'subject_reference' => (string) $noteId,
                'payload' => json_encode([
                    'note_id' => $noteId,
                    'type' => $data->type,
                    'visibility' => $data->visibility,
                ], JSON_THROW_ON_ERROR),
                'created_by' => $data->actorId,
                'occurred_at' => $timestamp,
                'created_at' => $timestamp,
            ]);

            return $this->noteView(
                'project_note',
                $noteId,
                $data->type,
                $data->visibility,
                $data->body,
                $data->supersedesNoteId,
                $data->actorId,
                $data->occurredAt,
            );
        });
    }

    public function appendDocumentNote(AppendDocumentNoteData $data): HistoryItemView
    {
        $this->assertCurrentActor($data->ownerId, $data->actorId);

        return DB::transaction(function () use ($data): HistoryItemView {
            $series = $this->seriesRecord($data->ownerId, $data->seriesUuid, true);
            $seriesId = $this->requiredInt($series->getKey());
            if ($data->revisionId !== null) {
                $revisionExists = DB::table('finance_document_revisions')
                    ->where('user_id', $data->ownerId)
                    ->where('document_series_id', $seriesId)
                    ->where('id', $data->revisionId)
                    ->lockForUpdate()
                    ->exists();
                if (! $revisionExists) {
                    throw (new ModelNotFoundException)->setModel(DocumentRevisionRecord::class, [$data->revisionId]);
                }
            }
            if ($data->supersedesNoteId !== null) {
                $exists = DB::table('finance_document_notes')
                    ->where('user_id', $data->ownerId)
                    ->where('document_series_id', $seriesId)
                    ->where('id', $data->supersedesNoteId)
                    ->lockForUpdate()
                    ->exists();
                if (! $exists) {
                    throw (new ModelNotFoundException)->setModel(DocumentNoteRecord::class, [$data->supersedesNoteId]);
                }
            }
            $timestamp = $this->timestamp($data->occurredAt);
            $noteId = (int) DB::table('finance_document_notes')->insertGetId([
                'user_id' => $data->ownerId,
                'document_series_id' => $seriesId,
                'document_revision_id' => $data->revisionId,
                'type' => $data->type,
                'visibility' => $data->visibility,
                'body' => $data->body,
                'supersedes_note_id' => $data->supersedesNoteId,
                'created_by' => $data->actorId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return $this->noteView(
                'document_note',
                $noteId,
                $data->type,
                $data->visibility,
                $data->body,
                $data->supersedesNoteId,
                $data->actorId,
                $data->occurredAt,
                $data->seriesUuid,
                $data->revisionId,
            );
        });
    }

    public function projectNotes(ProjectId $projectId, ProjectNoteFilter $filter): HistoryPage
    {
        $recordId = $this->projectRecordId($projectId);
        $query = DB::table('finance_project_notes')
            ->where('user_id', $projectId->ownerId)
            ->where('project_id', $recordId);
        $this->applyNoteFilter($query, $filter);

        return $this->notePage($query, $filter, 'project_note');
    }

    public function documentNotes(int $ownerId, string $seriesUuid, ProjectNoteFilter $filter): HistoryPage
    {
        if ($ownerId < 1) {
            throw new ModelNotFoundException;
        }
        $canonicalUuid = strtolower($seriesUuid);
        $series = $this->seriesRecord($ownerId, $canonicalUuid);
        $query = DB::table('finance_document_notes')
            ->where('user_id', $ownerId)
            ->where('document_series_id', $this->requiredInt($series->getKey()));
        $this->applyNoteFilter($query, $filter);

        return $this->notePage($query, $filter, 'document_note', $canonicalUuid);
    }

    public function projectActivity(ProjectId $projectId, ?string $cursor, int $perPage): HistoryPage
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('project_activity_page_invalid');
        }
        $recordId = $this->projectRecordId($projectId);
        $state = $cursor === null
            ? $this->initialActivityState($projectId, $recordId)
            : $this->decodeActivityCursor($projectId, $cursor);
        $series = $this->linkedSeriesSnapshot($projectId->ownerId, $recordId, $state['link_high_water']);

        $projectQuery = DB::table('finance_project_activities')
            ->where('user_id', $projectId->ownerId)
            ->where('project_id', $recordId)
            ->where('id', '<=', $state['project_high_water']);
        $this->applyActivityBoundary($projectQuery, 'project', 'occurred_at', 'id', $state['last']);
        $projectRows = $projectQuery
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($perPage + 1)
            ->get();

        $documentRows = collect();
        if ($series !== []) {
            $documentQuery = DB::table('finance_document_activities as activity')
                ->join('finance_document_series as series', function (JoinClause $join): void {
                    $join->on('series.id', '=', 'activity.document_series_id')
                        ->on('series.user_id', '=', 'activity.user_id');
                })
                ->where('activity.user_id', $projectId->ownerId)
                ->whereIn('activity.document_series_id', array_keys($series))
                ->where('activity.id', '<=', $state['document_high_water'])
                ->select([
                    'activity.id', 'activity.type', 'activity.payload', 'activity.created_by',
                    'activity.created_at', 'activity.document_series_id', 'activity.document_revision_id',
                    'series.uuid as series_uuid',
                ]);
            $this->applyActivityBoundary($documentQuery, 'document', 'activity.created_at', 'activity.id', $state['last']);
            $documentRows = $documentQuery
                ->orderByDesc('activity.created_at')
                ->orderByDesc('activity.id')
                ->limit($perPage + 1)
                ->get();
        }

        $items = [];
        foreach ($projectRows as $record) {
            if (! is_object($record)) {
                throw new LogicException('Stored project activity is invalid.');
            }
            $view = $this->projectActivityView(get_object_vars($record));
            $items['project:'.$view->sourceId] = $view;
        }
        foreach ($documentRows as $record) {
            if (! is_object($record)) {
                throw new LogicException('Stored document activity is invalid.');
            }
            $view = $this->documentActivityView(get_object_vars($record));
            $items['document:'.$view->sourceId] = $view;
        }
        $merged = array_values($items);
        usort($merged, $this->compareActivity(...));
        $hasMore = count($merged) > $perPage;
        $pageItems = array_slice($merged, 0, $perPage);
        $nextCursor = null;
        if ($hasMore && $pageItems !== []) {
            $last = $pageItems[array_key_last($pageItems)];
            $state['last'] = [
                'occurred_at' => $this->timestamp($last->occurredAt),
                'source_kind' => $last->sourceKind,
                'source_id' => $last->sourceId,
            ];
            $nextCursor = $this->encodeActivityCursor($projectId, $state);
        }

        return new HistoryPage($pageItems, $perPage, nextCursor: $nextCursor);
    }

    /** @param array<mixed> $record */
    private function projectActivityView(array $record): HistoryItemView
    {
        return new HistoryItemView(
            'project',
            $this->requiredInt($record['id'] ?? null),
            $this->requiredString($record['type'] ?? null),
            null,
            null,
            null,
            $this->optionalString($record['subject_type'] ?? null),
            $this->optionalString($record['subject_reference'] ?? null),
            $this->safePayload($record['payload'] ?? null),
            $this->optionalInt($record['created_by'] ?? null),
            new DateTimeImmutable($this->requiredString($record['occurred_at'] ?? null)),
        );
    }

    /** @param array<mixed> $record */
    private function documentActivityView(array $record): HistoryItemView
    {
        $seriesUuid = $this->requiredString($record['series_uuid'] ?? null);

        return new HistoryItemView(
            'document',
            $this->requiredInt($record['id'] ?? null),
            $this->requiredString($record['type'] ?? null),
            null,
            null,
            null,
            'document_series',
            $seriesUuid,
            $this->safePayload($record['payload'] ?? null),
            $this->optionalInt($record['created_by'] ?? null),
            new DateTimeImmutable($this->requiredString($record['created_at'] ?? null)),
            $seriesUuid,
            $this->optionalInt($record['document_revision_id'] ?? null),
        );
    }

    /**
     * @return array{project_high_water:int,document_high_water:int,link_high_water:int,last:null}
     */
    private function initialActivityState(ProjectId $projectId, int $recordId): array
    {
        $linkHighWater = $this->optionalInt(DB::table('finance_project_document_links')
            ->where('user_id', $projectId->ownerId)
            ->where('project_id', $recordId)
            ->max('id')) ?? 0;
        $series = $this->linkedSeriesSnapshot($projectId->ownerId, $recordId, $linkHighWater);

        return [
            'project_high_water' => $this->optionalInt(DB::table('finance_project_activities')
                ->where('user_id', $projectId->ownerId)
                ->where('project_id', $recordId)
                ->max('id')) ?? 0,
            'document_high_water' => $series === [] ? 0 : ($this->optionalInt(DB::table('finance_document_activities')
                ->where('user_id', $projectId->ownerId)
                ->whereIn('document_series_id', array_keys($series))
                ->max('id')) ?? 0),
            'link_high_water' => $linkHighWater,
            'last' => null,
        ];
    }

    /** @return array<int,string> */
    private function linkedSeriesSnapshot(int $ownerId, int $projectId, int $linkHighWater): array
    {
        if ($linkHighWater < 1) {
            return [];
        }
        $rows = DB::table('finance_project_document_links as link')
            ->join('finance_document_series as series', function (JoinClause $join): void {
                $join->on('series.id', '=', 'link.document_series_id')
                    ->on('series.user_id', '=', 'link.user_id');
            })
            ->where('link.user_id', $ownerId)
            ->where('link.project_id', $projectId)
            ->where('link.id', '<=', $linkHighWater)
            ->where('link.source_type', 'finance_series')
            ->whereNotNull('link.document_series_id')
            ->select(['link.document_series_id', 'series.uuid'])
            ->distinct()
            ->orderBy('link.document_series_id')
            ->get();
        $series = [];
        foreach ($rows as $row) {
            if (! is_object($row)) {
                throw new LogicException('Stored document link is invalid.');
            }
            $values = get_object_vars($row);
            $series[$this->requiredInt($values['document_series_id'] ?? null)] = $this->requiredString($values['uuid'] ?? null);
        }

        return $series;
    }

    /**
     * @param  array{occurred_at:string,source_kind:string,source_id:int}|null  $last
     */
    private function applyActivityBoundary(QueryBuilder $query, string $sourceKind, string $timeColumn, string $idColumn, ?array $last): void
    {
        if ($last === null) {
            return;
        }
        $kindComparison = strcmp($sourceKind, $last['source_kind']);
        if ($kindComparison > 0) {
            $query->where($timeColumn, '<=', $last['occurred_at']);

            return;
        }
        if ($kindComparison < 0) {
            $query->where($timeColumn, '<', $last['occurred_at']);

            return;
        }
        $query->where(function (QueryBuilder $boundary) use ($timeColumn, $idColumn, $last): void {
            $boundary->where($timeColumn, '<', $last['occurred_at'])
                ->orWhere(function (QueryBuilder $tie) use ($timeColumn, $idColumn, $last): void {
                    $tie->where($timeColumn, '=', $last['occurred_at'])
                        ->where($idColumn, '<', $last['source_id']);
                });
        });
    }

    private function compareActivity(HistoryItemView $left, HistoryItemView $right): int
    {
        $time = $right->occurredAt <=> $left->occurredAt;
        if ($time !== 0) {
            return $time;
        }
        $kind = strcmp($left->sourceKind, $right->sourceKind);
        if ($kind !== 0) {
            return $kind;
        }

        return $right->sourceId <=> $left->sourceId;
    }

    /** @return array<string,mixed> */
    private function safePayload(mixed $payload): array
    {
        if (is_string($payload)) {
            try {
                $payload = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }
        }
        if (! is_array($payload)) {
            return [];
        }

        return $this->sanitizePayloadMap($payload);
    }

    /**
     * @param  array<array-key,mixed>  $payload
     * @return array<string,mixed>
     */
    private function sanitizePayloadMap(array $payload): array
    {
        $safe = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! in_array($key, self::ACTIVITY_PAYLOAD_KEYS, true)) {
                continue;
            }
            $sanitized = $this->sanitizePayloadValue($value);
            if ($sanitized !== null || $value === null) {
                $safe[$key] = $sanitized;
            }
        }

        return $safe;
    }

    private function sanitizePayloadValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 2_048);
        }
        if (! is_array($value)) {
            return null;
        }
        if (array_is_list($value)) {
            $safe = [];
            foreach (array_slice($value, 0, 100) as $item) {
                $sanitized = $this->sanitizePayloadValue($item);
                if ($sanitized !== null || $item === null) {
                    $safe[] = $sanitized;
                }
            }

            return $safe;
        }

        return $this->sanitizePayloadMap($value);
    }

    /**
     * @param  array{project_high_water:int,document_high_water:int,link_high_water:int,last:array{occurred_at:string,source_kind:string,source_id:int}|null}  $state
     */
    private function encodeActivityCursor(ProjectId $projectId, array $state): string
    {
        $payload = $this->base64UrlEncode(json_encode([
            'v' => 1,
            'filter' => $this->activityDigest($projectId),
            ...$state,
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.hash_hmac('sha256', $payload, $this->cursorKey());
    }

    /**
     * @return array{project_high_water:int,document_high_water:int,link_high_water:int,last:array{occurred_at:string,source_kind:string,source_id:int}|null}
     */
    private function decodeActivityCursor(ProjectId $projectId, string $cursor): array
    {
        $parts = explode('.', $cursor);
        if (count($parts) !== 2 || ! hash_equals(hash_hmac('sha256', $parts[0], $this->cursorKey()), $parts[1])) {
            throw new InvalidArgumentException('project_activity_cursor_invalid');
        }
        $decoded = $this->base64UrlDecode($parts[0]);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        if (! is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || ($payload['filter'] ?? null) !== $this->activityDigest($projectId)
            || ! is_int($payload['project_high_water'] ?? null)
            || ! is_int($payload['document_high_water'] ?? null)
            || ! is_int($payload['link_high_water'] ?? null)
            || $payload['project_high_water'] < 0
            || $payload['document_high_water'] < 0
            || $payload['link_high_water'] < 0) {
            throw new InvalidArgumentException('project_activity_cursor_invalid');
        }
        $last = $payload['last'] ?? null;
        if (! is_array($last)
            || ! is_string($last['occurred_at'] ?? null)
            || ! in_array($last['source_kind'] ?? null, ['document', 'project'], true)
            || ! is_int($last['source_id'] ?? null)
            || $last['source_id'] < 1) {
            throw new InvalidArgumentException('project_activity_cursor_invalid');
        }
        try {
            $time = new DateTimeImmutable($last['occurred_at']);
        } catch (\Throwable) {
            throw new InvalidArgumentException('project_activity_cursor_invalid');
        }
        if ($this->timestamp($time) !== $last['occurred_at']) {
            throw new InvalidArgumentException('project_activity_cursor_invalid');
        }

        return [
            'project_high_water' => $payload['project_high_water'],
            'document_high_water' => $payload['document_high_water'],
            'link_high_water' => $payload['link_high_water'],
            'last' => [
                'occurred_at' => $last['occurred_at'],
                'source_kind' => $last['source_kind'],
                'source_id' => $last['source_id'],
            ],
        ];
    }

    private function activityDigest(ProjectId $projectId): string
    {
        return hash('sha256', json_encode([
            'owner_id' => $projectId->ownerId,
            'project_uuid' => strtolower($projectId->uuid),
        ], JSON_THROW_ON_ERROR));
    }

    private function cursorKey(): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new LogicException('Application key is required for project activity cursors.');
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $encoded = strtr($value, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($encoded, true);
    }

    private function requiredInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException('Stored history integer is invalid.');
    }

    private function optionalInt(mixed $value): ?int
    {
        return $value === null ? null : $this->requiredInt($value);
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new LogicException('Stored history string is invalid.');
        }

        return $value;
    }

    private function optionalString(mixed $value): ?string
    {
        return $value === null ? null : $this->requiredString($value);
    }

    private function assertCurrentActor(int $ownerId, int $actorId): void
    {
        // The current application has no delegated Finance-user authority model.
        if ($ownerId !== $actorId) {
            throw (new ModelNotFoundException)->setModel(User::class, [$actorId]);
        }
    }

    private function projectRecordId(ProjectId $projectId, bool $lock = false): int
    {
        $query = ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $projectId->ownerId)
            ->where('uuid', strtolower($projectId->uuid));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $this->requiredInt($query->firstOrFail(['id'])->getKey());
    }

    private function seriesRecord(int $ownerId, string $seriesUuid, bool $lock = false): DocumentSeriesRecord
    {
        $query = DocumentSeriesRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('uuid', strtolower($seriesUuid));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail(['id', 'uuid']);
    }

    private function projectNoteRecord(int $ownerId, int $projectId, int $noteId, bool $lock): object
    {
        $query = DB::table('finance_project_notes')
            ->where('user_id', $ownerId)
            ->where('project_id', $projectId)
            ->where('id', $noteId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $record = $query->first();
        if ($record === null) {
            throw (new ModelNotFoundException)->setModel(ProjectNoteRecord::class, [$noteId]);
        }

        return $record;
    }

    /** @param Builder $query */
    private function applyNoteFilter($query, ProjectNoteFilter $filter): void
    {
        if ($filter->q !== null && trim($filter->q) !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filter->q)));
            $query->whereRaw("LOWER(body) LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
        }
        if ($filter->types !== []) {
            $query->whereIn('type', array_values(array_unique($filter->types)));
        }
        if ($filter->visibilities !== []) {
            $query->whereIn('visibility', array_values(array_unique($filter->visibilities)));
        }
        if ($filter->authorId !== null) {
            $query->where('created_by', $filter->authorId);
        }
        if ($filter->from !== null) {
            $query->where('created_at', '>=', $this->timestamp($filter->from));
        }
        if ($filter->to !== null) {
            $query->where('created_at', '<=', $this->timestamp($filter->to));
        }
    }

    /** @param Builder $query */
    private function notePage($query, ProjectNoteFilter $filter, string $sourceKind, ?string $seriesUuid = null): HistoryPage
    {
        $total = (clone $query)->count();
        $records = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset(($filter->page - 1) * $filter->perPage)
            ->limit($filter->perPage)
            ->get();
        $items = [];
        foreach ($records as $record) {
            if (! is_object($record)) {
                throw new LogicException('Stored note is invalid.');
            }
            $values = get_object_vars($record);
            $items[] = $this->noteView(
                $sourceKind,
                $this->requiredInt($values['id'] ?? null),
                $this->requiredString($values['type'] ?? null),
                $this->requiredString($values['visibility'] ?? null),
                $this->requiredString($values['body'] ?? null),
                $this->optionalInt($values['supersedes_note_id'] ?? null),
                $this->optionalInt($values['created_by'] ?? null),
                new DateTimeImmutable($this->requiredString($values['created_at'] ?? null)),
                $seriesUuid,
                $this->optionalInt($values['document_revision_id'] ?? null),
            );
        }

        return new HistoryPage($items, $filter->perPage, $filter->page, $total);
    }

    private function noteView(
        string $sourceKind,
        int $sourceId,
        string $type,
        string $visibility,
        string $body,
        ?int $supersedesNoteId,
        ?int $authorId,
        DateTimeImmutable $occurredAt,
        ?string $seriesUuid = null,
        ?int $revisionId = null,
    ): HistoryItemView {
        return new HistoryItemView(
            $sourceKind,
            $sourceId,
            $type,
            $visibility,
            $body,
            $supersedesNoteId,
            null,
            null,
            [],
            $authorId,
            $occurredAt,
            $seriesUuid,
            $revisionId,
        );
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.u');
    }
}
