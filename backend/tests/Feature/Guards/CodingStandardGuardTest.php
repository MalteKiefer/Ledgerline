<?php

declare(strict_types=1);

namespace Tests\Feature\Guards;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * strict_types is a project rule, and a rule that only lives in a document drifts
 * the moment a file is scaffolded by a generator. Pint enforces the formatting
 * (PSR-12); this enforces the one declaration Pint does not add for you.
 */
class CodingStandardGuardTest extends TestCase
{
    public function test_every_php_file_declares_strict_types(): void
    {
        $missing = [];
        foreach (['app', 'config', 'database', 'routes', 'tests'] as $dir) {
            $path = base_path($dir);
            if (! is_dir($path)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($it as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (! str_contains($source, 'declare(strict_types=1);')) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $missing,
            'PHP file(s) without declare(strict_types=1). Every file in the backend carries it; a generator '
            .'stub that skips it silently opts that file out of strict type checks.');
    }
}
