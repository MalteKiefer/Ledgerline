<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsGalleryPhoto;
use App\Models\GalleryInternalShare;
use App\Models\GalleryPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recipient side of internal gallery shares ("shared with me"). Read-only: the
 * recipient browses and streams the owner's photos (a single album subtree, or
 * the whole gallery when gallery_album_id is null) but never mutates them.
 * Every access resolves the share by recipient_id (owner scope removed) so a
 * stranger cannot reach another user's grant.
 */
class SharedGalleryController extends Controller
{
    use StreamsGalleryPhoto;

    /** Shares granted TO the current user. */
    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $rows = GalleryInternalShare::query()->withoutGlobalScopes()
            ->where('recipient_id', $uid)->latest('id')->get()
            ->map(function (GalleryInternalShare $s): array {
                $first = $this->photos($s)->first();

                return [
                    'id' => $s->id,
                    'scope' => $s->isAlbum() ? 'album' : 'library',
                    'name' => $s->isAlbum() ? $s->album?->name : $s->owner?->name,
                    'owner' => $s->owner?->name,
                    'count' => $this->photos($s)->count(),
                    'cover' => $first?->id,
                ];
            })->values();

        return response()->json(['shares' => $rows]);
    }

    public function browse(Request $request, int $share): JsonResponse
    {
        $row = $this->shareForMe($request, $share);
        $photos = $this->photos($row)->map(fn (GalleryPhoto $p): array => [
            'id' => $p->id,
            'name' => $p->name,
            'media_type' => $p->media_type,
            'taken_at' => $p->taken_at?->toIso8601String(),
            'width' => $p->width,
            'height' => $p->height,
        ])->values();

        return response()->json([
            'name' => $row->isAlbum() ? $row->album?->name : $row->owner?->name,
            'photos' => $photos,
        ]);
    }

    public function thumb(Request $request, int $share, int $photo): StreamedResponse
    {
        return $this->streamGalleryThumb($this->photoOr404($request, $share, $photo));
    }

    public function preview(Request $request, int $share, int $photo): StreamedResponse
    {
        return $this->streamGalleryPreview($this->photoOr404($request, $share, $photo));
    }

    public function raw(Request $request, int $share, int $photo): StreamedResponse
    {
        return $this->streamGalleryOriginal($this->photoOr404($request, $share, $photo), $request->boolean('download'));
    }

    // ---- internals ----

    private function shareForMe(Request $request, int $share): GalleryInternalShare
    {
        $uid = (int) $this->requireUser($request)->id;

        return GalleryInternalShare::query()->withoutGlobalScopes()
            ->where('recipient_id', $uid)->findOrFail($share);
    }

    private function photoOr404(Request $request, int $share, int $photo): GalleryPhoto
    {
        $row = $this->photos($this->shareForMe($request, $share))->firstWhere('id', $photo);
        abort_unless($row instanceof GalleryPhoto, 404);

        return $row;
    }

    /**
     * The photos a share exposes: one album's non-trashed photos, or the whole
     * gallery of the owner. Owner scope removed (the caller is the recipient).
     *
     * @return Collection<int, GalleryPhoto>
     */
    private function photos(GalleryInternalShare $share): Collection
    {
        if ($share->isAlbum()) {
            $album = $share->album;
            if ($album === null) {
                return new Collection;
            }

            return $album->photos()
                ->withoutGlobalScopes()
                ->where('gallery_photos.user_id', $share->owner_id)
                ->whereNull('gallery_photos.deleted_at')
                ->orderByRaw('COALESCE(gallery_photos.taken_at, gallery_photos.created_at) DESC')
                ->get();
        }

        return GalleryPhoto::query()->withoutGlobalScopes()
            ->where('user_id', $share->owner_id)
            ->whereNull('deleted_at')
            ->orderByRaw('COALESCE(gallery_photos.taken_at, gallery_photos.created_at) DESC')
            ->get();
    }
}
