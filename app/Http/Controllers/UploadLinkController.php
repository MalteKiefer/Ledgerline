<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ExtractFileText;
use App\Models\FileFolder;
use App\Models\FileVersion;
use App\Models\StoredFile;
use App\Models\UploadLink;
use App\Support\ArchiveName;
use App\Support\BlobStore;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "File request" links: the owner creates a tokenised link that lets anyone
 * upload files into one chosen folder. The visitor can only upload — never
 * list, view or download — and only sees whether each file succeeded.
 */
class UploadLinkController extends Controller
{
    private const EXPIRY = [3600, 86400, 604800, 2592000, 7776000];

    // ---- Owner (authenticated) ----

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'links' => UploadLink::orderByDesc('created_at')->get()->map(fn (UploadLink $l) => $this->toArray($l)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('file_folders', 'id')->where('user_id', $request->user()->id)],
            'label' => ['nullable', 'string', 'max:120'],
            'extensions' => ['nullable', 'string', 'max:255'],
            'expires_in' => ['nullable', 'integer', Rule::in(self::EXPIRY)],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'max_file_mb' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('files.max_upload_mb', 512)],
        ]);

        $link = new UploadLink;
        $link->forceFill([
            'token' => Str::random(48),
            'user_id' => $request->user()->id,
            'file_folder_id' => $data['folder_id'] ?? null,
            'label' => $data['label'] ?? null,
            'allowed_extensions' => $this->normaliseExtensions($data['extensions'] ?? null),
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
            'password' => filled($data['password'] ?? null) ? $data['password'] : null,
            'max_file_mb' => $data['max_file_mb'] ?? null,
        ])->save();

        return response()->json($this->toArray($link));
    }

    public function destroy(UploadLink $link): JsonResponse
    {
        abort_unless((int) $link->user_id === (int) auth()->id(), 403);
        $link->delete();

        return response()->json(['ok' => true]);
    }

    // ---- Public (no auth) ----

    public function show(Request $request, string $token): View|Response
    {
        $link = $this->resolve($token);
        abort_if($link->isExpired(), 410, __('upload_links.expired'));

        if ($link->isProtected() && ! $this->unlocked($request, $link)) {
            return response()->view('upload-link.password', ['token' => $token, 'error' => false]);
        }

        return response()->view('upload-link.show', [
            'token' => $token,
            'label' => $link->label,
            'extensions' => $link->extensions(),
            'maxMb' => $link->max_file_mb ?: (int) config('files.max_upload_mb', 512),
        ]);
    }

    public function unlock(Request $request, string $token): Response|RedirectResponse
    {
        $link = $this->resolve($token);
        abort_if($link->isExpired(), 410);
        $given = (string) $request->input('password', '');
        if (! $link->isProtected() || ! Hash::check($given, $link->password)) {
            return response()->view('upload-link.password', ['token' => $token, 'error' => true]);
        }
        $request->session()->put($this->sessionKey($link), true);

        return redirect()->route('upload-link.show', $token);
    }

    /** Accept one uploaded file into the link's folder (owned by the link owner). */
    public function upload(Request $request, string $token): JsonResponse
    {
        $link = $this->resolve($token);
        abort_if($link->isExpired(), 410, __('upload_links.expired'));
        abort_if($link->isProtected() && ! $this->unlocked($request, $link), 403);

        $maxKb = ($link->max_file_mb ?: (int) config('files.max_upload_mb', 512)) * 1024;
        $request->validate(['file' => ['required', 'file', 'max:'.$maxKb]]);
        $file = $request->file('file');
        $name = $file->getClientOriginalName();

        abort_unless($link->allowsFilename($name), 422, __('upload_links.type_not_allowed'));

        $ownerId = (int) $link->user_id;
        // Preserve a dropped folder's structure under the link's target folder
        // (owner-scoped, traversal-safe).
        $folderId = $this->resolveUploadFolder($ownerId, $link->file_folder_id, (string) $request->input('path', ''));
        $blob = (string) Str::uuid();

        // Serialize the quota check + write on the owner's per-user key (same as
        // the authenticated paths), so concurrent anonymous uploads can't each
        // pass the same stale quota baseline and overshoot the owner's storage.
        try {
            Cache::lock('files-write:'.$ownerId, 120)->block(20, function () use ($ownerId, $file, $folderId, $name, $blob): void {
                abort_if($this->quotaExceeded($ownerId, (int) $file->getSize()), 413, __('files.quota_exceeded'));
                abort_if(BlobStore::disk()->putFileAs('files', $file, $blob) === false, 500, __('files.upload_failed'));
                try {
                    $stored = new StoredFile;
                    $stored->forceFill([
                        'id' => (string) Str::uuid(),
                        'user_id' => $ownerId,
                        'file_folder_id' => $folderId,
                        'name' => $this->uniqueName($name, $ownerId, $folderId),
                        'blob' => $blob,
                        'size' => (int) $file->getSize(),
                        'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    ])->save();
                    ExtractFileText::dispatch($stored->id, $blob)->afterCommit();
                } catch (\Throwable $e) {
                    BlobStore::disk()->delete('files/'.$blob);
                    throw $e;
                }
            });
        } catch (LockTimeoutException) {
            abort(429, __('files.busy'));
        }

        $link->increment('uploads');

        return response()->json(['ok' => true, 'name' => $name]);
    }

    // ---- helpers ----

    private function resolve(string $token): UploadLink
    {
        $link = UploadLink::withoutGlobalScopes()->where('token', $token)->first();
        abort_if($link === null, 404);

        return $link;
    }

    private function unlocked(Request $request, UploadLink $link): bool
    {
        return (bool) $request->session()->get($this->sessionKey($link));
    }

    private function sessionKey(UploadLink $link): string
    {
        return 'uploadlink:'.$link->token;
    }

    private function normaliseExtensions(?string $raw): ?string
    {
        if (! filled($raw)) {
            return null;
        }
        $exts = array_values(array_filter(array_map(
            fn ($e) => ltrim(strtolower(trim($e)), '.'),
            preg_split('/[,\s]+/', $raw) ?: []
        )));

        return $exts === [] ? null : implode(',', array_unique($exts));
    }

    private function quotaExceeded(int $userId, int $incoming): bool
    {
        $quota = (int) config('files.quota_mb', 0) * 1024 * 1024;
        if ($quota <= 0) {
            return false;
        }
        $used = (int) StoredFile::withoutGlobalScopes()->withTrashed()->where('user_id', $userId)->sum('size')
            + (int) FileVersion::where('user_id', $userId)->sum('size');

        return ($used + $incoming) > $quota;
    }

    /** Find/create the subfolder chain from a dropped relative path; returns the
     *  leaf folder id (or the root when there is no subpath). */
    private function resolveUploadFolder(int $ownerId, ?string $rootId, string $relPath): ?string
    {
        $relPath = str_replace('\\', '/', $relPath);
        $parts = explode('/', $relPath);
        array_pop($parts); // drop the filename
        $parentId = $rootId;
        foreach ($parts as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..') {
                continue; // never traverse up or emit empty segments
            }
            $name = ArchiveName::sanitize($seg);
            if ($name === '') {
                continue;
            }
            $existing = FileFolder::withoutGlobalScopes()->where('user_id', $ownerId)
                ->where('parent_id', $parentId)->where('name', $name)->first();
            if ($existing) {
                $parentId = $existing->id;

                continue;
            }
            $folder = new FileFolder;
            $folder->forceFill(['id' => (string) Str::uuid(), 'user_id' => $ownerId, 'parent_id' => $parentId, 'name' => $name])->save();
            $parentId = $folder->id;
        }

        return $parentId;
    }

    private function uniqueName(string $name, int $userId, ?string $folderId): string
    {
        $used = StoredFile::withoutGlobalScopes()->whereNull('deleted_at')->where('user_id', $userId)
            ->where('file_folder_id', $folderId)->pluck('name')->flip()->all();

        return ArchiveName::unique($name, $used, ' ', true);
    }

    /** @return array<string,mixed> */
    private function toArray(UploadLink $l): array
    {
        return [
            'id' => $l->id,
            'token' => $l->token,
            'url' => route('upload-link.show', $l->token),
            'label' => $l->label,
            'folderId' => $l->file_folder_id,
            'extensions' => $l->extensions(),
            'expiresAt' => $l->expires_at?->toIso8601String(),
            'hasPassword' => $l->isProtected(),
            'uploads' => (int) $l->uploads,
        ];
    }
}
