<?php

declare(strict_types=1);

namespace App\Dav;

/**
 * The user's Files tree mounted as a named collection ("files") under the unified
 * /dav server, so it sits alongside the CardDAV "addressbooks" and "principals"
 * collections without colliding. Behaviour is the root FolderNode's; only the
 * collection name differs (WebDAV clients mount /dav/files/).
 */
class FilesHome extends FolderNode
{
    public function __construct()
    {
        parent::__construct(null);
    }

    public function getName(): string
    {
        return 'files';
    }
}
