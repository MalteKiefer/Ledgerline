<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GalleryRequest;
use App\Jobs\GeneratePhotoRenditions;
use App\Jobs\ReadPhotoMetadata;
use App\Models\CompanyProfile;
use App\Models\Photo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Gallery settings: trip-grouping thresholds and independent maintenance jobs
 * (re-read metadata, regenerate thumbnails).
 */
class GalleryController extends Controller
{
    public function edit(): View
    {
        return view('settings.gallery.edit', [
            'company' => CompanyProfile::current(),
            'photoCount' => Photo::count(),
        ]);
    }

    public function update(GalleryRequest $request): RedirectResponse
    {
        CompanyProfile::current()->update($request->validated());

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.gallery_settings_saved'));
    }

    /**
     * Re-read every photo's metadata (EXIF + reverse-geocoded place).
     */
    public function rescan(): RedirectResponse
    {
        $count = 0;
        Photo::query()->orderBy('id')->each(function (Photo $photo) use (&$count): void {
            ReadPhotoMetadata::dispatch($photo->id);
            $count++;
        });

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.photos_rescan_queued', ['count' => $count]));
    }

    /**
     * Regenerate every photo's thumbnails from the original.
     */
    public function regenerate(): RedirectResponse
    {
        $count = 0;
        Photo::query()->orderBy('id')->each(function (Photo $photo) use (&$count): void {
            GeneratePhotoRenditions::dispatch($photo->id);
            $count++;
        });

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.photos_regenerate_queued', ['count' => $count]));
    }
}
