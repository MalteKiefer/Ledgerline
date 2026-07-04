<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\CalDavBackend;
use App\Dav\DavContext;
use App\Models\AddressBook;
use App\Models\AppSettings;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\Contact;
use App\Models\User;
use App\Services\Calendar\CalendarObjectPersister;
use App\Services\Calendar\ContactDerivedCalendars;
use App\Services\Calendar\HolidayCalendarBuilder;
use App\Services\Calendar\ICalService;
use App\Services\Contacts\ContactImporter;
use App\Services\Contacts\ContactWriter;
use App\Services\Contacts\DavCredentialService;
use App\Services\Notifications\ChannelNotifier;
use App\Support\OutboundUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Sabre\DAV\Exception\Forbidden;
use Tests\TestCase;

class CalendarAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ssrf_guard_blocks_ipv4_mapped_ipv6_metadata(): void
    {
        // Cloud metadata reached via an IPv4-mapped/compatible IPv6 literal.
        $this->assertFalse(OutboundUrl::safe('http://[::ffff:169.254.169.254]/latest/meta-data/'));
        $this->assertFalse(OutboundUrl::safe('http://[::ffff:a9fe:a9fe]/'));
        $this->assertFalse(OutboundUrl::safe('http://[::169.254.169.254]/'));
        // A legitimate public host still passes.
        $this->assertTrue(OutboundUrl::safe('https://[::ffff:8.8.8.8]/x.ics'));
    }

    public function test_rrule_crlf_injection_is_rejected(): void
    {
        $ical = app(ICalService::class);
        // A CRLF-injection payload must never smuggle extra properties/components.
        $ics = $ical->buildEvent([
            'summary' => 'X', 'start' => '2026-09-01 10:00', 'end' => '2026-09-01 11:00',
            'rrule' => "FREQ=DAILY\r\nSUMMARY:injected\r\nX-EVIL:1",
        ]);
        $this->assertStringNotContainsString('X-EVIL', $ics);
        $this->assertSame(1, substr_count($ics, 'SUMMARY:'));
        $this->assertStringNotContainsString('RRULE', $ics); // whole tainted value dropped

        // A clean recurrence rule survives.
        $ok = $ical->buildEvent([
            'summary' => 'X', 'start' => '2026-09-01 10:00', 'end' => '2026-09-01 11:00',
            'rrule' => 'FREQ=WEEKLY;COUNT=5',
        ]);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;COUNT=5', $ok);
    }

    public function test_subscription_url_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $secret = 'https://example.com/feed.ics?token=SUPERSECRET';
        $cal = Calendar::create([
            'user_id' => $user->id, 'uri' => 'feed', 'name' => 'Sub', 'components' => ['VEVENT'],
            'synctoken' => 1, 'read_only' => true, 'subscription_url' => $secret,
        ]);

        $raw = DB::table('calendars')->where('id', $cal->id)->value('subscription_url');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString('SUPERSECRET', (string) $raw);
        $this->assertSame($secret, $cal->fresh()->subscription_url);
    }

    public function test_caldav_rows_carry_calendarid_and_calendar_query_runs(): void
    {
        app(DavCredentialService::class)->generate(1);
        app(DavContext::class)->set(1);
        $cal = Calendar::where('user_id', 1)->where('uri', 'default')->firstOrFail();
        $backend = app(CalDavBackend::class);
        $backend->createCalendarObject($cal->id, 'e.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:e\r\nSUMMARY:Q\r\nDTSTART:20260901T100000Z\r\nDTEND:20260901T110000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $rows = $backend->getCalendarObjects($cal->id);
        $this->assertArrayHasKey('calendarid', $rows[0]);

        // The inherited AbstractBackend calendar-query fallback must not fatal.
        $filters = [
            'name' => 'VCALENDAR',
            'comp-filters' => [[
                'name' => 'VEVENT', 'comp-filters' => [], 'prop-filters' => [],
                'is-not-defined' => false, 'time-range' => null,
            ]],
            'prop-filters' => [], 'is-not-defined' => false, 'time-range' => null,
        ];
        $this->assertContains('e.ics', $backend->calendarQuery($cal->id, $filters));
    }

    public function test_oversized_caldav_object_is_rejected(): void
    {
        app(DavCredentialService::class)->generate(1);
        app(DavContext::class)->set(1);
        $cal = Calendar::where('user_id', 1)->where('uri', 'default')->firstOrFail();
        $backend = app(CalDavBackend::class);

        $huge = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:big\r\nSUMMARY:".str_repeat('A', CalendarObjectPersister::MAX_ICS_BYTES + 10)."\r\nDTSTART:20260901T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $this->expectException(Forbidden::class);
        $backend->createCalendarObject($cal->id, 'big.ics', $huge);
    }

    public function test_derived_and_reserved_calendars_are_undeletable(): void
    {
        $user = $this->signIn();
        foreach (['default', 'tasks', 'birthdays', 'anniversaries', 'holidays'] as $uri) {
            $cal = Calendar::create([
                'user_id' => $user->id, 'uri' => $uri, 'name' => ucfirst($uri),
                'components' => ['VEVENT'], 'synctoken' => 1,
            ]);
            $this->deleteJson(route('calendar.calendars.destroy', $cal))->assertStatus(422);
            $this->assertDatabaseHas('calendars', ['id' => $cal->id]);
        }
    }

    public function test_sync_collection_does_not_re_report_changes(): void
    {
        app(DavCredentialService::class)->generate(1);
        app(DavContext::class)->set(1);
        $cal = Calendar::where('user_id', 1)->where('uri', 'default')->firstOrFail();
        $backend = app(CalDavBackend::class);
        $backend->createCalendarObject($cal->id, 'e.ics', "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:e\r\nSUMMARY:S\r\nDTSTART:20260901T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        // First delta sync from the initial token sees the change...
        $first = $backend->getChangesForCalendar($cal->id, '1', 1);
        $this->assertContains('e.ics', $first['added']);

        // ...and syncing again from the token it returned sees nothing new.
        $second = $backend->getChangesForCalendar($cal->id, $first['syncToken'], 1);
        $this->assertSame([], $second['added']);
        $this->assertSame([], $second['modified']);
        $this->assertSame([], $second['deleted']);
    }

    public function test_all_day_event_uses_exclusive_dtend_and_round_trips_inclusive(): void
    {
        $ical = app(ICalService::class);
        $ics = $ical->buildEvent(['summary' => 'Trip', 'start' => '2026-07-01', 'end' => '2026-07-03', 'all_day' => true]);
        // Exclusive DTEND on the wire: last day 07-03 → DTEND 07-04.
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260704', $ics);
        // Editor sees the inclusive last day again.
        $this->assertSame('2026-07-03', $ical->editable($ics)['end']);

        // Single-day all-day event is a valid 1-day interval, not zero-duration.
        $one = $ical->buildEvent(['summary' => 'Day', 'start' => '2026-07-01', 'all_day' => true]);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260701', $one);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260702', $one);
    }

    public function test_alarms_do_not_fire_for_read_only_calendars(): void
    {
        $user = User::factory()->create();
        $sub = Calendar::create([
            'user_id' => $user->id, 'uri' => 'feed', 'name' => 'Sub', 'components' => ['VEVENT'],
            'synctoken' => 1, 'read_only' => true, 'subscription_url' => 'https://example.com/f.ics',
        ]);
        $start = now()->addMinutes(10);
        $ics = app(ICalService::class)->buildEvent([
            'summary' => 'Sub event', 'start' => $start->format('Y-m-d\TH:i'),
            'end' => (clone $start)->addMinutes(30)->format('Y-m-d\TH:i'), 'reminder_minutes' => 15,
        ]);
        app(CalendarObjectPersister::class)->persistNew($sub, 'x.ics', $ics);

        $notifier = \Mockery::mock(ChannelNotifier::class);
        $notifier->shouldNotReceive('send');
        $this->app->instance(ChannelNotifier::class, $notifier);

        $this->artisan('calendar:remind')->assertSuccessful();
        $this->assertSame(0, DB::table('calendar_alarm_log')->count());
    }

    public function test_web_cannot_create_events_in_the_tasks_calendar(): void
    {
        $user = $this->signIn();
        $tasks = Calendar::create([
            'user_id' => $user->id, 'uri' => 'tasks', 'name' => 'Tasks',
            'components' => ['VTODO'], 'synctoken' => 1,
        ]);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $tasks->id, 'summary' => 'X', 'start' => '2026-09-01T10:00', 'end' => '2026-09-01T11:00',
        ])->assertForbidden();
    }

    public function test_derived_calendar_rebuild_is_idempotent(): void
    {
        $user = User::factory()->create();
        AddressBook::create(['user_id' => $user->id, 'uri' => 'default', 'name' => 'C', 'synctoken' => 1]);
        app(ContactWriter::class)->create(
            AddressBook::where('user_id', $user->id)->first(),
            ['fn' => 'Al', 'bday' => '1990-05-04']
        );
        AppSettings::current()->update(['calendar_birthdays_enabled' => true]);

        app(ContactDerivedCalendars::class)->sync();
        $cal = Calendar::where('user_id', $user->id)->where('uri', 'birthdays')->firstOrFail();
        $tokenAfterFirst = $cal->fresh()->synctoken;

        // A second rebuild with identical data must be a no-op: no new change-log
        // rows, sync token unchanged (deterministic uris + diff).
        app(ContactDerivedCalendars::class)->sync();
        $this->assertSame($tokenAfterFirst, $cal->fresh()->synctoken);
        $this->assertSame(1, CalendarObject::where('calendar_id', $cal->id)->count());
    }

    public function test_import_dedup_is_exact_uid_not_substring(): void
    {
        $user = $this->signIn();
        $book = AddressBook::create(['user_id' => $user->id, 'uri' => 'default', 'name' => 'C', 'synctoken' => 1]);
        $importer = app(ContactImporter::class);

        $importer->import($book, "BEGIN:VCARD\r\nVERSION:4.0\r\nUID:foo\r\nFN:Foo\r\nEND:VCARD\r\n");
        // 'foobar' must NOT match 'foo' (the old LIKE '%UID:foo%' did).
        $importer->import($book, "BEGIN:VCARD\r\nVERSION:4.0\r\nUID:foobar\r\nFN:Bar\r\nEND:VCARD\r\n");

        $this->assertSame(2, Contact::where('address_book_id', $book->id)->count());
    }

    public function test_holidays_build_for_a_user_without_an_address_book(): void
    {
        $user = User::factory()->create(); // no address book / contacts
        AppSettings::current()->update(['calendar_holiday_countries' => ['DE']]);

        app(HolidayCalendarBuilder::class)->sync(2026);

        $this->assertDatabaseHas('calendars', ['user_id' => $user->id, 'uri' => 'holidays']);
    }

    public function test_calendar_color_must_be_a_hex_value(): void
    {
        $this->signIn();
        // Web routes render validation failures as a redirect with session errors.
        $this->post(route('calendar.calendars.store'), ['name' => 'X', 'color' => 'red; DROP'])->assertSessionHasErrors('color');
        $this->postJson(route('calendar.calendars.store'), ['name' => 'X', 'color' => '#a1b2c3'])->assertCreated();
    }

    public function test_read_only_calendar_cannot_be_renamed_via_web(): void
    {
        $user = $this->signIn();
        $sub = Calendar::create([
            'user_id' => $user->id, 'uri' => 'holidays', 'name' => 'Holidays',
            'components' => ['VEVENT'], 'synctoken' => 1, 'read_only' => true,
        ]);
        $this->putJson(route('calendar.calendars.update', $sub), ['name' => 'Hacked'])->assertForbidden();
    }

    public function test_unparseable_event_date_is_a_422_not_a_500(): void
    {
        $user = $this->signIn();
        $cal = Calendar::create([
            'user_id' => $user->id, 'uri' => 'default', 'name' => 'C', 'components' => ['VEVENT'], 'synctoken' => 1,
        ]);
        $this->post(route('calendar.events.store'), [
            'calendar_id' => $cal->id, 'summary' => 'X', 'start' => 'not-a-date',
        ])->assertSessionHasErrors('start');
    }

    public function test_invalid_rrule_frequency_is_dropped(): void
    {
        $ics = app(ICalService::class)->buildEvent([
            'summary' => 'X', 'start' => '2026-09-01 10:00', 'end' => '2026-09-01 11:00', 'rrule' => 'FREQ=FORTNIGHTLY',
        ]);
        $this->assertStringNotContainsString('RRULE', $ics);
    }

    public function test_unreachable_feed_error_does_not_leak_the_url(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to sekret.example.com'));
        $user = $this->signIn();
        $cal = Calendar::create([
            'user_id' => $user->id, 'uri' => 'default', 'name' => 'C', 'components' => ['VEVENT'], 'synctoken' => 1,
        ]);

        $res = $this->postJson(route('calendar.import-url'), [
            'url' => 'https://sekret.example.com/feed.ics?token=SECRET', 'calendar_id' => $cal->id,
        ])->assertStatus(422);
        $this->assertStringNotContainsString('SECRET', $res->getContent());
        $this->assertStringNotContainsString('sekret.example.com', $res->getContent());
    }
}
