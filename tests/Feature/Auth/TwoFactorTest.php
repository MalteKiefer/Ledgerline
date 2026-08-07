<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function currentOtp(User $user): string
    {
        $secret = decrypt((string) $user->two_factor_secret);

        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /**
     * Two-factor management now requires a fresh password confirmation
     * (config/fortify.php `confirmPassword => true`). Prime the session via the
     * real confirm-password endpoint so the guarded 2FA routes proceed.
     */
    private function confirmPassword(string $password = 'password'): void
    {
        $this->post(route('password.confirm.store'), ['password' => $password])
            ->assertSessionHasNoErrors();
    }

    public function test_a_user_can_enable_confirm_and_disable_two_factor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->confirmPassword();

        // Enable — generates a pending secret + recovery codes, not yet confirmed.
        $this->post(route('two-factor.enable'))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        // Confirm with a valid TOTP code.
        $this->post(route('two-factor.confirm'), ['code' => $this->currentOtp($user)])
            ->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);

        // Recovery codes are available once enabled.
        $codes = $this->get(route('two-factor.recovery-codes'))->json();
        $this->assertIsArray($codes);
        $this->assertNotEmpty($codes);

        // Disable clears the secret.
        $this->delete(route('two-factor.disable'))->assertSessionHasNoErrors();
        $this->assertNull($user->refresh()->two_factor_secret);
    }

    public function test_two_factor_management_requires_a_fresh_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Without a prior password confirmation the guarded 2FA routes must NOT
        // act — Fortify's RequirePassword middleware redirects to the
        // confirm-password screen. Defends a stolen/hijacked web session from
        // silently weakening the second factor.
        $this->post(route('two-factor.enable'))->assertRedirect(route('password.confirm'));
        $this->assertNull($user->refresh()->two_factor_secret);

        $this->delete(route('two-factor.disable'))->assertRedirect(route('password.confirm'));

        // After confirming the password, the same op proceeds.
        $this->confirmPassword();
        $this->post(route('two-factor.enable'))->assertSessionHasNoErrors();
        $this->assertNotNull($user->refresh()->two_factor_secret);
    }

    public function test_login_with_two_factor_enabled_requires_the_challenge(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-very-strong-passphrase')]);
        // Simulate a fully-enabled 2FA account.
        $this->actingAs($user);
        $this->confirmPassword('a-very-strong-passphrase');
        $this->post(route('two-factor.enable'));
        $user->refresh();
        $this->post(route('two-factor.confirm'), ['code' => $this->currentOtp($user)]);
        $this->post(route('logout'));

        // Password login now stops at the two-factor challenge instead of the home page.
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'a-very-strong-passphrase',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_the_challenge_accepts_a_valid_recovery_code(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-very-strong-passphrase')]);
        $this->actingAs($user);
        $this->confirmPassword('a-very-strong-passphrase');
        $this->post(route('two-factor.enable'));
        $user->refresh();
        $this->post(route('two-factor.confirm'), ['code' => $this->currentOtp($user)]);
        $this->post(route('logout'));

        /** @var array<int, string> $codes */
        $codes = json_decode(decrypt((string) $user->refresh()->two_factor_recovery_codes), true);
        $this->assertNotEmpty($codes);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'a-very-strong-passphrase',
        ]);

        $this->post(route('two-factor.login.store'), ['recovery_code' => $codes[0]]);

        $this->assertAuthenticatedAs($user);
    }
}
