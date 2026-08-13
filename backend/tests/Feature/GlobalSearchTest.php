<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\GalleryPhoto;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Global search across modules (owner-scoped, module-gated), gallery search matching
 * photo OCR text, and the queued reindex triggers (me + admin).
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function seed(User $user): void
    {
        FileEntry::forceCreate(['user_id' => $user->id, 'name' => 'quarterly-report.pdf', 'storage_path' => 'files/'.Str::uuid(),
            'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('0', 64), 'version' => 0, 'search_text' => 'revenue and profit report']);
        Note::forceCreate(['user_id' => $user->id, 'title' => 'Report meeting', 'body' => 'discuss the report', 'version' => 0]);
        GalleryPhoto::forceCreate(['user_id' => $user->id, 'name' => 'scan.jpg', 'storage_path' => 'gallery/'.Str::uuid(),
            'mime' => 'image/jpeg', 'size' => 10, 'sha256' => str_repeat('1', 64), 'version' => 0, 'media_type' => 'image', 'status' => 'ready',
            'ocr_text' => 'annual report summary']);
    }

    public function test_global_search_groups_owner_scoped_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->seed($user);
        // Another user's identical data must not surface.
        $this->seed(User::factory()->create());

        $res = $this->getJson(route('search', ['q' => 'report']))->assertOk();
        $modules = collect($res->json('groups'))->pluck('module')->all();
        $this->assertContains('files', $modules);
        $this->assertContains('notes', $modules);
        $this->assertContains('gallery', $modules); // matched via ocr_text
        // Each group has exactly the one owner's hit.
        foreach ($res->json('groups') as $g) {
            $this->assertCount(1, $g['items']);
        }
    }

    public function test_global_search_is_module_gated(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['modules' => ['files']])->save(); // only Files enabled
        $this->actingAs($user);
        $this->seed($user);

        $modules = collect($this->getJson(route('search', ['q' => 'report']))->json('groups'))->pluck('module')->all();
        $this->assertContains('files', $modules);
        $this->assertNotContains('notes', $modules);
        $this->assertNotContains('gallery', $modules);
    }

    public function test_gallery_search_matches_ocr_text(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->seed($user);

        $this->getJson(route('gallery.search', ['q' => 'annual']))
            ->assertOk()->assertJsonCount(1, 'photos')->assertJsonPath('photos.0.name', 'scan.jpg');
    }

    public function test_reindex_me_queues_and_admin_is_gated(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->postJson(route('me.reindex'))->assertOk()->assertJsonPath('queued', true);

        // Non-admin cannot trigger the all-users reindex.
        $this->postJson(route('admin.reindex'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin);
        $this->postJson(route('admin.reindex'))->assertOk()->assertJsonPath('queued', true);
    }
}
