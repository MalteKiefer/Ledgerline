<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateFileVideoThumbnail;
use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FileLabel;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilesRelationalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    public function test_whole_upload_stores_row_and_bytes(): void
    {
        $this->actingAs(User::factory()->create());

        $res = $this->post(route('files.rel.upload'), [
            'file' => UploadedFile::fake()->createWithContent('hello.txt', 'hello world'),
        ])->assertCreated();

        $id = (int) $res->json('file.id');
        $file = FileEntry::findOrFail($id);
        $this->assertSame(11, $file->size);
        $this->assertSame(hash('sha256', 'hello world'), $file->sha256);
        Storage::disk(config('files.disk'))->assertExists($file->storage_path);
    }

    public function test_activity_feed_records_upload_rename_and_trash(): void
    {
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('files.rel.upload'), [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'x'),
        ])->assertCreated()->json('file.id');
        $this->putJson(route('files.rel.update', $id), ['name' => 'b.txt', 'version' => 0])->assertOk();

        // per-file feed is scoped to that file (checked before trashing so the
        // route binding still resolves the live row)
        $perFile = $this->getJson(route('files.rel.entries.activity', $id))->assertOk()->json('activity');
        $this->assertNotEmpty($perFile);
        foreach ($perFile as $r) {
            $this->assertSame($id, $r['file_id']);
        }

        $this->deleteJson(route('files.rel.destroy', $id))->assertOk();

        $rows = $this->getJson(route('files.rel.activity'))->assertOk()->json('activity');
        $actions = array_column($rows, 'action');
        $this->assertContains('upload', $actions);
        $this->assertContains('rename', $actions);
        $this->assertContains('trash', $actions);
    }

    public function test_activity_feed_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->post(route('files.rel.upload'), [
            'file' => UploadedFile::fake()->createWithContent('secret.txt', 'x'),
        ])->assertCreated();

        $this->assertNotEmpty($this->actingAs($owner)->getJson(route('files.rel.activity'))->json('activity'));
        $this->assertEmpty($this->actingAs($other)->getJson(route('files.rel.activity'))->json('activity'));
    }

    public function test_download_headers_and_owner_scope(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner);
        $id = (int) $this->post(route('files.rel.upload'), [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'secret bytes'),
        ])->json('file.id');

        $res = $this->get(route('files.rel.raw', $id))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            // SecurityHeaders leaves a route's own sandbox CSP intact ($isSandboxed).
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->assertSame('secret bytes', $res->streamedContent());

        // Foreign user cannot resolve the row (owner global scope) → 404.
        $this->actingAs($other)->get(route('files.rel.raw', $id))->assertNotFound();
    }

    public function test_folder_nest_and_cycle_move_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $a = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'A'])->assertCreated()->json('folder.id');
        $b = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'B', 'parent_id' => $a])->assertCreated()->json('folder.id');

        // Moving A under its own descendant B would create a cycle → 422.
        $this->postJson(route('files.rel.folders.move', $a), ['parent_id' => $b])->assertStatus(422);
        $this->assertNull(FileFolder::find($a)->parent_id);

        // A valid move works.
        $this->postJson(route('files.rel.folders.move', $b), ['parent_id' => null])->assertOk();
        $this->assertNull(FileFolder::find($b)->parent_id);
    }

    public function test_rename_and_move_file(): void
    {
        $this->actingAs(User::factory()->create());
        $folder = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Docs'])->json('folder.id');
        $id = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('x.txt', 'x')])->json('file.id');

        $this->putJson(route('files.rel.update', $id), ['name' => 'renamed.txt', 'file_folder_id' => $folder, 'version' => 0])
            ->assertOk()->assertJsonPath('file.name', 'renamed.txt')->assertJsonPath('file.file_folder_id', $folder);

        // Stale version → 409.
        $this->putJson(route('files.rel.update', $id), ['name' => 'again.txt', 'version' => 0])->assertStatus(409);
    }

    public function test_copy_duplicates_blob_and_row_into_target_folder(): void
    {
        $this->actingAs(User::factory()->create());
        $folder = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Dest'])->json('folder.id');
        $id = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('report.pdf', 'PDFBYTES')])->json('file.id');
        $srcPath = FileEntry::findOrFail($id)->storage_path;

        $copyId = (int) $this->postJson(route('files.rel.copy', $id), ['file_folder_id' => $folder])
            ->assertCreated()->json('file.id');

        $copy = FileEntry::findOrFail($copyId);
        $this->assertSame('report (copy).pdf', $copy->name);
        $this->assertSame($folder, $copy->file_folder_id);
        $this->assertNotSame($srcPath, $copy->storage_path); // distinct blob
        Storage::disk(config('files.disk'))->assertExists($copy->storage_path);
        $this->assertSame(2, FileEntry::query()->count()); // original + copy
    }

    public function test_copy_folder_duplicates_the_whole_subtree(): void
    {
        $this->actingAs(User::factory()->create());
        $src = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Project'])->json('folder.id');
        $sub = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Notes', 'parent_id' => $src])->json('folder.id');
        $dest = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Archive'])->json('folder.id');

        $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('top.txt', 'top'), 'file_folder_id' => $src])->assertCreated();
        $deep = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('deep.txt', 'deep'), 'file_folder_id' => $sub])->json('file.id');
        $deepPath = FileEntry::findOrFail($deep)->storage_path;

        $copyId = (int) $this->postJson(route('files.rel.folders.copy', $src), ['parent_id' => $dest])
            ->assertCreated()->json('folder.id');

        $copy = FileFolder::findOrFail($copyId);
        $this->assertSame('Project (copy)', $copy->name);
        $this->assertSame($dest, $copy->parent_id);

        // The child folder hangs off the copy, not off the original it was
        // cloned from -- getting that wrong is the whole difficulty here.
        $subCopy = FileFolder::query()->where('parent_id', $copyId)->firstOrFail();
        $this->assertSame('Notes', $subCopy->name);

        $deepCopy = FileEntry::query()->where('file_folder_id', $subCopy->id)->firstOrFail();
        $this->assertSame('deep.txt', $deepCopy->name);
        $this->assertNotSame($deepPath, $deepCopy->storage_path);
        Storage::disk(config('files.disk'))->assertExists($deepCopy->storage_path);

        // Originals untouched, copies added: 3 folders + 2 files become
        // 5 folders + 4 files.
        $this->assertSame(5, FileFolder::query()->count());
        $this->assertSame(4, FileEntry::query()->count());
    }

    public function test_copy_folder_refuses_to_copy_into_itself(): void
    {
        $this->actingAs(User::factory()->create());
        $src = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Project'])->json('folder.id');
        $sub = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Notes', 'parent_id' => $src])->json('folder.id');

        // Copying a folder into its own descendant would walk its own output.
        $this->postJson(route('files.rel.folders.copy', $src), ['parent_id' => $sub])
            ->assertStatus(422)->assertJsonPath('error', 'cycle');

        $this->assertSame(2, FileFolder::query()->count());
    }

    public function test_copy_folder_is_owner_scoped(): void
    {
        $this->actingAs(User::factory()->create());
        $foreign = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Theirs'])->json('folder.id');

        $this->actingAs(User::factory()->create());
        $this->postJson(route('files.rel.folders.copy', $foreign))->assertNotFound();
    }

    public function test_video_upload_queues_a_poster_frame_and_thumb_is_cache_only(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $id = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->create('clip.mp4', 12, 'video/mp4')])->json('file.id');

        Queue::assertPushed(GenerateFileVideoThumbnail::class, fn ($job): bool => $job->fileId === $id);

        // Cache-only: no poster yet, so the client gets a 404 and falls back to
        // the type icon rather than the request holding open through ffmpeg.
        $this->get(route('files.rel.thumb', $id))->assertNotFound();

        $file = FileEntry::findOrFail($id);
        Storage::disk(config('files.disk'))->put('files/thumb/'.$file->id.'-'.$file->version.'.webp', 'WEBPBYTES');

        $this->get(route('files.rel.thumb', $id))->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_a_file_with_no_video_track_is_not_requeued_on_every_listing(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        // A .wma is sniffed as video/x-ms-asf and has no frame to grab. Without
        // the marker every listing would run ffmpeg again for a frame that does
        // not exist.
        $id = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->create('song.wma', 8, 'video/x-ms-asf')])->json('file.id');
        $file = FileEntry::findOrFail($id);
        Storage::disk(config('files.disk'))->put('files/thumb/'.$file->id.'-'.$file->version.'.webp.none', '');

        Queue::fake(); // forget the upload dispatch; only the listing matters here
        $this->get(route('files.rel.thumb', $id))->assertNotFound();

        Queue::assertNotPushed(GenerateFileVideoThumbnail::class);
    }

    public function test_trash_restore_force_removes_bytes(): void
    {
        $this->actingAs(User::factory()->create());
        $id = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('t.txt', 'trash me')])->json('file.id');
        $path = FileEntry::findOrFail($id)->storage_path;

        $this->deleteJson(route('files.rel.destroy', $id))->assertOk();
        $this->getJson(route('files.rel.index'))->assertOk()->assertJsonCount(0, 'files');
        $this->getJson(route('files.rel.trash'))->assertOk()->assertJsonCount(1, 'files');

        $this->postJson(route('files.rel.restore', $id))->assertOk();
        $this->getJson(route('files.rel.index'))->assertJsonCount(1, 'files');

        $this->deleteJson(route('files.rel.destroy', $id))->assertOk();
        $this->deleteJson(route('files.rel.force', $id))->assertOk();
        $this->assertNull(FileEntry::withTrashed()->find($id));
        Storage::disk(config('files.disk'))->assertMissing($path);
    }

    public function test_folder_trash_restore_and_force_deletes_subtree(): void
    {
        $this->actingAs(User::factory()->create());
        $folder = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Box'])->json('folder.id');
        $fileId = (int) $this->post(route('files.rel.upload'), [
            'file' => UploadedFile::fake()->createWithContent('in.txt', 'inside'),
            'file_folder_id' => $folder,
        ])->json('file.id');
        $path = FileEntry::findOrFail($fileId)->storage_path;

        // Trash the folder → folder + contained file are soft-deleted.
        $this->deleteJson(route('files.rel.folders.destroy', $folder))->assertOk();
        $this->assertSoftDeleted('file_folders', ['id' => $folder]);
        $this->assertSoftDeleted('files', ['id' => $fileId]);

        // Restore via the folder endpoint → both come back. (Regression: the SPA
        // used to call the file endpoint with a folder id, 404ing or restoring a
        // stray file that happened to share the numeric id.)
        $this->postJson(route('files.rel.folders.restore', $folder))->assertOk();
        $this->assertNull(FileFolder::find($folder)->deleted_at);
        $this->assertNull(FileEntry::find($fileId)->deleted_at);

        // Trash again, then force-delete the subtree → rows + bytes gone.
        $this->deleteJson(route('files.rel.folders.destroy', $folder))->assertOk();
        $this->deleteJson(route('files.rel.folders.force', $folder))->assertOk();
        $this->assertDatabaseMissing('file_folders', ['id' => $folder]);
        $this->assertDatabaseMissing('files', ['id' => $fileId]);
        Storage::disk(config('files.disk'))->assertMissing($path);
    }

    public function test_replace_content_versions_and_prune(): void
    {
        $user = User::factory()->create();
        UserSetting::query()->updateOrCreate(['user_id' => $user->id], ['file_max_versions' => 2]);
        $this->actingAs($user);

        $id = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('v.txt', 'v0')])->json('file.id');
        $origPath = FileEntry::findOrFail($id)->storage_path;

        foreach (['v1', 'v2', 'v3'] as $body) {
            $this->post(route('files.rel.content', $id), ['file' => UploadedFile::fake()->createWithContent('v.txt', $body)])->assertOk();
        }

        // Current bytes are the latest revision.
        $this->assertSame('v3', $this->get(route('files.rel.raw', $id))->streamedContent());
        // Cap = 2 → only the two newest prior revisions survive; the original blob is pruned.
        $this->getJson(route('files.rel.versions', $id))->assertOk()->assertJsonCount(2, 'versions');
        Storage::disk(config('files.disk'))->assertMissing($origPath);
    }

    public function test_quota_rejects_over_limit_upload(): void
    {
        config(['files.quota_mb' => 1]); // 1 MiB workspace cap
        $this->actingAs(User::factory()->create());

        $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->create('big.bin', 2048)])
            ->assertStatus(413)->assertJsonPath('error', 'quota');
        $this->assertSame(0, FileEntry::withTrashed()->count());
    }

    public function test_files_are_private_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('mine.txt', 'mine')])->assertCreated();
        $this->actingAs($b)->getJson(route('files.rel.index'))->assertOk()->assertJsonCount(0, 'files');
    }

    public function test_chunked_upload_assembles_bytes(): void
    {
        $this->actingAs(User::factory()->create());

        $sessionId = $this->postJson(route('files.rel.chunk.init'), ['name' => 'big.bin', 'size' => 6])
            ->assertCreated()->json('id');

        $this->post(route('files.rel.chunk.part'), ['id' => $sessionId, 'index' => 0, 'file' => UploadedFile::fake()->createWithContent('0', 'foo')])->assertOk();
        $this->post(route('files.rel.chunk.part'), ['id' => $sessionId, 'index' => 1, 'file' => UploadedFile::fake()->createWithContent('1', 'bar')])->assertOk();

        $id = (int) $this->postJson(route('files.rel.chunk.complete'), ['id' => $sessionId])->assertCreated()->json('file.id');

        $file = FileEntry::findOrFail($id);
        $this->assertSame(6, $file->size);
        $this->assertSame(hash('sha256', 'foobar'), $file->sha256);
        $this->assertSame('foobar', $this->get(route('files.rel.raw', $id))->streamedContent());
    }

    public function test_files_page_serves_the_spa_shell(): void
    {
        // SPA-only: /files serves the SPA shell; the Files UI renders client-side.
        $this->actingAs(User::factory()->create());
        $this->get(route('files.index'))->assertOk()->assertSee('id="app"', false);
    }

    public function test_label_crud_assign_and_search(): void
    {
        $this->actingAs(User::factory()->create());

        // Create a label.
        $lid = (int) $this->postJson(route('files.rel.labels.store'), ['name' => 'Steuer', 'color' => '#e2915a'])
            ->assertCreated()->json('label.id');
        $this->assertSame('Steuer', FileLabel::findOrFail($lid)->name);

        // Upload a file, then assign the label.
        $fid = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('a.txt', 'x')])->json('file.id');
        $this->postJson(route('files.rel.entry.labels', $fid), ['label_ids' => [$lid]])
            ->assertOk()->assertJsonPath('file.labels.0.id', $lid);

        // The listing eager-loads labels.
        $this->getJson(route('files.rel.index'))->assertOk()->assertJsonPath('files.0.labels.0.id', $lid);

        // Rename search finds by name (content search falls back to LIKE on sqlite).
        $this->getJson(route('files.rel.search', ['q' => 'a.txt']))->assertOk()->assertJsonCount(1, 'files');
        $this->getJson(route('files.rel.search', ['q' => 'zzzznope']))->assertOk()->assertJsonCount(0, 'files');

        // Delete the label → pivot cascades, file keeps existing.
        $this->deleteJson(route('files.rel.labels.destroy', $lid))->assertOk();
        $this->assertSame(0, FileLabel::count());
        $this->assertNotNull(FileEntry::find($fid));
    }

    public function test_labels_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $lid = (int) $this->postJson(route('files.rel.labels.store'), ['name' => 'Mine'])->json('label.id');

        $this->actingAs(User::factory()->create());
        $this->getJson(route('files.rel.labels'))->assertOk()->assertJsonCount(0, 'labels');
        $this->putJson(route('files.rel.labels.update', $lid), ['name' => 'x'])->assertNotFound();
    }

    public function test_image_thumbnail_is_generated_and_cached(): void
    {
        $this->actingAs(User::factory()->create());
        $fid = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->image('pic.png', 800, 600)])->json('file.id');

        $res = $this->get(route('files.rel.thumb', $fid))->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotEmpty($res->streamedContent());

        // Cached on the files disk under files/thumb/.
        $file = FileEntry::findOrFail($fid);
        Storage::disk(config('files.disk'))->assertExists('files/thumb/'.$fid.'-'.$file->version.'.webp');

        // A non-image file has no thumbnail → 404.
        $tid = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('t.txt', 'x')])->json('file.id');
        $this->get(route('files.rel.thumb', $tid))->assertNotFound();
    }

    public function test_zip_download_and_storage_stats_with_duplicates(): void
    {
        $this->actingAs(User::factory()->create());
        $a = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('a.txt', 'same-bytes')])->json('file.id');
        $b = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('b.txt', 'same-bytes')])->json('file.id');
        $c = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('c.txt', 'other')])->json('file.id');

        // ZIP a selection.
        $this->post(route('files.zip'), ['ids' => [$a, $b, $c]])->assertOk()->assertHeader('Content-Type', 'application/zip');

        // Empty selection → 422.
        $this->postJson(route('files.zip'), [])->assertStatus(422);

        // Stats: a.txt + b.txt share a sha256 → one duplicate group.
        $res = $this->getJson(route('files.stats'))->assertOk();
        $this->assertGreaterThanOrEqual(1, count($res->json('duplicates')));
        $this->assertArrayHasKey('TEXT', $res->json('by_type'));
    }
}
