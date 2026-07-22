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

/**
 * Files settings, split by audience: the per-user version-keep count lives in
 * the profile hub (edit/update); the global storage limits — quota, max upload
 * size and the blob orphan grace window — are workspace-wide and live under the
 * admin settings hub (limits/limitsUpdate), gated by manage-global-settings.
 */
class FilesController extends Controller
{
    use RedirectsToSettings;

    /** Personal: how many previous versions of a file to keep. */
    public function edit(Request $request): View
    {
        $user = $this->requireUser($request);

        return view('settings.files.edit', [
            'maxVersions' => UserSetting::for($user->id)->file_max_versions ?? 10,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'file_max_versions' => ['required', 'integer', 'min:1', 'max:200'],
        ]);
        $user = $this->requireUser($request);
        UserSetting::for($user->id)->update(['file_max_versions' => $request->integer('file_max_versions')]);

        return $this->savedSettings('files', 'settings.files.edit', 'settings.files_saved');
    }

    /** Admin: workspace-wide file limits. Route gated by manage-global-settings. */
    public function limits(Request $request): View
    {
        $this->requireUser($request);

        return view('settings.files.limits', ['limits' => AppSettings::current()]);
    }

    public function limitsUpdate(Request $request): RedirectResponse
    {
        // A null value clears the override so the config/env default applies again.
        $request->validate([
            'files_quota_mb' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'files_max_upload_mb' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'files_blob_orphan_grace_hours' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $nullableInt = static function (Request $r, string $key): ?int {
            $v = $r->input($key);

            return is_numeric($v) ? (int) $v : null;
        };
        AppSettings::current()->update([
            'files_quota_mb' => $nullableInt($request, 'files_quota_mb'),
            'files_max_upload_mb' => $nullableInt($request, 'files_max_upload_mb'),
            'files_blob_orphan_grace_hours' => $nullableInt($request, 'files_blob_orphan_grace_hours'),
        ]);
        Cache::forget(AppServiceProvider::OVERRIDES_CACHE_KEY);

        return $this->savedSettings('files', 'settings.files.limits', 'settings.files_saved');
    }
}
