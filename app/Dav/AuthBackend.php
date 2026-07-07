<?php

declare(strict_types=1);

namespace App\Dav;

use App\Services\Contacts\DavCredentialService;
use Illuminate\Support\Facades\Cache;
use Sabre\DAV\Auth\Backend\AbstractBasic;

/**
 * CardDAV Basic-auth backend: validates the external app-password against
 * dav_credentials (never the app session). The authenticated user is the DAV
 * username, mapped to a principal by PrincipalBackend.
 */
class AuthBackend extends AbstractBasic
{
    public function __construct(
        private readonly DavCredentialService $credentials,
        private readonly DavContext $context,
    ) {
        $this->realm = 'Ledgerline CardDAV';
        $this->principalPrefix = 'principals/';
    }

    protected function validateUserPass($username, $password): bool
    {
        $key = self::authMarkerKey((string) $username, (string) $password);

        // macOS webdavfs re-sends Basic auth on EVERY request (dozens per copy).
        // bcrypt on each one dominates the latency (copies crawl), so cache the
        // user id behind a real verification and skip the rehash while the marker
        // is warm. A wrong password never has a marker → always full bcrypt, so
        // brute-forcing gains nothing (and stays 60/min via the rate limiter).
        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->context->set((int) $cached);

            return true;
        }

        $credential = $this->credentials->verify((string) $username, (string) $password);
        if ($credential === null) {
            return false;
        }

        // Remember who authenticated so the backends can scope to their data, and
        // drop the marker (also read by the DAV rate limiter to grant its
        // generous quota only to genuinely-authenticated clients).
        $this->context->set((int) $credential->user_id);
        Cache::put($key, (int) $credential->user_id, 300);

        return true;
    }

    /** Cache key proving a (username, password) pair recently passed bcrypt. */
    public static function authMarkerKey(string $username, string $password): string
    {
        return 'dav-auth-ok:'.hash('sha256', $username.'|'.$password);
    }
}
