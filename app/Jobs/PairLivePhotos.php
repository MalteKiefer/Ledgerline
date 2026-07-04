<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pairs an Apple Live Photo whose still (HEIC/JPEG) and motion clip (MOV) were
 * uploaded as two files sharing a ContentIdentifier. The clip is attached to the
 * still as its motion clip and the standalone video row is retired, so the pair
 * shows as a single motion photo. Runs for both halves after their metadata is
 * read; a row lock keeps the two runs from pairing twice.
 */
class PairLivePhotos implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $photoId) {}

    public function handle(): void
    {
        $trigger = Photo::find($this->photoId);
        if ($trigger === null || $trigger->content_id === null) {
            return;
        }

        DB::transaction(function () use ($trigger): void {
            // Lock this owner's rows for the shared identifier so the still's and
            // the movie's jobs cannot both pair the pair.
            $rows = Photo::query()
                ->where('content_id', $trigger->content_id)
                ->where('uploaded_by', $trigger->uploaded_by)
                ->lockForUpdate()
                ->get();

            $still = $rows->firstWhere('media_type', 'image');
            $video = $rows->firstWhere('media_type', 'video');

            if ($still === null || $video === null) {
                return; // the other half has not been processed yet
            }

            if ($still->motion_path !== null) {
                return; // already has a motion clip (embedded or previously paired)
            }

            $disk = Storage::disk(config('files.disk'));
            if (! $disk->exists($video->disk_path)) {
                return;
            }

            $motionPath = dirname($still->disk_path)."/motion/{$still->uuid}.mp4";
            $disk->copy($video->disk_path, $motionPath);

            $still->forceFill([
                'motion_path' => $motionPath,
                'duration' => $video->duration,
            ])->save();

            // Retire the standalone movie so the pair shows as one motion photo.
            // Soft delete keeps its original recoverable from the trash.
            $video->delete();
        });
    }
}
