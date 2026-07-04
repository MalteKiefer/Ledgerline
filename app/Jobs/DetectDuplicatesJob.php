<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Gallery\DuplicateDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs duplicate detection on the queue (triggered from gallery settings), so a
 * large library can be scanned without holding a request.
 */
class DetectDuplicatesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function handle(DuplicateDetector $detector): void
    {
        $detector->run();
    }
}
