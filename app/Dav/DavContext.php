<?php

declare(strict_types=1);

namespace App\Dav;

/**
 * Request-scoped holder for the authenticated CardDAV user. Set by AuthBackend
 * on successful Basic auth; read by the backends to scope every operation to the
 * caller's own address books (defence-in-depth on top of the DAVACL plugin).
 */
class DavContext
{
    private ?int $userId = null;

    public function set(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }
}
