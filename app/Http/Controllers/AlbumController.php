<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Photo;
use App\Models\PublicShare;
use App\Models\ResourceShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Photo albums: user-owned collections that can be shared with other users
 * (ResourceShare) and via a public link (PublicShare). Visibility comes from the
 * Album model's SharesWithUsers scope (owned OR shared); edits are guarded by
 * canEdit(), management (delete) by ownership.
 */
class AlbumController extends Controller
{
    public function index(): View
    {
        return view('gallery.albums');
    }

    /** Albums the user owns or that are shared with them. */
    public function data(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $albums = Album::withCount('photos')->with('cover')->orderBy('name')->get()
            ->map(fn (Album $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'count' => $a->photos_count,
                'owned' => $a->isOwnedBy($userId),
                'cover' => $a->cover ? route('gallery.image', ['photo' => $a->cover, 'size' => 'thumb']) : null,
            ]);

        return response()->json(['albums' => $albums]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'photo_ids' => ['array'],
            'photo_ids.*' => ['integer'],
        ]);

        $album = Album::create(['name' => $data['name']]);
        if (! empty($data['photo_ids'])) {
            $album->photos()->syncWithoutDetaching($this->ownedPhotoIds($request, $data['photo_ids']));
            $this->ensureCover($album);
        }

        return response()->json(['id' => $album->id], 201);
    }

    public function show(Album $album): View
    {
        return view('gallery.album', ['album' => $album]);
    }

    public function showData(Request $request, Album $album): JsonResponse
    {
        $photos = $album->photos()->get()->map(fn (Photo $p): array => [
            'id' => $p->id,
            'thumb' => route('gallery.image', ['photo' => $p, 'size' => 'thumb']),
        ]);

        return response()->json([
            'album' => ['id' => $album->id, 'name' => $album->name, 'owned' => $album->isOwnedBy($request->user()->id), 'can_edit' => $album->canEdit($request->user()->id)],
            'photos' => $photos->values(),
        ]);
    }

    public function update(Request $request, Album $album): JsonResponse
    {
        abort_unless($album->canEdit($request->user()->id), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'cover_photo_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        $attrs = [];
        if (array_key_exists('name', $data)) {
            $attrs['name'] = $data['name'];
        }
        if (array_key_exists('cover_photo_id', $data)) {
            $coverId = $data['cover_photo_id'];
            abort_unless($coverId === null || $album->photos()->whereKey($coverId)->exists(), 422);
            $attrs['cover_photo_id'] = $coverId;
        }
        $album->forceFill($attrs)->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Album $album): JsonResponse
    {
        abort_unless($album->isOwnedBy($request->user()->id), 403);
        // Clean up the album's shares so no dangling internal/public share rows
        // survive (the photos themselves are untouched — pivot rows drop with it).
        $morph = $album->getMorphClass();
        ResourceShare::where('shareable_type', $morph)->where('shareable_id', $album->getKey())->delete();
        PublicShare::where('shareable_type', $morph)->where('shareable_id', $album->getKey())->delete();
        $album->photos()->detach();
        $album->delete();

        return response()->json(['ok' => true]);
    }

    public function addPhotos(Request $request, Album $album): JsonResponse
    {
        abort_unless($album->canEdit($request->user()->id), 403);
        $ids = $this->ownedPhotoIds($request, $request->validate([
            'photo_ids' => ['required', 'array'], 'photo_ids.*' => ['integer'],
        ])['photo_ids']);
        $album->photos()->syncWithoutDetaching($ids);
        $this->ensureCover($album);

        return response()->json(['ok' => true]);
    }

    public function removePhotos(Request $request, Album $album): JsonResponse
    {
        abort_unless($album->canEdit($request->user()->id), 403);
        $ids = $request->validate(['photo_ids' => ['required', 'array'], 'photo_ids.*' => ['integer']])['photo_ids'];
        $album->photos()->detach($ids);
        $this->ensureCover($album);

        return response()->json(['ok' => true]);
    }

    /** Keep only the caller's own photos (never add someone else's photo). */
    private function ownedPhotoIds(Request $request, array $ids): array
    {
        return Photo::ownedBy($request->user()->id)
            ->whereIn('id', $ids)->pluck('id')->all();
    }

    /** Point the cover at the first photo if none is set (or the current one left). */
    private function ensureCover(Album $album): void
    {
        $first = $album->photos()->first();
        if ($first === null) {
            $album->forceFill(['cover_photo_id' => null])->save();

            return;
        }
        if ($album->cover_photo_id === null || ! $album->photos()->whereKey($album->cover_photo_id)->exists()) {
            $album->forceFill(['cover_photo_id' => $first->id])->save();
        }
    }
}
