<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Console\Commands\DispatchCalendarReminders;
use App\Models\AppNotification;
use App\Models\CalendarReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarReminderTest extends TestCase
{
    use RefreshDatabase;

    private function reminder(User $user, string $eventId, Carbon $at, ?Carbon $delivered = null): CalendarReminder
    {
        $r = new CalendarReminder(['event_id' => $eventId, 'remind_at' => $at]);
        $r->user_id = $user->id; // user_id is not fillable (AssignsOwner); set directly
        $r->delivered_at = $delivered;
        $r->save();

        return $r;
    }

    public function test_put_replaces_future_undelivered_reminders_owner_scoped(): void
    {
        $user = User::factory()->create();
        $future = Carbon::now()->addHour()->toIso8601String();
        $past = Carbon::now()->subHour()->toIso8601String();

        $this->actingAs($user)->putJson('/calendar/reminders', ['reminders' => [
            ['event_id' => 'e1', 'recurrence_id' => '2026-08-05', 'remind_at' => $future],
            ['event_id' => 'e2', 'remind_at' => $past], // past → ignored
        ]])->assertOk()->assertJson(['count' => 1]);

        $this->assertDatabaseHas('calendar_reminders', ['user_id' => $user->id, 'event_id' => 'e1']);
        $this->assertDatabaseMissing('calendar_reminders', ['event_id' => 'e2']);

        // A second PUT replaces the future set.
        $this->actingAs($user)->putJson('/calendar/reminders', ['reminders' => [
            ['event_id' => 'e3', 'remind_at' => $future],
        ]])->assertOk()->assertJson(['count' => 1]);

        $this->assertDatabaseMissing('calendar_reminders', ['event_id' => 'e1']);
        $this->assertDatabaseHas('calendar_reminders', ['event_id' => 'e3']);
    }

    public function test_empty_set_clears_reminders(): void
    {
        $user = User::factory()->create();
        $this->reminder($user, 'e1', Carbon::now()->addHour());

        $this->actingAs($user)->putJson('/calendar/reminders', ['reminders' => []])->assertOk()->assertJson(['count' => 0]);
        $this->assertDatabaseCount('calendar_reminders', 0);
    }

    public function test_dispatch_pushes_due_reminders_and_marks_delivered(): void
    {
        $user = User::factory()->create();
        $due = $this->reminder($user, 'e1', Carbon::now()->subMinute());
        $notYet = $this->reminder($user, 'e2', Carbon::now()->addHour());

        $this->artisan(DispatchCalendarReminders::class)->assertSuccessful();

        $this->assertNotNull($due->fresh()->delivered_at);
        $this->assertNull($notYet->fresh()->delivered_at);
        // A generic in-app bell notification was recorded (no event content).
        $this->assertDatabaseHas('app_notifications', ['user_id' => $user->id, 'category' => 'calendar']);
        $note = AppNotification::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($note);
        $this->assertSame('Calendar reminder', $note->title);
    }
}
