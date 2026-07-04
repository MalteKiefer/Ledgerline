<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AddressBook;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Calendar\HolidayCalendarBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function userWithBook(): User
    {
        $user = User::factory()->create();
        AddressBook::create(['user_id' => $user->id, 'uri' => 'default', 'name' => 'Contacts', 'synctoken' => 1]);

        return $user;
    }

    public function test_selecting_a_country_builds_a_holidays_calendar(): void
    {
        $user = $this->userWithBook();
        UserSetting::for($user->id)->update(['calendar_holiday_countries' => ['DE']]);

        app(HolidayCalendarBuilder::class)->sync(2026);

        $calendar = Calendar::where('user_id', $user->id)->where('uri', 'holidays')->firstOrFail();
        $this->assertTrue($calendar->isReadOnly());
        // New Year's Day is in every generated year of the rolling window.
        $this->assertDatabaseHas('calendar_objects', ['calendar_id' => $calendar->id, 'starts_at' => '2026-01-01 00:00:00']);
        $this->assertGreaterThan(10, CalendarObject::where('calendar_id', $calendar->id)->count());
    }

    public function test_multiple_countries_tag_the_country(): void
    {
        $user = $this->userWithBook();
        UserSetting::for($user->id)->update(['calendar_holiday_countries' => ['DE', 'US']]);

        app(HolidayCalendarBuilder::class)->sync(2026);

        $calendar = Calendar::where('user_id', $user->id)->where('uri', 'holidays')->firstOrFail();
        $summaries = CalendarObject::where('calendar_id', $calendar->id)->pluck('summary');
        $this->assertTrue($summaries->contains(fn ($s) => str_contains((string) $s, '(DE)')));
        $this->assertTrue($summaries->contains(fn ($s) => str_contains((string) $s, '(US)')));
    }

    public function test_clearing_countries_removes_the_calendar(): void
    {
        $user = $this->userWithBook();
        UserSetting::for($user->id)->update(['calendar_holiday_countries' => ['DE']]);
        app(HolidayCalendarBuilder::class)->sync(2026);
        $this->assertDatabaseHas('calendars', ['user_id' => $user->id, 'uri' => 'holidays']);

        UserSetting::for($user->id)->update(['calendar_holiday_countries' => []]);
        app(HolidayCalendarBuilder::class)->sync(2026);
        $this->assertDatabaseMissing('calendars', ['user_id' => $user->id, 'uri' => 'holidays']);
    }

    public function test_settings_save_rejects_an_unsupported_country(): void
    {
        $this->signIn();

        $this->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'monday',
            'calendar_default_event_minutes' => 60,
            'calendar_holiday_countries' => ['XX'],
        ])->assertSessionHasErrors('calendar_holiday_countries.0');
    }

    public function test_settings_save_accepts_and_persists_countries(): void
    {
        $user = $this->signIn();

        $this->put(route('settings.calendar.update'), [
            'calendar_week_start' => 'monday',
            'calendar_default_event_minutes' => 60,
            'calendar_holiday_countries' => ['DE', 'AT'],
        ])->assertRedirect(route('settings.calendar.edit'));

        $this->assertSame(['DE', 'AT'], UserSetting::for($user->id)->calendar_holiday_countries);
    }
}
