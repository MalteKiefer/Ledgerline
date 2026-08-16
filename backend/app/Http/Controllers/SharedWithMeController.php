<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileEntry;
use App\Models\FileFolder;
use App\Models\FolderShare;
use App\Models\FolderShareMember;
use App\Models\User;
use App\Support\StorageUsage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Member side of plaintext cross-user folder sharing (pivot). A user who has been
 * granted a folder share lists what is shared with them, browses ONLY the shared
 * folder's subtree, downloads files, and — as an editor — uploads / renames /
 * deletes within it.
 *
 * Every row is resolved WITHOUT the global owner scope and re-scoped explicitly
 * to the SHARE OWNER's user_id (the member is authenticated, so the default scope
 * would otherwise filter to the member's own rows). Anything outside the shared
 * subtree is a 404. Access is gated by FolderSharePolicy: a non-member is 404
 * (existence hidden); a viewer attempting a mutation is 403. Mutations create /
 * touch files owned by the share owner, so quota is attributed to the owner.
 */
class SharedWithMeController extends Controller
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

    private function maxUploadKb(): int
    {
        $mb = config('files.max_upload_mb', 2048);

        return (is_numeric($mb) ? (int) $mb : 2048) * 1024;
    }

    /** Filesystem-safe download filename (strips path separators + control chars). */
    private function safeName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'file' : $clean;
    }

    // ---- Listing ----

    /** Everything shared with the authenticated user (folder or file, owner, role). */
    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        $memberships = FolderShareMember::query()->where('user_id', $uid)->get();

        $out = [];
        foreach ($memberships as $m) {
            $share = FolderShare::withoutGlobalScopes()->find($m->folder_share_id);
            if (! $share instanceof FolderShare) {
                continue;
            }
            $owner = User::query()->find($share->owner_id);
            $ownerPayload = ['id' => $owner?->id, 'name' => $owner?->name, 'email' => $owner?->email];

            if ($share->isFile()) {
                $name = FileEntry::withoutGlobalScopes()
                    ->where('user_id', $share->owner_id)
                    ->whereKey($share->file_id)
                    ->whereNull('deleted_at')
                    ->value('name');
                if (! is_string($name)) {
                    continue; // shared file is gone or trashed
                }
                $out[] = [
                    'id' => $share->id,
                    'kind' => 'file',
                    'file_name' => $name,
                    'role' => $m->role,
                    'owner' => $ownerPayload,
                ];

                continue;
            }

            $name = FileFolder::withoutGlobalScopes()
                ->where('user_id', $share->owner_id)
                ->whereKey($share->file_folder_id)
                ->whereNull('deleted_at')
                ->value('name');
            if (! is_string($name)) {
                continue; // shared folder is gone or trashed
            }
            $out[] = [
                'id' => $share->id,
                'kind' => 'folder',
                'folder_name' => $name,
                'role' => $m->role,
                'owner' => $ownerPayload,
            ];
        }

        return response()->json(['shares' => $out]);
    }

    // ---- Browse ----

    /** The shared folder's whole subtree (folders + files), or a lone shared file. */
    public function browse(Request $request, int $share): JsonResponse
    {
        $shareModel = $this->resolveForMember($request, $share);

        // A file-share resolves to exactly its one file (no folders, no subtree).
        if ($shareModel->isFile()) {
            $row = $this->sharedSingleFile($shareModel);
            abort_unless($row instanceof FileEntry, 404); // shared file gone / trashed

            return response()->json([
                'share_id' => $shareModel->id,
                'role' => $this->memberRole($request, $shareModel),
                'kind' => 'file',
                'file' => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'mime' => $row->mime,
                    'size' => $row->size,
                    'updated_at' => $row->updated_at?->toIso8601String(),
                ],
            ]);
        }

        $ids = $this->subtreeFolderIds($shareModel);
        abort_if($ids === [], 404); // shared folder gone / trashed

        $folders = FileFolder::withoutGlobalScopes()
            ->where('user_id', $shareModel->owner_id)
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $files = FileEntry::withoutGlobalScopes()
            ->where('user_id', $shareModel->owner_id)
            ->whereIn('file_folder_id', $ids)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'share_id' => $shareModel->id,
            'role' => $this->memberRole($request, $shareModel),
            'kind' => 'folder',
            'root_id' => $shareModel->file_folder_id,
            'folders' => $folders->map(fn (FileFolder $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'parent_id' => $d->parent_id,
            ])->values(),
            'files' => $files->map(fn (FileEntry $f): array => [
                'id' => $f->id,
                'name' => $f->name,
                'mime' => $f->mime,
                'size' => $f->size,
                'file_folder_id' => $f->file_folder_id,
                'updated_at' => $f->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /** Stream a file's plaintext bytes (viewer + editor); sandboxed, nosniff. */
    public function raw(Request $request, int $share, int $file): StreamedResponse
    {
        $shareModel = $this->resolveForMember($request, $share);
        $row = $this->resolveMemberFile($shareModel, $file);
        abort_unless($row instanceof FileEntry, 404);
        if (! $this->fs()->exists($row->storage_path)) {
            abort(404);
        }

        return $this->fs()->response($row->storage_path, $this->safeName($row->name), [
            'Content-Type' => $row->mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    // ---- Editor mutations (files owned by the share owner) ----

    /** Upload a file into the shared subtree (editor only); quota hits the owner. */
    public function upload(Request $request, int $share): JsonResponse
    {
        $shareModel = $this->resolveForMember($request, $share);
        abort_if($shareModel->isFile(), 422); // a lone file-share has no folder to upload into
        abort_unless($this->requireUser($request)->can('contribute', $shareModel), 403);
        $request->validate([
            'file' => ['required', 'file', 'max:'.$this->maxUploadKb()],
            'file_folder_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:500'],
        ]);

        $ids = $this->subtreeFolderIds($shareModel);
        abort_if($ids === [], 404);
        $target = $request->filled('file_folder_id') ? $request->integer('file_folder_id') : $shareModel->file_folder_id;
        // Membership of the target folder in the shared subtree, resolved as a
        // query (bool) so it stays a genuine runtime guard — mirrors the sibling
        // PublicFileShareController's whereIn-scoped subtree access.
        $targetInSubtree = FileFolder::withoutGlobalScopes()
            ->whereKey($target)
            ->whereIn('id', $ids)
            ->exists();
        abort_unless($targetInSubtree, 404);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }

        $ownerId = $shareModel->owner_id;
        $incoming = (int) $upload->getSize();
        if (StorageUsage::wouldExceed($ownerId, $incoming)) {
            return response()->json(['error' => 'quota'], 413);
        }

        $sha = $this->hashUpload($upload);
        $path = 'files/'.Str::uuid()->toString();
        $this->fs()->putFileAs('files', $upload, basename($path));

        $name = $request->filled('name') ? $request->string('name')->value() : $upload->getClientOriginalName();
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();

        $file = new FileEntry;
        // user_id is forced to the SHARE OWNER (never the uploading member) so the
        // file lives in the owner's tree; AssignsOwner's null-stamp is bypassed.
        $file->forceFill([
            'user_id' => $ownerId,
            'file_folder_id' => $target,
            'name' => $name !== '' ? $name : 'file',
            'storage_path' => $path,
            'size' => (int) $this->fs()->size($path),
            'mime' => $mime !== '' ? $mime : null,
            'sha256' => $sha,
        ]);
        $file->save();

        return response()->json(['file' => $file], 201);
    }

    /** Rename a file within the shared subtree (editor only). */
    public function rename(Request $request, int $share, int $file): JsonResponse
    {
        $shareModel = $this->resolveForMember($request, $share);
        abort_unless($this->requireUser($request)->can('contribute', $shareModel), 403);
        $request->validate(['name' => ['required', 'string', 'max:500']]);

        $row = $this->resolveMemberFile($shareModel, $file);
        abort_unless($row instanceof FileEntry, 404);
        $row->forceFill(['name' => $request->string('name')->value()]);
        $row->version = $row->version + 1;
        $row->save();

        return response()->json(['file' => $row]);
    }

    /**
     * Soft-delete a file within the shared subtree (editor only).
     *
     * A LONE file-share is deliberately non-deletable by a member (403, even for
     * an editor): unlike a folder share — a mutable workspace the owner offered —
     * a file-share targets exactly the one file the owner explicitly shared;
     * letting a recipient trash the owner's only shared object is a surprising,
     * destructive side effect that would leave the share dangling. Rename
     * (reversible metadata) stays allowed for an editor; deletion does not.
     */
    public function destroy(Request $request, int $share, int $file): JsonResponse
    {
        $shareModel = $this->resolveForMember($request, $share);
        abort_if($shareModel->isFile(), 403); // members cannot delete the owner's lone shared file
        abort_unless($this->requireUser($request)->can('contribute', $shareModel), 403);

        $row = $this->subtreeFile($shareModel, $file);
        abort_unless($row instanceof FileEntry, 404);
        $row->delete();

        return response()->json(['ok' => true]);
    }

    // ---- Helpers ----

    /** Resolve a share the caller is a member of, else 404 (existence hidden). */
    private function resolveForMember(Request $request, int $shareId): FolderShare
    {
        $user = $this->requireUser($request);
        $share = FolderShare::withoutGlobalScopes()->find($shareId);
        abort_unless($share instanceof FolderShare && $user->can('view', $share), 404);

        return $share;
    }

    /** The caller's role on the share (owner → 'owner'), for the browse payload. */
    private function memberRole(Request $request, FolderShare $share): string
    {
        $uid = (int) $this->requireUser($request)->id;
        if ($share->owner_id === $uid) {
            return 'owner';
        }
        $role = $share->members()->where('user_id', $uid)->value('role');

        return is_string($role) ? $role : 'viewer';
    }

    /**
     * Resolve the file a member is addressing, for either share kind. A folder
     * share authorizes any file within its subtree; a file share authorizes
     * EXACTLY its one file (a mismatched id → 404, hiding it). Both are scoped to
     * the share owner + non-trashed.
     */
    private function resolveMemberFile(FolderShare $share, int $fileId): ?FileEntry
    {
        if ($share->isFile()) {
            return $fileId === (int) $share->file_id ? $this->sharedSingleFile($share) : null;
        }

        return $this->subtreeFile($share, $fileId);
    }

    /** The lone non-trashed file targeted by a file-share, owned by the share owner. */
    private function sharedSingleFile(FolderShare $share): ?FileEntry
    {
        if ($share->file_id === null) {
            return null;
        }

        return FileEntry::withoutGlobalScopes()
            ->where('user_id', $share->owner_id)
            ->whereKey($share->file_id)
            ->whereNull('deleted_at')
            ->first();
    }

    /** A single non-trashed file inside the shared subtree, owned by the share owner. */
    private function subtreeFile(FolderShare $share, int $fileId): ?FileEntry
    {
        $ids = $this->subtreeFolderIds($share);
        if ($ids === []) {
            return null;
        }

        return FileEntry::withoutGlobalScopes()
            ->where('user_id', $share->owner_id)
            ->whereKey($fileId)
            ->whereIn('file_folder_id', $ids)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * The shared folder id plus every non-trashed descendant folder id, scoped to
     * the share owner. Empty when the shared folder itself is gone or trashed.
     *
     * @return list<int>
     */
    /**
     * @return list<int>
     */
    private function subtreeFolderIds(FolderShare $share): array
    {
        $root = FileFolder::withoutGlobalScopes()
            ->where('user_id', $share->owner_id)
            ->whereKey($share->file_folder_id)
            ->whereNull('deleted_at')
            ->first();
        if (! $root instanceof FileFolder) {
            return [];
        }

        $ids = [$root->id];
        $frontier = [$root->id];
        $guard = 0;
        while ($frontier !== [] && $guard++ < 10000) {
            $children = FileFolder::withoutGlobalScopes()
                ->where('user_id', $share->owner_id)
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

    private function hashUpload(UploadedFile $upload): ?string
    {
        $real = $upload->getRealPath();
        if (! is_string($real)) {
            return null;
        }
        $hash = hash_file('sha256', $real);

        return $hash !== false ? $hash : null;
    }
}
