<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\CalendarBackend;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CalDAV wiring: the relational Sabre backend, owner-scoped listing and the
 * object PUT/GET path. The full DAV protocol exchange over the SAPI is verified
 * manually against a real client; here we drive the backend directly (mirrors
 * WebDavTest) plus the shared /dav 401 challenge.
 */
class CalendarDavTest extends TestCase
{
    use RefreshDatabase;

    private function calendar(int $userId): Calendar
    {
        return Calendar::create([
            'user_id' => $userId,
            'name' => 'Personal',
            'uri' => 'calendar-'.$userId.'-'.Str::lower(Str::random(4)),
            'color' => '#6750a4',
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);
    }

    private function ics(string $uid): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n"
            ."BEGIN:VEVENT\r\nUID:{$uid}\r\nSUMMARY:Meeting\r\nDTSTART:20260803T090000Z\r\nDTEND:20260803T093000Z\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n";
    }

    public function test_dav_endpoint_requires_authentication(): void
    {
        $this->call('PROPFIND', '/dav/')->assertStatus(401);
    }

    public function test_backend_lists_only_the_owners_calendars(): void
    {
        $owner = $this->calendar(User::factory()->create()->id)->user_id;
        $ownerUser = User::findOrFail($owner);
        $this->calendar(User::factory()->create()->id); // a foreign calendar

        Auth::login($ownerUser);
        $backend = app(CalendarBackend::class);
        $calendars = $backend->getCalendarsForUser('principals/'.$ownerUser->email);

        $this->assertCount(1, $calendars);
        $this->assertSame('Personal', $calendars[0]['{DAV:}displayname']);
        $this->assertArrayHasKey('{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set', $calendars[0]);
    }

    public function test_put_creates_an_event_row_and_get_returns_bytes(): void
    {
        $user = User::factory()->create();
        $calendar = $this->calendar($user->id);
        Auth::login($user);
        $backend = app(CalendarBackend::class);

        $etag = $backend->createCalendarObject($calendar->id, 'evt-1.ics', $this->ics('dav-uid-1'));
        $this->assertIsString($etag);
        $this->assertDatabaseHas('calendar_events', ['calendar_id' => $calendar->id, 'uri' => 'evt-1.ics', 'summary' => 'Meeting']);

        $object = $backend->getCalendarObject($calendar->id, 'evt-1.ics');
        $this->assertIsArray($object);
        $this->assertStringContainsString('SUMMARY:Meeting', (string) $object['calendardata']);
        $this->assertSame($etag, $object['etag']);

        // Listing includes the object; a sync-token change was logged.
        $this->assertCount(1, $backend->getCalendarObjects($calendar->id));
        $this->assertDatabaseHas('calendar_changes', ['calendar_id' => $calendar->id, 'operation' => 1]);
    }

    public function test_backend_is_owner_scoped_for_writes_and_reads(): void
    {
        $owner = User::factory()->create();
        $calendar = $this->calendar($owner->id);
        Auth::login($owner);
        app(CalendarBackend::class)->createCalendarObject($calendar->id, 'evt-1.ics', $this->ics('u1'));

        // A different user cannot read or write that calendar's objects.
        Auth::login(User::factory()->create());
        $backend = app(CalendarBackend::class);
        $this->assertSame([], $backend->getCalendarObjects($calendar->id));
        $this->assertNull($backend->getCalendarObject($calendar->id, 'evt-1.ics'));
        $this->assertNull($backend->createCalendarObject($calendar->id, 'evt-2.ics', $this->ics('u2')));
        $this->assertDatabaseMissing('calendar_events', ['uri' => 'evt-2.ics']);
        // The owner's event survived (no foreign delete).
        $this->assertSame(1, CalendarEvent::withoutGlobalScopes()->where('calendar_id', $calendar->id)->count());
    }

    public function test_get_changes_reports_initial_and_incremental(): void
    {
        $user = User::factory()->create();
        $calendar = $this->calendar($user->id);
        Auth::login($user);
        $backend = app(CalendarBackend::class);
        $backend->createCalendarObject($calendar->id, 'evt-1.ics', $this->ics('u1'));

        $initial = $backend->getChangesForCalendar($calendar->id, null, 1);
        $this->assertIsArray($initial);
        $this->assertContains('evt-1.ics', $initial['added']);

        $token = $initial['syncToken'];
        $backend->createCalendarObject($calendar->id, 'evt-2.ics', $this->ics('u2'));
        $delta = $backend->getChangesForCalendar($calendar->id, $token, 1);
        $this->assertIsArray($delta);
        $this->assertContains('evt-2.ics', $delta['added']);
        $this->assertNotContains('evt-1.ics', $delta['added']);
    }
}
