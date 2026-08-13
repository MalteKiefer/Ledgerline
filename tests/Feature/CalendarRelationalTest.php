<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ImipMail;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Calendar\CalendarEventService;
use App\Services\Calendar\ImipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarRelationalTest extends TestCase
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

    public function test_ics_import_dedupes_and_carries_timezone_under_prevented_lazy_loading(): void
    {
        // The bulk-update path must not lazy-load the calendar relation (disabled
        // app-wide) or every re-imported event is silently skipped.
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
        try {
            $user = $this->signIn();
            $calendar = $this->calendar($user->id);
            $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n"
                ."BEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\nBEGIN:STANDARD\r\nDTSTART:19701025T030000\r\n"
                ."TZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nEND:STANDARD\r\n"
                ."BEGIN:DAYLIGHT\r\nDTSTART:19700329T020000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nEND:DAYLIGHT\r\nEND:VTIMEZONE\r\n"
                ."BEGIN:VEVENT\r\nUID:evt-1\r\nSUMMARY:Termin\r\nDTSTART;TZID=Europe/Berlin:20260429T090000\r\n"
                ."DTEND;TZID=Europe/Berlin:20260429T100000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

            $this->post(route('calendar.import'), ['calendar_id' => $calendar->id, 'file' => UploadedFile::fake()->createWithContent('c.ics', $ics)])
                ->assertOk()->assertJson(['created' => 1]);
            $this->post(route('calendar.import'), ['calendar_id' => $calendar->id, 'file' => UploadedFile::fake()->createWithContent('c.ics', $ics)])
                ->assertOk()->assertJson(['created' => 0, 'updated' => 1, 'skipped' => 0]);
            $this->assertDatabaseCount('calendar_events', 1);

            $event = CalendarEvent::firstOrFail();
            // The referenced VTIMEZONE is carried into the stored event, and the
            // TZID start is denormalised to the correct UTC instant (09:00 CEST → 07:00Z).
            $this->assertStringContainsString('BEGIN:VTIMEZONE', $event->ics);
            $this->assertStringContainsString('Europe/Berlin', $event->ics);
            $this->assertSame('2026-04-29 07:00:00', $event->dtstart?->utc()->format('Y-m-d H:i:s'));
        } finally {
            \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
        }
    }

    public function test_store_creates_an_event_and_bumps_the_sync_token(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id,
            'summary' => 'Standup',
            'location' => 'Room 1',
            'dtstart' => '2026-08-03T09:00:00Z',
            'dtend' => '2026-08-03T09:15:00Z',
        ])->assertStatus(201);

        $event = CalendarEvent::firstOrFail();
        $this->assertSame('Standup', $event->summary);
        $this->assertStringContainsString('SUMMARY:Standup', $event->ics);
        $this->assertSame(2, $calendar->fresh()->synctoken);
        $this->assertDatabaseHas('calendar_changes', ['operation' => 1]);
    }

    public function test_events_rejects_an_over_wide_window(): void
    {
        $user = $this->signIn();
        $this->calendar($user->id);

        // A multi-decade span would fan out recurrence expansion (self-DoS) → 422.
        $this->getJson(route('calendar.events', ['from' => '1970-01-01', 'to' => '3000-01-01']))
            ->assertStatus(422)->assertJson(['error' => 'range_too_large']);

        // A normal month-wide window is accepted.
        $this->getJson(route('calendar.events', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk();
    }

    public function test_data_lists_calendars_and_settings_scoped_to_the_user(): void
    {
        $user = $this->signIn();
        $this->calendar($user->id);
        // A foreign calendar must not appear.
        $this->calendar(User::factory()->create()->id);

        $this->getJson(route('calendar.data'))
            ->assertOk()
            ->assertJsonCount(1, 'calendars')
            ->assertJsonPath('calendars.0.name', 'Personal')
            ->assertJsonPath('settings.default_view', 'month')
            ->assertJsonPath('settings.week_start', 1);
    }

    public function test_range_query_expands_a_weekly_recurring_event(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id,
            'summary' => 'Weekly sync',
            'dtstart' => '2026-08-03T07:00:00Z', // a Monday
            'dtend' => '2026-08-03T07:30:00Z',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        ])->assertStatus(201);

        $events = $this->getJson(route('calendar.events', [
            'from' => '2026-08-01T00:00:00Z',
            'to' => '2026-09-01T00:00:00Z',
        ]))->assertOk()->json('events');

        // Mondays in August 2026: 3, 10, 17, 24, 31 → 5 occurrences.
        $this->assertCount(5, $events);
        $this->assertTrue($events[0]['recurring']);
        $this->assertSame('Weekly sync', $events[0]['summary']);
        $this->assertSame('#6750a4', $events[0]['color']);
        // Every occurrence points back at the same master for editing.
        $this->assertSame($events[0]['id'], $events[4]['id']);
    }

    public function test_range_query_returns_a_single_event_only_when_it_overlaps(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'One-off',
            'dtstart' => '2026-08-15T10:00:00Z', 'dtend' => '2026-08-15T11:00:00Z',
        ])->assertStatus(201);

        $this->getJson(route('calendar.events', ['from' => '2026-08-01T00:00:00Z', 'to' => '2026-09-01T00:00:00Z']))
            ->assertOk()->assertJsonCount(1, 'events');
        // A window before the event → nothing.
        $this->getJson(route('calendar.events', ['from' => '2026-07-01T00:00:00Z', 'to' => '2026-08-01T00:00:00Z']))
            ->assertOk()->assertJsonCount(0, 'events');
    }

    public function test_show_returns_parsed_editor_data_and_update_keeps_uid_bumps_sequence(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'A', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertStatus(201);
        $event = CalendarEvent::firstOrFail();

        $show = $this->getJson(route('calendar.events.show', $event))->assertOk()->json();
        $this->assertSame('A', $show['summary']);
        $this->assertNotEmpty($show['uid']);
        $this->assertSame($event->etag, $show['etag']);
        $uid = $show['uid'];

        $this->putJson(route('calendar.events.update', $event), [
            'summary' => 'A2', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertOk()->assertJsonPath('ok', true);

        $event->refresh();
        $this->assertSame('A2', $event->summary);
        $this->assertSame($uid, $this->parseUid($event));
        $this->assertSame(1, $event->sequence);
    }

    public function test_update_preserves_unmodelled_ics_properties(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Orig', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertStatus(201);
        $event = CalendarEvent::firstOrFail();

        // Inject properties the editor does not model (as a CalDAV client would).
        // (VALARM is editor-owned for events — see the reminder round-trip test.)
        $extra = "CATEGORIES:Work\r\nURL:https://example.com/x\r\nEND:VEVENT";
        $event->ics = str_replace('END:VEVENT', $extra, $event->ics);
        $event->saveQuietly();

        $this->putJson(route('calendar.events.update', $event), [
            'summary' => 'New', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertOk();

        $event->refresh();
        $this->assertStringContainsString('SUMMARY:New', $event->ics);       // modelled field updated
        $this->assertStringContainsString('CATEGORIES:Work', $event->ics);   // unmodelled preserved
        $this->assertStringContainsString('https://example.com/x', $event->ics);
        $this->assertStringNotContainsString('SUMMARY:Orig', $event->ics);
    }

    public function test_event_round_trips_a_reminder_alarm(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'A', 'dtstart' => '2026-08-03T09:00:00Z', 'alarm_minutes_before' => 15,
        ])->assertStatus(201);
        $event = CalendarEvent::firstOrFail();
        $this->assertStringContainsString('BEGIN:VALARM', $event->ics);
        $this->assertStringContainsString('TRIGGER:-PT15M', $event->ics);

        $show = $this->getJson(route('calendar.events.show', $event))->assertOk()->json();
        $this->assertSame(15, $show['alarm_minutes_before']);

        // Clearing the reminder drops the VALARM.
        $this->putJson(route('calendar.events.update', $event), [
            'summary' => 'A', 'dtstart' => '2026-08-03T09:00:00Z', 'alarm_minutes_before' => null,
        ])->assertOk();
        $this->assertStringNotContainsString('BEGIN:VALARM', $event->refresh()->ics);
    }

    public function test_exclude_and_override_a_single_occurrence(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Weekly sync',
            'dtstart' => '2026-08-03T07:00:00Z', 'dtend' => '2026-08-03T07:30:00Z', 'rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        ])->assertStatus(201);
        $event = CalendarEvent::firstOrFail();
        $window = ['from' => '2026-08-01T00:00:00Z', 'to' => '2026-09-01T00:00:00Z'];

        // Exclude the Aug 10 occurrence → 5 becomes 4, and the series survives.
        $this->postJson(route('calendar.events.exclude', $event), ['start' => '2026-08-10T07:00:00Z'])->assertOk();
        $after = $this->getJson(route('calendar.events', $window))->assertOk()->json('events');
        $this->assertCount(4, $after);
        $this->assertEmpty(array_filter($after, fn ($e) => str_starts_with((string) $e['start'], '2026-08-10')));

        // Override the Aug 17 occurrence's summary → that instance only shows the new title.
        $this->putJson(route('calendar.events.occurrence', $event), [
            'recurrence_id' => '2026-08-17T07:00:00Z', 'summary' => 'Moved sync', 'dtstart' => '2026-08-17T09:00:00Z',
        ])->assertOk();
        $final = $this->getJson(route('calendar.events', $window))->assertOk()->json('events');
        $this->assertCount(4, $final); // still 4 (exclusion holds, override replaces not adds)
        $moved = array_values(array_filter($final, fn ($e) => $e['summary'] === 'Moved sync'));
        $this->assertCount(1, $moved);
        $this->assertNotEmpty(array_filter($final, fn ($e) => $e['summary'] === 'Weekly sync')); // the rest untouched
    }

    public function test_update_rejects_a_stale_etag_with_409(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'A', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertStatus(201);
        $event = CalendarEvent::firstOrFail();

        $this->putJson(route('calendar.events.update', $event), [
            'summary' => 'B', 'dtstart' => '2026-08-03T09:00:00Z', 'etag' => 'stale-etag',
        ])->assertStatus(409)->assertJsonPath('error', 'etag_conflict')->assertJsonPath('etag', $event->etag);

        // The current etag is accepted.
        $this->putJson(route('calendar.events.update', $event), [
            'summary' => 'B', 'dtstart' => '2026-08-03T09:00:00Z', 'etag' => $event->etag,
        ])->assertOk();
    }

    public function test_destroy_logs_a_tombstone(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'A', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertStatus(201);
        $event = CalendarEvent::firstOrFail();

        $this->deleteJson(route('calendar.events.destroy', $event))->assertOk();
        $this->assertDatabaseCount('calendar_events', 0);
        $this->assertDatabaseHas('calendar_changes', ['operation' => 3]);
    }

    public function test_events_are_owner_scoped(): void
    {
        $owner = $this->signIn();
        $calendar = $this->calendar($owner->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Mine', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertStatus(201);
        $event = CalendarEvent::firstOrFail();

        // Another user cannot see, view, update or delete it.
        $this->signIn();
        $this->getJson(route('calendar.events', ['from' => '2026-08-01T00:00:00Z', 'to' => '2026-09-01T00:00:00Z']))
            ->assertOk()->assertJsonCount(0, 'events');
        $this->getJson(route('calendar.events.show', $event))->assertForbidden();
        $this->putJson(route('calendar.events.update', $event), ['summary' => 'x', 'dtstart' => '2026-08-03T09:00:00Z'])->assertForbidden();
        $this->deleteJson(route('calendar.events.destroy', $event))->assertForbidden();
    }

    public function test_store_into_a_foreign_calendar_is_rejected(): void
    {
        $this->signIn();
        $foreign = $this->calendar(User::factory()->create()->id);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $foreign->id, 'summary' => 'x', 'dtstart' => '2026-08-03T09:00:00Z',
        ])->assertNotFound();
    }

    public function test_calendar_crud_and_last_calendar_guard(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);

        // Cannot delete the only calendar.
        $this->deleteJson(route('calendars.destroy', $calendar))->assertStatus(422);

        // Create a second, rename it, then delete it.
        $id = $this->postJson(route('calendars.store'), ['name' => 'Work', 'color' => '#ff0000'])
            ->assertStatus(201)->json('id');
        $this->putJson(route('calendars.update', $id), ['name' => 'Team', 'color' => '#00ff00'])->assertOk();
        $this->assertSame('Team', Calendar::findOrFail($id)->name);
        $this->deleteJson(route('calendars.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('calendars', ['id' => $id]);
    }

    public function test_settings_persist(): void
    {
        $user = $this->signIn();
        $this->calendar($user->id);

        $this->postJson(route('calendar.settings'), ['default_view' => 'week', 'week_start' => 0])
            ->assertOk()->assertJsonPath('ok', true);

        $this->getJson(route('calendar.data'))->assertOk()
            ->assertJsonPath('settings.default_view', 'week')
            ->assertJsonPath('settings.week_start', 0);
    }

    public function test_ics_import_export_round_trip(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n"
            ."BEGIN:VEVENT\r\nUID:e1\r\nSUMMARY:One\r\nDTSTART:20260803T090000Z\r\nDTEND:20260803T093000Z\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:e2\r\nSUMMARY:Two\r\nDTSTART:20260804T090000Z\r\nDTEND:20260804T093000Z\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n";

        $this->post(route('calendar.import'), [
            'calendar_id' => $calendar->id,
            'file' => UploadedFile::fake()->createWithContent('c.ics', $ics),
        ])->assertOk()->assertJson(['created' => 2, 'updated' => 0]);
        $this->assertDatabaseCount('calendar_events', 2);

        // Export contains both events.
        $out = $this->get(route('calendar.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:One', $out);
        $this->assertStringContainsString('SUMMARY:Two', $out);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $out);

        // Re-import → dedupe by UID (update, not create).
        $this->post(route('calendar.import'), [
            'calendar_id' => $calendar->id,
            'file' => UploadedFile::fake()->createWithContent('c.ics', $ics),
        ])->assertOk()->assertJson(['created' => 0, 'updated' => 2]);
        $this->assertDatabaseCount('calendar_events', 2);
    }

    public function test_event_round_trips_geo_coordinates_and_writes_geo_into_ics(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id,
            'summary' => 'Meetup',
            'location' => 'Alexanderplatz, Berlin',
            'geo_lat' => 52.5219184,
            'geo_lon' => 13.4132147,
            'dtstart' => '2026-08-15T18:00:00Z',
            'dtend' => '2026-08-15T20:00:00Z',
        ])->assertStatus(201);

        $event = CalendarEvent::firstOrFail();
        // Denormalised columns + the authoritative ICS both carry the coordinate.
        $this->assertSame(52.5219184, (float) $event->geo_lat);
        $this->assertSame(13.4132147, (float) $event->geo_lon);
        $this->assertStringContainsString('GEO:52.5219184;13.4132147', $event->ics);
        // LOCATION stays the human address, GEO is the coordinate.
        $this->assertStringContainsString('LOCATION:Alexanderplatz', $event->ics);

        // show() returns the coordinate for the editor + map marker.
        $this->getJson(route('calendar.events.show', $event))->assertOk()
            ->assertJsonPath('geo_lat', 52.5219184)
            ->assertJsonPath('geo_lon', 13.4132147);

        // The range/occurrence shape carries it too (marker on the calendar map).
        $this->getJson(route('calendar.events', ['from' => '2026-08-01T00:00:00Z', 'to' => '2026-09-01T00:00:00Z']))
            ->assertOk()
            ->assertJsonPath('events.0.geo_lat', 52.5219184)
            ->assertJsonPath('events.0.geo_lon', 13.4132147);
    }

    public function test_event_without_geo_reports_null_and_omits_geo_property(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'No place', 'dtstart' => '2026-08-15T10:00:00Z',
        ])->assertStatus(201);

        $event = CalendarEvent::firstOrFail();
        $this->assertNull($event->geo_lat);
        $this->assertNull($event->geo_lon);
        $this->assertStringNotContainsString('GEO:', $event->ics);
        $this->getJson(route('calendar.events.show', $event))->assertOk()
            ->assertJsonPath('geo_lat', null)
            ->assertJsonPath('geo_lon', null);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        // Exercised on the /api/v1 twin, which renders a clean JSON 422 (the web
        // route flashes validation to the session + redirects, app-wide).
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('iphone', ['device'])->plainTextToken];

        $this->postJson(route('api.calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'x', 'dtstart' => '2026-08-15T10:00:00Z',
            'geo_lat' => 120, 'geo_lon' => 13.4,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('geo_lat');

        $this->postJson(route('api.calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'x', 'dtstart' => '2026-08-15T10:00:00Z',
            'geo_lat' => 52.5, 'geo_lon' => 200,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('geo_lon');

        $this->assertDatabaseCount('calendar_events', 0);
    }

    private function parseUid(CalendarEvent $event): ?string
    {
        $uid = app(CalendarEventService::class)->parse($event->ics)['uid'] ?? null;

        return is_string($uid) ? $uid : null;
    }

    public function test_imip_invite_rsvp_and_inbound(): void
    {
        Mail::fake();
        $owner = $this->signIn();
        $calendar = $this->calendar($owner->id);

        // Create an event with an attendee → a REQUEST is e-mailed.
        $id = (string) $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Sync',
            'dtstart' => '2026-08-10T09:00:00Z', 'dtend' => '2026-08-10T10:00:00Z',
            'attendees' => [['email' => 'guest@example.test', 'name' => 'Guest']],
        ])->assertCreated()->json('id');
        Mail::assertSent(ImipMail::class);

        // The ICS carries ORGANIZER + ATTENDEE (NEEDS-ACTION).
        $detail = $this->getJson(route('calendar.events.show', ['event' => $id]))->assertOk()->json();
        $this->assertSame($owner->email, $detail['organizer']);
        $this->assertSame('guest@example.test', $detail['attendees'][0]['email']);
        $this->assertSame('NEEDS-ACTION', $detail['attendees'][0]['partstat']);

        // Inbound REPLY from the guest → the organizer's attendee flips to ACCEPTED.
        $uid = CalendarEvent::findOrFail($id)->uid;
        $reply = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:REPLY\r\nBEGIN:VEVENT\r\nUID:{$uid}\r\nSEQUENCE:0\r\nORGANIZER:mailto:{$owner->email}\r\nATTENDEE;PARTSTAT=ACCEPTED:mailto:guest@example.test\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $this->postJson(route('calendar.imip'), ['ics' => $reply])->assertOk()->assertJsonPath('action', 'updated');
        $after = $this->getJson(route('calendar.events.show', ['event' => $id]))->json();
        $this->assertSame('ACCEPTED', $after['attendees'][0]['partstat']);

        // Inbound REQUEST (new invitation) → an event is created.
        $newUid = 'imip-new-'.uniqid();
        $req = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:{$newUid}\r\nDTSTART:20260901T090000Z\r\nDTEND:20260901T100000Z\r\nSUMMARY:Invited\r\nORGANIZER:mailto:boss@example.test\r\nATTENDEE:mailto:{$owner->email}\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $this->postJson(route('calendar.imip'), ['ics' => $req])->assertOk()->assertJsonPath('action', 'created');
        $this->assertSame(1, CalendarEvent::where('uid', $newUid)->count());
    }

    public function test_imip_inbound_rejects_a_spoofed_sender(): void
    {
        $owner = $this->signIn();
        $calendar = $this->calendar($owner->id);
        $id = (string) $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Sync',
            'dtstart' => '2026-08-10T09:00:00Z', 'dtend' => '2026-08-10T10:00:00Z',
            'attendees' => [['email' => 'guest@example.test', 'name' => 'Guest']],
        ])->assertCreated()->json('id');
        $uid = CalendarEvent::findOrFail($id)->uid;
        $imip = app(ImipService::class);
        $svc = app(CalendarEventService::class);
        $reply = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:REPLY\r\nBEGIN:VEVENT\r\nUID:{$uid}\r\nORGANIZER:mailto:{$owner->email}\r\nATTENDEE;PARTSTAT=ACCEPTED:mailto:guest@example.test\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        // Spoofed sender (not the attendee) → rejected, PARTSTAT unchanged.
        $this->assertSame('sender_mismatch', $imip->ingest((int) $owner->id, $reply, 'attacker@evil.test')['action']);
        $this->assertSame('NEEDS-ACTION', $svc->parse(CalendarEvent::findOrFail($id)->ics)['attendees'][0]['partstat']);

        // Genuine sender (the attendee) → accepted.
        $this->assertSame('updated', $imip->ingest((int) $owner->id, $reply, 'guest@example.test')['action']);
        $this->assertSame('ACCEPTED', $svc->parse(CalendarEvent::findOrFail($id)->ics)['attendees'][0]['partstat']);
    }

    public function test_free_busy_and_slot_finder(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        // A busy block 10:00–11:00 UTC on 2026-08-10.
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Busy',
            'dtstart' => '2026-08-10T10:00:00Z', 'dtend' => '2026-08-10T11:00:00Z',
        ])->assertCreated();

        $busy = $this->getJson(route('calendar.free-busy', ['from' => '2026-08-10T00:00:00Z', 'to' => '2026-08-11T00:00:00Z']))
            ->assertOk()->json('busy');
        $this->assertCount(1, $busy);
        $this->assertStringStartsWith('2026-08-10T10:00', $busy[0]['start']);

        // 30-min slots on that day, working window 09:00–12:00 → one gap before
        // (09:00–10:00) and one after (11:00–12:00) the busy block.
        $slots = $this->postJson(route('calendar.slots'), [
            'from' => '2026-08-10T00:00:00Z', 'to' => '2026-08-11T00:00:00Z',
            'duration_min' => 30, 'day_start' => 9, 'day_end' => 12,
        ])->assertOk()->json('slots');
        $this->assertGreaterThanOrEqual(2, count($slots));
        $this->assertSame('2026-08-10T09:00:00Z', $slots[0]['start']);
    }

    public function test_shared_calendar_editor_can_write_viewer_cannot(): void
    {
        $owner = $this->signIn();
        $calendar = $this->calendar($owner->id);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Owner event',
            'dtstart' => '2026-08-10T09:00:00Z', 'dtend' => '2026-08-10T10:00:00Z',
        ])->assertCreated();

        $editor = User::factory()->create(['email' => 'editor@example.test']);
        $viewer = User::factory()->create(['email' => 'viewer@example.test']);
        $stranger = User::factory()->create();

        // Share as editor + viewer.
        $this->actingAs($owner)->postJson(route('calendar.shares.store'), ['calendar_id' => $calendar->id, 'email' => 'editor@example.test', 'role' => 'editor'])->assertCreated();
        $this->actingAs($owner)->postJson(route('calendar.shares.store'), ['calendar_id' => $calendar->id, 'email' => 'viewer@example.test', 'role' => 'viewer'])->assertCreated();
        $this->actingAs($owner)->postJson(route('calendar.shares.store'), ['calendar_id' => $calendar->id, 'email' => $owner->email])->assertStatus(422); // self

        // Recipients see the calendar + its events.
        $this->actingAs($viewer)->getJson(route('calendar.data'))->assertOk()
            ->assertJsonFragment(['id' => $calendar->id, 'role' => 'viewer', 'writable' => false]);
        $this->actingAs($editor)->getJson(route('calendar.events', ['from' => '2026-08-01T00:00:00Z', 'to' => '2026-09-01T00:00:00Z']))
            ->assertOk()->assertJsonCount(1, 'events');

        // Editor can create an event in the shared calendar; viewer cannot.
        $this->actingAs($editor)->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Editor event',
            'dtstart' => '2026-08-11T09:00:00Z', 'dtend' => '2026-08-11T10:00:00Z',
        ])->assertCreated();
        $this->actingAs($viewer)->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'nope',
            'dtstart' => '2026-08-12T09:00:00Z', 'dtend' => '2026-08-12T10:00:00Z',
        ])->assertForbidden();

        // Stranger sees nothing + cannot write.
        $this->actingAs($stranger)->getJson(route('calendar.data'))->assertOk()->assertJsonMissing(['id' => $calendar->id]);
        $this->actingAs($stranger)->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'nope', 'dtstart' => '2026-08-12T09:00:00Z', 'dtend' => '2026-08-12T10:00:00Z',
        ])->assertNotFound(); // non-accessible calendar is hidden (404)
    }
}
