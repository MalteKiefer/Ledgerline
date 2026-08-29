<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\Invoice;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use DateTimeImmutable;
use InvalidArgumentException;

final class LegacyInvoiceDocumentSource implements ProjectDocumentSource
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'legacy_invoice';
    }

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        if (! $this->supports($ref->sourceType)) {
            throw new InvalidArgumentException('Unsupported legacy invoice reference.');
        }
        $id = (int) substr($ref->sourceReference, 15);
        $row = Invoice::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $ownerId)->findOrFail($id,
            ['id', 'number', 'status', 'type', 'issue_date', 'pdf_path', 'created_at', 'deleted_at']);
        $deleted = $row->deleted_at !== null;

        $occurredAt = $row->issue_date ?? $row->created_at;
        if ($occurredAt === null) {
            throw new \LogicException('Legacy invoice occurrence is missing.');
        }

        return new ProjectDocumentMetadata($ref, $row->number !== null ? 'Invoice '.(string) $row->number : 'Invoice #'.(int) $row->id,
            $row->pdf_path !== null ? 'application/pdf' : null, null, null, 'invoice', (string) $row->status,
            new DateTimeImmutable($occurredAt->toAtomString()), $deleted ? 'deleted' : 'available',
            ! $deleted && $row->pdf_path !== null ? 'finance.invoices.pdf' : null, ! $deleted && $row->pdf_path !== null ? ['invoice' => (int) $row->id] : []);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId || ($filter->sourceTypes !== [] && ! in_array('legacy_invoice', $filter->sourceTypes, true))) {
            return new ProjectDocumentSourcePage([], null);
        }
        $offset = $this->offset($filter->cursor);
        $query = Invoice::query()->withoutGlobalScopes()->where('user_id', $ownerId)->whereNull('deleted_at');
        if ($filter->q !== null && trim($filter->q) !== '') {
            $query->where('number', 'like', '%'.trim($filter->q).'%');
        }
        if ($filter->from !== null) {
            $query->where('issue_date', '>=', $filter->from);
        } if ($filter->to !== null) {
            $query->where('issue_date', '<=', $filter->to);
        }
        if ($filter->mimeGroups !== [] && ! in_array('pdf', $filter->mimeGroups, true)) {
            return new ProjectDocumentSourcePage([], null);
        }
        $rows = $query->orderByDesc('issue_date')->orderByDesc('id')->offset($offset)->limit($filter->perPage + 1)->get(['id']);
        $items = array_values($rows->take($filter->perPage)->map(fn ($r) => $this->resolve($ownerId, new ProjectDocumentSourceRef('legacy_invoice', 'legacy-invoice:'.$r->id)))->all());

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
