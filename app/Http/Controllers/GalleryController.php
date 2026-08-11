<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\EmbedGalleryPhoto;
use App\Jobs\GenerateGalleryThumbnail;
use App\Jobs\ProcessGalleryVideo;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Support\BlobStore;
use App\Support\DiskTempFile;
use App\Support\FilesUsage;
use App\Support\ImageManagerFactory;
use App\Support\MachineLearning;
use App\Support\Vector;
use App\Support\VideoProcessor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Direction;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gallery module (plaintext-relational, phase 1). Photos as owner-scoped rows;
 * bytes live plaintext on the files disk under gallery/{uuid}. Whole + chunked
 * upload, server-side WebP thumbnails, sandboxed originals, favorite + a
 * soft-delete recycle bin. One guard-agnostic controller for web + /api/v1.
 *
 * Reuses the Files thumbnail canon (ImageManagerFactory + pixel/byte guards +
 * DiskTempFile) and BlobStore for immutable serving.
 */
class GalleryController extends Controller
{
    private const PART_SIZE = 8 * 1024 * 1024;

    private const THUMB_MAX_SRC_BYTES = 40 * 1024 * 1024;

    private const THUMB_MAX_PIXELS = 100 * 1000 * 1000;

    private const MOTION_ALLOW = ['mov', 'mp4', 'm4v', 'qt'];

    /** Broad video extension set — "any format" uploads; ffprobe validates later. */
    private const VIDEO_EXT = [
        'mp4', 'm4v', 'mov', 'qt', 'webm', 'mkv', 'avi', 'wmv', 'flv', 'mpg', 'mpeg',
        '3gp', '3g2', 'm2ts', 'mts', 'ts', 'ogv', 'vob', 'mxf', 'asf', 'rm', 'rmvb', 'divx',
    ];

    // ---- Listings ----

    public function data(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $query = GalleryPhoto::query();
        $albumId = $request->integer('album_id');
        if ($albumId > 0) {
            $query->whereHas('albums', fn ($q) => $q->whereKey($albumId));
        }
        $photos = $query->orderByRaw('COALESCE(taken_at, created_at) DESC')->orderByDesc('id')->get()
            ->map(fn (GalleryPhoto $p): array => $this->row($p))->all();

        return response()->json(['photos' => $photos]);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $photos = GalleryPhoto::onlyTrashed()->orderByDesc('deleted_at')->get()
            ->map(fn (GalleryPhoto $p): array => $this->row($p))->all();

        return response()->json(['photos' => $photos]);
    }

    // ---- Upload (whole) ----

    public function upload(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'file' => ['required', 'file', 'max:'.$this->maxUploadKb()],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        $isImage = str_starts_with($mime, 'image/');
        $isVideo = ! $isImage && $this->looksLikeVideo($mime, $upload->getClientOriginalName());
        if (! $isImage && ! $isVideo) {
            abort(415);
        }
        if ($over = $this->overQuota($uid, (int) $upload->getSize())) {
            return $over;
        }

        $real = $upload->getRealPath();
        $sha = is_string($real) ? (hash_file('sha256', $real) ?: null) : null;
        if (($dupe = $this->findDuplicate($uid, $sha)) !== null) {
            return response()->json(['photo' => $this->row($dupe), 'duplicate' => true], 200);
        }
        $path = 'gallery/'.Str::uuid()->toString();
        $this->fs()->putFileAs('gallery', $upload, basename($path));
        $name = $upload->getClientOriginalName();
        $name = $name !== '' ? $name : ($isVideo ? 'video' : 'photo');
        $size = (int) $this->fs()->size($path);

        if ($isVideo) {
            $photo = DB::transaction(fn (): GalleryPhoto => $this->persist(
                $uid, $name, $path, $size, $mime, $sha, null, null, [], 'video', 'processing'
            ));
            ProcessGalleryVideo::dispatch($photo->id);

            return response()->json(['photo' => $this->row($photo)], 201);
        }

        $dims = is_string($real) ? @getimagesize($real) : false;
        $meta = is_string($real) ? $this->extractExif($real) : [];
        $photo = DB::transaction(fn (): GalleryPhoto => $this->persist(
            $uid, $name, $path, $size, $mime, $sha,
            is_array($dims) ? (int) $dims[0] : null,
            is_array($dims) ? (int) $dims[1] : null,
            $meta,
        ));
        $this->attachEmbeddedMotion($photo, is_string($real) ? $real : null, $mime);
        GenerateGalleryThumbnail::dispatch($photo->id);
        EmbedGalleryPhoto::dispatch($photo->id);

        return response()->json(['photo' => $this->row($photo)], 201);
    }

    // ---- Upload (chunked) ----

    public function chunkInit(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['name' => ['required', 'string', 'max:500'], 'size' => ['required', 'integer', 'min:0']]);
        if ($over = $this->overQuota($uid, $request->integer('size'))) {
            return $over;
        }
        $id = Str::uuid()->toString();
        Cache::put($this->sessionKey($uid, $id), ['name' => $request->string('name')->value(), 'received' => 0], now()->addHours(6));

        return response()->json(['id' => $id, 'partSize' => self::PART_SIZE], 201);
    }

    public function chunkPart(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'id' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0'],
            'file' => ['required', 'file', 'max:'.((int) ceil(self::PART_SIZE / 1024) + 64)],
        ]);
        $id = $request->string('id')->value();
        $key = $this->sessionKey($uid, $id);
        /** @var array{name:string, received?:int}|null $session */
        $session = Cache::get($key);
        if ($session === null) {
            abort(404);
        }
        $part = $request->file('file');
        if (! $part instanceof UploadedFile) {
            abort(422);
        }
        $received = (int) ($session['received'] ?? 0) + (int) $part->getSize();
        if ($over = $this->overQuota($uid, $received)) {
            $this->fs()->deleteDirectory($this->tmpDir($uid, $id));
            Cache::forget($key);

            return $over;
        }
        $session['received'] = $received;
        Cache::put($key, $session, now()->addHours(6));
        $part->storeAs($this->tmpDir($uid, $id), (string) $request->integer('index'), ['disk' => $this->disk()]);

        return response()->json(['ok' => true, 'index' => $request->integer('index')]);
    }

    public function chunkComplete(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['id' => ['required', 'string']]);
        $id = $request->string('id')->value();
        /** @var array{name:string}|null $session */
        $session = Cache::get($this->sessionKey($uid, $id));
        if ($session === null) {
            abort(404);
        }

        $tmpDir = $this->tmpDir($uid, $id);
        $parts = $this->fs()->files($tmpDir);
        usort($parts, fn (string $a, string $b): int => (int) basename($a) <=> (int) basename($b));

        $tmp = DiskTempFile::create('llgup');
        $out = fopen($tmp->path(), 'wb');
        if ($out === false) {
            abort(500);
        }
        $ctx = hash_init('sha256');
        $size = 0;
        foreach ($parts as $partPath) {
            $in = $this->fs()->readStream($partPath);
            if (! is_resource($in)) {
                continue;
            }
            while (! feof($in)) {
                $buf = fread($in, 1 << 20);
                if ($buf === false) {
                    break;
                }
                fwrite($out, $buf);
                hash_update($ctx, $buf);
                $size += strlen($buf);
            }
            fclose($in);
        }
        fclose($out);
        $sha = hash_final($ctx);

        if ($over = $this->overQuota($uid, $size)) {
            $this->fs()->deleteDirectory($tmpDir);
            Cache::forget($this->sessionKey($uid, $id));

            return $over;
        }

        $mime = $this->sniffMime($tmp->path());
        $isImage = $mime !== null && str_starts_with($mime, 'image/');
        $isVideo = ! $isImage && $this->looksLikeVideo($mime, $session['name']);
        if (! $isImage && ! $isVideo) {
            $this->fs()->deleteDirectory($tmpDir);
            Cache::forget($this->sessionKey($uid, $id));
            abort(415);
        }
        if (($dupe = $this->findDuplicate($uid, $sha)) !== null) {
            $this->fs()->deleteDirectory($tmpDir);
            Cache::forget($this->sessionKey($uid, $id));

            return response()->json(['photo' => $this->row($dupe), 'duplicate' => true], 200);
        }
        $dims = $isVideo ? false : @getimagesize($tmp->path());
        $meta = $isVideo ? [] : $this->extractExif($tmp->path());

        $path = 'gallery/'.Str::uuid()->toString();
        $stream = fopen($tmp->path(), 'rb');
        if ($stream === false) {
            abort(500);
        }
        $this->fs()->writeStream($path, $stream);
        fclose($stream);

        $name = $session['name'] !== '' ? $session['name'] : ($isVideo ? 'video' : 'photo');
        $photo = DB::transaction(fn (): GalleryPhoto => $this->persist(
            $uid, $name, $path, $size, $mime, $sha,
            is_array($dims) ? (int) $dims[0] : null,
            is_array($dims) ? (int) $dims[1] : null,
            $meta,
            $isVideo ? 'video' : 'image',
            $isVideo ? 'processing' : 'ready',
        ));
        if ($isVideo) {
            $this->fs()->deleteDirectory($tmpDir);
            Cache::forget($this->sessionKey($uid, $id));
            ProcessGalleryVideo::dispatch($photo->id);

            return response()->json(['photo' => $this->row($photo)], 201);
        }
        // $tmp still holds the assembled bytes (unlinked on scope exit) — extract an
        // embedded Android/Samsung Motion Photo clip before it goes away.
        $this->attachEmbeddedMotion($photo, $tmp->path(), $mime);
        $this->fs()->deleteDirectory($tmpDir);
        Cache::forget($this->sessionKey($uid, $id));
        GenerateGalleryThumbnail::dispatch($photo->id);
        EmbedGalleryPhoto::dispatch($photo->id);

        return response()->json(['photo' => $this->row($photo)], 201);
    }

    public function chunkAbort(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['id' => ['required', 'string']]);
        $id = $request->string('id')->value();
        $this->fs()->deleteDirectory($this->tmpDir($uid, $id));
        Cache::forget($this->sessionKey($uid, $id));

        return response()->json(['ok' => true]);
    }

    // ---- Serve ----

    public function raw(Request $request, GalleryPhoto $photo): StreamedResponse
    {
        $this->requireUser($request);
        $src = (string) $photo->storage_path;
        abort_unless(str_starts_with($src, 'gallery/') && $this->fs()->exists($src), 404);
        $etag = $photo->sha256 !== null && $photo->sha256 !== '' ? $photo->sha256 : (string) $photo->id;

        return BlobStore::immutableResponse($this->fs()->response($src, $this->safeName($photo->name), [], 'inline'), $etag);
    }

    /** Stream a Live Photo's motion clip (sandboxed). Best-effort playback — the
     *  clip is stored as received (no transcode; ffmpeg is deliberately not in
     *  the image), so a QuickTime .MOV plays in Safari but may not in every browser. */
    public function motion(Request $request, GalleryPhoto $photo): StreamedResponse
    {
        $this->requireUser($request);
        $src = (string) $photo->motion_path;
        abort_unless($src !== '' && str_starts_with($src, 'gallery/') && $this->fs()->exists($src), 404);

        return $this->fs()->response($src, 'motion.mp4', [
            'Content-Type' => 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    /**
     * Stream a video for playback: the web-friendly rendition when one was
     * produced, else the original (already playable). Range requests are honored
     * for seeking when the disk is local (BinaryFileResponse).
     */
    public function play(Request $request, GalleryPhoto $photo): SymfonyResponse
    {
        $this->requireUser($request);
        abort_unless($photo->media_type === 'video', 404);
        $rel = $this->safeBlobPath($photo->playback_path) ?? $this->safeBlobPath($photo->storage_path);
        abort_if($rel === null || ! $this->fs()->exists($rel), 404);

        $type = $photo->playback_path !== null && $photo->playback_path !== ''
            ? 'video/mp4'
            : ((string) $photo->mime !== '' ? (string) $photo->mime : 'video/mp4');
        $headers = [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ];

        $abs = $this->localPath($rel);
        if ($abs !== null) {
            return response()->file($abs, $headers); // BinaryFileResponse → Range/seek support
        }

        return $this->fs()->response($rel, 'video.mp4', $headers, 'inline');
    }

    /**
     * Process an uploaded video (worker, via ProcessGalleryVideo): ffprobe for
     * metadata, extract a poster frame + thumbnail, and produce a web-friendly
     * MP4 rendition when the source is not directly playable. Idempotent-ish;
     * any failure marks the photo failed (original kept).
     */
    public function processVideo(GalleryPhoto $photo, ImageManagerFactory $images): void
    {
        if ($photo->media_type !== 'video') {
            return;
        }
        $srcRel = (string) $photo->storage_path;
        $abs = $this->localPath($srcRel);
        $stage = null;
        try {
            if ($abs === null) {
                // Remote disk → stage the original to a local temp for ffmpeg.
                $stage = DiskTempFile::create('llgvin');
                $in = $this->fs()->readStream($srcRel);
                $dst = fopen($stage->path(), 'wb');
                if (is_resource($in) && $dst !== false) {
                    stream_copy_to_stream($in, $dst);
                    fclose($in);
                    fclose($dst);
                    $abs = $stage->path();
                }
            }
            if ($abs === null || ! VideoProcessor::available()) {
                $photo->forceFill(['status' => 'failed'])->save();

                return;
            }
            $probe = VideoProcessor::probe($abs);
            if ($probe === null) {
                $photo->forceFill(['status' => 'failed'])->save();

                return;
            }

            // Poster frame → thumbnail source.
            $posterRel = null;
            $posterTmp = DiskTempFile::create('llgvp')->withExtension('jpg');
            if (VideoProcessor::poster($abs, $posterTmp->path(), $probe['duration'])) {
                $posterRel = 'gallery/'.Str::uuid()->toString().'-poster.jpg';
                $ph = fopen($posterTmp->path(), 'rb');
                if ($ph !== false) {
                    $this->fs()->writeStream($posterRel, $ph);
                    fclose($ph);
                }
            }

            // Web-friendly playback rendition when needed.
            $playRel = null;
            $plan = VideoProcessor::playbackPlan($probe);
            if ($plan !== 'none') {
                $mvTmp = DiskTempFile::create('llgvt')->withExtension('mp4');
                $ok = $plan === 'remux'
                    ? VideoProcessor::remux($abs, $mvTmp->path())
                    : VideoProcessor::transcode($abs, $mvTmp->path());
                if (! $ok && $plan === 'remux') {
                    $ok = VideoProcessor::transcode($abs, $mvTmp->path()); // remux failed → re-encode
                }
                if ($ok) {
                    $playRel = 'gallery/'.Str::uuid()->toString().'-play.mp4';
                    $vh = fopen($mvTmp->path(), 'rb');
                    if ($vh !== false) {
                        $this->fs()->writeStream($playRel, $vh);
                        fclose($vh);
                    }
                }
            }

            $photo->forceFill([
                'duration' => $probe['duration'],
                'width' => $probe['width'],
                'height' => $probe['height'],
                'poster_path' => $posterRel,
                'playback_path' => $playRel,
                'status' => $posterRel !== null ? 'ready' : 'failed',
            ])->save();

            if ($posterRel !== null) {
                $this->generateThumb($photo, $images);
                EmbedGalleryPhoto::dispatch($photo->id); // embed the poster frame
            }
        } catch (\Throwable) {
            $photo->forceFill(['status' => 'failed'])->save();
        }
    }

    /**
     * Compute + store a photo's CLIP embedding (worker, via EmbedGalleryPhoto).
     * For a video the poster frame is embedded. No-op when ML/pgvector are off.
     */
    public function embedPhoto(GalleryPhoto $photo, MachineLearning $ml): void
    {
        if (! $ml->enabled() || ! Vector::available()) {
            return;
        }
        $rel = $photo->media_type === 'video' ? (string) $photo->poster_path : (string) $photo->storage_path;
        if ($rel === '' || ! $this->fs()->exists($rel)) {
            return;
        }
        $abs = $this->localPath($rel);
        $stage = null;
        if ($abs === null) {
            $stage = DiskTempFile::create('llgem');
            $in = $this->fs()->readStream($rel);
            $dst = fopen($stage->path(), 'wb');
            if (is_resource($in) && $dst !== false) {
                stream_copy_to_stream($in, $dst);
                fclose($in);
                fclose($dst);
                $abs = $stage->path();
            }
        }
        if ($abs === null) {
            return;
        }
        $vec = $ml->embed($abs);
        if ($vec === null || count($vec) !== 512) {
            return;
        }
        DB::update(
            'UPDATE gallery_photos SET embedding = ?::vector, embedded_at = now() WHERE id = ?',
            [MachineLearning::toVectorLiteral($vec), $photo->id],
        );
    }

    /**
     * Semantic search: embed the query into CLIP space and return the nearest
     * photos by cosine distance (pgvector). Empty when ML/pgvector are off or the
     * query does not embed — the client then falls back to a name filter.
     */
    public function search(Request $request, MachineLearning $ml): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $q = trim($request->string('q')->value());
        if ($q === '' || ! $ml->enabled() || ! Vector::available()) {
            return response()->json(['photos' => []]);
        }
        $vec = $ml->embedText($q);
        if ($vec === null || count($vec) !== 512) {
            return response()->json(['photos' => []]);
        }
        $lit = MachineLearning::toVectorLiteral($vec);
        $maxCfg = config('ml.search_max_distance', 0.78);
        $max = is_numeric($maxCfg) ? (float) $maxCfg : 0.78;
        $rows = DB::select(
            'SELECT id FROM gallery_photos
             WHERE user_id = ? AND deleted_at IS NULL AND embedding IS NOT NULL
               AND (embedding <=> ?::vector) < ?
             ORDER BY embedding <=> ?::vector LIMIT 80',
            [$uid, $lit, $max, $lit],
        );
        $ids = array_values(array_filter(array_map(
            static fn ($r): int => is_object($r) && isset($r->id) && is_numeric($r->id) ? (int) $r->id : 0,
            $rows,
        ), static fn (int $i): bool => $i > 0));
        if ($ids === []) {
            return response()->json(['photos' => []]);
        }
        $byId = GalleryPhoto::query()->whereIn('id', $ids)->get()->keyBy('id');
        $photos = [];
        foreach ($ids as $id) {
            $p = $byId->get($id);
            if ($p instanceof GalleryPhoto) {
                $photos[] = $this->row($p);
            }
        }

        return response()->json(['photos' => $photos]);
    }

    /**
     * Serve a photo's WebP thumbnail — CACHE ONLY. A HEIC decode is ~20s, so the
     * web path never generates inline (a grid of N photos would stampede the FPM
     * pool). On a cache miss the generation job is (re)queued and a 404 is
     * returned; the SPA renders a spinner (photo.thumb=false) and reloads when it
     * is ready. Thumbnails are produced on upload/edit by GenerateGalleryThumbnail.
     */
    public function thumb(Request $request, GalleryPhoto $photo): StreamedResponse
    {
        $this->requireUser($request);
        $isImage = str_starts_with((string) $photo->mime, 'image/');
        abort_unless($isImage || $photo->media_type === 'video', 404);

        $thumbPath = $this->thumbPath($photo);
        if (! $this->fs()->exists($thumbPath)) {
            // Only (re)queue when a decode source exists: an image, or a video
            // whose poster frame has been extracted. A still-processing video has
            // no poster yet → the video job will generate the thumb.
            if ($isImage || ($photo->poster_path !== null && $photo->poster_path !== '')) {
                GenerateGalleryThumbnail::dispatch($photo->id);
            }
            abort(404);
        }

        return $this->fs()->response($thumbPath, 'thumb.webp', [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    private function thumbPath(GalleryPhoto $photo): string
    {
        return 'gallery/thumb/'.$photo->id.'-'.$photo->version.'.webp';
    }

    /**
     * Produce and cache the WebP thumbnail (idempotent). Called off the web path
     * by GenerateGalleryThumbnail (worker: high memory, long timeout). Returns
     * true on success; any failure returns false (never throws/aborts).
     */
    public function generateThumb(GalleryPhoto $photo, ImageManagerFactory $images): bool
    {
        // For a video the decode source is its extracted poster frame (a JPEG),
        // not the video bytes; for an image it's the original (byte/mime-guarded).
        if ($photo->media_type === 'video') {
            $src = (string) $photo->poster_path;
        } else {
            $mime = (string) $photo->mime;
            if (! str_starts_with($mime, 'image/') || (int) $photo->size > self::THUMB_MAX_SRC_BYTES) {
                return false;
            }
            $src = (string) $photo->storage_path;
        }
        $thumbPath = $this->thumbPath($photo);
        if ($this->fs()->exists($thumbPath)) {
            return true;
        }
        if ($src === '' || ! $this->fs()->exists($src)) {
            return false;
        }
        try {
            $tmp = DiskTempFile::create('llgthumb')->withExtension('img');
            $in = $this->fs()->readStream($src);
            $dst = fopen($tmp->path(), 'wb');
            if (! is_resource($in) || $dst === false) {
                return false;
            }
            stream_copy_to_stream($in, $dst);
            fclose($in);
            fclose($dst);
            $dims = @getimagesize($tmp->path());
            if (is_array($dims) && (int) $dims[0] * (int) $dims[1] > self::THUMB_MAX_PIXELS) {
                return false;
            }
            $img = $this->applyEdits($images->make()->decodePath($tmp->path()), $photo);
            $webp = (string) $img->cover(400, 400)->encode(new WebpEncoder(quality: 78));
            $this->fs()->put($thumbPath, $webp);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    // ---- Mutations ----

    public function favorite(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $this->requireUser($request);
        $photo->forceFill(['favorite' => $request->boolean('favorite')])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Attach a Live Photo's motion clip (.MOV/.MP4) to an existing still, so the
     * pair lands as ONE gallery entry instead of two. The client resolves the
     * matching still (by base name / already in the library) and posts the clip
     * here. Video-only, quota-counted; replaces any prior clip.
     */
    public function attachMotion(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', self::MOTION_ALLOW), 'max:'.$this->maxUploadKb()],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        if (! str_starts_with($mime, 'video/')) {
            abort(415);
        }
        if ($over = $this->overQuota($uid, (int) $upload->getSize())) {
            return $over;
        }

        $real = $upload->getRealPath();
        $contentId = is_string($real) ? $this->extractQuickTimeContentId($real) : null;

        // Replace any existing clip so re-attaching never leaks a blob.
        $old = $this->safeBlobPath($photo->motion_path);
        $path = 'gallery/'.Str::uuid()->toString().'-mv';
        $this->fs()->putFileAs('gallery', $upload, basename($path));
        $photo->forceFill([
            'motion_path' => $path,
            'content_id' => $contentId ?? $photo->content_id,
        ])->save();
        if ($old !== null && $old !== $path) {
            $this->fs()->delete($old);
        }

        return response()->json(['photo' => $this->row($photo)]);
    }

    /**
     * Non-invasive light edit: capture date/time, place + coordinates, rotation
     * and horizontal mirror. The original bytes are never rewritten — rotation/
     * flip are baked only into the thumbnail (regenerated via the version bump)
     * and the "edited" download variant. Optimistic version → 409 on mismatch.
     */
    public function update(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $this->requireUser($request);
        $request->validate([
            'taken_at' => ['sometimes', 'nullable', 'date'],
            'place' => ['sometimes', 'nullable', 'string', 'max:500'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'rotation' => ['sometimes', 'integer', 'in:0,90,180,270'],
            'flip_h' => ['sometimes', 'boolean'],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);

        return DB::transaction(function () use ($request, $photo): JsonResponse {
            /** @var GalleryPhoto $fresh */
            $fresh = GalleryPhoto::query()->whereKey($photo->getKey())->lockForUpdate()->firstOrFail();
            if ($request->has('version') && $request->integer('version') !== (int) $fresh->version) {
                return response()->json(['error' => 'version_conflict', 'version' => $fresh->version], 409);
            }
            $patch = [];
            if ($request->has('taken_at')) {
                $ta = $request->input('taken_at');
                $patch['taken_at'] = is_string($ta) && $ta !== '' ? Carbon::parse($ta, 'UTC') : null;
            }
            if ($request->has('place')) {
                $patch['place'] = $request->filled('place') ? $request->string('place')->value() : null;
            }
            if ($request->has('lat')) {
                $lat = $request->input('lat');
                $patch['lat'] = is_numeric($lat) ? (float) $lat : null;
            }
            if ($request->has('lng')) {
                $lng = $request->input('lng');
                $patch['lng'] = is_numeric($lng) ? (float) $lng : null;
            }
            if ($request->has('rotation')) {
                $patch['rotation'] = $request->integer('rotation');
            }
            if ($request->has('flip_h')) {
                $patch['flip_h'] = $request->boolean('flip_h');
            }
            $patch['version'] = (int) $fresh->version + 1;
            $fresh->forceFill($patch)->save();
            // Version bump changes the thumb cache path; regenerate off the web path.
            GenerateGalleryThumbnail::dispatch($fresh->id);

            return response()->json(['photo' => $this->row($fresh)]);
        });
    }

    public function download(Request $request, GalleryPhoto $photo, ImageManagerFactory $images): StreamedResponse
    {
        $this->requireUser($request);
        $src = (string) $photo->storage_path;
        abort_unless(str_starts_with($src, 'gallery/') && $this->fs()->exists($src), 404);

        $wantsEdited = $request->query('variant') === 'edited';
        $hasEdit = (int) $photo->rotation !== 0 || $photo->flip_h;

        // Original (or an edited request with no actual transform) → raw bytes.
        if (! $wantsEdited || ! $hasEdit) {
            return $this->fs()->response($src, $this->safeName($photo->name), [], 'attachment');
        }

        abort_if((int) $photo->size > self::THUMB_MAX_SRC_BYTES, 404);
        try {
            $tmp = DiskTempFile::create('llgdl')->withExtension('img');
            $in = $this->fs()->readStream($src);
            $dst = fopen($tmp->path(), 'wb');
            if (! is_resource($in) || $dst === false) {
                abort(404);
            }
            stream_copy_to_stream($in, $dst);
            fclose($in);
            fclose($dst);
            $dims = @getimagesize($tmp->path());
            if (is_array($dims) && (int) $dims[0] * (int) $dims[1] > self::THUMB_MAX_PIXELS) {
                abort(404);
            }
            $img = $this->applyEdits($images->make()->decodePath($tmp->path()), $photo);
            $mime = (string) $photo->mime;
            $bytes = match (true) {
                str_contains($mime, 'png') => (string) $img->encode(new PngEncoder),
                str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => (string) $img->encode(new JpegEncoder(quality: 90)),
                default => (string) $img->encode(new WebpEncoder(quality: 90)),
            };
        } catch (\Throwable) {
            // Baking failed — fall back to the untouched original.
            return $this->fs()->response($src, $this->safeName($photo->name), [], 'attachment');
        }

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $this->safeName($photo->name), ['Content-Type' => (string) $photo->mime ?: 'application/octet-stream']);
    }

    /** Apply the stored non-invasive transforms (mirror then rotate). */
    private function applyEdits(ImageInterface $img, GalleryPhoto $photo): ImageInterface
    {
        if ($photo->flip_h) {
            $img->flip(Direction::HORIZONTAL);
        }
        $deg = (int) $photo->rotation % 360;
        if ($deg !== 0) {
            // Stored rotation is clockwise; Intervention rotates counter-clockwise
            // for a positive angle, so negate.
            $img->rotate(-$deg);
        }

        return $img;
    }

    public function destroy(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $this->requireUser($request);
        $photo->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->requireUser($request);
        GalleryPhoto::onlyTrashed()->whereKey($id)->restore();

        return response()->json(['ok' => true]);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->requireUser($request);
        $photo = GalleryPhoto::onlyTrashed()->whereKey($id)->first();
        if ($photo instanceof GalleryPhoto) {
            $this->purgeBlobs($photo);
            $photo->forceDelete();
        }

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(Request $request): JsonResponse
    {
        $this->requireUser($request);
        foreach (GalleryPhoto::onlyTrashed()->get() as $photo) {
            $this->purgeBlobs($photo);
            $photo->forceDelete();
        }

        return response()->json(['ok' => true]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $ids = $this->intIds($request->input('ids', []));
        if ($ids !== []) {
            GalleryPhoto::query()->whereIn('id', $ids)->delete();
        }

        return response()->json(['ok' => true]);
    }

    // ---- Albums ----

    public function albums(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $albums = GalleryAlbum::query()->withCount('photos')->orderBy('name')->get()
            ->map(fn (GalleryAlbum $a): array => $this->albumRow($a))->all();

        return response()->json(['albums' => $albums]);
    }

    public function albumStore(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['name' => ['required', 'string', 'max:191']]);
        $album = new GalleryAlbum;
        $album->forceFill(['user_id' => $uid, 'name' => $request->string('name')->value()]);
        $album->save();

        return response()->json(['album' => $this->albumRow($album->loadCount('photos'))], 201);
    }

    public function albumUpdate(Request $request, GalleryAlbum $album): JsonResponse
    {
        $this->requireUser($request);
        $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'cover_photo_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        if ($request->has('name')) {
            $album->name = $request->string('name')->value();
        }
        if ($request->has('cover_photo_id')) {
            $cover = $request->integer('cover_photo_id');
            // Only accept a cover that is actually in this owner's album.
            $album->cover_photo_id = $cover > 0 && $album->photos()->whereKey($cover)->exists() ? $cover : null;
        }
        $album->save();

        return response()->json(['album' => $this->albumRow($album->loadCount('photos'))]);
    }

    public function albumDestroy(Request $request, GalleryAlbum $album): JsonResponse
    {
        $this->requireUser($request);
        $album->delete();

        return response()->json(['ok' => true]);
    }

    public function albumAttach(Request $request, GalleryAlbum $album): JsonResponse
    {
        $this->requireUser($request);
        $ids = $this->ownedPhotoIds($request);
        if ($ids !== []) {
            $album->photos()->syncWithoutDetaching($ids);
        }

        return response()->json(['ok' => true]);
    }

    public function albumDetach(Request $request, GalleryAlbum $album): JsonResponse
    {
        $this->requireUser($request);
        $ids = $this->ownedPhotoIds($request);
        if ($ids !== []) {
            $album->photos()->detach($ids);
        }

        return response()->json(['ok' => true]);
    }

    // ---- Helpers ----

    /** @return list<int> owner-scoped photo ids from the request. */
    private function ownedPhotoIds(Request $request): array
    {
        $ids = $this->intIds($request->input('ids', []));
        if ($ids === []) {
            return [];
        }
        $existing = $this->intIds(GalleryPhoto::query()->whereIn('id', $ids)->pluck('id')->all());

        return array_values(array_intersect($ids, $existing));
    }

    /** @return list<int> */
    private function intIds(mixed $raw): array
    {
        $out = [];
        foreach ((array) $raw as $v) {
            if (is_numeric($v) && (int) $v > 0) {
                $out[] = (int) $v;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return array{id:int,name:string,count:int,cover_photo_id:?int,version:int} */
    private function albumRow(GalleryAlbum $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'count' => (int) ($a->photos_count ?? 0),
            'cover_photo_id' => $a->cover_photo_id,
            'version' => $a->version,
        ];
    }

    /** @return array{id:int,name:string,mime:?string,width:?int,height:?int,size:int,favorite:bool,thumb:bool,motion:bool,media_type:string,status:string,duration:?int,rotation:int,flip_h:bool,taken_at:?string,camera:?string,place:?string,lat:?float,lng:?float,version:int,created_at:?string} */
    private function row(GalleryPhoto $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'mime' => $p->mime,
            'width' => $p->width,
            'height' => $p->height,
            'size' => $p->size,
            'favorite' => (bool) $p->favorite,
            'thumb' => $this->fs()->exists($this->thumbPath($p)),
            'motion' => $p->motion_path !== null && $p->motion_path !== '',
            'media_type' => $p->media_type,
            'status' => $p->status,
            'duration' => $p->duration,
            'rotation' => (int) $p->rotation,
            'flip_h' => (bool) $p->flip_h,
            'taken_at' => $p->taken_at?->toIso8601String(),
            'camera' => $p->camera,
            'place' => $p->place,
            'lat' => $p->lat,
            'lng' => $p->lng,
            'version' => (int) $p->version,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{taken_at?:?string,camera?:?string,lat?:?float,lng?:?float,exif?:?array<string,mixed>}  $meta
     */
    private function persist(int $uid, string $name, string $path, int $size, ?string $mime, ?string $sha, ?int $w, ?int $h, array $meta = [], string $mediaType = 'image', string $status = 'ready'): GalleryPhoto
    {
        $photo = new GalleryPhoto;
        $photo->forceFill([
            'user_id' => $uid,
            'storage_path' => $path,
            'name' => $name,
            'mime' => $mime,
            'media_type' => $mediaType,
            'status' => $status,
            'size' => $size,
            'sha256' => $sha,
            'width' => $w,
            'height' => $h,
            'taken_at' => $meta['taken_at'] ?? null,
            'camera' => $meta['camera'] ?? null,
            'lat' => $meta['lat'] ?? null,
            'lng' => $meta['lng'] ?? null,
            'exif' => $meta['exif'] ?? null,
        ]);
        $photo->save();

        return $photo;
    }

    /**
     * Fail-safe EXIF read (JPEG/TIFF). Returns capture date, camera model and
     * GPS coordinates when present; any error yields an empty array so an upload
     * never breaks over bad EXIF.
     *
     * @return array{taken_at?:?string,camera?:?string,lat?:?float,lng?:?float,exif?:?array<string,mixed>}
     */
    private function extractExif(string $path): array
    {
        if (! function_exists('exif_read_data')) {
            return [];
        }
        try {
            $raw = @exif_read_data($path, null, true);
        } catch (\Throwable) {
            return [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $ifd0 = is_array($raw['IFD0'] ?? null) ? $raw['IFD0'] : [];
        $exifSec = is_array($raw['EXIF'] ?? null) ? $raw['EXIF'] : [];

        $takenRaw = $exifSec['DateTimeOriginal'] ?? $ifd0['DateTime'] ?? null;
        $takenAt = null;
        if (is_string($takenRaw) && $takenRaw !== '') {
            $ts = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', trim($takenRaw));
            if ($ts instanceof \DateTimeImmutable) {
                $takenAt = $ts->format('Y-m-d H:i:s');
            }
        }

        $make = is_string($ifd0['Make'] ?? null) ? trim((string) $ifd0['Make']) : '';
        $model = is_string($ifd0['Model'] ?? null) ? trim((string) $ifd0['Model']) : '';
        $camera = trim($make.' '.$model);
        $camera = $camera !== '' ? mb_substr($camera, 0, 190) : null;

        $gps = is_array($raw['GPS'] ?? null) ? $raw['GPS'] : [];
        $lat = $this->gpsCoord($gps['GPSLatitude'] ?? null, is_string($gps['GPSLatitudeRef'] ?? null) ? $gps['GPSLatitudeRef'] : null);
        $lng = $this->gpsCoord($gps['GPSLongitude'] ?? null, is_string($gps['GPSLongitudeRef'] ?? null) ? $gps['GPSLongitudeRef'] : null);

        return [
            'taken_at' => $takenAt,
            'camera' => $camera,
            'lat' => $lat,
            'lng' => $lng,
            'exif' => ['taken_at' => $takenAt, 'camera' => $camera],
        ];
    }

    /** Convert an EXIF GPS [deg,min,sec] rational triple + hemisphere ref to a signed float. */
    private function gpsCoord(mixed $parts, ?string $ref): ?float
    {
        if (! is_array($parts) || count($parts) < 3) {
            return null;
        }
        $deg = $this->rational($parts[0]);
        $min = $this->rational($parts[1]);
        $sec = $this->rational($parts[2]);
        if ($deg === null || $min === null || $sec === null) {
            return null;
        }
        $val = $deg + $min / 60 + $sec / 3600;
        if ($ref === 'S' || $ref === 'W') {
            $val = -$val;
        }

        return round($val, 7);
    }

    private function rational(mixed $v): ?float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        if (is_string($v) && str_contains($v, '/')) {
            [$n, $d] = array_pad(explode('/', $v, 2), 2, '1');
            $d = (float) $d;

            return $d !== 0.0 ? (float) $n / $d : null;
        }

        return null;
    }

    private function purgeBlobs(GalleryPhoto $photo): void
    {
        foreach ([$photo->storage_path, $photo->motion_path, $photo->poster_path, $photo->playback_path] as $blob) {
            $p = $this->safeBlobPath($blob);
            if ($p !== null) {
                $this->fs()->delete($p);
            }
        }
        $this->fs()->delete('gallery/thumb/'.$photo->id.'-'.$photo->version.'.webp');
    }

    /** Absolute filesystem path when the files disk is local, else null (remote). */
    private function localPath(string $rel): ?string
    {
        $disk = $this->fs();

        return $disk instanceof FilesystemAdapter ? $disk->path($rel) : null;
    }

    private function safeBlobPath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || str_contains($path, '..') || ! str_starts_with($path, 'gallery/')) {
            return null;
        }

        return $path;
    }

    /**
     * Android / Samsung "Motion Photo" (Google Pixel MicroVideo, Galaxy) store the
     * clip EMBEDDED inside a single JPEG — an MP4 appended after the image. Extract
     * it and attach it as this photo's motion clip so it becomes a Live entry too.
     * The appended MP4 is a real ISO-BMFF (H.264/HEVC) → plays in every browser.
     * JPEG-only (HEIC also starts with an ftyp box, which would false-match).
     */
    private function attachEmbeddedMotion(GalleryPhoto $photo, ?string $stillLocalPath, ?string $mime): void
    {
        if ($stillLocalPath === null || $mime === null
            || (! str_contains($mime, 'jpeg') && ! str_contains($mime, 'jpg'))) {
            return;
        }
        $bytes = $this->extractEmbeddedMp4($stillLocalPath);
        if ($bytes === null) {
            return;
        }
        $path = 'gallery/'.Str::uuid()->toString().'-mv';
        $this->fs()->put($path, $bytes);
        $photo->forceFill(['motion_path' => $path])->save();
    }

    /** The appended MP4 bytes of a Motion Photo JPEG, or null. */
    private function extractEmbeddedMp4(string $path): ?string
    {
        $size = @filesize($path);
        if ($size === false || $size <= 0 || $size > self::THUMB_MAX_SRC_BYTES) {
            return null;
        }
        $data = @file_get_contents($path);
        if ($data === false || substr($data, 0, 2) !== "\xFF\xD8") { // JPEG SOI
            return null;
        }
        $len = strlen($data);

        // Google Motion Photo v1: XMP GCamera:MicroVideoOffset = bytes from EOF.
        if (preg_match('/MicroVideoOffset["\':=\s]+(\d+)/', $data, $m) === 1) {
            $off = (int) $m[1];
            $start = $len - $off;
            if ($off > 8 && $start >= 4 && substr($data, $start + 4, 4) === 'ftyp') {
                return substr($data, $start);
            }
        }

        // Fallback (Samsung / Motion Photo v2): the first ISO-BMFF `ftyp` box after
        // the JPEG SOI marks the appended MP4. Validate the 4-byte box-size prefix.
        $pos = strpos($data, 'ftyp', 4);
        if ($pos !== false && $pos >= 4) {
            $start = $pos - 4;
            $box = unpack('N', substr($data, $start, 4));
            $boxLen = is_array($box) && is_numeric($box[1] ?? null) ? (int) $box[1] : 0;
            if ($boxLen >= 8 && $boxLen <= $len - $start && $len - $start > 8192) {
                return substr($data, $start);
            }
        }

        return null;
    }

    /** Video by sniffed MIME or by a known video extension ("any format" upload). */
    private function looksLikeVideo(?string $mime, string $name): bool
    {
        if ($mime !== null && str_starts_with($mime, 'video/')) {
            return true;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return $ext !== '' && in_array($ext, self::VIDEO_EXT, true);
    }

    /** An existing non-trashed photo of the same user with identical bytes, if any. */
    private function findDuplicate(int $uid, ?string $sha): ?GalleryPhoto
    {
        if ($sha === null || $sha === '') {
            return null;
        }

        return GalleryPhoto::query()->where('user_id', $uid)->where('sha256', $sha)->first();
    }

    /**
     * Extract Apple's Live Photo content identifier from a QuickTime .MOV/.MP4:
     * the value of the `com.apple.quicktime.content.identifier` metadata key.
     * Best-effort — scans the moov metadata; returns null when absent. Bounded to
     * the first 512 KiB (Apple writes this near the moov atom).
     */
    private function extractQuickTimeContentId(string $path): ?string
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        $head = (string) fread($fh, 512 * 1024);
        fclose($fh);
        $needle = 'com.apple.quicktime.content.identifier';
        $at = strpos($head, $needle);
        if ($at === false) {
            return null;
        }
        // The value follows the key inside a `data` atom; a Live Photo id is a
        // UUID. Grab the first UUID-shaped token after the key.
        if (preg_match('/[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}/', substr($head, $at, 256), $m) === 1) {
            return $m[0];
        }

        return null;
    }

    private function disk(): string
    {
        $d = config('files.disk');

        return is_string($d) ? $d : 'files';
    }

    private function fs(): Filesystem
    {
        return Storage::disk($this->disk());
    }

    private function maxUploadKb(): int
    {
        $mb = config('files.max_upload_mb', 2048);

        return (is_numeric($mb) ? (int) $mb : 2048) * 1024;
    }

    /** Combined files+gallery quota against config('files.quota_mb') (null = unlimited). */
    private function overQuota(int $uid, int $incoming): ?JsonResponse
    {
        $mb = config('files.quota_mb');
        $mb = is_numeric($mb) ? (int) $mb : 0;
        if ($mb <= 0) {
            return null;
        }
        $used = FilesUsage::forUser($uid) + (int) GalleryPhoto::withTrashed()->where('user_id', $uid)->sum('size');
        if ($used + $incoming > $mb * 1024 * 1024) {
            return response()->json(['error' => 'quota'], 413);
        }

        return null;
    }

    private function tmpDir(int $uid, string $id): string
    {
        return 'gallery-tmp/'.$uid.'/'.preg_replace('/[^A-Za-z0-9\-]/', '', $id);
    }

    private function sessionKey(int $uid, string $id): string
    {
        return 'gallery-upload:'.$uid.':'.$id;
    }

    private function sniffMime(string $path): ?string
    {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f === false) {
            return null;
        }
        $mime = finfo_file($f, $path);
        finfo_close($f);

        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    private function safeName(string $name): string
    {
        $clean = preg_replace('#[\x00-\x1F\x7F"\\\\/]+#', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'photo' : $clean;
    }
}
