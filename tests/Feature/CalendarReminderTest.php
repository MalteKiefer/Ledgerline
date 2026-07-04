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
