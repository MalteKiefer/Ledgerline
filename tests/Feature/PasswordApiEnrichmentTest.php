<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanctum bearer-token (device ability) tests for the password enrichment
 * endpoints mirrored to /api/v1 for mobile parity.
 *
 * These exercise the same controllers used by the web routes (PasswordIconController,
 * PasswordBreachController, TwoFactorDirectoryController) via Sanctum bearer auth,
 * confirming the controllers are guard-agnostic.
 *
 * Uses real tokens (createToken) rather than Sanctum::actingAs because the
 * UpdateTokenIp middleware needs a persisted PersonalAccessToken row.
 * Outbound calls are avoided by exercising the validation / early-return paths
 * in each controller before any HTTP egress occurs.
 */
class PasswordApiEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Return Authorization header for a device-scoped bearer token. */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('device', ['device'])->plainTextToken];
    }

    /** Return Authorization header for a token with a non-device ability. */
    private function bearerWithAbility(User $user, string $ability): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('other', [$ability])->plainTextToken];
    }

    // =========================================================================
    // GET /api/v1/passwords/icon  (PasswordIconController@fetch)
    // =========================================================================

    public function test_icon_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/passwords/icon');

        $response->assertUnauthorized();
    }

    public function test_icon_wrong_ability_returns_403(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/passwords/icon', $this->bearerWithAbility($user, 'read-only'));

        $response->assertForbidden();
    }

    public function test_icon_missing_domain_returns_null_icon(): void
    {
        // No 'domain' param → controller regex fails → returns {icon: null} (no egress).
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/passwords/icon', $this->bearer($user));

        $response->assertOk();
        $response->assertJson(['icon' => null]);
    }

    public function test_icon_invalid_domain_returns_null_icon(): void
    {
        // Malformed domain → regex guard fails → {icon: null} (no egress).
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/passwords/icon?domain=not-a-valid-domain-!!', $this->bearer($user));

        $response->assertOk();
        $response->assertJson(['icon' => null]);
    }

    // =========================================================================
    // GET /api/v1/passwords/breach  (PasswordBreachController@range)
    // =========================================================================

    public function test_breach_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/passwords/breach');

        $response->assertUnauthorized();
    }

    public function test_breach_wrong_ability_returns_403(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/passwords/breach', $this->bearerWithAbility($user, 'read-only'));

        $response->assertForbidden();
    }

    public function test_breach_missing_prefix_returns_422(): void
    {
        // No 'prefix' param → empty string fails /^[0-9A-F]{5}$/ → abort(422) (no egress).
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/passwords/breach', $this->bearer($user));

        $response->assertUnprocessable();
    }

    public function test_breach_invalid_prefix_returns_422(): void
    {
        // Too short / invalid chars → abort(422) (no egress).
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/passwords/breach?prefix=XYZ', $this->bearer($user));

        $response->assertUnprocessable();
    }

    // =========================================================================
    // GET /api/v1/passwords/tfa-directory  (TwoFactorDirectoryController@index)
    // =========================================================================

    public function test_tfa_directory_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/passwords/tfa-directory');

        $response->assertUnauthorized();
    }

    public function test_tfa_directory_wrong_ability_returns_403(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/passwords/tfa-directory', $this->bearerWithAbility($user, 'read-only'));

        $response->assertForbidden();
    }

    public function test_tfa_directory_returns_entries_shape(): void
    {
        // The controller wraps its result in a 24h server-side cache. On first call
        // in test the upstream fetch fails (no network) → Cache::remember returns []
        // → {entries: {}} is still a valid 200 with the expected JSON key.
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/passwords/tfa-directory', $this->bearer($user));

        $response->assertOk();
        $response->assertJsonStructure(['entries']);
    }
}
