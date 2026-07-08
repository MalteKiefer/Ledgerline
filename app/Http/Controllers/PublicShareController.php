<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\AppSettings;
use App\Models\Photo;
use App\Models\PublicShare;
use App\Services\Notifications\ChannelNotifier;
use App\Support\BlobStore;
use App\Support\Shareable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Public, tokenised read-only links to a photo album for people without an
 * account: an HTML gallery view. The authenticated half (create/revoke/email)
 * is owner-only.
 */
class PublicShareController extends Controller
{
    /**
     * Slugs public sharing accepts. This is a deliberate SUBSET of the global
     * Shareable registry: public (no-auth) links must never widen to
     * notes/files/folders/photos — only whole albums.
     */
    private const ALLOWED = ['albums'];

    /** Allowed expiry presets, in seconds (null = never). */
    private const EXPIRY = [3600, 86400, 604800, 2592000];

    /** Create (or update) the public link for an owned album. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::ALLOWED)],
            'id' => ['required'],
            'expires_in' => ['nullable', 'integer', Rule::in(self::EXPIRY)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $resource = $this->ownedResource($data['type'], $data['id'], $request->user()->id);
        $share = PublicShare::forResource($resource, $request->user()->id);

        // Expiry applies to the link. An empty password clears it.
        $share->expires_at = ! empty($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null;
        if (array_key_exists('password', $data)) {
            $share->password = filled($data['password']) ? Hash::make($data['password']) : null;
        }
        $share->save();

        return response()->json([
            'ok' => true,
            'url' => $share->url(),
            'expires_at' => $share->expires_at?->toIso8601String(),
            'has_password' => $share->hasPassword(),
        ], 201);
    }

    /** Rotate the token so a leaked URL stops working. */
    public function rotate(Request $request, PublicShare $publicShare): JsonResponse
    {
        abort_unless($publicShare->owner_id === $request->user()->id, 403);
        $publicShare->update(['token' => Str::random(48)]);

        return response()->json(['ok' => true, 'url' => $publicShare->url()]);
    }

    public function destroy(Request $request, PublicShare $publicShare): JsonResponse
    {
        abort_unless($publicShare->owner_id === $request->user()->id, 403);
        $publicShare->delete();

        return response()->json(['ok' => true]);
    }

    /** Email the public link to any address (SMTP required). */
    public function email(Request $request, PublicShare $publicShare, ChannelNotifier $notifier): JsonResponse
    {
        abort_unless($publicShare->owner_id === $request->user()->id, 403);
        abort_unless(ChannelNotifier::mailConfigured(), 422, __('shares.mail_unavailable'));
        $to = $request->validate(['email' => ['required', 'email']])['email'];

        $owner = $request->user()->name ?: $request->user()->email;
        $link = $publicShare->url();
        try {
            $notifier->mailTo(AppSettings::current(), $to, __('shares.mail_subject', ['user' => $owner]), __('shares.mail_body', ['user' => $owner, 'link' => $link]));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    // ---- public (no auth) --------------------------------------------------

    /** Public HTML gallery page for a shared album. */
    public function album(Request $request, PublicShare $publicShare): View
    {
        $resource = $publicShare->shareable;
        abort_unless($resource instanceof Album, 404);
        abort_if($publicShare->isExpired(), 410, __('shares.public_expired'));

        if ($publicShare->hasPassword() && ! $this->unlocked($request, $publicShare)) {
            return view('public-share.password', ['share' => $publicShare, 'error' => false]);
        }

        $photos = $resource->photos()->get(['photos.id']);

        return view('public-share.album', ['share' => $publicShare, 'album' => $resource, 'photos' => $photos]);
    }

    /** Verify the album password and unlock it for this session. */
    public function albumUnlock(Request $request, PublicShare $publicShare): Response|View|RedirectResponse
    {
        $resource = $publicShare->shareable;
        abort_unless($resource instanceof Album, 404);
        abort_if($publicShare->isExpired(), 410, __('shares.public_expired'));

        $given = (string) $request->input('password', '');
        if (! $publicShare->hasPassword() || ! Hash::check($given, $publicShare->password)) {
            return view('public-share.password', ['share' => $publicShare, 'error' => true]);
        }

        $request->session()->put($this->sessionKey($publicShare), true);

        return redirect()->route('public-share.album', $publicShare->token);
    }

    /** Stream a photo of a shared album (thumb/medium/original), no auth. */
    public function photo(Request $request, PublicShare $publicShare, Photo $photo, string $size): Response
    {
        $album = $publicShare->shareable;
        abort_unless($album instanceof Album, 404);
        abort_if($publicShare->isExpired(), 410);
        abort_if($publicShare->hasPassword() && ! $this->unlocked($request, $publicShare), 403);
        abort_unless($album->photos()->whereKey($photo->id)->exists(), 404);

        $path = match ($size) {
            'thumb' => $photo->thumb_path,
            'medium' => $photo->medium_path,
            default => $photo->disk_path,
        };
        $disk = BlobStore::disk();
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, $photo->name, [
            'Content-Type' => $size === 'original' ? $photo->mime_type : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], $size === 'original' ? 'attachment' : 'inline');
    }

    private function sessionKey(PublicShare $publicShare): string
    {
        return 'pubshare_unlock_'.$publicShare->id;
    }

    private function unlocked(Request $request, PublicShare $publicShare): bool
    {
        return (bool) $request->session()->get($this->sessionKey($publicShare), false);
    }

    private function ownedResource(string $type, mixed $id, int $userId): Model
    {
        return Shareable::resolveOwned($type, $id, $userId);
    }
}
