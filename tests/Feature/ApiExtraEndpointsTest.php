<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Smoke-tests for the mobile API endpoints that were added alongside the
 * devices/notifications/preferences/account features.  Every test uses a
 * real Sanctum bearer token (abilities:device) the same way the native app
 * does.  The goal is to prove each route is reachable, returns JSON (not a
 * redirect), and respects the auth guard — not to exercise every edge case.
 */
class ApiExtraEndpointsTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function bearer(User $user, string $name = 'device'): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken($name, ['device'])->plainTextToken];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Auth guard
    // ──────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_to_devices_index_returns_401(): void
    {
        $this->getJson(route('api.devices.index'))->assertUnauthorized();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Devices
    // ──────────────────────────────────────────────────────────────────────

    public function test_devices_index_returns_device_list(): void
    {
        $user = User::factory()->create();

        $this->getJson(route('api.devices.index'), $this->bearer($user))
            ->assertOk()
            ->assertJsonStructure(['devices']);
    }

    public function test_devices_revoke_removes_the_target_token(): void
    {
        $user = User::factory()->create();

        // Create a second token that will be the revocation target.
        $target = $user->createToken('phone', ['device']);
        $targetId = $target->accessToken->id;

        $this->deleteJson(
            route('api.devices.revoke', $targetId),
            [],
            $this->bearer($user),
        )
            ->assertOk()
            ->assertJson(['ok' => true]);

        // The target token row must be gone.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $targetId]);
    }

    public function test_devices_wipe_flags_the_target_token(): void
    {
        $user = User::factory()->create();

        // Create a second token that will receive the wipe flag.
        $target = $user->createToken('tablet', ['device']);
        $targetId = $target->accessToken->id;

        $this->postJson(
            route('api.devices.wipe', $targetId),
            [],
            $this->bearer($user),
        )
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $targetId,
        ]);
        // The wipe_requested_at column must be populated.
        $this->assertNotNull(
            DB::table('personal_access_tokens')->where('id', $targetId)->value('wipe_requested_at'),
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Notifications
    // ──────────────────────────────────────────────────────────────────────

    public function test_notifications_index_returns_empty_list_for_fresh_user(): void
    {
        $user = User::factory()->create();

        $this->getJson(route('api.notifications.index'), $this->bearer($user))
            ->assertOk()
            ->assertJsonStructure(['unread', 'items']);
    }

    public function test_notifications_mark_all_read_returns_ok_true(): void
    {
        $user = User::factory()->create();

        // Seed one unread notification.
        AppNotification::create([
            'user_id' => $user->id,
            'level' => 'info',
            'category' => 'test',
            'title' => 'Hello',
            'body' => 'World',
        ]);

        $this->postJson(route('api.notifications.read-all'), [], $this->bearer($user))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Preferences: locale + theme + avatar refresh
    // These controllers branch on expectsJson() and must return JSON (not
    // a 302 redirect) when reached via Bearer token.
    // ──────────────────────────────────────────────────────────────────────

    public function test_locale_update_returns_json_not_redirect(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(
            route('api.locale.update'),
            ['locale' => 'en'],
            $this->bearer($user),
        );

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(200, $response->status(), 'Must be 200, not a 302 redirect');
    }

    public function test_theme_update_returns_json_with_theme_value(): void
    {
        $user = User::factory()->create();
        // Ensure the UserSetting row exists so the update() won't hit
        // firstOrCreate with a missing memoisation key.
        UserSetting::for($user->id);

        $response = $this->postJson(
            route('api.theme.update'),
            ['theme' => 'dark'],
            $this->bearer($user),
        );

        $response->assertOk()->assertJson(['ok' => true, 'theme' => 'dark']);
        $this->assertSame(200, $response->status(), 'Must be 200, not a 302 redirect');
    }

    public function test_avatar_refresh_returns_json_with_refreshed_key(): void
    {
        $user = User::factory()->create();

        // The AvatarFetcher will silently return false when no avatar_url is
        // set — that is fine; we only care that the route is reachable and
        // answers JSON (not a redirect).
        $response = $this->postJson(
            route('api.profile.avatar.refresh'),
            [],
            $this->bearer($user),
        );

        $response->assertOk()->assertJsonStructure(['refreshed']);
        $this->assertSame(200, $response->status(), 'Must be 200, not a 302 redirect');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Account
    // ──────────────────────────────────────────────────────────────────────

    public function test_revoke_session_returns_ok_true_for_nonexistent_id(): void
    {
        // Revoking a session id that does not exist is a no-op — the query
        // deletes 0 rows; the controller still returns {ok:true}.
        $user = User::factory()->create();

        $this->deleteJson(
            route('api.account.sessions.revoke', 'nonexistent-session-id'),
            [],
            $this->bearer($user),
        )
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_account_destroy_with_correct_confirmation_deletes_user(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $this->deleteJson(
            route('api.account.destroy'),
            ['confirmation' => $user->email],
            $this->bearer($user),
        )
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_account_destroy_with_wrong_confirmation_returns_422(): void
    {
        $user = User::factory()->create();

        $this->deleteJson(
            route('api.account.destroy'),
            ['confirmation' => 'wrong@example.com'],
            $this->bearer($user),
        )->assertUnprocessable();
    }
}
