<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\Backup\Sources\FilesSource;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiskArchiveSourceTest extends TestCase
{
    private function work(): string
    {
        $dir = sys_get_temp_dir().'/llbktar_'.uniqid();
        mkdir($dir, 0700, true);

        return $dir;
    }

    public function test_it_archives_files_under_the_prefix(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        Storage::disk('files')->put('vault/a.bin', 'hello');
        Storage::disk('files')->put('vault/sub/b.bin', 'world');

        $work = $this->work();
        $artifact = (new FilesSource)->build($work);

        $this->assertFileExists($artifact->path);
        $this->assertSame('tar.gz', $artifact->extension);
        $this->assertGreaterThan(0, filesize($artifact->path));

        (new Filesystem)->deleteDirectory($work);
    }

    public function test_an_empty_source_still_produces_a_valid_archive(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        // No files under vault/ at all.

        $work = $this->work();
        $artifact = (new FilesSource)->build($work);

        // Must not throw (PharData rejects empty dirs) and must be a real archive.
        $this->assertFileExists($artifact->path);
        $this->assertGreaterThan(0, filesize($artifact->path));

        (new Filesystem)->deleteDirectory($work);
    }
}
