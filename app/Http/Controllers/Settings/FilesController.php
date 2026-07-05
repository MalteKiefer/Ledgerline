<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Per-user Files preferences: how many previous versions of a file to keep
 * (1–10) when its content changes.
 */
class FilesController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.files.edit', [
            'maxVersions' => UserSetting::for($request->user()->id)->file_max_versions ?? 10,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file_max_versions' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        UserSetting::for($request->user()->id)->update(['file_max_versions' => $data['file_max_versions']]);

        return redirect()->route('settings.files.edit')->with('status', __('settings.files_saved'));
    }
}
