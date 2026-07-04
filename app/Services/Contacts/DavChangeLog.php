<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Enums\DavChangeOperation;
use App\Models\AddressBook;
use App\Models\Calendar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Records a WebDAV sync-collection change: appends a change row at the
 * collection's CURRENT sync token, then increments the token — matching Sabre's
 * PDO convention so a REPORT with `synctoken >= clientToken` returns each change
 * exactly once (no re-report on the following sync). The row is locked for the
 * duration so concurrent DAV writers cannot lose an increment. Shared by the DAV
 * backend and the web-side writers.
 */
class DavChangeLog
{
    public function record(AddressBook $book, string $uri, DavChangeOperation $op): void
    {
        $this->append($book, 'dav_changes', 'address_book_id', $uri, $op);
    }

    /** Same, for a calendar (its own change log + sync token). */
    public function recordCalendar(Calendar $calendar, string $uri, DavChangeOperation $op): void
    {
        $this->append($calendar, 'calendar_changes', 'calendar_id', $uri, $op);
    }

    private function append(Model $collection, string $table, string $foreignKey, string $uri, DavChangeOperation $op): void
    {
        DB::transaction(function () use ($collection, $table, $foreignKey, $uri, $op): void {
            // Lock the collection row so the read-modify-write of synctoken is
            // serialised against concurrent DAV writes.
            $locked = $collection->newQuery()->lockForUpdate()->find($collection->getKey());
            $token = (int) ($locked?->synctoken ?? $collection->synctoken);

            DB::table($table)->insert([
                $foreignKey => $collection->getKey(),
                'uri' => $uri,
                'operation' => $op->value,
                'synctoken' => $token, // pre-increment: change lives below the new token
            ]);

            $collection->forceFill(['synctoken' => $token + 1])->save();
        });
    }
}
