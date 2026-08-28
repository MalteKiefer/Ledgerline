<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared;

use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;

final readonly class Money
{
    private const int SCALE = 2;

    private const int MAX_MINOR = 99_999_999_999_999;

    private function __construct(private int $minor, private string $currency) {}

    public static function fromDecimal(string $amount, string $currency): self
    {
        if (preg_match('/\A(-?)(\d+)(?:\.(\d{1,2}))?\z/D', $amount, $parts) !== 1) {
            throw new InvalidMoney('Amount must be a canonical decimal with at most two fraction digits.');
        }

        $whole = self::parseWhole($parts[2]);
        $fraction = str_pad($parts[3] ?? '', self::SCALE, '0');
        $minor = self::checkedAdd(self::checkedMultiply($whole, 100), (int) $fraction);

        if ($parts[1] === '-') {
            $minor = -$minor;
        }

        return self::fromMinor($minor, $currency);
    }

    public static function fromMinor(int $minor, string $currency): self
    {
        if ($minor > self::MAX_MINOR || $minor < -self::MAX_MINOR) {
            throw new InvalidMoney('Amount exceeds the supported decimal(14,2) range.');
        }

        return new self($minor, self::normalizeCurrency($currency));
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromMinor(self::checkedAdd($this->minor, $other->minor), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromMinor(self::checkedSubtract($this->minor, $other->minor), $this->currency);
    }

    private static function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper($currency);

        if (preg_match('/\A[A-Z]{3}\z/D', $normalized) !== 1) {
            throw new InvalidMoney('Currency must be a three-letter ISO currency code.');
        }

        return $normalized;
    }

    private static function parseWhole(string $whole): int
    {
        $normalized = ltrim($whole, '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        if (strlen($normalized) > 12 || (strlen($normalized) === 12 && strcmp($normalized, '999999999999') > 0)) {
            throw new InvalidMoney('Amount exceeds the supported decimal(14,2) range.');
        }

        return (int) $normalized;
    }

    private static function checkedMultiply(int $value, int $multiplier): int
    {
        if ($value > intdiv(self::MAX_MINOR, $multiplier)) {
            throw new InvalidMoney('Amount exceeds the supported decimal(14,2) range.');
        }

        return $value * $multiplier;
    }

    private static function checkedAdd(int $left, int $right): int
    {
        if (($right > 0 && $left > self::MAX_MINOR - $right)
            || ($right < 0 && $left < -self::MAX_MINOR - $right)) {
            throw new InvalidMoney('Amount exceeds the supported decimal(14,2) range.');
        }

        return $left + $right;
    }

    private static function checkedSubtract(int $left, int $right): int
    {
        if (($right < 0 && $left > self::MAX_MINOR + $right)
            || ($right > 0 && $left < -self::MAX_MINOR + $right)) {
            throw new InvalidMoney('Amount exceeds the supported decimal(14,2) range.');
        }

        return $left - $right;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidMoney('Money values must have the same currency.');
        }
    }
}
