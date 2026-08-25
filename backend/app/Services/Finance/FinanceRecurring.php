<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\BankTransaction;
use App\Support\FinanceScope;
use Illuminate\Support\Carbon;

/**
 * Finds the payments that keep coming back.
 *
 * Everything needed for this has been sitting in `bank_transactions` for years —
 * counterparty, amount, date — and nothing ever looked at the intervals between
 * them. Read-only: it reports, it never writes a row or files a category.
 *
 * What it answers, in the order the answers matter:
 *  - what does this cost per year (a 9.99/month line reads as ~120)
 *  - did the price change (9.99 -> 12.99 is the thing nobody notices)
 *  - has it stopped coming (probably cancelled, or a service being paid for and
 *    not used — worth seeing either way)
 *  - when is it due next
 *
 * Deliberately conservative: three charges minimum and a stable interval, so a
 * pair of unrelated payments to the same shop is not announced as a
 * subscription. A false "you have a subscription" is worse than a miss, because
 * the whole point is to be believed.
 */
final class FinanceRecurring
{
    /** Below this many charges there is no rhythm to speak of. */
    private const MIN_CHARGES = 3;

    /** Interval buckets: label => [min days, max days, charges per year]. */
    private const CADENCES = [
        'weekly' => [6, 8, 52],
        'monthly' => [26, 35, 12],
        'quarterly' => [83, 97, 4],
        'semiannual' => [172, 190, 2],
        'annual' => [352, 380, 1],
    ];

    /** Share of gaps that must sit near the median before we call it a rhythm. */
    private const CONSISTENCY = 0.6;

    /**
     * @return list<array<string, mixed>>
     */
    public function detect(): array
    {
        /** @var array<string, list<BankTransaction>> $groups */
        $groups = [];
        $rows = BankTransaction::query()
            ->with('paymentMethod')
            ->where('amount', '<', 0)
            ->whereNotNull('date')
            ->orderBy('date')
            ->get();

        foreach ($rows as $tx) {
            $key = $this->merchantKey($tx);
            if ($key === '') {
                continue;
            }
            $groups[$key][] = $tx;
        }

        $out = [];
        foreach ($groups as $rowsOfMerchant) {
            $found = $this->analyse($rowsOfMerchant);
            if ($found !== null) {
                $out[] = $found;
            }
        }

        // Most expensive per year first — that is the order someone reviewing
        // their subscriptions wants to read.
        usort($out, fn (array $a, array $b): int => ($b['yearly'] <=> $a['yearly']));

        return $out;
    }

    /**
     * @param  list<BankTransaction>  $rows
     * @return array<string, mixed>|null
     */
    private function analyse(array $rows): ?array
    {
        if (count($rows) < self::MIN_CHARGES) {
            return null;
        }

        /** @var list<Carbon> $dates */
        $dates = [];
        foreach ($rows as $tx) {
            $d = $tx->date;
            if ($d instanceof Carbon) {
                $dates[] = $d->copy()->startOfDay();
            }
        }
        if (count($dates) < self::MIN_CHARGES) {
            return null;
        }

        /** @var list<int> $gaps */
        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) {
            $gaps[] = (int) $dates[$i - 1]->diffInDays($dates[$i]);
        }
        $median = $this->median($gaps);
        if ($median <= 0) {
            return null; // several charges on one day is a split payment, not a rhythm
        }

        $cadence = $this->cadenceFor($median);
        if ($cadence === null) {
            return null;
        }

        // A rhythm has to actually hold. One late charge is fine; a scatter of
        // unrelated payments to the same merchant is not a subscription.
        $near = 0;
        foreach ($gaps as $gap) {
            if (abs($gap - $median) <= max(3, (int) round($median * 0.25))) {
                $near++;
            }
        }
        if ($near / count($gaps) < self::CONSISTENCY) {
            return null;
        }

        $amounts = array_map(static fn (BankTransaction $tx): float => abs((float) $tx->amount), $rows);
        $last = $rows[count($rows) - 1];
        $lastAmount = abs((float) $last->amount);
        $lastDate = $dates[count($dates) - 1];

        // Price change: compare the most recent charge against the one before it,
        // and only report a change that survived (i.e. is not a one-off blip).
        $priceChange = null;
        if (count($amounts) >= 2) {
            $previous = $amounts[count($amounts) - 2];
            if (abs($lastAmount - $previous) > 0.01) {
                $priceChange = [
                    'from' => round($previous, 2),
                    'to' => round($lastAmount, 2),
                    'at' => $lastDate->toDateString(),
                ];
            }
        }

        // Stale = the next charge is more than half an interval overdue. Either it
        // was cancelled, or it silently stopped — both are worth surfacing.
        $due = $lastDate->copy()->addDays($median);
        $stale = $due->copy()->addDays((int) round($median * 0.5))->isPast();

        return [
            'merchant' => (string) ($last->counterparty ?? ''),
            'scope' => FinanceScope::ofTransaction($last),
            'cadence' => $cadence,
            'interval_days' => $median,
            'charges' => count($rows),
            'amount' => round($lastAmount, 2),
            // Annualised from the CADENCE, not the measured gap: a leap year or a
            // charge that slipped by two days must not move the yearly figure.
            'yearly' => round($lastAmount * self::CADENCES[$cadence][2], 2),
            'first_at' => $dates[0]->toDateString(),
            'last_at' => $lastDate->toDateString(),
            'next_at' => $due->toDateString(),
            'stale' => $stale,
            'price_change' => $priceChange,
            'transaction_ids' => array_map(static fn (BankTransaction $tx): int => (int) $tx->id, $rows),
        ];
    }

    /** @param list<int> $values */
    private function median(array $values): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);
        $mid = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$mid]
            : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    private function cadenceFor(int $days): ?string
    {
        foreach (self::CADENCES as $label => [$min, $max, $_perYear]) {
            if ($days >= $min && $days <= $max) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Group key for a merchant: the counterparty with legal forms, punctuation and
     * the noise banks add stripped, so "NETCUP GmbH" and "netcup Deutschland"
     * land together instead of reading as two subscriptions.
     */
    private function merchantKey(BankTransaction $tx): string
    {
        $name = mb_strtolower(trim((string) ($tx->counterparty ?? '')));
        if ($name === '') {
            return '';
        }
        $name = (string) preg_replace('/\b(gmbh|ag|ug|kg|ohg|mbh|e\.?k\.?|ltd|limited|inc|llc|plc|s\.?a\.?|b\.?v\.?|co|company|deutschland|germany|europe|eu)\b/u', ' ', $name);
        $name = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        // Statement lines often carry a per-charge reference; the first couple of
        // words are the merchant, the rest is bookkeeping noise.
        $words = array_slice(explode(' ', $name), 0, 3);

        return implode(' ', $words);
    }
}
