<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsFlexibly;
use App\Jobs\BuildExport;
use App\Jobs\ProcessPhoto;
use App\Models\Album;
use App\Models\AppSettings;
use App\Models\Export;
use App\Models\Face;
use App\Models\Photo;
use App\Models\UserSetting;
use App\Services\Files\ReverseGeocoder;
use App\Services\Gallery\GalleryFormats;
use App\Services\Gallery\PhotoExporter;
use App\Services\Gallery\PhotoStorage;
use App\Services\Gallery\TripGrouper;
use App\Support\ArchiveName;
use App\Support\BlobStore;
use App\Support\Bytes;
use App\Support\DiskTempFile;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

/**
 * The photo gallery: a timeline grouped by capture day, drag-and-drop upload
 * (one photo per request, with progress), thumbnails, a lightbox, and a trash.
 */
class GalleryController extends Controller
{
    use RespondsFlexibly;

    public function index(): View
    {
        // Zero-knowledge: the gallery renders entirely client-side from the sealed
        // index + decrypted blobs. The server ships only the (empty) shell.
        return view('gallery.index');
    }

    /**
     * Toggle a photo's favourite state.
     */
    public function favorite(Request $request, Photo $photo): RedirectResponse|JsonResponse
    {
        // Favourite is a single owner column, so only the owner may toggle it —
        // a write-share collaborator must not mutate the owner's favourites.
        abort_unless($photo->isOwnedBy($request->user()->id), 403);
        $photo->forceFill(['favorited_at' => $photo->isFavorite() ? null : now()])->save();

        if ($request->expectsJson()) {
            return response()->json(['favorite' => $photo->fresh()->isFavorite()]);
        }

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

        // Escape LIKE metacharacters (\, %, _) in the term so they match
        // literally instead of acting as wildcards (e.g. "%" no longer matches
        // everything, and "_" matches a literal underscore).
        $like = '%'.Like::escape($q).'%';

        // Text columns (and the JSON dump / timestamp cast to text), matched
        // case-insensitively across both PostgreSQL and SQLite.
        $columns = [
            'name', 'original_name', 'place', 'camera',
            $pg ? 'metadata::text' : 'metadata',
            $pg ? 'taken_at::text' : 'taken_at',
        ];

        $query->where(function ($w) use ($columns, $like, $q): void {
            foreach ($columns as $column) {
                $w->orWhereRaw('LOWER('.$column.") LIKE ? ESCAPE '\\'", [$like]);
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
            'mapZoom' => (int) (AppSettings::current()->gallery_map_zoom ?? 13),
        ]);
    }

    /**
     * Trips: geotagged photos grouped by location and date.
     */
    public function trips(TripGrouper $grouper): View
    {
        $company = AppSettings::current();

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
    /** Persist the per-user gallery zoom (photos per row). */
    public function setColumns(Request $request): JsonResponse
    {
        $data = $request->validate(['columns' => ['required', 'integer', 'min:2', 'max:12']]);
        UserSetting::for($request->user()->id)->update(['gallery_columns' => $data['columns']]);

        return response()->json(['ok' => true]);
    }

    /**
     * Photos for a thumbnail picker (avatar cropper + mail attach picker).
     * An optional `q` filters by album name, person (face) name, free text over
     * the photo's own metadata, or a bare photo id. Everything is owner-scoped.
     */
    public function pickerList(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        $query = Photo::query()
            ->where('status', 'ready')
            ->where('media_type', '!=', 'video');

        if ($q !== '') {
            $this->pickerSearch($query, $q);
        }

        $photos = $query
            ->orderByDesc('taken_at')
            ->limit($q === '' ? 120 : 300)
            ->get()
            ->map(fn (Photo $p): array => [
                'id' => $p->id,
                'name' => $p->name ?: ('photo-'.$p->id),
                'thumb' => route('gallery.image', ['photo' => $p, 'size' => 'thumb']),
                'full' => route('gallery.image', ['photo' => $p, 'size' => 'medium']),
            ]);

        return response()->json(['photos' => $photos]);
    }

    /**
     * Filter the picker query by album name, person name, free text over the
     * photo's own fields, or a bare id. All sub-queries are owner-scoped so a
     * user can never surface another user's photos, albums or people.
     *
     * @param  Builder<Photo>  $query
     */
    private function pickerSearch(Builder $query, string $q): void
    {
        $uid = (int) auth()->id();
        $like = '%'.Like::escape($q).'%';

        // Photo ids belonging to an album whose name matches (owner-scoped via
        // the SharesWithUsers global scope + explicit user_id on the query).
        $albumPhotoIds = Album::query()
            ->where('user_id', $uid)
            ->whereRaw("lower(name) like ? escape '\\'", [$like])
            ->get()
            ->flatMap(fn (Album $a) => $a->photos()->pluck('photos.id'))
            ->unique();

        // Photo ids that have a matching (owner-scoped) named person.
        $personPhotoIds = Face::query()
            ->whereHas('person', fn ($p) => $p
                ->where('people.user_id', $uid)
                ->whereRaw("lower(people.name) like ? escape '\\'", [$like]))
            ->pluck('photo_id')
            ->unique();

        $query->where(function ($w) use ($like, $q, $albumPhotoIds, $personPhotoIds): void {
            $w->whereRaw("lower(name) like ? escape '\\'", [$like])
                ->orWhereRaw("lower(original_name) like ? escape '\\'", [$like])
                ->orWhereRaw("lower(place) like ? escape '\\'", [$like]);

            if (ctype_digit($q)) {
                $w->orWhere('id', (int) $q);
            }
            if ($albumPhotoIds->isNotEmpty()) {
                $w->orWhereIn('id', $albumPhotoIds);
            }
            if ($personPhotoIds->isNotEmpty()) {
                $w->orWhereIn('id', $personPhotoIds);
            }
        });
    }

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
    public function store(Request $request, PhotoStorage $storage, GalleryFormats $formats): JsonResponse
    {
        $maxMb = (int) (AppSettings::current()->gallery_max_upload_mb ?? 200);

        // Validate presence and size first, so an unsupported-but-valid file can
        // be reported as skipped rather than a hard error.
        $request->validate([
            'photo' => ['required', 'file', 'max:'.($maxMb * 1024)],
        ]);

        $upload = $request->file('photo');
        $mime = $upload->getMimeType() ?: 'application/octet-stream';
        $extension = strtolower($upload->getClientOriginalExtension());

        // A HEIC/HEIF/AVIF file this runtime cannot decode (no libheif-enabled
        // Imagick): report it as skipped so the uploader can list it instead of
        // storing an original it could never render.
        if ($formats->isUnsupportedImage($extension, $mime)) {
            return response()->json([
                'skipped' => true,
                'reason' => 'unsupported',
                'name' => $upload->getClientOriginalName(),
            ], 200);
        }

        // Reject anything outside the supported image/video types (HEIC/HEIF/AVIF
        // are included only when the runtime can decode them).
        $request->validate([
            'photo' => ['mimes:'.$formats->allowedExtensionsCsv()],
        ]);

        $mediaType = str_starts_with($mime, 'video/') ? 'video' : 'image';

        // Skip duplicates before writing any bytes — but only on identical bytes
        // (checksum + size), and only against the UPLOADER'S OWN photos. Matching
        // on name+size (when a checksum can't be computed) could silently swallow
        // a genuinely different file; matching another user's shared photo could
        // discard a real upload. Both are avoided here.
        $name = $upload->getClientOriginalName();
        $size = (int) $upload->getSize();
        $checksum = hash_file('sha256', $upload->getRealPath()) ?: null;

        $duplicate = $checksum === null
            ? null
            : Photo::ownedBy($request->user()->id)->where('size', $size)->where('checksum', $checksum)->first();

        if ($duplicate !== null) {
            return response()->json(['duplicate' => true, 'id' => $duplicate->id, 'name' => $duplicate->name], 200);
        }

        $original = $storage->storeOriginal($upload);

        try {
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
        } catch (Throwable $e) {
            // Don't leak the just-stored original if the row couldn't be created.
            BlobStore::disk()->delete($original['disk_path']);
            throw $e;
        }

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
            // Constrain to a sane range so an out-of-range date can't be stored
            // and break the timeline grouping (e.g. year 0 or 9999).
            'date' => ['required', 'date', 'after:1900-01-01', 'before:2100-01-01'],
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
        $photo->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'name' => $photo->name,
                'date' => $photo->taken_at->isoFormat('LL'),
                'dateiso' => $photo->taken_at->format('Y-m-d'),
                'time' => $photo->taken_at->format('H:i'),
                'camera' => $photo->camera,
                'lat' => $photo->latitude,
                'lng' => $photo->longitude,
                'place' => $photo->place,
                'placeLines' => $photo->placeLines(),
            ]);
        }

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
    public function bulkLocation(Request $request, ReverseGeocoder $geocoder): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'max:1000'],
            'photo_ids.*' => ['integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $geo = $geocoder->lookupDetailed((float) $validated['latitude'], (float) $validated['longitude']);

        $count = Photo::ownedBy($request->user()->id)->whereNull('deleted_at')->whereIn('id', $validated['photo_ids'])->get()
            ->each(fn (Photo $photo) => $photo->forceFill([
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'place' => $geo['display'],
                'place_details' => $geo['address'] ?: null,
                'meta_locked' => true,
            ])->save())
            ->count();

        return $this->flexible($request, ['count' => $count], 'flash.photos_location_set', ['count' => $count]);
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

        $disk = BlobStore::disk();
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
     * Download the selected photos' originals as a single zip. Photos are added
     * one at a time to a temp file so memory stays bounded regardless of the
     * total size; the file is streamed and removed after sending.
     */
    public function bulkDownload(Request $request, PhotoExporter $exporter): BinaryFileResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'max:1000'],
            'photo_ids.*' => ['integer'],
            'variant' => ['nullable', 'in:original,edited'],
        ]);
        $edited = ($validated['variant'] ?? 'original') === 'edited';

        // Owner-only + live-only: never zip/serve bytes of photos merely shared
        // with the user, or trashed ones (ownedBy strips the SoftDeletingScope).
        $photos = Photo::ownedBy($request->user()->id)->whereNull('deleted_at')
            ->whereIn('id', $validated['photo_ids'])->get();
        abort_if($photos->isEmpty(), 404);

        $disk = BlobStore::disk();
        $tmp = tempnam(sys_get_temp_dir(), 'gallery-zip').'.zip';

        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $used = [];
        $temps = [];
        foreach ($photos as $photo) {
            if (! $disk->exists($photo->disk_path)) {
                continue;
            }
            if ($edited) {
                // Each photo may yield more than one file (a motion photo exports
                // its still and its clip). Add from temp files so large edited
                // videos never sit in memory.
                foreach ($exporter->editedFiles($photo) as $file) {
                    $temps[] = $file['path'];
                    $zip->addFile($file['path'], $this->uniqueZipName($file['name'], $used));
                }
            } else {
                // Pull to a temp file and add by path so a large original never
                // sits fully in PHP memory (unbounded addFromString OOMs).
                $local = DiskTempFile::pull($disk, $photo->disk_path, 'gallery-src');
                $temps[] = $local;
                $zip->addFile($local, $this->uniqueZipName($photo->name ?: ('photo-'.$photo->id), $used));
            }
        }
        $zip->close();

        foreach ($temps as $path) {
            @unlink($path);
        }

        return response()->download($tmp, 'photos.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    /**
     * Queue an asynchronous export of the selected photos. A worker builds the
     * zip(s) in the background; the user collects them from the Downloads page.
     */
    public function queueExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'max:5000'],
            'photo_ids.*' => ['integer'],
            'variant' => ['nullable', 'in:original,edited'],
        ]);

        $variant = $validated['variant'] ?? 'original';

        // Keep only photos the caller actually OWNS. BuildExport runs on the
        // queue with no Auth context, so Photo's owner global scope is inert
        // there — an unscoped payload would let any user export another user's
        // photo bytes by id (mirrors FileController::queueExport's scoping).
        $uid = $request->user()->id;
        $photoIds = Photo::ownedBy($uid)->whereNull('deleted_at')
            ->whereIn('id', array_values($validated['photo_ids']))->pluck('id')->all();
        abort_if($photoIds === [], 422, 'Nothing selected.');
        $count = count($photoIds);

        // Cap how many exports one user can have building at once so a single
        // user can't flood the queue with huge jobs.
        abort_if(
            Export::inFlightCount($request->user()->id) >= Export::MAX_IN_FLIGHT,
            429,
            __('downloads.error.too_many', ['max' => Export::MAX_IN_FLIGHT])
        );

        $export = Export::create([
            'user_id' => $request->user()->id,
            'source' => 'gallery',
            'variant' => $variant,
            'title' => trans_choice('downloads.title.gallery', $count, ['count' => $count]),
            'status' => 'queued',
            'item_count' => $count,
            'payload' => ['photo_ids' => $photoIds],
        ]);

        BuildExport::dispatch($export->id);

        return response()->json(['queued' => true, 'export_id' => $export->id], 202);
    }

    /**
     * Download one photo with its edits baked in (rotation/flip) and the current
     * metadata written into EXIF. The unedited original is served by the normal
     * image route with size=original.
     */
    public function downloadEdited(Photo $photo, PhotoExporter $exporter): BinaryFileResponse
    {
        $files = $exporter->editedFiles($photo);

        // A motion photo exports as still + clip, bundled into one zip so both
        // come down together; a plain photo/video downloads as a single file.
        if (count($files) > 1) {
            $tmp = tempnam(sys_get_temp_dir(), 'edited-zip').'.zip';
            $zip = new ZipArchive;
            $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $used = [];
            foreach ($files as $file) {
                $zip->addFile($file['path'], $this->uniqueZipName($file['name'], $used));
            }
            $zip->close();
            foreach ($files as $file) {
                @unlink($file['path']);
            }

            $zipName = pathinfo($photo->name ?: 'photo', PATHINFO_FILENAME).'.zip';

            return response()->download($tmp, $zipName, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
        }

        return response()->download($files[0]['path'], $files[0]['name'], [
            'Content-Type' => $photo->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    /**
     * A zip entry name unique within the archive, appending " (n)" before the
     * extension on collisions.
     *
     * @param  array<string, bool>  $used
     */
    private function uniqueZipName(string $name, array &$used): string
    {
        $name = trim(str_replace(['/', '\\'], '-', $name)) ?: 'file';

        return ArchiveName::unique($name, $used, parenthesize: true);
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

        return $this->streamMedia($photo->disk_path, $this->videoContentType($photo));
    }

    /**
     * A browser-playable content type derived from the stored file extension.
     * Some uploads (e.g. an .mp4 detected as video/quicktime) carry a mime that
     * browsers refuse to play, so trust the container extension for playback.
     */
    private function videoContentType(Photo $photo): string
    {
        return match (strtolower(pathinfo((string) $photo->disk_path, PATHINFO_EXTENSION))) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mov', 'qt' => 'video/quicktime',
            default => $photo->mime_type ?: 'video/mp4',
        };
    }

    /**
     * Stream a motion photo's embedded clip for inline playback, using the same
     * signed-URL / range-capable local file strategy as video().
     */
    public function motion(Photo $photo): Response
    {
        abort_unless($photo->hasMotion(), 404);

        return $this->streamMedia($photo->motion_path, 'video/mp4');
    }

    /**
     * Stream a media file inline: redirect to a short-lived signed URL when the
     * disk supports it (remote disk serves byte ranges), else a range-capable
     * local file response.
     */
    private function streamMedia(string $path, string $contentType): Response
    {
        $disk = BlobStore::disk();
        abort_unless($disk->exists($path), 404);

        try {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(30), [
                'ResponseContentType' => $contentType,
                'ResponseContentDisposition' => 'inline',
            ]));
        } catch (Throwable) {
            // Local disk: BinaryFileResponse handles Range requests natively.
            return response()->file($disk->path($path), [
                'Content-Type' => $contentType,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }
    }

    /**
     * Soft-delete a selection of photos (move to trash).
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'max:1000'],
            'photo_ids.*' => ['integer'],
        ]);

        $count = Photo::ownedBy($request->user()->id)->whereNull('deleted_at')
            ->whereIn('id', $validated['photo_ids'])->get()->each->delete()->count();

        return $this->flexible($request, ['ids' => $validated['photo_ids'], 'count' => $count], 'flash.photos_trashed', ['count' => $count]);
    }

    /**
     * Move EVERY one of the caller's photos to the trash. Soft-delete (chunked so
     * a huge library does not load at once, and per-model so Photo delete events
     * still fire); recoverable from the trash until purged.
     */
    public function destroyAll(Request $request): RedirectResponse|JsonResponse
    {
        $uid = $request->user()->id;
        $count = 0;
        Photo::ownedBy($uid)->whereNull('deleted_at')->chunkById(500, function ($photos) use (&$count): void {
            foreach ($photos as $photo) {
                $photo->delete();
                $count++;
            }
        });

        return $this->flexible($request, ['count' => $count], 'flash.photos_trashed', ['count' => $count]);
    }

    public function trash(): View
    {
        return view('gallery.trash', [
            'photos' => Photo::onlyTrashed()->orderByDesc('deleted_at')->paginate(60),
        ]);
    }

    /** The Duplicates review page. */
    public function duplicates(): View
    {
        return view('gallery.duplicates');
    }

    /** Duplicate groups as JSON: each group's members with name, size, similarity. */
    public function duplicatesData(Request $request): JsonResponse
    {
        $groups = Photo::query()
            // Own photos only — the ownerOrShared scope would otherwise leak a
            // shared photo's duplicate group/score.
            ->where('uploaded_by', $request->user()->id)
            ->whereNotNull('duplicate_group_id')
            ->orderByDesc('dup_score')
            ->orderBy('duplicate_group_id')
            ->get()
            ->groupBy('duplicate_group_id')
            ->map(function ($members, $groupId): array {
                // Best copy first (largest bytes, then highest resolution, then
                // oldest) so a keep-first default keeps the best, not a random one.
                $sorted = $members->sortByDesc(fn (Photo $p) => [
                    (int) $p->size,
                    (int) ($p->width ?? 0) * (int) ($p->height ?? 0),
                    -($p->taken_at?->getTimestamp() ?? 0),
                ])->values();

                return [
                    'group' => $groupId,
                    'score' => (float) ($members->max('dup_score') ?? 0),
                    'photos' => $sorted->map(fn (Photo $p): array => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'size' => (int) $p->size,
                        'size_human' => Bytes::format((int) $p->size),
                        'dimensions' => $p->width && $p->height ? $p->width.'×'.$p->height : null,
                        'thumb' => route('gallery.image', ['photo' => $p, 'size' => 'thumb']),
                        'media_type' => $p->media_type,
                        'taken_at' => $p->taken_at?->toDateString(),
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['groups' => $groups]);
    }

    /** Keep one photo of a group and move the rest to the trash. */
    public function resolveDuplicate(Request $request, string $group): JsonResponse|RedirectResponse
    {
        $keepId = $request->validate(['keep_id' => ['required', 'integer']])['keep_id'];

        $members = Photo::ownedBy($request->user()->id)->whereNull('deleted_at')->where('duplicate_group_id', $group)->get();
        abort_if($members->isEmpty(), 404);
        abort_unless($members->contains('id', $keepId), 422);

        foreach ($members as $photo) {
            if ($photo->id === (int) $keepId) {
                $photo->forceFill(['duplicate_group_id' => null, 'dup_score' => null])->save();
            } else {
                $photo->delete(); // soft delete → gallery trash
            }
        }

        return $this->flexible($request, ['kept' => (int) $keepId]);
    }

    /** Mark a group as "not a duplicate" so it is excluded from future scans. */
    public function dismissDuplicate(Request $request, string $group): JsonResponse|RedirectResponse
    {
        $affected = Photo::ownedBy($request->user()->id)->whereNull('deleted_at')
            ->where('duplicate_group_id', $group)
            ->update(['duplicate_group_id' => null, 'dup_score' => null, 'dup_dismissed_at' => now()]);

        abort_if($affected === 0, 404);

        return $this->flexible($request);
    }

    /**
     * Restore trashed photos: a selection (photo_ids) or all (all=1).
     */
    public function restore(Request $request): RedirectResponse|JsonResponse
    {
        $count = $this->trashedQuery($request)->restore();

        return $this->flexible($request, ['count' => $count], 'flash.photos_restored', ['count' => $count]);
    }

    /**
     * Permanently delete trashed photos (selection or all), with their bytes.
     */
    public function forceDestroy(Request $request): RedirectResponse|JsonResponse
    {
        $disk = BlobStore::disk();
        $count = 0;

        $this->trashedQuery($request)->get()->each(function (Photo $photo) use ($disk, &$count): void {
            // Delete the row FIRST (in a transaction), then the bytes. If the row
            // delete fails it rolls back and the bytes stay intact; a post-commit
            // disk failure only leaves a harmless orphan (never a live/trashed row
            // pointing at already-deleted bytes = a broken, unrecoverable photo).
            $paths = $photo->allPaths();
            try {
                DB::transaction(fn () => $photo->forceDelete());
            } catch (Throwable) {
                return; // skip this one, keep the bytes; don't strand the rest
            }
            $disk->delete($paths);
            $count++;
        });

        return $this->flexible($request, ['count' => $count], 'flash.photos_deleted', ['count' => $count]);
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
            'photo_ids' => ['nullable', 'array', 'max:1000'],
            'photo_ids.*' => ['integer'],
        ]);

        // Owner-only: bulk restore/purge are builder ops that bypass the
        // SharesWithUsers guard, so never touch photos merely shared with the user.
        $query = Photo::ownedBy($request->user()->id)->onlyTrashed();

        return $request->boolean('all')
            ? $query
            : $query->whereIn('id', $request->input('photo_ids', []));
    }
}
