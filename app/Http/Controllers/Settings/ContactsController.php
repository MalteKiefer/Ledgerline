<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Contacts\DavCredentialService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Contacts / CardDAV settings: enable the feature (generates the DAV login),
 * show the sync URL + username, and rotate the app-password. The password is
 * shown once (flashed) and only its hash is stored.
 */
class ContactsController extends Controller
{
    public function edit(DavCredentialService $credentials): View
    {
        return view('settings.contacts.edit', [
            'credential' => $credentials->for(auth()->id()),
            'davUrl' => url('/dav/'),
        ]);
    }

    public function generate(Request $request, DavCredentialService $credentials): RedirectResponse
    {
        $result = $credentials->generate($request->user()->id);

        return redirect()->route('settings.contacts.edit')
            ->with('status', __('flash.dav_password_generated'))
            ->with('dav_username', $result['credential']->username)
            ->with('dav_password', $result['password']);
    }
}
