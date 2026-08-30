<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CreateLedgerEntryData
{
    public string $currency;

    public function __construct(
        public ProjectId $projectId,
        public string $direction,
        public int $amountMinor,
        string $currency,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
        public ?DateTimeImmutable $occurredOn = null,
        public ?string $title = null,
        public ?string $note = null,
        public ?string $categoryReference = null,
        public ?string $paymentMethodReference = null,
    ) {
        if (! in_array($direction, ['out', 'in'], true) || $amountMinor <= 0) {
            throw new InvalidArgumentException('ledger_entry_invalid');
        }
        $this->currency = Money::fromMinor(0, $currency)->currency();
    }
}
