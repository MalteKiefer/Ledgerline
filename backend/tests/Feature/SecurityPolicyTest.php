<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin', 'two_factor_confirmed_at' => now()])->save();

        return $u;
    }

    public function test_admin_updates_password_policy_and_it_is_enforced(): void
    {
        $admin = $this->admin();
        $this->putJson('/api/v1/admin/security', [
            'max_connected_devices' => 3,
            'pw_min_length' => 16, 'pw_require_symbols' => true,
        ], $this->bearer($admin))->assertOk()->assertJsonPath('pw_require_symbols', true);

        // A 12-char no-symbol password now fails the shared rule.
        $rule = PasswordValidationRules::passwordRule();
        $this->assertTrue(Validator::make(['p' => 'short12chars'], ['p' => $rule])->fails());
        $this->assertTrue(Validator::make(['p' => 'LongEnoughButNoSymbol1'], ['p' => $rule])->fails());
        $this->assertFalse(Validator::make(['p' => 'LongEnough-With#Symbol1'], ['p' => $rule])->fails());
    }

    public function test_force_2fa_blocks_unenrolled_but_allows_enrolment_paths(): void
    {
        AppSettings::current()->update(['force_2fa' => true]);
        $user = User::factory()->create(); // no 2FA

        // A normal data endpoint is blocked with the enrolment status.
        $this->getJson('/api/v1/finance/data', $this->bearer($user))
            ->assertStatus(403)->assertJsonPath('status', 'two_factor_required');

        // /me and the 2FA setup path stay reachable so the user can enrol.
        $this->getJson('/api/v1/me', $this->bearer($user))->assertOk()->assertJsonPath('user.two_factor_required', true);
    }

    public function test_enrolled_user_is_unaffected_by_force_2fa(): void
    {
        AppSettings::current()->update(['force_2fa' => true]);
        $user = User::factory()->create();
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->getJson('/api/v1/me', $this->bearer($user))->assertOk()->assertJsonPath('user.two_factor_required', false);
    }
}
