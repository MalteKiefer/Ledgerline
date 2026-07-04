<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\Todo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Exposes the shared to-dos as VTODO objects over CalDAV. The to-do row stays the
 * single source of truth: reads generate VTODO on the fly, and writes from a
 * CalDAV client (completion, title, due date) are applied back to the to-do. No
 * mirror table — the tasks calendar is virtual.
 */
class TodoVtodoBridge
{
    /** iCal PRIORITY (1 = highest) mapped to/from the to-do priority. */
    private const PRIORITY_TO_ICAL = ['high' => 1, 'normal' => 5, 'low' => 9];

    public function uriFor(Todo $todo): string
    {
        return 'todo-'.$todo->id.'.ics';
    }

    private function idFromUri(string $uri): ?int
    {
        return preg_match('/^todo-(\d+)\.ics$/', $uri, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Rows for getCalendarObjects (sabre shape). $withData includes the ICS.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $calendarId): array
    {
        return Todo::query()->orderBy('id')->get()->map(fn (Todo $t): array => $this->row($t, $calendarId, false))->all();
    }

    /** @return array<string, mixed>|null */
    public function get(string $calendarId, string $uri): ?array
    {
        $id = $this->idFromUri($uri);
        $todo = $id !== null ? Todo::find($id) : null;

        return $todo !== null ? $this->row($todo, $calendarId, true) : null;
    }

    /**
     * Apply a client-written VTODO back to a to-do (create or update). Returns the
     * etag of the resulting object, or null if the payload had no VTODO.
     */
    public function write(string $uri, string $ics): ?string
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return null;
        }
        $vtodo = $vcal->VTODO[0] ?? null;
        if ($vtodo === null) {
            return null;
        }

        // Only edits to an existing to-do (todo-<id>.ics) are honoured; a forged
        // or unknown URI is rejected rather than silently creating a mismatched
        // to-do (new tasks are created inside the app).
        $id = $this->idFromUri($uri);
        $todo = $id !== null ? Todo::find($id) : null;
        if ($todo === null) {
            return null;
        }

        if (isset($vtodo->SUMMARY) && filled((string) $vtodo->SUMMARY)) {
            $todo->title = (string) $vtodo->SUMMARY;
        }
        if (isset($vtodo->DESCRIPTION)) {
            $todo->description = (string) $vtodo->DESCRIPTION;
        }
        if (isset($vtodo->DUE)) {
            $todo->due_at = Carbon::instance($vtodo->DUE->getDateTime());
        }
        if (isset($vtodo->PRIORITY)) {
            $todo->priority = $this->priorityFromICal((int) (string) $vtodo->PRIORITY);
        }
        $status = isset($vtodo->STATUS) ? strtoupper((string) $vtodo->STATUS) : null;
        if ($status !== null) {
            $todo->done = $status === 'COMPLETED';
        }
        $todo->save();

        return '"'.md5($this->buildVtodo($todo)).'"';
    }

    public function delete(string $uri): void
    {
        $id = $this->idFromUri($uri);
        if ($id !== null) {
            Todo::where('id', $id)->get()->each->delete();
        }
    }

    /** Serialize a to-do as a VTODO ICS document. */
    public function buildVtodo(Todo $todo): string
    {
        $vcal = new VCalendar;
        $vtodo = $vcal->add('VTODO', ['UID' => 'todo-'.$todo->id]);
        $vtodo->add('SUMMARY', (string) $todo->title);
        if (filled($todo->description)) {
            $vtodo->add('DESCRIPTION', (string) $todo->description);
        }
        if ($todo->due_at !== null) {
            $vtodo->add('DUE', $todo->due_at);
        }
        $vtodo->add('PRIORITY', (string) (self::PRIORITY_TO_ICAL[$todo->priority] ?? 5));
        $vtodo->add('STATUS', $todo->done ? 'COMPLETED' : 'NEEDS-ACTION');
        if ($todo->done) {
            $vtodo->add('PERCENT-COMPLETE', '100');
            // RFC5545: COMPLETED is the completion instant (UTC DATE-TIME).
            $vtodo->add('COMPLETED', ($todo->updated_at ?? now())->utc());
        }
        if (filled($todo->url)) {
            $vtodo->add('URL', (string) $todo->url);
        }

        return $vcal->serialize();
    }

    /** @return array<string, mixed> */
    private function row(Todo $todo, string $calendarId, bool $withData): array
    {
        $ics = $this->buildVtodo($todo);
        $row = [
            'id' => $todo->id,
            'calendarid' => $calendarId,
            'uri' => $this->uriFor($todo),
            'lastmodified' => $todo->updated_at?->getTimestamp(),
            'etag' => '"'.md5($ics).'"',
            'size' => strlen($ics),
            'component' => 'vtodo',
        ];
        if ($withData) {
            $row['calendardata'] = $ics;
        }

        return $row;
    }

    private function priorityFromICal(int $priority): string
    {
        if ($priority >= 1 && $priority <= 4) {
            return 'high';
        }
        if ($priority >= 6 && $priority <= 9) {
            return 'low';
        }

        return 'normal';
    }

    /** The tasks calendars (there is normally one per DAV principal). */
    public function tasksCalendars(): Collection
    {
        return Calendar::where('uri', 'tasks')->get();
    }
}
