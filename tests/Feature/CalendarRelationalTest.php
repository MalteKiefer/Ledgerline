<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Calendar\CalendarEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    private function parseUid(CalendarEvent $event): ?string
    {
        $uid = app(CalendarEventService::class)->parse($event->ics)['uid'] ?? null;

        return is_string($uid) ? $uid : null;
    }
}
