<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Public /api/v1/invite/{invite}/{token} consumption: show reports validity as JSON;
 * store sets the password and mints a bearer (not a session login).
 */
class ApiInviteConsumeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{user: User, invite: InviteLink, token: string} */
    private function seedInvite(bool $expired = false): array
    {
        $user = User::factory()->create(['email' => 'invitee@example.com']);
        $token = InviteLink::newToken();
        $invite = new InviteLink;
        $invite->forceFill([
            'user_id' => $user->id,
            'token_hash' => InviteLink::hashToken($token),
            'expires_at' => $expired ? now()->subHour() : now()->addDay(),
            'created_by' => $user->id,
        ])->save();

        return ['user' => $user, 'invite' => $invite, 'token' => $token];
    }

    public function test_show_reports_a_valid_link(): void
    {
        ['invite' => $invite, 'token' => $token] = $this->seedInvite();

        $this->getJson("/api/v1/invite/{$invite->id}/{$token}")
            ->assertOk()
            ->assertJson(['valid' => true, 'email' => 'invitee@example.com']);
    }

    public function test_show_rejects_a_bad_token(): void
    {
        ['invite' => $invite] = $this->seedInvite();

        $this->getJson("/api/v1/invite/{$invite->id}/wrong-token")
            ->assertStatus(404)
            ->assertJson(['valid' => false]);
    }

    public function test_show_rejects_an_expired_link(): void
    {
        ['invite' => $invite, 'token' => $token] = $this->seedInvite(expired: true);

        $this->getJson("/api/v1/invite/{$invite->id}/{$token}")->assertStatus(404);
    }

    public function test_store_sets_the_password_and_mints_a_working_bearer(): void
    {
        ['user' => $user, 'invite' => $invite, 'token' => $token] = $this->seedInvite();

        $bearer = $this->postJson("/api/v1/invite/{$invite->id}/{$token}", [
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
        ])->assertOk()->assertJsonPath('user.email', 'invitee@example.com')->json('token');

        $this->assertIsString($bearer);
        // Password was set.
        $this->assertTrue(Hash::check('a-strong-password-123', (string) $user->fresh()?->password));
        // Link is single-use (consumed).
        $this->assertNotNull($invite->fresh()?->used_at);
        // The minted bearer authenticates the API.
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$bearer])->assertOk();
    }

    public function test_store_revokes_pre_existing_tokens_on_consume(): void
    {
        ['user' => $user, 'invite' => $invite, 'token' => $token] = $this->seedInvite();
        // A stale device token exists before the invite is consumed.
        $stale = $user->createToken('old-device', ['device'])->plainTextToken;

        $fresh = $this->postJson("/api/v1/invite/{$invite->id}/{$token}", [
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
        ])->assertOk()->json('token');

        // Kill-switch: the pre-existing device token is revoked (row gone); only the
        // freshly minted bearer remains.
        $this->assertNull(PersonalAccessToken::findToken($stale));
        $this->assertIsString($fresh);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_store_rejects_a_consumed_link_on_reuse(): void
    {
        ['invite' => $invite, 'token' => $token] = $this->seedInvite();

        $this->postJson("/api/v1/invite/{$invite->id}/{$token}", [
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
        ])->assertOk();

        // Second use is refused (used_at set).
        $this->postJson("/api/v1/invite/{$invite->id}/{$token}", [
            'password' => 'another-strong-pass-123',
            'password_confirmation' => 'another-strong-pass-123',
        ])->assertStatus(404);
    }

    public function test_store_validates_password_confirmation(): void
    {
        ['invite' => $invite, 'token' => $token] = $this->seedInvite();

        $this->postJson("/api/v1/invite/{$invite->id}/{$token}", [
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertStatus(422);
    }
}
