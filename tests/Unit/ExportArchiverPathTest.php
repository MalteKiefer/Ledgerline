<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Export\ExportArchiver;
use App\Services\Gallery\PhotoExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ExportArchiverPathTest extends TestCase
{
    #[DataProvider('malicious')]
    public function test_safe_path_never_escapes_the_zip_root(string $input): void
    {
        // ExportArchiver only needs its collaborator's type for construction here.
        $archiver = new ExportArchiver($this->createStub(PhotoExporter::class));
        $method = new ReflectionMethod($archiver, 'safePath');
        $method->setAccessible(true);

        $out = $method->invoke($archiver, $input);

        $this->assertStringStartsNotWith('/', $out, 'no leading slash');
        $this->assertNotContains('..', explode('/', $out), 'no traversal segment');
        $this->assertNotSame('', $out);
    }

    public static function malicious(): array
    {
        return [
            ['....//etc/passwd'],           // overlapping ../ reforms
            ['....//....//run_at_boot.sh'],
            ['../../x'],
            ['..\\..\\windows'],
            ['/abs/path'],
            ['a/../../b'],
            ['./././x'],
            ['normal/dir/file.txt'],        // legit path stays multi-segment
        ];
    }
}
