<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    // SPA-only cutover: the password form lives in the SPA (the /profile/security
    // Blade page is gone). The change itself still goes through Fortify's
    // user-password.update endpoint, which these tests exercise directly.

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->actingAs($user)
            ->from('/profile/security')
            ->put(route('user-password.update'), [
                'current_password' => 'old-password-123',
                'password' => 'a-brand-new-secret-42',
                'password_confirmation' => 'a-brand-new-secret-42',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('a-brand-new-secret-42', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->actingAs($user)
            ->from('/profile/security')
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'a-brand-new-secret-42',
                'password_confirmation' => 'a-brand-new-secret-42',
            ])
            ->assertRedirect('/profile/security')
            ->assertSessionHasErrors('current_password', null, 'updatePassword');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_short_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->actingAs($user)
            ->from('/profile/security')
            ->put(route('user-password.update'), [
                'current_password' => 'old-password-123',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password', null, 'updatePassword');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }
}
