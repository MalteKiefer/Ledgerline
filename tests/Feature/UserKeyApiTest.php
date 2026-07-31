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
            'public_key' => 'pubkey-abc',
            'wrapped_secret_key' => 'wrapped-sk-abc',
            'fingerprint' => 'fp-abc',
            'mlkem_public_key' => 'mlkem-ek-abc',
            'wrapped_mlkem_secret_key' => 'wrapped-mlkem-abc',
        ]);

        $response->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('pubkey-abc', $fresh->x25519_public_key);
        $this->assertSame('wrapped-sk-abc', $fresh->wrapped_x25519_secret_key);
        $this->assertSame('fp-abc', $fresh->public_key_fingerprint);
        $this->assertSame('mlkem-ek-abc', $fresh->mlkem_public_key);
        $this->assertSame('wrapped-mlkem-abc', $fresh->wrapped_mlkem_secret_key);
    }

    public function test_republishing_same_public_key_is_idempotent(): void
    {
        $user = $this->signIn();

        $user->forceFill([
            'x25519_public_key' => 'pubkey-abc',
            'wrapped_x25519_secret_key' => 'wrapped-sk-abc',
            'public_key_fingerprint' => 'fp-abc',
        ])->save();

        $response = $this->putJson(route('user.keys.store'), [
            'public_key' => 'pubkey-abc',
            'wrapped_secret_key' => 'wrapped-sk-abc-new',  // wrapped key may differ on re-wrap
            'fingerprint' => 'fp-abc',
            'mlkem_public_key' => 'mlkem-ek-abc',
            'wrapped_mlkem_secret_key' => 'wrapped-mlkem-abc',
        ]);

        // Same public_key → idempotent OK.
        $response->assertOk();
    }

    public function test_publishing_different_public_key_returns_409(): void
    {
        $user = $this->signIn();

        $user->forceFill([
            'x25519_public_key' => 'pubkey-original',
            'wrapped_x25519_secret_key' => 'wrapped-sk',
            'public_key_fingerprint' => 'fp-original',
        ])->save();

        $response = $this->putJson(route('user.keys.store'), [
            'public_key' => 'pubkey-different',
            'wrapped_secret_key' => 'wrapped-sk-different',
            'fingerprint' => 'fp-different',
            'mlkem_public_key' => 'mlkem-ek-different',
            'wrapped_mlkem_secret_key' => 'wrapped-mlkem-different',
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
            'public_key' => 'pubkey-abc',
            'wrapped_secret_key' => 'wrapped-sk-abc',
            'fingerprint' => 'fp-abc',
        ]);

        // 302 redirect to login page.
        $response->assertRedirect();
    }

    public function test_publishing_key_requires_all_fields(): void
    {
        $this->signIn();

        // Web routes use shouldRenderJsonWhen(api/*) so validation failures on
        // web routes redirect back (302) rather than returning 422. Assert
        // redirect specifically to lock down the rejection behaviour.
        $response = $this->put(route('user.keys.store'), []);

        $response->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // GET /vaults/keys — owner-scoping: never leaks another user's key material
    // -------------------------------------------------------------------------

    public function test_get_keys_returns_own_published_key_material(): void
    {
        $user = $this->signIn();
        $user->forceFill([
            'x25519_public_key' => 'pubkey-user1',
            'wrapped_x25519_secret_key' => 'wrapped-sk-user1',
            'public_key_fingerprint' => 'fp-user1',
        ])->save();

        $response = $this->getJson(route('user.keys.show'));

        $response->assertOk()
            ->assertJson([
                'public_key' => 'pubkey-user1',
                'wrapped_secret_key' => 'wrapped-sk-user1',
                'fingerprint' => 'fp-user1',
            ]);
    }

    public function test_get_keys_never_returns_another_users_key_material(): void
    {
        // Second user has published keys.
        $other = User::factory()->create();
        $other->forceFill([
            'x25519_public_key' => 'pubkey-other',
            'wrapped_x25519_secret_key' => 'wrapped-sk-other',
            'public_key_fingerprint' => 'fp-other',
        ])->save();

        // First user has their own keys.
        $user = $this->signIn();
        $user->forceFill([
            'x25519_public_key' => 'pubkey-user1',
            'wrapped_x25519_secret_key' => 'wrapped-sk-user1',
            'public_key_fingerprint' => 'fp-user1',
        ])->save();

        $response = $this->getJson(route('user.keys.show'));

        $response->assertOk();

        // Own keys are returned.
        $response->assertJson([
            'public_key' => 'pubkey-user1',
            'wrapped_secret_key' => 'wrapped-sk-user1',
            'fingerprint' => 'fp-user1',
        ]);

        // Other user's key material must not appear anywhere in the response.
        $body = $response->getContent();
        $this->assertStringNotContainsString('pubkey-other', $body);
        $this->assertStringNotContainsString('wrapped-sk-other', $body);
        $this->assertStringNotContainsString('fp-other', $body);
    }

    public function test_get_keys_returns_nulls_when_no_identity_published(): void
    {
        // Second user has keys — must never bleed through to a different user.
        $other = User::factory()->create();
        $other->forceFill([
            'x25519_public_key' => 'pubkey-other',
            'wrapped_x25519_secret_key' => 'wrapped-sk-other',
            'public_key_fingerprint' => 'fp-other',
        ])->save();

        // Authenticated user has no published identity.
        $this->signIn();

        $response = $this->getJson(route('user.keys.show'));

        $response->assertOk()
            ->assertJson([
                'public_key' => null,
                'wrapped_secret_key' => null,
                'fingerprint' => null,
            ]);

        // Other user's key material must not appear.
        $body = $response->getContent();
        $this->assertStringNotContainsString('pubkey-other', $body);
        $this->assertStringNotContainsString('wrapped-sk-other', $body);
    }
}
