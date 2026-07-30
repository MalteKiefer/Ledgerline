<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExploreCoupling;
use App\Models\ExploreSetting;
use App\Models\ExploreTrack;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plaintext-relational Explore (pivot). GPS tracks + photo↔track couplings +
 * matching tolerances as owner-scoped rows (OwnsUserData). Per-row CRUD in DB
 * transactions; no whole-blob re-serialize, so the opaque last-writer-wins loss
 * class cannot occur.
 *
 * A track's ordered point list is location PII → the `points` column is
 * `encrypted`-cast (kept out of DB dumps); aggregate stats stay plaintext for
 * listing. Optional raw track files (gpx/kml/…) live plaintext on the file disk.
 * All geo parsing/compute stays client-side — the controller only persists the
 * already-parsed points + stats the client sends.
 */
class ExploreController extends Controller
{
    /** Allowed raw track-file extensions (parsing is client-side). */
    private const TRACK_EXTENSIONS = ['gpx', 'kml', 'kmz', 'tcx', 'fit'];

    /** Max raw track-file size in KB (~25 MiB). */
    private const MAX_TRACK_FILE_KB = 25600;

    /** The Explore page: render the shell + inline the current data. */
    public function page(Request $request): View
    {
        $uid = (int) $this->requireUser($request)->id;

        return view('explore', [
            'tracks' => ExploreTrack::query()->orderByDesc('created_at')->get(),
            'couplings' => ExploreCoupling::query()->get(),
            'settings' => ExploreSetting::query()->firstOrNew(['user_id' => $uid]),
        ]);
    }

    /** Combined snapshot (tracks + couplings + settings) for API / client refresh. */
    public function index(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;

        return response()->json([
            'tracks' => ExploreTrack::query()->orderByDesc('created_at')->get(),
            'couplings' => ExploreCoupling::query()->get(),
            'settings' => ExploreSetting::query()->firstOrNew(['user_id' => $uid]),
        ]);
    }

    // --- Tracks ----------------------------------------------------------

    public function storeTrack(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:300'],
            'source_format' => ['required', 'string', Rule::in(['recorded', 'imported', 'planned'])],
            'points' => ['required', 'array'],
            'stats' => ['nullable', 'array'],
            'note' => ['nullable', 'string', 'max:5000'],
            'imported_at' => ['nullable', 'date'],
        ]);

        $payload = [
            'name' => $request->string('name')->value(),
            'source_format' => $request->string('source_format')->value(),
            'points' => $request->array('points'),
            'stats' => $request->has('stats') ? $request->array('stats') : null,
            'note' => $request->filled('note') ? $request->string('note')->value() : null,
            'imported_at' => $request->filled('imported_at') ? $request->date('imported_at') : null,
        ];

        $track = DB::transaction(fn (): ExploreTrack => ExploreTrack::create($payload));

        return response()->json(['track' => $track], 201);
    }

    /** Update a track (rename/note/stats/points) with optimistic concurrency. */
    public function updateTrack(Request $request, ExploreTrack $track): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:300'],
            'points' => ['sometimes', 'array'],
            'stats' => ['nullable', 'array'],
            'note' => ['nullable', 'string', 'max:5000'],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);

        $patch = [];
        if ($request->has('name')) {
            $patch['name'] = $request->string('name')->value();
        }
        if ($request->has('points')) {
            $patch['points'] = $request->array('points');
        }
        if ($request->has('stats')) {
            $patch['stats'] = $request->filled('stats') ? $request->array('stats') : null;
        }
        if ($request->has('note')) {
            $patch['note'] = $request->filled('note') ? $request->string('note')->value() : null;
        }
        $expected = $request->has('version') ? $request->integer('version') : null;

        $result = DB::transaction(function () use ($track, $patch, $expected): ExploreTrack|bool|null {
            $fresh = ExploreTrack::query()->lockForUpdate()->find($track->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false; // conflict sentinel
            }
            $fresh->fill($patch);
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = ExploreTrack::query()->find($track->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['track' => $result]);
    }

    /** Soft-delete a track; its couplings are removed outright (simplest). */
    public function destroyTrack(ExploreTrack $track): JsonResponse
    {
        DB::transaction(function () use ($track): void {
            $track->couplings()->delete();
            $track->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function restoreTrack(int $id): JsonResponse
    {
        $track = ExploreTrack::onlyTrashed()->findOrFail($id);
        $track->restore();

        return response()->json(['track' => $track]);
    }

    /** Permanently delete a trashed track: its raw file, then the row (couplings cascade). */
    public function forceDeleteTrack(int $id): JsonResponse
    {
        $track = ExploreTrack::withTrashed()->findOrFail($id);
        DB::transaction(function () use ($track): void {
            if (is_string($track->blob_path) && $track->blob_path !== '') {
                $this->fs()->delete($track->blob_path);
            }
            $track->forceDelete(); // cascades explore_couplings via FK
        });

        return response()->json(['ok' => true]);
    }

    public function trash(): JsonResponse
    {
        return response()->json([
            'tracks' => ExploreTrack::onlyTrashed()->orderByDesc('deleted_at')->get(),
        ]);
    }

    // --- Raw track file (optional) ---------------------------------------

    /** Store the original track file (gpx/kml/…) plaintext on disk; set blob_path. */
    public function uploadTrackFile(Request $request, ExploreTrack $track): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.self::MAX_TRACK_FILE_KB]]);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422);
        }
        $ext = strtolower($upload->getClientOriginalExtension());
        if (! in_array($ext, self::TRACK_EXTENSIONS, true)) {
            return response()->json(['error' => 'unsupported_format'], 422);
        }

        $path = 'explore/'.Str::uuid()->toString();
        $this->fs()->putFileAs('explore', $upload, basename($path));

        $fresh = DB::transaction(function () use ($track, $path): ExploreTrack {
            $current = ExploreTrack::query()->lockForUpdate()->findOrFail($track->id);
            // Replace any prior raw file so a re-upload never orphans bytes.
            if (is_string($current->blob_path) && $current->blob_path !== '' && $current->blob_path !== $path) {
                $this->fs()->delete($current->blob_path);
            }
            $current->forceFill(['blob_path' => $path]);
            $current->save();

            return $current;
        });

        return response()->json(['track' => $fresh]);
    }

    public function trackFile(ExploreTrack $track): StreamedResponse
    {
        if (! is_string($track->blob_path) || $track->blob_path === '' || ! $this->fs()->exists($track->blob_path)) {
            abort(404);
        }

        return $this->fs()->response($track->blob_path, $this->safeName($track->name), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ], 'attachment');
    }

    // --- Couplings -------------------------------------------------------

    /** Upsert the coupling for a photo (one per photo per user). */
    public function setCoupling(Request $request): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'photo_id' => ['required', 'string', 'max:64'],
            'explore_track_id' => ['required', 'integer', Rule::exists('explore_tracks', 'id')->where('user_id', $uid)->whereNull('deleted_at')],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'source' => ['nullable', 'string', Rule::in(['exif', 'interpolated', 'manual'])],
        ]);

        $coupling = DB::transaction(fn (): ExploreCoupling => ExploreCoupling::updateOrCreate(
            ['photo_id' => $request->string('photo_id')->value()],
            [
                'explore_track_id' => $request->integer('explore_track_id'),
                'lat' => $request->filled('lat') ? $request->float('lat') : null,
                'lng' => $request->filled('lng') ? $request->float('lng') : null,
                'source' => $request->filled('source') ? $request->string('source')->value() : null,
            ],
        ));

        return response()->json(['coupling' => $coupling]);
    }

    /** Remove the coupling for a photo (by photo_id in the body). */
    public function deleteCoupling(Request $request): JsonResponse
    {
        $request->validate(['photo_id' => ['required', 'string', 'max:64']]);
        ExploreCoupling::query()->where('photo_id', $request->string('photo_id')->value())->delete();

        return response()->json(['ok' => true]);
    }

    // --- Settings --------------------------------------------------------

    public function saveSettings(Request $request): JsonResponse
    {
        $request->validate([
            'coupling_time_tolerance_s' => ['required', 'integer', 'min:0', 'max:604800'],
            'coupling_distance_tolerance_m' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $uid = (int) $this->requireUser($request)->id;
        $settings = DB::transaction(fn (): ExploreSetting => ExploreSetting::updateOrCreate(
            ['user_id' => $uid],
            [
                'coupling_time_tolerance_s' => $request->integer('coupling_time_tolerance_s'),
                'coupling_distance_tolerance_m' => $request->integer('coupling_distance_tolerance_m'),
            ],
        ));

        return response()->json(['settings' => $settings]);
    }

    // --- Helpers ---------------------------------------------------------

    private function disk(): string
    {
        $d = config('files.disk');

        return is_string($d) ? $d : 'files';
    }

    private function fs(): Filesystem
    {
        return Storage::disk($this->disk());
    }

    /** Filesystem-safe download filename (strips path separators + control chars). */
    private function safeName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name);
        $clean = is_string($clean) ? trim($clean) : '';

        return $clean === '' ? 'track' : $clean;
    }
}
