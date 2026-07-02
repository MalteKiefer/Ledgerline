<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessPhoto;
use App\Models\CompanyProfile;
use App\Models\Photo;
use App\Services\Files\ReverseGeocoder;
use App\Services\Gallery\PhotoStorage;
use App\Services\Gallery\TripGrouper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The photo gallery: a timeline grouped by capture day, drag-and-drop upload
 * (one photo per request, with progress), thumbnails, a lightbox, and a trash.
 */
class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        $photos = $this->page($request);

        return view('gallery.index', [
            'photos' => $photos,
            'grouped' => $this->groupByDay($photos),
            'favoritesOnly' => $request->boolean('favorites'),
            'searchQuery' => trim((string) $request->input('q')),
            'mapZoom' => (int) (CompanyProfile::current()->gallery_map_zoom ?? 13),
            // Cameras already seen, to offer as suggestions when editing.
            'cameras' => Photo::query()->whereNotNull('camera')->distinct()->orderBy('camera')->pluck('camera')->all(),
        ]);
    }

    /**
     * Toggle a photo's favourite state.
     */
    public function favorite(Photo $photo): RedirectResponse
    {
        $photo->forceFill(['favorited_at' => $photo->isFavorite() ? null : now()])->save();

        return back();
    }

    /**
     * Return the next page of the timeline as a rendered fragment for infinite
     * scroll, plus whether more pages remain.
     */
    public function feed(Request $request): View
    {
        $photos = $this->page($request);

        return view('gallery._timeline', [
            'grouped' => $this->groupByDay($photos),
            'hasMore' => $photos->hasMorePages(),
            'nextPage' => $photos->currentPage() + 1,
        ]);
    }

    /**
     * The year/month buckets across the whole library, for the timeline
     * scrubber. Ordered newest first with a per-month count.
     */
    public function months(Request $request): JsonResponse
    {
        $months = Photo::query()
            ->when($request->boolean('favorites'), fn ($q) => $q->whereNotNull('favorited_at'))
            ->orderByDesc('taken_at')
            ->pluck('taken_at')
            ->groupBy(fn (Carbon $d): string => $d->format('Y-m'))
            ->map(fn (Collection $g, string $ym): array => [
                'ym' => $ym,
                'year' => substr($ym, 0, 4),
                // At most three letters, e.g. Juli → Jul, September → Sep.
                'month' => mb_substr(Carbon::parse($ym.'-01')->isoFormat('MMMM'), 0, 3),
                'count' => $g->count(),
            ])
            ->values();

        return response()->json(['months' => $months]);
    }

    private function page(Request $request): LengthAwarePaginator
    {
        return Photo::query()
            ->when($request->boolean('favorites'), fn ($q) => $q->whereNotNull('favorited_at'))
            ->when($request->filled('q'), fn ($q) => $this->search($q, trim((string) $request->input('q'))))
            ->orderByDesc('taken_at')->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();
    }

    /**
     * Search photos across all metadata: filename, place (address / city /
     * country), camera, capture date and time, the full metadata dump, and
     * "lat,lng" coordinates.
     *
     * @param  Builder<Photo>  $query
     */
    private function search(Builder $query, string $q): void
    {
        $pg = $query->getConnection()->getDriverName() === 'pgsql';
        $like = '%'.mb_strtolower($q).'%';

        // Text columns (and the JSON dump / timestamp cast to text), matched
        // case-insensitively across both PostgreSQL and SQLite.
        $columns = [
            'name', 'original_name', 'place', 'camera',
            $pg ? 'metadata::text' : 'metadata',
            $pg ? 'taken_at::text' : 'taken_at',
        ];

        $query->where(function ($w) use ($columns, $like, $q): void {
            foreach ($columns as $column) {
                $w->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
            }

            // "lat,lng" → photos near those coordinates.
            if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $q, $m)) {
                $w->orWhere(fn ($c) => $c
                    ->whereBetween('latitude', [(float) $m[1] - 0.05, (float) $m[1] + 0.05])
                    ->whereBetween('longitude', [(float) $m[2] - 0.05, (float) $m[2] + 0.05]));
            }
        });
    }

    /**
     * @return Collection<string, Collection<int, Photo>>
     */
    private function groupByDay(LengthAwarePaginator $photos): Collection
    {
        return $photos->getCollection()->groupBy(fn (Photo $p): string => $p->taken_at->format('Y-m-d'));
    }

    /**
     * The map view (photos with a known location).
     */
    public function map(): View
    {
        return view('gallery.map', [
            'mapZoom' => (int) (CompanyProfile::current()->gallery_map_zoom ?? 13),
        ]);
    }

    /**
     * Trips: geotagged photos grouped by location and date.
     */
    public function trips(TripGrouper $grouper): View
    {
        $company = CompanyProfile::current();

        $photos = Photo::query()
            ->where('status', 'ready')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('taken_at')
            ->get();

        $trips = $grouper->group(
            $photos,
            (int) ($company->gallery_trip_gap_days ?? 2),
            (float) ($company->gallery_trip_radius_km ?? 100),
        );

        return view('gallery.trips', ['trips' => $trips]);
    }

    /**
     * Photo locations as JSON for the map (id, lat, lng, thumb, medium, date).
     */
    public function points(): JsonResponse
    {
        $points = Photo::query()
            ->where('status', 'ready')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('taken_at')
            ->get()
            ->map(fn (Photo $p): array => [
                'id' => $p->id,
                'lat' => $p->latitude,
                'lng' => $p->longitude,
                'thumb' => route('gallery.image', ['photo' => $p, 'size' => 'thumb']),
                'medium' => route('gallery.image', ['photo' => $p, 'size' => 'medium']),
                'original' => route('gallery.image', ['photo' => $p, 'size' => 'original']),
                'date' => $p->taken_at->toDateString(),
            ]);

        return response()->json(['points' => $points]);
    }

    /**
     * Store one uploaded photo (called per file by the uploader). Returns JSON
     * so the client can update its progress list.
     */
    public function store(Request $request, PhotoStorage $storage): JsonResponse
    {
        $maxMb = (int) (CompanyProfile::current()->gallery_max_upload_mb ?? 200);

        // Validate presence and size first, so an unsupported-but-valid file
        // (e.g. HEIC) can be reported as skipped rather than a hard error.
        $request->validate([
            'photo' => ['required', 'file', 'max:'.($maxMb * 1024)],
        ]);

        $upload = $request->file('photo');
        $mime = $upload->getMimeType() ?: 'application/octet-stream';
        $extension = strtolower($upload->getClientOriginalExtension());

        // HEIC/HEIF cannot be decoded yet; skip it explicitly so the uploader
        // can list it instead of failing.
        if (in_array($extension, ['heic', 'heif'], true) || str_contains($mime, 'heic') || str_contains($mime, 'heif')) {
            return response()->json([
                'skipped' => true,
                'reason' => 'heic',
                'name' => $upload->getClientOriginalName(),
            ], 200);
        }

        // Reject anything outside the supported image/video types.
        $request->validate([
            'photo' => ['mimes:jpg,jpeg,png,webp,gif,mp4,mov'],
        ]);

        $mediaType = str_starts_with($mime, 'video/') ? 'video' : 'image';

        // Skip duplicates before writing any bytes. Match on identical bytes
        // (checksum + size) rather than the name, so re-uploading a file that
        // was renamed by the filename template is still caught. Fall back to
        // name + size only when a checksum could not be computed.
        $name = $upload->getClientOriginalName();
        $size = (int) $upload->getSize();
        $checksum = hash_file('sha256', $upload->getRealPath()) ?: null;

        $duplicate = Photo::query()
            ->where('size', $size)
            ->when(
                $checksum !== null,
                fn ($q) => $q->where('checksum', $checksum),
                fn ($q) => $q->where('original_name', $name),
            )
            ->first();

        if ($duplicate !== null) {
            return response()->json(['duplicate' => true, 'id' => $duplicate->id, 'name' => $duplicate->name], 200);
        }

        $original = $storage->storeOriginal($upload);

        // Save immediately with placeholder renditions; the queue fills the rest.
        $photo = new Photo([
            'uuid' => $original['uuid'],
            'name' => $name,
            'original_name' => $name,
            'status' => 'processing',
            'media_type' => $mediaType,
            'disk_path' => $original['disk_path'],
            'thumb_path' => $original['disk_path'],
            'medium_path' => $original['disk_path'],
            'mime_type' => $mime,
            'size' => $original['size'],
            'checksum' => $original['checksum'],
            'taken_at' => now(),
        ]);
        $photo->uploaded_by = $request->user()->id;
        $photo->save();

        ProcessPhoto::dispatch($photo->id);

        return response()->json(['id' => $photo->id, 'name' => $photo->name], 201);
    }

    /**
     * Edit a photo's date, time and location (stored in the DB; the original
     * file is not touched). Marks the metadata as user-locked so a re-scan will
     * not overwrite it from EXIF.
     */
    public function editMeta(Request $request, Photo $photo, ReverseGeocoder $geocoder): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'camera' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = $validated['latitude'] ?? null;
        $lng = $validated['longitude'] ?? null;

        $attributes = [
            'name' => $validated['name'],
            'taken_at' => Carbon::parse($validated['date'].' '.$validated['time']),
            'camera' => ($validated['camera'] ?? null) ?: null,
            'latitude' => $lat,
            'longitude' => $lng,
            'meta_locked' => true,
        ];

        // Re-geocode when the coordinates were set or changed so the place name
        // reflects the new spot; clear the place when coordinates are removed.
        if ($lat !== null && $lng !== null) {
            $changed = (float) $lat !== (float) $photo->latitude
                || (float) $lng !== (float) $photo->longitude
                || $photo->place === null;
            if ($changed) {
                $geo = $geocoder->lookupDetailed((float) $lat, (float) $lng);
                $attributes['place'] = $geo['display'];
                $attributes['place_details'] = $geo['address'] ?: null;
            }
        } else {
            $attributes['place'] = null;
            $attributes['place_details'] = null;
        }

        $photo->forceFill($attributes)->save();

        return back()->with('status', __('flash.photo_updated'));
    }

    /**
     * Reverse-geocode coordinates to a place for a live preview while editing,
     * returning both the display name and the structured lines the viewer shows.
     */
    public function geocodeReverse(Request $request, ReverseGeocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $geo = $geocoder->lookupDetailed((float) $validated['lat'], (float) $validated['lon']);
        $preview = (new Photo)->forceFill(['place' => $geo['display'], 'place_details' => $geo['address'] ?: null]);

        return response()->json(['place' => $geo['display'], 'lines' => $preview->placeLines()]);
    }

    /**
     * Forward-geocode an address / place query to candidate coordinates for the
     * location picker's search box.
     */
    public function geocodeSearch(Request $request, ReverseGeocoder $geocoder): JsonResponse
    {
        $query = (string) $request->validate(['q' => ['required', 'string', 'max:200']])['q'];

        return response()->json(['results' => $geocoder->search($query)]);
    }

    /**
     * Apply a non-destructive transform (rotate/flip) and re-generate the
     * renditions from the untouched original.
     */
    public function transform(Request $request, Photo $photo): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', 'in:rotate_left,rotate_right,flip'],
        ])['action'];

        match ($action) {
            'rotate_left' => $photo->rotation = ((int) $photo->rotation + 270) % 360,
            'rotate_right' => $photo->rotation = ((int) $photo->rotation + 90) % 360,
            'flip' => $photo->flipped = ! $photo->flipped,
        };
        $photo->save();

        ProcessPhoto::dispatch($photo->id);

        return back()->with('status', __('flash.photo_updated'));
    }

    /**
     * Set the same location on a selection of photos at once. The coordinates
     * are geocoded a single time and applied to every selected photo, marking
     * their metadata as user-locked.
     */
    public function bulkLocation(Request $request, ReverseGeocoder $geocoder): RedirectResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array'],
            'photo_ids.*' => ['integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $geo = $geocoder->lookupDetailed((float) $validated['latitude'], (float) $validated['longitude']);

        $count = Photo::query()->whereIn('id', $validated['photo_ids'])->get()
            ->each(fn (Photo $photo) => $photo->forceFill([
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'place' => $geo['display'],
                'place_details' => $geo['address'] ?: null,
                'meta_locked' => true,
            ])->save())
            ->count();

        return back()->with('status', __('flash.photos_location_set', ['count' => $count]));
    }

    /**
     * Stream a photo rendition (thumb, medium or original) inline.
     */
    public function image(Request $request, Photo $photo, string $size): StreamedResponse
    {
        $path = match ($size) {
            'thumb' => $photo->thumb_path,
            'medium' => $photo->medium_path,
            default => $photo->disk_path,
        };

        $disk = Storage::disk(config('files.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $photo->name, [
            'Content-Type' => $size === 'original' ? $photo->mime_type : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ], $size === 'original' ? 'attachment' : 'inline');
    }

    /**
     * Stream a video for inline HTML5 playback with HTTP Range support. On a
     * remote disk this redirects to a short-lived signed URL so the storage
     * backend serves the byte ranges; on a local disk a range-capable file
     * response is returned.
     */
    public function video(Photo $photo): Response
    {
        abort_unless($photo->isVideo(), 404);

        $disk = Storage::disk(config('files.disk'));
        abort_unless($disk->exists($photo->disk_path), 404);

        try {
            return redirect()->away($disk->temporaryUrl($photo->disk_path, now()->addMinutes(30), [
                'ResponseContentType' => $photo->mime_type,
                'ResponseContentDisposition' => 'inline',
            ]));
        } catch (Throwable) {
            // Local disk: BinaryFileResponse handles Range requests natively.
            return response()->file($disk->path($photo->disk_path), [
                'Content-Type' => $photo->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }
    }

    /**
     * Stream a motion photo's embedded clip for inline playback, using the same
     * signed-URL / range-capable local file strategy as video().
     */
    public function motion(Photo $photo): Response
    {
        abort_unless($photo->hasMotion(), 404);

        $disk = Storage::disk(config('files.disk'));
        abort_unless($disk->exists($photo->motion_path), 404);

        try {
            return redirect()->away($disk->temporaryUrl($photo->motion_path, now()->addMinutes(30), [
                'ResponseContentType' => 'video/mp4',
                'ResponseContentDisposition' => 'inline',
            ]));
        } catch (Throwable) {
            return response()->file($disk->path($photo->motion_path), [
                'Content-Type' => 'video/mp4',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }
    }

    /**
     * Soft-delete a selection of photos (move to trash).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array'],
            'photo_ids.*' => ['integer'],
        ]);

        $count = Photo::query()->whereIn('id', $validated['photo_ids'])->get()->each->delete()->count();

        return back()->with('status', __('flash.photos_trashed', ['count' => $count]));
    }

    public function trash(): View
    {
        return view('gallery.trash', [
            'photos' => Photo::onlyTrashed()->orderByDesc('deleted_at')->paginate(60),
        ]);
    }

    /**
     * Restore trashed photos: a selection (photo_ids) or all (all=1).
     */
    public function restore(Request $request): RedirectResponse
    {
        $count = $this->trashedQuery($request)->restore();

        return back()->with('status', __('flash.photos_restored', ['count' => $count]));
    }

    /**
     * Permanently delete trashed photos (selection or all), with their bytes.
     */
    public function forceDestroy(Request $request): RedirectResponse
    {
        $disk = Storage::disk(config('files.disk'));
        $count = 0;

        $this->trashedQuery($request)->get()->each(function (Photo $photo) use ($disk, &$count): void {
            $disk->delete($photo->allPaths());
            $photo->forceDelete();
            $count++;
        });

        return back()->with('status', __('flash.photos_deleted', ['count' => $count]));
    }

    /**
     * Trashed photos targeted by a bulk action: either the given ids, or all.
     *
     * @return Builder<Photo>
     */
    private function trashedQuery(Request $request): Builder
    {
        $request->validate([
            'all' => ['nullable', 'boolean'],
            'photo_ids' => ['nullable', 'array'],
            'photo_ids.*' => ['integer'],
        ]);

        $query = Photo::onlyTrashed();

        return $request->boolean('all')
            ? $query
            : $query->whereIn('id', $request->input('photo_ids', []));
    }
}
