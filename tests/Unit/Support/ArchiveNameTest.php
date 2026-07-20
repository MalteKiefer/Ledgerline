<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ArchiveName;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ArchiveNameTest extends TestCase
{
    public function test_rejects_dotdot_component(): void
    {
        $this->expectException(RuntimeException::class);
        ArchiveName::safe('/tmp/base', '../etc/passwd');
    }

    public function test_rejects_embedded_dotdot(): void
    {
        $this->expectException(RuntimeException::class);
        ArchiveName::safe('/tmp/base', 'foo/../etc/passwd');
    }

    public function test_rejects_absolute_path(): void
    {
        $this->expectException(RuntimeException::class);
        ArchiveName::safe('/tmp/base', '/etc/passwd');
    }

    public function test_rejects_null_byte(): void
    {
        $this->expectException(RuntimeException::class);
        ArchiveName::safe('/tmp/base', "foo\0bar");
    }

    public function test_accepts_normal_filename(): void
    {
        $result = ArchiveName::safe('/tmp/base', 'file.txt');
        $this->assertSame('/tmp/base/file.txt', $result);
    }

    public function test_accepts_nested_path(): void
    {
        $result = ArchiveName::safe('/tmp/base', 'subdir/file.txt');
        $this->assertSame('/tmp/base/subdir/file.txt', $result);
    }

    public function test_returns_safe_joined_absolute_path(): void
    {
        $result = ArchiveName::safe('/var/staging', 'objects/abc123');
        $this->assertSame('/var/staging/objects/abc123', $result);
    }
}
