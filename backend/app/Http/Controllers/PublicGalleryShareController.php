<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsGalleryPhoto;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use App\Models\GalleryPublicShare;
use App\Support\ShareGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public (unauthenticated) consumption of a gallery album share link. Resolve by
 * sha256(token) with the owner scope removed; gate an optional password via a
 * session or stateless grant; expose the album's photo manifest and stream
 * thumb/preview/original bytes (sandboxed). Download is gated by allow_download.
 */
class PublicGalleryShareController extends Controller
{
    use StreamsGalleryPhoto;

    public function meta(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);
        if ($share === null || $share->isExpired() || $this->album($share) === null) {
            return response()->json(['found' => false], 404);
        }
        $album = $this->album($share);
        $unlocked = $this->unlocked($request, $share);
        $reveal = ! $share->needsPassword() || $unlocked;

        return response()->json([
            'found' => true,
            'name' => $reveal ? $album?->name : null,
            'count' => $reveal ? $this->photos($share)->count() : null,
            'needsPassword' => $share->needsPassword(),
            'unlocked' => $unlocked,
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
        if ($request->hasSession()) {
            $request->session()->put($this->gateKey($share), true);
        }

        return response()->json(['ok' => true, 'grant' => ShareGrant::issue($share)]);
    }

    public function manifest(Request $request, string $token): JsonResponse
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->unlocked($request, $share), 403);
        $album = $this->album($share);
        abort_if($album === null, 404);

        $photos = $this->photos($share)->map(fn (GalleryPhoto $p): array => [
            'id' => $p->id,
            'name' => $p->name,
            'media_type' => $p->media_type,
            'taken_at' => $p->taken_at?->toIso8601String(),
            'width' => $p->width,
            'height' => $p->height,
        ])->values();

        return response()->json([
            'name' => $album->name,
            'allowDownload' => $share->allow_download,
            'photos' => $photos,
        ]);
    }

    public function thumb(Request $request, string $token, int $photo): StreamedResponse
    {
        return $this->streamGalleryThumb($this->photoOr404($request, $token, $photo));
    }

    public function preview(Request $request, string $token, int $photo): StreamedResponse
    {
        return $this->streamGalleryPreview($this->photoOr404($request, $token, $photo));
    }

    public function raw(Request $request, string $token, int $photo): StreamedResponse
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->unlocked($request, $share), 403);
        $row = $this->photos($share)->firstWhere('id', $photo);
        abort_unless($row instanceof GalleryPhoto, 404);
        $wantsDownload = $request->boolean('download');
        abort_if($wantsDownload && ! $share->allow_download, 403);

        return $this->streamGalleryOriginal($row, $wantsDownload);
    }

    // ---- internals ----

    private function photoOr404(Request $request, string $token, int $photo): GalleryPhoto
    {
        $share = $this->resolveShare($token);
        abort_if($share === null || $share->isExpired(), 404);
        abort_unless($this->unlocked($request, $share), 403);
        $row = $this->photos($share)->firstWhere('id', $photo);
        abort_unless($row instanceof GalleryPhoto, 404);

        return $row;
    }

    private function resolveShare(string $token): ?GalleryPublicShare
    {
        if (! preg_match('/^[A-Za-z0-9]{1,64}$/', $token)) {
            return null;
        }

        return GalleryPublicShare::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->first();
    }

    private function album(GalleryPublicShare $share): ?GalleryAlbum
    {
        return GalleryAlbum::withoutGlobalScopes()
            ->where('user_id', $share->user_id)
            ->whereKey($share->gallery_album_id)
            ->first();
    }

    /**
     * The album's non-trashed photos, owner-scoped (owner scope removed so a
     * stranger may open the link).
     *
     * @return Collection<int, GalleryPhoto>
     */
    private function photos(GalleryPublicShare $share): Collection
    {
        $album = $this->album($share);
        if ($album === null) {
            return new Collection;
        }

        return $album->photos()
            ->withoutGlobalScopes()
            ->where('gallery_photos.user_id', $share->user_id)
            ->whereNull('gallery_photos.deleted_at')
            ->orderByRaw('COALESCE(gallery_photos.taken_at, gallery_photos.created_at) DESC')
            ->get();
    }

    private function unlocked(Request $request, GalleryPublicShare $share): bool
    {
        if (! $share->needsPassword()) {
            return true;
        }
        $grant = $request->header('X-Share-Grant');
        if (! is_string($grant) || $grant === '') {
            $q = $request->query('grant');
            $grant = is_string($q) ? $q : null;
        }
        if (ShareGrant::valid($grant, $share)) {
            return true;
        }

        return $request->hasSession() && (bool) $request->session()->get($this->gateKey($share));
    }

    private function gateKey(GalleryPublicShare $share): string
    {
        return 'gallery_share_unlocked.'.$share->id;
    }
}
