<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\GalleryBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/v1/me exposes the combined storage limit (usage.quota, bytes) so apps
 * can render a used/limit ring. Null when unlimited.
 */
class MeUsageQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('phone', ['device'])->plainTextToken;
    }

    public function test_quota_is_null_when_unlimited(): void
    {
        config(['files.quota_mb' => 0, 'gallery.quota_mb' => 0]);
        $user = User::factory()->create();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJsonStructure(['usage' => ['files', 'gallery', 'quota']])
            ->assertJson(['usage' => ['quota' => null]]);
    }

    public function test_quota_is_the_combined_files_and_gallery_limit_in_bytes(): void
    {
        config(['files.quota_mb' => 0, 'gallery.quota_mb' => 0]);
        $user = User::factory()->create();
        // Per-user overrides: 30 GB files + 20 GB gallery = 50 GB combined.
        $user->forceFill(['files_quota_mb' => 30_000, 'gallery_quota_mb' => 20_000])->save();

        FileBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 4096, 'created_at' => now()]);
        GalleryBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 2048, 'created_at' => now()]);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJson(['usage' => [
                'files' => 4096,
                'gallery' => 2048,
                'quota' => 50_000 * 1024 * 1024,
            ]]);
    }

    public function test_quota_is_null_when_only_one_dimension_is_capped(): void
    {
        // Files capped, gallery unlimited → the pool has no finite cap.
        config(['files.quota_mb' => 0, 'gallery.quota_mb' => 0]);
        $user = User::factory()->create();
        $user->forceFill(['files_quota_mb' => 30_000])->save();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJson(['usage' => ['quota' => null]]);
    }

    public function test_workspace_default_quota_applies_without_a_per_user_override(): void
    {
        config(['files.quota_mb' => 10_000, 'gallery.quota_mb' => 10_000]);
        $user = User::factory()->create();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJson(['usage' => ['quota' => 20_000 * 1024 * 1024]]);
    }
}
