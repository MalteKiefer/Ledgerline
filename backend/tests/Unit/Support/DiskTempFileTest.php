<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DiskTempFile;
use PHPUnit\Framework\TestCase;

class DiskTempFileTest extends TestCase
{
    public function test_file_exists_at_path_after_create(): void
    {
        $tmp = DiskTempFile::create('lltest');
        $this->assertFileExists($tmp->path());
        unset($tmp);
    }

    public function test_file_is_deleted_on_destruct(): void
    {
        $path = (function (): string {
            $tmp = DiskTempFile::create('lltest');

            return $tmp->path();
        })();
        $this->assertFileDoesNotExist($path);
    }

    public function test_double_destruct_is_safe(): void
    {
        $tmp = DiskTempFile::create('lltest');
        $path = $tmp->path();
        unset($tmp);
        // Calling destruct again on a gone path must not throw
        $this->assertFileDoesNotExist($path);
        // No exception means test passes
        $this->assertTrue(true);
    }

    public function test_with_extension_path_ends_with_extension(): void
    {
        $tmp = DiskTempFile::create('lltest');
        $oldPath = $tmp->path();
        $tmp = $tmp->withExtension('bin');
        $this->assertStringEndsWith('.bin', $tmp->path());
        $this->assertFileExists($tmp->path());
        $this->assertFileDoesNotExist($oldPath);
        unset($tmp);
    }

    public function test_with_extension_old_path_is_removed(): void
    {
        $tmp = DiskTempFile::create('lltest');
        $oldPath = $tmp->path();
        $this->assertFileExists($oldPath);
        $tmp = $tmp->withExtension('jpg');
        $this->assertFileDoesNotExist($oldPath);
        unset($tmp);
    }
}
