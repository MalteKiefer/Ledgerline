<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Recurring;

use DateTimeImmutable;

final readonly class RecurringRunView
{
    public function __construct(
        public RecurringRunId $id,
        public string $uuid,
        public RecurringTemplateId $templateId,
        public int $templateVersionId,
        public DateTimeImmutable $scheduledFor,
        public string $scheduledLocalDate,
        public string $status,
        public ?string $lastCompletedStep,
        public ?int $invoiceId,
        public ?int $deliveryId,
        public int $attempts,
        public ?DateTimeImmutable $claimedAt,
        public ?DateTimeImmutable $claimExpiresAt,
        public ?DateTimeImmutable $nextRetryAt,
        public ?string $lastErrorCode,
        public ?string $lastErrorDetail,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
