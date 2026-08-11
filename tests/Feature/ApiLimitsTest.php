<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('phone', ['device'])->plainTextToken];
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    public function test_requires_admin(): void
    {
        $this->getJson('/api/v1/admin/limits')->assertUnauthorized();
        $this->getJson('/api/v1/admin/limits', $this->bearer(User::factory()->create()))->assertForbidden();
    }

    public function test_show_returns_settings_and_effective(): void
    {
        $this->getJson('/api/v1/admin/limits', $this->bearer($this->admin()))->assertOk()
            ->assertJsonStructure(['settings' => ['audit_retention_days', 'files_quota_mb'], 'effective' => ['audit_retention_days']]);
    }

    public function test_update_persists_validates_and_overrides_config(): void
    {
        $admin = $this->admin();
        // Out-of-range rejected.
        $this->putJson('/api/v1/admin/limits', ['audit_retention_days' => -5], $this->bearer($admin))->assertStatus(422);

        $this->putJson('/api/v1/admin/limits', [
            'audit_retention_days' => 90, 'session_lifetime_minutes' => 240, 'files_quota_mb' => 5000,
        ], $this->bearer($admin))->assertOk();

        $s = AppSettings::current();
        $this->assertSame(90, $s->audit_retention_days);
        $this->assertSame(240, $s->session_lifetime_minutes);

        Cache::forget(AppServiceProvider::OVERRIDES_CACHE_KEY);
        (new AppServiceProvider(app()))->boot();
        $this->assertSame(90, (int) config('ops.audit_retention_days'));
        $this->assertSame(240, (int) config('session.lifetime'));
        $this->assertSame(5000, (int) config('files.quota_mb'));
    }
}
