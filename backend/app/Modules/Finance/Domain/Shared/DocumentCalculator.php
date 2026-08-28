<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared;

use App\Modules\Finance\Domain\Shared\Exception\InvalidDocument;

final class DocumentCalculator
{
    private const int QUANTITY_SCALE = 10_000;

    private const int RATE_SCALE = 10_000;

    private const int MAX_MINOR = 99_999_999_999_999;

    /**
     * @param  list<DocumentLine>  $lines
     */
    public function calculate(array $lines, Discount $discount): DocumentTotals
    {
        if ($lines === []) {
            throw new InvalidDocument('A document must contain at least one line.');
        }

        $currency = null;
        $rawLines = [];
        $totalNet = 0;

        foreach ($lines as $position => $line) {
            if (! $line instanceof DocumentLine) {
                throw new InvalidDocument('Every document line must be a DocumentLine.');
            }

            $currency ??= $line->unitPrice->currency();

            if ($line->unitPrice->currency() !== $currency) {
                throw new InvalidDocument('Every document line must use the same currency.');
            }

            if ($line->taxRateBasisPoints < 0 || $line->taxRateBasisPoints > self::RATE_SCALE) {
                throw new InvalidDocument('Tax rates must be between zero and 10000 basis points.');
            }

            $lineNet = $this->multiplyAndRound(
                $line->quantity->scaled(),
                $line->unitPrice->minor(),
                self::QUANTITY_SCALE,
            );
            $totalNet = $this->checkedAdd($totalNet, $lineNet);
            $rawLines[] = [
                'position' => $position,
                'taxRateBasisPoints' => $line->taxRateBasisPoints,
                'net' => $lineNet,
            ];
        }

        if ($discount->currency() !== $currency) {
            throw new InvalidDocument('The discount must use the document currency.');
        }

        $discountMinor = $this->discountMinor($discount, $totalNet);
        $allocations = $this->distributeDiscount($rawLines, $discountMinor, $totalNet);
        $groupNets = [];

        foreach ($rawLines as $index => $rawLine) {
            $rate = $rawLine['taxRateBasisPoints'];
            $discountedNet = $this->checkedSubtract($rawLine['net'], $allocations[$index]);
            $groupNets[$rate] = $this->checkedAdd($groupNets[$rate] ?? 0, $discountedNet);
        }

        ksort($groupNets, SORT_NUMERIC);

        $taxBreakdowns = [];
        $discountedTotalNet = 0;
        $totalVat = 0;

        foreach ($groupNets as $rate => $groupNet) {
            $vat = $this->multiplyAndRound($groupNet, $rate, self::RATE_SCALE);
            $gross = $this->checkedAdd($groupNet, $vat);
            $discountedTotalNet = $this->checkedAdd($discountedTotalNet, $groupNet);
            $totalVat = $this->checkedAdd($totalVat, $vat);
            $taxBreakdowns[] = new TaxBreakdown(
                $rate,
                Money::fromMinor($groupNet, $currency),
                Money::fromMinor($vat, $currency),
                Money::fromMinor($gross, $currency),
            );
        }

        $totalGross = $this->checkedAdd($discountedTotalNet, $totalVat);

        return new DocumentTotals(
            Money::fromMinor($discountedTotalNet, $currency),
            Money::fromMinor($totalVat, $currency),
            Money::fromMinor($totalGross, $currency),
            Money::fromMinor($discountMinor, $currency),
            $taxBreakdowns,
        );
    }

    private function discountMinor(Discount $discount, int $totalNet): int
    {
        if ($discount->isPercent()) {
            if ($discount->basisPoints() < 0 || $discount->basisPoints() > self::RATE_SCALE) {
                throw new InvalidDocument('Percentage discounts must be between zero and 10000 basis points.');
            }

            $discountMinor = $this->multiplyAndRound($totalNet, $discount->basisPoints(), self::RATE_SCALE);
        } else {
            $discountMinor = $discount->fixedMinor();
        }

        if ($discountMinor < 0 || ($discountMinor > 0 && $discountMinor > $totalNet)) {
            throw new InvalidDocument('The discount must be between zero and the document net.');
        }

        return $discountMinor;
    }

    /**
     * @param  list<array{position: int|string, taxRateBasisPoints: int, net: int}>  $rawLines
     * @return list<int>
     */
    private function distributeDiscount(array $rawLines, int $discountMinor, int $totalNet): array
    {
        $allocations = array_fill(0, count($rawLines), 0);

        if ($discountMinor === 0) {
            return $allocations;
        }

        foreach ($rawLines as $index => $rawLine) {
            $allocations[$index] = $this->multiplyDivideTowardZero($discountMinor, $rawLine['net'], $totalNet);
        }

        $allocated = 0;

        foreach ($allocations as $allocation) {
            $allocated = $this->checkedAdd($allocated, $allocation);
        }

        $remainder = $discountMinor - $allocated;
        $orderedIndexes = array_values(array_filter(
            array_keys($rawLines),
            static fn (int $index): bool => $remainder > 0
                ? $rawLines[$index]['net'] > 0
                : $rawLines[$index]['net'] < 0,
        ));
        usort($orderedIndexes, static function (int $left, int $right) use ($rawLines): int {
            return [$rawLines[$left]['taxRateBasisPoints'], $rawLines[$left]['position']]
                <=> [$rawLines[$right]['taxRateBasisPoints'], $rawLines[$right]['position']];
        });
        $increment = $remainder < 0 ? -1 : 1;

        for ($offset = 0; $offset < abs($remainder); $offset++) {
            $index = $orderedIndexes[$offset];
            $allocations[$index] += $increment;
        }

        return array_values($allocations);
    }

    private function multiplyAndRound(int $left, int $right, int $denominator): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        if ($left === PHP_INT_MIN || $right === PHP_INT_MIN) {
            throw new InvalidDocument('The calculated amount exceeds the supported money range.');
        }

        $absoluteLeft = abs($left);
        $absoluteRight = abs($right);
        $maximumNumerator = self::MAX_MINOR * $denominator + intdiv($denominator, 2) - 1;

        if ($absoluteLeft > intdiv($maximumNumerator, $absoluteRight)) {
            throw new InvalidDocument('The calculated amount exceeds the supported money range.');
        }

        return Rounding::halfAwayFromZero($left * $right, $denominator);
    }

    private function multiplyDivideTowardZero(int $left, int $right, int $denominator): int
    {
        $negative = ($left < 0) !== ($right < 0);
        $quotient = $this->floorMultiplyDivide(abs($left), abs($right), $denominator);

        return $negative ? -$quotient : $quotient;
    }

    private function floorMultiplyDivide(int $left, int $right, int $denominator): int
    {
        $whole = intdiv($left, $denominator);

        if ($whole !== 0 && $right > intdiv(self::MAX_MINOR, $whole)) {
            throw new InvalidDocument('A proportional discount share exceeds the supported money range.');
        }

        $quotient = $whole * $right;
        $remainder = 0;
        $addQuotient = 0;
        $addRemainder = $left % $denominator;
        $multiplier = $right;

        while ($multiplier > 0) {
            if ($multiplier % 2 === 1) {
                if ($quotient > self::MAX_MINOR - $addQuotient) {
                    throw new InvalidDocument('A proportional discount share exceeds the supported money range.');
                }

                $quotient += $addQuotient;
                $remainder += $addRemainder;

                if ($remainder >= $denominator) {
                    $quotient = $this->checkedAdd($quotient, 1);
                    $remainder -= $denominator;
                }
            }

            $multiplier = intdiv($multiplier, 2);

            if ($multiplier === 0) {
                break;
            }

            $doubledRemainder = $addRemainder * 2;
            $carry = intdiv($doubledRemainder, $denominator);
            $addRemainder = $doubledRemainder % $denominator;

            if ($addQuotient > intdiv(self::MAX_MINOR - $carry, 2)) {
                throw new InvalidDocument('A proportional discount share exceeds the supported money range.');
            }

            $addQuotient = $addQuotient * 2 + $carry;
        }

        return $quotient;
    }

    private function checkedAdd(int $left, int $right): int
    {
        if (($right > 0 && $left > self::MAX_MINOR - $right)
            || ($right < 0 && $left < -self::MAX_MINOR - $right)) {
            throw new InvalidDocument('The calculated amount exceeds the supported money range.');
        }

        return $left + $right;
    }

    private function checkedSubtract(int $left, int $right): int
    {
        if (($right < 0 && $left > self::MAX_MINOR + $right)
            || ($right > 0 && $left < -self::MAX_MINOR + $right)) {
            throw new InvalidDocument('The calculated amount exceeds the supported money range.');
        }

        return $left - $right;
    }
}
