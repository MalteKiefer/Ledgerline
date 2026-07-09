<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\PublicShare;
use App\Services\Notifications\ChannelNotifier;
use App\Support\Shareable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Public, tokenised read-only links to an owned resource for people without an
 * account. The authenticated half (create/revoke/email) is owner-only. Every
 * module is now zero-knowledge, so the shareable registry is currently empty
 * and no public resource type is accepted; the CRUD surface remains as the
 * extension point should a plaintext, publicly shareable resource return.
 */
class PublicShareController extends Controller
{
    /**
     * Slugs public sharing accepts. A deliberate SUBSET of the global Shareable
     * registry: public (no-auth) links must never widen to zero-knowledge
     * resources. Nothing is publicly shareable at present.
     */
    private const ALLOWED = [];

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

    private function ownedResource(string $type, mixed $id, int $userId): Model
    {
        return Shareable::resolveOwned($type, $id, $userId);
    }
}
