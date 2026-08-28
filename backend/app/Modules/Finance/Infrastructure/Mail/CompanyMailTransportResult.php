<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

final readonly class CompanyMailTransportResult
{
    private function __construct(public bool $accepted) {}

    public static function accepted(): self
    {
        return new self(true);
    }
}
