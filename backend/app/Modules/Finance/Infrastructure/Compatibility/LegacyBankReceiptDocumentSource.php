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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

final class LegacyBankReceiptDocumentSource implements ProjectDocumentSource
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === 'bank_transaction_receipt';
    }

    public function resolve(int $ownerId, ProjectDocumentSourceRef $ref): ProjectDocumentMetadata
    {
        if (! $this->supports($ref->sourceType)) {
            throw new InvalidArgumentException('Unsupported transaction receipt reference.');
        }
        [, $transactionId, $receiptId] = explode(':', $ref->sourceReference, 3);
        $transaction = BankTransaction::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $ownerId)->findOrFail((int) $transactionId, ['id', 'date', 'created_at', 'deleted_at', 'receipts']);
        $receipt = null;
        foreach ((array) $transaction->receipts as $item) {
            if (is_array($item) && isset($item['id']) && is_string($item['id']) && hash_equals(mb_strtolower($item['id']), mb_strtolower($receiptId))) {
                $receipt = $item;
                break;
            }
        }
        if ($receipt === null) {
            throw (new ModelNotFoundException)->setModel(BankTransaction::class, [$receiptId]);
        }
        $deleted = $transaction->deleted_at !== null;
        $mime = isset($receipt['mime']) && is_string($receipt['mime']) ? $receipt['mime'] : null;
        $size = isset($receipt['size']) && is_numeric($receipt['size']) ? (int) $receipt['size'] : null;
        $sha = isset($receipt['sha256']) && is_string($receipt['sha256']) && preg_match('/\A[0-9a-f]{64}\z/Di', $receipt['sha256']) === 1 ? $receipt['sha256'] : null;

        $occurredAt = $transaction->date ?? $transaction->created_at;
        if ($occurredAt === null) {
            throw new \LogicException('Transaction receipt occurrence is missing.');
        }

        return new ProjectDocumentMetadata($ref, isset($receipt['name']) && is_string($receipt['name']) && trim($receipt['name']) !== '' ? $receipt['name'] : 'Receipt '.$receiptId, $mime, $size, $sha, 'receipt', isset($receipt['kind']) && is_string($receipt['kind']) ? $receipt['kind'] : null, new DateTimeImmutable($occurredAt->toAtomString()), $deleted ? 'deleted' : 'available', $deleted ? null : 'finance.transactions.receipts.raw', $deleted ? [] : ['transaction' => (int) $transaction->id, 'receipt' => $receiptId]);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId || ($filter->sourceTypes !== [] && ! in_array('bank_transaction_receipt', $filter->sourceTypes, true))) {
            return new ProjectDocumentSourcePage([], null);
        }
        $all = [];
        $transactions = BankTransaction::query()->withoutGlobalScopes()->where('user_id', $ownerId)->whereNull('deleted_at')->whereNotNull('receipts')->orderByDesc('date')->orderByDesc('id')->limit(100)->get(['id', 'receipts']);
        foreach ($transactions as $transaction) {
            foreach ((array) $transaction->receipts as $receipt) {
                if (! is_array($receipt) || ! isset($receipt['id']) || ! is_string($receipt['id'])
                    || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/Di', $receipt['id']) !== 1) {
                    continue;
                }$ref = new ProjectDocumentSourceRef('bank_transaction_receipt', 'bank-transaction-receipt:'.$transaction->id.':'.$receipt['id']);
                $item = $this->resolve($ownerId, $ref);
                if ($this->matches($item, $filter)) {
                    $all[] = $item;
                }
            }
        }
        usort($all, static fn ($a, $b) => (($b->occurredAt?->getTimestamp() ?? 0) <=> ($a->occurredAt?->getTimestamp() ?? 0)) ?: ($a->source->sourceReference <=> $b->source->sourceReference));
        $offset = $this->offset($filter->cursor);

        return new ProjectDocumentSourcePage(array_slice($all, $offset, $filter->perPage), count($all) > $offset + $filter->perPage ? base64_encode((string) ($offset + $filter->perPage)) : null);
    }

    private function matches(ProjectDocumentMetadata $i, ProjectDocumentSourceFilter $f): bool
    {
        if ($f->q !== null && trim($f->q) !== '' && ! str_contains(mb_strtolower($i->title), mb_strtolower(trim($f->q)))) {
            return false;
        }$g = $i->mime === 'application/pdf' ? 'pdf' : (is_string($i->mime) && str_starts_with($i->mime, 'image/') ? 'image' : 'other');
        if ($f->mimeGroups !== [] && ! in_array($g, $f->mimeGroups, true)) {
            return false;
        }if ($f->from !== null && ($i->occurredAt === null || $i->occurredAt < $f->from)) {
            return false;
        }

        return $f->to === null || ($i->occurredAt !== null && $i->occurredAt <= $f->to);
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
