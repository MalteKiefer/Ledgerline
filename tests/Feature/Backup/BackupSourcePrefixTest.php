<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Http\Controllers\ContactBlobController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GalleryBlobController;
use App\Services\Backup\Sources\FilesSource;
use App\Services\Backup\Sources\GallerySource;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A backup mirror uploads the disk prefix a source names; blobs are written to
 * the prefix the blob controller's module() returns. If those drift, the mirror
 * scans an empty prefix and silently backs up nothing — this locks them together.
 */
class BackupSourcePrefixTest extends TestCase
{
    private function protectedString(object $o, string $method): string
    {
        $m = new ReflectionMethod($o, $method);

        return (string) $m->invoke($o);
    }

    public function test_source_prefixes_match_the_blob_controller_modules(): void
    {
        $this->assertSame(
            $this->protectedString(new FileController, 'module'),
            $this->protectedString(new FilesSource, 'prefix'),
            'FilesSource must mirror the same disk prefix FileController writes to.',
        );

        $this->assertSame(
            $this->protectedString(new GalleryBlobController, 'module'),
            $this->protectedString(new GallerySource, 'prefix'),
            'GallerySource must mirror the same disk prefix GalleryBlobController writes to.',
        );

        // Guard the concrete values too, so a rename of both in lockstep still trips.
        $this->assertSame('gallery', $this->protectedString(new GalleryBlobController, 'module'));
        $this->assertSame('contacts', $this->protectedString(new ContactBlobController, 'module'));
    }
}
