<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileFolder;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesSyncGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_accepts_a_kept_version_blob_so_restore_works(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $u = User::factory()->create();

        $newBlob = (string) Str::uuid();
        $oldBlob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$newBlob, 'new');
        Storage::disk('files')->put('files/'.$oldBlob, 'old');

        $id = (string) Str::uuid();
        (new StoredFile)->forceFill(['id' => $id, 'user_id' => $u->id, 'name' => 'x', 'blob' => $newBlob, 'mime' => 'text/plain', 'size' => 3])->save();
        FileVersion::create(['id' => (string) Str::uuid(), 'file_id' => $id, 'user_id' => $u->id,
            'name' => 'x', 'mime' => 'text/plain', 'size' => 3, 'blob' => $oldBlob, 'created_at' => now()]);

        // Restore: point the file back at the version's blob and sync — the blob
        // is one of the user's own versions, so the allow-list must accept it.
        $this->actingAs($u)->putJson(route('files.sync'), ['folders' => [], 'files' => [[
            'id' => $id, 'blob' => $oldBlob, 'name' => 'x', 'mime' => 'text/plain', 'size' => 3, 'folder' => null, 'tags' => [],
        ]]])->assertOk();

        $this->assertSame($oldBlob, StoredFile::withoutGlobalScopes()->find($id)->blob);
    }

    public function test_sync_soft_deletes_a_dropped_folder_so_it_is_recoverable(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $u = User::factory()->create();

        $keep = (string) Str::uuid();
        $drop = (string) Str::uuid();
        (new FileFolder)->forceFill(['id' => $keep, 'user_id' => $u->id, 'parent_id' => null, 'name' => 'Keep'])->save();
        (new FileFolder)->forceFill(['id' => $drop, 'user_id' => $u->id, 'parent_id' => null, 'name' => 'Drop'])->save();

        // Manifest keeps one folder, omits the other → the omitted one is
        // SOFT-deleted (recoverable), never hard-deleted.
        $this->actingAs($u)->putJson(route('files.sync'), [
            'folders' => [['id' => $keep, 'name' => 'Keep', 'parent' => null]], 'files' => [],
        ])->assertOk();

        $this->assertNotNull(FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->find($keep));
        $this->assertNull(FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->find($drop));
        $this->assertNotNull(FileFolder::withoutGlobalScopes()->withTrashed()->find($drop)?->deleted_at);
    }

    public function test_sync_self_heals_a_dangling_folder_reference_to_root(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $u = User::factory()->create();

        // A live file whose folder points at a folder NOT in the incoming manifest
        // (e.g. one that was trashed) — the sync must not 422; it reparents to root.
        $gone = (string) Str::uuid();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'b');
        $fid = (string) Str::uuid();
        (new StoredFile)->forceFill(['id' => $fid, 'user_id' => $u->id, 'file_folder_id' => $gone, 'name' => 'f', 'blob' => $blob, 'mime' => 'text/plain', 'size' => 1])->save();

        // Manifest keeps the file (folder=gone, not listed) plus one live folder.
        $keep = (string) Str::uuid();
        $this->actingAs($u)->putJson(route('files.sync'), [
            'folders' => [['id' => $keep, 'name' => 'Keep', 'parent' => null]],
            'files' => [['id' => $fid, 'blob' => $blob, 'name' => 'f', 'mime' => 'text/plain', 'size' => 1, 'folder' => $gone, 'tags' => []]],
        ])->assertOk();

        $this->assertNull(StoredFile::withoutGlobalScopes()->find($fid)->file_folder_id);
    }

    public function test_sync_refuses_an_empty_folder_list_while_folders_exist(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $u = User::factory()->create();

        (new FileFolder)->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'parent_id' => null, 'name' => 'Keep'])->save();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'b');
        (new StoredFile)->forceFill(['id' => ($fid = (string) Str::uuid()), 'user_id' => $u->id, 'name' => 'f', 'blob' => $blob, 'mime' => 'text/plain', 'size' => 1])->save();

        // A manifest with a file but folders=[] would hard-delete the folder tree.
        $this->actingAs($u)->putJson(route('files.sync'), ['folders' => [], 'files' => [[
            'id' => $fid, 'blob' => $blob, 'name' => 'f', 'mime' => 'text/plain', 'size' => 1, 'folder' => null, 'tags' => [],
        ]]])->assertStatus(409);

        $this->assertSame(1, FileFolder::withoutGlobalScopes()->count());
    }
}
