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
        $credential = $this->credentials->verify((string) $username, (string) $password);
        if ($credential === null) {
            return false;
        }

        // Remember who authenticated so the backends can scope to their data.
        $this->context->set((int) $credential->user_id);

        // Drop a short-lived marker proving THIS (username, password) pair passed
        // a real bcrypt check. The DAV rate limiter grants its generous quota
        // only when this marker exists, so an attacker cannot forge a Basic
        // header to escape the tight unauthenticated 60/min bucket.
        Cache::put(self::authMarkerKey((string) $username, (string) $password), true, 300);

        return true;
    }

    /** Cache key proving a (username, password) pair recently passed bcrypt. */
    public static function authMarkerKey(string $username, string $password): string
    {
        return 'dav-auth-ok:'.hash('sha256', $username.'|'.$password);
    }
}
