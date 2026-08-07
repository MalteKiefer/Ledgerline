<?php

declare(strict_types=1);

namespace App\Dav;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Sabre\DAV\Auth\Backend\AbstractBasic;

/**
 * HTTP Basic auth for WebDAV clients: username = e-mail, password = the user's
 * app-specific WebDAV password (hashed at rest). On success the user is logged
 * into the request (no session persistence needed) so the OwnsUserData global
 * scope transparently scopes every Files query to them.
 *
 * WebDAV over plaintext HTTP puts the credential on the wire — acceptable on the
 * isolated, non-internet-facing LAN this is built for; the app-specific,
 * revocable password limits the blast radius, and TLS protects it if added.
 */
class WebDavAuth extends AbstractBasic
{
    protected function validateUserPass($username, $password): bool
    {
        $user = User::query()->where('email', $username)->first();
        if (! $user instanceof User || ! is_string($user->webdav_password) || $user->webdav_password === '') {
            return false;
        }
        if (! Hash::check($password, $user->webdav_password)) {
            return false;
        }
        Auth::login($user); // request-scoped; drives the OwnsUserData scope

        return true;
    }
}
