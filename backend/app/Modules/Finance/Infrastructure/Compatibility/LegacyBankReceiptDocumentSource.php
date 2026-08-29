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
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LegacyBankReceiptDocumentSource implements ProjectDocumentSource
{
    private const int TRANSACTION_BATCH_SIZE = 50;

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

        return $this->metadata($transaction, $ref, $receipt);
    }

    /** @param array<string, mixed> $receipt */
    private function metadata(BankTransaction $transaction, ProjectDocumentSourceRef $ref, array $receipt): ProjectDocumentMetadata
    {
        $deleted = $transaction->deleted_at !== null;
        $mime = isset($receipt['mime']) && is_string($receipt['mime']) ? $receipt['mime'] : null;
        $size = isset($receipt['size']) && is_numeric($receipt['size']) ? (int) $receipt['size'] : null;
        $sha = isset($receipt['sha256']) && is_string($receipt['sha256']) && preg_match('/\A[0-9a-f]{64}\z/Di', $receipt['sha256']) === 1 ? $receipt['sha256'] : null;

        $occurredAt = $transaction->date ?? $transaction->created_at;
        if ($occurredAt === null) {
            throw new \LogicException('Transaction receipt occurrence is missing.');
        }

        $receiptId = $receipt['id'] ?? null;
        if (! is_string($receiptId)) {
            throw new \LogicException('Transaction receipt identity is missing.');
        }

        return new ProjectDocumentMetadata($ref, isset($receipt['name']) && is_string($receipt['name']) && trim($receipt['name']) !== '' ? $receipt['name'] : 'Receipt '.$receiptId, $mime, $size, $sha, 'receipt', isset($receipt['kind']) && is_string($receipt['kind']) ? $receipt['kind'] : null, new DateTimeImmutable($occurredAt->format('Y-m-d\TH:i:s.uP')), $deleted ? 'deleted' : 'available', $deleted ? null : 'finance.transactions.receipts.raw', $deleted ? [] : ['transaction' => (int) $transaction->id, 'receipt' => $receiptId]);
    }

    public function search(int $ownerId, ProjectDocumentSourceFilter $filter): ProjectDocumentSourcePage
    {
        if ($ownerId !== $filter->ownerId || ($filter->sourceTypes !== [] && ! in_array('bank_transaction_receipt', $filter->sourceTypes, true))) {
            return new ProjectDocumentSourcePage([], null);
        }
        [$transactionOffset, $receiptOffset] = $this->position($filter->cursor);
        $items = [];
        $transactions = BankTransaction::query()->withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->whereNotNull('receipts')
            ->orderByDesc(DB::raw('COALESCE(date, created_at)'))
            ->orderBy('id')
            ->offset($transactionOffset)
            ->limit(self::TRANSACTION_BATCH_SIZE)
            ->get(['id', 'date', 'created_at', 'deleted_at', 'receipts']);
        foreach ($transactions as $batchIndex => $transaction) {
            $receipts = array_values(array_filter(
                (array) $transaction->receipts,
                static fn (mixed $receipt): bool => is_array($receipt)
                    && isset($receipt['id'])
                    && is_string($receipt['id'])
                    && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/Di', $receipt['id']) === 1,
            ));
            usort($receipts, static fn (array $a, array $b): int => strtolower((string) $a['id']) <=> strtolower((string) $b['id']));
            for ($index = $receiptOffset; $index < count($receipts); $index++) {
                $receiptId = (string) $receipts[$index]['id'];
                $ref = new ProjectDocumentSourceRef('bank_transaction_receipt', 'bank-transaction-receipt:'.$transaction->id.':'.$receiptId);
                $item = $this->metadata($transaction, $ref, $receipts[$index]);
                $nextPosition = $index + 1 < count($receipts)
                    ? [$transactionOffset, $index + 1]
                    : [$transactionOffset + 1, 0];
                if ($this->matches($item, $filter)) {
                    $items[] = $item;
                    if (count($items) === $filter->perPage) {
                        $hasMore = $index + 1 < count($receipts)
                            || $batchIndex + 1 < $transactions->count()
                            || $transactions->count() === self::TRANSACTION_BATCH_SIZE;

                        return new ProjectDocumentSourcePage(
                            $items,
                            $hasMore ? $this->cursor($nextPosition[0], $nextPosition[1]) : null,
                        );
                    }
                }
            }
            $transactionOffset++;
            $receiptOffset = 0;
        }

        return new ProjectDocumentSourcePage(
            $items,
            $transactions->count() === self::TRANSACTION_BATCH_SIZE ? $this->cursor($transactionOffset, 0) : null,
        );
    }

    private function matches(ProjectDocumentMetadata $i, ProjectDocumentSourceFilter $f): bool
    {
        if ($f->q !== null && trim($f->q) !== '' && ! str_contains(mb_strtolower($i->title), mb_strtolower(trim($f->q)))) {
            return false;
        }
        $g = $i->mime === 'application/pdf' ? 'pdf' : (is_string($i->mime) && str_starts_with($i->mime, 'image/') ? 'image' : 'other');
        if ($f->mimeGroups !== [] && ! in_array($g, $f->mimeGroups, true)) {
            return false;
        }
        if ($f->from !== null && ($i->occurredAt === null || $i->occurredAt < $f->from)) {
            return false;
        }

        return $f->to === null || ($i->occurredAt !== null && $i->occurredAt <= $f->to);
    }

    /** @return array{int, int} */
    private function position(?string $cursor): array
    {
        if ($cursor === null) {
            return [0, 0];
        }
        $json = base64_decode($cursor, true);
        $position = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($position) || ! is_int($position['transaction_offset'] ?? null) || ! is_int($position['receipt_offset'] ?? null)
            || $position['transaction_offset'] < 0 || $position['receipt_offset'] < 0) {
            throw new InvalidArgumentException('Invalid source cursor.');
        }

        return [$position['transaction_offset'], $position['receipt_offset']];
    }

    private function cursor(int $transactionOffset, int $receiptOffset): string
    {
        return base64_encode(json_encode(['transaction_offset' => $transactionOffset, 'receipt_offset' => $receiptOffset], JSON_THROW_ON_ERROR));
    }
}
