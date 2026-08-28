<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Recurring;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OverflowException;

final readonly class RecurrenceSchedule
{
    private DateTimeZone $timezone;

    private int $anchorDay;

    private bool $monthEndAnchor;

    private ?string $endLocalDate;

    private function __construct(
        private RecurrenceInterval $interval,
        private DateTimeImmutable $start,
        ?DateTimeImmutable $end,
    ) {
        $this->timezone = $start->getTimezone();

        if ($this->timezone->getName() !== 'UTC' && $this->timezone->getLocation() === false) {
            throw new InvalidArgumentException('A recurrence schedule requires an IANA timezone.');
        }

        $this->anchorDay = (int) $start->format('j');
        $this->monthEndAnchor = $this->anchorDay === (int) $start->format('t');
        $this->endLocalDate = $end?->setTimezone($this->timezone)->format('Y-m-d');

        if ($this->endLocalDate !== null && $this->endLocalDate < $start->format('Y-m-d')) {
            throw new InvalidArgumentException('A recurrence end date cannot be before its start date.');
        }
    }

    public static function monthly(DateTimeImmutable $start, ?DateTimeImmutable $end = null): self
    {
        return new self(RecurrenceInterval::Monthly, $start, $end);
    }

    public static function quarterly(DateTimeImmutable $start, ?DateTimeImmutable $end = null): self
    {
        return new self(RecurrenceInterval::Quarterly, $start, $end);
    }

    public static function semiannual(DateTimeImmutable $start, ?DateTimeImmutable $end = null): self
    {
        return new self(RecurrenceInterval::Semiannual, $start, $end);
    }

    public static function annual(DateTimeImmutable $start, ?DateTimeImmutable $end = null): self
    {
        return new self(RecurrenceInterval::Annual, $start, $end);
    }

    public static function forInterval(
        RecurrenceInterval $interval,
        DateTimeImmutable $start,
        ?DateTimeImmutable $end = null,
    ): self {
        return new self($interval, $start, $end);
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function nextAfter(DateTimeImmutable $after): ?DateTimeImmutable
    {
        $localAfter = $after->setTimezone($this->timezone);

        if ($localAfter < $this->start) {
            return $this->withinEndDate($this->start) ? $this->start : null;
        }

        $monthDifference = (((int) $localAfter->format('Y') - (int) $this->start->format('Y')) * 12)
            + ((int) $localAfter->format('n') - (int) $this->start->format('n'));
        $step = intdiv($monthDifference, $this->interval->months());
        $candidate = $this->occurrenceAtStep($step);

        if ($candidate <= $localAfter) {
            $candidate = $this->occurrenceAtStep($step + 1);
        }

        return $this->withinEndDate($candidate) ? $candidate : null;
    }

    private function occurrenceAtStep(int $step): DateTimeImmutable
    {
        if ($step < 0 || $step > intdiv(PHP_INT_MAX, $this->interval->months())) {
            throw new OverflowException('The recurrence step exceeds the supported integer range.');
        }

        $months = $step * $this->interval->months();
        $firstOfStartMonth = $this->start->setDate(
            (int) $this->start->format('Y'),
            (int) $this->start->format('n'),
            1,
        );
        $firstOfTargetMonth = $months === 0
            ? $firstOfStartMonth
            : $firstOfStartMonth->modify(sprintf('+%d months', $months));
        $daysInTargetMonth = (int) $firstOfTargetMonth->format('t');
        $day = $this->monthEndAnchor
            ? $daysInTargetMonth
            : min($this->anchorDay, $daysInTargetMonth);

        return $firstOfTargetMonth->setDate(
            (int) $firstOfTargetMonth->format('Y'),
            (int) $firstOfTargetMonth->format('n'),
            $day,
        );
    }

    private function withinEndDate(DateTimeImmutable $candidate): bool
    {
        return $this->endLocalDate === null || $candidate->format('Y-m-d') <= $this->endLocalDate;
    }
}
