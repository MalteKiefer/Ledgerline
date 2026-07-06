<?php

declare(strict_types=1);

namespace App\Dav\Files;

use Sabre\DAV\INode;
use Sabre\DAVACL\AbstractPrincipalCollection;
use Sabre\DAVACL\PrincipalBackend\BackendInterface;

/**
 * WebDAV root at /dav/files/ — one home collection per principal, exposing that
 * user's file browser as a real filesystem (read/write, mkdir, rename, move).
 */
class FilesRoot extends AbstractPrincipalCollection
{
    public function __construct(
        BackendInterface $principalBackend,
        private readonly FileDavBackend $backend,
    ) {
        parent::__construct($principalBackend, 'principals');
    }

    public function getName(): string
    {
        return 'files';
    }

    /** @param array<string, mixed> $principalInfo */
    public function getChildForPrincipal(array $principalInfo): INode
    {
        return new FilesHome($this->backend, $principalInfo['uri']);
    }
}
