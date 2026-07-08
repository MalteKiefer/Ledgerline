<?php

namespace Tests\Feature;

use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesMoveThenDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_files_to_parent_then_trashing_child_keeps_them(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $pid = (string) Str::uuid();
        $cid = (string) Str::uuid();
        $fid = (string) Str::uuid();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'hello');

        // seed folders + file-in-child directly (as if already synced)
        (new FileFolder)->forceFill(['id' => $pid, 'user_id' => $u->id, 'parent_id' => null, 'name' => 'Privat'])->save();
        (new FileFolder)->forceFill(['id' => $cid, 'user_id' => $u->id, 'parent_id' => $pid, 'name' => 'Private'])->save();
        (new StoredFile)->forceFill(['id' => $fid, 'user_id' => $u->id, 'file_folder_id' => $cid, 'name' => 'moved.txt', 'blob' => $blob, 'size' => 5, 'mime' => 'text/plain'])->save();

        // CLIENT MOVE: full manifest PUT, file now in parent
        $manifest = [
            'folders' => [['id' => $pid, 'name' => 'Privat', 'parent' => null], ['id' => $cid, 'name' => 'Private', 'parent' => $pid]],
            'files' => [['id' => $fid, 'enc_metadata' => 'sealed', 'enc_file_key' => 'wrapped', 'folder' => $pid, 'blob' => $blob, 'trashed' => null, 'tags' => []]],
        ];
        $this->actingAs($u)->putJson(route('files.sync'), $manifest)->assertOk();
        $this->assertSame($pid, StoredFile::withoutGlobalScopes()->find($fid)->file_folder_id, 'move did not persist');

        // CLIENT DELETE child folder
        $this->actingAs($u)->postJson(route('files.trash'), ['folder_ids' => [$cid]])->assertOk();

        $row = StoredFile::withoutGlobalScopes()->withTrashed()->find($fid);
        $this->assertNotNull($row, 'file row hard-deleted = DATA LOSS');
        $this->assertNull($row->deleted_at, 'file in parent was trashed = DATA LOSS');
        $this->assertSame($pid, $row->file_folder_id);
    }
}
