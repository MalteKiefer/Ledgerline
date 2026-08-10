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
 * tasks:remind-alarms creates a notification (category=task) when a VTODO's
 * per-task reminder trigger (due − alarm_minutes_before) falls in the current
 * tick, dedups across overlapping ticks, and does not fire for tasks without an
 * alarm. The daily digest command skips tasks that carry an alarm.
 */
class RemindTaskAlarmsTest extends TestCase
{
    use RefreshDatabase;

    private function taskList(int $userId): Calendar
    {
        return Calendar::create([
            'user_id' => $userId, 'name' => 'Reminders',
            'uri' => 'tasks-'.$userId.'-'.Str::lower(Str::random(4)), 'color' => '#6750a4',
            'component' => Calendar::COMPONENT_TODO, 'timezone' => 'UTC', 'synctoken' => 1,
        ]);
    }

    public function test_task_alarm_fires_once_when_trigger_arrives(): void
    {
        Queue::fake();
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        // Due in 15 min with a 15-min reminder → trigger is now.
        $due = CarbonImmutable::now()->utc()->addMinutes(15);
        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Pay invoice',
            'due' => $due->toIso8601ZuluString(), 'alarm_minutes_before' => 15,
        ])->assertStatus(201);

        $this->artisan('tasks:remind-alarms')->assertExitCode(0);

        $this->assertSame(1, AppNotification::where('category', 'task')->count());
        Queue::assertPushed(SendPushJob::class);

        // Overlapping second tick → deduped.
        $this->artisan('tasks:remind-alarms')->assertExitCode(0);
        $this->assertSame(1, AppNotification::where('category', 'task')->count());
    }

    public function test_task_without_alarm_does_not_fire_and_digest_skips_alarmed(): void
    {
        Queue::fake();
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        // Overdue task WITHOUT an alarm → the precise command ignores it.
        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'No alarm',
            'due' => CarbonImmutable::now()->utc()->subDay()->toIso8601ZuluString(),
        ])->assertStatus(201);
        // Overdue task WITH an alarm → the daily digest must skip it (no double push).
        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'With alarm',
            'due' => CarbonImmutable::now()->utc()->subDay()->toIso8601ZuluString(),
            'alarm_minutes_before' => 30,
        ])->assertStatus(201);

        $this->artisan('tasks:remind-alarms')->assertExitCode(0);
        // Neither is in this tick's window (both overdue by a day) → no alarm push.
        $this->assertSame(0, AppNotification::where('category', 'task')->count());

        $this->artisan('tasks:remind')->assertExitCode(0);
        // Only the no-alarm task gets the date-level digest.
        $this->assertSame(1, AppNotification::where('category', 'task')->count());
    }
}
