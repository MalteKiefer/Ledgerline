<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\UploadLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadLinkFolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_upload_recreates_subfolders(): void
    {
        Storage::fake('files');
        $owner = User::factory()->create();
        $link = new UploadLink;
        $link->forceFill(['token' => 'ft', 'user_id' => $owner->id, 'file_folder_id' => null])->save();

        $this->post(route('upload-link.upload', 'ft'), [
            'file' => UploadedFile::fake()->create('a.txt', 2),
            'path' => 'Photos/2026/a.txt',
        ])->assertOk();

        $photos = FileFolder::withoutGlobalScopes()->where('name', 'Photos')->firstOrFail();
        $y = FileFolder::withoutGlobalScopes()->where('name', '2026')->where('parent_id', $photos->id)->firstOrFail();
        $file = StoredFile::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($y->id, $file->file_folder_id);
        $this->assertSame($owner->id, (int) $file->user_id);
    }

    public function test_path_traversal_is_stripped(): void
    {
        Storage::fake('files');
        $owner = User::factory()->create();
        $link = new UploadLink;
        $link->forceFill(['token' => 'ft2', 'user_id' => $owner->id, 'file_folder_id' => null])->save();

        $this->post(route('upload-link.upload', 'ft2'), [
            'file' => UploadedFile::fake()->create('a.txt', 2),
            'path' => '../../etc/a.txt',
        ])->assertOk();

        // No '..' escape: the up-levels are stripped, 'etc' becomes a normal
        // subfolder directly under the link's root (parent_id null).
        $this->assertSame(0, FileFolder::withoutGlobalScopes()->where('name', '..')->count());
        $etc = FileFolder::withoutGlobalScopes()->where('name', 'etc')->firstOrFail();
        $this->assertNull($etc->parent_id);
        $this->assertSame($etc->id, StoredFile::withoutGlobalScopes()->firstOrFail()->file_folder_id);
    }
}
