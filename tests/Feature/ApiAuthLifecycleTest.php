<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Public account lifecycle on /api/v1/auth/*: forgot-password (no enumeration),
 * reset-password (Fortify action reuse + kill switch), register (allow_registration
 * gate + role forced 'user' + email-verification hold).
 */
class ApiAuthLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_is_generic_and_does_not_enumerate(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'exists@example.com']);

        // Existing and non-existent addresses get an identical generic 200.
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'exists@example.com'])
            ->assertOk()->assertJson(['status' => 'reset-link-sent']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()->assertJson(['status' => 'reset-link-sent']);

        // The link is only actually sent for the real account (behaviour, not response).
        Notification::assertSentTo(User::where('email', 'exists@example.com')->first(), ResetPassword::class);
    }

    public function test_reset_password_consumes_a_token_and_revokes_all_tokens(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $user->createToken('old-device', ['device']); // must be evicted (kill switch)
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ])->assertOk()->assertJson(['status' => 'password-reset']);

        $this->assertTrue(Hash::check('brand-new-password-1', (string) $user->fresh()?->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset2@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'reset2@example.com',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ])->assertStatus(422);
    }

    public function test_register_is_forbidden_when_self_registration_is_off(): void
    {
        AppSettings::current()->update(['allow_registration' => false]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
        ])->assertStatus(403);

        $this->assertNull(User::where('email', 'new@example.com')->first());
    }

    public function test_register_creates_a_plain_user_and_holds_for_verification(): void
    {
        Notification::fake();
        AppSettings::current()->update(['allow_registration' => true]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
        ])->assertStatus(201)->assertJson(['status' => 'verify-email'])->assertJsonMissing(['token' => true]);

        $created = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame('user', $created?->role); // role never taken from input
        $this->assertNull($created?->email_verified_at);
    }

    public function test_register_ignores_a_hostile_role_field(): void
    {
        AppSettings::current()->update(['allow_registration' => true]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
            'role' => 'admin',
        ])->assertStatus(201);

        $this->assertSame('user', User::where('email', 'sneaky@example.com')->first()?->role);
    }
}
