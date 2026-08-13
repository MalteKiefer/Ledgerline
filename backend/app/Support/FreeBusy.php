<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CalendarEvent;
use App\Services\Calendar\CalendarEventService;
use Carbon\CarbonImmutable;

/**
 * Free/busy computation + slot finding over a set of calendars. Busy intervals
 * come from recurrence-expanded events (cancelled events skipped); slots are the
 * gaps that fit a duration within a daily working window.
 */
final class FreeBusy
{
    public function __construct(private readonly CalendarEventService $events) {}

    /**
     * Merged busy intervals (UTC) across the given calendars in [from,to].
     *
     * @param  list<int|string>  $calendarIds
     * @return list<array{start:CarbonImmutable,end:CarbonImmutable}>
     */
    public function busy(array $calendarIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($calendarIds === []) {
            return [];
        }
        $candidates = CalendarEvent::query()
            ->whereIn('calendar_id', $calendarIds)
            ->whereNotNull('dtstart')
            ->where('dtstart', '<', $to)
            ->where(fn ($w) => $w->whereNull('dtend')->orWhere('dtend', '>', $from)->orWhereNotNull('rrule'))
            ->get();

        $intervals = [];
        foreach ($candidates as $event) {
            foreach ($this->events->expand($event, $from, $to) as $occ) {
                if (($occ['status'] ?? null) === 'CANCELLED') {
                    continue;
                }
                $s = CarbonImmutable::parse((string) $occ['start']);
                $e = CarbonImmutable::parse((string) $occ['end']);
                if ($e->lessThanOrEqualTo($s)) {
                    continue;
                }
                $intervals[] = ['start' => $s->max($from), 'end' => $e->min($to)];
            }
        }

        return $this->merge($intervals);
    }

    /**
     * Free slots of at least $durationMin within a daily [$dayStart,$dayEnd]
     * window, avoiding the given busy intervals. The daily window is wall-clock
     * in $tz (the caller's timezone) so "08:00–18:00" means their local hours,
     * not UTC; returned slots are absolute UTC 'Z' instants.
     *
     * @param  list<array{start:CarbonImmutable,end:CarbonImmutable}>  $busy
     * @return list<array{start:string,end:string}>
     */
    public function freeSlots(array $busy, CarbonImmutable $from, CarbonImmutable $to, int $durationMin, int $dayStart, int $dayEnd, string $tz = 'UTC'): array
    {
        $busy = $this->merge($busy);
        $dur = max(5, $durationMin);
        $slots = [];
        // Walk days in the caller's timezone so the window boundaries land on
        // their local wall-clock hours (DST-correct).
        $day = $from->setTimezone($tz)->startOfDay();
        while ($day->lessThan($to) && count($slots) < 50) {
            $winStart = $day->setTime($dayStart, 0)->max($from);
            $winEnd = ($dayEnd >= 24 ? $day->addDay()->startOfDay() : $day->setTime($dayEnd, 0))->min($to);
            $cursor = $winStart;
            foreach ($busy as $b) {
                if ($b['end']->lessThanOrEqualTo($winStart) || $b['start']->greaterThanOrEqualTo($winEnd)) {
                    continue;
                }
                if ($b['start']->greaterThan($cursor) && $cursor->diffInMinutes($b['start']) >= $dur) {
                    $slots[] = ['start' => $cursor->toIso8601ZuluString(), 'end' => $b['start']->toIso8601ZuluString()];
                }
                $cursor = $cursor->max($b['end']);
            }
            if ($cursor->lessThan($winEnd) && $cursor->diffInMinutes($winEnd) >= $dur) {
                $slots[] = ['start' => $cursor->toIso8601ZuluString(), 'end' => $winEnd->toIso8601ZuluString()];
            }
            $day = $day->addDay();
        }

        return $slots;
    }

    /**
     * @param  list<array{start:CarbonImmutable,end:CarbonImmutable}>  $intervals
     * @return list<array{start:CarbonImmutable,end:CarbonImmutable}>
     */
    private function merge(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }
        usort($intervals, fn (array $a, array $b): int => $a['start']->getTimestamp() <=> $b['start']->getTimestamp());
        $out = [array_shift($intervals)];
        foreach ($intervals as $iv) {
            $last = &$out[count($out) - 1];
            if ($iv['start']->lessThanOrEqualTo($last['end'])) {
                if ($iv['end']->greaterThan($last['end'])) {
                    $last['end'] = $iv['end'];
                }
            } else {
                $out[] = $iv;
            }
            unset($last);
        }

        return $out;
    }
}
