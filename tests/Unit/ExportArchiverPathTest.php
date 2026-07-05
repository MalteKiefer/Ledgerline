<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ArchiveName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExportArchiverPathTest extends TestCase
{
    #[DataProvider('malicious')]
    public function test_safe_path_never_escapes_the_zip_root(string $input): void
    {
        // The zip-slip scrub now lives in the shared ArchiveName::sanitize().
        $out = ArchiveName::sanitize($input);

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
