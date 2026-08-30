<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class InvoiceTimeLine
{
    public function __construct(public int $hoursScaled, public int $hourlyRateMinor, public int $valueMinor, public string $currency, public string $description) {}
}
