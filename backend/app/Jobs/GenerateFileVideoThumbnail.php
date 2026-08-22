<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FileEntry;
use App\Support\BinaryProcess;
use App\Support\DiskTempFile;
use App\Support\ImageManagerFactory;
use App\Support\VideoProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;

/**
 * A poster frame for a video in the file browser, produced off the web request.
 *
 * Videos showed a generic icon while ffmpeg has been in the image for years and
 * the gallery has been pulling posters with it all along. It runs here rather
 * than in `thumb()` for the reason the gallery learned the hard way in
 * v1.611.0: decoding inline lets one folder of clips stampede the worker pool,
 * and unlike an image, a video is an untrusted container going through libav.
 *
 * Idempotent: a no-op when the cache already holds this file's version.
 */
class GenerateFileVideoThumbnail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    /** A poster frame is one seek; anything this large is not worth the decode. */
    private const MAX_SRC_BYTES = 2 * 1024 * 1024 * 1024;

    public function __construct(public int $fileId) {}

    public function handle(ImageManagerFactory $images): void
    {
        if (! BinaryProcess::available('ffmpeg')) {
            return;
        }

        // No auth in a queued context, so the owner global scope is a no-op and
        // a plain find resolves the row (thumbnailing is an internal operation).
        $file = FileEntry::query()->withoutGlobalScopes()->find($this->fileId);
        if (! $file instanceof FileEntry || ! str_starts_with((string) $file->mime, 'video/')) {
            return;
        }
        if ((int) $file->size > self::MAX_SRC_BYTES) {
            return;
        }

        $configured = config('files.disk');
        $disk = Storage::disk(is_string($configured) ? $configured : 'files');

        $thumbPath = 'files/thumb/'.$file->id.'-'.$file->version.'.webp';
        if ($disk->exists($thumbPath)) {
            return;
        }

        $src = (string) $file->storage_path;
        if ($src === '' || ! str_starts_with($src, 'files/') || ! $disk->exists($src)) {
            return;
        }

        // Stream to a temp path: ffmpeg needs to seek, which it cannot do
        // through a remote disk, and the whole file must not be held in memory.
        $tmp = DiskTempFile::create('llvthumb')->withExtension('vid');
        $in = $disk->readStream($src);
        $out = fopen($tmp->path(), 'wb');
        if (! is_resource($in) || $out === false) {
            return;
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $poster = DiskTempFile::create('llvposter')->withExtension('jpg');
        $probe = VideoProcessor::probe($tmp->path());
        $duration = is_array($probe) && is_numeric($probe['duration'] ?? null) ? (int) $probe['duration'] : null;
        if (! VideoProcessor::poster($tmp->path(), $poster->path(), $duration)) {
            return;
        }

        try {
            $webp = (string) $images->make()->decodePath($poster->path())
                ->cover(400, 400)->encode(new WebpEncoder(quality: 78));
            $disk->put($thumbPath, $webp);
        } catch (\Throwable) {
            // A clip whose first frame will not decode keeps its type icon.
        }
    }
}
