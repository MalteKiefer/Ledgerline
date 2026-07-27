<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="email"', false)->assertSee('name="password"', false);
    }

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com', 'password' => 'super-secret-123']);

        $this->post('/login', ['email' => 'me@example.com', 'password' => 'super-secret-123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'me@example.com', 'password' => 'super-secret-123']);

        $this->post('/login', ['email' => 'me@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
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
