<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Support\Tags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plain (non-encrypted) bookmarks and folders, exposed as a JSON API so the
 * browser renders and mutates them without page reloads.
 */
class BookmarkController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'folders' => BookmarkFolder::orderBy('name')->get(['id', 'name']),
            'bookmarks' => Bookmark::orderByDesc('favorite')->orderByDesc('updated_at')->get()->map(fn (Bookmark $b) => $this->toArray($b)),
        ]);
    }

    /* ---- Folders ---- */

    public function storeFolder(Request $request): JsonResponse
    {
        $folder = BookmarkFolder::create($request->validate(['name' => ['required', 'string', 'max:120']]));

        return response()->json(['id' => $folder->id, 'name' => $folder->name]);
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
        $request->validate(['favorite' => ['sometimes', 'boolean'], 'trashed' => ['sometimes', 'boolean']]);
        if ($request->has('favorite')) {
            $bookmark->favorite = $request->boolean('favorite');
            $bookmark->save();
        }
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
        Bookmark::onlyTrashed()->forceDelete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'bookmark_folder_id' => ['nullable', Rule::exists('bookmark_folders', 'id')->where('user_id', $request->user()->id)],
            'title' => ['required', 'string', 'max:255'],
            // Only http(s): a javascript:/data:/vbscript: URL would execute on
            // click via the :href binding (stored XSS).
            'url' => ['required', 'string', 'max:2048', 'regex:#^https?://#i'],
            'description' => ['nullable', 'string', 'max:20000'],
            'favorite' => ['sometimes', 'boolean'],
            ...Tags::rules(),
        ]);

        return [
            'bookmark_folder_id' => $v['bookmark_folder_id'] ?? null,
            'title' => $v['title'],
            'url' => $v['url'],
            'description' => $v['description'] ?? null,
            'tags' => Tags::normalize($v['tags'] ?? null),
            'favorite' => (bool) ($v['favorite'] ?? false),
        ];
    }

    /** @return array<string,mixed> */
    private function toArray(Bookmark $b): array
    {
        return [
            'id' => $b->id,
            'folderId' => $b->bookmark_folder_id,
            'title' => $b->title,
            'url' => $b->url,
            'description' => $b->description,
            'tags' => $b->tags ?? [],
            'favorite' => (bool) $b->favorite,
            'trashed' => $b->trashed(),
        ];
    }
}
