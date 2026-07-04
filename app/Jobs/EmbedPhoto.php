<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Photo;
use App\Services\Gallery\MachineLearning;
use App\Services\Gallery\PerceptualHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Computes a photo's CLIP embedding (via the ML sidecar) for content-similarity
 * duplicate detection and stores it in the pgvector column. Uses the medium
 * rendition (a JPEG; for videos this is the poster frame) so HEIC/large files
 * never hit the ML service directly.
 */
class EmbedPhoto implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 2;

    public function __construct(public int $photoId) {}

    public function handle(MachineLearning $ml, PerceptualHash $hasher): void
    {
        $photo = Photo::find($this->photoId);
        if ($photo === null) {
            return;
        }

        $needPhash = $photo->phash === null;
        $needEmbed = $ml->enabled() && $photo->embedded_at === null;
        if (! $needPhash && ! $needEmbed) {
            return;
        }

        $disk = Storage::disk(config('files.disk'));
        $path = $photo->medium_path ?: $photo->disk_path;
        if (! $disk->exists($path)) {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'embed');
        $stream = $disk->readStream($path);
        try {
            file_put_contents($tmp, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        try {
            if ($needPhash) {
                $hash = $hasher->hash($tmp);
                if ($hash !== null) {
                    $photo->forceFill(['phash' => $hash])->save();
                }
            }

            if ($needEmbed) {
                $vector = $ml->embed($tmp);
                if ($vector === null) {
                    return; // ML unavailable; a later backfill retries
                }

                if (DB::getDriverName() === 'pgsql') {
                    DB::update('UPDATE photos SET embedding = ?::vector, embedded_at = ? WHERE id = ?', [
                        '['.implode(',', $vector).']',
                        Carbon::now(),
                        $photo->id,
                    ]);
                } else {
                    // No pgvector (e.g. sqlite test DB): record that we embedded.
                    $photo->forceFill(['embedded_at' => Carbon::now()])->save();
                }
            }
        } finally {
            @unlink($tmp);
        }
    }
}
