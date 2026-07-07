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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FileArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function file(User $u, string $name, string $body, ?string $folderId = null): StoredFile
    {
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, $body);
        $f = new StoredFile;
        $f->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => $folderId,
            'name' => $name, 'blob' => $blob, 'size' => strlen($body), 'mime' => 'text/plain'])->save();

        return $f;
    }

    public function test_create_and_extract_round_trip(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();
        $a = $this->file($u, 'a.txt', 'alpha');
        $b = $this->file($u, 'b.txt', 'bravo');

        $zip = app(ArchiveManager::class)->create($u->id, [
            ['kind' => 'file', 'id' => $a->id], ['kind' => 'file', 'id' => $b->id],
        ], null, 'bundle');

        $this->assertSame('bundle.zip', $zip->name);
        $this->assertSame('application/zip', $zip->mime);
        Storage::disk('files')->assertExists('files/'.$zip->blob);

        // Extract it into a new folder.
        $count = app(ArchiveManager::class)->extract($u->id, $zip);
        $this->assertSame(2, $count);
        $folder = FileFolder::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'bundle')->firstOrFail();
        $names = StoredFile::withoutGlobalScopes()->where('file_folder_id', $folder->id)->pluck('name')->sort()->values()->all();
        $this->assertSame(['a.txt', 'b.txt'], $names);
    }

    public function test_extract_rejects_zip_slip(): void
    {
        Storage::fake('files');
        $u = User::factory()->create();

        // Hand-build a malicious zip with a traversal entry.
        $tmp = tempnam(sys_get_temp_dir(), 'evil');
        $za = new \ZipArchive;
        $za->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $za->addFromString('../escape.txt', 'pwned');
        $za->close();
        $blob = (string) Str::uuid();
        Storage::disk('files')->put('files/'.$blob, file_get_contents($tmp));
        @unlink($tmp);
        $zip = new StoredFile;
        $zip->forceFill(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'file_folder_id' => null,
            'name' => 'evil.zip', 'blob' => $blob, 'size' => 100, 'mime' => 'application/zip'])->save();

        $this->expectException(HttpException::class);
        app(ArchiveManager::class)->extract($u->id, $zip);
    }
}
