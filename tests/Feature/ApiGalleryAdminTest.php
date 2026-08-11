<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DetectGalleryFaces;
use App\Models\AppSettings;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiGalleryAdminTest extends TestCase
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
        $this->getJson('/api/v1/admin/gallery')->assertUnauthorized();
        $this->getJson('/api/v1/admin/gallery', $this->bearer(User::factory()->create()))->assertForbidden();
    }

    public function test_show_reports_settings_status_and_operator(): void
    {
        $res = $this->getJson('/api/v1/admin/gallery', $this->bearer($this->admin()))->assertOk();
        $res->assertJsonStructure([
            'settings' => ['ml_enabled', 'ml_face_enabled', 'ml_url'],
            'effective' => ['enabled', 'face_enabled', 'url', 'vector'],
            'status' => ['sidecar', 'queue' => ['pending', 'failed'], 'counts' => ['photos', 'faces', 'people']],
            'operator' => ['restart', 'update', 'logs'],
        ]);
    }

    public function test_update_persists_and_overrides_config(): void
    {
        $admin = $this->admin();
        $this->putJson('/api/v1/admin/gallery', [
            'ml_enabled' => true, 'ml_face_enabled' => false,
            'ml_clip_model' => 'ViT-L-14__openai', 'ml_search_distance' => 0.5,
        ], $this->bearer($admin))->assertOk();

        $s = AppSettings::current();
        $this->assertTrue((bool) $s->ml_enabled);
        $this->assertSame('ViT-L-14__openai', $s->ml_clip_model);
        $this->assertEqualsWithDelta(0.5, (float) $s->ml_search_distance, 0.001);

        // Overlay is busted + reapplied → config reflects the DB value.
        Cache::forget(AppServiceProvider::ML_CACHE_KEY);
        (new AppServiceProvider(app()))->boot();
        $this->assertSame('ViT-L-14__openai', config('ml.clip_model'));
    }

    public function test_reprocess_queues_site_wide_jobs(): void
    {
        Queue::fake();
        $admin = $this->admin();
        GalleryPhoto::query()->forceCreate([
            'user_id' => User::factory()->create()->id, 'storage_path' => 'gallery/x',
            'name' => 'p.jpg', 'media_type' => 'image', 'status' => 'ready', 'size' => 1, 'version' => 1,
        ]);
        $this->postJson('/api/v1/admin/gallery/reprocess', ['scope' => 'faces'], $this->bearer($admin))
            ->assertOk()->assertJsonPath('scope', 'faces');
        Queue::assertPushed(DetectGalleryFaces::class);
    }

    public function test_clear_queue_works_for_admin(): void
    {
        $this->postJson('/api/v1/admin/gallery/queue/clear', [], $this->bearer($this->admin()))->assertOk();
    }

    public function test_clear_queue_forbidden_for_non_admin(): void
    {
        $this->postJson('/api/v1/admin/gallery/queue/clear', [], $this->bearer(User::factory()->create()))->assertForbidden();
    }
}
