<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\UserSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Files settings: per-user version-keep count, plus (for admins) the global
 * file limits — storage quota, max upload size, trash retention and the
 * archive extract/create caps.
 */
class FilesController extends Controller
{
    use RedirectsToSettings;

    public function edit(Request $request): View
    {
        $admin = Gate::allows('manage-global-settings');

        return view('settings.files.edit', [
            'maxVersions' => UserSetting::for($request->user()->id)->file_max_versions ?? 10,
            'isAdmin' => $admin,
            'limits' => $admin ? AppSettings::current() : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file_max_versions' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        UserSetting::for($request->user()->id)->update(['file_max_versions' => $data['file_max_versions']]);

        // Global limits are admin-only; a null value clears the override so the
        // config/env default applies again.
        if (Gate::allows('manage-global-settings')) {
            $limits = $request->validate([
                'files_quota_mb' => ['nullable', 'integer', 'min:0', 'max:100000000'],
                'files_max_upload_mb' => ['nullable', 'integer', 'min:1', 'max:100000000'],
                'files_blob_orphan_grace_hours' => ['nullable', 'integer', 'min:0', 'max:100000'],
            ]);
            AppSettings::current()->update($limits);
            Cache::forget(AppServiceProvider::OVERRIDES_CACHE_KEY);
        }

        return $this->savedRedirect('settings.files.edit', 'settings.files_saved');
    }
}
