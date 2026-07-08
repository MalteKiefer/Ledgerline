<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesTrashEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function file(User $u, string $name, ?string $folderId = null): StoredFile
    {
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'x');
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => $folderId,
            'name' => $name, 'blob' => $blob, 'size' => 1, 'mime' => 'text/plain'])->save();

        return $f;
    }

    public function test_trash_soft_deletes_and_keeps_blob(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $f = $this->file($u, 'a.txt');

        $this->actingAs($u)->postJson(route('files.trash'), ['file_ids' => [$f->id]])->assertOk();

        $this->assertNotNull(StoredFile::withoutGlobalScopes()->withTrashed()->find($f->id)->deleted_at);
        Storage::disk('files')->assertExists('files/'.$f->blob);
    }

    public function test_permanent_delete_removes_row_and_blob(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $f = $this->file($u, 'a.txt');

        $this->actingAs($u)->postJson(route('files.trash'), ['file_ids' => [$f->id], 'permanent' => true])->assertOk();

        $this->assertNull(StoredFile::withoutGlobalScopes()->withTrashed()->find($f->id));
        Storage::disk('files')->assertMissing('files/'.$f->blob);
    }

    public function test_restore_untrashes(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $f = $this->file($u, 'a.txt');
        $f->delete();

        $this->actingAs($u)->postJson(route('files.restore'), ['file_ids' => [$f->id]])->assertOk();

        $this->assertNull(StoredFile::withoutGlobalScopes()->withTrashed()->find($f->id)->deleted_at);
    }

    public function test_trash_folder_soft_deletes_the_folder_and_keeps_the_hierarchy(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $folder = new FileFolder;
        $folder->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'parent_id' => null, 'name' => 'Docs'])->save();
        $f = $this->file($u, 'inside.txt', $folder->id);

        $this->actingAs($u)->postJson(route('files.trash'), ['folder_ids' => [$folder->id]])->assertOk();

        // Folder soft-deleted (recoverable), and the file keeps its folder id and
        // is soft-deleted — so a restore brings the whole hierarchy back.
        $this->assertNull(FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->find($folder->id));
        $this->assertNotNull(FileFolder::withoutGlobalScopes()->withTrashed()->find($folder->id)?->deleted_at);
        $row = StoredFile::withoutGlobalScopes()->withTrashed()->find($f->id);
        $this->assertSame($folder->id, $row->file_folder_id);
        $this->assertNotNull($row->deleted_at);

        // Restoring the file un-trashes it AND its folder chain.
        $this->actingAs($u)->postJson(route('files.restore'), ['file_ids' => [$f->id]])->assertOk();
        $this->assertNull(StoredFile::withoutGlobalScopes()->find($f->id)->deleted_at);
        $this->assertNotNull(FileFolder::withoutGlobalScopes()->whereNull('deleted_at')->find($folder->id));
    }

    public function test_cannot_trash_another_users_file(): void
    {
        Storage::fake('files');
        $me = User::factory()->create();
        $other = User::factory()->create();
        $theirs = $this->file($other, 'secret.txt');

        $this->actingAs($me)->postJson(route('files.trash'), ['file_ids' => [$theirs->id]])->assertOk();

        // Untouched — owner scoping filtered it out.
        $this->assertNull(StoredFile::withoutGlobalScopes()->withTrashed()->find($theirs->id)->deleted_at);
    }
}
