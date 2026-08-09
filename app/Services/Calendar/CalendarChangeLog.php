<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use Illuminate\Support\Facades\DB;

/**
 * Records a CalDAV sync-collection change: appends a change row at the calendar's
 * CURRENT sync token, then increments the token — matching Sabre's PDO convention
 * so a REPORT with `synctoken >= clientToken` returns each change exactly once (no
 * re-report on the following sync). The calendar row is locked for the duration so
 * concurrent DAV writers cannot lose an increment. Byte-for-byte analogue of
 * Contacts\DavChangeLog, shared by the DAV backend and the web-side writers.
 */
class CalendarChangeLog
{
    public function record(Calendar $calendar, string $uri, DavChangeOperation $op): void
    {
        $this->append($calendar, 'calendar_changes', 'calendar_id', $uri, $op);
    }

    private function append(Calendar $collection, string $table, string $foreignKey, string $uri, DavChangeOperation $op): void
    {
        DB::transaction(function () use ($collection, $table, $foreignKey, $uri, $op): void {
            // Lock the collection row so the read-modify-write of synctoken is
            // serialised against concurrent DAV writes.
            $locked = $collection->newQuery()->whereKey($collection->getKey())->lockForUpdate()->first();
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
