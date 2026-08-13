<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * RAII wrapper for a temporary disk file. The file is created on construction
 * and automatically unlinked on destruct — even if an exception short-circuits
 * the owning scope. This guarantees no transient plaintext survives a throw in
 * zero-knowledge contexts.
 */
final class DiskTempFile
{
    private ?string $path;

    private function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Create a new temporary file and return a RAII handle to it.
     *
     * @throws RuntimeException if tempnam fails
     */
    public static function create(string $prefix = 'll'): self
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Failed to create temporary file.');
        }

        return new self($path);
    }

    /** The current filesystem path of this temporary file. */
    public function path(): string
    {
        if ($this->path === null) {
            throw new RuntimeException('DiskTempFile has already been unlinked.');
        }

        return $this->path;
    }

    /**
     * Rename the underlying file to have the given extension and return a new
     * handle. The old path (the extensionless tempnam base) is unlinked. The
     * new path inherits the same RAII guarantee: it is deleted on destruct.
     */
    public function withExtension(string $ext): self
    {
        if ($this->path === null) {
            throw new RuntimeException('DiskTempFile has already been unlinked.');
        }

        $newPath = $this->path.'.'.$ext;
        if (! rename($this->path, $newPath)) {
            throw new RuntimeException("Failed to rename temp file to extension .{$ext}.");
        }

        $this->path = null; // old path is gone; prevent double-unlink in our destructor

        return new self($newPath);
    }

    /** Unlink the file. Idempotent and exception-safe (safe to call from __destruct). */
    public function __destruct()
    {
        if ($this->path !== null && is_file($this->path)) {
            @unlink($this->path);
        }
        $this->path = null;
    }
}
