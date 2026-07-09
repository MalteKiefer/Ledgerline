<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AvatarFetcher;
use App\Support\BlobStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the signed-in user's avatar from our own domain.
 *
 * The image was downloaded from Pocket-ID at login and stored on the
 * object-storage disk. Serving it here keeps it same-origin (avoiding the
 * cross-origin resource-policy block) and behind authentication.
 */
class AvatarController extends Controller
{
    /**
     * Stream the current user's avatar, or 404 when none is stored.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $path = $request->user()->avatar;
        $disk = BlobStore::disk();

        abort_if(! is_string($path) || $path === '' || ! $disk->exists($path), 404);

        return $disk->response($path, 'avatar', [
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    /**
     * Re-download the avatar from its stored Pocket-ID source URL.
     */
    public function refresh(Request $request, AvatarFetcher $avatars): RedirectResponse
    {
        $ok = $avatars->fetch($request->user(), $request->user()->avatar_url);

        return back()->with('status', __($ok ? 'flash.avatar_refreshed' : 'flash.avatar_refresh_failed'));
    }
}
