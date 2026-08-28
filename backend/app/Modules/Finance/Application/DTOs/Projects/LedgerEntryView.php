<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;

final readonly class LedgerEntryView
{
    public function __construct(
        public ProjectId $projectId,
        public string $uuid,
        public string $direction,
        public int $amountMinor,
        public string $currency,
        public ?DateTimeImmutable $occurredOn,
        public ?string $title,
        public ?string $note,
        public ?string $categoryReference,
        public ?string $paymentMethodReference,
        public int $version,
    ) {}
}
