<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\CalendarObject;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Splitter\ICalendar;
use Throwable;

/**
 * Imports an .ics file (one or many VEVENTs) into a calendar. Each object is
 * deduped by UID (update in place), and malformed objects are skipped, not
 * fatal (mirrors ContactImporter).
 */
class CalendarImporter
{
    /** Upper bound on objects created from one file/feed (DoS guard). */
    public const MAX_OBJECTS = 10000;

    public function __construct(private readonly CalendarObjectPersister $persister) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(Calendar $calendar, string $ics): array
    {
        $created = $updated = $skipped = 0;

        try {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $ics);
            rewind($stream);
            $splitter = new ICalendar($stream);
        } catch (Throwable) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        while (true) {
            try {
                $vobj = $splitter->getNext();
            } catch (Throwable) {
                $skipped++;

                continue;
            }
            if ($vobj === null) {
                break;
            }
            if ($created + $updated >= self::MAX_OBJECTS) {
                break; // stop importing once the cap is hit
            }
            if (! $vobj instanceof VCalendar) {
                $skipped++;

                continue;
            }

            try {
                $comp = $vobj->VEVENT[0] ?? $vobj->VTODO[0] ?? null;
                if ($comp === null) {
                    $skipped++;

                    continue;
                }
                $uid = isset($comp->UID) ? (string) $comp->UID : (string) Str::uuid();
                $comp->UID = $uid;
                $payload = $vobj->serialize();

                $existing = CalendarObject::where('calendar_id', $calendar->id)
                    ->where('uid', $uid)->first();

                if ($existing !== null) {
                    $this->persister->persistUpdate($existing, $payload);
                    $updated++;
                } else {
                    $this->persister->persistNew($calendar, Str::uuid().'.ics', $payload);
                    $created++;
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
