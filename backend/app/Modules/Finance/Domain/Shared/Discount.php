<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared;

final readonly class Discount
{
    private const string NONE = 'none';

    private const string PERCENT = 'percent';

    private const string FIXED = 'fixed';

    private function __construct(
        private string $type,
        private string $currency,
        private int $basisPoints = 0,
        private ?Money $amount = null,
    ) {}

    public static function none(string $currency): self
    {
        return new self(self::NONE, Money::fromMinor(0, $currency)->currency());
    }

    public static function percentBasisPoints(int $basisPoints, string $currency): self
    {
        return new self(self::PERCENT, Money::fromMinor(0, $currency)->currency(), $basisPoints);
    }

    public static function fixed(Money $amount): self
    {
        return new self(self::FIXED, $amount->currency(), amount: $amount);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function isPercent(): bool
    {
        return $this->type === self::PERCENT;
    }

    public function basisPoints(): int
    {
        return $this->basisPoints;
    }

    public function fixedMinor(): int
    {
        return $this->amount?->minor() ?? 0;
    }
}
