<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GalleryRequest;
use App\Jobs\ProcessPhoto;
use App\Models\CompanyProfile;
use App\Models\Photo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Gallery settings: trip-grouping thresholds and a metadata re-scan.
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
     * Re-queue every photo so its renditions and metadata are refreshed.
     */
    public function rescan(): RedirectResponse
    {
        $count = 0;
        Photo::query()->orderBy('id')->each(function (Photo $photo) use (&$count): void {
            ProcessPhoto::dispatch($photo->id);
            $count++;
        });

        return redirect()->route('settings.gallery.edit')->with('status', __('flash.photos_rescan_queued', ['count' => $count]));
    }
}
