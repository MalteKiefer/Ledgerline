<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendPushJob;
use App\Models\AppNotification;
use App\Models\Calendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * calendar:remind creates a notification (category=event) when an event's VALARM
 * trigger (start − alarm_minutes_before) falls in the current tick, and dedups
 * across overlapping ticks.
 */
class RemindCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function calendar(int $userId): Calendar
    {
        return Calendar::create([
            'user_id' => $userId, 'name' => 'Personal',
            'uri' => 'cal-'.$userId.'-'.Str::lower(Str::random(4)), 'color' => '#6750a4',
            'component' => Calendar::COMPONENT_EVENT, 'timezone' => 'UTC', 'synctoken' => 1,
        ]);
    }

    public function test_event_reminder_fires_once_when_trigger_arrives(): void
    {
        Queue::fake();
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        // Start in 15 min with a 15-min reminder → trigger is now.
        $start = CarbonImmutable::now()->utc()->addMinutes(15);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Standup',
            'dtstart' => $start->toIso8601ZuluString(), 'alarm_minutes_before' => 15,
        ])->assertStatus(201);

        $this->artisan('calendar:remind')->assertExitCode(0);

        $this->assertSame(1, AppNotification::where('category', 'event')->count());
        Queue::assertPushed(SendPushJob::class);

        // Overlapping second tick → deduped.
        $this->artisan('calendar:remind')->assertExitCode(0);
        $this->assertSame(1, AppNotification::where('category', 'event')->count());
    }

    public function test_event_without_alarm_does_not_fire(): void
    {
        Queue::fake();
        $user = $this->signIn();
        $calendar = $this->calendar($user->id);
        $start = CarbonImmutable::now()->utc()->addMinutes(15);
        $this->postJson(route('calendar.events.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'No alarm',
            'dtstart' => $start->toIso8601ZuluString(),
        ])->assertStatus(201);

        $this->artisan('calendar:remind')->assertExitCode(0);

        $this->assertSame(0, AppNotification::where('category', 'event')->count());
    }
}
