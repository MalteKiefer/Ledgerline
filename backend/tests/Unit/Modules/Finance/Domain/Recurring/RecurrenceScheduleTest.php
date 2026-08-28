<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Recurring;

use App\Modules\Finance\Domain\Recurring\RecurrenceSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class RecurrenceScheduleTest extends TestCase
{
    public function test_month_end_anchor_survives_shorter_and_longer_months(): void
    {
        $schedule = RecurrenceSchedule::monthly($this->berlin('2026-01-31 08:00:00'));

        $february = $schedule->nextAfter($schedule->start());
        $this->assertInstanceOf(DateTimeImmutable::class, $february);
        $march = $schedule->nextAfter($february);

        $this->assertSame('2026-02-28T08:00:00+01:00', $february->format(DATE_ATOM));
        $this->assertSame('2026-03-31T08:00:00+02:00', $march?->format(DATE_ATOM));
    }

    public function test_leap_day_remains_a_february_month_end_anchor(): void
    {
        $schedule = RecurrenceSchedule::annual($this->berlin('2028-02-29 08:00:00'));

        $this->assertSame(
            '2029-02-28T08:00:00+01:00',
            $schedule->nextAfter($schedule->start())?->format(DATE_ATOM),
        );
    }

    public function test_quarterly_semiannual_and_annual_intervals_advance_calendar_months(): void
    {
        $start = $this->berlin('2026-01-31 08:00:00');

        $this->assertSame('2026-04-30', RecurrenceSchedule::quarterly($start)->nextAfter($start)?->format('Y-m-d'));
        $this->assertSame('2026-07-31', RecurrenceSchedule::semiannual($start)->nextAfter($start)?->format('Y-m-d'));
        $this->assertSame('2027-01-31', RecurrenceSchedule::annual($start)->nextAfter($start)?->format('Y-m-d'));
    }

    public function test_an_explicit_end_date_is_inclusive_and_then_stops_the_schedule(): void
    {
        $schedule = RecurrenceSchedule::monthly(
            $this->berlin('2026-01-31 08:00:00'),
            $this->berlin('2026-02-28 00:00:00'),
        );

        $last = $schedule->nextAfter($schedule->start());
        $this->assertInstanceOf(DateTimeImmutable::class, $last);

        $this->assertSame('2026-02-28T08:00:00+01:00', $last->format(DATE_ATOM));
        $this->assertNull($schedule->nextAfter($last));
    }

    public function test_local_wall_clock_time_is_preserved_across_berlin_dst_changes(): void
    {
        $spring = RecurrenceSchedule::monthly($this->berlin('2026-03-28 08:00:00'));
        $autumn = RecurrenceSchedule::monthly($this->berlin('2026-09-25 08:00:00'));

        $springNext = $spring->nextAfter($spring->start());
        $autumnNext = $autumn->nextAfter($autumn->start());

        $this->assertSame('2026-04-28T08:00:00+02:00', $springNext?->format(DATE_ATOM));
        $this->assertSame('2026-04-28T06:00:00+00:00', $springNext?->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM));
        $this->assertSame('2026-10-25T08:00:00+01:00', $autumnNext?->format(DATE_ATOM));
        $this->assertSame('2026-10-25T07:00:00+00:00', $autumnNext?->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM));
    }

    public function test_next_after_compares_instants_even_when_the_caller_uses_utc(): void
    {
        $schedule = RecurrenceSchedule::monthly($this->berlin('2026-01-31 08:00:00'));

        $next = $schedule->nextAfter($schedule->start()->setTimezone(new DateTimeZone('UTC')));

        $this->assertSame('2026-02-28T08:00:00+01:00', $next?->format(DATE_ATOM));
    }

    private function berlin(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('Europe/Berlin'));
    }
}
