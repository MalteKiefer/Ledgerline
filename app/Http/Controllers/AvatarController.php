<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AvatarFetcher;
use App\Support\BlobStore;
use Illuminate\Http\JsonResponse;
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
        $path = $this->requireUser($request)->avatar;
        $disk = BlobStore::disk();

        abort_if(! is_string($path) || $path === '' || ! $disk->exists($path), 404);

        // The avatar is stored with a real image extension, so the disk sets a
        // correct image Content-Type; nosniff + a script-less sandbox CSP then
        // stop a (semi-trusted, IdP-supplied) file from ever being interpreted
        // as anything active if it navigated to directly — matching the other
        // blob-serving endpoints.
        return $disk->response($path, 'avatar', [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }

    /**
     * Re-download the avatar from its stored Pocket-ID source URL.
     */
    public function refresh(Request $request, AvatarFetcher $avatars): RedirectResponse|JsonResponse
    {
        $user = $this->requireUser($request);
        $ok = $avatars->fetch($user, $user->avatar_url);

        if ($request->expectsJson()) {
            return response()->json(['refreshed' => $ok]);
        }

        return back()->with('status', __($ok ? 'flash.avatar_refreshed' : 'flash.avatar_refresh_failed'));
    }
}
