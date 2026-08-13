<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\CalendarTodo;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Throwable;

/**
 * Imports an .ics file (one or many VTODOs) into a task-list calendar. Each task is
 * wrapped into its own VCALENDAR, deduped by UID (update in place), and persisted
 * through the shared persister so the task sync log stays consistent. Malformed
 * tasks are skipped, not fatal. Mirrors CalendarImporter.
 */
class CalendarTodoImporter
{
    public function __construct(
        private readonly CalendarTodoService $todos,
        private readonly CalendarTodoPersister $persister,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(Calendar $calendar, string $ics): array
    {
        return CalendarTodo::withoutEvents(fn (): array => $this->importTodos($calendar, $ics));
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importTodos(Calendar $calendar, string $ics): array
    {
        $created = $updated = $skipped = 0;

        foreach ($this->todos->parseCalendarStream($ics) as $vtodo) {
            try {
                $rawUid = $vtodo->UID ?? null;
                $uid = is_scalar($rawUid) || $rawUid instanceof \Stringable ? trim((string) $rawUid) : '';
                if ($uid === '') {
                    $uid = (string) Str::uuid();
                    $vtodo->remove('UID');
                    $vtodo->add('UID', $uid);
                }

                $wrapper = new VCalendar;
                $wrapper->add(clone $vtodo);
                $serialized = $wrapper->serialize();
                $todoIcs = is_string($serialized) ? $serialized : '';

                $existing = CalendarTodo::where('calendar_id', $calendar->id)->where('uid', $uid)->first();
                if ($existing !== null) {
                    $this->persister->persistUpdate($existing, $todoIcs);
                    $updated++;
                } else {
                    $this->persister->persistNew($calendar, Str::uuid().'.ics', $todoIcs);
                    $created++;
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
