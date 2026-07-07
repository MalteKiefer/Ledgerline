<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PurgesOwnedTrash;
use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Services\Bookmarks\FaviconFetcher;
use App\Services\Bookmarks\NetscapeBookmarks;
use App\Support\BlobStore;
use App\Support\OutboundUrl;
use App\Support\Tags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plain (non-encrypted) bookmarks and folders, exposed as a JSON API so the
 * browser renders and mutates them without page reloads.
 */
class BookmarkController extends Controller
{
    use PurgesOwnedTrash;

    public function index(): JsonResponse
    {
        return response()->json([
            'folders' => BookmarkFolder::orderBy('name')->get(['id', 'name', 'parent_id']),
            'bookmarks' => Bookmark::orderByDesc('favorite')->orderByDesc('updated_at')->get()->map(fn (Bookmark $b) => $this->toArray($b)),
        ]);
    }

    /* ---- Folders ---- */

    public function storeFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', Rule::exists('bookmark_folders', 'id')->where('user_id', $request->user()->id)],
        ]);
        $folder = BookmarkFolder::create($data);

        return response()->json(['id' => $folder->id, 'name' => $folder->name, 'parent_id' => $folder->parent_id]);
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

    /* ---- Netscape import / export ---- */

    /** Export every bookmark as a browser-compatible Netscape HTML file. */
    public function export(NetscapeBookmarks $netscape): StreamedResponse
    {
        $html = $netscape->build(BookmarkFolder::orderBy('name')->get(), Bookmark::orderBy('title')->get());

        return response()->streamDownload(fn () => print $html, 'bookmarks.html', [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /** Import a Netscape HTML file; existing URLs are skipped, folders reused by name. */
    public function import(Request $request, NetscapeBookmarks $netscape): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        $entries = $netscape->parse((string) file_get_contents($request->file('file')->getRealPath()));

        $existing = Bookmark::withTrashed()->pluck('url')->flip();
        $folders = BookmarkFolder::pluck('id', 'name');
        $created = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            if (isset($existing[$entry['url']])) {
                $skipped++;

                continue;
            }
            $folderId = null;
            if ($entry['folder'] !== null) {
                $folderId = $folders[$entry['folder']] ?? BookmarkFolder::create(['name' => $entry['folder']])->id;
                $folders[$entry['folder']] = $folderId;
            }
            Bookmark::create([
                'bookmark_folder_id' => $folderId,
                'title' => $entry['title'],
                'url' => $entry['url'],
                'description' => $entry['description'],
                'tags' => Tags::normalize($entry['tags']),
            ]);
            $existing[$entry['url']] = true;
            $created++;
        }

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    /* ---- Metadata / favicons / link health ---- */

    /** Fetch a page's title + description so the editor can prefill them. */
    public function fetchMeta(Request $request): JsonResponse
    {
        $url = $request->validate(['url' => ['required', 'string', 'max:2048', 'regex:#^https?://#i']])['url'];
        abort_unless(OutboundUrl::safe($url), 422);

        try {
            $html = substr(OutboundUrl::client($url, 8)->get($url)->body(), 0, 300_000);
        } catch (\Throwable) {
            return response()->json(['title' => null, 'description' => null]);
        }

        $title = preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) === 1
            ? mb_substr(trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5)), 0, 255) : null;
        $description = preg_match('/<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/is', $html, $m) === 1
            ? mb_substr(trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5)), 0, 1000) : null;

        return response()->json(['title' => $title ?: null, 'description' => $description ?: null]);
    }

    /** Serve the (server-cached) favicon for a bookmark's host. */
    public function favicon(Request $request, FaviconFetcher $favicons): Response
    {
        $host = (string) $request->query('host', '');
        $icon = $favicons->fetch($host);

        // Hosts without a favicon return a cached 1x1 transparent PNG instead of
        // a 404, so the <img> never logs a console error for a missing icon.
        if ($icon === null) {
            $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

            return response($pixel, 200, [
                'Content-Type' => 'image/png',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response(BlobStore::disk()->get($icon['path']), 200, [
            'Content-Type' => $icon['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'public, max-age=604800',
        ]);
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
            'readLater' => (bool) $b->read_later,
            'read' => $b->read_at !== null,
            'dead' => $b->dead_at !== null,
            'trashed' => $b->trashed(),
        ];
    }
}
