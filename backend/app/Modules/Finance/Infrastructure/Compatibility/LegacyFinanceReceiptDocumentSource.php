<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\FinanceReceipt;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyFinanceReceiptDocumentSource implements ProjectDocumentSource
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'finance_receipt';
    }

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        if (! $this->supports($ref->sourceType)) {
            throw new InvalidArgumentException('Unsupported finance receipt reference.');
        }
        $row = FinanceReceipt::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $ownerId)->findOrFail((int) substr($ref->sourceReference, 16), ['id', 'name', 'mime', 'size', 'sig', 'kind', 'date', 'doc_number', 'created_at', 'deleted_at']);
        $deleted = $row->deleted_at !== null;
        $digest = is_string($row->sig) && preg_match('/\A[0-9a-f]{64}\z/Di', $row->sig) === 1 ? $row->sig : null;

        $occurredAt = $row->date ?? $row->created_at;
        if ($occurredAt === null) {
            throw new \LogicException('Finance receipt occurrence is missing.');
        }

        return new ProjectDocumentMetadata($ref, (string) $row->name, is_string($row->mime) ? $row->mime : null, (int) $row->size, $digest,
            'receipt', is_string($row->doc_number) ? $row->doc_number : (is_string($row->kind) ? $row->kind : null),
            new DateTimeImmutable($occurredAt->format('Y-m-d\TH:i:s.uP')), $deleted ? 'deleted' : 'available',
            $deleted ? null : 'finance.receipts.raw', $deleted ? [] : ['receipt' => (int) $row->id]);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId || ($filter->sourceTypes !== [] && ! in_array('finance_receipt', $filter->sourceTypes, true))) {
            return new ProjectDocumentSourcePage([], null);
        }
        $offset = $this->offset($filter->cursor);
        $q = FinanceReceipt::query()->withoutGlobalScopes()->where('user_id', $ownerId)->whereNull('deleted_at');
        if ($filter->q !== null && trim($filter->q) !== '') {
            $q->where(fn ($x) => $x->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($filter->q)).'%'])->orWhere('doc_number', 'like', '%'.trim($filter->q).'%')->orWhere('order_ref', 'like', '%'.trim($filter->q).'%'));
        }
        if ($filter->from !== null) {
            $q->whereRaw('COALESCE(date, created_at) >= ?', [$filter->from->format('Y-m-d H:i:s.u')]);
        } if ($filter->to !== null) {
            $q->whereRaw('COALESCE(date, created_at) <= ?', [$filter->to->format('Y-m-d H:i:s.u')]);
        }
        if ($filter->mimeGroups !== []) {
            $q->where(function ($x) use ($filter) {
                foreach ($filter->mimeGroups as $g) {
                    $g === 'pdf' ? $x->orWhere('mime', 'application/pdf') : ($g === 'image' ? $x->orWhere('mime', 'like', 'image/%') : $x->orWhere(fn ($z) => $z->whereNull('mime')->orWhere(fn ($known) => $known->where('mime', 'not like', 'image/%')->where('mime', '!=', 'application/pdf'))));
                }
            });
        }
        $rows = $q->orderByDesc(DB::raw('COALESCE(date, created_at)'))->orderBy('id')->offset($offset)->limit($filter->perPage + 1)->get(['id']);
        $items = array_values($rows->take($filter->perPage)->map(fn ($r) => $this->resolve($ownerId, new ProjectDocumentSourceRef('finance_receipt', 'finance-receipt:'.$r->id)))->all());

        return new ProjectDocumentSourcePage($items, $rows->count() > $filter->perPage ? base64_encode((string) ($offset + $filter->perPage)) : null);
    }

    private function offset(?string $c): int
    {
        if ($c === null) {
            return 0;
        }$v = base64_decode($c, true);
        if (! is_string($v) || ! ctype_digit($v)) {
            throw new InvalidArgumentException('Invalid source cursor.');
        }

        return (int) $v;
    }
}
