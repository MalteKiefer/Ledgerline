<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Recurring;

use App\Modules\Finance\Domain\Recurring\RecurrenceInterval;
use App\Modules\Finance\Domain\Recurring\RecurrenceSchedule;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RecurringTemplateData
{
    private RecurrenceSchedule $schedule;

    public function __construct(
        public string $mode,
        public RecurrenceInterval $interval,
        public string $timezone,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public string $runTime,
        public RecurringTemplateVersionData $initialVersion,
    ) {
        if (! in_array($mode, ['draft', 'auto_send'], true)) {
            throw new InvalidArgumentException('Recurring invoice mode is invalid.');
        }

        $start = $startDate->format('Y-m-d');
        $end = $endDate?->format('Y-m-d');
        $this->schedule = RecurrenceSchedule::fromLocal(
            $interval,
            $start,
            $runTime,
            $timezone,
            $end,
        );

        if ($initialVersion->effectiveLocalDate() !== $start) {
            throw new InvalidArgumentException('The initial recurring template version must be effective on the start date.');
        }
    }

    public function startLocalDate(): string
    {
        return $this->startDate->format('Y-m-d');
    }

    public function endLocalDate(): ?string
    {
        return $this->endDate?->format('Y-m-d');
    }

    public function firstRunAt(): DateTimeImmutable
    {
        return $this->schedule->start();
    }

    public function anchorDay(): int
    {
        return (int) $this->startDate->format('j');
    }

    public function monthEndAnchor(): bool
    {
        return $this->anchorDay() === (int) $this->startDate->format('t');
    }
}
