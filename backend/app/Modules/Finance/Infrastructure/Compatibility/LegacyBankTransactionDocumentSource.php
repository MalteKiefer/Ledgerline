<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\BankTransaction;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentMetadata;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceFilter;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourcePage;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\Ports\Projects\ProjectDocumentSource;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyBankTransactionDocumentSource implements ProjectDocumentSource
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'bank_transaction';
    }

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        if (! $this->supports($ref->sourceType)) {
            throw new InvalidArgumentException('Unsupported bank transaction reference.');
        }
        $row = BankTransaction::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $ownerId)->findOrFail((int) substr($ref->sourceReference, 17), ['id', 'counterparty', 'purpose', 'booking_text', 'date', 'invoice_number', 'created_at', 'deleted_at']);
        $deleted = $row->deleted_at !== null;
        $title = trim((string) ($row->counterparty ?: $row->purpose ?: $row->booking_text ?: 'Bank transaction #'.$row->id));

        $occurredAt = $row->date ?? $row->created_at;
        if ($occurredAt === null) {
            throw new \LogicException('Bank transaction occurrence is missing.');
        }

        return new ProjectDocumentMetadata($ref, $title, null, null, null, 'payment', is_string($row->invoice_number) ? $row->invoice_number : null, new DateTimeImmutable($occurredAt->toAtomString()), $deleted ? 'deleted' : 'available', $deleted ? null : 'finance.index', $deleted ? [] : ['transaction' => (int) $row->id]);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId || ($filter->sourceTypes !== [] && ! in_array('bank_transaction', $filter->sourceTypes, true)) || ($filter->mimeGroups !== [] && ! in_array('other', $filter->mimeGroups, true))) {
            return new ProjectDocumentSourcePage([], null);
        }
        $offset = $this->offset($filter->cursor);
        $q = BankTransaction::query()->withoutGlobalScopes()->where('user_id', $ownerId)->whereNull('deleted_at');
        if ($filter->q !== null && trim($filter->q) !== '') {
            $n = '%'.mb_strtolower(trim($filter->q)).'%';
            $q->where(fn ($x) => $x->whereRaw('LOWER(counterparty) LIKE ?', [$n])->orWhereRaw('LOWER(purpose) LIKE ?', [$n])->orWhereRaw('LOWER(booking_text) LIKE ?', [$n])->orWhere('invoice_number', 'like', '%'.trim($filter->q).'%'));
        }
        if ($filter->from !== null) {
            $q->whereRaw('COALESCE(date, created_at) >= ?', [$filter->from]);
        }if ($filter->to !== null) {
            $q->whereRaw('COALESCE(date, created_at) <= ?', [$filter->to]);
        }
        $rows = $q->orderByDesc(DB::raw('COALESCE(date, created_at)'))->orderByDesc('id')->offset($offset)->limit($filter->perPage + 1)->get(['id']);
        $items = $rows->take($filter->perPage)->map(fn ($r) => $this->resolve($ownerId, new ProjectDocumentSourceRef('bank_transaction', 'bank-transaction:'.$r->id)))->values()->all();

        return new ProjectDocumentSourcePage(array_values($items), $rows->count() > $filter->perPage ? base64_encode((string) ($offset + $filter->perPage)) : null);
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
