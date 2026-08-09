<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_serves_the_spa_shell(): void
    {
        // The Blade UI is retired; /login now returns the SPA shell and the Vue
        // app renders the login screen client-side (auth via the bearer API).
        $this->get('/login')->assertOk()->assertSee('id="app"', false);
    }

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com', 'password' => 'super-secret-123']);

        $this->post('/login', ['email' => 'me@example.com', 'password' => 'super-secret-123'])
            ->assertRedirect('/finance');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_stamps_last_login_at(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com', 'password' => 'super-secret-123', 'last_login_at' => null]);

        $this->post('/login', ['email' => 'me@example.com', 'password' => 'super-secret-123']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'me@example.com', 'password' => 'super-secret-123']);

        $this->post('/login', ['email' => 'me@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_blocked_user_cannot_log_in_via_fortify(): void
    {
        $user = User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => 'super-secret-123',
            'blocked_at' => now(),
        ]);

        // Correct credentials, but the account is blocked: generic failure, no
        // enumeration, and no session (the block gate returns null).
        $this->post('/login', ['email' => 'blocked@example.com', 'password' => 'super-secret-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Unblocking restores login.
        $user->forceFill(['blocked_at' => null])->save();
        $this->post('/login', ['email' => 'blocked@example.com', 'password' => 'super-secret-123'])
            ->assertRedirect('/finance');
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_password_is_hashed_and_gate_keys_on_role(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'super-secret-123']);
        $user = User::factory()->create();

        $this->assertTrue(str_starts_with((string) $admin->password, '$'), 'password stored hashed');
        $this->assertTrue($admin->managesGlobalSettings());
        $this->assertFalse($user->managesGlobalSettings());
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout')->assertRedirect();
        $this->assertGuest();
    }
}
