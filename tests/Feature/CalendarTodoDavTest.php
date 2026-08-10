<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\CalendarBackend;
use App\Models\Calendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Tests\TestCase;

/**
 * CalDAV VTODO wiring: a task-list calendar advertises the VTODO component set, a
 * VTODO PUT via the backend creates a calendar_todos row, and a VTODO comp-filter
 * calendar-query returns it. Drives the relational Sabre backend directly (mirrors
 * CalendarDavTest for events).
 */
class CalendarTodoDavTest extends TestCase
{
    use RefreshDatabase;

    private function taskList(int $userId): Calendar
    {
        return Calendar::create([
            'user_id' => $userId,
            'name' => 'Reminders',
            'uri' => 'tasks-'.$userId.'-'.Str::lower(Str::random(4)),
            'color' => '#6750a4',
            'component' => Calendar::COMPONENT_TODO,
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);
    }

    private function ics(string $uid): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n"
            ."BEGIN:VTODO\r\nUID:{$uid}\r\nSUMMARY:Buy milk\r\nDUE:20260810T170000Z\r\nSTATUS:NEEDS-ACTION\r\nEND:VTODO\r\n"
            ."END:VCALENDAR\r\n";
    }

    public function test_task_list_advertises_the_vtodo_component_set(): void
    {
        $user = User::factory()->create();
        $this->taskList($user->id);
        Auth::login($user);

        $calendars = app(CalendarBackend::class)->getCalendarsForUser('principals/'.$user->email);
        $this->assertCount(1, $calendars);
        $set = $calendars[0]['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'];
        $this->assertInstanceOf(SupportedCalendarComponentSet::class, $set);
        $this->assertSame(['VTODO'], $set->getValue());
    }

    public function test_mkcalendar_component_set_creates_a_vtodo_calendar(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $backend = app(CalendarBackend::class);

        $id = $backend->createCalendar('principals/'.$user->email, 'reminders', [
            '{DAV:}displayname' => 'Reminders',
            '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO']),
        ]);

        $this->assertSame(Calendar::COMPONENT_TODO, Calendar::findOrFail($id)->component);
    }

    public function test_put_creates_a_todo_row_and_query_returns_it(): void
    {
        $user = User::factory()->create();
        $calendar = $this->taskList($user->id);
        Auth::login($user);
        $backend = app(CalendarBackend::class);

        $etag = $backend->createCalendarObject($calendar->id, 'task-1.ics', $this->ics('dav-todo-1'));
        $this->assertIsString($etag);
        $this->assertDatabaseHas('calendar_todos', ['calendar_id' => $calendar->id, 'uri' => 'task-1.ics', 'summary' => 'Buy milk']);
        // Events table is untouched for a task-list calendar.
        $this->assertDatabaseCount('calendar_events', 0);

        $object = $backend->getCalendarObject($calendar->id, 'task-1.ics');
        $this->assertIsArray($object);
        $this->assertSame('vtodo', $object['component']);
        $this->assertStringContainsString('SUMMARY:Buy milk', (string) $object['calendardata']);

        // A VTODO comp-filter calendar-query matches the task; a VEVENT filter does not.
        $vtodoFilter = [
            'name' => 'VCALENDAR',
            'comp-filters' => [['name' => 'VTODO', 'comp-filters' => [], 'prop-filters' => [], 'is-not-defined' => false, 'time-range' => null]],
            'prop-filters' => [], 'is-not-defined' => false, 'time-range' => null,
        ];
        $this->assertSame(['task-1.ics'], $backend->calendarQuery($calendar->id, $vtodoFilter));

        $veventFilter = $vtodoFilter;
        $veventFilter['comp-filters'][0]['name'] = 'VEVENT';
        $this->assertSame([], $backend->calendarQuery($calendar->id, $veventFilter));

        // Sync-collection change log records the add in the task change table.
        $this->assertDatabaseHas('calendar_todo_changes', ['calendar_id' => $calendar->id, 'operation' => 1]);
        $initial = $backend->getChangesForCalendar($calendar->id, null, 1);
        $this->assertIsArray($initial);
        $this->assertContains('task-1.ics', $initial['added']);
    }

    public function test_backend_is_owner_scoped_for_task_writes(): void
    {
        $owner = User::factory()->create();
        $calendar = $this->taskList($owner->id);
        Auth::login($owner);
        app(CalendarBackend::class)->createCalendarObject($calendar->id, 'task-1.ics', $this->ics('u1'));

        Auth::login(User::factory()->create());
        $backend = app(CalendarBackend::class);
        $this->assertSame([], $backend->getCalendarObjects($calendar->id));
        $this->assertNull($backend->createCalendarObject($calendar->id, 'task-2.ics', $this->ics('u2')));
        $this->assertDatabaseMissing('calendar_todos', ['uri' => 'task-2.ics']);
    }
}
