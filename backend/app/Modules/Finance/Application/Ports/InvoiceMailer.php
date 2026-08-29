<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;

interface InvoiceMailer
{
    public function assertConfigured(int $ownerId): void;

    public function assertDocumentReady(string $path, string $sha256): void;

    public function dispatch(int $ownerId, DeliveryId $deliveryId): void;
}
