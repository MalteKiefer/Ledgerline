<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The trash used to keep everything forever, and the quota with it.
 *
 * Two properties matter. Off by default: a window that silently deletes a file
 * somebody meant to restore is worse than a full trash. And the bytes go with
 * the row -- a row deleted without its blob leaks disk that nothing will find
 * again, which is why the deletion happens in that order.
 */
class FileTrashRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function trashedFile(string $name, int $daysAgo): FileEntry
    {
        $id = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent($name, 'bytes')])->json('file.id');
        $this->delete(route('files.rel.destroy', $id));

        $file = FileEntry::onlyTrashed()->findOrFail($id);
        $file->forceFill(['deleted_at' => now()->subDays($daysAgo)])->saveQuietly();

        return $file;
    }

    public function test_it_deletes_nothing_while_retention_is_off(): void
    {
        $this->actingAs(User::factory()->create());
        config(['files.trash_retention_days' => 0]);
        $file = $this->trashedFile('old.txt', 400);

        $this->artisan('files:prune-trash')->assertExitCode(0);

        $this->assertNotNull(FileEntry::onlyTrashed()->find($file->id));
        Storage::disk(config('files.disk'))->assertExists($file->storage_path);
    }

    public function test_it_removes_the_row_and_the_bytes_past_the_window(): void
    {
        $this->actingAs(User::factory()->create());
        config(['files.trash_retention_days' => 30]);

        $old = $this->trashedFile('old.txt', 40);
        $recent = $this->trashedFile('recent.txt', 3);

        $this->artisan('files:prune-trash')->assertExitCode(0);

        $this->assertNull(FileEntry::withTrashed()->find($old->id));
        Storage::disk(config('files.disk'))->assertMissing($old->storage_path);

        // Inside the window, so still recoverable.
        $this->assertNotNull(FileEntry::onlyTrashed()->find($recent->id));
        Storage::disk(config('files.disk'))->assertExists($recent->storage_path);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->actingAs(User::factory()->create());
        config(['files.trash_retention_days' => 30]);
        $file = $this->trashedFile('old.txt', 90);

        $this->artisan('files:prune-trash', ['--dry-run' => true])->assertExitCode(0);

        $this->assertNotNull(FileEntry::onlyTrashed()->find($file->id));
        Storage::disk(config('files.disk'))->assertExists($file->storage_path);
    }

    public function test_a_folder_still_holding_trashed_files_survives(): void
    {
        $this->actingAs(User::factory()->create());
        config(['files.trash_retention_days' => 30]);

        $folderId = (int) $this->postJson(route('files.rel.folders.store'), ['name' => 'Old'])->json('folder.id');
        $fileId = (int) $this->post(route('files.rel.upload'), ['file' => UploadedFile::fake()->createWithContent('in.txt', 'x'), 'file_folder_id' => $folderId])->json('file.id');
        $this->delete(route('files.rel.folders.destroy', $folderId));

        // The folder is over the window; the file inside it is not. Deleting
        // the folder now would take the file's row with it through the cascade
        // and leave its bytes behind with nothing pointing at them.
        FileFolder::onlyTrashed()->findOrFail($folderId)->forceFill(['deleted_at' => now()->subDays(90)])->saveQuietly();

        $this->artisan('files:prune-trash')->assertExitCode(0);

        $this->assertNotNull(FileFolder::onlyTrashed()->find($folderId));
        $this->assertNotNull(FileEntry::onlyTrashed()->find($fileId));
    }
}
