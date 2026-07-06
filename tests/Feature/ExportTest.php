<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\BuildExport;
use App\Models\AppSettings;
use App\Models\Export;
use App\Models\FileFolder;
use App\Models\Photo;
use App\Models\StoredFile;
use App\Services\Export\ExportArchiver;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private function runBuild(Export $export): void
    {
        (new BuildExport($export->id))->handle(app(ExportArchiver::class), app(ChannelNotifier::class));
    }

    public function test_gallery_export_endpoint_queues_a_job(): void
    {
        Queue::fake();
        $this->signIn();
        $photo = Photo::factory()->create();

        $this->postJson(route('gallery.export'), ['photo_ids' => [$photo->id], 'variant' => 'original'])
            ->assertStatus(202)
            ->assertJson(['queued' => true]);

        $this->assertDatabaseHas('exports', ['source' => 'gallery', 'status' => 'queued', 'variant' => 'original']);
        Queue::assertPushed(BuildExport::class);
    }

    public function test_files_export_endpoint_queues_a_job(): void
    {
        Queue::fake();
        $this->signIn();
        $file = StoredFile::create(['id' => (string) Str::uuid(), 'name' => 'a.txt', 'blob' => (string) Str::uuid(), 'size' => 3]);

        $this->postJson(route('files.export'), ['file_ids' => [$file->id]])
            ->assertStatus(202)
            ->assertJson(['queued' => true]);

        $this->assertDatabaseHas('exports', ['source' => 'files', 'status' => 'queued']);
        Queue::assertPushed(BuildExport::class);
    }

    public function test_build_produces_a_ready_zip_and_a_notification(): void
    {
        Storage::fake('files');
        $user = $this->signIn();
        AppSettings::current()->update(['export_notify_desktop' => true]);

        $photo = Photo::factory()->create(['disk_path' => 'photos/a.jpg', 'name' => 'a.jpg', 'size' => 3]);
        Storage::disk('files')->put('photos/a.jpg', 'AAA');

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'gallery', 'variant' => 'original',
            'title' => '1 photo', 'status' => 'queued', 'item_count' => 1,
            'payload' => ['photo_ids' => [$photo->id]],
        ]);

        $this->runBuild($export);
        $export->refresh();

        $this->assertSame('ready', $export->status);
        $this->assertSame(1, $export->part_count);
        $this->assertNotNull($export->expires_at);
        Storage::disk('files')->assertExists($export->parts()[0]['path']);
        $this->assertDatabaseHas('app_notifications', ['category' => 'export', 'level' => 'success']);
    }

    public function test_build_splits_into_parts_over_the_max_size(): void
    {
        Storage::fake('files');
        $user = $this->signIn();
        // 1 MB cap; two ~0.7 MB items must land in separate parts.
        AppSettings::current()->update(['export_gallery_max_zip_mb' => 1]);

        $ids = [];
        foreach (['a', 'b'] as $n) {
            $photo = Photo::factory()->create(['disk_path' => "photos/{$n}.jpg", 'name' => "{$n}.jpg", 'size' => 700000]);
            Storage::disk('files')->put("photos/{$n}.jpg", str_repeat('x', 10));
            $ids[] = $photo->id;
        }

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'gallery', 'variant' => 'original',
            'title' => '2 photos', 'status' => 'queued', 'item_count' => 2,
            'payload' => ['photo_ids' => $ids],
        ]);

        $this->runBuild($export);
        $export->refresh();

        $this->assertSame('ready', $export->status);
        $this->assertSame(2, $export->part_count);
    }

    public function test_build_includes_a_folder_tree_for_files(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $folder = FileFolder::create(['id' => (string) Str::uuid(), 'name' => 'Docs']);
        $file = StoredFile::create(['id' => (string) Str::uuid(), 'file_folder_id' => $folder->id, 'name' => 'note.txt', 'blob' => (string) Str::uuid(), 'size' => 3]);
        Storage::disk('files')->put('files/'.$file->blob, 'abc');

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'files', 'title' => '1 item', 'status' => 'queued',
            'item_count' => 1, 'payload' => ['file_ids' => [], 'folder_ids' => [$folder->id]],
        ]);

        $this->runBuild($export);
        $export->refresh();

        $this->assertSame('ready', $export->status);
        $this->assertSame(1, $export->part_count);
        Storage::disk('files')->assertExists($export->parts()[0]['path']);
    }

    public function test_downloads_page_lists_and_serves_and_deletes(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'gallery', 'variant' => 'original',
            'title' => 'x', 'status' => 'ready', 'item_count' => 1, 'part_count' => 1,
            'total_size' => 3, 'files' => [['name' => 'x.zip', 'path' => 'exports/1/1/part-1.zip', 'size' => 3]],
            'expires_at' => now()->addDays(7),
        ]);
        Storage::disk('files')->put('exports/1/1/part-1.zip', 'ZIP');

        $this->getJson(route('downloads.data'))
            ->assertOk()
            ->assertJsonPath('exports.0.id', $export->id)
            ->assertJsonPath('exports.0.status', 'ready');

        $this->get(route('downloads.part', ['export' => $export->id, 'index' => 0]))->assertOk();

        $this->deleteJson(route('downloads.destroy'), ['ids' => [$export->id]])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
        Storage::disk('files')->assertMissing('exports/1/1/part-1.zip');
    }

    public function test_a_user_cannot_download_another_users_export(): void
    {
        Storage::fake('files');
        $this->signIn();
        $other = Export::create([
            'user_id' => 99999, 'source' => 'gallery', 'title' => 'x', 'status' => 'ready',
            'files' => [['name' => 'x.zip', 'path' => 'exports/9/9/part-1.zip', 'size' => 1]],
            'expires_at' => now()->addDay(),
        ]);

        // Another user's export is invisible (global owner scope → 404), not
        // merely forbidden.
        $this->get(route('downloads.part', ['export' => $other->id, 'index' => 0]))->assertNotFound();
    }

    public function test_unseen_ready_exports_badge_and_clearing(): void
    {
        $user = $this->signIn();

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'files', 'title' => 'x', 'status' => 'ready',
            'files' => [], 'expires_at' => now()->addDay(),
        ]);
        Export::create([
            'user_id' => $user->id, 'source' => 'files', 'title' => 'building', 'status' => 'processing',
        ]);
        Export::create([
            'user_id' => 99999, 'source' => 'files', 'title' => 'foreign', 'status' => 'ready',
            'expires_at' => now()->addDay(),
        ]);

        // Only the own, ready, unseen export counts.
        $this->assertSame(1, Export::unseenReadyCount($user->id));

        // Visiting the Downloads page marks it seen and clears the badge.
        $this->get(route('downloads.index'))->assertOk();
        $this->assertNotNull($export->fresh()->seen_at);
        $this->assertSame(0, Export::unseenReadyCount($user->id));

        // The foreign user's badge is untouched.
        $this->assertSame(1, Export::unseenReadyCount(99999));
    }

    public function test_nav_shows_downloads_badge_for_unseen_ready_export(): void
    {
        $user = $this->signIn();
        Export::create([
            'user_id' => $user->id, 'source' => 'files', 'title' => 'x', 'status' => 'ready',
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee(__('downloads.new_ready'));
    }

    public function test_prune_removes_expired_exports_and_their_files(): void
    {
        Storage::fake('files');
        $user = $this->signIn();

        $export = Export::create([
            'user_id' => $user->id, 'source' => 'gallery', 'title' => 'old', 'status' => 'ready',
            'files' => [['name' => 'x.zip', 'path' => 'exports/1/1/part-1.zip', 'size' => 3]],
            'expires_at' => now()->subDay(),
        ]);
        Storage::disk('files')->put('exports/1/1/part-1.zip', 'ZIP');

        $this->artisan('exports:prune')->assertOk();

        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
        Storage::disk('files')->assertMissing('exports/1/1/part-1.zip');
    }
}
