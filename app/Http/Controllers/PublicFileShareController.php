<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Support\ShareGrant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public (unauthenticated) consumption of a Files share link. Mirrors the
 * Gallery /gallery-share/{token} controller: resolve a link by token (owner
 * scope removed so an anonymous or logged-in stranger can open it), gate an
 * optional password via a session grant, expose the shared subtree's manifest,
 * and stream individual file bytes.
 *
 * A shared FOLDER exposes that folder's whole subtree (descendant folders + all
 * their files); a shared FILE exposes just that file. Nothing outside the shared
 * set is ever streamable. Bytes are served plaintext with a script-less sandbox
 * CSP + nosniff; download (attachment) is gated by the owner's allow_download.
 */
class PublicFileShareController extends Controller
{
    private function disk(): string
    {
        $d = config('files.disk');

        return is_string($d) ? $d : 'files';
    }

    private function fs(): Filesystem
    {
        return Storage::disk($this->disk());
    }

    public function meta(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);
        if ($share === null || $share->isExpired()) {
            return response()->json(['found' => false], 404);
        }
        $name = $this->shareName($share);
        if ($name === null) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found' => true,
            'kind' => $share->kind,
            'name' => $name,
            'needsPassword' => $share->needsPassword(),
            'unlocked' => $this->shareUnlocked($request, $share),
            'allowDownload' => $share->allow_download,
            'expiresAt' => $share->expires_at?->toIso8601String(),
        ]);
    }

    public function unlock(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        $request->validate(['password' => ['required', 'string', 'max:200']]);

        if (! $share->needsPassword() || ! Hash::check($request->string('password')->value(), (string) $share->password_hash)) {
            return response()->json(['ok' => false], 422);
        }
        // Web clients carry the unlock in the session; a tokenless API client has no
        // session, so also hand back a short-lived stateless grant it can present on
        // manifest/raw (X-Share-Grant header or ?grant=). Both paths coexist.
        if ($request->hasSession()) {
            $request->session()->put($this->shareGateKey($share), true);
        }

        return response()->json(['ok' => true, 'grant' => ShareGrant::issue($share)]);
    }

    public function manifest(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->shareUnlocked($request, $share), 403);
        abort_if($this->shareName($share) === null, 404);

        $files = $this->shareFiles($share)->map(fn (FileEntry $f): array => [
            'id' => $f->id,
            'name' => $f->name,
            'mime' => $f->mime,
            'size' => $f->size,
            'file_folder_id' => $f->file_folder_id,
            'updated_at' => $f->updated_at?->toIso8601String(),
        ])->values();

        $folders = $this->shareFolders($share)->map(fn (FileFolder $d): array => [
            'id' => $d->id,
            'name' => $d->name,
            'parent_id' => $d->parent_id,
        ])->values();

        return response()->json([
            'kind' => $share->kind,
            'name' => $this->shareName($share),
            'allowDownload' => $share->allow_download,
            'folders' => $folders,
            'files' => $files,
        ]);
    }

    public function raw(Request $request, string $token, int $file): StreamedResponse
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->shareUnlocked($request, $share), 403);

        $row = $this->shareFiles($share)->firstWhere('id', $file);
        abort_unless($row instanceof FileEntry, 404);

        // Only an owner who set allow_download may pull an attachment; inline
        // viewing is always allowed past the gate. A download request against a
        // download-disabled share is refused (403), not silently downgraded.
        $wantsDownload = $request->boolean('download');
        abort_if($wantsDownload && ! $share->allow_download, 403);

        if (! $this->fs()->exists($row->storage_path)) {
            abort(404);
        }

        return $this->fs()->response($row->storage_path, $this->safeName($row->name), [
            'Content-Type' => $row->mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $wantsDownload ? 'attachment' : 'inline');
    }

    /** Resolve a share by token WITHOUT the owner scope (a logged-in stranger may open it). */
    private function resolveShare(string $token): ?FileShare
    {
        if (! preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return null;
        }

        // Look up by sha256(token), not the raw capability — a DB/backup leak no
        // longer yields a directly-usable link (the plaintext lives only in the URL).
        return FileShare::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->first();
    }

    /** The display name of the shared target, or null if it is gone / trashed. */
    private function shareName(FileShare $share): ?string
    {
        if ($share->kind === 'file') {
            $file = FileEntry::withoutGlobalScopes()
                ->where('user_id', $share->user_id)
                ->whereKey($share->file_id)
                ->whereNull('deleted_at')
                ->first();

            return $file?->name;
        }

        $folder = FileFolder::withoutGlobalScopes()
            ->where('user_id', $share->user_id)
            ->whereKey($share->file_folder_id)
            ->whereNull('deleted_at')
            ->first();

        return $folder?->name;
    }

    /**
     * The files a share exposes, scoped strictly to the share owner and the
     * shared subtree (folder → its whole descendant subtree; file → just it).
     * The owner scope is bypassed because a logged-in stranger may open the link.
     *
     * @return Collection<int, FileEntry>
     */
    private function shareFiles(FileShare $share): Collection
    {
        if ($share->kind === 'file') {
            return FileEntry::withoutGlobalScopes()
                ->where('user_id', $share->user_id)
                ->whereKey($share->file_id)
                ->whereNull('deleted_at')
                ->get();
        }

        $ids = $this->subtreeFolderIds($share);
        if ($ids === []) {
            return new Collection;
        }

        return FileEntry::withoutGlobalScopes()
            ->where('user_id', $share->user_id)
            ->whereIn('file_folder_id', $ids)
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * The shared folder plus every descendant folder (for the manifest tree).
     *
     * @return Collection<int, FileFolder>
     */
    private function shareFolders(FileShare $share): Collection
    {
        $ids = $this->subtreeFolderIds($share);
        if ($ids === []) {
            return new Collection;
        }

        return FileFolder::withoutGlobalScopes()
            ->where('user_id', $share->user_id)
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * The shared folder id plus every non-trashed descendant folder id, scoped
     * to the owner. Empty when the shared folder itself is gone or trashed.
     *
     * @return list<int>
     */
    private function subtreeFolderIds(FileShare $share): array
    {
        if ($share->kind !== 'folder' || $share->file_folder_id === null) {
            return [];
        }
        $root = FileFolder::withoutGlobalScopes()
            ->where('user_id', $share->user_id)
            ->whereKey($share->file_folder_id)
            ->whereNull('deleted_at')
            ->first();
        if ($root === null) {
            return [];
        }

        $ids = [$root->id];
        $frontier = [$root->id];
        $guard = 0;
        while ($frontier !== [] && $guard++ < 10000) {
            $children = FileFolder::withoutGlobalScopes()
                ->where('user_id', $share->user_id)
                ->whereIn('parent_id', $frontier)
                ->whereNull('deleted_at')
                ->pluck('id')->all();
            $frontier = [];
            foreach ($children as $child) {
                if (! is_numeric($child)) {
                    continue;
                }
                $cid = (int) $child;
                $ids[] = $cid;
                $frontier[] = $cid;
            }
        }

        return array_values(array_unique($ids));
    }

    private function shareUnlocked(Request $request, FileShare $share): bool
    {
        if (! $share->needsPassword()) {
            return true;
        }

        // Stateless grant (tokenless API clients) — header or query carrier.
        $grant = $request->header('X-Share-Grant');
        if (! is_string($grant) || $grant === '') {
            $q = $request->query('grant');
            $grant = is_string($q) ? $q : null;
        }
        if (ShareGrant::valid($grant, $share)) {
            return true;
        }

        // Session grant (web clients that unlocked via the browser).
        return $request->hasSession() && (bool) $request->session()->get($this->shareGateKey($share));
    }

    private function shareGateKey(FileShare $share): string
    {
        return 'file_share_unlocked.'.$share->id;
    }

    /** Filesystem-safe download filename (strips path separators + control chars). */
    private function safeName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'file' : $clean;
    }
}
