<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Todo;
use App\Models\User;
use App\Services\Todos\TodoReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodoReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeTodo(User $user, array $attrs): Todo
    {
        $this->actingAs($user);

        return Todo::create($attrs + ['title' => 'Task']);
    }

    public function test_due_selection_picks_overdue_not_done_only(): void
    {
        $user = User::factory()->create();
        $due = $this->makeTodo($user, ['title' => 'Overdue', 'due' => Carbon::now()->subDay()]);
        $this->makeTodo($user, ['title' => 'Future', 'due' => Carbon::now()->addDays(2)]);
        $this->makeTodo($user, ['title' => 'Done', 'due' => Carbon::now()->subDay(), 'done' => true]);
        $this->makeTodo($user, ['title' => 'No due']);

        $picked = app(TodoReminders::class)->dueForReminder();

        $this->assertCount(1, $picked);
        $this->assertSame($due->id, $picked->first()?->id);
    }

    public function test_command_reminds_and_stamps_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $todo = $this->makeTodo($user, ['title' => 'Pay rent', 'due' => Carbon::now()->subHour()]);

        $this->artisan('todos:remind')->assertSuccessful();

        $todo->refresh();
        $this->assertNotNull($todo->reminded_at, 'reminded_at should be stamped');
        // In-app bell notification (the always-available per-user channel).
        $this->assertDatabaseHas('app_notifications', ['user_id' => $user->id, 'category' => 'reminder']);
        $this->assertSame(1, AppNotification::where('user_id', $user->id)->count());

        // Second run must not re-notify (reminded_at now >= due).
        $this->artisan('todos:remind')->assertSuccessful();
        $this->assertSame(1, AppNotification::where('user_id', $user->id)->count());
    }

    public function test_future_and_done_are_not_reminded(): void
    {
        $user = User::factory()->create();
        $future = $this->makeTodo($user, ['title' => 'Later', 'due' => Carbon::now()->addWeek()]);
        $done = $this->makeTodo($user, ['title' => 'Finished', 'due' => Carbon::now()->subDay(), 'done' => true]);

        $this->artisan('todos:remind')->assertSuccessful();

        $this->assertNull($future->refresh()->reminded_at);
        $this->assertNull($done->refresh()->reminded_at);
        $this->assertSame(0, AppNotification::where('user_id', $user->id)->count());
    }

    public function test_moving_due_forward_rearms_reminder(): void
    {
        $user = User::factory()->create();
        $todo = $this->makeTodo($user, ['title' => 'Shift', 'due' => Carbon::now()->subDay()]);

        $this->artisan('todos:remind')->assertSuccessful();
        $todo->refresh();
        $firstReminded = $todo->reminded_at;
        $this->assertNotNull($firstReminded);

        // Push the due date to a moment after the last reminder → re-arm.
        $todo->forceFill(['due' => Carbon::now()->addMinute()])->saveQuietly();
        // Travel a little past the new due so it is overdue again.
        $this->travel(2)->minutes();
        $this->artisan('todos:remind')->assertSuccessful();

        $this->assertSame(2, AppNotification::where('user_id', $user->id)->count());
        $this->travelBack();
    }
}
