<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\AuthBackend;
use App\Dav\CalDavBackend;
use App\Dav\DavContext;
use App\Dav\PrincipalBackend;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Services\Calendar\ICalService;
use App\Services\Contacts\DavCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Plugin;
use Sabre\DAV\Server;
use Sabre\DAVACL\PrincipalCollection;
use Tests\TestCase;

class DavCalendarTest extends TestCase
{
    use RefreshDatabase;

    private const VEVENT = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\nBEGIN:VEVENT\r\nUID:evt-1\r\nSUMMARY:Standup\r\nDTSTART:20260810T090000Z\r\nDTEND:20260810T093000Z\r\nLOCATION:Office\r\nRRULE:FREQ=DAILY;COUNT=5\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    public function test_generate_creates_a_default_calendar(): void
    {
        app(DavCredentialService::class)->generate(1);

        $this->assertDatabaseHas('calendars', ['user_id' => 1, 'uri' => 'default', 'name' => 'Calendar']);
    }

    public function test_ical_build_and_denormalize_round_trip(): void
    {
        $ical = app(ICalService::class);

        $ics = $ical->buildEvent([
            'summary' => 'Lunch',
            'start' => '2026-08-10 12:00:00',
            'end' => '2026-08-10 13:00:00',
            'location' => 'Cafe',
            'rrule' => 'FREQ=WEEKLY;COUNT=3',
            'reminder_minutes' => 15,
        ]);

        $this->assertStringContainsString('SUMMARY:Lunch', $ics);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;COUNT=3', $ics);
        $this->assertStringContainsString('TRIGGER:-PT15M', $ics);

        $data = $ical->denormalize($ics);
        $this->assertSame('VEVENT', $data['component']);
        $this->assertSame('Lunch', $data['summary']);
        $this->assertSame('2026-08-10 12:00:00', $data['starts_at']);
        $this->assertSame('2026-08-10 13:00:00', $data['ends_at']);
        $this->assertFalse($data['all_day']);
        $this->assertSame('FREQ=WEEKLY;COUNT=3', $data['rrule']);
    }

    public function test_ical_all_day_event_round_trip(): void
    {
        $ical = app(ICalService::class);
        $ics = $ical->buildEvent(['summary' => 'Holiday', 'start' => '2026-12-25', 'all_day' => true]);

        $data = $ical->denormalize($ics);
        $this->assertTrue($data['all_day']);
        $this->assertSame('Holiday', $data['summary']);
    }

    public function test_ical_expand_unrolls_recurrence_within_window(): void
    {
        $ical = app(ICalService::class);

        $instances = $ical->expand(
            self::VEVENT,
            new \DateTimeImmutable('2026-08-10 00:00:00'),
            new \DateTimeImmutable('2026-08-31 00:00:00'),
        );

        // FREQ=DAILY;COUNT=5 → 5 instances.
        $this->assertCount(5, $instances);
        $this->assertSame('2026-08-10 09:00:00', $instances[0]['start']);
    }

    public function test_caldav_backend_stores_reads_updates_and_deletes_objects(): void
    {
        app(DavCredentialService::class)->generate(1);
        app(DavContext::class)->set(1);
        $calendar = Calendar::where('user_id', 1)->firstOrFail();
        $backend = app(CalDavBackend::class);

        // Create.
        $etag = $backend->createCalendarObject($calendar->id, 'evt.ics', self::VEVENT);
        $this->assertNotNull($etag);
        $object = CalendarObject::where('calendar_id', $calendar->id)->where('uri', 'evt.ics')->firstOrFail();
        $this->assertSame('Standup', $object->summary);
        $this->assertSame('VEVENT', $object->component);
        $this->assertSame('FREQ=DAILY;COUNT=5', $object->rrule);
        $this->assertSame(2, $calendar->fresh()->synctoken);
        $this->assertDatabaseHas('calendar_changes', ['uri' => 'evt.ics', 'operation' => 1]);

        // Read.
        $row = $backend->getCalendarObject($calendar->id, 'evt.ics');
        $this->assertSame(self::VEVENT, $row['calendardata']);

        // Sync since token 1 → the added object.
        $changes = $backend->getChangesForCalendar($calendar->id, '1', 1);
        $this->assertContains('evt.ics', $changes['added']);

        // Update.
        $backend->updateCalendarObject($calendar->id, 'evt.ics', str_replace('Standup', 'Retro', self::VEVENT));
        $this->assertSame('Retro', $object->fresh()->summary);
        $this->assertSame(3, $calendar->fresh()->synctoken);

        // Delete.
        $backend->deleteCalendarObject($calendar->id, 'evt.ics');
        $this->assertNull($backend->getCalendarObject($calendar->id, 'evt.ics'));
        $this->assertSame(4, $calendar->fresh()->synctoken);
    }

    public function test_backend_denies_access_to_another_users_calendar(): void
    {
        app(DavCredentialService::class)->generate(1);
        $mine = Calendar::where('user_id', 1)->firstOrFail();
        $theirs = Calendar::create(['user_id' => 2, 'uri' => 'default', 'name' => 'Theirs', 'components' => ['VEVENT'], 'synctoken' => 1]);

        app(DavContext::class)->set(1);
        $backend = app(CalDavBackend::class);

        // Own calendar: writable.
        $this->assertNotNull($backend->createCalendarObject($mine->id, 'a.ics', self::VEVENT));

        // Someone else's calendar: every operation is denied.
        $this->assertNull($backend->createCalendarObject($theirs->id, 'x.ics', self::VEVENT));
        $this->assertNull($backend->getCalendarObject($theirs->id, 'x.ics'));
        $this->assertSame([], $backend->getCalendarObjects($theirs->id));
        $this->assertNull($backend->getChangesForCalendar($theirs->id, null, 1));
        $this->assertDatabaseCount('calendar_objects', 1); // only the one in the own calendar
    }

    public function test_read_only_calendar_rejects_writes(): void
    {
        app(DavCredentialService::class)->generate(1);
        app(DavContext::class)->set(1);
        $readonly = Calendar::create([
            'user_id' => 1, 'uri' => 'feed', 'name' => 'Subscribed', 'components' => ['VEVENT'],
            'synctoken' => 1, 'read_only' => true,
        ]);
        $backend = app(CalDavBackend::class);

        $this->assertNull($backend->createCalendarObject($readonly->id, 'x.ics', self::VEVENT));
        $this->assertDatabaseCount('calendar_objects', 0);
    }

    public function test_well_known_caldav_redirects_to_dav(): void
    {
        $this->get('/.well-known/caldav')->assertRedirect('/dav/');
        $this->call('PROPFIND', '/.well-known/caldav')->assertRedirect('/dav/');
    }

    public function test_sabre_server_tree_builds_with_calendar_root(): void
    {
        $principals = app(PrincipalBackend::class);
        $calendars = app(CalDavBackend::class);

        $server = new Server([
            new PrincipalCollection($principals),
            new CalendarRoot($principals, $calendars),
        ]);
        $server->addPlugin(new \Sabre\DAV\Auth\Plugin(app(AuthBackend::class)));
        $server->addPlugin(new Plugin);
        $server->addPlugin(new \Sabre\DAV\Sync\Plugin);

        $this->assertInstanceOf(Server::class, $server);
    }
}
