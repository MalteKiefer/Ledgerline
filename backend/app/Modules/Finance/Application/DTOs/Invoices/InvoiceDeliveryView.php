<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use DateTimeImmutable;

final readonly class InvoiceDeliveryView
{
    public function __construct(
        public DeliveryId $id,
        public int $invoiceId,
        public string $kind,
        public string $recipient,
        public string $status,
        public int $attempts,
        public ?DateTimeImmutable $lastAttemptAt,
        public ?DateTimeImmutable $sentAt,
        public ?DateTimeImmutable $nextRetryAt,
        public ?string $lastErrorCode,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
