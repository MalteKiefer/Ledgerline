<?php

declare(strict_types=1);

namespace App\Enums;

/** WebDAV sync-collection change operation (sabre convention). */
enum DavChangeOperation: int
{
    case Added = 1;
    case Modified = 2;
    case Deleted = 3;
}
