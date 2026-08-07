<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * API 2FA management + password change tests.
 *
 * Tests the following endpoints (registered in routes/api.php under the
 * `auth:sanctum + abilities:device` group):
 *   POST   /api/v1/user/two-factor/enable
 *   GET    /api/v1/user/two-factor/qr
 *   POST   /api/v1/user/two-factor/confirm
 *   GET    /api/v1/user/two-factor/recovery-codes
 *   POST   /api/v1/user/two-factor/recovery-codes/regenerate
 *   DELETE /api/v1/user/two-factor
 *   PUT    /api/v1/user/password
 *   POST   /api/v1/user/email/verify/resend
 */
class TwoFactorApiTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function deviceToken(User $user): string
    {
        return $user->createToken('test-device', ['device'])->plainTextToken;
    }

    private function currentOtp(User $user): string
    {
        $user->refresh();
        $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2FA: enable → QR → confirm → recovery codes → regenerate → disable
    // ──────────────────────────────────────────────────────────────────────────

    public function test_enable_generates_secret_and_returns_enabled(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/user/two-factor/enable')
            ->assertOk()
            ->assertJson(['enabled' => true]);

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_qr_returns_svg_secret_and_uri_after_enable(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)->postJson('/api/v1/user/two-factor/enable');

        $response = $this->withToken($token)
            ->getJson('/api/v1/user/two-factor/qr')
            ->assertOk();

        $this->assertArrayHasKey('svg', $response->json());
        $this->assertArrayHasKey('secret', $response->json());
        $this->assertArrayHasKey('uri', $response->json());
        $this->assertStringContainsString('otpauth://', (string) $response->json('uri'));
        $this->assertStringContainsString('<svg', (string) $response->json('svg'));
    }

    public function test_qr_returns_404_when_2fa_not_enabled(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/user/two-factor/qr')
            ->assertNotFound();
    }

    public function test_confirm_with_valid_code_sets_confirmed_at(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)->postJson('/api/v1/user/two-factor/enable');

        $this->withToken($token)
            ->postJson('/api/v1/user/two-factor/confirm', ['code' => $this->currentOtp($user)])
            ->assertOk()
            ->assertJson(['confirmed' => true]);

        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);
    }

    public function test_confirm_with_invalid_code_returns_422(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)->postJson('/api/v1/user/two-factor/enable');

        $this->withToken($token)
            ->postJson('/api/v1/user/two-factor/confirm', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_recovery_codes_available_after_confirmation(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)->postJson('/api/v1/user/two-factor/enable');
        $this->withToken($token)->postJson('/api/v1/user/two-factor/confirm', ['code' => $this->currentOtp($user)]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/user/two-factor/recovery-codes')
            ->assertOk();

        $codes = $response->json('recovery_codes');
        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);
    }

    public function test_recovery_codes_returns_404_when_not_active(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/user/two-factor/recovery-codes')
            ->assertNotFound();
    }

    public function test_regenerate_recovery_codes_returns_fresh_codes(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)->postJson('/api/v1/user/two-factor/enable');
        $this->withToken($token)->postJson('/api/v1/user/two-factor/confirm', ['code' => $this->currentOtp($user)]);

        $firstCodes = $this->withToken($token)
            ->getJson('/api/v1/user/two-factor/recovery-codes')
            ->json('recovery_codes');

        $newCodes = $this->withToken($token)
            ->postJson('/api/v1/user/two-factor/recovery-codes/regenerate', ['current_password' => 'password'])
            ->assertOk()
            ->json('recovery_codes');

        $this->assertIsArray($newCodes);
        $this->assertCount(8, $newCodes);
        // Fresh codes must differ from the old set.
        $this->assertNotSame($firstCodes, $newCodes);
    }

    public function test_disable_clears_two_factor(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)->postJson('/api/v1/user/two-factor/enable');
        $this->withToken($token)->postJson('/api/v1/user/two-factor/confirm', ['code' => $this->currentOtp($user)]);

        $this->withToken($token)
            ->deleteJson('/api/v1/user/two-factor', ['current_password' => 'password'])
            ->assertOk()
            ->assertJson(['enabled' => false]);

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_disable_and_regenerate_require_the_current_password(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);
        $this->withToken($token)->postJson('/api/v1/user/two-factor/enable');
        $this->withToken($token)->postJson('/api/v1/user/two-factor/confirm', ['code' => $this->currentOtp($user)]);

        // Wrong / missing password → 422, 2FA stays active (stolen device token alone can't disable it).
        $this->withToken($token)->deleteJson('/api/v1/user/two-factor', ['current_password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors(['current_password']);
        $this->withToken($token)->postJson('/api/v1/user/two-factor/recovery-codes/regenerate')
            ->assertUnprocessable()->assertJsonValidationErrors(['current_password']);
        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Password change
    // ──────────────────────────────────────────────────────────────────────────

    public function test_password_change_succeeds_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldCorrectPassword1!')]);
        $token = $this->deviceToken($user);

        $this->withToken($token)
            ->putJson('/api/v1/user/password', [
                'current_password' => 'OldCorrectPassword1!',
                'password' => 'NewStrongPassword99!',
                'password_confirmation' => 'NewStrongPassword99!',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue(Hash::check('NewStrongPassword99!', (string) $user->refresh()->password));
    }

    public function test_password_change_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CorrectPassword1!')]);
        $token = $this->deviceToken($user);

        $this->withToken($token)
            ->putJson('/api/v1/user/password', [
                'current_password' => 'WrongPassword!!',
                'password' => 'NewStrongPassword99!',
                'password_confirmation' => 'NewStrongPassword99!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_validates_minimum_length(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CorrectPassword1!')]);
        $token = $this->deviceToken($user);

        $this->withToken($token)
            ->putJson('/api/v1/user/password', [
                'current_password' => 'CorrectPassword1!',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Auth guard
    // ──────────────────────────────────────────────────────────────────────────

    public function test_all_endpoints_require_bearer_token(): void
    {
        $this->postJson('/api/v1/user/two-factor/enable')->assertUnauthorized();
        $this->getJson('/api/v1/user/two-factor/qr')->assertUnauthorized();
        $this->postJson('/api/v1/user/two-factor/confirm', ['code' => '123456'])->assertUnauthorized();
        $this->getJson('/api/v1/user/two-factor/recovery-codes')->assertUnauthorized();
        $this->postJson('/api/v1/user/two-factor/recovery-codes/regenerate', ['current_password' => 'password'])->assertUnauthorized();
        $this->deleteJson('/api/v1/user/two-factor', ['current_password' => 'password'])->assertUnauthorized();
        $this->putJson('/api/v1/user/password', [])->assertUnauthorized();
        $this->postJson('/api/v1/user/email/verify/resend')->assertUnauthorized();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // E-mail verification resend
    // ──────────────────────────────────────────────────────────────────────────

    public function test_resend_verification_returns_ok_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/user/email/verify/resend')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_resend_verification_is_no_op_for_verified_user(): void
    {
        $user = User::factory()->create();
        $token = $this->deviceToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/user/email/verify/resend')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
