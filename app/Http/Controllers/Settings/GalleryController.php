<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GalleryRequest;
use App\Jobs\DetectDuplicatesJob;
use App\Jobs\GeneratePhotoRenditions;
use App\Jobs\ReadPhotoMetadata;
use App\Jobs\RenamePhotos;
use App\Models\AppSettings;
use App\Models\Photo;
use App\Providers\AppServiceProvider;
use App\Services\Gallery\VideoProcessor;
use App\Services\QueueStatus;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * Gallery settings: trip-grouping thresholds and independent maintenance jobs
 * (re-read metadata, regenerate thumbnails).
 */
class GalleryController extends Controller
{
    use RedirectsToSettings;

    public function edit(QueueStatus $queue, VideoProcessor $video): View
    {
        $counts = Photo::counts();

        return view('settings.gallery.edit', [
            'company' => AppSettings::current(),
            'photoCount' => $counts['total'],
            'counts' => $counts,
            'queue' => $queue->snapshot(),
            'ffmpegResolved' => $video->binaryPath(),
            'ffmpegAvailable' => $video->available(),
        ]);
    }

    /**
     * Live queue counts (pending and failed jobs) for the settings page.
     */
    public function queueStatus(QueueStatus $queue): JsonResponse
    {
        return response()->json($queue->snapshot());
    }

    public function update(GalleryRequest $request): RedirectResponse
    {
        AppSettings::current()->update($request->validated());
        Cache::forget(AppServiceProvider::OVERRIDES_CACHE_KEY);

        return $this->savedRedirect('settings.gallery.edit', 'flash.gallery_settings_saved');
    }

    /**
     * Re-read metadata (EXIF + reverse-geocoded place).
     */
    public function rescan(Request $request): RedirectResponse
    {
        return $this->dispatchBatch(
            $request,
            'gallery-metadata',
            fn (int $id): array => [new ReadPhotoMetadata($id)],
            fn (Builder $q) => $q->whereNull('metadata'),
            'flash.photos_rescan_queued',
        );
    }

    /**
     * Regenerate thumbnails from the original.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        return $this->dispatchBatch(
            $request,
            'gallery-thumbnails',
            fn (int $id): array => [new GeneratePhotoRenditions($id)],
            fn (Builder $q) => $q->where('status', '!=', 'ready'),
            'flash.photos_regenerate_queued',
        );
    }

    /**
     * Re-apply the filename template to the display name.
     */
    public function rename(Request $request): RedirectResponse
    {
        return $this->dispatchBatch(
            $request,
            'gallery-rename',
            fn (int $id): array => [new RenamePhotos($id)],
            fn (Builder $q) => $q->whereNull('processed_at'),
            'flash.photos_rename_queued',
        );
    }

    /**
     * Queue every maintenance job (thumbnails, metadata, rename).
     */
    public function runAll(Request $request): RedirectResponse
    {
        return $this->dispatchBatch(
            $request,
            'gallery-all',
            fn (int $id): array => [new GeneratePhotoRenditions($id), new ReadPhotoMetadata($id), new RenamePhotos($id)],
            fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('status', '!=', 'ready')->orWhereNull('metadata')),
            'flash.photos_all_jobs_queued',
        );
    }

    /**
     * Scan the library for content-based duplicates (perceptual hash + CLIP).
     * Backfills any missing embeddings first, then clusters into groups.
     */
    public function detectDuplicates(Request $request): RedirectResponse
    {
        Artisan::call('gallery:embed');
        DetectDuplicatesJob::dispatch();

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.duplicates_scan_queued'));
    }

    /**
     * Scan the library for faces (detect + cluster into people).
     */
    public function detectFaces(Request $request): RedirectResponse
    {
        Artisan::call('gallery:faces');

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.faces_scan_queued'));
    }

    /**
     * Live progress for a running maintenance batch.
     */
    public function batchStatus(Request $request): JsonResponse
    {
        $batch = $request->filled('id') ? Bus::findBatch((string) $request->query('id')) : null;

        if ($batch === null) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'total' => $batch->totalJobs,
            'pending' => $batch->pendingJobs,
            'processed' => $batch->processedJobs(),
            'failed' => $batch->failedJobs,
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
        ]);
    }

    /**
     * Batch a maintenance job across the target photos. Scope is one of: the
     * whole library (all), the newest N (recent + limit), or only items still
     * missing this job's output (missing).
     *
     * @param  callable(int): array<object>  $jobsFor
     */
    private function dispatchBatch(Request $request, string $name, callable $jobsFor, Closure $missing, string $flashKey): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'in:all,recent,missing'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $limit = $validated['limit'] ?? null;
        // Default to "recent" when only a limit is posted (backwards compatible).
        $scope = $validated['scope'] ?? ($limit !== null ? 'recent' : 'all');

        // Library-wide maintenance (admin-gated): operate across every user's
        // photos, not just the admin's own (the per-user scope would hide the rest).
        $query = Photo::withoutGlobalScopes()->select('id')->orderByDesc('id');
        if ($scope === 'missing') {
            $missing($query);
        } elseif ($scope === 'recent' && $limit !== null) {
            $query->limit($limit);
        }

        $ids = $query->pluck('id');
        $jobs = $ids->flatMap($jobsFor)->all();

        $redirect = redirect()->route('settings.gallery.edit')
            ->with('status', __($flashKey, ['count' => $ids->count()]));

        if ($jobs !== []) {
            $batch = Bus::batch($jobs)->name($name)->allowFailures()->dispatch();
            $redirect->with('batch_id', $batch->id);
        }

        return $redirect;
    }
}
