<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A small money value object: an integer amount in minor units (cents) plus an
 * ISO 4217 currency code. Money is always stored and computed as integer cents
 * to avoid floating-point errors.
 */
final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency,
    ) {}

    /**
     * Build from a decimal major-unit amount (e.g. 12.50 EUR).
     */
    public static function fromAmount(float|string $amount, string $currency): self
    {
        return new self((int) round(((float) $amount) * 100), $currency);
    }

    /**
     * The amount in major units.
     */
    public function amount(): float
    {
        return $this->cents / 100;
    }

    /**
     * Formatted amount with the currency code (e.g. "1,234.56 EUR").
     */
    public function format(): string
    {
        return number_format($this->cents / 100, 2).' '.$this->currency;
    }
}
