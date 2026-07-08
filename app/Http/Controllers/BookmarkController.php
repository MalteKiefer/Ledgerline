<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PurgesOwnedTrash;
use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Zero-knowledge bookmarks and folders, exposed as a JSON API. The browser
 * seals each bookmark's {title, url, description, tags} with the per-user vault
 * key; the server only stores + returns ciphertext (enc_bookmark). No
 * server-side export/import, metadata/favicon fetch, dead-link check or
 * search — those all need the plaintext URL the server never has.
 */
class BookmarkController extends Controller
{
    use PurgesOwnedTrash;

    public function index(): JsonResponse
    {
        return response()->json([
            // name is the sealed {c,n} string; the client decrypts + sorts it.
            'folders' => BookmarkFolder::orderBy('id')->get(['id', 'name', 'parent_id', 'color', 'icon', 'is_encrypted']),
            'bookmarks' => Bookmark::orderByDesc('favorite')->orderByDesc('updated_at')->get()->map(fn (Bookmark $b) => $this->toArray($b)),
        ]);
    }

    /* ---- Folders ---- */

    public function storeFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Sealed folder name (zero-knowledge) — opaque ciphertext.
            'name' => ['required', 'string', 'max:4096'],
            'parent_id' => ['nullable', Rule::exists('bookmark_folders', 'id')->where('user_id', $request->user()->id)],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:40'],
        ]);
        $folder = BookmarkFolder::create([...$data, 'is_encrypted' => true]);

        return response()->json($this->folderArray($folder));
    }

    /** Rename or restyle a folder. */
    public function updateFolder(Request $request, BookmarkFolder $folder): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:4096'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:40'],
        ]);
        if (isset($data['name'])) {
            $data['is_encrypted'] = true;
        }
        $folder->update($data);

        return response()->json($this->folderArray($folder->refresh()));
    }

    /** @return array<string,mixed> */
    private function folderArray(BookmarkFolder $f): array
    {
        return ['id' => $f->id, 'name' => $f->name, 'parent_id' => $f->parent_id, 'color' => $f->color, 'icon' => $f->icon, 'is_encrypted' => (bool) $f->is_encrypted];
    }

    /** Move a bookmark into a folder (or to no folder). */
    public function moveBookmark(Request $request, Bookmark $bookmark): JsonResponse
    {
        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('bookmark_folders', 'id')->where('user_id', $request->user()->id)],
        ]);
        $bookmark->update(['bookmark_folder_id' => $data['folder_id'] ?? null]);

        return response()->json($this->toArray($bookmark->refresh()));
    }

    /** Re-parent a folder (or move it to the root), guarding against cycles. */
    public function moveFolder(Request $request, BookmarkFolder $folder): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', Rule::exists('bookmark_folders', 'id')->where('user_id', $request->user()->id)],
        ]);
        $parentId = $data['parent_id'] ?? null;

        // A folder can't become its own ancestor.
        abort_if($parentId !== null && $this->wouldCycle($request->user()->id, (int) $folder->id, (int) $parentId), 422, __('bookmarks.folder_cycle'));

        $folder->update(['parent_id' => $parentId]);

        return response()->json(['id' => $folder->id, 'name' => $folder->name, 'parent_id' => $folder->parent_id]);
    }

    /** True if making $parentId the parent of $folderId would create a cycle. */
    private function wouldCycle(int $userId, int $folderId, int $parentId): bool
    {
        $parents = BookmarkFolder::where('user_id', $userId)->pluck('parent_id', 'id');
        for ($cur = $parentId; $cur !== null; $cur = $parents[$cur] ?? null) {
            if ((int) $cur === $folderId) {
                return true;
            }
        }

        return false;
    }

    public function destroyFolder(BookmarkFolder $folder): JsonResponse
    {
        $folder->delete(); // bookmarks fall back to "no folder"

        return response()->json(['ok' => true]);
    }

    /* ---- Bookmarks ---- */

    public function store(Request $request): JsonResponse
    {
        $bookmark = Bookmark::create($this->validated($request));

        return response()->json($this->toArray($bookmark), 201);
    }

    public function update(Request $request, Bookmark $bookmark): JsonResponse
    {
        $bookmark->update($this->validated($request));

        return response()->json($this->toArray($bookmark->refresh()));
    }

    public function patch(Request $request, Bookmark $bookmark): JsonResponse
    {
        $request->validate([
            'favorite' => ['sometimes', 'boolean'],
            'trashed' => ['sometimes', 'boolean'],
            'read_later' => ['sometimes', 'boolean'],
            'read' => ['sometimes', 'boolean'],
        ]);
        if ($request->has('favorite')) {
            $bookmark->favorite = $request->boolean('favorite');
        }
        if ($request->has('read_later')) {
            $bookmark->read_later = $request->boolean('read_later');
            $bookmark->read_at = null; // (re-)queued: unread again
        }
        if ($request->has('read')) {
            $bookmark->read_at = $request->boolean('read') ? now() : null;
        }
        $bookmark->save();
        if ($request->has('trashed')) {
            $request->boolean('trashed') ? $bookmark->delete() : $bookmark->restore();
        }

        return response()->json($this->toArray($bookmark));
    }

    public function destroy(Bookmark $bookmark): JsonResponse
    {
        $bookmark->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        return $this->emptyOwnedTrash(Bookmark::class);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        // Zero-knowledge: the browser seals {title, url, description, tags} into
        // enc_bookmark; the plaintext columns are never received. favorite +
        // folder stay plaintext ordering/organisation flags.
        $v = $request->validate([
            'enc_bookmark' => ['required', 'string', 'max:400000'],
            'bookmark_folder_id' => ['nullable', Rule::exists('bookmark_folders', 'id')->where('user_id', $request->user()->id)],
            'favorite' => ['sometimes', 'boolean'],
        ]);

        return [
            'title' => null,
            'url' => null,
            'description' => null,
            'tags' => null,
            'enc_bookmark' => $v['enc_bookmark'],
            'is_encrypted' => true,
            'bookmark_folder_id' => $v['bookmark_folder_id'] ?? null,
            'favorite' => (bool) ($v['favorite'] ?? false),
        ];
    }

    /** @return array<string,mixed> */
    private function toArray(Bookmark $b): array
    {
        return [
            'id' => $b->id,
            'folderId' => $b->bookmark_folder_id,
            // Sealed {title, url, description, tags}; decrypted client-side.
            'enc_bookmark' => $b->enc_bookmark,
            'favorite' => (bool) $b->favorite,
            'readLater' => (bool) $b->read_later,
            'read' => $b->read_at !== null,
            'dead' => $b->dead_at !== null,
            'trashed' => $b->trashed(),
        ];
    }
}
