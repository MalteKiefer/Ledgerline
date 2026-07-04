<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\User;
use App\Services\Calendar\ICalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function calendarFor(User $user): Calendar
    {
        return Calendar::create([
            'user_id' => $user->id, 'uri' => 'default', 'name' => 'Calendar',
            'color' => '#3366cc', 'components' => ['VEVENT'], 'synctoken' => 1,
        ]);
    }

    public function test_ical_stores_a_zoned_event_in_utc_and_round_trips_the_wall_time(): void
    {
        $ical = app(ICalService::class);
        $ics = $ical->buildEvent([
            'summary' => 'Call', 'start' => '2026-09-01 12:00', 'end' => '2026-09-01 13:00', 'timezone' => 'Europe/Berlin',
        ]);

        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20260901T120000', $ics);
        // Denormalised to UTC (Berlin is +2 in September).
        $this->assertSame('2026-09-01 10:00:00', $ical->denormalize($ics)['starts_at']);

        $editable = $ical->editable($ics);
        $this->assertSame('2026-09-01T12:00', $editable['start']);
        $this->assertSame('Europe/Berlin', $editable['timezone']);
    }

    public function test_data_renders_instances_in_the_requested_timezone(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Zoned',
            'start' => '2026-09-01T12:00', 'end' => '2026-09-01T13:00', 'timezone' => 'Europe/Berlin',
        ])->assertCreated();

        // Same absolute time, two display zones.
        $utc = $this->getJson(route('calendar.data', ['from' => '2026-08-01', 'to' => '2026-10-01', 'tz' => 'UTC']));
        $this->assertSame('2026-09-01 10:00:00', $utc->json('events.0.start'));

        $ny = $this->getJson(route('calendar.data', ['from' => '2026-08-01', 'to' => '2026-10-01', 'tz' => 'America/New_York']));
        $this->assertSame('2026-09-01 06:00:00', $ny->json('events.0.start'));
    }

    public function test_show_returns_the_events_own_wall_time_and_timezone(): void
    {
        $user = $this->signIn();
        $calendar = $this->calendarFor($user);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Z',
            'start' => '2026-09-01T12:00', 'end' => '2026-09-01T13:00', 'timezone' => 'Europe/Berlin',
        ])->assertCreated();
        $object = CalendarObject::firstOrFail();

        $this->getJson(route('calendar.events.show', $object))
            ->assertOk()
            ->assertJsonPath('start', '2026-09-01T12:00')
            ->assertJsonPath('timezone', 'Europe/Berlin');
    }

    public function test_settings_accepts_a_timezone_and_normalises_empty_to_null(): void
    {
        $this->signIn();

        $this->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'monday', 'calendar_default_event_minutes' => 60,
            'calendar_timezone' => 'Europe/Berlin',
        ])->assertRedirect(route('settings.calendar.edit'));
        $this->assertSame('Europe/Berlin', AppSettings::current()->calendar_timezone);

        $this->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'monday', 'calendar_default_event_minutes' => 60,
            'calendar_timezone' => '',
        ])->assertRedirect(route('settings.calendar.edit'));
        $this->assertNull(AppSettings::current()->calendar_timezone);
    }

    public function test_settings_rejects_an_invalid_timezone(): void
    {
        $this->signIn();
        $this->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'monday', 'calendar_default_event_minutes' => 60,
            'calendar_timezone' => 'Mars/Phobos',
        ])->assertSessionHasErrors('calendar_timezone');
    }

    public function test_the_detection_endpoint_pins_the_browser_timezone(): void
    {
        $this->signIn();

        $this->postJson(route('calendar.timezone'), ['timezone' => 'Asia/Tokyo'])->assertOk();
        $this->assertSame('Asia/Tokyo', AppSettings::current()->calendar_timezone);

        // Web routes render validation failures as a redirect with session errors
        // (JSON is reserved for api/*), so an invalid zone is rejected, not stored.
        $this->post(route('calendar.timezone'), ['timezone' => 'Nope/Nope'])->assertSessionHasErrors('timezone');
        $this->assertSame('Asia/Tokyo', AppSettings::current()->calendar_timezone);
    }
}
