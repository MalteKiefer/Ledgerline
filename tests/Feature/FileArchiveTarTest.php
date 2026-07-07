<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileFolder;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\ArchiveManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileArchiveTarTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_tar_gz(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();

        // Build a real tar.gz.
        $tmp = tempnam(sys_get_temp_dir(), 'tt');
        @unlink($tmp);
        $tarPath = $tmp.'.tar';
        $tar = new \PharData($tarPath);
        $tar->addFromString('sub/hi.txt', 'hello');
        $tar->addFromString('top.txt', 'top');
        $tar->compress(\Phar::GZ);
        $bytes = file_get_contents($tarPath.'.gz');
        @unlink($tarPath);
        @unlink($tarPath.'.gz');

        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, $bytes);
        $arc = new StoredFile;
        $arc->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => 'bundle.tar.gz', 'blob' => $blob, 'size' => strlen($bytes), 'mime' => 'application/gzip'])->save();

        $count = app(ArchiveManager::class)->extract($u->id, $arc);
        $this->assertSame(2, $count);
        $root = FileFolder::withoutGlobalScopes()->where('name', 'bundle')->firstOrFail();
        $this->assertTrue(StoredFile::withoutGlobalScopes()->where('name', 'top.txt')->exists());
        $this->assertTrue(FileFolder::withoutGlobalScopes()->where('name', 'sub')->exists());
    }

    public function test_note_saved(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, 'x');
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => 'a.txt', 'blob' => $blob, 'size' => 1, 'mime' => 'text/plain'])->save();

        $this->actingAs($u)->postJson(route('files.note', $f->id), ['note' => 'my note'])->assertOk();
        $this->assertSame('my note', StoredFile::withoutGlobalScopes()->find($f->id)->note);
    }
}
