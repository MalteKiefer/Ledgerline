<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

interface InvoiceNumberAllocator
{
    /** @return array{number: string, year: int, sequence: int} */
    public function allocate(int $ownerId, string $issueDate): array;
}
