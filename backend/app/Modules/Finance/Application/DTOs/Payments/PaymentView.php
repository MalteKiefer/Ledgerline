<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Payments;

use DateTimeImmutable;

final readonly class PaymentView
{
    public function __construct(
        public PaymentId $id,
        public string $uuid,
        public int $amountMinor,
        public int $allocatedMinor,
        public int $unappliedMinor,
        public string $currency,
        public DateTimeImmutable $receivedAt,
        public ?string $reference,
        public ?string $counterparty,
        public int $version,
        public ?int $paymentMethodId = null,
        public ?string $sourceType = null,
        public ?string $sourceKey = null,
    ) {}
}
