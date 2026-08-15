<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\BankTransaction;
use App\Models\FinanceReceipt;
use Illuminate\Support\Collection;

/**
 * Read-only suggestions for linking standalone receipts ("Fremdbelege") to the bank
 * transaction(s) that settled them, in BOTH directions:
 *   - detect(): several receipts summing to ONE charge (e.g. Amazon splitting an
 *     order into multiple shipment invoices that are charged together);
 *   - detectSplitPayments(): the inverse — ONE receipt whose total was actually
 *     debited as several separate charges (e.g. an INWX invoice billed as one
 *     document but settled by two transactions two days apart).
 * This only groups/sums, it never mutates a row — the owner applies a detect()
 * suggestion by PUTing bank_transaction_id on each receipt, and a
 * detectSplitPayments() suggestion by PUTing linked_transaction_ids on the receipt.
 *
 * Matching is deterministic and cent-exact (no currency guessing — that stays in the
 * client-side manual "assign a booking" picker, which a human reviews anyway):
 *   1) de-dupe accidental re-uploads of the same document (same order_ref/doc_number
 *      + amount) so they are never double-counted into a sum,
 *   2) receipts sharing a merchant-printed payment/order reference (order_ref) that
 *      sum to a transaction's amount — the strongest signal, e.g. Amazon's
 *      "Zahlungsreferenznummer",
 *   3) a single receipt whose amount matches a transaction to the cent,
 *   4) a small (<= MAX_GROUP) combination of receipts near the transaction's date
 *      whose amounts sum to it — the generic case for any merchant that splits a
 *      charge across several documents without a shared reference,
 *   5) (detectSplitPayments only) a small (<= MAX_GROUP) combination of
 *      transactions near the receipt's date whose amounts sum to the receipt's
 *      total — the mirror of (4) when the split happened on the bank's side
 *      instead of the vendor's paperwork.
 * Each transaction and each receipt is used in at most one suggested group.
 */
class ReceiptMatcher
{
    /**
     * Settlement can lag a receipt's document date well beyond "a few days" — an
     * invoice with net-30 terms (e.g. a Telekom/netcup bill) commonly settles 2-3
     * weeks after its document date, verified against real invoice<->payment pairs
     * (netcup: invoice dated the 22nd, debited the 10th of the next month, 18 days
     * later). 14 days excluded that real pair; 30 covers typical net-30 terms
     * without being so wide it starts pulling in unrelated recurring charges.
     */
    private const DAY_WINDOW = 30;

    private const CENT_TOL = 0.01;

    private const MAX_GROUP = 4;

    /** Cap the subset-sum search space per transaction (combinatorial safety net). */
    private const POOL_CAP = 25;

    /**
     * @return array{
     *     groups: list<array{transaction_id: int, receipt_ids: list<int>, reason: string, total: float}>,
     *     duplicates: list<array{receipt_id: int, duplicate_of: int}>,
     * }
     */
    public function detect(): array
    {
        $receipts = FinanceReceipt::query()
            ->whereNull('bank_transaction_id')
            ->whereNotNull('amount')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        [$pool, $duplicates] = $this->dedupe($receipts);

        $documentedTxIds = FinanceReceipt::query()
            ->whereNotNull('bank_transaction_id')
            ->get('bank_transaction_id')
            ->pluck('bank_transaction_id')
            ->filter()
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->unique();

        /** @var Collection<int, BankTransaction> $transactions */
        $transactions = BankTransaction::query()
            ->where('amount', '<', 0)
            ->get()
            ->reject(function (BankTransaction $t) use ($documentedTxIds): bool {
                $embedded = is_array($t->receipts) ? $t->receipts : [];

                return count($embedded) > 0 || $documentedTxIds->contains((int) $t->id);
            })
            ->sortBy(static fn (BankTransaction $t): string => (string) $t->date)
            ->values();

        $used = [];
        $groups = [];

        foreach ($transactions as $tx) {
            $target = round(abs((float) $tx->amount), 2);
            if ($target <= 0.0) {
                continue;
            }

            $candidates = $pool
                ->reject(static fn (FinanceReceipt $r): bool => isset($used[$r->id]))
                ->filter(function (FinanceReceipt $r) use ($tx): bool {
                    if ($r->date === null || $tx->date === null) {
                        return true; // no date on either side — still eligible, just unranked
                    }

                    return abs($tx->date->diffInDays($r->date)) <= self::DAY_WINDOW;
                })
                ->values();

            $hit = $this->matchByOrderRef($candidates, $target)
                ?? $this->matchExact($candidates, $target)
                ?? $this->matchSubsetSum($candidates, $tx, $target);

            if ($hit === null) {
                continue;
            }

            foreach ($hit['ids'] as $id) {
                $used[$id] = true;
            }
            $groups[] = [
                'transaction_id' => (int) $tx->id,
                'receipt_ids' => $hit['ids'],
                'reason' => $hit['reason'],
                'total' => $target,
            ];
        }

        return ['groups' => $groups, 'duplicates' => $duplicates];
    }

    /**
     * The inverse of detect(): a receipt whose amount matches no SINGLE undocumented
     * transaction, but a small combination of them sums to it — the real case this
     * was built for: an INWX invoice (42.07 EUR, several line items) was actually
     * debited as two separate charges (32.55 EUR + 9.52 EUR two days apart), one
     * receipt needing several bank_transaction rows rather than the usual one. Same
     * bounded subset-sum search as matchSubsetSum, just swapping which side is
     * fixed (one receipt total) and which side is combined (several transactions).
     * Read-only; the client applies a group by PUTing linked_transaction_ids on the
     * receipt.
     *
     * @return list<array{receipt_id: int, transaction_ids: list<int>, reason: string, total: float}>
     */
    public function detectSplitPayments(): array
    {
        $receipts = FinanceReceipt::query()
            ->whereNull('bank_transaction_id')
            ->whereNull('linked_transaction_ids')
            ->whereNotNull('amount')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($receipts->isEmpty()) {
            return [];
        }

        // Any transaction a receipt already claims — via the single link or a prior
        // split link — is off the table, same as embedded-receipt transactions.
        $singleClaimed = FinanceReceipt::query()
            ->whereNotNull('bank_transaction_id')
            ->get('bank_transaction_id')
            ->pluck('bank_transaction_id')
            ->filter()
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0);
        $splitClaimed = FinanceReceipt::query()
            ->whereNotNull('linked_transaction_ids')
            ->get('linked_transaction_ids')
            ->flatMap(static fn (FinanceReceipt $r): array => is_array($r->linked_transaction_ids) ? $r->linked_transaction_ids : [])
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0);
        $claimedTxIds = $singleClaimed->merge($splitClaimed)->unique();

        /** @var Collection<int, BankTransaction> $pool */
        $pool = BankTransaction::query()
            ->where('amount', '<', 0)
            ->get()
            ->reject(function (BankTransaction $t) use ($claimedTxIds): bool {
                $embedded = is_array($t->receipts) ? $t->receipts : [];

                return count($embedded) > 0 || $claimedTxIds->contains((int) $t->id);
            })
            ->values();

        $used = [];
        $groups = [];

        foreach ($receipts as $r) {
            $target = round(abs((float) $r->amount), 2);
            if ($target <= 0.0) {
                continue;
            }

            $candidates = $pool
                ->reject(static fn (BankTransaction $t): bool => isset($used[$t->id]))
                ->filter(function (BankTransaction $t) use ($r): bool {
                    if ($t->date === null || $r->date === null) {
                        return true; // no date on either side — still eligible, just unranked
                    }

                    return abs($t->date->diffInDays($r->date)) <= self::DAY_WINDOW;
                })
                ->values();

            $hit = $this->matchTransactionSubsetSum($candidates, $r, $target);
            if ($hit === null) {
                continue;
            }

            foreach ($hit['ids'] as $id) {
                $used[$id] = true;
            }
            $groups[] = [
                'receipt_id' => (int) $r->id,
                'transaction_ids' => $hit['ids'],
                'reason' => $hit['reason'],
                'total' => $target,
            ];
        }

        return $groups;
    }

    /**
     * Bounded subset-sum over transactions instead of receipts — the mirror of
     * matchSubsetSum(). Only sizes 2..MAX_GROUP: a single matching transaction is
     * already handled by the client's own auto-pick on upload, so this only ever
     * fires for the case nothing single-handedly matched.
     *
     * @param  Collection<int, BankTransaction>  $candidates
     * @return array{ids: list<int>, reason: string}|null
     */
    private function matchTransactionSubsetSum(Collection $candidates, FinanceReceipt $r, float $target): ?array
    {
        /** @var list<BankTransaction> $pool */
        $pool = array_values(
            $candidates
                ->sortBy(function (BankTransaction $t) use ($r): int {
                    if ($t->date === null || $r->date === null) {
                        return PHP_INT_MAX;
                    }

                    return (int) abs($t->date->diffInDays($r->date));
                })
                ->take(self::POOL_CAP)
                ->all()
        );

        $n = count($pool);
        if ($n < 2) {
            return null;
        }

        for ($size = 2; $size <= self::MAX_GROUP && $size <= $n; $size++) {
            foreach ($this->combinations(range(0, $n - 1), $size) as $combo) {
                $sum = 0.0;
                foreach ($combo as $idx) {
                    $sum += abs((float) $pool[$idx]->amount);
                }
                if (abs(round($sum, 2) - $target) < self::CENT_TOL) {
                    $ids = array_values(array_map(static fn (int $idx): int => (int) $pool[$idx]->id, $combo));

                    return ['ids' => $ids, 'reason' => 'sum'];
                }
            }
        }

        return null;
    }

    /**
     * Collapse accidental re-uploads of the same document: same non-empty order_ref
     * or doc_number AND the same amount. The earliest upload (already sorted by
     * created_at) is kept; later duplicates are reported, never summed twice.
     *
     * @param  Collection<int, FinanceReceipt>  $receipts
     * @return array{0: Collection<int, FinanceReceipt>, 1: list<array{receipt_id: int, duplicate_of: int}>}
     */
    private function dedupe(Collection $receipts): array
    {
        $seen = [];
        $kept = [];
        $duplicates = [];

        foreach ($receipts as $r) {
            $ref = trim((string) ($r->order_ref ?? ''));
            $num = trim((string) ($r->doc_number ?? ''));
            if ($ref === '' && $num === '') {
                $kept[] = $r;

                continue;
            }
            $amount = $r->amount !== null ? number_format((float) $r->amount, 2, '.', '') : '';
            $key = mb_strtolower($ref).'|'.mb_strtolower($num).'|'.$amount;

            if (isset($seen[$key])) {
                $duplicates[] = ['receipt_id' => (int) $r->id, 'duplicate_of' => $seen[$key]];

                continue;
            }
            $seen[$key] = (int) $r->id;
            $kept[] = $r;
        }

        return [collect($kept), $duplicates];
    }

    /**
     * @param  Collection<int, FinanceReceipt>  $candidates
     * @return array{ids: list<int>, reason: string}|null
     */
    private function matchByOrderRef(Collection $candidates, float $target): ?array
    {
        $byRef = $candidates
            ->filter(static fn (FinanceReceipt $r): bool => trim((string) ($r->order_ref ?? '')) !== '')
            ->groupBy(static fn (FinanceReceipt $r): string => mb_strtolower(trim((string) $r->order_ref)));

        foreach ($byRef as $group) {
            $sum = round((float) $group->sum(static fn (FinanceReceipt $r): float => (float) $r->amount), 2);
            if (abs($sum - $target) < self::CENT_TOL) {
                $ids = array_values($group->map(static fn (FinanceReceipt $r): int => (int) $r->id)->all());

                return ['ids' => $ids, 'reason' => 'order_ref'];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, FinanceReceipt>  $candidates
     * @return array{ids: list<int>, reason: string}|null
     */
    private function matchExact(Collection $candidates, float $target): ?array
    {
        $hits = $candidates->filter(
            static fn (FinanceReceipt $r): bool => abs((float) $r->amount - $target) < self::CENT_TOL
        );
        $only = $hits->count() === 1 ? $hits->first() : null;
        if ($only instanceof FinanceReceipt) {
            return ['ids' => [(int) $only->id], 'reason' => 'exact'];
        }

        return null;
    }

    /**
     * Bounded subset-sum: try every combination of size 2..MAX_GROUP among the
     * (date-window-filtered, already capped) candidates for one whose total lands on
     * the transaction amount to the cent. Candidates are ranked by date-closeness to
     * the transaction first, so the first hit found is also the most plausible one.
     *
     * @param  Collection<int, FinanceReceipt>  $candidates
     * @return array{ids: list<int>, reason: string}|null
     */
    private function matchSubsetSum(Collection $candidates, BankTransaction $tx, float $target): ?array
    {
        /** @var list<FinanceReceipt> $pool */
        $pool = array_values(
            $candidates
                ->sortBy(function (FinanceReceipt $r) use ($tx): int {
                    if ($r->date === null || $tx->date === null) {
                        return PHP_INT_MAX;
                    }

                    return (int) abs($tx->date->diffInDays($r->date));
                })
                ->take(self::POOL_CAP)
                ->all()
        );

        $n = count($pool);
        if ($n < 2) {
            return null;
        }

        for ($size = 2; $size <= self::MAX_GROUP && $size <= $n; $size++) {
            foreach ($this->combinations(range(0, $n - 1), $size) as $combo) {
                $sum = 0.0;
                foreach ($combo as $idx) {
                    $sum += (float) $pool[$idx]->amount;
                }
                if (abs(round($sum, 2) - $target) < self::CENT_TOL) {
                    $ids = array_values(array_map(static fn (int $idx): int => (int) $pool[$idx]->id, $combo));

                    return ['ids' => $ids, 'reason' => 'sum'];
                }
            }
        }

        return null;
    }

    /**
     * All k-combinations of the indices 0..count($items)-1, as a generator
     * (memory-safe for the sizes this ever sees — a handful of receipts, group
     * size <= MAX_GROUP).
     *
     * @param  list<int>  $items
     * @return \Generator<array<int, int>>
     */
    private function combinations(array $items, int $k): \Generator
    {
        $n = count($items);
        if ($k > $n) {
            return;
        }
        $indices = range(0, $k - 1);
        while (true) {
            yield array_map(static fn (int $i): int => $items[$i], $indices);

            $i = $k - 1;
            while ($i >= 0 && $indices[$i] === $i + $n - $k) {
                $i--;
            }
            if ($i < 0) {
                return;
            }
            $indices[$i]++;
            for ($j = $i + 1; $j < $k; $j++) {
                $indices[$j] = $indices[$j - 1] + 1;
            }
        }
    }
}
