<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\Backup\DiskMirror;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Tests\TestCase;

class DiskMirrorTest extends TestCase
{
    private function destFs(): array
    {
        $dir = sys_get_temp_dir().'/llmirror_'.uniqid();
        mkdir($dir, 0700, true);

        return [new Filesystem(new LocalFilesystemAdapter($dir)), $dir];
    }

    public function test_it_uploads_new_skips_existing_and_removes_deleted(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        Storage::disk('files')->put('vault/a', 'aaa');
        Storage::disk('files')->put('vault/b', 'bbb');

        [$dest, $dir] = $this->destFs();
        $step = fn (string $m) => null;
        $mirror = new DiskMirror;

        // First run: both uploaded.
        $r1 = $mirror->mirror($dest, 'vault', 'job-1', $step);
        $this->assertSame(2, $r1['source']);
        $this->assertSame(2, $r1['uploaded']);
        $this->assertTrue($dest->fileExists('job-1/vault/a'));
        $this->assertTrue($dest->fileExists('job-1/vault/b'));

        // Add one, remove one; second run uploads only the new, removes the gone.
        Storage::disk('files')->put('vault/c', 'ccc');
        Storage::disk('files')->delete('vault/a');

        $r2 = $mirror->mirror($dest, 'vault', 'job-1', $step);
        $this->assertSame(1, $r2['uploaded']); // only c
        $this->assertSame(1, $r2['removed']);  // a
        $this->assertFalse($dest->fileExists('job-1/vault/a'));
        $this->assertTrue($dest->fileExists('job-1/vault/c'));

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
    }
}
