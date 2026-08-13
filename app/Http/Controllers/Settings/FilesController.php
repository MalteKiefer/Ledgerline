<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Per-user Files settings: how many previous versions of a file to keep. Lives
 * in the profile hub (not admin) — it is a personal preference.
 */
class FilesController extends Controller
{
    use RedirectsToSettings;

    public function edit(Request $request): View
    {
        $user = $this->requireUser($request);

        return view('spa', [
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
}
