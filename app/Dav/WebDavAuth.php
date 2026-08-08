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
    /** A real (algorithm-valid) bogus hash to equalize timing on the miss path. */
    private static ?string $dummyHash = null;

    protected function validateUserPass($username, $password): bool
    {
        $user = User::query()->where('email', (string) $username)->first();
        $hash = ($user instanceof User && is_string($user->webdav_password) && $user->webdav_password !== '')
            ? $user->webdav_password
            : null;

        // Always run a real hash verify — against a bogus-but-valid hash when the
        // account is unknown or has no WebDAV password — so response time doesn't
        // reveal which e-mails have WebDAV enabled (timing/enumeration oracle).
        self::$dummyHash ??= Hash::make('webdav-timing-equalizer');
        $ok = Hash::check((string) $password, $hash ?? self::$dummyHash);

        if (! $ok || $hash === null || ! $user instanceof User) {
            return false;
        }
        Auth::login($user); // request-scoped; drives the OwnsUserData scope

        return true;
    }
}
