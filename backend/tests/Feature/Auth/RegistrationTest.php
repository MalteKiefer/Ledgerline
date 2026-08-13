<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function allowRegistration(bool $on): void
    {
        AppSettings::current()->forceFill(['allow_registration' => $on])->save();
    }

    public function test_register_screen_is_hidden_when_registration_is_disabled(): void
    {
        $this->allowRegistration(false);
        $this->get(route('register'))->assertRedirect(route('login'));
    }

    public function test_register_screen_renders_when_registration_is_enabled(): void
    {
        $this->allowRegistration(true);
        $this->get(route('register'))->assertOk();
    }

    public function test_registration_is_blocked_when_disabled(): void
    {
        $this->allowRegistration(false);

        $this->post(route('register.store'), [
            'name' => 'Mallory',
            'email' => 'mallory@example.test',
            'password' => 'a-very-strong-passphrase',
            'password_confirmation' => 'a-very-strong-passphrase',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'mallory@example.test']);
    }

    public function test_a_user_can_register_when_enabled_and_is_never_admin(): void
    {
        $this->allowRegistration(true);

        $this->post(route('register.store'), [
            'name' => 'Newcomer',
            'email' => 'newcomer@example.test',
            'password' => 'a-very-strong-passphrase',
            'password_confirmation' => 'a-very-strong-passphrase',
        ]);

        $user = User::where('email', 'newcomer@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('user', $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_registration_enforces_the_twelve_character_password_floor(): void
    {
        $this->allowRegistration(true);

        $this->post(route('register.store'), [
            'name' => 'Shorty',
            'email' => 'shorty@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'shorty@example.test']);
    }
}
