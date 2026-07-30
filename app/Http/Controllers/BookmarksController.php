<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Plaintext-relational Bookmarks (pivot Phase 1). Nested folders + bookmarks as
 * owner-scoped rows; per-row writes, soft-delete trash. A folder delete nulls its
 * children's parent + its bookmarks' folder via FK (no manifest reparenting).
 */
class BookmarksController extends Controller
{
    public function page(): View
    {
        return view('bookmarks.index', [
            'folders' => BookmarkFolder::query()->orderBy('name')->get(),
            'bookmarks' => Bookmark::query()->orderByDesc('updated_at')->get(),
        ]);
    }

    // ---- Folders ----
    public function folders(): JsonResponse
    {
        return response()->json(['folders' => BookmarkFolder::query()->orderBy('name')->get()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function folderRules(Request $request): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'parent_id' => ['nullable', 'integer', Rule::exists('bookmark_folders', 'id')->where('user_id', (int) $this->requireUser($request)->id)],
            'color' => ['nullable', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $request->validate($this->folderRules($request));
        $folder = DB::transaction(fn (): BookmarkFolder => BookmarkFolder::create([
            'name' => $request->string('name')->value(),
            'parent_id' => $request->filled('parent_id') ? $request->integer('parent_id') : null,
            'color' => $request->filled('color') ? $request->string('color')->value() : null,
            'icon' => $request->filled('icon') ? $request->string('icon')->value() : null,
        ]));

        return response()->json(['folder' => $folder], 201);
    }

    public function updateFolder(Request $request, BookmarkFolder $folder): JsonResponse
    {
        $request->validate($this->folderRules($request));
        // Guard against making a folder its own ancestor (a cycle).
        $parentId = $request->filled('parent_id') ? $request->integer('parent_id') : null;
        if ($parentId !== null && $this->wouldCycle($folder->id, $parentId)) {
            $parentId = $folder->parent_id;
        }
        $folder->update([
            'name' => $request->string('name')->value(),
            'parent_id' => $parentId,
            'color' => $request->filled('color') ? $request->string('color')->value() : null,
            'icon' => $request->filled('icon') ? $request->string('icon')->value() : null,
        ]);

        return response()->json(['folder' => $folder]);
    }

    /** Move a folder under a new parent (drag & drop). */
    public function moveFolder(Request $request, BookmarkFolder $folder): JsonResponse
    {
        $request->validate(['parent_id' => ['nullable', 'integer', Rule::exists('bookmark_folders', 'id')->where('user_id', (int) $this->requireUser($request)->id)]]);
        $parentId = $request->filled('parent_id') ? $request->integer('parent_id') : null;
        if ($parentId !== null && $this->wouldCycle($folder->id, $parentId)) {
            return response()->json(['error' => 'cycle'], 422);
        }
        $folder->update(['parent_id' => $parentId]);

        return response()->json(['folder' => $folder]);
    }

    private function wouldCycle(int $folderId, int $newParentId): bool
    {
        $cursor = $newParentId;
        $guard = 0;
        while ($cursor !== null && $guard++ < 1000) {
            if ($cursor === $folderId) {
                return true;
            }
            $parent = BookmarkFolder::query()->whereKey($cursor)->value('parent_id');
            $cursor = is_numeric($parent) ? (int) $parent : null;
        }

        return false;
    }

    public function destroyFolder(BookmarkFolder $folder): JsonResponse
    {
        // FK nullOnDelete handles children (parent → null) + bookmarks (folder → null).
        $folder->delete();

        return response()->json(['ok' => true]);
    }

    // ---- Bookmarks ----
    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        return [
            'bookmark_folder_id' => ['nullable', 'integer', Rule::exists('bookmark_folders', 'id')->where('user_id', (int) $this->requireUser($request)->id)],
            'title' => ['nullable', 'string', 'max:500'],
            'url' => ['required', 'string', 'max:2000', 'regex:#^https?://#i'],
            'description' => ['nullable', 'string', 'max:20000'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['string', 'max:100'],
            'favorite' => ['sometimes', 'boolean'],
            'read_later' => ['sometimes', 'boolean'],
            'read' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        /** @var list<string> $tags */
        $tags = array_values(array_filter($request->array('tags'), static fn ($t): bool => is_string($t)));
        $url = trim($request->string('url')->value());
        $host = parse_url($url, PHP_URL_HOST);
        $title = $request->filled('title') ? trim($request->string('title')->value()) : '';
        if ($title === '') {
            $title = is_string($host) ? $host : $url;
        }

        return [
            'bookmark_folder_id' => $request->filled('bookmark_folder_id') ? $request->integer('bookmark_folder_id') : null,
            'title' => $title,
            'url' => $url,
            'description' => $request->filled('description') ? $request->string('description')->value() : null,
            'tags' => $request->has('tags') ? ($tags !== [] ? $tags : null) : null,
            'favorite' => $request->boolean('favorite'),
            'read_later' => $request->boolean('read_later'),
            'read' => $request->boolean('read'),
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(['bookmarks' => Bookmark::query()->orderByDesc('updated_at')->get()]);
    }

    public function trashed(): JsonResponse
    {
        return response()->json(['bookmarks' => Bookmark::onlyTrashed()->orderByDesc('deleted_at')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->rules($request));
        $bookmark = DB::transaction(fn (): Bookmark => Bookmark::create($this->payload($request)));

        return response()->json(['bookmark' => $bookmark], 201);
    }

    public function update(Request $request, Bookmark $bookmark): JsonResponse
    {
        $request->validate($this->rules($request) + ['version' => ['sometimes', 'integer', 'min:0']]);
        $payload = $this->payload($request);
        $expected = $request->has('version') ? $request->integer('version') : null;

        $result = DB::transaction(function () use ($bookmark, $payload, $expected): Bookmark|bool|null {
            $fresh = Bookmark::query()->lockForUpdate()->find($bookmark->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false;
            }
            $fresh->fill($payload);
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = Bookmark::query()->find($bookmark->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['bookmark' => $result]);
    }

    /** Quick flag toggle (favorite / read_later / read). */
    public function toggle(Request $request, Bookmark $bookmark): JsonResponse
    {
        $request->validate(['field' => [Rule::in(['favorite', 'read_later', 'read'])], 'value' => ['required', 'boolean']]);
        $field = $request->string('field')->value();
        $value = $request->boolean('value');
        $patch = [$field => $value];
        // Turning off read-later clears the read flag too (matches the old UX).
        if ($field === 'read_later' && ! $value) {
            $patch['read'] = false;
        }
        $bookmark->update($patch);

        return response()->json(['bookmark' => $bookmark]);
    }

    /** Move a bookmark into a folder (drag & drop / null = root). */
    public function move(Request $request, Bookmark $bookmark): JsonResponse
    {
        $request->validate(['bookmark_folder_id' => ['nullable', 'integer', Rule::exists('bookmark_folders', 'id')->where('user_id', (int) $this->requireUser($request)->id)]]);
        $bookmark->update(['bookmark_folder_id' => $request->filled('bookmark_folder_id') ? $request->integer('bookmark_folder_id') : null]);

        return response()->json(['bookmark' => $bookmark]);
    }

    public function destroy(Bookmark $bookmark): JsonResponse
    {
        $bookmark->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $bookmark = Bookmark::onlyTrashed()->findOrFail($id);
        $bookmark->restore();

        return response()->json(['bookmark' => $bookmark]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        Bookmark::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['ok' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        $n = 0;
        Bookmark::onlyTrashed()->chunkById(200, function ($chunk) use (&$n): void {
            foreach ($chunk as $bookmark) {
                $bookmark->forceDelete();
                $n++;
            }
        });

        return response()->json(['deleted' => $n]);
    }
}
