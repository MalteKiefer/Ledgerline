<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/v1/me exposes the storage limit (usage.quota, bytes) so apps can render
 * a used/limit ring. Quota is the files quota (the gallery module was removed);
 * null when unlimited.
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
        config(['files.quota_mb' => 0]);
        $user = User::factory()->create();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJsonStructure(['usage' => ['files', 'quota']])
            ->assertJson(['usage' => ['quota' => null]]);
    }

    public function test_usage_reports_the_files_bytes_and_quota(): void
    {
        config(['files.quota_mb' => 0]);
        $user = User::factory()->create();
        // Per-user override: 30 GB files quota.
        $user->forceFill(['files_quota_mb' => 30_000])->save();

        FileBlob::create(['blob' => (string) Str::uuid(), 'user_id' => $user->id, 'size' => 4096, 'created_at' => now()]);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJson(['usage' => [
                'files' => 4096,
                'quota' => 30_000 * 1024 * 1024,
            ]]);
    }

    public function test_workspace_default_quota_applies_without_a_per_user_override(): void
    {
        config(['files.quota_mb' => 10_000]);
        $user = User::factory()->create();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$this->token($user)])
            ->assertOk()
            ->assertJson(['usage' => ['quota' => 10_000 * 1024 * 1024]]);
    }
}
