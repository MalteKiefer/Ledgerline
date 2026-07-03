<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Reminder;
use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_todo_with_due_and_channels_creates_a_reminder(): void
    {
        $this->signIn();

        $this->postJson(route('todos.store'), [
            'title' => 'Pay invoice',
            'priority' => 'high',
            'due' => '2026-07-20T09:00',
            'reminder_channels' => ['desktop', 'ntfy'],
            'url' => 'https://example.com/invoice',
        ])->assertOk();

        $todo = Todo::firstWhere('title', 'Pay invoice');
        $reminder = Reminder::firstWhere('todo_id', $todo->id);
        $this->assertNotNull($reminder);
        $this->assertSame(['desktop', 'ntfy'], $reminder->channels);
        $this->assertSame('Pay invoice', $reminder->title);
    }

    public function test_completing_a_todo_removes_its_reminder(): void
    {
        $this->signIn();
        $todo = Todo::create(['title' => 'X', 'priority' => 'normal', 'due_at' => now()->addDay(), 'reminder_channels' => ['desktop']]);
        Reminder::create(['todo_id' => $todo->id, 'due_at' => $todo->due_at, 'channels' => ['desktop'], 'title' => 'X']);

        $this->patchJson(route('todos.patch', $todo), ['done' => true])->assertOk();

        $this->assertSame(0, Reminder::where('todo_id', $todo->id)->count());
    }

    public function test_a_non_http_url_is_stripped_from_the_reminder(): void
    {
        $this->signIn();

        $this->postJson(route('todos.store'), [
            'title' => 'Bad link', 'priority' => 'normal',
            'due' => '2026-07-20T09:00', 'reminder_channels' => ['desktop'],
            'url' => 'javascript:alert(1)',
        ])->assertOk();

        $todo = Todo::firstWhere('title', 'Bad link');
        $this->assertNull(Reminder::firstWhere('todo_id', $todo->id)->url);
    }

    public function test_the_command_fires_due_reminders_once(): void
    {
        $this->signIn(); // AppNotification::record writes one entry per user
        Reminder::create(['due_at' => now()->subMinute(), 'channels' => ['desktop'], 'title' => 'Do it']);
        Reminder::create(['due_at' => now()->addDay(), 'channels' => ['desktop'], 'title' => 'Later']);

        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame(1, AppNotification::count());
        $this->assertSame(1, Reminder::whereNotNull('fired_at')->count());

        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame(1, AppNotification::count()); // not re-sent
    }
}
