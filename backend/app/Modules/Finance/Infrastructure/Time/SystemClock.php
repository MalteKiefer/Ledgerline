<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Time;

use App\Modules\Finance\Application\Ports\Clock;
use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
