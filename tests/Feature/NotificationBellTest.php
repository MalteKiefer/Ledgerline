<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_notifications_with_an_unread_count(): void
    {
        $user = $this->signIn();
        AppNotification::create(['user_id' => $user->id, 'level' => 'success', 'category' => 'backup', 'title' => 'A']);
        AppNotification::create(['user_id' => $user->id, 'level' => 'error', 'category' => 'backup', 'title' => 'B', 'read_at' => now()]);

        $this->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonCount(2, 'items');
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $user = $this->signIn();
        $n = AppNotification::create(['user_id' => $user->id, 'level' => 'info', 'title' => 'X']);

        $this->postJson(route('notifications.read', $n))->assertOk();

        $this->assertNotNull($n->refresh()->read_at);
    }

    public function test_all_can_be_marked_read(): void
    {
        $user = $this->signIn();
        AppNotification::create(['user_id' => $user->id, 'level' => 'info', 'title' => 'X']);
        AppNotification::create(['user_id' => $user->id, 'level' => 'info', 'title' => 'Y']);

        $this->postJson(route('notifications.read-all'))->assertOk();

        $this->assertSame(0, AppNotification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_a_user_cannot_read_anothers_notification(): void
    {
        $this->signIn();
        $other = User::factory()->create();
        $n = AppNotification::create(['user_id' => $other->id, 'level' => 'info', 'title' => 'Secret']);

        $this->postJson(route('notifications.read', $n))->assertForbidden();
    }

    public function test_push_creates_one_per_user(): void
    {
        $user = $this->signIn();
        AppNotification::record('success', 'Backup done', 'details', 'backup');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id, 'level' => 'success', 'category' => 'backup', 'title' => 'Backup done',
        ]);
    }
}
