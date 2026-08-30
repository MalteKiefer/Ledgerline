<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use DateTimeImmutable;

interface InventoryMovementPort
{
    /** @param array<int, int> $quantityScaledByProduct */
    public function recordInvoiceSale(
        int $ownerId,
        string $invoiceUuid,
        array $quantityScaledByProduct,
        DateTimeImmutable $occurredAt,
    ): void;
}
