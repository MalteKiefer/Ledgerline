<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryAlbum;
use App\Models\GalleryInternalShare;
use App\Models\GalleryPublicShare;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Owner-side gallery sharing management: public album links (token, optional
 * password/expiry, allow_download) and internal cross-user shares (an album or
 * the whole gallery, viewer-only). All rows are owner-scoped via the models'
 * global scope; recipients are resolved only by exact email (no enumeration).
 */
class GalleryShareController extends Controller
{
    /** Everything the current user shares: public links + internal grants. */
    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        $public = GalleryPublicShare::query()->where('user_id', $uid)->latest('id')->get()
            ->map(fn (GalleryPublicShare $s): array => [
                'id' => $s->id,
                'album_id' => $s->gallery_album_id,
                'album' => $s->album?->name,
                'token' => $s->token,
                'has_password' => $s->needsPassword(),
                'allow_download' => $s->allow_download,
                'expires_at' => $s->expires_at?->toIso8601String(),
            ])->values();

        $internal = GalleryInternalShare::query()->where('owner_id', $uid)->latest('id')->get()
            ->map(fn (GalleryInternalShare $s): array => [
                'id' => $s->id,
                'album_id' => $s->gallery_album_id,
                'album' => $s->album?->name,
                'recipient' => $s->recipient?->email,
                'scope' => $s->isAlbum() ? 'album' : 'library',
                'role' => $s->role,
            ])->values();

        return response()->json(['public' => $public, 'internal' => $internal]);
    }

    // ---- Public album links ----

    public function storePublic(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'album_id' => ['required', 'integer'],
            'password' => ['nullable', 'string', 'min:4', 'max:200'],
            'allow_download' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $album = GalleryAlbum::query()->where('user_id', $user->id)->findOrFail($request->integer('album_id'));

        $share = new GalleryPublicShare;
        $share->forceFill([
            'user_id' => $user->id,
            'gallery_album_id' => $album->id,
            'token' => Str::random(48),
            'password_hash' => $request->filled('password') ? Hash::make($request->string('password')->value()) : null,
            'allow_download' => $request->boolean('allow_download'),
            'expires_at' => $request->date('expires_at'),
        ])->save();

        return response()->json($this->publicPayload($share), 201);
    }

    public function updatePublic(Request $request, int $share): JsonResponse
    {
        $user = $this->requireUser($request);
        $row = GalleryPublicShare::query()->where('user_id', $user->id)->findOrFail($share);
        $request->validate([
            'password' => ['nullable', 'string', 'min:4', 'max:200'],
            'clear_password' => ['sometimes', 'boolean'],
            'allow_download' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        if ($request->boolean('clear_password')) {
            $row->password_hash = null;
        } elseif ($request->filled('password')) {
            $row->password_hash = Hash::make($request->string('password')->value());
        }
        if ($request->has('allow_download')) {
            $row->allow_download = $request->boolean('allow_download');
        }
        if ($request->has('expires_at')) {
            $row->expires_at = $request->date('expires_at');
        }
        $row->save();

        return response()->json($this->publicPayload($row));
    }

    public function destroyPublic(Request $request, int $share): JsonResponse
    {
        $user = $this->requireUser($request);
        GalleryPublicShare::query()->where('user_id', $user->id)->findOrFail($share)->delete();

        return response()->json(['ok' => true]);
    }

    // ---- Internal cross-user shares ----

    public function storeInternal(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'email' => ['required', 'email'],
            'album_id' => ['nullable', 'integer'], // null = whole gallery
            'role' => ['nullable', 'in:viewer,editor'],
        ]);

        $recipient = User::query()->where('email', $request->string('email')->value())->first();
        // Unified 422 for "no such user" AND self-share (no directory enumeration).
        if (! $recipient instanceof User || $recipient->id === $user->id) {
            return response()->json(['message' => 'recipient_invalid'], 422);
        }

        $albumId = null;
        if ($request->filled('album_id')) {
            $album = GalleryAlbum::query()->where('user_id', $user->id)->findOrFail($request->integer('album_id'));
            $albumId = $album->id;
        }
        // Editor (collaborative-album contribution) only makes sense for a specific
        // album; a whole-gallery share is always viewer-only.
        $role = ($request->string('role')->value() === 'editor' && $albumId !== null) ? 'editor' : 'viewer';

        $existing = GalleryInternalShare::query()
            ->where('owner_id', $user->id)
            ->where('recipient_id', $recipient->id)
            ->where('gallery_album_id', $albumId)
            ->first();
        if ($existing instanceof GalleryInternalShare) {
            $existing->forceFill(['role' => $role])->save();

            return response()->json(['ok' => true, 'id' => $existing->id]);
        }

        $share = new GalleryInternalShare;
        $share->forceFill([
            'owner_id' => $user->id,
            'recipient_id' => $recipient->id,
            'gallery_album_id' => $albumId,
            'role' => $role,
        ])->save();

        return response()->json(['ok' => true, 'id' => $share->id], 201);
    }

    public function destroyInternal(Request $request, int $share): JsonResponse
    {
        $user = $this->requireUser($request);
        GalleryInternalShare::query()->where('owner_id', $user->id)->findOrFail($share)->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function publicPayload(GalleryPublicShare $s): array
    {
        return [
            'id' => $s->id,
            'album_id' => $s->gallery_album_id,
            'token' => $s->token,
            'has_password' => $s->needsPassword(),
            'allow_download' => $s->allow_download,
            'expires_at' => $s->expires_at?->toIso8601String(),
        ];
    }
}
