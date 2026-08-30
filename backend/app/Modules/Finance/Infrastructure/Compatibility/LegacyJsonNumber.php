<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

/**
 * A JSON number lexeme captured verbatim by {@see LegacyJsonCursor}, never
 * cast through a PHP float. `lexeme` is the exact source text (e.g. `"12.50"`
 * or `"-3"`); `hasExponent` flags scientific notation so callers can reject
 * it explicitly instead of silently losing precision.
 */
final readonly class LegacyJsonNumber
{
    public function __construct(public string $lexeme, public bool $hasExponent) {}
}
