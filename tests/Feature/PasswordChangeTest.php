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

    public function test_security_page_shows_the_password_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.security'))
            ->assertOk()
            ->assertSee(__('account.password_title'))
            ->assertSee('current_password', false)
            ->assertSee(route('user-password.update'), false);
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->actingAs($user)
            ->from(route('profile.security'))
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
            ->from(route('profile.security'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'a-brand-new-secret-42',
                'password_confirmation' => 'a-brand-new-secret-42',
            ])
            ->assertRedirect(route('profile.security'))
            ->assertSessionHasErrors('current_password', null, 'updatePassword');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_short_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->actingAs($user)
            ->from(route('profile.security'))
            ->put(route('user-password.update'), [
                'current_password' => 'old-password-123',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password', null, 'updatePassword');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }
}
