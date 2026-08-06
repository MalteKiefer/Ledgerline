<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\FileBlob;
use App\Models\PublicShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * API: stateless public-share consumption  /api/v1/s/{token}/*
 *
 * These routes are PUBLIC (no auth:sanctum, no session) — the only credential
 * is the share token (URL) + an optional password → HMAC grant.
 */
class PublicShareApiTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────── helpers ──────

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeShare(User $owner, array $attrs = []): PublicShare
    {
        return PublicShare::create(array_merge([
            'token' => Str::random(22),
            'user_id' => $owner->id,
            'kind' => 'file',
            'sealed_manifest' => 'enc_manifest_placeholder',
            'blob_refs' => [],
            'allow_download' => true,
            'password_hash' => null,
            'expires_at' => null,
        ], $attrs));
    }

    /** Create a real file blob record + fake disk file, returns the blob UUID. */
    private function makeFileBlob(User $owner, PublicShare $share): string
    {
        Storage::fake(config('files.disk'));
        $ref = (string) Str::uuid();

        FileBlob::create([
            'blob' => $ref,
            'user_id' => $owner->id,
            'size' => 16,
        ]);

        Storage::disk(config('files.disk'))->put('files/'.$ref, 'ciphertext_bytes');

        $share->forceFill(['blob_refs' => [$ref]])->saveQuietly();

        return $ref;
    }

    // ─────────────────────────────────────────── meta ────────────────────

    public function test_meta_returns_share_info_for_public_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $resp = $this->getJson("/api/v1/s/{$share->token}/meta");

        $resp->assertOk()->assertJson([
            'found' => true,
            'expired' => false,
            'kind' => 'file',
            'needs_password' => false,
            'allow_download' => true,
        ]);
    }

    public function test_meta_returns_404_for_unknown_token(): void
    {
        $this->getJson('/api/v1/s/doesnotexist99/meta')->assertNotFound();
    }

    public function test_meta_returns_410_for_expired_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['expires_at' => now()->subDay()]);

        $this->getJson("/api/v1/s/{$share->token}/meta")
            ->assertStatus(410)
            ->assertJson(['found' => true, 'expired' => true]);
    }

    public function test_meta_indicates_password_required(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['password_hash' => Hash::make('s3cret')]);

        $this->getJson("/api/v1/s/{$share->token}/meta")
            ->assertOk()
            ->assertJson(['needs_password' => true]);
    }

    // ─────────────────────────────────────────── manifest (no password) ──

    public function test_manifest_streams_sealed_ciphertext_for_public_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['sealed_manifest' => 'my_sealed_blob']);

        $this->getJson("/api/v1/s/{$share->token}/manifest")
            ->assertOk()
            ->assertJson([
                'sealed' => 'my_sealed_blob',
                'allow_download' => true,
            ]);
    }

    public function test_manifest_increments_view_count(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $this->getJson("/api/v1/s/{$share->token}/manifest")->assertOk();

        $this->assertSame(1, $share->fresh()->views);
    }

    public function test_manifest_returns_404_for_expired_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['expires_at' => now()->subDay()]);

        $this->getJson("/api/v1/s/{$share->token}/manifest")->assertNotFound();
    }

    // ─────────────────────────────────────────── blob (no password) ──────

    public function test_blob_streams_bytes_for_valid_ref(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);
        $ref = $this->makeFileBlob($owner, $share);

        $resp = $this->get("/api/v1/s/{$share->token}/blob/{$ref}");

        $resp->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        // Cache-Control directives are present; order varies by framework version.
        $this->assertStringContainsString('immutable', $resp->headers->get('Cache-Control', ''));
        $this->assertStringContainsString('max-age=31536000', $resp->headers->get('Cache-Control', ''));
    }

    public function test_blob_returns_404_for_ref_not_in_blob_refs(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);
        $stray = (string) Str::uuid(); // valid UUID but not in blob_refs

        $this->get("/api/v1/s/{$share->token}/blob/{$stray}")->assertNotFound();
    }

    public function test_blob_returns_404_for_ref_owned_by_different_user(): void
    {
        Storage::fake(config('files.disk'));
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $share = $this->makeShare($owner);
        $ref = (string) Str::uuid();

        // Blob exists but belongs to $other, NOT $owner
        FileBlob::create(['blob' => $ref, 'user_id' => $other->id, 'size' => 8]);
        Storage::disk(config('files.disk'))->put('files/'.$ref, 'secret');
        $share->forceFill(['blob_refs' => [$ref]])->saveQuietly();

        $this->get("/api/v1/s/{$share->token}/blob/{$ref}")->assertNotFound();
    }

    public function test_blob_returns_403_when_download_disabled(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['allow_download' => false]);
        $ref = $this->makeFileBlob($owner, $share);

        $this->get("/api/v1/s/{$share->token}/blob/{$ref}")->assertForbidden();
    }

    // ─────────────────────────────────────────── password-protected flow ─

    public function test_unlock_rejects_wrong_password(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['password_hash' => Hash::make('correct')]);

        $this->postJson("/api/v1/s/{$share->token}/unlock", ['password' => 'wrong'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_unlock_issues_grant_for_correct_password(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['password_hash' => Hash::make('correct')]);

        $resp = $this->postJson("/api/v1/s/{$share->token}/unlock", ['password' => 'correct']);

        $resp->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['grant']);

        $this->assertNotEmpty($resp->json('grant'));
    }

    public function test_manifest_requires_grant_for_password_protected_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['password_hash' => Hash::make('x')]);

        // Without grant → 403
        $this->getJson("/api/v1/s/{$share->token}/manifest")->assertForbidden();
    }

    public function test_manifest_accepts_valid_grant(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['password_hash' => Hash::make('x')]);

        $grant = $this->postJson("/api/v1/s/{$share->token}/unlock", ['password' => 'x'])
            ->assertOk()
            ->json('grant');

        $this->getJson("/api/v1/s/{$share->token}/manifest", ['X-Share-Grant' => $grant])
            ->assertOk()
            ->assertJsonStructure(['sealed']);
    }

    public function test_blob_accepts_grant_via_query_param(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['password_hash' => Hash::make('x')]);
        $ref = $this->makeFileBlob($owner, $share);

        $grant = $this->postJson("/api/v1/s/{$share->token}/unlock", ['password' => 'x'])
            ->json('grant');

        $this->get("/api/v1/s/{$share->token}/blob/{$ref}?grant={$grant}")->assertOk();
    }

    public function test_grant_cannot_be_used_for_different_share(): void
    {
        $owner = $this->makeUser();
        $shareA = $this->makeShare($owner, ['password_hash' => Hash::make('x')]);
        $shareB = $this->makeShare($owner, ['password_hash' => Hash::make('y')]);

        $grantA = $this->postJson("/api/v1/s/{$shareA->token}/unlock", ['password' => 'x'])
            ->json('grant');

        // Grant for A must NOT work for B
        $this->getJson("/api/v1/s/{$shareB->token}/manifest", ['X-Share-Grant' => $grantA])
            ->assertForbidden();
    }

    public function test_forged_grant_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['password_hash' => Hash::make('x')]);

        $forged = base64_encode(json_encode(['share_id' => $share->id, 'expires' => time() + 3600])).'.invalidsig';

        $this->getJson("/api/v1/s/{$share->token}/manifest", ['X-Share-Grant' => $forged])
            ->assertForbidden();
    }

    public function test_unlock_returns_404_for_expired_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, [
            'password_hash' => Hash::make('x'),
            'expires_at' => now()->subHour(),
        ]);

        $this->postJson("/api/v1/s/{$share->token}/unlock", ['password' => 'x'])
            ->assertNotFound();
    }

    // ──────────────────────────── throttle is wired (smoke, not timing) ──

    public function test_unlock_throttle_header_present(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['password_hash' => Hash::make('x')]);

        $resp = $this->postJson("/api/v1/s/{$share->token}/unlock", ['password' => 'x']);

        // X-RateLimit-Limit header confirms the throttle middleware is active
        $resp->assertHeader('X-RateLimit-Limit');
    }
}
