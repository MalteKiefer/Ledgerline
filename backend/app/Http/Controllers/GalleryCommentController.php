<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryInternalShare;
use App\Models\GalleryPhoto;
use App\Models\GalleryPhotoComment;
use App\Models\GalleryPhotoReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comments + emoji reactions on gallery photos, usable by the owner AND any
 * user the photo is shared with (internal album/library share). Access is
 * gated per photo — never route-model-bound, since a recipient does not own
 * the photo (the owner scope would 404 it).
 */
class GalleryCommentController extends Controller
{
    public function index(Request $request, int $photo): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $this->accessiblePhoto($uid, $photo);

        $comments = GalleryPhotoComment::query()->with('author:id,name')
            ->where('gallery_photo_id', $photo)->orderBy('id')->get()
            ->map(fn (GalleryPhotoComment $c): array => [
                'id' => $c->id,
                'body' => $c->body,
                'author' => $c->author?->name,
                'mine' => $c->user_id === $uid,
                'created_at' => $c->created_at->toIso8601String(),
            ])->all();

        $reactions = GalleryPhotoReaction::query()->where('gallery_photo_id', $photo)->get();
        $counts = [];
        $mine = null;
        foreach ($reactions as $r) {
            $counts[$r->emoji] = ($counts[$r->emoji] ?? 0) + 1;
            if ($r->user_id === $uid) {
                $mine = $r->emoji;
            }
        }

        return response()->json(['comments' => $comments, 'reactions' => $counts, 'my_reaction' => $mine]);
    }

    public function store(Request $request, int $photo): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $p = $this->accessiblePhoto($uid, $photo);
        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $comment = new GalleryPhotoComment;
        $comment->forceFill([
            'gallery_photo_id' => $p->id,
            'user_id' => $uid,
            'body' => $request->string('body')->value(),
            'created_at' => now(),
        ])->save();

        return response()->json(['id' => $comment->id], 201);
    }

    public function destroy(Request $request, int $comment): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $row = GalleryPhotoComment::query()->findOrFail($comment);
        // Author may delete their own; the photo owner may delete any comment.
        $photo = GalleryPhoto::withoutGlobalScopes()->find($row->gallery_photo_id);
        $isOwner = $photo instanceof GalleryPhoto && (int) $photo->user_id === $uid;
        abort_unless($row->user_id === $uid || $isOwner, 403);
        $row->delete();

        return response()->json(['ok' => true]);
    }

    /** Set (or clear, when emoji is empty) the caller's single reaction. */
    public function react(Request $request, int $photo): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $this->accessiblePhoto($uid, $photo);
        $request->validate(['emoji' => ['nullable', 'string', 'max:16']]);
        $emoji = $request->string('emoji')->value();

        $existing = GalleryPhotoReaction::query()->where('gallery_photo_id', $photo)->where('user_id', $uid)->first();
        if ($emoji === '' || ($existing && $existing->emoji === $emoji)) {
            $existing?->delete(); // toggle off / clear

            return response()->json(['ok' => true, 'my_reaction' => null]);
        }
        if ($existing) {
            $existing->forceFill(['emoji' => $emoji])->save();
        } else {
            (new GalleryPhotoReaction)->forceFill(['gallery_photo_id' => $photo, 'user_id' => $uid, 'emoji' => $emoji, 'created_at' => now()])->save();
        }

        return response()->json(['ok' => true, 'my_reaction' => $emoji]);
    }

    /**
     * Resolve a photo the caller may access (owns it, or it is shared with them
     * via an internal album/library share). 404 otherwise.
     */
    private function accessiblePhoto(int $uid, int $photoId): GalleryPhoto
    {
        $photo = GalleryPhoto::withoutGlobalScopes()->whereNull('deleted_at')->find($photoId);
        abort_unless($photo instanceof GalleryPhoto, 404);
        if ((int) $photo->user_id === $uid) {
            return $photo;
        }

        // A share from the photo's owner to this user that exposes it: the whole
        // gallery (album null), or an album that contains the photo.
        $shared = GalleryInternalShare::query()->withoutGlobalScopes()
            ->where('owner_id', $photo->user_id)
            ->where('recipient_id', $uid)
            ->get()
            ->contains(function (GalleryInternalShare $s) use ($photo): bool {
                if ($s->gallery_album_id === null) {
                    return true; // whole-gallery share
                }

                return $photo->albums()->withoutGlobalScopes()->whereKey($s->gallery_album_id)->exists();
            });
        abort_unless($shared, 404);

        return $photo;
    }
}
