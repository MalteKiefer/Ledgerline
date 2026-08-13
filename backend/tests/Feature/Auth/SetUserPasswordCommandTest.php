<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetUserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_a_password_and_verifies_the_email(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test', 'email_verified_at' => null]);

        $this->artisan('user:set-password', ['email' => 'owner@example.test'])
            ->expectsQuestion('New password (min 12 chars)', 'a-very-strong-passphrase')
            ->expectsQuestion('Confirm password', 'a-very-strong-passphrase')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('a-very-strong-passphrase', (string) $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('user', $user->role);
    }

    public function test_the_admin_flag_grants_the_admin_role(): void
    {
        User::factory()->create(['email' => 'boss@example.test', 'role' => 'user']);

        $this->artisan('user:set-password', ['email' => 'boss@example.test', '--admin' => true])
            ->expectsQuestion('New password (min 12 chars)', 'a-very-strong-passphrase')
            ->expectsQuestion('Confirm password', 'a-very-strong-passphrase')
            ->assertSuccessful();

        $this->assertSame('admin', User::where('email', 'boss@example.test')->first()->role);
    }

    public function test_it_rejects_a_short_password(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);
        $original = $user->password;

        $this->artisan('user:set-password', ['email' => 'owner@example.test'])
            ->expectsQuestion('New password (min 12 chars)', 'short')
            ->assertFailed();

        $this->assertSame($original, $user->refresh()->password);
    }

    public function test_it_rejects_a_mismatched_confirmation(): void
    {
        User::factory()->create(['email' => 'owner@example.test']);

        $this->artisan('user:set-password', ['email' => 'owner@example.test'])
            ->expectsQuestion('New password (min 12 chars)', 'a-very-strong-passphrase')
            ->expectsQuestion('Confirm password', 'different-passphrase-entirely')
            ->assertFailed();
    }

    public function test_it_fails_for_an_unknown_email(): void
    {
        $this->artisan('user:set-password', ['email' => 'nobody@example.test'])
            ->assertFailed();
    }
}
