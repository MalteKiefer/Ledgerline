<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Services\Contacts\DavChangeLog;
use Illuminate\Support\Facades\DB;

/**
 * Persists a calendar object's ICS consistently (etag + denormalised columns +
 * calendar change-log), shared by the web controller, importer and CalDAV
 * backend (mirrors ContactPersister).
 */
class CalendarObjectPersister
{
    /** Hard ceiling for a single calendar object's ICS (a real VEVENT/VTODO is KBs). */
    public const MAX_ICS_BYTES = 1_048_576;

    public function __construct(
        private readonly ICalService $ical,
        private readonly DavChangeLog $changes,
    ) {}

    /** Whether an ICS payload is within the per-object size limit. */
    public static function withinLimit(string $ics): bool
    {
        return strlen($ics) <= self::MAX_ICS_BYTES;
    }

    public function persistNew(Calendar $calendar, string $uri, string $ics): CalendarObject
    {
        $object = CalendarObject::create(array_merge([
            'calendar_id' => $calendar->id,
            'uri' => $uri,
            'etag' => md5($ics),
            'ics' => $ics,
        ], $this->ical->denormalize($ics)));

        $this->changes->recordCalendar($calendar, $uri, DavChangeOperation::Added);

        return $object;
    }

    public function persistUpdate(CalendarObject $object, string $ics): CalendarObject
    {
        $object->forceFill(array_merge(['etag' => md5($ics), 'ics' => $ics], $this->ical->denormalize($ics)))->save();
        $this->changes->recordCalendar($object->calendar, $object->uri, DavChangeOperation::Modified);

        return $object;
    }

    /**
     * Reconcile a calendar's objects to exactly the given uri => ICS map by
     * diffing against what is stored: create new uris, update changed ones (by
     * etag), delete vanished ones, and leave unchanged objects (and the sync
     * token) untouched. Used by every generated/mirrored calendar
     * (subscriptions, birthdays, anniversaries, holidays) so a no-op rebuild
     * produces zero change-log churn and stable CalDAV sync.
     *
     * @param  array<string, string>  $uriToIcs
     */
    public function replace(Calendar $calendar, array $uriToIcs): void
    {
        // Atomic: a mid-rebuild failure must not leave the calendar half-empty
        // with a bumped sync token.
        DB::transaction(function () use ($calendar, $uriToIcs): void {
            $this->reconcile($calendar, $uriToIcs);
        });
    }

    /**
     * @param  array<string, string>  $uriToIcs
     */
    private function reconcile(Calendar $calendar, array $uriToIcs): void
    {
        $existing = CalendarObject::where('calendar_id', $calendar->id)->get(['id', 'uri', 'etag'])->keyBy('uri');

        foreach ($uriToIcs as $uri => $ics) {
            $current = $existing->get($uri);
            if ($current === null) {
                $this->persistNew($calendar, (string) $uri, $ics);
            } elseif ($current->etag !== md5($ics)) {
                $object = CalendarObject::find($current->id);
                if ($object !== null) {
                    $object->setRelation('calendar', $calendar);
                    $this->persistUpdate($object, $ics);
                }
            }
            $existing->forget($uri);
        }

        foreach ($existing as $uri => $current) {
            CalendarObject::whereKey($current->id)->delete();
            $this->changes->recordCalendar($calendar, (string) $uri, DavChangeOperation::Deleted);
        }
    }
}
