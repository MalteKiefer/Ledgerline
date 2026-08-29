<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\GalleryPhoto;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyGalleryPhotoDocumentSource implements ProjectDocumentSource
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'gallery_photo';
    }

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        if (! $this->supports($ref->sourceType)) {
            throw new InvalidArgumentException('Unsupported gallery reference.');
        }
        $row = GalleryPhoto::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $ownerId)->findOrFail((int) substr($ref->sourceReference, 14), ['id', 'name', 'mime', 'size', 'sha256', 'taken_at', 'created_at', 'deleted_at']);
        $deleted = $row->deleted_at !== null;

        $occurredAt = $row->taken_at ?? $row->created_at;
        if ($occurredAt === null) {
            throw new \LogicException('Gallery photo occurrence is missing.');
        }

        return new ProjectDocumentMetadata($ref, (string) $row->name, is_string($row->mime) ? $row->mime : null, (int) $row->size, is_string($row->sha256) ? $row->sha256 : null,
            'photo', null, new DateTimeImmutable($occurredAt->toAtomString()), $deleted ? 'deleted' : 'available', $deleted ? null : 'gallery.raw', $deleted ? [] : ['photo' => (int) $row->id]);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId || ($filter->sourceTypes !== [] && ! in_array('gallery_photo', $filter->sourceTypes, true)) || ($filter->mimeGroups !== [] && ! in_array('image', $filter->mimeGroups, true))) {
            return new ProjectDocumentSourcePage([], null);
        }
        $offset = $this->offset($filter->cursor);
        $q = GalleryPhoto::query()->withoutGlobalScopes()->where('user_id', $ownerId)->whereNull('deleted_at');
        if ($filter->q !== null && trim($filter->q) !== '') {
            $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($filter->q)).'%']);
        }
        if ($filter->from !== null) {
            $q->whereRaw('COALESCE(taken_at, created_at) >= ?', [$filter->from]);
        } if ($filter->to !== null) {
            $q->whereRaw('COALESCE(taken_at, created_at) <= ?', [$filter->to]);
        }
        $rows = $q->orderByDesc(DB::raw('COALESCE(taken_at, created_at)'))->orderByDesc('id')->offset($offset)->limit($filter->perPage + 1)->get(['id']);
        $items = array_values($rows->take($filter->perPage)->map(fn ($r) => $this->resolve($ownerId, new ProjectDocumentSourceRef('gallery_photo', 'gallery-photo:'.$r->id)))->all());

        return new ProjectDocumentSourcePage($items, $rows->count() > $filter->perPage ? base64_encode((string) ($offset + $filter->perPage)) : null);
    }

    private function offset(?string $cursor): int
    {
        if ($cursor === null) {
            return 0;
        } $v = base64_decode($cursor, true);
        if (! is_string($v) || ! ctype_digit($v)) {
            throw new InvalidArgumentException('Invalid source cursor.');
        }

        return (int) $v;
    }
}
