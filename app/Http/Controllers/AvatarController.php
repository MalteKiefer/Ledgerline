<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\BlobStore;
use App\Support\ImageManagerFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the signed-in user's stored avatar from our own domain (same-origin,
 * behind authentication), or 404 when none is stored — plus upload (store) and
 * remove (destroy). The avatar is a non-secret profile image (not ZK); it is
 * re-encoded server-side to a square JPEG (stripping any metadata / active
 * payload) before it is stored, and served with a script-less sandbox CSP.
 */
class AvatarController extends Controller
{
    // Client sends an already-square crop; the 10 MiB cap is the user's ORIGINAL
    // (checked client-side too). The re-encode normalises whatever arrives.
    private const MAX_KB = 10240; // 10 MiB

    private const SIZE = 512;

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

    /**
     * Upload / replace the signed-in user's avatar. The image is re-encoded to a
     * square SIZE×SIZE JPEG (cover-cropped, metadata stripped) and stored on the
     * blob disk under avatars/{uuid}.jpg; the previous avatar is deleted.
     */
    public function store(Request $request, ImageManagerFactory $images): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:'.self::MAX_KB],
        ]);

        $file = $request->file('avatar');
        if ($file === null || is_array($file)) {
            abort(422, 'invalid image');
        }

        // Re-encode to a normalized square JPEG — this strips EXIF/metadata and
        // any non-image payload, and guarantees a small, uniform avatar.
        $img = $images->make()->decodePath($file->getRealPath());
        $jpeg = (string) $img->cover(self::SIZE, self::SIZE)->encode(new JpegEncoder(quality: 88));

        $disk = BlobStore::disk();
        $path = 'avatars/'.Str::uuid()->toString().'.jpg';
        if ($disk->put($path, $jpeg) === false) {
            abort(500, 'store failed');
        }

        $old = $user->avatar;
        $user->forceFill(['avatar' => $path])->save();
        if (is_string($old) && $old !== '' && $old !== $path) {
            $disk->delete($old);
        }

        return response()->json(['ok' => true, 'has_avatar' => true])->header('Cache-Control', 'no-store');
    }

    /** Remove the signed-in user's avatar (deletes the stored file). */
    public function destroy(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $old = $user->avatar;
        $user->forceFill(['avatar' => null])->save();
        if (is_string($old) && $old !== '') {
            BlobStore::disk()->delete($old);
        }

        return response()->json(['ok' => true, 'has_avatar' => false])->header('Cache-Control', 'no-store');
    }
}
