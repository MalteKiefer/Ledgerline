<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class LogTimeData
{
    public DecimalQuantity $quantity;

    public string $currency;

    public function __construct(
        public ProjectId $projectId,
        public ?string $workItemUuid,
        public DateTimeImmutable $workedOn,
        mixed $hours,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
        public ?string $description = null,
        public bool $billable = true,
        public ?Money $hourlyRate = null,
        string $currency = 'EUR',
    ) {
        if (! is_string($hours)) {
            throw new InvalidArgumentException('time_quantity_must_be_decimal_string');
        }

        $this->quantity = DecimalQuantity::fromString($hours);
        $this->currency = Money::fromMinor(0, $currency)->currency();
        if ($hourlyRate !== null && $hourlyRate->currency() !== $this->currency) {
            throw new InvalidArgumentException('time_rate_currency_mismatch');
        }
    }
}
