<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Gallery\DuplicateDetector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs duplicate detection on the queue (triggered from gallery settings), so a
 * large library can be scanned without holding a request.
 */
class DetectDuplicatesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    // Only one detection run at a time: run() wipes then rebuilds every group,
    // so overlapping runs would see each other's half-nulled state.
    public int $uniqueFor = 1800;

    public function uniqueId(): string
    {
        return 'detect-duplicates';
    }

    public function handle(DuplicateDetector $detector): void
    {
        $detector->run();
    }
}
