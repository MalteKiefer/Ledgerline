<?php

declare(strict_types=1);

namespace App\Services\Backup;

/**
 * A locally-staged backup file produced by a source, ready to (optionally)
 * encrypt and upload. $extension is the logical suffix (e.g. "sql.gz",
 * "tar.gz") used to name the remote object.
 */
final readonly class BackupArtifact
{
    public function __construct(
        public string $path,
        public string $extension,
    ) {}
}
