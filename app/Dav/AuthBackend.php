<?php

declare(strict_types=1);

namespace App\Dav;

use App\Services\Contacts\DavCredentialService;
use Sabre\DAV\Auth\Backend\AbstractBasic;

/**
 * CardDAV Basic-auth backend: validates the external app-password against
 * dav_credentials (never the app session). The authenticated user is the DAV
 * username, mapped to a principal by PrincipalBackend.
 */
class AuthBackend extends AbstractBasic
{
    public function __construct(private readonly DavCredentialService $credentials)
    {
        $this->realm = 'Ledgerline CardDAV';
        $this->principalPrefix = 'principals/';
    }

    protected function validateUserPass($username, $password): bool
    {
        return $this->credentials->verify((string) $username, (string) $password) !== null;
    }
}
