<?php

namespace Tests\Feature;

use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesTrashedFolderSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_with_a_trashed_file_pointing_at_a_trashed_folder(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        $u = User::factory()->create();
        // a TRASHED folder + a TRASHED file inside it (as after trashing a folder)
        $tf = (string) Str::uuid();
        $folder = (new FileFolder);
        $folder->forceFill(['id' => $tf, 'user_id' => $u->id, 'parent_id' => null, 'name' => 'Gone'])->save();
        $folder->delete();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'x');
        $fid = (string) Str::uuid();
        $f = (new StoredFile);
        $f->forceFill(['id' => $fid, 'user_id' => $u->id, 'file_folder_id' => $tf, 'name' => 'f.txt', 'blob' => $blob, 'mime' => 'text/plain', 'size' => 1])->save();
        $f->delete();
        // a live folder to keep manifest non-empty
        $live = (string) Str::uuid();
        (new FileFolder)->forceFill(['id' => $live, 'user_id' => $u->id, 'parent_id' => null, 'name' => 'Live'])->save();
        // client manifest = live folders + ALL files (incl trashed) as data() returns
        $res = $this->actingAs($u)->putJson(route('files.sync'), [
            'folders' => [['id' => $live, 'name' => 'Live', 'parent' => null]],
            'files' => [['id' => $fid, 'blob' => $blob, 'name' => 'f.txt', 'mime' => 'text/plain', 'size' => 1, 'folder' => $tf, 'trashed' => now()->toIso8601String(), 'tags' => []]],
        ]);
        $res->assertOk();
    }
}
