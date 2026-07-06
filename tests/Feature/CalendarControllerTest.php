<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    private function calendarFor(User $user, array $attrs = []): Calendar
    {
        return Calendar::create(array_merge([
            'user_id' => $user->id,
            'uri' => 'default',
            'name' => 'Calendar',
            'color' => '#3366cc',
            'components' => ['VEVENT'],
            'synctoken' => 1,
        ], $attrs));
    }

    public function test_the_page_and_data_load(): void
    {
        $user = $this->signIn();
        $this->calendarFor($user);

        $this->get(route('calendar.index'))->assertOk();
        $this->getJson(route('calendar.data'))
            ->assertOk()
            ->assertJsonPath('calendars.0.name', 'Calendar')
            ->assertJsonPath('events', []);
    }

    public function test_move_updates_start_and_end_and_keeps_everything_else(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id,
            'summary' => 'Dentist',
            'start' => '2026-09-01T10:00',
            'end' => '2026-09-01T11:00',
            'location' => 'Downtown',
            'reminder_minutes' => 30,
        ])->assertCreated();
        $object = CalendarObject::firstOrFail();

        $this->patchJson(route('calendar.events.move', $object), [
            'start' => '2026-09-02 14:00:00',
            'end' => '2026-09-02 15:30:00',
        ])->assertOk();

        $object->refresh();
        // Time moved, the rest survived the rewrite.
        $show = $this->getJson(route('calendar.events.show', $object))->assertOk()->json();
        $this->assertStringContainsString('2026-09-02', $show['start']);
        $this->assertStringContainsString('14:00', $show['start']);
        $this->assertStringContainsString('15:30', $show['end']);
        $this->assertSame('Dentist', $show['summary']);
        $this->assertSame('Downtown', $show['location']);
        $this->assertSame(30, $show['reminder_minutes']);
    }

    public function test_move_rejects_recurring_events_and_foreign_users(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id,
            'summary' => 'Standup',
            'start' => '2026-09-01T09:00',
            'end' => '2026-09-01T09:15',
            'rrule' => 'FREQ=DAILY',
        ])->assertCreated();
        $recurring = CalendarObject::firstOrFail();

        // Recurring series need exception handling, not a blind move.
        $this->patchJson(route('calendar.events.move', $recurring), [
            'start' => '2026-09-01 10:00:00',
        ])->assertStatus(422);

        // Another user cannot move it either.
        $this->signIn();
        $this->patchJson(route('calendar.events.move', $recurring), [
            'start' => '2026-09-01 10:00:00',
        ])->assertForbidden();
    }

    public function test_it_creates_an_event(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id,
            'summary' => 'Dentist',
            'start' => '2026-09-01T10:00',
            'end' => '2026-09-01T11:00',
            'location' => 'Downtown',
        ])->assertCreated();

        $object = CalendarObject::firstOrFail();
        $this->assertSame('Dentist', $object->summary);
        $this->assertStringContainsString('LOCATION:Downtown', $object->ics);
        $this->assertSame(2, $calendar->fresh()->synctoken);
    }

    public function test_data_expands_a_recurring_event(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id,
            'summary' => 'Standup',
            'start' => '2026-09-07T09:00',
            'end' => '2026-09-07T09:15',
            'rrule' => 'FREQ=DAILY',
        ])->assertCreated();

        $res = $this->getJson(route('calendar.data', ['from' => '2026-09-07', 'to' => '2026-09-11']))->assertOk();
        // Mon–Thu inclusive of the window → at least 4 daily instances.
        $this->assertGreaterThanOrEqual(4, count($res->json('events')));
    }

    public function test_it_updates_and_deletes_an_event(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Old', 'start' => '2026-09-01T10:00', 'end' => '2026-09-01T11:00',
        ])->assertCreated();
        $object = CalendarObject::firstOrFail();

        $this->putJson(route('calendar.events.update', $object), [
            'calendar_id' => $calendar->id, 'summary' => 'New', 'start' => '2026-09-01T10:00', 'end' => '2026-09-01T11:00',
        ])->assertOk();
        $this->assertSame('New', $object->fresh()->summary);

        $this->deleteJson(route('calendar.events.destroy', $object))->assertOk();
        $this->assertSame(0, CalendarObject::count());
    }

    public function test_it_manages_calendars(): void
    {
        $user = $this->signIn();
        $this->calendarFor($user); // default

        $id = $this->postJson(route('calendar.calendars.store'), ['name' => 'Work', 'color' => '#e11d48'])
            ->assertCreated()->json('id');
        $this->assertDatabaseHas('calendars', ['id' => $id, 'name' => 'Work']);

        $this->putJson(route('calendar.calendars.update', $id), ['name' => 'Job'])->assertOk();
        $this->assertDatabaseHas('calendars', ['id' => $id, 'name' => 'Job']);

        $this->deleteJson(route('calendar.calendars.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('calendars', ['id' => $id]);
    }

    public function test_the_default_calendar_cannot_be_deleted(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);

        $this->deleteJson(route('calendar.calendars.destroy', $calendar))->assertStatus(422);
        $this->assertDatabaseHas('calendars', ['id' => $calendar->id]);
    }

    public function test_a_read_only_calendar_rejects_event_creation(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user, ['uri' => 'feed', 'read_only' => true]);

        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'X', 'start' => '2026-09-01T10:00', 'end' => '2026-09-01T11:00',
        ])->assertForbidden();
        $this->assertSame(0, CalendarObject::count());
    }

    public function test_it_denies_access_to_another_users_event(): void
    {
        $owner = User::factory()->create();
        $calendar = $this->calendarFor($owner);
        $object = CalendarObject::create([
            'calendar_id' => $calendar->id, 'uri' => 'e.ics', 'etag' => 'x', 'component' => 'VEVENT',
            'ics' => "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:e\r\nSUMMARY:Secret\r\nDTSTART:20260901T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            'summary' => 'Secret', 'starts_at' => '2026-09-01 10:00:00',
        ]);

        $this->signIn(); // a different user
        $this->getJson(route('calendar.events.show', $object))->assertForbidden();
        $this->deleteJson(route('calendar.events.destroy', $object))->assertForbidden();
        $this->assertDatabaseHas('calendar_objects', ['id' => $object->id]);
    }

    public function test_it_exports_events_as_ics(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Exported', 'start' => '2026-09-01T10:00', 'end' => '2026-09-01T11:00',
        ])->assertCreated();

        $res = $this->get(route('calendar.export'));
        $res->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $body = $res->streamedContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:Exported', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
    }

    public function test_it_imports_events_from_ics(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:imp-1\r\nSUMMARY:Imported\r\nDTSTART:20260901T100000Z\r\nDTEND:20260901T110000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $file = UploadedFile::fake()->createWithContent('cal.ics', $ics);

        $this->post(route('calendar.import'), ['file' => $file, 'calendar_id' => $calendar->id])
            ->assertOk()->assertJson(['created' => 1, 'updated' => 0]);

        $this->assertDatabaseHas('calendar_objects', ['calendar_id' => $calendar->id, 'summary' => 'Imported']);

        // Re-importing the same UID updates in place, not duplicates.
        $file2 = UploadedFile::fake()->createWithContent('cal.ics', $ics);
        $this->post(route('calendar.import'), ['file' => $file2, 'calendar_id' => $calendar->id])
            ->assertOk()->assertJson(['created' => 0, 'updated' => 1]);
        $this->assertSame(1, CalendarObject::count());
    }

    public function test_import_into_a_read_only_calendar_is_forbidden(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user, ['uri' => 'feed', 'read_only' => true]);
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\nSUMMARY:X\r\nDTSTART:20260901T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $file = UploadedFile::fake()->createWithContent('cal.ics', $ics);

        $this->post(route('calendar.import'), ['file' => $file, 'calendar_id' => $calendar->id])->assertForbidden();
        $this->assertSame(0, CalendarObject::count());
    }

    public function test_it_moves_an_event_to_another_calendar(): void
    {
        $user = $this->signIn();
        $a = $this->calendarFor($user);
        $b = $this->calendarFor($user, ['uri' => 'work', 'name' => 'Work']);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $a->id, 'summary' => 'Move me', 'start' => '2026-09-01T10:00', 'end' => '2026-09-01T11:00',
        ])->assertCreated();
        $object = CalendarObject::firstOrFail();

        $this->putJson(route('calendar.events.update', $object), [
            'calendar_id' => $b->id, 'summary' => 'Move me', 'start' => '2026-09-01T10:00', 'end' => '2026-09-01T11:00',
        ])->assertOk();

        $this->assertSame($b->id, CalendarObject::firstOrFail()->calendar_id);
        $this->assertSame(1, CalendarObject::count());
    }
}
