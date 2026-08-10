<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarTodo;

/**
 * Persists a task's ICS consistently: the etag + denormalised columns are derived
 * the same way everywhere, and the matching CalDAV change is logged into
 * calendar_todo_changes. Shared by the web controller, the importer and the CalDAV
 * backend so persistence and the task sync-collection log never drift apart.
 * Mirrors CalendarEventPersister; the owning user_id is stamped from the calendar
 * so a todo is always owned by the calendar's owner (never the acting session).
 */
class CalendarTodoPersister
{
    public function __construct(
        private readonly CalendarTodoService $todos,
        private readonly CalendarChangeLog $changes,
    ) {}

    public function persistNew(Calendar $calendar, string $uri, string $ics): CalendarTodo
    {
        $todo = CalendarTodo::create(array_merge([
            'calendar_id' => $calendar->id,
            'user_id' => $calendar->user_id,
            'uri' => $uri,
            'etag' => md5($ics),
            'ics' => $ics,
        ], $this->todos->denormalize($ics)));

        $this->changes->recordTodo($calendar, $uri, DavChangeOperation::Added);

        return $todo;
    }

    public function persistUpdate(CalendarTodo $todo, string $ics): CalendarTodo
    {
        $todo->forceFill(array_merge(
            ['etag' => md5($ics), 'ics' => $ics],
            $this->todos->denormalize($ics),
        ))->save();

        $calendar = $todo->calendar;
        if ($calendar !== null) {
            $this->changes->recordTodo($calendar, $todo->uri, DavChangeOperation::Modified);
        }

        return $todo;
    }
}
