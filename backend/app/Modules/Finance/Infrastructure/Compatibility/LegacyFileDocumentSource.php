<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\FileEntry;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class LegacyFileDocumentSource implements ProjectDocumentSource
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'file';
    }

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        $id = $this->id($ref);
        $record = FileEntry::query()->withoutGlobalScopes()->withTrashed()
            ->where('user_id', $ownerId)->findOrFail($id, ['id', 'name', 'mime', 'size', 'sha256', 'created_at', 'deleted_at']);

        return $this->metadata($record, $ref);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId || ($filter->sourceTypes !== [] && ! in_array('file', $filter->sourceTypes, true))) {
            return new ProjectDocumentSourcePage([], null);
        }
        $query = FileEntry::query()->withoutGlobalScopes()->where('user_id', $ownerId)->whereNull('deleted_at');
        if ($filter->q !== null && trim($filter->q) !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower(trim($filter->q))).'%']);
        }
        $this->dates($query, $filter);
        $offset = $this->offset($filter->cursor);
        $rows = $query->orderByDesc('created_at')->orderBy('id')->offset($offset)->limit($filter->perPage + 1)
            ->get(['id', 'name', 'mime', 'size', 'sha256', 'created_at', 'deleted_at']);
        $items = array_values($rows->take($filter->perPage)->map(fn (FileEntry $row): ProjectDocumentMetadata => $this->metadata($row, new ProjectDocumentSourceRef('file', 'file:'.$row->id)))->all());

        return new ProjectDocumentSourcePage($items, $rows->count() > $filter->perPage ? base64_encode((string) ($offset + $filter->perPage)) : null);
    }

    private function id(ProjectDocumentSourceRef $ref): int
    {
        if (! $this->supports($ref->sourceType)) {
            throw new \InvalidArgumentException('Unsupported project document source.');
        }

        return (int) substr($ref->sourceReference, 5);
    }

    private function metadata(FileEntry $row, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        return new ProjectDocumentMetadata($ref, (string) $row->name, is_string($row->mime) ? $row->mime : null, (int) $row->size,
            is_string($row->sha256) ? $row->sha256 : null, 'file', null,
            $row->created_at !== null ? new DateTimeImmutable($row->created_at->format('Y-m-d\TH:i:s.uP')) : null,
            $row->deleted_at === null ? 'available' : 'deleted', $row->deleted_at === null ? 'files.rel.raw' : null,
            $row->deleted_at === null ? ['file' => (int) $row->id] : []);
    }

    /** @param Builder<FileEntry> $query */
    private function dates(Builder $query, ProjectDocumentSourceFilter $filter): void
    {
        if ($filter->from !== null) {
            $query->where('created_at', '>=', $filter->from->format('Y-m-d H:i:s.u'));
        }
        if ($filter->to !== null) {
            $query->where('created_at', '<=', $filter->to->format('Y-m-d H:i:s.u'));
        }
        if ($filter->mimeGroups !== []) {
            $query->where(function (Builder $q) use ($filter): void {
                foreach ($filter->mimeGroups as $group) {
                    $group === 'pdf' ? $q->orWhere('mime', 'application/pdf') : ($group === 'image' ? $q->orWhere('mime', 'like', 'image/%') : $q->orWhere(fn (Builder $x) => $x->whereNull('mime')->orWhere(fn (Builder $known) => $known->where('mime', 'not like', 'image/%')->where('mime', '!=', 'application/pdf'))));
                }
            });
        }
    }

    private function offset(?string $cursor): int
    {
        if ($cursor === null) {
            return 0;
        }
        $value = base64_decode($cursor, true);
        if (! is_string($value) || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            throw new \InvalidArgumentException('Invalid source cursor.');
        }

        return (int) $value;
    }
}
