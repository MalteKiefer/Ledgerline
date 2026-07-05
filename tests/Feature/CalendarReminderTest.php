<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\User;
use App\Services\Calendar\CalendarWriter;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CalendarReminderTest extends TestCase
{
    use RefreshDatabase;

    private function calendar(): Calendar
    {
        $user = User::factory()->create();

        return Calendar::create([
            'user_id' => $user->id, 'uri' => 'default', 'name' => 'Calendar',
            'color' => '#3366cc', 'components' => ['VEVENT'], 'synctoken' => 1,
        ]);
    }

    public function test_a_due_alarm_fires_once_and_dedupes(): void
    {
        $calendar = $this->calendar();
        $start = Carbon::now()->addMinutes(10);
        app(CalendarWriter::class)->create($calendar, [
            'summary' => 'Take pills',
            'start' => $start->format('Y-m-d\TH:i'),
            'end' => (clone $start)->addMinutes(30)->format('Y-m-d\TH:i'),
            'reminder_minutes' => 15, // fires 5 minutes ago → due now
        ]);

        $notifier = Mockery::mock(ChannelNotifier::class);
        $notifier->shouldReceive('send')->once()
            ->with(Mockery::type('array'), 'Take pills', Mockery::type('string'), Mockery::type('array'));
        $this->app->instance(ChannelNotifier::class, $notifier);

        $this->artisan('calendar:remind')->assertSuccessful();
        $this->assertSame(1, DB::table('calendar_alarm_log')->count());

        // A second run must not fire again (already logged).
        $this->artisan('calendar:remind')->assertSuccessful();
        $this->assertSame(1, DB::table('calendar_alarm_log')->count());
    }

    public function test_a_future_alarm_does_not_fire_yet(): void
    {
        $calendar = $this->calendar();
        $start = Carbon::now()->addHours(3);
        app(CalendarWriter::class)->create($calendar, [
            'summary' => 'Later',
            'start' => $start->format('Y-m-d\TH:i'),
            'end' => (clone $start)->addMinutes(30)->format('Y-m-d\TH:i'),
            'reminder_minutes' => 15, // fires in ~2h45m → not due
        ]);

        $notifier = Mockery::mock(ChannelNotifier::class);
        $notifier->shouldNotReceive('send');
        $this->app->instance(ChannelNotifier::class, $notifier);

        $this->artisan('calendar:remind')->assertSuccessful();
        $this->assertSame(0, DB::table('calendar_alarm_log')->count());
    }

    public function test_recurring_alarm_with_tzid_is_dst_correct(): void
    {
        // A daily 09:00 Europe/Berlin standup with a 15-minute lead. Berlin
        // switches to CEST (UTC+2) on Sun 29 Mar 2026, so on/after 30 Mar the
        // 09:00 wall-clock instance is 07:00Z (fire 06:45Z), whereas before DST
        // it was 08:00Z (fire 07:45Z). The alarm must track the event timezone.
        $calendar = $this->calendar();
        app(CalendarWriter::class)->create($calendar, [
            'summary' => 'Standup',
            'start' => '2026-03-27T09:00',
            'end' => '2026-03-27T09:30',
            'timezone' => 'Europe/Berlin',
            'rrule' => 'FREQ=DAILY',
            'reminder_minutes' => 15,
        ]);

        // Fire the run exactly at the DST-correct instant for the 30-Mar (CEST)
        // occurrence: 09:00 CEST = 07:00Z, minus the 15m lead = 06:45Z.
        Carbon::setTestNow(Carbon::parse('2026-03-30 06:45:00', 'UTC'));
        $notifier = Mockery::mock(ChannelNotifier::class);
        $notifier->shouldReceive('send');
        $this->app->instance(ChannelNotifier::class, $notifier);
        $this->artisan('calendar:remind')->assertSuccessful();

        // The 30-Mar occurrence must be logged at 07:00Z (CEST). A UTC-computed
        // (DST-blind) expansion would place it at 08:00Z — an hour off.
        $this->assertSame(
            1,
            DB::table('calendar_alarm_log')->where('occurrence_at', '2026-03-30 07:00:00')->count(),
            'The 30-Mar occurrence must fire at 07:00Z (CEST), not 08:00Z.',
        );
        $this->assertSame(
            0,
            DB::table('calendar_alarm_log')->where('occurrence_at', '2026-03-30 08:00:00')->count(),
            'No 08:00Z instance should exist on 30 Mar — that would be the pre-DST (+1h) offset.',
        );

        Carbon::setTestNow();
    }

    public function test_a_recurring_event_fires_per_instance(): void
    {
        $calendar = $this->calendar();
        // Daily event starting 10 minutes from now with a 15-minute lead: today's
        // instance is due, tomorrow's is not.
        $start = Carbon::now()->addMinutes(10);
        app(CalendarWriter::class)->create($calendar, [
            'summary' => 'Standup',
            'start' => $start->format('Y-m-d\TH:i'),
            'end' => (clone $start)->addMinutes(15)->format('Y-m-d\TH:i'),
            'rrule' => 'FREQ=DAILY',
            'reminder_minutes' => 15,
        ]);

        $notifier = Mockery::mock(ChannelNotifier::class);
        $notifier->shouldReceive('send')->once();
        $this->app->instance(ChannelNotifier::class, $notifier);

        $this->artisan('calendar:remind')->assertSuccessful();
        $this->assertSame(1, DB::table('calendar_alarm_log')->count());
    }
}
