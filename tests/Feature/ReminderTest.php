<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_reminder_with_encrypted_title(): void
    {
        $this->signIn();

        $res = $this->postJson(route('reminders.store'), [
            'due_at' => '2026-07-20T09:00',
            'channels' => ['desktop', 'ntfy'],
            'title' => 'Pay the invoice',
            'url' => 'https://example.com/invoice',
        ])->assertOk()->assertJsonStructure(['id']);

        $id = $res->json('id');
        $reminder = Reminder::find($id);
        $this->assertSame('Pay the invoice', $reminder->title);
        $this->assertSame(['desktop', 'ntfy'], $reminder->channels);

        // Title stored encrypted, not as plaintext.
        $raw = DB::table('reminders')->where('id', $id)->value('title');
        $this->assertStringNotContainsString('Pay the invoice', (string) $raw);
    }

    public function test_updating_rearms_a_fired_reminder(): void
    {
        $this->signIn();
        $reminder = Reminder::create([
            'due_at' => now()->subDay(), 'channels' => ['desktop'], 'title' => 'Old', 'fired_at' => now()->subDay(),
        ]);

        $this->putJson(route('reminders.update', $reminder), [
            'due_at' => '2026-08-01T10:00', 'channels' => ['mail'], 'title' => 'New',
        ])->assertOk();

        $reminder->refresh();
        $this->assertNull($reminder->fired_at);
        $this->assertSame('New', $reminder->title);
    }

    public function test_it_rejects_an_unknown_channel(): void
    {
        $this->signIn();
        $this->post(route('reminders.store'), [
            'due_at' => '2026-07-20T09:00', 'channels' => ['carrier-pigeon'], 'title' => 'x',
        ])->assertInvalid('channels.0');
        $this->assertSame(0, Reminder::count());
    }

    public function test_the_command_fires_due_reminders_once(): void
    {
        $this->signIn(); // AppNotification::record writes one entry per user
        // A due, unfired reminder on the in-app channel creates a bell entry and
        // is stamped fired; running again does nothing.
        Reminder::create(['due_at' => now()->subMinute(), 'channels' => ['desktop'], 'title' => 'Do it']);
        // A future one must not fire.
        Reminder::create(['due_at' => now()->addDay(), 'channels' => ['desktop'], 'title' => 'Later']);

        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertSame(1, AppNotification::count());
        $this->assertSame(1, Reminder::whereNotNull('fired_at')->count());

        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame(1, AppNotification::count()); // not re-sent
    }
}
