<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryAlbum;
use App\Models\GalleryUploadLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

/**
 * Public (unauthenticated) side of a gallery album upload link: anonymous
 * guests contribute photos into the owner's album. Write-only — no listing or
 * download. The token is the capability; optional password + expiry gate it.
 * Contributed photos land under the OWNER (never the guest) and count against
 * the owner's quota.
 */
class PublicGalleryUploadController extends Controller
{
    public function meta(string $token): JsonResponse
    {
        $link = $this->resolve($token);

        return response()->json([
            'label' => $link->label,
            'album' => $link->album?->name,
            'needs_password' => $link->needsPassword(),
        ]);
    }

    public function store(Request $request, string $token, GalleryController $gallery): JsonResponse
    {
        $link = $this->resolve($token);
        $request->validate([
            'file' => ['required', 'file'],
            'password' => ['nullable', 'string', 'max:200'],
        ]);
        if ($link->needsPassword() && ! Hash::check((string) $request->string('password')->value(), (string) $link->password_hash)) {
            return response()->json(['error' => 'password'], 403);
        }
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }
        $album = GalleryAlbum::withoutGlobalScopes()->where('user_id', $link->user_id)->find($link->gallery_album_id);
        if ($album === null) {
            abort(404);
        }

        $result = $gallery->contribute((int) $link->user_id, $upload, $album);
        if (! ($result['ok'] ?? false)) {
            $error = $result['error'] ?? 'error';

            return response()->json(['error' => $error], $error === 'quota' ? 413 : 415);
        }

        return response()->json(['ok' => true], 201);
    }

    private function resolve(string $token): GalleryUploadLink
    {
        $link = GalleryUploadLink::query()->withoutGlobalScopes()->where('token', $token)->first();
        abort_if(! $link instanceof GalleryUploadLink || $link->isExpired(), 404);

        return $link;
    }
}
