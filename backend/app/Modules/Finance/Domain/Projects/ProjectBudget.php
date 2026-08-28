<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Projects;

use App\Modules\Finance\Domain\Shared\Money;

final readonly class ProjectBudget
{
    private function __construct(private ?int $minor, private string $currency) {}

    public static function fromMinor(?int $minor, string $currency): self
    {
        $validated = Money::fromMinor($minor ?? 0, $currency);

        return new self($minor, $validated->currency());
    }

    public function minor(): ?int
    {
        return $this->minor;
    }

    public function currency(): string
    {
        return $this->currency;
    }
}
