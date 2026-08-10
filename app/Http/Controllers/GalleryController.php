<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryPhoto;
use App\Support\BlobStore;
use App\Support\DiskTempFile;
use App\Support\FilesUsage;
use App\Support\ImageManagerFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
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

    private const MIME_ALLOW = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'];

    // ---- Listings ----

    public function data(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $photos = GalleryPhoto::query()->orderByDesc('created_at')->orderByDesc('id')->get()
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
            'file' => ['required', 'file', 'mimes:'.implode(',', self::MIME_ALLOW), 'max:'.$this->maxUploadKb()],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }
        if ($over = $this->overQuota($uid, (int) $upload->getSize())) {
            return $over;
        }

        $real = $upload->getRealPath();
        $sha = is_string($real) ? (hash_file('sha256', $real) ?: null) : null;
        $dims = is_string($real) ? @getimagesize($real) : false;
        $path = 'gallery/'.Str::uuid()->toString();
        $this->fs()->putFileAs('gallery', $upload, basename($path));

        $name = $upload->getClientOriginalName();
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        $photo = DB::transaction(fn (): GalleryPhoto => $this->persist(
            $uid,
            $name !== '' ? $name : 'photo',
            $path,
            (int) $this->fs()->size($path),
            $mime !== '' ? $mime : null,
            $sha,
            is_array($dims) ? (int) $dims[0] : null,
            is_array($dims) ? (int) $dims[1] : null,
        ));

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
        if ($mime === null || ! str_starts_with($mime, 'image/')) {
            $this->fs()->deleteDirectory($tmpDir);
            Cache::forget($this->sessionKey($uid, $id));
            abort(415);
        }
        $dims = @getimagesize($tmp->path());

        $path = 'gallery/'.Str::uuid()->toString();
        $stream = fopen($tmp->path(), 'rb');
        if ($stream === false) {
            abort(500);
        }
        $this->fs()->writeStream($path, $stream);
        fclose($stream);
        $this->fs()->deleteDirectory($tmpDir);
        Cache::forget($this->sessionKey($uid, $id));

        $name = $session['name'] !== '' ? $session['name'] : 'photo';
        $photo = DB::transaction(fn (): GalleryPhoto => $this->persist(
            $uid, $name, $path, $size, $mime, $sha,
            is_array($dims) ? (int) $dims[0] : null,
            is_array($dims) ? (int) $dims[1] : null,
        ));

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

    public function thumb(Request $request, GalleryPhoto $photo, ImageManagerFactory $images): StreamedResponse
    {
        $this->requireUser($request);
        $mime = (string) $photo->mime;
        abort_unless(str_starts_with($mime, 'image/'), 404);
        abort_if((int) $photo->size > self::THUMB_MAX_SRC_BYTES, 404);

        $thumbPath = 'gallery/thumb/'.$photo->id.'-'.$photo->version.'.webp';
        if (! $this->fs()->exists($thumbPath)) {
            $src = (string) $photo->storage_path;
            abort_if($src === '' || ! $this->fs()->exists($src), 404);
            try {
                $tmp = DiskTempFile::create('llgthumb')->withExtension('img');
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
                $webp = (string) $images->make()->decodePath($tmp->path())->cover(400, 400)->encode(new WebpEncoder(quality: 78));
                $this->fs()->put($thumbPath, $webp);
            } catch (\Throwable) {
                abort(404);
            }
        }

        return $this->fs()->response($thumbPath, 'thumb.webp', [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    // ---- Mutations ----

    public function favorite(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $this->requireUser($request);
        $photo->forceFill(['favorite' => $request->boolean('favorite')])->save();

        return response()->json(['ok' => true]);
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

    // ---- Helpers ----

    /** @return array{id:int,name:string,mime:?string,width:?int,height:?int,size:int,favorite:bool,created_at:?string} */
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
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    private function persist(int $uid, string $name, string $path, int $size, ?string $mime, ?string $sha, ?int $w, ?int $h): GalleryPhoto
    {
        $photo = new GalleryPhoto;
        $photo->forceFill([
            'user_id' => $uid,
            'storage_path' => $path,
            'name' => $name,
            'mime' => $mime,
            'size' => $size,
            'sha256' => $sha,
            'width' => $w,
            'height' => $h,
        ]);
        $photo->save();

        return $photo;
    }

    private function purgeBlobs(GalleryPhoto $photo): void
    {
        $src = $this->safeBlobPath($photo->storage_path);
        if ($src !== null) {
            $this->fs()->delete($src);
        }
        $this->fs()->delete('gallery/thumb/'.$photo->id.'-'.$photo->version.'.webp');
    }

    private function safeBlobPath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || str_contains($path, '..') || ! str_starts_with($path, 'gallery/')) {
            return null;
        }

        return $path;
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
