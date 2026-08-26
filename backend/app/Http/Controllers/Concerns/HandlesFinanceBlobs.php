<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * The finance module's document bytes: where they live, and the guard every
 * stream and delete goes through.
 *
 * Shared so invoices and quotes cannot end up with two different ideas of what
 * a safe blob path is. Every real path is server-generated (`invoices/{uuid}`);
 * the prefix check is defence in depth for the case where one ever is not.
 */
trait HandlesFinanceBlobs
{
    /** Where every finance document blob lives. */
    protected function financeDisk(): string
    {
        $d = config('files.disk');

        return is_string($d) ? $d : 'files';
    }

    protected function fs(): Filesystem
    {
        return Storage::disk($this->financeDisk());
    }

    protected function maxUploadKb(): int
    {
        $mb = config('files.max_upload_mb', 2048);

        return (is_numeric($mb) ? (int) $mb : 2048) * 1024;
    }

    /** A filename safe to put in a Content-Disposition header. */
    protected function safeName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'file' : $clean;
    }

    /**
     * Confine a blob path to the finance prefix; reject traversal, NUL bytes and
     * anything absolute.
     *
     * Quotes share the `invoices/` prefix rather than getting their own: the
     * prefix is what the guard checks, and a second one would mean a second
     * guard to keep in step with this one for no gain — the paths are UUIDs and
     * never collide.
     */
    protected function safeBlobPath(mixed $path): ?string
    {
        return is_string($path)
            && str_starts_with($path, 'invoices/')
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0")
            ? $path
            : null;
    }
}
