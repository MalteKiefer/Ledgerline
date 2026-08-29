<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentPage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentView;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectDocumentLinkRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectRecord;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EloquentProjectDocumentRepository implements ProjectDocumentRepository
{
    private const array ROLES = ['source_quote', 'quote', 'invoice', 'payment', 'receipt', 'file', 'photo', 'other'];

    public function attach(ProjectId $projectId, ProjectDocumentMetadata $metadata, string $role, int $actorId, DateTimeImmutable $at): ProjectDocumentView
    {
        if (! in_array($role, self::ROLES, true) || $actorId !== $projectId->ownerId || $metadata->availability !== 'available') {
            throw new InvalidArgumentException('Project document attachment is invalid.');
        }
        $this->assertRole($role, $metadata);

        try {
            return DB::transaction(function () use ($projectId, $metadata, $role, $actorId, $at): ProjectDocumentView {
                $project = $this->project($projectId, true);
                $existing = ProjectDocumentLinkRecord::query()->withoutGlobalScopes()
                    ->where('user_id', $projectId->ownerId)->where('project_id', $project->id)
                    ->where('source_type', $metadata->source->sourceType)->where('source_reference', $metadata->source->sourceReference)
                    ->where('role', $role)->whereNull('detached_at')->lockForUpdate()->first();
                if ($existing instanceof ProjectDocumentLinkRecord) {
                    throw new DomainException('document_already_attached');
                }

                [$seriesId, $revisionId] = $this->seriesIds($projectId->ownerId, $metadata);
                $timestamp = $at->format('Y-m-d H:i:s.u');
                $id = (int) DB::table('finance_project_document_links')->insertGetId([
                    'user_id' => $projectId->ownerId, 'project_id' => $project->id,
                    'source_type' => $metadata->source->sourceType, 'source_reference' => $metadata->source->sourceReference,
                    'document_series_id' => $seriesId, 'pinned_revision_id' => $revisionId, 'role' => $role,
                    'metadata_snapshot' => json_encode($metadata->snapshot(), JSON_THROW_ON_ERROR),
                    'attached_by' => $actorId, 'attached_at' => $timestamp, 'detached_by' => null, 'detached_at' => null,
                ]);
                $this->activity((int) $project->id, $projectId->ownerId, 'project.document_attached', ['link_id' => $id, 'source_type' => $metadata->source->sourceType, 'source_reference' => $metadata->source->sourceReference, 'role' => $role], $actorId, $timestamp);

                return $this->view($projectId, ProjectDocumentLinkRecord::query()->withoutGlobalScopes()->findOrFail($id), $metadata);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('document_already_attached');
        }
    }

    public function detach(ProjectId $projectId, int $linkId, int $actorId, DateTimeImmutable $at): ProjectDocumentView
    {
        if ($linkId < 1 || $actorId !== $projectId->ownerId) {
            throw new InvalidArgumentException('Project document detachment is invalid.');
        }

        return DB::transaction(function () use ($projectId, $linkId, $actorId, $at): ProjectDocumentView {
            $project = $this->project($projectId, true);
            $link = ProjectDocumentLinkRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)
                ->where('project_id', $project->id)->whereKey($linkId)->lockForUpdate()->firstOrFail();
            if ($link->detached_at !== null) {
                throw new DomainException('document_already_detached');
            }
            $timestamp = $at->format('Y-m-d H:i:s.u');
            $link->forceFill(['detached_by' => $actorId, 'detached_at' => $timestamp]);
            $link->save();
            $this->activity((int) $project->id, $projectId->ownerId, 'project.document_detached', ['link_id' => $linkId], $actorId, $timestamp);

            return $this->view($projectId, $link->refresh(), null);
        }, 3);
    }

    public function get(ProjectId $projectId, int $linkId): ProjectDocumentView
    {
        $project = $this->project($projectId);
        $link = ProjectDocumentLinkRecord::query()->withoutGlobalScopes()->where('user_id', $projectId->ownerId)
            ->where('project_id', $project->id)->findOrFail($linkId);

        return $this->view($projectId, $link, null);
    }

    public function page(ProjectDocumentFilter $filter, ProjectDocumentSource $catalog): ProjectDocumentPage
    {
        $project = $this->project($filter->projectId);
        $query = ProjectDocumentLinkRecord::query()->withoutGlobalScopes()->where('user_id', $filter->projectId->ownerId)->where('project_id', $project->id);
        if (! $filter->includeDetached) {
            $query->whereNull('detached_at');
        }
        if ($filter->sourceTypes !== []) {
            $query->whereIn('source_type', $filter->sourceTypes);
        }
        if ($filter->roles !== []) {
            $query->whereIn('role', $filter->roles);
        }
        if ($filter->from !== null) {
            $query->where('attached_at', '>=', $filter->from);
        }
        if ($filter->to !== null) {
            $query->where('attached_at', '<=', $filter->to);
        }
        $views = [];
        foreach ($query->orderByDesc('attached_at')->orderByDesc('id')->get() as $link) {
            try {
                $resolved = $catalog->resolve($filter->projectId->ownerId, $this->ref($link));
                $current = $resolved->availability === 'available' ? $resolved : null;
                $availability = $resolved->availability;
            } catch (ModelNotFoundException) {
                $current = null;
                $availability = 'missing';
            }
            $view = $this->view($filter->projectId, $link, $current, $availability);
            if (! $this->matches($view, $filter)) {
                continue;
            }
            $views[] = $view;
        }
        $total = count($views);
        $items = array_slice($views, ($filter->page - 1) * $filter->perPage, $filter->perPage);

        return new ProjectDocumentPage($items, $filter->page, $filter->perPage, $total);
    }

    private function project(ProjectId $id, bool $lock = false): ProjectRecord
    {
        $query = ProjectRecord::query()->withoutGlobalScopes()->where('user_id', $id->ownerId)->where('uuid', $id->uuid);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail(['id', 'uuid']);
    }

    /** @return array{?int, ?int} */
    private function seriesIds(int $ownerId, ProjectDocumentMetadata $metadata): array
    {
        if ($metadata->source->sourceType !== 'finance_series') {
            return [null, null];
        }
        $series = DocumentSeriesRecord::query()->withoutGlobalScopes()->where('user_id', $ownerId)->where('uuid', $metadata->source->sourceReference)->first(['id']);
        if (! $series instanceof DocumentSeriesRecord || $metadata->source->pinnedRevisionId === null) {
            throw (new ModelNotFoundException)->setModel(DocumentSeriesRecord::class);
        }
        $revision = DocumentRevisionRecord::query()->withoutGlobalScopes()->where('user_id', $ownerId)->where('document_series_id', $series->id)->whereKey($metadata->source->pinnedRevisionId)->first(['id']);
        if (! $revision instanceof DocumentRevisionRecord) {
            throw (new ModelNotFoundException)->setModel(DocumentRevisionRecord::class);
        }

        return [$series->id, $revision->id];
    }

    private function assertRole(string $role, ProjectDocumentMetadata $metadata): void
    {
        $allowed = match ($role) {
            'source_quote', 'quote' => $metadata->source->sourceType === 'finance_series' && $metadata->documentType === 'quote',
            'invoice' => in_array($metadata->source->sourceType, ['finance_series', 'legacy_invoice'], true) && $metadata->documentType === 'invoice',
            'payment' => $metadata->source->sourceType === 'bank_transaction',
            'receipt' => in_array($metadata->source->sourceType, ['finance_receipt', 'bank_transaction_receipt'], true),
            'file' => $metadata->source->sourceType === 'file', 'photo' => $metadata->source->sourceType === 'gallery_photo', 'other' => true,
            default => false,
        };
        if (! $allowed) {
            throw new DomainException('document_role_incompatible');
        }
    }

    /** @param array<string, mixed> $payload */
    private function activity(int $projectId, int $ownerId, string $type, array $payload, int $actorId, string $at): void
    {
        DB::table('finance_project_activities')->insert(['user_id' => $ownerId, 'project_id' => $projectId, 'type' => $type, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_by' => $actorId, 'occurred_at' => $at, 'created_at' => $at]);
    }

    private function ref(ProjectDocumentLinkRecord $link): ProjectDocumentSourceRef
    {
        return new ProjectDocumentSourceRef((string) $link->source_type, (string) $link->source_reference, $link->pinned_revision_id !== null ? (int) $link->pinned_revision_id : null);
    }

    private function view(ProjectId $projectId, ProjectDocumentLinkRecord $link, ?ProjectDocumentMetadata $current, ?string $availability = null): ProjectDocumentView
    {
        $snapshot = $this->snapshot($link->metadata_snapshot);

        return new ProjectDocumentView((int) $link->id, $projectId, $this->ref($link), (string) $link->role, $snapshot, $current,
            $availability ?? ($current?->availability ?? 'available'), (int) $link->attached_by,
            $this->date($link->attached_at), $link->detached_by !== null ? (int) $link->detached_by : null,
            $link->detached_at !== null ? $this->date($link->detached_at) : null);
    }

    private function matches(ProjectDocumentView $view, ProjectDocumentFilter $filter): bool
    {
        if ($filter->availabilities !== [] && ! in_array($view->availability, $filter->availabilities, true)) {
            return false;
        }
        $mime = $view->current?->mime ?? ($view->snapshot['mime'] ?? null);
        $group = is_string($mime) && $mime === 'application/pdf' ? 'pdf' : (is_string($mime) && str_starts_with($mime, 'image/') ? 'image' : 'other');
        if ($filter->mimeGroups !== [] && ! in_array($group, $filter->mimeGroups, true)) {
            return false;
        }
        if ($filter->q !== null && trim($filter->q) !== '') {
            $snapshotTitle = $view->snapshot['title'] ?? '';
            $title = $view->current?->title ?? (is_string($snapshotTitle) ? $snapshotTitle : '');
            if (! str_contains(mb_strtolower($title), mb_strtolower(trim($filter->q)))) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function snapshot(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        }
        if (! is_array($value)) {
            throw new \LogicException('Project document snapshot is invalid.');
        }
        $snapshot = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new \LogicException('Project document snapshot keys are invalid.');
            }
            $snapshot[$key] = $item;
        }

        return $snapshot;
    }

    private function date(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format(DATE_ATOM));
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new \LogicException('Project document timestamp is invalid.');
    }
}
