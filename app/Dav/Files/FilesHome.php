<?php

declare(strict_types=1);

namespace App\Dav\Files;

/** The per-user root of the WebDAV file tree (folders/files with no parent). */
class FilesHome extends FileCollection
{
    public function __construct(FileDavBackend $backend, string $principalUri)
    {
        parent::__construct($backend, $backend->userId($principalUri), $principalUri, null);
    }

    public function getName(): string
    {
        return basename($this->principalUri);
    }
}
