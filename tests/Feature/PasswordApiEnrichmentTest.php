<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanctum bearer-token (device ability) tests for the site-icon proxy endpoint
 * mirrored to /api/v1 for mobile parity (retained for the Finance module; the
 * password manager that first used it — and its breach/2fa-directory helpers —
 * has been removed).
 *
 * Exercises the same controller used by the web route (PasswordIconController)
 * via Sanctum bearer auth, confirming it is guard-agnostic.
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
}
