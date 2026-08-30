<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

interface DocumentRenderer
{
    /** @param array<array-key, mixed> $snapshot */
    public function render(array $snapshot): string;
}
