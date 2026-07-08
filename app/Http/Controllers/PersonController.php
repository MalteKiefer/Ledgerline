<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsFlexibly;
use App\Models\Face;
use App\Models\Person;
use App\Models\Photo;
use App\Services\Gallery\FaceClusterer;
use App\Support\BlobStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The gallery "People" section: browse clustered people, open a person's photos,
 * and manage them (name, merge, hide, reassign a face).
 */
class PersonController extends Controller
{
    use RespondsFlexibly;

    public function index(): View
    {
        return view('gallery.people');
    }

    /** People grid data: cover thumbnail, name, count. Hidden + tiny clusters excluded unless ?all. */
    public function data(Request $request): JsonResponse
    {
        $min = (int) config('gallery.face_min_per_person', 2);
        $all = $request->boolean('all');

        $people = Person::query()
            ->when(! $all, fn ($q) => $q->whereNull('hidden_at')->where('faces_count', '>=', $min))
            // Named people first (A→Z), then the still-unassigned clusters.
            ->orderByRaw("case when name is null or name = '' then 1 else 0 end asc")
            ->orderByRaw('lower(name) asc')
            ->orderByDesc('faces_count')
            ->get()
            ->map(fn (Person $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'count' => $p->faces_count,
                'hidden' => $p->hidden_at !== null,
                'cover' => $p->cover_face_id ? route('gallery.faces.thumb', ['face' => $p->cover_face_id]) : null,
            ]);

        return response()->json(['people' => $people]);
    }

    public function show(Person $person): View
    {
        return view('gallery.person', ['person' => $person]);
    }

    /** A person's photos, their faces (for reassignment) and other people (merge targets). */
    public function showData(Person $person): JsonResponse
    {
        $faces = Face::where('person_id', $person->id)->orderByDesc('det_score')->get();
        $photos = Photo::query()->whereIn('id', $faces->pluck('photo_id')->unique())->orderByDesc('taken_at')->get()
            ->map(fn (Photo $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'thumb' => route('gallery.image', ['photo' => $p, 'size' => 'thumb']),
                'full' => route('gallery.image', ['photo' => $p, 'size' => 'medium']),
            ]);

        return response()->json([
            'person' => ['id' => $person->id, 'name' => $person->name, 'count' => $person->faces_count, 'hidden' => $person->hidden_at !== null],
            'photos' => $photos->values(),
            'faces' => $faces->filter(fn (Face $f) => $f->thumb_path !== null)->map(fn (Face $f): array => [
                'id' => $f->id,
                'thumb' => route('gallery.faces.thumb', ['face' => $f]),
            ])->values(),
            // Only already-named people can be merged in (an unnamed cluster is
            // not something you'd knowingly merge), sorted by name for the picker.
            'others' => Person::query()->where('id', '!=', $person->id)->whereNull('hidden_at')
                ->whereNotNull('name')->where('name', '!=', '')
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn (Person $p): array => ['id' => $p->id, 'name' => $p->name]),
        ]);
    }

    /** Rename and/or hide a person. */
    public function update(Request $request, Person $person): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hidden' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $person->forceFill(['name' => $data['name'] ?: null])->save();
        }
        if ($request->has('hidden')) {
            $person->forceFill(['hidden_at' => $request->boolean('hidden') ? now() : null])->save();
        }

        return $this->flexible($request);
    }

    /** Merge another person's faces into this one (pinning them), then delete it. */
    public function merge(Request $request, Person $person, FaceClusterer $clusterer): JsonResponse|RedirectResponse
    {
        $sourceId = $request->validate(['source_id' => ['required', 'string']])['source_id'];
        abort_if($sourceId === $person->id, 422);
        $source = Person::findOrFail($sourceId);

        Face::where('person_id', $source->id)->update(['person_id' => $person->id, 'pinned' => true]);
        $clusterer->recompute($person->id);
        $source->delete();

        return $this->flexible($request);
    }

    /** Move a single face to another person (or a fresh one), pinning it. */
    public function reassignFace(Request $request, Face $face, FaceClusterer $clusterer): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'person_id' => ['nullable', 'string'],
            'new' => ['sometimes', 'boolean'],
        ]);

        $old = $face->person_id;
        $target = $request->boolean('new') || empty($data['person_id'])
            ? Person::create()->id
            : Person::findOrFail($data['person_id'])->id;

        $face->forceFill(['person_id' => $target, 'pinned' => true])->save();
        $clusterer->recompute($target);
        if ($old !== null && $old !== $target) {
            $clusterer->recompute($old);
        }

        return $this->flexible($request, ['person_id' => $target]);
    }

    /** Stream a face crop thumbnail. */
    public function thumb(Face $face): StreamedResponse
    {
        abort_if($face->thumb_path === null, 404);
        $disk = BlobStore::disk();
        abort_unless($disk->exists($face->thumb_path), 404);

        return $disk->response($face->thumb_path, 'face.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
