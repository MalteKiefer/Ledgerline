<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\BlobStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the signed-in user's stored avatar from our own domain (same-origin,
 * behind authentication), or 404 when none is stored.
 */
class AvatarController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $path = $this->requireUser($request)->avatar;
        $disk = BlobStore::disk();

        abort_if(! is_string($path) || $path === '' || ! $disk->exists($path), 404);

        // The avatar is stored with a real image extension, so the disk sets a
        // correct image Content-Type; nosniff + a script-less sandbox CSP then
        // stop the file from ever being interpreted as anything active if opened
        // directly — matching the other blob-serving endpoints.
        return $disk->response($path, 'avatar', [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }
}
