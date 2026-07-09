<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Face;
use App\Models\Photo;
use App\Services\Gallery\FaceCropper;
use App\Services\Gallery\MachineLearning;
use App\Support\BlobStore;
use App\Support\DiskTempFile;
use App\Support\Vector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Detects faces in a photo (or a video's poster frame) via the ML sidecar,
 * filters weak/tiny detections, stores a face crop thumbnail and a Face row per
 * face (with its embedding on Postgres), then queues clustering for each.
 */
class DetectFaces implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(public int $photoId) {}

    public function handle(MachineLearning $ml, FaceCropper $cropper): void
    {
        $photo = Photo::find($this->photoId);
        if ($photo === null || ! $ml->faceEnabled()) {
            return;
        }

        $disk = BlobStore::disk();
        $path = $photo->medium_path ?: $photo->disk_path;
        if (! $disk->exists($path)) {
            return;
        }

        $tmp = DiskTempFile::pull($disk, $path, 'face');

        try {
            $faces = $ml->detectFaces($tmp);
            [$imgW, $imgH] = @getimagesize($tmp) ?: [0, 0];
            $minScore = (float) config('gallery.face_min_score', 0.7);
            $minSize = (int) config('gallery.face_min_size', 32);

            // Idempotent: drop the photo's previous faces + their thumbs first.
            foreach (Face::where('photo_id', $photo->id)->get() as $old) {
                if ($old->thumb_path) {
                    $disk->delete($old->thumb_path);
                }
                $old->delete();
            }

            foreach ($faces as $f) {
                if ($f['score'] < $minScore) {
                    continue;
                }
                $faceH = ($f['box'][3] - $f['box'][1]) * ($imgH ?: 1);
                $faceW = ($f['box'][2] - $f['box'][0]) * ($imgW ?: 1);
                if ($imgH > 0 && min($faceH, $faceW) < $minSize) {
                    continue;
                }

                $face = Face::create([
                    // Faces inherit the photo's owner (job runs without an auth
                    // context, so set it explicitly for per-user clustering).
                    'user_id' => $photo->uploaded_by,
                    'photo_id' => $photo->id,
                    'det_score' => $f['score'],
                    'box_x1' => $f['box'][0], 'box_y1' => $f['box'][1],
                    'box_x2' => $f['box'][2], 'box_y2' => $f['box'][3],
                ]);

                $crop = $cropper->crop($tmp, $f['box']);
                if ($crop !== null) {
                    $thumb = "faces/{$photo->id}/{$face->id}.jpg";
                    $disk->put($thumb, $crop);
                    $face->forceFill(['thumb_path' => $thumb])->save();
                }

                if (Vector::available()) {
                    Vector::store('faces', $face->id, $f['embedding']);
                }

                ClusterFace::dispatch($face->id);
            }
        } finally {
            @unlink($tmp);
        }
    }
}
