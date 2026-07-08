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
            'enc_todo' => 'sealed-invoice-blob',
            'priority' => 'high',
            'due' => '2026-07-20T09:00',
            'reminder_channels' => ['desktop', 'ntfy'],
        ])->assertOk();

        $todo = Todo::firstWhere('enc_todo', 'sealed-invoice-blob');
        $reminder = Reminder::firstWhere('todo_id', $todo->id);
        $this->assertNotNull($reminder);
        $this->assertSame($todo->id, $reminder->todo_id);
        $this->assertSame(['desktop', 'ntfy'], $reminder->channels);
        $this->assertNotNull($reminder->due_at);
    }

    public function test_completing_a_todo_removes_its_reminder(): void
    {
        $this->signIn();
        $todo = Todo::create(['enc_todo' => 'sealed', 'is_encrypted' => true, 'priority' => 'normal', 'due_at' => now()->addDay(), 'reminder_channels' => ['desktop']]);
        Reminder::create(['todo_id' => $todo->id, 'due_at' => $todo->due_at, 'channels' => ['desktop']]);

        $this->patchJson(route('todos.patch', $todo), ['done' => true])->assertOk();

        $this->assertSame(0, Reminder::where('todo_id', $todo->id)->count());
    }

    public function test_the_command_fires_due_reminders_once(): void
    {
        $this->signIn(); // the desktop bell targets the to-do owner
        $todo = Todo::create(['enc_todo' => 'sealed', 'is_encrypted' => true, 'priority' => 'normal']); // owned by the signed-in user
        Reminder::create(['todo_id' => $todo->id, 'due_at' => now()->subMinute(), 'channels' => ['desktop']]);
        Reminder::create(['todo_id' => $todo->id, 'due_at' => now()->addDay(), 'channels' => ['desktop']]);

        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame(1, AppNotification::count());
        $this->assertSame(1, Reminder::whereNotNull('fired_at')->count());

        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame(1, AppNotification::count()); // not re-sent
    }
}
