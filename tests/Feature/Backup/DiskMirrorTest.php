<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\Backup\BackupCancelled;
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

    public function test_delta_uploads_only_the_given_blobs_and_tolerates_missing(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        Storage::disk('files')->put('photos/x', 'xxx');
        Storage::disk('files')->put('photos/y', 'yyy');
        // 'z' is in the ledger list but already gone from disk.

        [$dest, $dir] = $this->destFs();
        $mirror = new DiskMirror;

        $r = $mirror->delta($dest, 'photos', 'job-1', ['x', 'y', 'z'], fn (string $m) => null);
        $this->assertSame(2, $r['uploaded']);
        $this->assertSame(1, $r['missing']);
        $this->assertTrue($dest->fileExists('job-1/photos/x'));
        $this->assertTrue($dest->fileExists('job-1/photos/y'));
        $this->assertFalse($dest->fileExists('job-1/photos/z'));

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
    }

    public function test_a_cancel_rolls_back_objects_uploaded_so_far(): void
    {
        Storage::fake('files');
        config(['files.disk' => 'files']);
        // More than the 50-object checkpoint interval so the cancel check fires.
        for ($i = 0; $i < 60; $i++) {
            Storage::disk('files')->put('vault/blob-'.$i, str_repeat('x', 8));
        }

        [$dest, $dir] = $this->destFs();
        $mirror = new DiskMirror;
        $cancel = fn () => throw new BackupCancelled('stop');

        $this->expectException(BackupCancelled::class);
        try {
            $mirror->mirror($dest, 'vault', 'job-1', fn (string $m) => null, $cancel);
        } finally {
            // Every object written before the cancel must be gone again.
            $left = array_filter(
                iterator_to_array($dest->listContents('job-1', true)),
                fn ($item) => $item->isFile(),
            );
            $this->assertCount(0, $left);
            (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
        }
    }
}
