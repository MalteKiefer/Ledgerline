<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dav\Files\FileDavBackend;
use App\Dav\Files\FileNode;
use App\Dav\Files\FilesHome;
use App\Dav\Files\FolderNode;
use App\Models\DavCredential;
use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilesDavTest extends TestCase
{
    use RefreshDatabase;

    private function home(User $user): FilesHome
    {
        DavCredential::create([
            'user_id' => $user->id, 'username' => 'files-'.$user->id, 'password_hash' => bcrypt('x'),
        ]);

        return new FilesHome(app(FileDavBackend::class), 'principals/files-'.$user->id);
    }

    public function test_create_read_list_files_and_folders(): void
    {
        Storage::fake('files');
        $user = User::factory()->create();
        $home = $this->home($user);

        $home->createDirectory('Docs');
        $home->createFile('a.txt', 'hello');

        $this->assertDatabaseHas('file_folders', ['user_id' => $user->id, 'name' => 'Docs', 'parent_id' => null]);
        $file = StoredFile::withoutGlobalScopes()->where('name', 'a.txt')->firstOrFail();
        $this->assertSame(5, $file->size);
        $this->assertSame('text/plain', $file->mime);
        Storage::disk('files')->assertExists('files/'.$file->blob);

        // Listing shows the folder + the file.
        $names = collect($home->getChildren())->map->getName()->sort()->values()->all();
        $this->assertSame(['Docs', 'a.txt'], $names);

        // Read the file back.
        $node = $home->getChild('a.txt');
        $this->assertInstanceOf(FileNode::class, $node);
        $this->assertSame('hello', stream_get_contents($node->get()));

        // Create a file inside the folder.
        $docs = $home->getChild('Docs');
        $this->assertInstanceOf(FolderNode::class, $docs);
        $docs->createFile('note.txt', 'inner');
        $this->assertDatabaseHas('files', ['name' => 'note.txt', 'file_folder_id' => FileFolder::where('name', 'Docs')->value('id')]);
    }

    public function test_put_replaces_bytes_and_snapshots_the_old_blob_as_a_version(): void
    {
        Storage::fake('files');
        $user = User::factory()->create();
        $home = $this->home($user);
        $home->createFile('a.txt', 'hello');
        $file = StoredFile::withoutGlobalScopes()->firstOrFail();
        $old = $file->blob;

        /** @var FileNode $node */
        $node = $home->getChild('a.txt');
        $node->put('longer content');

        $file->refresh();
        $this->assertNotSame($old, $file->blob);
        $this->assertSame(14, $file->size);
        Storage::disk('files')->assertExists('files/'.$file->blob);
        // The prior content is kept as a recoverable version (parity with web),
        // so its blob survives the overwrite instead of being released.
        Storage::disk('files')->assertExists('files/'.$old);
        $this->assertDatabaseHas('file_versions', ['file_id' => $file->id, 'blob' => $old]);
    }

    public function test_empty_put_does_not_wipe_existing_content(): void
    {
        Storage::fake('files');
        $user = User::factory()->create();
        $home = $this->home($user);
        $home->createFile('a.txt', 'real content');
        /** @var FileNode $node */
        $node = $home->getChild('a.txt');

        // macOS sends a trailing 0-byte PUT; it must not blank the file.
        $node->put('');

        $file = StoredFile::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(12, $file->size);
        $this->assertSame('real content', stream_get_contents($home->getChild('a.txt')->get()));
    }

    public function test_move_reparents_without_a_new_blob(): void
    {
        Storage::fake('files');
        $user = User::factory()->create();
        $home = $this->home($user);
        $home->createDirectory('Docs');
        $home->createFile('a.txt', 'hi');
        $blob = StoredFile::withoutGlobalScopes()->value('blob');

        /** @var FolderNode $docs */
        $docs = $home->getChild('Docs');
        $moved = $docs->moveInto('a.txt', '', $home->getChild('a.txt'));
        $this->assertTrue($moved);

        $file = StoredFile::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(FileFolder::where('name', 'Docs')->value('id'), $file->file_folder_id);
        $this->assertSame($blob, $file->blob); // same blob, not re-uploaded
    }

    public function test_delete_trashes_the_file_and_keeps_the_blob_for_restore(): void
    {
        Storage::fake('files');
        $user = User::factory()->create();
        $home = $this->home($user);
        $home->createFile('a.txt', 'bye');
        $file = StoredFile::withoutGlobalScopes()->firstOrFail();
        $blob = $file->blob;

        $home->getChild('a.txt')->delete();

        // Soft-deleted (goes to the web trash) and the blob is kept so it can be
        // restored — deleting over WebDAV must not permanently drop the bytes.
        $this->assertNotNull(StoredFile::withoutGlobalScopes()->withTrashed()->find($file->id)->deleted_at);
        Storage::disk('files')->assertExists('files/'.$blob);
    }

    public function test_deleted_file_no_longer_appears_in_the_listing(): void
    {
        Storage::fake('files');
        $user = User::factory()->create();
        $home = $this->home($user);
        $home->createFile('gone.txt', 'bye');

        $home->getChild('gone.txt')->delete();

        // Soft-deleted, but withoutGlobalScopes() must not resurface it.
        $names = collect($home->getChildren())->map->getName()->all();
        $this->assertNotContains('gone.txt', $names);
        $this->assertFalse($home->childExists('gone.txt'));
    }

    public function test_the_tree_is_owner_scoped(): void
    {
        Storage::fake('files');
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->home($other)->createFile('theirs.txt', 'secret');

        $home = $this->home($me);
        $this->assertCount(0, $home->getChildren());
    }
}
