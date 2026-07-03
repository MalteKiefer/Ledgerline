<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Plain (non-encrypted) bookmarks and folders, rendered server-side with plain
 * form posts. Filtering, sorting and search run in PHP.
 */
class BookmarkController extends Controller
{
    public function index(Request $request): View
    {
        $view = (string) $request->query('view', 'all');
        $tag = (string) $request->query('tag', '');
        $q = trim((string) $request->query('q', ''));

        $query = Bookmark::query();
        $view === 'trash' ? $query->whereNotNull('trashed_at') : $query->whereNull('trashed_at');
        if ($view === 'favorites') {
            $query->where('favorite', true);
        } elseif (str_starts_with($view, 'folder:')) {
            $query->where('bookmark_folder_id', (int) substr($view, 7));
        }
        if ($tag !== '') {
            $query->whereJsonContains('tags', $tag);
        }
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")
                ->orWhere('url', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        $bookmarks = $query->orderByDesc('favorite')->orderByDesc('updated_at')->get();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = Bookmark::find($request->integer('edit'));
        } elseif ($request->boolean('new')) {
            $editing = new Bookmark;
        }

        return view('bookmarks.index', [
            'folders' => BookmarkFolder::orderBy('name')->get(),
            'bookmarks' => $bookmarks,
            'allTags' => $this->allTags(),
            'trashCount' => Bookmark::whereNotNull('trashed_at')->count(),
            'view' => $view,
            'activeTag' => $tag,
            'q' => $q,
            'editing' => $editing,
        ]);
    }

    /* ---- Folders ---- */

    public function storeFolder(Request $request): RedirectResponse
    {
        BookmarkFolder::create($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back();
    }

    public function destroyFolder(BookmarkFolder $folder): RedirectResponse
    {
        $folder->delete(); // bookmarks fall back to "no folder"

        return redirect()->route('bookmarks.index');
    }

    /* ---- Bookmarks ---- */

    public function store(Request $request): RedirectResponse
    {
        Bookmark::create($this->validated($request));

        return redirect()->route('bookmarks.index');
    }

    public function update(Request $request, Bookmark $bookmark): RedirectResponse
    {
        $bookmark->update($this->validated($request));

        return redirect()->route('bookmarks.index');
    }

    public function toggleFavorite(Bookmark $bookmark): RedirectResponse
    {
        $bookmark->update(['favorite' => ! $bookmark->favorite]);

        return back();
    }

    public function trash(Bookmark $bookmark): RedirectResponse
    {
        $bookmark->update(['trashed_at' => Carbon::now()]);

        return back();
    }

    public function restore(Bookmark $bookmark): RedirectResponse
    {
        $bookmark->update(['trashed_at' => null]);

        return back();
    }

    public function destroy(Bookmark $bookmark): RedirectResponse
    {
        $bookmark->delete();

        return back();
    }

    public function emptyTrash(): RedirectResponse
    {
        Bookmark::whereNotNull('trashed_at')->delete();

        return redirect()->route('bookmarks.index');
    }

    /* ---- Helpers ---- */

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'bookmark_folder_id' => ['nullable', 'integer', 'exists:bookmark_folders,id'],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:20000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'favorite' => ['sometimes', 'boolean'],
        ]);

        return [
            'bookmark_folder_id' => $v['bookmark_folder_id'] ?? null,
            'title' => $v['title'],
            'url' => $v['url'],
            'description' => $v['description'] ?? null,
            'tags' => $this->parseTags($v['tags'] ?? null),
            'favorite' => $request->boolean('favorite'),
        ];
    }

    /** @return list<string> */
    private function parseTags(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
    }

    /** @return list<string> */
    private function allTags(): array
    {
        return Bookmark::whereNotNull('tags')->pluck('tags')
            ->flatMap(fn ($t) => is_array($t) ? $t : [])
            ->unique()->sort()->values()->all();
    }
}
