<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendPushJob;
use App\Models\AppNotification;
use App\Models\Calendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * tasks:remind creates a notification-centre row (category=task) for a VTODO due
 * today/overdue, once per task per due-date, which fans out to push via the
 * AppNotification::record choke point.
 */
class RemindTasksTest extends TestCase
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

    public function test_overdue_task_creates_one_notification_and_is_throttled(): void
    {
        Queue::fake();
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Pay rent', 'due' => '2026-08-01T09:00:00Z',
        ])->assertStatus(201);

        $this->artisan('tasks:remind')->assertExitCode(0);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $user->id, 'category' => 'task']);
        $this->assertSame(1, AppNotification::where('category', 'task')->count());
        Queue::assertPushed(SendPushJob::class);

        // Second run same day → throttled, no duplicate row.
        $this->artisan('tasks:remind')->assertExitCode(0);
        $this->assertSame(1, AppNotification::where('category', 'task')->count());
    }

    public function test_completed_task_is_skipped(): void
    {
        Queue::fake();
        $user = $this->signIn();
        $calendar = $this->taskList($user->id);
        $this->postJson(route('calendar.todos.store'), [
            'calendar_id' => $calendar->id, 'summary' => 'Done', 'due' => '2026-08-01T09:00:00Z', 'status' => 'COMPLETED',
        ])->assertStatus(201);

        $this->artisan('tasks:remind')->assertExitCode(0);

        $this->assertSame(0, AppNotification::where('category', 'task')->count());
    }
}
