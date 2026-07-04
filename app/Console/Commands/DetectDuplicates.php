<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gallery\DuplicateDetector;
use Illuminate\Console\Command;

/**
 * Re-runs content-based duplicate detection over the whole gallery.
 */
class DetectDuplicates extends Command
{
    protected $signature = 'gallery:duplicates';

    protected $description = 'Cluster same/similar photos into duplicate groups';

    public function handle(DuplicateDetector $detector): int
    {
        $groups = $detector->run();
        $this->info("Formed {$groups} duplicate group(s).");

        return self::SUCCESS;
    }
}
