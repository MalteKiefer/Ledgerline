<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Services\Backup\Sources\FilesSource;
use App\Services\Backup\Sources\GallerySource;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A backup mirror uploads the disk prefix a source names; blobs are written to
 * that same prefix by the module that owns them. If those drift, the mirror
 * scans an empty prefix and silently backs up nothing — this locks them together.
 */
class BackupSourcePrefixTest extends TestCase
{
    private function protectedString(object $o, string $method): string
    {
        $m = new ReflectionMethod($o, $method);

        return (string) $m->invoke($o);
    }

    public function test_source_prefixes_match_where_blobs_are_written(): void
    {
        // Files (plaintext-relational core): FilesController stores bytes at
        // files/<uuid>, so the backup source must mirror the `files` prefix.
        $this->assertSame('files', $this->protectedString(new FilesSource, 'prefix'));

        // Gallery (plaintext-relational core): GalleryController stores photo bytes
        // + renditions under gallery/<uuid>, so the source must mirror `gallery`.
        $this->assertSame('gallery', $this->protectedString(new GallerySource, 'prefix'));
        $this->assertSame('gallery', $this->protectedString(new GallerySource, 'diskPrefix'));
    }
}
