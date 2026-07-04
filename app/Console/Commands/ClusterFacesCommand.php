<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gallery\FaceClusterer;
use Illuminate\Console\Command;

/**
 * Rebuilds face clustering across the whole library (preserving manual pins).
 * Run after changing the grouping threshold.
 */
class ClusterFacesCommand extends Command
{
    protected $signature = 'gallery:cluster';

    protected $description = 'Rebuild people clusters from detected faces';

    public function handle(FaceClusterer $clusterer): int
    {
        $people = $clusterer->recluster();
        $this->info("Clustered into {$people} person/people.");

        return self::SUCCESS;
    }
}
