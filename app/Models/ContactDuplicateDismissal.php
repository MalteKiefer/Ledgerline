<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Remembers a duplicate group a user marked as "not a duplicate", keyed by a
 * stable signature of the group's contact ids, so it is not surfaced again.
 */
#[Fillable(['user_id', 'signature'])]
class ContactDuplicateDismissal extends Model
{
    /** sha1 hex of the group's contact ids (UUID strings), sorted and joined by "-". */
    public static function signatureFor(array $contactIds): string
    {
        $ids = array_map('strval', $contactIds);
        sort($ids, SORT_STRING);

        return sha1(implode('-', $ids));
    }
}
