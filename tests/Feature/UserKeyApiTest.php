<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP-level tests for PUT /vaults/keys (identity keypair publish).
 *
 * These are web routes protected by the `auth` middleware (session guard).
 * Uses actingAs() — no token required.
 */
class UserKeyApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // PUT /vaults/keys — publish identity keypair
    // -------------------------------------------------------------------------

    public function test_user_can_publish_their_x25519_keypair(): void
    {
        $user = $this->signIn();

        $response = $this->putJson(route('user.keys.store'), [
            'public_key'         => 'pubkey-abc',
            'wrapped_secret_key' => 'wrapped-sk-abc',
            'fingerprint'        => 'fp-abc',
        ]);

        $response->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('pubkey-abc', $fresh->x25519_public_key);
        $this->assertSame('wrapped-sk-abc', $fresh->wrapped_x25519_secret_key);
        $this->assertSame('fp-abc', $fresh->public_key_fingerprint);
    }

    public function test_republishing_same_public_key_is_idempotent(): void
    {
        $user = $this->signIn();

        $user->forceFill([
            'x25519_public_key'         => 'pubkey-abc',
            'wrapped_x25519_secret_key' => 'wrapped-sk-abc',
            'public_key_fingerprint'    => 'fp-abc',
        ])->save();

        $response = $this->putJson(route('user.keys.store'), [
            'public_key'         => 'pubkey-abc',
            'wrapped_secret_key' => 'wrapped-sk-abc-new',  // wrapped key may differ on re-wrap
            'fingerprint'        => 'fp-abc',
        ]);

        // Same public_key → idempotent OK.
        $response->assertOk();
    }

    public function test_publishing_different_public_key_returns_409(): void
    {
        $user = $this->signIn();

        $user->forceFill([
            'x25519_public_key'         => 'pubkey-original',
            'wrapped_x25519_secret_key' => 'wrapped-sk',
            'public_key_fingerprint'    => 'fp-original',
        ])->save();

        $response = $this->putJson(route('user.keys.store'), [
            'public_key'         => 'pubkey-different',
            'wrapped_secret_key' => 'wrapped-sk-different',
            'fingerprint'        => 'fp-different',
        ]);

        $response->assertStatus(409);

        // Original key must not be overwritten.
        $fresh = $user->fresh();
        $this->assertSame('pubkey-original', $fresh->x25519_public_key);
    }

    public function test_publishing_key_requires_authentication(): void
    {
        // Web routes redirect unauthenticated requests to login (302) rather
        // than returning 401 — this is the standard Laravel web middleware
        // behaviour even when the request has Accept: application/json.
        $response = $this->putJson(route('user.keys.store'), [
            'public_key'         => 'pubkey-abc',
            'wrapped_secret_key' => 'wrapped-sk-abc',
            'fingerprint'        => 'fp-abc',
        ]);

        // 302 redirect to login page.
        $response->assertRedirect();
    }

    public function test_publishing_key_requires_all_fields(): void
    {
        $this->signIn();

        // Web routes with missing fields redirect back with flashed errors (302)
        // rather than returning 422 JSON — the exception handler is configured
        // to render JSON only for api/* paths. Assert the request is rejected.
        $response = $this->putJson(route('user.keys.store'), []);

        // Redirect back with validation errors (not a successful 2xx).
        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(500, $response->getStatusCode());
    }
}
