<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Set or clear the signed-in user's app-specific WebDAV password (used to mount
 * the Files tree as a network drive). Stored hashed; never echoed back.
 */
class WebDavAccessController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate(['webdav_password' => ['required', 'string', 'min:12', 'max:255']]);
        $user = $this->requireUser($request);
        $user->forceFill(['webdav_password' => Hash::make($request->string('webdav_password')->value())])->save();

        return back()->with('status', 'webdav-set');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->requireUser($request);
        $user->forceFill(['webdav_password' => null])->save();

        return back()->with('status', 'webdav-cleared');
    }
}
