<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared;

use App\Modules\Finance\Domain\Shared\Exception\InvalidQuantity;

final readonly class DecimalQuantity
{
    private const int SCALE = 4;

    private function __construct(private int $scaled) {}

    public static function fromString(string $quantity): self
    {
        if (preg_match('/\A(-?)(\d+)(?:\.(\d{1,4}))?\z/D', $quantity, $parts) !== 1) {
            throw new InvalidQuantity('Quantity must be a canonical decimal with at most four fraction digits.');
        }

        $negative = $parts[1] === '-';
        $scaled = self::parseScaled($parts[2].str_pad($parts[3] ?? '', self::SCALE, '0'), $negative);

        return new self($scaled);
    }

    public function scaled(): int
    {
        return $this->scaled;
    }

    private static function parseScaled(string $digits, bool $negative): int
    {
        $normalized = ltrim($digits, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximum = $negative ? ltrim((string) PHP_INT_MIN, '-') : (string) PHP_INT_MAX;

        if (strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)) {
            throw new InvalidQuantity('Quantity exceeds the supported integer range.');
        }

        if ($negative && $normalized === $maximum) {
            return PHP_INT_MIN;
        }

        $scaled = (int) $normalized;

        return $negative ? -$scaled : $scaled;
    }
}
