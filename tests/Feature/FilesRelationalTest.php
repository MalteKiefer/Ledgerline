<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FileLabel;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_files_page_renders_for_an_authed_user(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('files.index'))->assertOk()->assertSee('x-data="files(', false);
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
}
