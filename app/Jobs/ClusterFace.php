<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Face;
use App\Services\Gallery\FaceClusterer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Assigns a newly detected face to a person (incremental clustering).
 */
class ClusterFace implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public string $faceId) {}

    public function handle(FaceClusterer $clusterer): void
    {
        $face = Face::find($this->faceId);
        if ($face !== null && $face->person_id === null) {
            $clusterer->assign($face);
        }
    }
}
