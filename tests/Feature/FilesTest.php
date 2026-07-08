<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileBlob;
use App\Models\FileFolder;
use App\Models\StoredFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected(): void
    {
        $this->get(route('files.index'))->assertRedirect(route('login'));
        $this->get(route('files.data'))->assertRedirect(route('login'));
    }

    public function test_the_page_loads_without_a_vault(): void
    {
        $this->signIn();
        $this->get(route('files.index'))->assertOk();
        $this->getJson(route('files.data'))->assertOk()->assertJson(['folders' => [], 'files' => []]);
    }

    public function test_upload_stores_a_file_and_sync_writes_rows(): void
    {
        $this->signIn();
        Storage::fake('files');
        config(['files.disk' => 'files']);

        $res = $this->post(route('files.upload'), ['file' => UploadedFile::fake()->create('doc.pdf', 12, 'application/pdf')])
            ->assertCreated();
        $blob = $res->json('id');
        Storage::disk('files')->assertExists('files/'.$blob);

        $fileId = (string) Str::uuid();
        $this->putJson(route('files.sync'), [
            'folders' => [],
            'files' => [[
                'id' => $fileId, 'blob' => $blob, 'enc_metadata' => '{"c":"c2VhbGVk","n":"bm9uY2U="}',
                'enc_file_key' => '{"c":"d3JhcHBlZA==","n":"bm9uY2Uy"}', 'folder' => null, 'tags' => ['work'],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('files', ['id' => $fileId, 'enc_metadata' => '{"c":"c2VhbGVk","n":"bm9uY2U="}', 'enc_file_key' => '{"c":"d3JhcHBlZA==","n":"bm9uY2Uy"}', 'is_encrypted' => true]);
        // Tags are sealed inside enc_metadata now — never stored plaintext server-side.
        $this->assertNull(StoredFile::find($fileId)->tags);
    }

    public function test_sync_soft_deletes_rows_missing_from_the_manifest(): void
    {
        $this->signIn();
        $keepBlob = (string) Str::uuid();
        $keep = StoredFile::create(['id' => (string) Str::uuid(), 'name' => 'keep', 'blob' => $keepBlob, 'mime' => 'text/plain', 'size' => 1]);
        $gone = StoredFile::create(['id' => (string) Str::uuid(), 'name' => 'gone', 'blob' => (string) Str::uuid(), 'mime' => 'text/plain', 'size' => 1]);

        // A manifest that keeps one file and omits the other: the omitted row is
        // SOFT-deleted (recoverable from the trash), never hard-deleted, so a
        // stale/partial/racing manifest can never cause irreversible loss.
        $this->putJson(route('files.sync'), ['folders' => [], 'files' => [[
            'id' => $keep->id, 'blob' => $keepBlob, 'enc_metadata' => '{"c":"c2VhbGVk","n":"bm9uY2U="}', 'enc_file_key' => '{"c":"d3JhcHBlZA==","n":"bm9uY2Uy"}', 'folder' => null, 'tags' => [],
        ]]])->assertOk();

        $this->assertNotNull(StoredFile::find($keep->id));
        $this->assertNull(StoredFile::find($gone->id));                                  // hidden
        $this->assertNotNull(StoredFile::withTrashed()->find($gone->id)?->deleted_at);   // but recoverable

        // An empty manifest while files still exist is refused (mass-wipe guard).
        $this->putJson(route('files.sync'), ['folders' => [], 'files' => []])->assertStatus(409);
    }

    public function test_sync_keeps_the_blob_of_a_removed_file_for_recovery(): void
    {
        $u = $this->signIn();
        Storage::fake('files');
        config(['files.disk' => 'files']);

        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'bytes');
        FileBlob::create(['blob' => $blob, 'user_id' => $u->id, 'created_at' => now()]);
        $id = (string) Str::uuid();
        $this->putJson(route('files.sync'), [
            'folders' => [],
            'files' => [['id' => $id, 'blob' => $blob, 'enc_metadata' => '{"c":"c2VhbGVk","n":"bm9uY2U="}', 'enc_file_key' => '{"c":"d3JhcHBlZA==","n":"bm9uY2Uy"}', 'folder' => null, 'tags' => []]],
        ])->assertOk();

        // Dropping the file (explicitly confirmed) SOFT-deletes it and KEEPS its
        // bytes — a sync must never permanently destroy data.
        $this->putJson(route('files.sync'), ['folders' => [], 'files' => [], 'confirm_wipe' => true])->assertOk();
        Storage::disk('files')->assertExists('files/'.$blob);
        $this->assertNotNull(StoredFile::withTrashed()->find($id)?->deleted_at);
    }

    public function test_sync_round_trips_a_trashed_file(): void
    {
        $u = $this->signIn();
        Storage::fake('files');
        config(['files.disk' => 'files']);

        $id = (string) Str::uuid();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'x');
        FileBlob::create(['blob' => $blob, 'user_id' => $u->id, 'created_at' => now()]);

        // A file the client marks trashed is stored soft-deleted and still comes
        // back from the data endpoint (so it shows in the trash view).
        $this->putJson(route('files.sync'), [
            'folders' => [],
            'files' => [['id' => $id, 'blob' => $blob, 'enc_metadata' => '{"c":"c2VhbGVk","n":"bm9uY2U="}', 'enc_file_key' => '{"c":"d3JhcHBlZA==","n":"bm9uY2Uy"}', 'folder' => null, 'tags' => [], 'trashed' => now()->toIso8601String()]],
        ])->assertOk();

        $this->assertTrue(StoredFile::withTrashed()->find($id)->trashed());
        $this->getJson(route('files.data'))->assertOk()
            ->assertJsonPath('files.0.id', $id)
            ->assertJsonPath('files.0.trashed', fn ($t) => $t !== null);
    }

    public function test_sync_rejects_a_folder_cycle(): void
    {
        $this->signIn();
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();

        $this->putJson(route('files.sync'), [
            'folders' => [
                ['id' => $a, 'name' => 'A', 'parent' => $b],
                ['id' => $b, 'name' => 'B', 'parent' => $a],
            ],
            'files' => [],
        ])->assertStatus(422);

        $this->assertSame(0, FileFolder::count());
    }

    public function test_sync_rejects_a_file_in_an_unknown_folder(): void
    {
        $this->signIn();

        $this->putJson(route('files.sync'), [
            'folders' => [],
            'files' => [[
                'id' => (string) Str::uuid(), 'blob' => (string) Str::uuid(), 'enc_metadata' => '{"c":"c2VhbGVk","n":"bm9uY2U="}',
                'enc_file_key' => '{"c":"d3JhcHBlZA==","n":"bm9uY2Uy"}', 'folder' => (string) Str::uuid(), 'tags' => [],
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, StoredFile::count());
    }

    public function test_delete_blob_refuses_while_a_row_still_references_it(): void
    {
        $this->signIn();
        Storage::fake('files');
        config(['files.disk' => 'files']);

        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'bytes');
        StoredFile::create(['id' => (string) Str::uuid(), 'name' => 'x', 'blob' => $blob, 'mime' => 'text/plain', 'size' => 5]);

        $this->deleteJson(route('files.blob.destroy', $blob))->assertStatus(409);
        Storage::disk('files')->assertExists('files/'.$blob);
    }
}
