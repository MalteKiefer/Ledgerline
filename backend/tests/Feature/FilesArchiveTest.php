<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExtractArchive;
use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\User;
use App\Support\Archiver;
use App\Support\BinaryProcess;
use App\Support\BlobStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilesArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('files.disk'));
    }

    private function putFile(User $u, string $name, string $body, ?int $folderId = null): FileEntry
    {
        $path = 'files/'.Str::uuid();
        BlobStore::disk()->put($path, $body);
        $f = new FileEntry;
        $f->forceFill(['user_id' => $u->id, 'file_folder_id' => $folderId, 'name' => $name,
            'storage_path' => $path, 'size' => strlen($body), 'mime' => 'text/plain', 'sha256' => hash('sha256', $body)])->save();

        return $f;
    }

    /** Build a real archive blob (via the Archiver) and store it as a FileEntry. */
    private function archiveEntry(User $u, array $entries, string $format, ?string $password = null): FileEntry
    {
        $stage = sys_get_temp_dir().'/t-'.Str::uuid();
        mkdir($stage);
        $map = [];
        foreach ($entries as $rel => $body) {
            $p = $stage.'/'.basename($rel);
            file_put_contents($p, $body);
            $map[$rel] = $p;
        }
        $out = sys_get_temp_dir().'/a-'.Str::uuid();
        Archiver::create($map, $format, 6, $password, $out);
        $blob = 'files/'.Str::uuid();
        BlobStore::disk()->put($blob, (string) file_get_contents($out));
        $f = new FileEntry;
        $ext = ['zip' => 'zip', 'tar.gz' => 'tar.gz', 'tar.xz' => 'tar.xz', '7z' => '7z'][$format];
        $f->forceFill(['user_id' => $u->id, 'file_folder_id' => null, 'name' => 'bundle.'.$ext,
            'storage_path' => $blob, 'size' => filesize($out), 'mime' => 'application/octet-stream'])->save();
        Archiver::rmrf($stage);
        @unlink($out);

        return $f;
    }

    public function test_create_zip_archive_from_a_selection(): void
    {
        $u = User::factory()->create();
        $a = $this->putFile($u, 'a.txt', 'AAA');
        $b = $this->putFile($u, 'b.txt', 'BBBB');

        $res = $this->actingAs($u)->postJson(route('files.archive'), [
            'ids' => [$a->id, $b->id], 'format' => 'zip',
        ])->assertOk();

        $archive = FileEntry::findOrFail($res->json('file.id'));
        $this->assertStringEndsWith('.zip', (string) $archive->name);
        // The stored bytes are a valid zip containing both files.
        $tmp = sys_get_temp_dir().'/'.Str::uuid().'.zip';
        file_put_contents($tmp, BlobStore::disk()->get((string) $archive->storage_path));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($tmp);
        $this->assertContains('a.txt', $names);
        $this->assertContains('b.txt', $names);
    }

    public function test_extract_zip_job_rebuilds_files_and_folder_tree(): void
    {
        $u = User::factory()->create();
        $archive = $this->archiveEntry($u, ['a.txt' => 'AAA', 'sub/b.txt' => 'BBBB'], 'zip');

        (new ExtractArchive($archive->id, $u->id, null, null))->handle();

        $a = FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'a.txt')->first();
        $b = FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'b.txt')->first();
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $sub = FileFolder::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'sub')->first();
        $this->assertNotNull($sub, 'sub/ folder should be recreated');
        $this->assertSame((int) $sub->id, (int) $b->file_folder_id);
        $this->assertSame('BBBB', BlobStore::disk()->get((string) $b->storage_path));
    }

    public function test_extract_confines_zip_slip_entries(): void
    {
        $u = User::factory()->create();
        // Hand-craft a zip whose entry escapes the destination.
        $tmp = sys_get_temp_dir().'/'.Str::uuid().'.zip';
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('../evil.txt', 'PWNED');
        $zip->addFromString('ok.txt', 'FINE');
        $zip->close();
        $blob = 'files/'.Str::uuid();
        BlobStore::disk()->put($blob, (string) file_get_contents($tmp));
        @unlink($tmp);
        $entry = new FileEntry;
        $entry->forceFill(['user_id' => $u->id, 'name' => 'evil.zip', 'storage_path' => $blob, 'size' => 1, 'mime' => 'application/zip'])->save();

        // ArchiveName::safe throws on the ../ entry → the whole extract fails and
        // NOTHING is written (fail-closed). The escaping file never lands.
        (new ExtractArchive($entry->id, $u->id, null, null))->handle();
        $this->assertSame(1, FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->count()); // only the archive itself
        $this->assertFileDoesNotExist(sys_get_temp_dir().'/evil.txt');
    }

    public function test_password_zip_round_trips_and_wrong_password_fails(): void
    {
        $u = User::factory()->create();
        $archive = $this->archiveEntry($u, ['secret.txt' => 'TOP'], 'zip', 'correct-pw');

        // Wrong password → nothing extracted (fail-closed).
        (new ExtractArchive($archive->id, $u->id, null, 'wrong'))->handle();
        $this->assertNull(FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'secret.txt')->first());

        // Correct password → the file appears with its plaintext.
        (new ExtractArchive($archive->id, $u->id, null, 'correct-pw'))->handle();
        $f = FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'secret.txt')->first();
        $this->assertNotNull($f);
        $this->assertSame('TOP', BlobStore::disk()->get((string) $f->storage_path));
    }

    public function test_tar_gz_round_trips(): void
    {
        if (! BinaryProcess::available('tar') || ! BinaryProcess::available('gzip')) {
            $this->markTestSkipped('tar/gzip not available.');
        }
        $u = User::factory()->create();
        $archive = $this->archiveEntry($u, ['x.txt' => 'XYZ'], 'tar.gz');

        (new ExtractArchive($archive->id, $u->id, null, null))->handle();

        $f = FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'x.txt')->first();
        $this->assertNotNull($f);
        $this->assertSame('XYZ', BlobStore::disk()->get((string) $f->storage_path));
    }

    public function test_7z_round_trips_when_available(): void
    {
        if (! BinaryProcess::available('7z') && ! BinaryProcess::available('7za')) {
            $this->markTestSkipped('7z not installed (image-only).');
        }
        $u = User::factory()->create();
        $archive = $this->archiveEntry($u, ['z.txt' => 'SEVENZIP'], '7z', 'pw7z');

        (new ExtractArchive($archive->id, $u->id, null, 'pw7z'))->handle();

        $f = FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'z.txt')->first();
        $this->assertNotNull($f);
        $this->assertSame('SEVENZIP', BlobStore::disk()->get((string) $f->storage_path));
    }

    public function test_extract_into_new_folder_default_and_optional_direct(): void
    {
        $u = User::factory()->create();
        $home = new FileFolder;
        $home->forceFill(['user_id' => $u->id, 'name' => 'Home', 'parent_id' => null])->save();
        $archive = $this->archiveEntry($u, ['doc.txt' => 'HI'], 'zip');

        // Default: a new folder (named after the archive) is created under the target.
        $res = $this->actingAs($u)->postJson(route('files.extract', ['file' => $archive->id]), ['target_folder_id' => $home->id])->assertOk();
        $this->assertNotNull($res->json('folder'));
        $newId = (int) $res->json('folder.id');
        $doc = FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'doc.txt')->first();
        $this->assertNotNull($doc);
        $this->assertSame($newId, (int) $doc->file_folder_id);

        // Optional: extract straight into the target folder (no new folder, folder=null).
        $archive2 = $this->archiveEntry($u, ['flat.txt' => 'YO'], 'zip');
        $res2 = $this->actingAs($u)->postJson(route('files.extract', ['file' => $archive2->id]), ['target_folder_id' => $home->id, 'into_new_folder' => false])->assertOk();
        $this->assertNull($res2->json('folder'));
        $flat = FileEntry::withoutGlobalScopes()->where('user_id', $u->id)->where('name', 'flat.txt')->first();
        $this->assertNotNull($flat);
        $this->assertSame((int) $home->id, (int) $flat->file_folder_id);
    }

    public function test_extract_rejects_non_archive(): void
    {
        $u = User::factory()->create();
        $doc = $this->putFile($u, 'notes.txt', 'hello');
        $this->actingAs($u)->postJson(route('files.extract', ['file' => $doc->id]))->assertStatus(422);
    }

    public function test_detect_format(): void
    {
        $this->assertSame('tar.gz', Archiver::detectFormat('a.tar.gz'));
        $this->assertSame('tar.gz', Archiver::detectFormat('a.TGZ'));
        $this->assertSame('7z', Archiver::detectFormat('a.7z'));
        $this->assertSame('zip', Archiver::detectFormat('a.zip'));
        $this->assertNull(Archiver::detectFormat('a.txt'));
        $this->assertTrue(Archiver::isArchive('x.rar'));
        $this->assertFalse(Archiver::isArchive('x.jpg'));
    }
}
