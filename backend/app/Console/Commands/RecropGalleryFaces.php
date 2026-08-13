<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GalleryFace;
use App\Services\GalleryFaceProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Restore missing face-crop thumbnail files by re-cropping from each face's
 * source photo using its stored bounding box. Non-destructive: person links and
 * names are preserved (only the crop image is regenerated). Run synchronously
 * (no ML sidecar needed — cropping is local ImageMagick).
 */
class RecropGalleryFaces extends Command
{
    protected $signature = 'gallery:recrop {--limit=0 : Max faces to process (0 = all)} {--all : Recrop every face, not just those with a missing crop file}';

    protected $description = 'Regenerate missing gallery face-crop thumbnails from stored boxes';

    public function handle(GalleryFaceProcessor $processor): int
    {
        $disk = config('files.disk');
        $fs = Storage::disk(is_string($disk) ? $disk : 'files');
        $all = (bool) $this->option('all');
        $limit = (int) $this->option('limit');

        $done = 0;
        $failed = 0;
        $skipped = 0;

        GalleryFace::query()->orderBy('id')->chunkById(200, function ($faces) use ($processor, $fs, $all, $limit, &$done, &$failed, &$skipped): bool {
            foreach ($faces as $face) {
                $path = (string) $face->crop_path;
                $present = $path !== '' && $fs->exists($path);
                if ($present && ! $all) {
                    $skipped++;

                    continue;
                }
                if ($processor->recropFace($face)) {
                    $done++;
                } else {
                    $failed++;
                }
                if ($limit > 0 && $done >= $limit) {
                    return false;
                }
            }

            return true;
        });

        $this->info("Recropped {$done} face(s); {$failed} failed; {$skipped} already present.");

        return self::SUCCESS;
    }
}
