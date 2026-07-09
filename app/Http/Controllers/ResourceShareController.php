<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AppSettings;
use App\Models\PublicShare;
use App\Models\ResourceShare;
use App\Models\User;
use App\Services\Notifications\ChannelNotifier;
use App\Support\Shareable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cross-user sharing management: grant/revoke another user's access to a
 * resource you own, and list what you share (and what is shared with you). The
 * per-model SharesWithUsers trait enforces visibility + write permission; this
 * controller only manages the grants.
 */
class ResourceShareController extends Controller
{
    /**
     * Slugs cross-user sharing accepts. Explicit allow-list validated against
     * the global Shareable registry — cross-user sharing supports the full
     * set, but keeping it enumerated here means the registry can't silently
     * widen what this controller permits.
     */
    // Every module is zero-knowledge encrypted: a recipient can't decrypt a
    // resource (no per-recipient key re-wrap exists) and a write-sharee editing
    // one would re-seal it with THEIR key, permanently locking the owner out. So
    // nothing is currently shareable cross-user.
    private const ALLOWED = [];

    /** What I share out + what others shared with me. */
    public function data(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $mine = ResourceShare::with('sharedWith:id,name,email')
            ->where('owner_id', $userId)->get()
            ->map(fn (ResourceShare $s): array => [
                'id' => $s->id,
                'type' => Shareable::slugFor($s->shareable_type) ?? $s->shareable_type,
                'resource_id' => $s->shareable_id,
                'permission' => $s->permission,
                'user' => ['name' => $s->sharedWith?->name, 'email' => $s->sharedWith?->email],
            ]);

        $withMe = ResourceShare::with('owner:id,name,email')
            ->where('shared_with_user_id', $userId)->get()
            ->map(fn (ResourceShare $s): array => [
                'id' => $s->id,
                'type' => Shareable::slugFor($s->shareable_type) ?? $s->shareable_type,
                'resource_id' => $s->shareable_id,
                'permission' => $s->permission,
                'owner' => ['name' => $s->owner?->name, 'email' => $s->owner?->email],
            ]);

        $public = PublicShare::where('owner_id', $userId)->get()
            ->map(fn ($p): array => [
                'id' => $p->id,
                'type' => Shareable::slugFor($p->shareable_type) ?? $p->shareable_type,
                'resource_id' => $p->shareable_id,
                'url' => $p->url(),
                'expires_at' => $p->expires_at?->toIso8601String(),
                'has_password' => $p->hasPassword(),
            ]);

        return response()->json(['shared_by_me' => $mine, 'shared_with_me' => $withMe, 'public' => $public]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::ALLOWED)],
            'id' => ['required'],
            'email' => ['required', 'email'],
            'permission' => ['required', Rule::in([ResourceShare::READ, ResourceShare::WRITE])],
        ]);

        $resource = $this->ownedResource($data['type'], $data['id'], $request->user()->id);
        $target = User::where('email', $data['email'])->first();
        abort_if($target === null, 422, 'No user with that email.');
        abort_if($target->id === $request->user()->id, 422, 'You cannot share with yourself.');

        $share = $resource->shareWith($target, $data['permission']);

        // Internal share → in-app notification for the recipient.
        AppNotification::record(
            $target->id,
            'info',
            __('shares.notify_title', ['user' => $request->user()->name ?: $request->user()->email]),
            $this->resourceLabel($resource),
            'share',
        );

        return response()->json(['ok' => true, 'id' => $share->id, 'link' => $this->linkFor($data['type'])], 201);
    }

    /** Email the recipient a link to the shared resource (SMTP required). */
    public function email(Request $request, ResourceShare $share, ChannelNotifier $notifier): JsonResponse
    {
        abort_unless($share->owner_id === $request->user()->id, 403);
        abort_unless(ChannelNotifier::mailConfigured(), 422, __('shares.mail_unavailable'));

        $to = $share->sharedWith?->email;
        abort_if(blank($to), 422);

        $type = Shareable::slugFor($share->shareable_type);
        $link = $this->linkFor($type);
        $owner = $request->user()->name ?: $request->user()->email;

        try {
            $notifier->mailTo(
                AppSettings::current(),
                (string) $to,
                __('shares.mail_subject', ['user' => $owner]),
                __('shares.mail_body', ['user' => $owner, 'link' => $link]),
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'detail' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /** App link to the page that lists a shared resource type. */
    private function linkFor(?string $type): string
    {
        return url('/');
    }

    private function resourceLabel(Model $resource): string
    {
        return (string) ($resource->name ?? $resource->title ?? '');
    }

    public function destroy(Request $request, ResourceShare $share): JsonResponse
    {
        // Only the owner of the grant may revoke it.
        abort_unless($share->owner_id === $request->user()->id, 403);
        $share->delete();

        return response()->json(['ok' => true]);
    }

    /** Resolve a shareable the caller actually OWNS (not merely one shared with them). */
    private function ownedResource(string $type, mixed $id, int $userId): Model
    {
        return Shareable::resolveOwned($type, $id, $userId);
    }
}
