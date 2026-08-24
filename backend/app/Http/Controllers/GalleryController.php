<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\DetectGalleryFaces;
use App\Jobs\EmbedGalleryPhoto;
use App\Jobs\ExtractGalleryOcr;
use App\Jobs\GenerateGalleryThumbnail;
use App\Jobs\ProcessGalleryVideo;
use App\Jobs\RefreshGalleryExif;
use App\Models\GalleryAlbum;
use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Services\Files\FileTextIndex;
use App\Support\BlobStore;
use App\Support\DiskTempFile;
use App\Support\GalleryMemories;
use App\Support\ImageManagerFactory;
use App\Support\MachineLearning;
use App\Support\StorageUsage;
use App\Support\Vector;
use App\Support\VideoProcessor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    private const PREVIEW_MAX = 2048;

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
        $personId = $request->integer('person_id');
        if ($personId > 0) {
            $query->whereHas('faces', fn ($q) => $q->where('gallery_person_id', $personId)->where('hidden', false));
        }
        // Archived photos are hidden from the main timeline; ?archived=1 shows
        // only them (the "Archive" view). Album/person views show everything.
        if ($albumId <= 0 && $personId <= 0) {
            $query->{$request->boolean('archived') ? 'whereNotNull' : 'whereNull'}('archived_at');
        }

        // Keyset (cursor) pagination over COALESCE(taken_at, created_at) DESC, id DESC.
        // Backward compatible: with no limit/cursor a client still gets a usable first
        // page (the default limit) — the old "everything at once" behaviour is gone
        // because it timed out on large libraries. Old clients that ignore next_cursor
        // simply see the newest page.
        $limit = max(1, min(500, $request->integer('limit') ?: 200));
        $sortExpr = 'COALESCE(taken_at, created_at)';

        // Jump straight to a month (scrubber): first row at or before the end of that
        // month, newest-first — i.e. everything strictly before the following month.
        $cursorYm = (string) $request->string('cursor_ym');
        if (preg_match('/^\d{4}-\d{2}$/', $cursorYm) === 1) {
            $monthEnd = Carbon::createFromFormat('Y-m-d', $cursorYm.'-01', 'UTC')?->startOfMonth()->addMonth();
            if ($monthEnd !== null) {
                $query->whereRaw("$sortExpr < ?", [$monthEnd->toDateTimeString()]);
            }
        }

        // Opaque cursor = base64(json{ts, id}) of the last row of the previous page.
        $cursor = $this->decodeGalleryCursor((string) $request->string('cursor'));
        if ($cursor !== null) {
            // Row-value comparison: (sort_ts, id) < (cursor_ts, cursor_id).
            $query->whereRaw("($sortExpr < ? OR ($sortExpr = ? AND id < ?))", [$cursor['ts'], $cursor['ts'], $cursor['id']]);
        }

        $rows = $query->orderByRaw($sortExpr.' DESC')->orderByDesc('id')->limit($limit + 1)->get();

        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit);
        $next = null;
        if ($hasMore) {
            $last = $page->last();
            if ($last instanceof GalleryPhoto) {
                $ts = ($last->taken_at ?? $last->created_at)?->toDateTimeString() ?? '';
                $next = base64_encode((string) json_encode(['ts' => $ts, 'id' => (int) $last->id]));
            }
        }

        return response()->json([
            'photos' => $page->map(fn (GalleryPhoto $p): array => $this->row($p))->values()->all(),
            'next_cursor' => $next,
        ]);
    }

    /**
     * Decode an opaque timeline cursor; null when absent or malformed.
     *
     * @return array{ts: string, id: int}|null
     */
    private function decodeGalleryCursor(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        $json = base64_decode($raw, true);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['ts'], $data['id']) || ! is_string($data['ts']) || ! is_numeric($data['id'])) {
            return null;
        }

        return ['ts' => $data['ts'], 'id' => (int) $data['id']];
    }

    /**
     * Month histogram for the date scrubber: one bucket per month with a count,
     * newest-first, honouring the same album/person/archived filters as data().
     * A single GROUP BY — cheap and indexable — so the scrubber shows the full
     * range without loading every row.
     */
    public function dates(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $query = GalleryPhoto::query();
        $albumId = $request->integer('album_id');
        if ($albumId > 0) {
            $query->whereHas('albums', fn ($q) => $q->whereKey($albumId));
        }
        $personId = $request->integer('person_id');
        if ($personId > 0) {
            $query->whereHas('faces', fn ($q) => $q->where('gallery_person_id', $personId)->where('hidden', false));
        }
        if ($albumId <= 0 && $personId <= 0) {
            $query->{$request->boolean('archived') ? 'whereNotNull' : 'whereNull'}('archived_at');
        }

        // Portable "YYYY-MM" of COALESCE(taken_at, created_at) across pgsql + sqlite.
        $dbDriver = config('database.default');
        $ym = match (true) {
            is_string($dbDriver) && str_contains(strtolower($dbDriver), 'sqlite') => "strftime('%Y-%m', COALESCE(taken_at, created_at))",
            default => "to_char(COALESCE(taken_at, created_at), 'YYYY-MM')",
        };
        $rows = $query->selectRaw("$ym as ym, COUNT(*) as count")
            ->groupByRaw($ym)
            ->orderByRaw('ym DESC')
            ->get();

        return response()->json([
            'months' => $rows->map(function ($r): array {
                $rowYm = $r->getAttribute('ym');
                $rowCount = $r->getAttribute('count');

                return [
                    'ym' => is_scalar($rowYm) ? (string) $rowYm : '',
                    'count' => is_numeric($rowCount) ? (int) $rowCount : 0,
                ];
            })->all(),
        ]);
    }

    /** Seed terms for CLIP-based auto theme cards (client localizes the label). */
    private const THEME_SEEDS = ['beach', 'mountains', 'food', 'sunset', 'city', 'nature', 'animals', 'snow'];

    /**
     * Memories / auto-curation: "on this day" (past years), auto-detected trips,
     * and CLIP theme cards (only when ML + pgvector are available). Archived and
     * trashed photos are excluded (the GalleryPhoto scope + whereNull handle it).
     */
    public function memories(Request $request, GalleryMemories $memories, MachineLearning $ml): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $today = now();

        // On this day (cap photos per year for the payload).
        $onThisDay = [];
        foreach ($memories->onThisDay((int) $today->year, (int) $today->month, (int) $today->day) as $sec) {
            $onThisDay[] = [
                'year' => $sec['year'],
                'years_ago' => $sec['years_ago'],
                'photos' => $this->rowsForIds(array_slice($sec['ids'], 0, 40)),
            ];
        }

        // Trips.
        $trips = [];
        foreach ($memories->trips() as $trip) {
            $photos = $this->rowsForIds(array_slice($trip['ids'], 0, 40));
            $trips[] = [
                'from' => $trip['from'],
                'to' => $trip['to'],
                'place' => $trip['place'],
                'cover' => $photos[0]['id'] ?? null,
                'count' => count($trip['ids']),
                'photos' => $photos,
            ];
        }

        // Themes (ML/pgvector-gated; degrades to empty). Each seed embeds via a
        // synchronous, inline call to the ML sidecar (this whole endpoint runs
        // in a request-serving Octane worker, not a queued job) — if the
        // sidecar is down/degraded, a FAILED embed on one seed is a strong
        // signal every subsequent one will fail too, so stop immediately
        // rather than retrying all 8 and holding the worker for up to 8x the
        // per-call timeout. A successful embed that simply finds few/no
        // matching photos for that particular theme is a normal per-theme
        // outcome (most libraries won't match every seed) and must NOT abort
        // the loop — only an embedText() failure does.
        $themes = [];
        if ($ml->enabled() && Vector::available()) {
            foreach (self::THEME_SEEDS as $seed) {
                $vec = $ml->embedText($seed);
                if ($vec === null || count($vec) !== 512) {
                    break;
                }
                $ids = $this->photoIdsForVector($uid, $vec, 30);
                if (count($ids) >= 4) {
                    $photos = $this->rowsForIds($ids);
                    $themes[] = ['key' => $seed, 'cover' => $photos[0]['id'] ?? null, 'count' => count($ids), 'photos' => $photos];
                }
            }
        }

        return response()->json(['on_this_day' => $onThisDay, 'trips' => $trips, 'themes' => $themes]);
    }

    /**
     * Resolve ids → row payloads preserving order, dropping missing/archived.
     *
     * @param  list<int>  $ids
     * @return list<array<string,mixed>>
     */
    private function rowsForIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $byId = GalleryPhoto::query()->whereIn('id', $ids)->whereNull('archived_at')->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) {
            $p = $byId->get($id);
            if ($p instanceof GalleryPhoto) {
                $out[] = $this->row($p);
            }
        }

        return $out;
    }

    /**
     * CLIP nearest-neighbour ids for an already-embedded vector (owner-scoped).
     * Split out from the embedding call itself so a caller iterating several
     * vectors (memories()' theme seeds) can tell "the embed call failed" apart
     * from "this vector legitimately has few/no nearby photos" — only the
     * former is a signal to stop trying further seeds.
     *
     * @param  list<float>  $vec
     * @return list<int>
     */
    private function photoIdsForVector(int $uid, array $vec, int $limit): array
    {
        $lit = MachineLearning::toVectorLiteral($vec);
        $maxCfg = config('ml.search_max_distance', 0.78);
        $max = is_numeric($maxCfg) ? (float) $maxCfg : 0.78;
        $rows = DB::select(
            'SELECT id FROM gallery_photos
             WHERE user_id = ? AND deleted_at IS NULL AND archived_at IS NULL AND embedding IS NOT NULL
               AND (embedding <=> ?::vector) < ?
             ORDER BY embedding <=> ?::vector LIMIT ?',
            [$uid, $lit, $max, $lit, $limit],
        );

        return array_values(array_filter(array_map(
            static fn ($r): int => is_object($r) && isset($r->id) && is_numeric($r->id) ? (int) $r->id : 0,
            $rows,
        ), static fn (int $i): bool => $i > 0));
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
        DetectGalleryFaces::dispatch($photo->id);
        ExtractGalleryOcr::dispatch($photo->id);

        return response()->json(['photo' => $this->row($photo)], 201);
    }

    /**
     * Ingest a contributed upload into a collaborative album on behalf of the
     * album OWNER (not the acting recipient). Bytes are stored under the owner,
     * count against the OWNER's quota, and the photo joins the album. Owner-side
     * queries drop the Auth-keyed global scope since the actor is the recipient.
     *
     * @return array{ok:bool,error?:string,photo?:array<string,mixed>}
     */
    public function contribute(int $ownerId, UploadedFile $upload, GalleryAlbum $album): array
    {
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        $isImage = str_starts_with($mime, 'image/');
        $isVideo = ! $isImage && $this->looksLikeVideo($mime, $upload->getClientOriginalName());
        if (! $isImage && ! $isVideo) {
            return ['ok' => false, 'error' => 'unsupported'];
        }

        $incoming = (int) $upload->getSize();
        if (StorageUsage::wouldExceed($ownerId, $incoming)) {
            return ['ok' => false, 'error' => 'quota'];
        }

        $real = $upload->getRealPath();
        $sha = is_string($real) ? (hash_file('sha256', $real) ?: null) : null;
        $dupe = $sha !== null
            ? GalleryPhoto::withoutGlobalScopes()->where('user_id', $ownerId)->where('sha256', $sha)->first()
            : null;
        if ($dupe instanceof GalleryPhoto) {
            $album->photos()->syncWithoutDetaching([$dupe->id]);

            return ['ok' => true, 'photo' => $this->row($dupe)];
        }

        $path = 'gallery/'.Str::uuid()->toString();
        $this->fs()->putFileAs('gallery', $upload, basename($path));
        $name = $upload->getClientOriginalName();
        $size = (int) $this->fs()->size($path);

        if ($isVideo) {
            $photo = DB::transaction(fn (): GalleryPhoto => $this->persist(
                $ownerId, $name, $path, $size, $mime, $sha, null, null, [], 'video', 'processing'
            ));
            $album->photos()->syncWithoutDetaching([$photo->id]);
            ProcessGalleryVideo::dispatch($photo->id);

            return ['ok' => true, 'photo' => $this->row($photo)];
        }

        $dims = is_string($real) ? @getimagesize($real) : false;
        $meta = is_string($real) ? $this->extractExif($real) : [];
        $photo = DB::transaction(fn (): GalleryPhoto => $this->persist(
            $ownerId, $name, $path, $size, $mime, $sha,
            is_array($dims) ? (int) $dims[0] : null,
            is_array($dims) ? (int) $dims[1] : null,
            $meta,
        ));
        $album->photos()->syncWithoutDetaching([$photo->id]);
        $this->attachEmbeddedMotion($photo, is_string($real) ? $real : null, $mime);
        GenerateGalleryThumbnail::dispatch($photo->id);
        EmbedGalleryPhoto::dispatch($photo->id);
        DetectGalleryFaces::dispatch($photo->id);
        ExtractGalleryOcr::dispatch($photo->id);

        return ['ok' => true, 'photo' => $this->row($photo)];
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
        DetectGalleryFaces::dispatch($photo->id);
        ExtractGalleryOcr::dispatch($photo->id);

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

    /**
     * Browser-viewable full-size preview (large WebP) for the lightbox. The
     * original may be HEIC/HEIF, which browsers cannot render in an <img>; the
     * preview is a decoded, orientation-baked WebP. Cache-only (produced by the
     * thumbnail job); a miss re-queues generation and 404s.
     */
    public function preview(Request $request, GalleryPhoto $photo): StreamedResponse
    {
        $this->requireUser($request);
        $isImage = str_starts_with((string) $photo->mime, 'image/');
        abort_unless($isImage || $photo->media_type === 'video', 404);

        $path = $this->previewPath($photo);
        if (! $this->fs()->exists($path)) {
            if ($isImage || ($photo->poster_path !== null && $photo->poster_path !== '')) {
                GenerateGalleryThumbnail::dispatch($photo->id);
            }
            abort(404);
        }

        return $this->fs()->response($path, 'preview.webp', [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    private function previewPath(GalleryPhoto $photo): string
    {
        return 'gallery/preview/'.$photo->id.'-'.$photo->version.'.webp';
    }

    /**
     * Best ML-decodable source for a photo: the WebP preview (the ML sidecar's
     * PIL cannot decode HEIC originals), else the video poster, else the
     * original. Shared by CLIP embedding + face detection.
     */
    public function mlSourcePath(GalleryPhoto $photo): string
    {
        if ($photo->media_type === 'video') {
            return (string) $photo->poster_path;
        }
        $preview = $this->previewPath($photo);
        if ($this->fs()->exists($preview)) {
            return $preview;
        }

        return (string) $photo->storage_path;
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

            $meta = [
                'duration' => $probe['duration'],
                'width' => $probe['width'],
                'height' => $probe['height'],
                'poster_path' => $posterRel,
                'playback_path' => $playRel,
                'status' => $posterRel !== null ? 'ready' : 'failed',
            ];
            // Capture metadata from the container — only fill what is still empty
            // so a manual edit (or a Live still's date) is never clobbered on reprocess.
            if ($photo->taken_at === null && is_string($probe['taken_at'] ?? null)) {
                $meta['taken_at'] = $probe['taken_at'];
            }
            if ($photo->lat === null && $photo->lng === null && is_float($probe['lat'] ?? null) && is_float($probe['lng'] ?? null)) {
                $meta['lat'] = $probe['lat'];
                $meta['lng'] = $probe['lng'];
            }
            if (($photo->camera === null || $photo->camera === '') && is_string($probe['camera'] ?? null)) {
                $meta['camera'] = $probe['camera'];
            }
            $photo->forceFill($meta)->save();

            if ($posterRel !== null) {
                $this->generateThumb($photo, $images);
                EmbedGalleryPhoto::dispatch($photo->id); // embed the poster frame
                DetectGalleryFaces::dispatch($photo->id);
            }
        } catch (\Throwable) {
            $photo->forceFill(['status' => 'failed'])->save();
        }
    }

    /**
     * Compute + store a photo's CLIP embedding (worker, via EmbedGalleryPhoto).
     * For a video the poster frame is embedded. No-op when ML/pgvector are off.
     */
    /**
     * Photo ids whose OCR text or filename match the query (owner-scoped, non-trashed).
     * Full-text over the GIN index on pgsql, LIKE fallback elsewhere.
     *
     * @return array<int, int>
     */
    private function ocrMatchIds(int $uid, string $q): array
    {
        $like = '%'.$q.'%';
        $query = GalleryPhoto::query()->where('user_id', $uid);
        if (DB::getDriverName() === 'pgsql') {
            $query->where(function (Builder $inner) use ($q, $like): void {
                $inner->whereRaw("to_tsvector('simple', coalesce(ocr_text, '')) @@ plainto_tsquery('simple', ?)", [$q])
                    ->orWhere('name', 'like', $like);
            });
        } else {
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('ocr_text', 'like', $like)->orWhere('name', 'like', $like);
            });
        }

        return $query->orderByDesc('id')->limit(80)->pluck('id')
            ->map(static fn ($v): int => is_numeric($v) ? (int) $v : 0)
            ->filter(static fn (int $v): bool => $v > 0)->values()->all();
    }

    /**
     * OCR a photo so text inside the image (signs, screenshots, receipts) becomes
     * searchable. Runs on the worker (not the web request). Uses the raster preview
     * (webp) / video poster — tesseract can't read HEIC, and the preview is the same
     * decodable source ML uses. Best-effort: failure leaves ocr_text null.
     */
    public function ocrPhoto(GalleryPhoto $photo): void
    {
        $rel = $this->mlSourcePath($photo);
        if ($rel === '' || ! $this->fs()->exists($rel)) {
            return;
        }
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
        $bytes = $this->fs()->get($rel);
        $text = is_string($bytes) ? (new FileTextIndex)->extractBytes($bytes, $mime) : null;
        $photo->forceFill(['ocr_text' => $text, 'ocr_at' => now()])->saveQuietly();
    }

    public function embedPhoto(GalleryPhoto $photo, MachineLearning $ml): void
    {
        if (! $ml->enabled() || ! Vector::available()) {
            return;
        }
        $rel = $this->mlSourcePath($photo);
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
     * Re-queue ML processing over the owner's photos: face detection + grouping
     * and/or CLIP embeddings. Runs on the worker one photo at a time. Use after
     * enabling ML, changing models, or to improve recognition.
     */
    public function reprocess(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $scope = $request->string('scope')->lower()->value();
        $scope = in_array($scope, ['faces', 'embeddings', 'exif', 'all'], true) ? $scope : 'all';
        $doFaces = $scope === 'faces' || $scope === 'all';
        $doEmb = $scope === 'embeddings' || $scope === 'all';
        $doExif = $scope === 'exif' || $scope === 'all';
        $count = 0;
        GalleryPhoto::query()->where('user_id', $uid)->orderBy('id')
            ->chunkById(200, function ($photos) use (&$count, $doFaces, $doEmb, $doExif): void {
                foreach ($photos as $photo) {
                    if ($doExif) {
                        RefreshGalleryExif::dispatch($photo->id);
                    }
                    if ($doEmb) {
                        EmbedGalleryPhoto::dispatch($photo->id);
                    }
                    if ($doFaces) {
                        DetectGalleryFaces::dispatch($photo->id);
                    }
                    $count++;
                }
            });

        return response()->json(['ok' => true, 'queued' => $count, 'scope' => $scope]);
    }

    /**
     * Re-read capture metadata from a photo's original (worker, via
     * RefreshGalleryExif): EXIF for images, ffprobe container tags for videos.
     * An explicit rescan — overwrites taken_at/GPS/camera from the source.
     */
    public function refreshMetadata(GalleryPhoto $photo): void
    {
        $rel = (string) $photo->storage_path;
        if ($rel === '' || ! $this->fs()->exists($rel)) {
            return;
        }
        $abs = $this->localPath($rel);
        $stage = null;
        if ($abs === null) {
            $stage = DiskTempFile::create('llgmeta');
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

        $patch = [];
        if ($photo->media_type === 'video') {
            if (! VideoProcessor::available()) {
                return;
            }
            $probe = VideoProcessor::probe($abs);
            if ($probe === null) {
                return;
            }
            $patch = [
                'taken_at' => $probe['taken_at'],
                'lat' => $probe['lat'],
                'lng' => $probe['lng'],
                'camera' => $probe['camera'],
            ];
        } else {
            $meta = $this->extractExif($abs);
            $patch = [
                'taken_at' => $meta['taken_at'] ?? null,
                'lat' => $meta['lat'] ?? null,
                'lng' => $meta['lng'] ?? null,
                'camera' => $meta['camera'] ?? null,
                'exif' => $meta['exif'] ?? null,
            ];
        }
        $photo->forceFill($patch)->save();
    }

    /**
     * Gallery + ML status for the settings page: feature flags, worker queue
     * depth, and library counts. Non-secret aggregates only.
     */
    public function mlStatus(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $ml = app(MachineLearning::class);
        $base = GalleryPhoto::query()->where('user_id', $uid);

        return response()->json([
            'ml' => [
                'enabled' => $ml->enabled(),
                'face_enabled' => $ml->faceEnabled(),
                'vector' => Vector::available(),
                'clip_model' => is_string(config('ml.clip_model')) ? config('ml.clip_model') : null,
                'face_model' => is_string(config('ml.face_model')) ? config('ml.face_model') : null,
            ],
            'queue' => ['pending' => $this->queuePending()],
            'counts' => [
                'photos' => (clone $base)->count(),
                'videos' => (clone $base)->where('media_type', 'video')->count(),
                'embedded' => Vector::available() ? (clone $base)->whereNotNull('embedded_at')->count() : 0,
                'with_date' => (clone $base)->whereNotNull('taken_at')->count(),
                'located' => (clone $base)->whereNotNull('lat')->count(),
                'faces' => GalleryFace::query()->where('user_id', $uid)->count(),
                'people' => GalleryPerson::query()->where('user_id', $uid)->count(),
            ],
        ]);
    }

    /** Best-effort pending job count for the default queue (driver-agnostic). */
    private function queuePending(): int
    {
        try {
            return (int) Queue::size();
        } catch (\Throwable) {
            return 0;
        }
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
        if ($q === '') {
            return response()->json(['photos' => []]);
        }

        // Semantic (CLIP) hits first, when ML + pgvector are available…
        $ids = [];
        if ($ml->enabled() && Vector::available()) {
            $vec = $ml->embedText($q);
            if ($vec !== null && count($vec) === 512) {
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
            }
        }

        // …then OCR-text + filename matches (text inside images, works without ML),
        // appended after the semantic hits (deduped).
        foreach ($this->ocrMatchIds($uid, $q) as $id) {
            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        $ids = array_slice($ids, 0, 80);
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
     * Near-duplicate groups by CLIP-embedding cosine distance (exact byte
     * duplicates are already blocked at upload by sha256). Greedy: for each
     * un-grouped embedded photo, pull its close neighbors via the HNSW index and
     * form a group of ≥2. pgvector-only; empty when ML/pgvector are off.
     */
    public function duplicates(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        if (! Vector::available()) {
            return response()->json(['groups' => []]);
        }
        $maxCfg = config('ml.dup_max_distance', 0.08);
        $max = is_numeric($maxCfg) ? (float) $maxCfg : 0.08;
        $rows = DB::select(
            'SELECT id FROM gallery_photos
             WHERE user_id = ? AND deleted_at IS NULL AND embedding IS NOT NULL
             ORDER BY created_at DESC, id DESC LIMIT 5000',
            [$uid],
        );
        $ids = array_values(array_filter(array_map(
            static fn ($r): int => is_object($r) && isset($r->id) && is_numeric($r->id) ? (int) $r->id : 0,
            $rows,
        ), static fn (int $i): bool => $i > 0));

        $seen = [];
        $groups = [];
        foreach ($ids as $id) {
            if (isset($seen[$id]) || count($groups) >= 200) {
                continue;
            }
            $nRows = DB::select(
                '(SELECT b.id FROM gallery_photos a JOIN gallery_photos b
                    ON b.user_id = a.user_id
                  WHERE a.id = ? AND b.id <> a.id AND b.deleted_at IS NULL AND b.embedding IS NOT NULL
                    AND (a.embedding <=> b.embedding) < ?
                  ORDER BY (a.embedding <=> b.embedding) LIMIT 20)',
                [$id, $max],
            );
            $group = [$id];
            foreach ($nRows as $nr) {
                $nid = is_object($nr) && isset($nr->id) && is_numeric($nr->id) ? (int) $nr->id : 0;
                if ($nid > 0 && ! isset($seen[$nid])) {
                    $group[] = $nid;
                }
            }
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $gid) {
                $seen[$gid] = true;
            }
            $groups[] = $group;
        }

        $flat = array_merge(...$groups === [] ? [[]] : $groups);
        $byId = GalleryPhoto::query()->whereIn('id', $flat)->get()->keyBy('id');
        $out = [];
        foreach ($groups as $group) {
            $photos = [];
            foreach ($group as $gid) {
                $p = $byId->get($gid);
                if ($p instanceof GalleryPhoto) {
                    $photos[] = $this->row($p);
                }
            }
            if (count($photos) >= 2) {
                $out[] = ['photos' => $photos];
            }
        }

        return response()->json(['groups' => $out]);
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
        $previewPath = $this->previewPath($photo);
        $haveThumb = $this->fs()->exists($thumbPath);
        $havePreview = $this->fs()->exists($previewPath);
        if ($haveThumb && $havePreview) {
            $this->markRenditionReady($photo, true, true);

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
            // One decode → a browser-viewable large preview (HEIC/HEIF cannot be
            // shown in an <img> directly) AND the 400px grid thumbnail.
            $img = $this->applyEdits($images->make()->decodePath($tmp->path()), $photo);
            if (! $havePreview) {
                $img->scaleDown(self::PREVIEW_MAX, self::PREVIEW_MAX);
                $this->fs()->put($previewPath, (string) $img->encode(new WebpEncoder(quality: 82)));
            }
            if (! $haveThumb) {
                $this->fs()->put($thumbPath, (string) $img->cover(400, 400)->encode(new WebpEncoder(quality: 78)));
            }
        } catch (\Throwable) {
            return false;
        }
        // Record readiness for the CURRENT version so the timeline (and clients) know
        // the rendition landed without stat-ing the disk. A later edit bumps version
        // and resets these to false (see update()).
        $this->markRenditionReady($photo, true, true);

        return true;
    }

    /** Persist rendition readiness on the row (worker-only; the timeline reads it). */
    private function markRenditionReady(GalleryPhoto $photo, bool $thumb, bool $preview): void
    {
        if ((bool) $photo->thumb_ready === $thumb && (bool) $photo->preview_ready === $preview) {
            return;
        }
        $photo->forceFill(['thumb_ready' => $thumb, 'preview_ready' => $preview])->saveQuietly();
    }

    // ---- Mutations ----

    public function favorite(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $this->requireUser($request);
        $photo->forceFill(['favorite' => $request->boolean('favorite')])->save();

        return response()->json(['ok' => true]);
    }

    /** Archive/unarchive a single photo (hidden from the main timeline). */
    public function archive(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $this->requireUser($request);
        $photo->forceFill(['archived_at' => $request->boolean('archived') ? now() : null])->save();

        return response()->json(['ok' => true]);
    }

    /** Bulk archive/unarchive owner-scoped photos. */
    public function bulkArchive(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $ids = $this->intIds($request->input('ids', []));
        if ($ids !== []) {
            GalleryPhoto::query()->whereIn('id', $ids)->update(['archived_at' => $request->boolean('archived') ? now() : null]);
        }

        return response()->json(['ok' => true, 'count' => count($ids)]);
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
            // Link the photo to a cost project (site photos belong with the build
            // they document) — same pointer receipts and transactions carry.
            'finance_project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('finance_projects', 'id')->where('user_id', (int) $this->requireUser($request)->id)->whereNull('deleted_at')],
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
            if ($request->has('finance_project_id')) {
                $patch['finance_project_id'] = $request->filled('finance_project_id') ? $request->integer('finance_project_id') : null;
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
            // The version bump changes the rendition cache path, so the current
            // renditions no longer apply — mark not-ready so clients show a spinner
            // until the worker re-renders and flips these back to true.
            $patch['thumb_ready'] = false;
            $patch['preview_ready'] = false;
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

    /** @return array{id:int,name:string,mime:?string,width:?int,height:?int,size:int,favorite:bool,thumb:bool,preview:bool,motion:bool,media_type:string,status:string,duration:?int,rotation:int,flip_h:bool,archived:bool,taken_at:?string,camera:?string,place:?string,lat:?float,lng:?float,version:int,created_at:?string} */
    public function row(GalleryPhoto $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'mime' => $p->mime,
            'width' => $p->width,
            'height' => $p->height,
            'size' => $p->size,
            'favorite' => (bool) $p->favorite,
            // Readiness comes from the DB (set by the worker after rendering) — never
            // stat the disk here: the timeline serializes thousands of rows and two
            // exists() per row timed out the request on large libraries.
            'thumb' => (bool) $p->thumb_ready,
            'preview' => (bool) $p->preview_ready,
            'motion' => $p->motion_path !== null && $p->motion_path !== '',
            'media_type' => $p->media_type,
            'status' => $p->status,
            'duration' => $p->duration,
            'rotation' => (int) $p->rotation,
            'flip_h' => (bool) $p->flip_h,
            'archived' => $p->archived_at !== null,
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
        // Full grouped EXIF first (this handles both exif_read_data AND the
        // Imagick `exif:*` fallback — the latter covers HEIC, where PHP's
        // exif_read_data returns false). Derive the hot fields FROM it so HEIC
        // never falls through with empty metadata.
        $sections = $this->readExifSections($path);
        $flat = [];
        foreach ($sections as $rows) {
            foreach ($rows as $k => $v) {
                if (! isset($flat[$k])) {
                    $flat[$k] = $v;
                }
            }
        }

        $takenRaw = $flat['DateTimeOriginal'] ?? $flat['DateTimeDigitized'] ?? $flat['DateTime'] ?? null;
        $takenAt = null;
        if (is_string($takenRaw) && $takenRaw !== '') {
            $ts = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', trim($takenRaw));
            // A blank EXIF date like "0000:00:00 00:00:00" does NOT return false —
            // it parses to year 0 (rendered "-0001-11-30"), which a timestamp
            // column rejects (SQLSTATE 22007). Only accept a plausible year.
            if ($ts instanceof \DateTimeImmutable) {
                $year = (int) $ts->format('Y');
                if ($year >= 1970 && $year <= 2100) {
                    $takenAt = $ts->format('Y-m-d H:i:s');
                }
            }
        }

        $make = isset($flat['Make']) ? trim($flat['Make']) : '';
        $model = isset($flat['Model']) ? trim($flat['Model']) : '';
        $camera = trim($make.' '.$model);
        $camera = $camera !== '' ? mb_substr($camera, 0, 190) : null;

        $lat = $this->parseGpsString($flat['GPSLatitude'] ?? null, $flat['GPSLatitudeRef'] ?? null);
        $lng = $this->parseGpsString($flat['GPSLongitude'] ?? null, $flat['GPSLongitudeRef'] ?? null);

        return [
            'taken_at' => $takenAt,
            'camera' => $camera,
            'lat' => $lat,
            'lng' => $lng,
            'exif' => $sections,
        ];
    }

    /**
     * Parse a GPS coordinate from a "deg, min, sec" string (values may be
     * rationals "48/1" or decimals "48") + a hemisphere ref (N/S/E/W). Covers
     * both the exif_read_data-array form (sanitized to "48, 8, 5.96") and the
     * Imagick `exif:GPSLatitude` form ("48/1, 8/1, 21321/1000").
     */
    private function parseGpsString(?string $val, ?string $ref): ?float
    {
        if (! is_string($val) || $val === '') {
            return null;
        }
        $parts = array_map('trim', explode(',', $val));
        if (count($parts) < 3) {
            return null;
        }
        $nums = [];
        foreach (array_slice($parts, 0, 3) as $p) {
            if (preg_match('#^(-?\d+(?:\.\d+)?)/(\d+(?:\.\d+)?)$#', $p, $m) === 1) {
                $den = (float) $m[2];
                $nums[] = $den !== 0.0 ? (float) $m[1] / $den : 0.0;
            } elseif (is_numeric($p)) {
                $nums[] = (float) $p;
            } else {
                return null;
            }
        }
        $deg = $nums[0] + $nums[1] / 60 + $nums[2] / 3600;
        $r = strtoupper((string) $ref);
        if ($r === 'S' || $r === 'W') {
            $deg = -$deg;
        }

        return round($deg, 7);
    }

    /**
     * Build the full, sanitized, section-grouped EXIF map for the detail view.
     * Values are coerced to short printable strings (rationals → decimals);
     * binary/noise keys are dropped. Falls back to Imagick's `exif:*` properties
     * (covers HEIC, where exif_read_data often returns nothing). Size-capped so a
     * hostile file can never bloat the row.
     *
     * @return array<string, array<string, string>>
     */
    private function readExifSections(string $path): array
    {
        $raw = null;
        if (function_exists('exif_read_data')) {
            try {
                $raw = @exif_read_data($path, null, true);
            } catch (\Throwable) {
                $raw = null;
            }
        }

        $dropSections = ['FILE', 'THUMBNAIL', 'MAKERNOTE', 'WINXP'];
        $dropKeys = ['MakerNote', 'ComponentsConfiguration', 'UserComment', 'FileName', 'SectionsFound', 'UndefinedTag:0x'];
        $out = [];
        $total = 0;

        if (is_array($raw)) {
            foreach ($raw as $section => $values) {
                if (! is_array($values) || in_array(strtoupper((string) $section), $dropSections, true)) {
                    continue;
                }
                $rows = [];
                foreach ($values as $key => $value) {
                    $k = (string) $key;
                    foreach ($dropKeys as $bad) {
                        if (str_starts_with($k, $bad)) {
                            continue 2;
                        }
                    }
                    $v = $this->exifScalar($value);
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $rows[$k] = $v;
                    if (++$total > 200) {
                        break 2;
                    }
                }
                if ($rows !== []) {
                    $out[(string) $section] = $rows;
                }
            }
        }

        // Imagick fallback (HEIC / when exif_read_data is empty).
        if ($out === [] && class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick($path);
                $props = $im->getImageProperties('exif:*');
                $im->clear();
                $rows = [];
                foreach ($props as $key => $value) {
                    $k = str_replace('exif:', '', (string) $key);
                    $v = $this->exifScalar($value);
                    if ($v === null || $v === '' || in_array($k, $dropKeys, true)) {
                        continue;
                    }
                    $rows[$k] = $v;
                    if (count($rows) > 200) {
                        break;
                    }
                }
                if ($rows !== []) {
                    $out['EXIF'] = $rows;
                }
            } catch (\Throwable) {
                // no EXIF obtainable — leave empty
            }
        }

        return $out;
    }

    /** Coerce any EXIF value to a short printable string; null when unusable. */
    private function exifScalar(mixed $value): ?string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $s = $this->exifScalar($item);
                if ($s !== null && $s !== '') {
                    $parts[] = $s;
                }
                if (count($parts) >= 6) {
                    break;
                }
            }

            return $parts === [] ? null : mb_substr(implode(', ', $parts), 0, 160);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $s = trim($value);
        // Rational "a/b" → decimal (e.g. "596/100" → "5.96", "1/1155" kept as-is only for sub-1).
        if (preg_match('#^(-?\d+)/(\d+)$#', $s, $m) === 1) {
            $den = (int) $m[2];
            $num = (int) $m[1];
            if ($den === 0) {
                return '0';
            }
            $q = $num / $den;
            $s = $q >= 1 || $q <= -1 ? rtrim(rtrim(number_format($q, 4, '.', ''), '0'), '.') : $m[1].'/'.$m[2];
        }
        // Drop non-printable / binary blobs.
        if ($s === '' || preg_match('/[\x00-\x08\x0E-\x1F]/', $s) === 1) {
            return null;
        }
        if (! mb_check_encoding($s, 'UTF-8')) {
            $s = (string) mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }

        return mb_substr($s, 0, 160);
    }

    /** Full section-grouped EXIF for the lightbox sidebar (owner-scoped). */
    public function exif(GalleryPhoto $photo): JsonResponse
    {
        $exif = is_array($photo->exif) ? $photo->exif : [];

        return response()->json([
            'id' => $photo->id,
            'name' => $photo->name,
            'mime' => $photo->mime,
            'size' => $photo->size,
            'width' => $photo->width,
            'height' => $photo->height,
            'taken_at' => $photo->taken_at?->toIso8601String(),
            'camera' => $photo->camera,
            'place' => $photo->place,
            'lat' => $photo->lat,
            'lng' => $photo->lng,
            'exif' => $exif,
        ]);
    }

    private function purgeBlobs(GalleryPhoto $photo): void
    {
        foreach ([$photo->storage_path, $photo->motion_path, $photo->poster_path, $photo->playback_path] as $blob) {
            $p = $this->safeBlobPath($blob);
            if ($p !== null) {
                $this->fs()->delete($p);
            }
        }
        $this->fs()->delete($this->thumbPath($photo));
        $this->fs()->delete($this->previewPath($photo));
        // Face crops (the gallery_faces rows cascade on the FK; remove their files).
        foreach (GalleryFace::query()->where('gallery_photo_id', $photo->id)->pluck('crop_path') as $crop) {
            $c = $this->safeBlobPath($crop);
            if ($c !== null && str_starts_with($c, 'gallery/faces/')) {
                $this->fs()->delete($c);
            }
        }
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
        return StorageUsage::wouldExceed($uid, $incoming) ? response()->json(['error' => 'quota'], 413) : null;
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
