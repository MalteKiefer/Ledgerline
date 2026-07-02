<?php

declare(strict_types=1);

namespace App\Services\Backup\Sources;

use App\Services\Backup\BackupArtifact;

/**
 * Produces one backup artifact (a single local file) for a source.
 */
interface BackupSource
{
    /** Build the artifact inside $workDir and return it. */
    public function build(string $workDir): BackupArtifact;
}
