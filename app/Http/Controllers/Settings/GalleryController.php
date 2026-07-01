<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GalleryRequest;
use App\Jobs\GeneratePhotoRenditions;
use App\Jobs\ReadPhotoMetadata;
use App\Jobs\RenamePhotos;
use App\Models\CompanyProfile;
use App\Models\Photo;
use App\Services\Gallery\VideoProcessor;
use App\Services\QueueStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gallery settings: trip-grouping thresholds and independent maintenance jobs
 * (re-read metadata, regenerate thumbnails).
 */
class GalleryController extends Controller
{
    public function edit(QueueStatus $queue, VideoProcessor $video): View
    {
        $counts = Photo::counts();

        return view('settings.gallery.edit', [
            'company' => CompanyProfile::current(),
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
        CompanyProfile::current()->update($request->validated());

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.gallery_settings_saved'));
    }

    /**
     * Re-read metadata (EXIF + reverse-geocoded place).
     */
    public function rescan(Request $request): RedirectResponse
    {
        $count = $this->dispatchToTargets($request, fn (int $id) => ReadPhotoMetadata::dispatch($id));

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.photos_rescan_queued', ['count' => $count]));
    }

    /**
     * Regenerate thumbnails from the original.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $count = $this->dispatchToTargets($request, fn (int $id) => GeneratePhotoRenditions::dispatch($id));

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.photos_regenerate_queued', ['count' => $count]));
    }

    /**
     * Re-apply the filename template to the display name.
     */
    public function rename(Request $request): RedirectResponse
    {
        $count = $this->dispatchToTargets($request, fn (int $id) => RenamePhotos::dispatch($id));

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.photos_rename_queued', ['count' => $count]));
    }

    /**
     * Queue every maintenance job (thumbnails, metadata, rename).
     */
    public function runAll(Request $request): RedirectResponse
    {
        $count = $this->dispatchToTargets($request, function (int $id): void {
            GeneratePhotoRenditions::dispatch($id);
            ReadPhotoMetadata::dispatch($id);
            RenamePhotos::dispatch($id);
        });

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.photos_all_jobs_queued', ['count' => $count]));
    }

    /**
     * Dispatch a job for the target photos: the newest N when a limit is given,
     * otherwise the whole library.
     */
    private function dispatchToTargets(Request $request, callable $dispatch): int
    {
        $limit = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ])['limit'] ?? null;

        $query = Photo::query()->select('id')->orderByDesc('id');
        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        $count = 0;
        foreach ($query->get() as $photo) {
            $dispatch($photo->id);
            $count++;
        }

        return $count;
    }
}
