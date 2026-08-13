<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Public (unauthenticated) /api/v1/file-share/{token} consumption. Mirrors the web
 * public share but is tokenless — a password-gated share hands back a stateless HMAC
 * grant on unlock that the client carries on manifest/raw (no session).
 */
class ApiPublicShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    /** @return array{share: FileShare, file: FileEntry} */
    private function seedShare(?string $password): array
    {
        $owner = User::factory()->create();
        $root = new FileFolder;
        $root->forceFill(['user_id' => $owner->id, 'name' => 'Shared', 'parent_id' => null])->save();

        $path = 'files/'.Str::uuid()->toString();
        Storage::disk(config('files.disk'))->put($path, 'alpha bytes');
        $file = new FileEntry;
        $file->forceFill([
            'user_id' => $owner->id,
            'file_folder_id' => $root->id,
            'name' => 'a.txt',
            'mime' => 'text/plain',
            'size' => 11,
            'storage_path' => $path,
            'sha256' => hash('sha256', 'alpha bytes'),
        ])->save();

        $share = new FileShare;
        $share->forceFill([
            'user_id' => $owner->id,
            'token' => Str::random(48),
            'kind' => 'folder',
            'file_folder_id' => $root->id,
            'password_hash' => $password !== null ? Hash::make($password) : null,
            'allow_download' => true,
        ])->save();

        return ['share' => $share, 'file' => $file];
    }

    public function test_meta_reports_locked_then_unlock_yields_a_grant_gating_manifest_and_raw(): void
    {
        ['share' => $share, 'file' => $file] = $this->seedShare('secret');
        $t = $share->token;

        $this->getJson("/api/v1/file-share/{$t}")
            ->assertOk()
            ->assertJson(['found' => true, 'needsPassword' => true, 'unlocked' => false]);

        // Wrong password → 422, no grant.
        $this->postJson("/api/v1/file-share/{$t}/unlock", ['password' => 'nope'])->assertStatus(422);

        // Without a grant, manifest + raw are locked (no session on the API).
        $this->getJson("/api/v1/file-share/{$t}/manifest")->assertStatus(403);
        $this->getJson("/api/v1/file-share/{$t}/file/{$file->id}/raw")->assertStatus(403);

        // Correct password → grant.
        $grant = $this->postJson("/api/v1/file-share/{$t}/unlock", ['password' => 'secret'])
            ->assertOk()->assertJsonPath('ok', true)->json('grant');
        $this->assertIsString($grant);

        // Grant via header unlocks manifest.
        $this->getJson("/api/v1/file-share/{$t}/manifest", ['X-Share-Grant' => $grant])
            ->assertOk()
            ->assertJsonPath('name', 'Shared')
            ->assertJsonCount(1, 'files');

        // Grant via query unlocks raw bytes.
        $this->get("/api/v1/file-share/{$t}/file/{$file->id}/raw?grant=".urlencode((string) $grant))
            ->assertOk();
    }

    public function test_a_grant_for_one_share_cannot_unlock_another(): void
    {
        ['share' => $a] = $this->seedShare('secret');
        ['share' => $b, 'file' => $bFile] = $this->seedShare('secret');

        $grant = $this->postJson("/api/v1/file-share/{$a->token}/unlock", ['password' => 'secret'])
            ->assertOk()->json('grant');

        // Replaying A's grant against B's manifest/raw is rejected.
        $this->getJson("/api/v1/file-share/{$b->token}/manifest", ['X-Share-Grant' => $grant])->assertStatus(403);
        $this->getJson("/api/v1/file-share/{$b->token}/file/{$bFile->id}/raw", ['X-Share-Grant' => $grant])->assertStatus(403);
    }

    public function test_password_free_share_needs_no_grant(): void
    {
        ['share' => $share, 'file' => $file] = $this->seedShare(null);
        $t = $share->token;

        $this->getJson("/api/v1/file-share/{$t}")->assertOk()->assertJson(['needsPassword' => false, 'unlocked' => true]);
        $this->getJson("/api/v1/file-share/{$t}/manifest")->assertOk()->assertJsonCount(1, 'files');
        $this->get("/api/v1/file-share/{$t}/file/{$file->id}/raw")->assertOk();
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->getJson('/api/v1/file-share/'.Str::random(48))->assertStatus(404);
    }
}
