<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FinanceSeriesDocumentSource implements ProjectDocumentSource
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'finance_series';
    }

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        if (! $this->supports($ref->sourceType) || $ref->pinnedRevisionId === null) {
            throw new InvalidArgumentException('Unsupported finance series reference.');
        }
        $row = DB::table('finance_document_series as s')->join('finance_document_revisions as r', 'r.document_series_id', '=', 's.id')
            ->where('s.user_id', $ownerId)->where('r.user_id', $ownerId)->where('s.uuid', $ref->sourceReference)->where('r.id', $ref->pinnedRevisionId)
            ->first(['s.uuid', 's.document_type', 'r.id', 'r.revision_number', 'r.snapshot', 'r.pdf_path', 'r.pdf_sha256', 'r.published_at', 'r.created_at']);
        if ($row === null) {
            throw (new ModelNotFoundException)->setModel(DocumentRevisionRecord::class, [$ref->pinnedRevisionId]);
        }
        if (! is_string($row->snapshot) || ! is_string($row->document_type) || ! is_numeric($row->revision_number)
            || ! is_string($row->uuid) || (! is_string($row->created_at) && ! is_string($row->published_at))) {
            throw new \LogicException('Finance series metadata is invalid.');
        }
        $decoded = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $snapshot = is_array($decoded) ? $decoded : [];
        $number = isset($snapshot['number']) && is_scalar($snapshot['number']) ? (string) $snapshot['number'] : null;
        if ($number === null && isset($snapshot['document']) && is_array($snapshot['document']) && isset($snapshot['document']['number']) && is_scalar($snapshot['document']['number'])) {
            $number = (string) $snapshot['document']['number'];
        }
        $type = $row->document_type;
        $revisionNumber = (int) $row->revision_number;
        $uuid = $row->uuid;
        $title = ucfirst($type).($number !== null && $number !== '' ? ' '.$number : ' revision '.$revisionNumber);
        $published = $row->published_at !== null;
        $occurredAt = is_string($row->published_at) ? $row->published_at : $row->created_at;

        return new ProjectDocumentMetadata($ref, $title, $published ? 'application/pdf' : null, null,
            is_string($row->pdf_sha256) ? $row->pdf_sha256 : null, $type, 'Revision '.$revisionNumber,
            new DateTimeImmutable($occurredAt), 'available',
            $published ? 'api.finance-v2.'.$type.'s.revisions.pdf' : null,
            $published ? [$type => $uuid, 'revision' => $revisionNumber] : []);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId || ($filter->sourceTypes !== [] && ! in_array('finance_series', $filter->sourceTypes, true))) {
            return new ProjectDocumentSourcePage([], null);
        }
        $offset = $this->offset($filter->cursor);
        $rows = DB::table('finance_document_series as s')->join('finance_document_revisions as r', 'r.document_series_id', '=', 's.id')
            ->where('s.user_id', $ownerId)->where('r.user_id', $ownerId)
            ->whereRaw('r.revision_number = (select max(r2.revision_number) from finance_document_revisions r2 where r2.document_series_id = s.id and r2.user_id = s.user_id)')
            ->orderByDesc(DB::raw('COALESCE(r.published_at, r.created_at)'))->orderBy('s.uuid')->offset($offset)->limit($filter->perPage + 1)->get(['s.uuid', 'r.id']);
        $items = [];
        foreach ($rows->take($filter->perPage) as $row) {
            if (! is_string($row->uuid) || ! is_numeric($row->id)) {
                throw new \LogicException('Finance series search result is invalid.');
            }
            $item = $this->resolve($ownerId, new ProjectDocumentSourceRef('finance_series', $row->uuid, (int) $row->id));
            if ($this->matches($item, $filter)) {
                $items[] = $item;
            }
        }

        return new ProjectDocumentSourcePage($items, $rows->count() > $filter->perPage ? base64_encode((string) ($offset + $filter->perPage)) : null);
    }

    private function matches(ProjectDocumentMetadata $item, ProjectDocumentSourceFilter $filter): bool
    {
        if ($filter->q !== null && trim($filter->q) !== '' && ! str_contains(mb_strtolower($item->title), mb_strtolower(trim($filter->q)))) {
            return false;
        }
        if ($filter->mimeGroups !== [] && ! in_array($item->mime === 'application/pdf' ? 'pdf' : 'other', $filter->mimeGroups, true)) {
            return false;
        }
        if ($filter->from !== null && ($item->occurredAt === null || $item->occurredAt < $filter->from)) {
            return false;
        }

        return $filter->to === null || ($item->occurredAt !== null && $item->occurredAt <= $filter->to);
    }

    private function offset(?string $cursor): int
    {
        if ($cursor === null) {
            return 0;
        }
        $v = base64_decode($cursor, true);
        if (! is_string($v) || ! ctype_digit($v)) {
            throw new InvalidArgumentException('Invalid source cursor.');
        }

        return (int) $v;
    }
}
