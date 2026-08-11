<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GalleryFace;
use App\Models\GalleryPerson;
use App\Models\GalleryPhoto;
use App\Support\BlobStore;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Face-recognition "people": browse the auto-grouped clusters, name them, merge,
 * reassign or hide faces. Owner-scoped via the models' global scope (route model
 * binding 404s for a non-owner). Backed by GalleryFaceProcessor (detection +
 * greedy grouping on the worker).
 */
class GalleryPeopleController extends Controller
{
    public function __construct(private readonly GalleryController $gallery) {}

    /** People with ≥1 visible face: named first, then by face count. */
    public function people(Request $request): JsonResponse
    {
        $this->requireUser($request);
        $people = GalleryPerson::query()
            ->where('hidden', false)
            ->withCount(['faces' => fn (Builder $q) => $q->where('hidden', false)])
            ->whereHas('faces', fn (Builder $q) => $q->where('hidden', false))
            ->orderByRaw('name IS NULL')       // named first
            ->orderBy('name')
            ->orderByDesc('faces_count')
            ->get()
            ->map(fn (GalleryPerson $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'count' => (int) ($p->faces_count ?? 0),
                'cover_face_id' => $p->cover_face_id,
            ])->all();

        return response()->json(['people' => $people]);
    }

    /** A person's photos (distinct, newest first). */
    public function person(Request $request, GalleryPerson $person): JsonResponse
    {
        $this->requireUser($request);
        $photos = GalleryPhoto::query()
            ->whereHas('faces', fn ($q) => $q->where('gallery_person_id', $person->id)->where('hidden', false))
            ->orderByRaw('COALESCE(taken_at, created_at) DESC')->orderByDesc('id')
            ->get()->map(fn (GalleryPhoto $p): array => $this->gallery->row($p))->all();

        return response()->json(['person' => ['id' => $person->id, 'name' => $person->name], 'photos' => $photos]);
    }

    public function personUpdate(Request $request, GalleryPerson $person): JsonResponse
    {
        $this->requireUser($request);
        $request->validate(['name' => ['sometimes', 'nullable', 'string', 'max:191']]);
        if ($request->has('name')) {
            $name = $request->filled('name') ? $request->string('name')->value() : null;
            $person->forceFill(['name' => $name])->save();
        }

        return response()->json(['ok' => true]);
    }

    /** Delete a person: its faces become unassigned (kept, re-groupable later). */
    public function personDestroy(Request $request, GalleryPerson $person): JsonResponse
    {
        $this->requireUser($request);
        $person->faces()->update(['gallery_person_id' => null]);
        $person->delete();

        return response()->json(['ok' => true]);
    }

    /** Merge one person into another (fixes an over-split cluster). */
    public function merge(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'from_id' => ['required', 'integer'],
            'into_id' => ['required', 'integer', 'different:from_id'],
        ]);
        $from = GalleryPerson::query()->whereKey($request->integer('from_id'))->first();
        $into = GalleryPerson::query()->whereKey($request->integer('into_id'))->first();
        if (! $from instanceof GalleryPerson || ! $into instanceof GalleryPerson) {
            abort(404);
        }
        GalleryFace::query()->where('user_id', $uid)->where('gallery_person_id', $from->id)
            ->update(['gallery_person_id' => $into->id]);
        // Keep the target's name; if it had none, inherit the source's.
        if (($into->name === null || $into->name === '') && $from->name !== null && $from->name !== '') {
            $into->forceFill(['name' => $from->name])->save();
        }
        $from->delete();

        return response()->json(['ok' => true, 'person' => ['id' => $into->id, 'name' => $into->name]]);
    }

    /** Faces detected on a photo (for lightbox chips). */
    public function photoFaces(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $this->requireUser($request);
        $faces = GalleryFace::query()->where('gallery_photo_id', $photo->id)->where('hidden', false)
            ->with('person')
            ->get()->map(fn (GalleryFace $f): array => $this->faceRow($f))->all();

        return response()->json(['faces' => $faces]);
    }

    /** Move a face to another person (existing id, or a new named person). */
    public function faceAssign(Request $request, GalleryFace $face): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'person_id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        $personId = null;
        if ($request->filled('person_id')) {
            $target = GalleryPerson::query()->whereKey($request->integer('person_id'))->first();
            $personId = $target instanceof GalleryPerson ? (int) $target->id : null;
        } elseif ($request->filled('name')) {
            $person = new GalleryPerson;
            $person->forceFill(['user_id' => $uid, 'name' => $request->string('name')->value(), 'cover_face_id' => $face->id]);
            $person->save();
            $personId = (int) $person->id;
        }
        $face->forceFill(['gallery_person_id' => $personId])->save();

        return response()->json(['ok' => true]);
    }

    public function faceHide(Request $request, GalleryFace $face): JsonResponse
    {
        $this->requireUser($request);
        $face->forceFill(['hidden' => true])->save();
        // If it was a person's cover, promote another of that person's faces.
        if ($face->gallery_person_id !== null) {
            $person = GalleryPerson::query()->whereKey($face->gallery_person_id)->first();
            if ($person instanceof GalleryPerson && $person->cover_face_id === $face->id) {
                $next = GalleryFace::query()->where('gallery_person_id', $person->id)->where('hidden', false)->first();
                $person->forceFill(['cover_face_id' => $next?->id])->save();
            }
        }

        return response()->json(['ok' => true]);
    }

    /** Serve a face crop (sandboxed, immutable). */
    public function faceCrop(Request $request, GalleryFace $face): StreamedResponse
    {
        $this->requireUser($request);
        $src = (string) $face->crop_path;
        abort_unless($src !== '' && str_starts_with($src, 'gallery/faces/') && $this->fs()->exists($src), 404);

        return BlobStore::immutableResponse(
            $this->fs()->response($src, 'face.jpg', ['Content-Type' => 'image/jpeg'], 'inline'),
            (string) $face->id,
        );
    }

    /** @return array{id:int,person_id:?int,person_name:?string,box:array<int,float>,score:float,crop:bool} */
    private function faceRow(GalleryFace $f): array
    {
        return [
            'id' => $f->id,
            'person_id' => $f->gallery_person_id,
            'person_name' => $f->person?->name,
            'box' => is_array($f->box) ? array_map(static fn ($v): float => (float) $v, $f->box) : [],
            'score' => (float) $f->score,
            'crop' => $f->crop_path !== null && $f->crop_path !== '',
        ];
    }

    private function fs(): Filesystem
    {
        $d = config('files.disk');

        return Storage::disk(is_string($d) ? $d : 'files');
    }
}
