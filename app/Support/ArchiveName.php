<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Zip-slip / path-traversal guard for archive entry names.
 *
 * Validates that an archive entry path does not escape the destination base
 * directory via ".." components, absolute paths or null bytes, then returns
 * the safe joined absolute path.
 */
final class ArchiveName
{
    /**
     * Return the safe absolute path formed by joining $base and $entry.
     *
     * @throws RuntimeException if $entry contains ".." components, is absolute,
     *                          contains null bytes, or would otherwise escape $base
     */
    public static function safe(string $base, string $entry): string
    {
        if (str_contains($entry, "\0")) {
            throw new RuntimeException("Unsafe archive entry path (null byte): {$entry}");
        }

        if (str_starts_with($entry, '/') || str_starts_with($entry, '\\')) {
            throw new RuntimeException("Unsafe archive entry path (absolute): {$entry}");
        }

        if (preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $entry)) {
            throw new RuntimeException("Unsafe archive entry path (directory traversal): {$entry}");
        }

        return $base.'/'.$entry;
    }

    private function __construct() {}
}
