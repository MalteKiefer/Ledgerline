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
        $firstOfStartMonth = DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            sprintf('%d-%d-1', (int) $this->start->format('Y'), (int) $this->start->format('n')),
            new DateTimeZone('UTC'),
        );

        if ($firstOfStartMonth === false) {
            throw new InvalidArgumentException('The recurrence start month is invalid.');
        }

        $firstOfTargetMonth = $months === 0
            ? $firstOfStartMonth
            : $firstOfStartMonth->modify(sprintf('+%d months', $months));
        $daysInTargetMonth = (int) $firstOfTargetMonth->format('t');
        $day = $this->monthEndAnchor
            ? $daysInTargetMonth
            : min($this->anchorDay, $daysInTargetMonth);

        return $this->resolveLocalDateTime(
            (int) $firstOfTargetMonth->format('Y'),
            (int) $firstOfTargetMonth->format('n'),
            $day,
        );
    }

    /**
     * Resolves a local calendar target at the timezone boundary.
     *
     * A nonexistent wall time moves forward by the exact DST gap. If a wall
     * time occurs twice, the earlier of its two UTC instants is selected.
     */
    private function resolveLocalDateTime(int $year, int $month, int $day): DateTimeImmutable
    {
        $microseconds = (int) $this->start->format('u');
        $wallTime = sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d.%06d',
            $year,
            $month,
            $day,
            (int) $this->start->format('H'),
            (int) $this->start->format('i'),
            (int) $this->start->format('s'),
            $microseconds,
        );
        $utc = new DateTimeZone('UTC');
        $naiveWallClock = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $wallTime, $utc);

        if ($naiveWallClock === false) {
            throw new InvalidArgumentException('The recurrence calendar target is invalid.');
        }

        if ($this->timezone->getName() === 'UTC') {
            return $naiveWallClock;
        }

        $naiveTimestamp = $naiveWallClock->getTimestamp();
        $transitions = $this->timezone->getTransitions(
            $naiveTimestamp - (3 * 86_400),
            $naiveTimestamp + (3 * 86_400),
        );

        if ($transitions === false || $transitions === []) {
            throw new InvalidArgumentException('The recurrence timezone has no transition data.');
        }

        /** @var array<int, true> $offsets */
        $offsets = [];

        foreach ($transitions as $transition) {
            $offsets[$transition['offset']] = true;
        }

        /** @var list<DateTimeImmutable> $matches */
        $matches = [];

        foreach (array_keys($offsets) as $offset) {
            $candidate = self::instantFromTimestamp($naiveTimestamp - $offset, $microseconds)
                ->setTimezone($this->timezone);

            if ($candidate->format('Y-m-d H:i:s.u') === $wallTime) {
                $matches[] = $candidate;
            }
        }

        if ($matches !== []) {
            usort(
                $matches,
                static fn (DateTimeImmutable $left, DateTimeImmutable $right): int => $left->getTimestamp() <=> $right->getTimestamp(),
            );

            return $matches[0];
        }

        $previousOffset = $transitions[0]['offset'];

        foreach (array_slice($transitions, 1) as $transition) {
            $nextOffset = $transition['offset'];

            if ($nextOffset > $previousOffset) {
                $gapStartsAt = $transition['ts'] + $previousOffset;
                $gapEndsAt = $transition['ts'] + $nextOffset;

                if ($naiveTimestamp >= $gapStartsAt && $naiveTimestamp < $gapEndsAt) {
                    return self::instantFromTimestamp(
                        $naiveTimestamp - $previousOffset,
                        $microseconds,
                    )->setTimezone($this->timezone);
                }
            }

            $previousOffset = $nextOffset;
        }

        throw new InvalidArgumentException('The recurrence wall time cannot be resolved.');
    }

    private static function instantFromTimestamp(int $timestamp, int $microseconds): DateTimeImmutable
    {
        $instant = DateTimeImmutable::createFromFormat(
            'U.u',
            sprintf('%d.%06d', $timestamp, $microseconds),
            new DateTimeZone('UTC'),
        );

        if ($instant === false) {
            throw new InvalidArgumentException('The recurrence instant is invalid.');
        }

        return $instant->setTimezone(new DateTimeZone('UTC'));
    }

    private function withinEndDate(DateTimeImmutable $candidate): bool
    {
        return $this->endLocalDate === null || $candidate->format('Y-m-d') <= $this->endLocalDate;
    }
}
