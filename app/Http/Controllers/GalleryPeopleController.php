<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contact;
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
            ->map(fn (GalleryPerson $p): array => $this->personRow($p))->all();

        return response()->json(['people' => $people]);
    }

    /** A person's photos, sortable by capture date (default newest first). */
    public function person(Request $request, GalleryPerson $person): JsonResponse
    {
        $this->requireUser($request);
        $dir = $request->string('sort')->lower()->value() === 'asc' ? 'asc' : 'desc';
        $photos = GalleryPhoto::query()
            ->whereHas('faces', fn ($q) => $q->where('gallery_person_id', $person->id)->where('hidden', false))
            ->orderByRaw('COALESCE(taken_at, created_at) '.$dir)->orderBy('id', $dir)
            ->get()->map(fn (GalleryPhoto $p): array => $this->gallery->row($p))->all();

        return response()->json(['person' => $this->personRow($person->loadCount(['faces' => fn (Builder $q) => $q->where('hidden', false)])), 'photos' => $photos]);
    }

    /**
     * Update a person: name (free text), contact link (contact_id — sets the name
     * from the contact when no explicit name is given), and cover face.
     */
    public function personUpdate(Request $request, GalleryPerson $person): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'contact_id' => ['sometimes', 'nullable', 'string'],
            'cover_face_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        $patch = [];
        if ($request->has('contact_id')) {
            $contact = $request->filled('contact_id') ? $this->resolveContact($uid, $request->string('contact_id')->value()) : null;
            $patch['contact_id'] = $contact?->id;
            if ($contact instanceof Contact && ! $request->filled('name')) {
                $patch['name'] = $this->contactName($contact);
            }
        }
        if ($request->has('name')) {
            $patch['name'] = $request->filled('name') ? $request->string('name')->value() : ($patch['name'] ?? null);
        }
        if ($request->has('cover_face_id')) {
            $cover = $request->filled('cover_face_id')
                ? GalleryFace::query()->whereKey($request->integer('cover_face_id'))->where('gallery_person_id', $person->id)->first()
                : null;
            $patch['cover_face_id'] = $cover?->id;
        }
        if ($patch !== []) {
            $person->forceFill($patch)->save();
        }

        return response()->json(['ok' => true, 'person' => $this->personRow($person->refresh())]);
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

    /** Move a face to another person (existing id, a contact, or a new named person). */
    public function faceAssign(Request $request, GalleryFace $face): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'person_id' => ['sometimes', 'nullable', 'integer'],
            'contact_id' => ['sometimes', 'nullable', 'string'],
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);
        $personId = null;
        if ($request->filled('person_id')) {
            $target = GalleryPerson::query()->whereKey($request->integer('person_id'))->first();
            $personId = $target instanceof GalleryPerson ? (int) $target->id : null;
        } elseif ($request->filled('contact_id')) {
            $contact = $this->resolveContact($uid, $request->string('contact_id')->value());
            if ($contact instanceof Contact) {
                // Reuse the person already linked to this contact, else create one.
                $existing = GalleryPerson::query()->where('contact_id', $contact->id)->first();
                if ($existing instanceof GalleryPerson) {
                    $personId = (int) $existing->id;
                } else {
                    $person = new GalleryPerson;
                    $person->forceFill(['user_id' => $uid, 'name' => $this->contactName($contact), 'contact_id' => $contact->id, 'cover_face_id' => $face->id]);
                    $person->save();
                    $personId = (int) $person->id;
                }
            }
        } elseif ($request->filled('name')) {
            $person = new GalleryPerson;
            $person->forceFill(['user_id' => $uid, 'name' => $request->string('name')->value(), 'cover_face_id' => $face->id]);
            $person->save();
            $personId = (int) $person->id;
        }
        $face->forceFill(['gallery_person_id' => $personId])->save();

        return response()->json(['ok' => true]);
    }

    /** Photos of the person(s) linked to a contact (shown on the contact page). */
    public function contactPhotos(Request $request, Contact $contact): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        // Owner-scope the contact (Contact is not globally owner-scoped).
        abort_unless($this->resolveContact($uid, (string) $contact->id) instanceof Contact, 404);
        $personIds = GalleryPerson::query()->where('contact_id', $contact->id)->pluck('id')->all();
        if ($personIds === []) {
            return response()->json(['photos' => []]);
        }
        $photos = GalleryPhoto::query()
            ->whereHas('faces', fn ($q) => $q->whereIn('gallery_person_id', $personIds)->where('hidden', false))
            ->orderByRaw('COALESCE(taken_at, created_at) DESC')->orderByDesc('id')
            ->get()->map(fn (GalleryPhoto $p): array => $this->gallery->row($p))->all();

        return response()->json(['photos' => $photos]);
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

    /** @return array{id:int,name:?string,contact_id:?string,count:int,cover_face_id:?int} */
    private function personRow(GalleryPerson $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'contact_id' => $p->contact_id,
            'count' => (int) ($p->faces_count ?? 0),
            'cover_face_id' => $p->cover_face_id,
        ];
    }

    private function resolveContact(int $uid, string $id): ?Contact
    {
        return Contact::query()
            ->whereKey($id)
            ->whereHas('addressBook', fn (Builder $q) => $q->where('user_id', $uid))
            ->first();
    }

    private function contactName(Contact $c): string
    {
        $fn = trim((string) $c->fn);
        if ($fn !== '') {
            return mb_substr($fn, 0, 191);
        }

        return mb_substr(trim(((string) $c->first_name).' '.((string) $c->last_name)), 0, 191) ?: 'Kontakt';
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
