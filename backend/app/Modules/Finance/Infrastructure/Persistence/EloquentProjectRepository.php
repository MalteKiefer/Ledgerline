<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\Projects\ChangeProjectStatusData;
use App\Modules\Finance\Application\DTOs\Projects\CreateProjectData;
use App\Modules\Finance\Application\DTOs\Projects\MoveProjectData;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectListFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectMutationResult;
use App\Modules\Finance\Application\DTOs\Projects\ProjectPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\DTOs\Projects\UpdateProjectData;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\ProjectKind;
use App\Modules\Finance\Domain\Projects\ProjectStatus;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class EloquentProjectRepository implements ProjectRepository
{
    public function __construct(private readonly Clock $clock) {}

    public function get(ProjectId $id): ProjectView
    {
        return $this->view($this->record($id));
    }

    public function page(ProjectListFilter $filter): ProjectPage
    {
        $query = ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('finance_project_records.user_id', $filter->ownerId)
            ->when(
                $filter->archived,
                static fn (Builder $query): Builder => $query->whereNotNull('archived_at'),
                static fn (Builder $query): Builder => $query->whereNull('archived_at'),
            );

        if ($filter->q !== null && trim($filter->q) !== '') {
            $escaped = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                mb_strtolower(trim($filter->q)),
            );
            $needle = '%'.$escaped.'%';
            $query->where(function (Builder $query) use ($needle, $filter): void {
                $query->whereRaw("LOWER(finance_project_records.name) LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereExists(function (QueryBuilder $notes) use ($needle, $filter): void {
                        $notes->selectRaw('1')
                            ->from('finance_project_notes')
                            ->whereColumn('finance_project_notes.project_id', 'finance_project_records.id')
                            ->where('finance_project_notes.user_id', $filter->ownerId)
                            ->whereRaw("LOWER(finance_project_notes.body) LIKE ? ESCAPE '!'", [$needle]);
                    });
            });
        }

        $this->applyFilters($query, $filter);
        if ($filter->parentUuid !== null) {
            $parentId = ProjectRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $filter->ownerId)
                ->where('uuid', $filter->parentUuid)
                ->value('id');
            if (! is_numeric($parentId)) {
                return new ProjectPage([], $filter->page, $filter->perPage, 0);
            }
            $query->where('parent_project_id', (int) $parentId);
        }

        $total = (clone $query)->count();
        $sortColumn = match ($filter->sort) {
            'name' => 'finance_project_records.name',
            'starts_on' => 'finance_project_records.starts_on',
            'due_on' => 'finance_project_records.due_on',
            'status' => 'finance_project_records.status',
            default => 'finance_project_records.updated_at',
        };
        $direction = $filter->direction === 'asc' ? 'asc' : 'desc';
        if ($filter->sort === 'starts_on') {
            $query->orderByRaw('CASE WHEN finance_project_records.starts_on IS NULL THEN 1 ELSE 0 END ASC');
        } elseif ($filter->sort === 'due_on') {
            $query->orderByRaw('CASE WHEN finance_project_records.due_on IS NULL THEN 1 ELSE 0 END ASC');
        }
        if ($filter->sort === 'name') {
            $query->orderBy(DB::raw('LOWER(finance_project_records.name)'), $direction);
        } else {
            $query->orderBy($sortColumn, $direction);
        }
        $records = $query
            ->orderBy('finance_project_records.id', $direction)
            ->offset(($filter->page - 1) * $filter->perPage)
            ->limit($filter->perPage)
            ->get();

        return new ProjectPage(
            array_values($records->map(fn (ProjectRecord $record): ProjectView => $this->view($record))->all()),
            $filter->page,
            $filter->perPage,
            $total,
        );
    }

    public function create(CreateProjectData $data): ProjectView
    {
        return DB::transaction(function () use ($data): ProjectView {
            $parentId = $this->lockedParentId($data->ownerId, $data->parentId);
            $timestamp = $data->occurredAt->format('Y-m-d H:i:s.u');
            $id = (int) DB::table('finance_project_records')->insertGetId([
                'user_id' => $data->ownerId,
                'uuid' => (string) Str::uuid(),
                'parent_project_id' => $parentId,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'name' => $data->name,
                'kind' => $data->kind->value,
                'status' => ProjectStatus::Planned->value,
                'partner_reference' => $data->partnerReference,
                'starts_on' => $this->dateValue($data->startsOn),
                'due_on' => $this->dateValue($data->dueOn),
                'budget_minor' => $data->budget->minor(),
                'currency' => $data->budget->currency(),
                'version' => 0,
                'archived_at' => null,
                'created_by' => $data->actorId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return $this->view($this->ownedRecordByInternalId($data->ownerId, $id));
        }, 1);
    }

    public function update(UpdateProjectData $data): ProjectMutationResult
    {
        return DB::transaction(function () use ($data): ProjectMutationResult {
            $record = $this->lockedRecord($data->projectId);
            $applied = $this->compareAndSwap($record, $data->expectedVersion, [
                'name' => $data->name,
                'kind' => $data->kind->value,
                'partner_reference' => $data->partnerReference,
                'starts_on' => $this->dateValue($data->startsOn),
                'due_on' => $this->dateValue($data->dueOn),
                'budget_minor' => $data->budget->minor(),
                'currency' => $data->budget->currency(),
            ]);

            return $this->mutationResult($applied, $data->projectId->ownerId, (int) $record->id);
        }, 1);
    }

    public function changeStatus(ChangeProjectStatusData $data): ProjectMutationResult
    {
        return DB::transaction(function () use ($data): ProjectMutationResult {
            $record = $this->lockedRecord($data->projectId);
            $applied = $this->compareAndSwap($record, $data->expectedVersion, ['status' => $data->target->value]);

            return $this->mutationResult($applied, $data->projectId->ownerId, (int) $record->id);
        }, 1);
    }

    public function move(MoveProjectData $data): ProjectMutationResult
    {
        return DB::transaction(function () use ($data): ProjectMutationResult {
            $record = $this->record($data->projectId);
            $newParentId = $this->parentInternalId($data->projectId->ownerId, $data->parentId);
            $hierarchy = $this->lockedOwnerHierarchy($data->projectId->ownerId);
            $lockedRecord = $hierarchy[(int) $record->id] ?? null;
            if (! $lockedRecord instanceof ProjectRecord) {
                throw (new ModelNotFoundException)->setModel(ProjectRecord::class, [(int) $record->id]);
            }
            if ((int) $lockedRecord->version !== $data->expectedVersion) {
                return $this->mutationResult(false, $data->projectId->ownerId, (int) $record->id);
            }
            $this->assertValidParent($lockedRecord, $newParentId, $hierarchy);

            $applied = $this->compareAndSwap($lockedRecord, $data->expectedVersion, ['parent_project_id' => $newParentId]);

            return $this->mutationResult($applied, $data->projectId->ownerId, (int) $record->id);
        }, 1);
    }

    /** @return array<int, ProjectRecord> */
    private function lockedOwnerHierarchy(int $ownerId): array
    {
        $records = ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'user_id', 'parent_project_id', 'archived_at', 'version']);
        $byId = [];
        foreach ($records as $record) {
            $byId[(int) $record->id] = $record;
        }

        return $byId;
    }

    /** @param array<int, ProjectRecord> $hierarchy */
    private function assertValidParent(
        ProjectRecord $project,
        ?int $parentId,
        array $hierarchy,
    ): void {
        $visited = [];
        while ($parentId !== null) {
            if ($parentId === (int) $project->id || isset($visited[$parentId])) {
                throw new InvalidProjectAction('project_parent_cycle');
            }
            $visited[$parentId] = true;
            $parent = $hierarchy[$parentId] ?? null;
            if (! $parent instanceof ProjectRecord) {
                throw (new ModelNotFoundException)->setModel(ProjectRecord::class, [$parentId]);
            }
            if ($parent->archived_at !== null) {
                throw new InvalidProjectAction('project_parent_archived');
            }
            $parentId = $parent->parent_project_id !== null ? (int) $parent->parent_project_id : null;
        }
    }

    public function archive(ProjectId $id, int $expectedVersion): ProjectMutationResult
    {
        return $this->setArchive($id, $expectedVersion, $this->clock->now());
    }

    public function restore(ProjectId $id, int $expectedVersion): ProjectMutationResult
    {
        return $this->setArchive($id, $expectedVersion, null);
    }

    private function setArchive(ProjectId $id, int $expectedVersion, ?DateTimeImmutable $archivedAt): ProjectMutationResult
    {
        return DB::transaction(function () use ($id, $expectedVersion, $archivedAt): ProjectMutationResult {
            $record = $this->lockedRecord($id);
            $applied = $this->compareAndSwap($record, $expectedVersion, ['archived_at' => $archivedAt]);

            return $this->mutationResult($applied, $id->ownerId, (int) $record->id);
        }, 1);
    }

    /** @param Builder<ProjectRecord> $query */
    private function applyFilters(Builder $query, ProjectListFilter $filter): void
    {
        if ($filter->status !== null) {
            $query->where('status', $filter->status->value);
        }
        if ($filter->kind !== null) {
            $query->where('kind', $filter->kind->value);
        }
        if ($filter->partnerReference !== null) {
            $query->where('partner_reference', $filter->partnerReference);
        }
        foreach ([
            ['starts_on', '>=', $filter->startsFrom], ['starts_on', '<=', $filter->startsTo],
            ['due_on', '>=', $filter->dueFrom], ['due_on', '<=', $filter->dueTo],
        ] as [$column, $operator, $date]) {
            if ($date instanceof DateTimeImmutable) {
                $query->where($column, $operator, $date->format('Y-m-d'));
            }
        }
    }

    /** @param array<string, mixed> $values */
    private function compareAndSwap(ProjectRecord $record, int $expectedVersion, array $values): bool
    {
        return DB::table('finance_project_records')
            ->where('id', $record->id)
            ->where('user_id', $record->user_id)
            ->where('version', $expectedVersion)
            ->update([
                ...$values,
                'version' => $expectedVersion + 1,
                'updated_at' => $this->clock->now(),
            ]) === 1;
    }

    private function mutationResult(bool $applied, int $ownerId, int $recordId): ProjectMutationResult
    {
        $current = $this->view($this->ownedRecordByInternalId($ownerId, $recordId));

        return $applied
            ? ProjectMutationResult::applied($current)
            : ProjectMutationResult::conflict($current);
    }

    private function lockedRecord(ProjectId $id): ProjectRecord
    {
        return ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $id->ownerId)
            ->where('uuid', $id->uuid)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function record(ProjectId $id): ProjectRecord
    {
        return ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $id->ownerId)
            ->where('uuid', $id->uuid)
            ->firstOrFail();
    }

    private function ownedRecordByInternalId(int $ownerId, int $id): ProjectRecord
    {
        return ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->findOrFail($id);
    }

    private function lockedParentId(int $ownerId, ?ProjectId $parentId): ?int
    {
        $internalId = $this->parentInternalId($ownerId, $parentId);
        if ($internalId === null) {
            return null;
        }

        $parent = ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereKey($internalId)
            ->lockForUpdate()
            ->firstOrFail(['id', 'archived_at']);
        if ($parent->archived_at !== null) {
            throw new InvalidProjectAction('project_parent_archived');
        }

        return $internalId;
    }

    private function parentInternalId(int $ownerId, ?ProjectId $parentId): ?int
    {
        if ($parentId === null) {
            return null;
        }
        if ($parentId->ownerId !== $ownerId) {
            throw (new ModelNotFoundException)->setModel(ProjectRecord::class, [$parentId->uuid]);
        }

        return (int) ProjectRecord::query()
            ->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('uuid', $parentId->uuid)
            ->firstOrFail(['id'])
            ->id;
    }

    private function view(ProjectRecord $record): ProjectView
    {
        $parent = null;
        if ($record->parent_project_id !== null) {
            $parent = ProjectRecord::query()
                ->withoutGlobalScopes()
                ->where('user_id', $record->user_id)
                ->whereKey($record->parent_project_id)
                ->first();
            if (! $parent instanceof ProjectRecord) {
                throw new LogicException('Project parent ownership is inconsistent.');
            }
        }

        return new ProjectView(
            new ProjectId((int) $record->user_id, (string) $record->uuid),
            $parent !== null ? new ProjectId((int) $record->user_id, (string) $parent->uuid) : null,
            $parent === null || $parent->archived_at === null,
            (string) $record->name,
            ProjectKind::from((string) $record->kind),
            ProjectStatus::from((string) $record->status),
            is_string($record->partner_reference) ? $record->partner_reference : null,
            $this->date($record->starts_on),
            $this->date($record->due_on),
            $record->budget_minor !== null ? (int) $record->budget_minor : null,
            (string) $record->currency,
            (int) $record->version,
            $record->archived_at !== null,
            $this->requiredDate($record->created_at),
            $this->requiredDate($record->updated_at),
        );
    }

    private function dateValue(?DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    private function requiredDate(mixed $value): DateTimeImmutable
    {
        return $this->date($value) ?? throw new LogicException('Project timestamp is missing.');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null;
    }
}
