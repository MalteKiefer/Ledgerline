<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Enums\DavChangeOperation;
use App\Models\AddressBook;
use Illuminate\Support\Facades\DB;

/**
 * Records a CardDAV change: bumps the address book's sync token and appends a
 * dav_changes row, atomically. The single source of the sync-collection log,
 * shared by the DAV backend and the web-side writers so clients always see edits.
 */
class DavChangeLog
{
    public function record(AddressBook $book, string $uri, DavChangeOperation $op): void
    {
        DB::transaction(function () use ($book, $uri, $op): void {
            $token = (int) $book->synctoken + 1;
            $book->forceFill(['synctoken' => $token])->save();

            DB::table('dav_changes')->insert([
                'address_book_id' => $book->id,
                'uri' => $uri,
                'operation' => $op->value,
                'synctoken' => $token,
            ]);
        });
    }
}
