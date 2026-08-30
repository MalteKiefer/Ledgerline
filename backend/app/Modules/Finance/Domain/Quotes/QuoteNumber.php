<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Quotes;

use InvalidArgumentException;

final readonly class QuoteNumber
{
    public function __construct(private string $base)
    {
        if (trim($base) === '') {
            throw new InvalidArgumentException('Quote base number must not be empty.');
        }
    }

    public function base(): string
    {
        return $this->base;
    }

    public function revisionLabel(int $revision): string
    {
        if ($revision < 1) {
            throw new InvalidArgumentException('Quote revision number must be positive.');
        }

        return $revision === 1
            ? $this->base
            : sprintf('%s-R%d', $this->base, $revision);
    }
}
