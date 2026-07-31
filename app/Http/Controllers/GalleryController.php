<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryAlbum;
use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Models\GalleryShare;
use App\Models\User;
use App\Services\Gallery\GalleryMl;
use App\Services\Gallery\GalleryProcessor;
use App\Support\DiskTempFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Plaintext-relational Gallery core (final pivot). Photos + videos + albums as
 * owner-scoped rows (OwnsUserData); the original bytes plus the server-generated
 * renditions (thumb/medium webp + motion clip) live plaintext on the file disk.
 * Renditions/EXIF/pHash/dimensions come from GalleryProcessor->process(). When
 * ML is enabled it also produces a CLIP embedding + detected faces, which
 * GalleryMl persists (pgvector, Postgres only) for semantic search + face/people
 * grouping; with ML off or no pgvector the plumbing degrades to plain rows.
 * Public /gallery-share links serve plaintext bytes with an optional
 * rate-limited password gate.
 *
 * Every write is a single-row transaction — no whole-blob re-serialize, so the
 * opaque last-writer-wins loss class of the ZK stores cannot occur.
 */
class GalleryController extends Controller
{
    /** Chunk size handed to the client for chunked uploads (8 MiB). */
    private const PART_SIZE = 8 * 1024 * 1024;

    // ---- Storage helpers ----

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
        $mb = config('gallery.max_upload_mb', 512);

        return (is_numeric($mb) ? (int) $mb : 512) * 1024;
    }

    /** Effective gallery quota in bytes, or null when unlimited (0 MB). */
    private function quotaBytes(int $uid): ?int
    {
        $mb = User::query()->findOrFail($uid)->effectiveGalleryQuotaMb();

        return $mb <= 0 ? null : $mb * 1024 * 1024;
    }

    /** Bytes the user currently occupies: original bytes of live + trashed photos. */
    private function usedBytes(): int
    {
        return (int) GalleryPhoto::withTrashed()->sum('size');
    }

    /**
     * @return array{used: int, quota: int|null}
     */
    private function usage(int $uid): array
    {
        return ['used' => $this->usedBytes(), 'quota' => $this->quotaBytes($uid)];
    }

    /** Filesystem-safe download filename (strips path separators + control chars). */
    private function safeName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'photo' : $clean;
    }

    // ---- Page / listings ----

    /** The gallery page shell, hydrated with the current photos + albums + usage. */
    public function page(Request $request): View
    {
        $uid = (int) $this->requireUser($request)->id;

        return view('gallery.index', [
            'photos' => $this->photoQuery()->get(),
            'albums' => GalleryAlbum::query()->orderByDesc('updated_at')->get(),
            'usage' => $this->usage($uid),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        return response()->json([
            'photos' => $this->photoQuery()->get(),
            'albums' => GalleryAlbum::query()->with('photos:id')->orderByDesc('updated_at')->get(),
            'usage' => $this->usage($uid),
        ]);
    }

    public function trashed(): JsonResponse
    {
        return response()->json([
            'photos' => GalleryPhoto::onlyTrashed()->orderByDesc('deleted_at')->get(),
        ]);
    }

    /**
     * Photos newest-taken first, with a NULL-safe fallback to upload time.
     *
     * @return Builder<GalleryPhoto>
     */
    /**
     * Every gallery_photos column EXCEPT the pgvector `embedding` (512 floats).
     * A bare SELECT * pulls that vector for the whole library on every gallery
     * load — never needed in list context (the ML paths query it via raw SQL).
     *
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'id', 'user_id', 'kind', 'mime', 'size', 'width', 'height', 'taken_at',
        'lat', 'lng', 'camera', 'phash', 'favorite', 'description', 'storage_path',
        'thumb_path', 'medium_path', 'motion_path', 'exif', 'embedded_at',
        'version', 'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * @return Builder<GalleryPhoto>
     */
    private function photoQuery(): Builder
    {
        return GalleryPhoto::query()
            ->select(self::LIST_COLUMNS)
            ->orderByRaw('COALESCE(taken_at, created_at) DESC')
            ->orderByDesc('id');
    }

    // ---- Whole upload ----

    public function upload(Request $request, GalleryProcessor $processor, GalleryMl $ml): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'file' => ['required', 'file', 'max:'.$this->maxUploadKb()],
        ]);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        if ($over = $this->overQuota($uid, (int) $upload->getSize())) {
            return $over;
        }

        $mime = (string) ($upload->getMimeType() ?: $upload->getClientMimeType() ?: 'application/octet-stream');
        $real = $upload->getRealPath();
        $derived = is_string($real) && $real !== ''
            ? $processor->process($real, $mime, withMl: $ml->enabled())
            : null;

        $uuid = Str::uuid()->toString();
        $origPath = 'gallery/'.$uuid;
        $this->fs()->putFileAs('gallery', $upload, $uuid);

        $photo = DB::transaction(fn (): GalleryPhoto => $this->persistPhoto(
            uuid: $uuid,
            origPath: $origPath,
            size: (int) $this->fs()->size($origPath),
            mime: $mime,
            derived: $derived,
        ));

        $this->storeMl($ml, $photo, $derived);

        return response()->json(['photo' => $photo], 201);
    }

    // ---- Chunked upload (large videos) ----

    public function chunkInit(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'name' => ['nullable', 'string', 'max:500'],
            'size' => ['required', 'integer', 'min:0'],
            'mime' => ['nullable', 'string', 'max:120'],
        ]);

        if ($over = $this->overQuota($uid, $request->integer('size'))) {
            return $over;
        }

        $id = Str::uuid()->toString();
        Cache::put($this->sessionKey($uid, $id), [
            'mime' => $request->filled('mime') ? $request->string('mime')->value() : 'application/octet-stream',
        ], now()->addHours(6));

        return response()->json(['id' => $id, 'partSize' => self::PART_SIZE], 201);
    }

    public function chunkPart(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'id' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0'],
            'file' => ['required', 'file'],
        ]);
        $id = $request->string('id')->value();
        if (Cache::get($this->sessionKey($uid, $id)) === null) {
            abort(404);
        }
        $part = $request->file('file');
        if (! $part instanceof UploadedFile) {
            abort(422);
        }
        $part->storeAs($this->tmpDir($uid, $id), (string) $request->integer('index'), ['disk' => $this->disk()]);

        return response()->json(['ok' => true, 'index' => $request->integer('index')]);
    }

    public function chunkComplete(Request $request, GalleryProcessor $processor, GalleryMl $ml): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['id' => ['required', 'string']]);
        $id = $request->string('id')->value();
        /** @var array{mime:string}|null $session */
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
                $size += strlen($buf);
            }
            fclose($in);
        }
        fclose($out);

        if ($over = $this->overQuota($uid, $size)) {
            $this->fs()->deleteDirectory($tmpDir);
            Cache::forget($this->sessionKey($uid, $id));

            return $over;
        }

        $mime = $session['mime'];
        $derived = $processor->process($tmp->path(), $mime, withMl: $ml->enabled());

        $uuid = Str::uuid()->toString();
        $origPath = 'gallery/'.$uuid;
        $stream = fopen($tmp->path(), 'rb');
        if ($stream === false) {
            abort(500);
        }
        $this->fs()->writeStream($origPath, $stream);
        fclose($stream);

        $this->fs()->deleteDirectory($tmpDir);
        Cache::forget($this->sessionKey($uid, $id));

        $photo = DB::transaction(fn (): GalleryPhoto => $this->persistPhoto(
            uuid: $uuid,
            origPath: $origPath,
            size: (int) $this->fs()->size($origPath),
            mime: $mime,
            derived: $derived,
        ));

        $this->storeMl($ml, $photo, $derived);

        return response()->json(['photo' => $photo], 201);
    }

    /**
     * Persist a photo's CLIP embedding + faces (best-effort): an ML sidecar or DB
     * hiccup must never fail the upload — the photo is already saved and the
     * embedding/faces are backfillable via reprocess/gallery:backfill-ml.
     *
     * @param  array<string, mixed>|null  $derived
     */
    private function storeMl(GalleryMl $ml, GalleryPhoto $photo, ?array $derived): void
    {
        if ($derived === null || ! $ml->enabled()) {
            return;
        }
        /** @var ?list<float> $embedding */
        $embedding = $derived['embedding'] ?? null;
        /** @var list<array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}> $faces */
        $faces = is_array($derived['faces'] ?? null) ? $derived['faces'] : [];
        try {
            $ml->storeDerived($photo, ['embedding' => $embedding, 'faces' => $faces]);
        } catch (Throwable) {
            // Best-effort — leave embedding/faces missing, backfill later.
        }
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

    private function sessionKey(int $uid, string $id): string
    {
        return 'gallery.chunk.'.$uid.'.'.$id;
    }

    private function tmpDir(int $uid, string $id): string
    {
        return 'gallery-tmp/'.$uid.'/'.preg_replace('/[^A-Za-z0-9\-]/', '', $id);
    }

    // ---- Download / render ----

    public function raw(Request $request, GalleryPhoto $photo): StreamedResponse
    {
        return $this->streamPath($photo->storage_path, $photo->mime, $this->safeName('photo-'.$photo->id), $request->boolean('download'));
    }

    public function thumb(GalleryPhoto $photo): StreamedResponse
    {
        return $this->streamPath($photo->thumb_path, 'image/webp', 'thumb', false);
    }

    public function medium(GalleryPhoto $photo): StreamedResponse
    {
        return $this->streamPath($photo->medium_path, 'image/webp', 'medium', false);
    }

    public function motion(GalleryPhoto $photo): StreamedResponse
    {
        return $this->streamPath($photo->motion_path, 'video/mp4', 'motion', false);
    }

    /** Stream a disk path with a script-less sandbox + nosniff + immutable cache. */
    private function streamPath(?string $path, ?string $mime, string $filename, bool $download): StreamedResponse
    {
        if (! is_string($path) || $path === '' || ! $this->fs()->exists($path)) {
            abort(404);
        }

        return $this->fs()->response($path, $this->safeName($filename), [
            'Content-Type' => $mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ], $download ? 'attachment' : 'inline');
    }

    // ---- Photo metadata mutations ----

    public function update(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $request->validate([
            'favorite' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:100000'],
            'taken_at' => ['nullable', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $expected = $request->has('version') ? $request->integer('version') : null;

        // The user-editable fields are mass-assignable; taken_at/lat/lng are a
        // correction of server-set columns → forceFill them explicitly.
        $fill = [];
        if ($request->has('favorite')) {
            $fill['favorite'] = $request->boolean('favorite');
        }
        if ($request->has('description')) {
            $fill['description'] = $request->filled('description') ? $request->string('description')->value() : null;
        }
        $force = [];
        if ($request->has('taken_at')) {
            $force['taken_at'] = $request->filled('taken_at') ? $request->date('taken_at') : null;
        }
        if ($request->has('lat')) {
            $force['lat'] = $request->filled('lat') ? $request->float('lat') : null;
        }
        if ($request->has('lng')) {
            $force['lng'] = $request->filled('lng') ? $request->float('lng') : null;
        }

        $result = DB::transaction(function () use ($photo, $fill, $force, $expected): GalleryPhoto|bool|null {
            $fresh = GalleryPhoto::query()->lockForUpdate()->find($photo->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false;
            }
            $fresh->fill($fill);
            $fresh->forceFill($force);
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = GalleryPhoto::query()->find($photo->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['photo' => $result]);
    }

    public function toggle(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $request->validate(['field' => [Rule::in(['favorite'])], 'value' => ['required', 'boolean']]);
        $photo->update(['favorite' => $request->boolean('value')]);

        return response()->json(['photo' => $photo]);
    }

    // ---- Trash lifecycle ----

    public function destroy(GalleryPhoto $photo): JsonResponse
    {
        $photo->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $photo = GalleryPhoto::onlyTrashed()->findOrFail($id);
        $photo->restore();

        return response()->json(['photo' => $photo]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $photo = GalleryPhoto::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($photo): void {
            foreach ($photo->storagePaths() as $p) {
                $this->fs()->delete($p);
            }
            $photo->forceDelete(); // pivot + shares nullOnDelete cascade via FK
        });

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        $n = 0;
        GalleryPhoto::onlyTrashed()->chunkById(100, function ($chunk) use (&$n): void {
            foreach ($chunk as $photo) {
                foreach ($photo->storagePaths() as $p) {
                    $this->fs()->delete($p);
                }
                $photo->forceDelete();
                $n++;
            }
        });

        return response()->json(['deleted' => $n]);
    }

    // ---- Albums ----

    public function albums(): JsonResponse
    {
        return response()->json(['albums' => GalleryAlbum::query()->orderByDesc('updated_at')->get()]);
    }

    public function storeAlbum(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:300']]);
        $album = DB::transaction(fn (): GalleryAlbum => GalleryAlbum::create([
            'name' => $request->string('name')->value(),
        ]));

        return response()->json(['album' => $album], 201);
    }

    public function updateAlbum(Request $request, GalleryAlbum $album): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'name' => ['sometimes', 'string', 'max:300'],
            'cover_photo_id' => ['nullable', 'integer', Rule::exists('gallery_photos', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $expected = $request->has('version') ? $request->integer('version') : null;

        $patch = [];
        if ($request->has('name')) {
            $patch['name'] = $request->string('name')->value();
        }
        if ($request->has('cover_photo_id')) {
            $patch['cover_photo_id'] = $request->filled('cover_photo_id') ? $request->integer('cover_photo_id') : null;
        }

        $result = DB::transaction(function () use ($album, $patch, $expected): GalleryAlbum|bool|null {
            $fresh = GalleryAlbum::query()->lockForUpdate()->find($album->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false;
            }
            $fresh->fill($patch);
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = GalleryAlbum::query()->find($album->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['album' => $result]);
    }

    public function destroyAlbum(GalleryAlbum $album): JsonResponse
    {
        $album->delete(); // soft — photos are untouched (album is only a grouping)

        return response()->json(['ok' => true]);
    }

    /** Attach photos to an album (append at the current tail position). */
    public function addPhotos(Request $request, GalleryAlbum $album): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'photo_ids' => ['required', 'array', 'max:5000'],
            'photo_ids.*' => ['integer', Rule::exists('gallery_photos', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);

        /** @var list<int> $ids */
        $ids = array_values(array_map('intval', array_filter($request->array('photo_ids'), 'is_numeric')));

        DB::transaction(function () use ($album, $ids): void {
            $max = $album->photos()->max('position');
            $pos = is_numeric($max) ? (int) $max : 0;
            $attach = [];
            foreach ($ids as $id) {
                $attach[$id] = ['position' => ++$pos];
            }
            $album->photos()->syncWithoutDetaching($attach);
        });

        return response()->json(['album' => $album->load('photos:id')]);
    }

    public function removePhoto(GalleryAlbum $album, int $photo): JsonResponse
    {
        $album->photos()->detach($photo);

        return response()->json(['ok' => true]);
    }

    public function setCover(Request $request, GalleryAlbum $album): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'photo_id' => ['required', 'integer', Rule::exists('gallery_photos', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);
        $album->update(['cover_photo_id' => $request->integer('photo_id')]);

        return response()->json(['album' => $album]);
    }

    // ---- Public share links (owner side) ----

    public function storeShare(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'kind' => ['required', Rule::in(['album', 'photo'])],
            'gallery_album_id' => ['required_if:kind,album', 'nullable', 'integer', Rule::exists('gallery_albums', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'gallery_photo_id' => ['required_if:kind,photo', 'nullable', 'integer', Rule::exists('gallery_photos', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'password' => ['nullable', 'string', 'max:200'],
            'allow_download' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $kind = $request->string('kind')->value();
        $share = DB::transaction(function () use ($request, $uid, $kind): GalleryShare {
            $share = new GalleryShare;
            $share->forceFill([
                'user_id' => $uid,
                'token' => Str::random(40),
                'kind' => $kind,
                'gallery_album_id' => $kind === 'album' ? $request->integer('gallery_album_id') : null,
                'gallery_photo_id' => $kind === 'photo' ? $request->integer('gallery_photo_id') : null,
                'password_hash' => $request->filled('password') ? Hash::make($request->string('password')->value()) : null,
                'allow_download' => $request->boolean('allow_download'),
                'expires_at' => $request->filled('expires_at') ? $request->date('expires_at') : null,
            ]);
            $share->save();

            return $share;
        });

        return response()->json(['share' => $this->shareView($share)], 201);
    }

    public function updateShare(Request $request, int $share): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'password' => ['nullable', 'string', 'max:200'],
            'remove_password' => ['sometimes', 'boolean'],
            'allow_download' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $expected = $request->has('version') ? $request->integer('version') : null;

        $result = DB::transaction(function () use ($request, $share, $uid, $expected): GalleryShare|bool|null {
            $fresh = GalleryShare::query()->where('id', $share)->where('user_id', $uid)->lockForUpdate()->first();
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false;
            }
            if ($request->boolean('remove_password')) {
                $fresh->password_hash = null;
            } elseif ($request->filled('password')) {
                $fresh->password_hash = Hash::make($request->string('password')->value());
            }
            if ($request->has('allow_download')) {
                $fresh->allow_download = $request->boolean('allow_download');
            }
            if ($request->has('expires_at')) {
                $fresh->expires_at = $request->filled('expires_at') ? $request->date('expires_at') : null;
            }
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = GalleryShare::query()->where('id', $share)->where('user_id', $uid)->first();

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['share' => $this->shareView($result)]);
    }

    public function destroyShare(Request $request, int $share): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        GalleryShare::query()->where('id', $share)->where('user_id', $uid)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * The owner-visible share representation (never leaks the password hash).
     *
     * @return array<string, mixed>
     */
    private function shareView(GalleryShare $share): array
    {
        return [
            'id' => $share->id,
            'token' => $share->token,
            'kind' => $share->kind,
            'gallery_album_id' => $share->gallery_album_id,
            'gallery_photo_id' => $share->gallery_photo_id,
            'needs_password' => $share->needsPassword(),
            'allow_download' => $share->allow_download,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'version' => $share->version,
        ];
    }

    // ---- Public share consumption (unauthenticated /gallery-share/{token}) ----

    public function shareMeta(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);
        if ($share === null) {
            return response()->json(['found' => false], 404);
        }
        if ($share->isExpired()) {
            return response()->json(['found' => true, 'expired' => true], 410);
        }

        return response()->json([
            'found' => true,
            'expired' => false,
            'kind' => $share->kind,
            'needsPassword' => $share->needsPassword(),
            'unlocked' => $this->shareUnlocked($request, $share),
            'allowDownload' => $share->allow_download,
        ]);
    }

    public function shareUnlock(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        $request->validate(['password' => ['required', 'string', 'max:200']]);

        if (! $share->needsPassword() || ! Hash::check($request->string('password')->value(), (string) $share->password_hash)) {
            return response()->json(['ok' => false], 422);
        }
        $request->session()->put($this->shareGateKey($share), true);

        return response()->json(['ok' => true]);
    }

    public function shareManifest(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->shareUnlocked($request, $share), 403);

        $photos = $this->sharePhotos($share)->map(fn (GalleryPhoto $p): array => [
            'id' => $p->id,
            'kind' => $p->kind,
            'mime' => $p->mime,
            'width' => $p->width,
            'height' => $p->height,
            'taken_at' => $p->taken_at?->toIso8601String(),
            'description' => $p->description,
        ])->values();

        return response()->json([
            'kind' => $share->kind,
            'allowDownload' => $share->allow_download,
            'photos' => $photos,
        ]);
    }

    public function sharePhotoRaw(Request $request, string $token, int $photo): StreamedResponse
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->shareUnlocked($request, $share), 403);

        $row = $this->sharePhotos($share)->firstWhere('id', $photo);
        abort_unless($row instanceof GalleryPhoto, 404);

        $variant = $request->string('variant')->value();
        [$path, $mime] = match ($variant) {
            'thumb' => [$row->thumb_path, 'image/webp'],
            'medium' => [$row->medium_path, 'image/webp'],
            default => [$row->storage_path, $row->mime],
        };
        // Only an owner who set allow_download gets an attachment disposition;
        // viewing (inline) is always allowed once past the gate.
        $download = $request->boolean('download') && $share->allow_download;

        return $this->streamPath($path, $mime, 'photo-'.$row->id, $download);
    }

    /** Resolve a share by token WITHOUT any owner scope (public route, maybe a logged-in visitor). */
    private function resolveShare(string $token): ?GalleryShare
    {
        if (! preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return null;
        }

        return GalleryShare::query()->where('token', $token)->first();
    }

    /**
     * The photos a share exposes, scoped strictly to the share owner (bypassing
     * the viewer's own owner scope, since a logged-in stranger may open the link).
     *
     * @return Collection<int, GalleryPhoto>
     */
    private function sharePhotos(GalleryShare $share): Collection
    {
        if ($share->kind === 'photo') {
            $photo = GalleryPhoto::query()->withoutGlobalScopes()
                ->where('user_id', $share->user_id)
                ->whereKey($share->gallery_photo_id)
                ->get();

            return $photo;
        }

        return GalleryPhoto::query()->withoutGlobalScopes()
            ->where('user_id', $share->user_id)
            ->whereHas('albums', fn ($q) => $q->where('gallery_albums.id', $share->gallery_album_id))
            ->get();
    }

    private function shareUnlocked(Request $request, GalleryShare $share): bool
    {
        return ! $share->needsPassword() || (bool) $request->session()->get($this->shareGateKey($share));
    }

    private function shareGateKey(GalleryShare $share): string
    {
        return 'gallery_share_unlocked.'.$share->id;
    }

    // ---- ML: semantic search, people, faces ----

    /** CLIP text search over the user's photo embeddings (empty on sqlite / ML-off). */
    public function search(Request $request, GalleryMl $ml): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $q = trim($request->string('q')->value());
        $scores = $q === '' ? [] : $ml->searchText($uid, $q);

        return response()->json(['photos' => $this->photosInOrder($scores), 'scores' => $scores]);
    }

    /** Image→image similarity: photos nearest to this one (empty on sqlite / ML-off). */
    public function similar(Request $request, GalleryPhoto $photo, GalleryMl $ml): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $scores = $ml->similarTo($uid, $photo);

        return response()->json(['photos' => $this->photosInOrder($scores), 'scores' => $scores]);
    }

    /**
     * Hydrate photo rows in the given id => distance order (owner-scoped).
     *
     * @param  array<int, float>  $scores
     * @return Collection<int, GalleryPhoto>
     */
    private function photosInOrder(array $scores): Collection
    {
        if ($scores === []) {
            /** @var Collection<int, GalleryPhoto> */
            return collect();
        }
        $ids = array_keys($scores);
        $order = array_flip($ids);

        return GalleryPhoto::query()->whereIn('id', $ids)->get()
            ->sortBy(fn (GalleryPhoto $p): int => $order[$p->id] ?? PHP_INT_MAX)
            ->values();
    }

    /** People with at least one non-hidden face (each with a cover + sample crops). */
    public function people(): JsonResponse
    {
        $people = GalleryPerson::query()
            ->whereHas('faces', fn (Builder $q): Builder => $q->where('hidden', false))
            ->with('faces')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (GalleryPerson $p): array => $this->personSummary($p))
            ->values();

        return response()->json(['people' => $people]);
    }

    /** One person with their faces + the photos those faces appear in. */
    public function person(GalleryPerson $person): JsonResponse
    {
        $faces = $person->faces()->orderByDesc('id')->get();
        $photoIds = $faces->pluck('gallery_photo_id')->unique()->values()->all();

        return response()->json([
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'cover' => $person->cover_face_id,
                'version' => $person->version,
            ],
            'faces' => $faces,
            'photos' => GalleryPhoto::query()->whereIn('id', $photoIds)->get(),
        ]);
    }

    public function updatePerson(Request $request, GalleryPerson $person): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $expected = $request->has('version') ? $request->integer('version') : null;
        $name = $request->filled('name') ? $request->string('name')->value() : null;

        $result = DB::transaction(function () use ($person, $name, $expected): GalleryPerson|bool|null {
            $fresh = GalleryPerson::query()->lockForUpdate()->find($person->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false;
            }
            $fresh->name = $name;
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = GalleryPerson::query()->find($person->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['person' => $result]);
    }

    /** Soft-delete a person; detach its faces (they become unassigned). */
    public function destroyPerson(GalleryPerson $person): JsonResponse
    {
        DB::transaction(function () use ($person): void {
            $person->faces()->update(['gallery_person_id' => null]);
            $person->delete();
        });

        return response()->json(['ok' => true]);
    }

    /** Merge one person's faces into another, then soft-delete the source. */
    public function mergePeople(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'source_id' => ['required', 'integer', Rule::exists('gallery_people', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'target_id' => ['required', 'integer', 'different:source_id', Rule::exists('gallery_people', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);
        $source = $request->integer('source_id');
        $target = $request->integer('target_id');

        DB::transaction(function () use ($source, $target): void {
            GalleryFace::query()->where('gallery_person_id', $source)->update(['gallery_person_id' => $target]);
            GalleryPerson::query()->whereKey($source)->first()?->delete();
        });

        return response()->json(['ok' => true, 'target_id' => $target]);
    }

    /** Move a face to another person, or detach it (null). */
    public function assignFace(Request $request, GalleryFace $face): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'gallery_person_id' => ['nullable', 'integer', Rule::exists('gallery_people', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
        ]);
        $face->forceFill([
            'gallery_person_id' => $request->filled('gallery_person_id') ? $request->integer('gallery_person_id') : null,
        ])->save();

        return response()->json(['face' => $face]);
    }

    /** Hide (or unhide) a face from the people views. */
    public function hideFace(Request $request, GalleryFace $face): JsonResponse
    {
        $request->validate(['value' => ['sometimes', 'boolean']]);
        $face->forceFill(['hidden' => $request->has('value') ? $request->boolean('value') : true])->save();

        return response()->json(['face' => $face]);
    }

    /** Stream a face crop (owner-scoped; 404 when the row/crop is absent). */
    public function faceCrop(GalleryFace $face): StreamedResponse
    {
        return $this->streamPath($face->crop_path, 'image/jpeg', 'face-'.$face->id, false);
    }

    /** Re-run ML on one photo (backfill embedding + faces for an off-ML upload). */
    public function reprocess(GalleryPhoto $photo, GalleryMl $ml): JsonResponse
    {
        $ok = $ml->reprocess($photo);

        return response()->json(['ok' => $ok, 'photo' => $photo->fresh()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function personSummary(GalleryPerson $person): array
    {
        // Use the eager-loaded relation when present (people() list) to avoid an
        // N+1 — filter/sort in memory; fall back to a scoped query for
        // single-person callers that pass an unloaded model.
        $faces = $person->relationLoaded('faces')
            ? $person->faces->where('hidden', false)->sortByDesc('id')->values()
            : $person->faces()->where('hidden', false)->orderByDesc('id')->get();
        $samples = $faces->take(4)->map(fn (GalleryFace $f): array => [
            'id' => $f->id,
            'url' => is_string($f->crop_path) && $f->crop_path !== '' ? route('gallery.rel.faces.crop', $f->id) : null,
        ])->values();

        return [
            'id' => $person->id,
            'name' => $person->name,
            'cover' => $person->cover_face_id,
            'version' => $person->version,
            'face_count' => $faces->count(),
            'samples' => $samples,
        ];
    }

    // ---- Shared helpers ----

    /** 413 quota response if used+incoming exceeds the cap, else null. */
    private function overQuota(int $uid, int $incoming): ?JsonResponse
    {
        $quota = $this->quotaBytes($uid);
        if ($quota !== null && $this->usedBytes() + $incoming > $quota) {
            return response()->json(['error' => 'quota'], 413);
        }

        return null;
    }

    /**
     * Persist a photo row with server-set byte/EXIF metadata + store the
     * renditions the processor produced. Never mass-assigns byte fields.
     *
     * @param  array{media_type: string, width: ?int, height: ?int, duration: ?float, content_id: ?string, exif: array<string,mixed>, place: array<string,mixed>, embedding: ?list<float>, phash: ?int, faces: list<mixed>, thumb: ?string, medium: ?string, motion: ?string}|null  $derived
     */
    private function persistPhoto(string $uuid, string $origPath, int $size, string $mime, ?array $derived): GalleryPhoto
    {
        $kind = 'image';
        $width = $height = $phash = null;
        $takenAt = null;
        $lat = $lng = $camera = null;
        $exifJson = null;
        $thumbPath = $mediumPath = $motionPath = null;

        if ($derived !== null) {
            $kind = $derived['media_type'] === 'video' ? 'video' : 'image';
            $width = $derived['width'];
            $height = $derived['height'];
            $phash = $derived['phash'];

            $exif = $derived['exif'];
            $rawTaken = $exif['taken_at'] ?? null;
            $takenAt = $rawTaken instanceof Carbon ? $rawTaken : null;
            $lat = is_numeric($exif['lat'] ?? null) ? (float) $exif['lat'] : null;
            $lng = is_numeric($exif['lon'] ?? null) ? (float) $exif['lon'] : null;
            $camera = is_string($exif['camera'] ?? null) && $exif['camera'] !== '' ? $exif['camera'] : null;
            $exifJson = [
                'taken_at' => $takenAt?->toIso8601String(),
                'lat' => $lat,
                'lng' => $lng,
                'camera' => $camera,
                'place' => $derived['place'] !== [] ? $derived['place'] : null,
            ];

            $thumbPath = $this->putRendition($derived['thumb'], $uuid.'-t.webp');
            $mediumPath = $this->putRendition($derived['medium'], $uuid.'-m.webp');
            $motionPath = $this->putRendition($derived['motion'], $uuid.'-mv');
        }

        $photo = new GalleryPhoto;
        $photo->forceFill([
            'kind' => $kind,
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'taken_at' => $takenAt,
            'lat' => $lat,
            'lng' => $lng,
            'camera' => $camera,
            'phash' => $phash,
            'storage_path' => $origPath,
            'thumb_path' => $thumbPath,
            'medium_path' => $mediumPath,
            'motion_path' => $motionPath,
            'exif' => $exifJson,
        ]);
        $photo->save();

        return $photo;
    }

    /** Write one rendition blob to disk, returning its path, or null if none. */
    private function putRendition(?string $bytes, string $name): ?string
    {
        if ($bytes === null || $bytes === '') {
            return null;
        }
        $path = 'gallery/'.$name;
        $this->fs()->put($path, $bytes);

        return $path;
    }
}
