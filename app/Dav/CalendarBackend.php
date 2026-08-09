<?php

declare(strict_types=1);

namespace App\Dav;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Services\Calendar\CalendarChangeLog;
use App\Services\Calendar\CalendarEventPersister;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\PropPatch;

/**
 * CalDAV storage backed by Eloquent. Events keep their raw ICS; write operations
 * bump the calendar's sync token and append a change row so clients can sync
 * incrementally.
 *
 * Every operation is owner-scoped to the request's authenticated user (set by
 * WebDavAuth via Auth::login), defence-in-depth on top of the DAVACL plugin: a
 * principal may only reach their own calendars (owner-only in Phase 1; sharing is
 * a later phase). Byte-for-byte mirror of AddressBookBackend.
 */
class CalendarBackend extends AbstractBackend implements SyncSupport
{
    public function __construct(
        private readonly CalendarChangeLog $changes,
        private readonly CalendarEventPersister $persister,
    ) {}

    /** The authenticated user id, or null when unauthenticated. */
    private function currentUserId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    /** The principal may see this calendar (owner-only in Phase 1). */
    private function ownsCalendar(string $calendarId): bool
    {
        $userId = $this->currentUserId();

        return $userId !== null && Calendar::query()->ownedBy($userId)->whereKey($calendarId)->exists();
    }

    /**
     * @param  string  $principalUri
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarsForUser($principalUri): array
    {
        $userId = $this->userId($principalUri);
        if ($userId === null) {
            return [];
        }

        return Calendar::query()->ownedBy($userId)->get()
            ->map(fn (Calendar $c): array => $this->calendarRow($c, $principalUri))->all();
    }

    /** @return array<string, mixed> */
    private function calendarRow(Calendar $c, string $principalUri): array
    {
        return [
            'id' => $c->id,
            'uri' => $c->uri,
            'principaluri' => $principalUri,
            '{DAV:}displayname' => $c->name,
            '{urn:ietf:params:xml:ns:caldav}calendar-description' => (string) $c->description,
            '{http://apple.com/ns/ical/}calendar-color' => (string) $c->color,
            '{http://sabredav.org/ns}sync-token' => (string) $c->synctoken,
            '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
        ];
    }

    /**
     * @param  string  $principalUri
     * @param  string  $calendarUri
     * @param  array<string, mixed>  $properties
     */
    public function createCalendar($principalUri, $calendarUri, array $properties): string
    {
        $userId = $this->userId($principalUri);
        if ($userId === null) {
            return '';
        }

        $dn = $properties['{DAV:}displayname'] ?? null;
        $desc = $properties['{urn:ietf:params:xml:ns:caldav}calendar-description'] ?? null;
        $color = $properties['{http://apple.com/ns/ical/}calendar-color'] ?? null;

        $calendar = Calendar::create([
            'user_id' => $userId,
            'uri' => $calendarUri,
            'name' => is_scalar($dn) ? (string) $dn : $calendarUri,
            'description' => is_scalar($desc) ? (string) $desc : null,
            'color' => is_scalar($color) ? substr((string) $color, 0, 9) : null,
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);

        return $calendar->id;
    }

    /** @param  string  $calendarId */
    public function updateCalendar($calendarId, PropPatch $propPatch): void
    {
        if (! $this->ownsCalendar($calendarId)) {
            return;
        }
        $calendar = Calendar::query()->withoutGlobalScopes()->find($calendarId);
        if ($calendar === null) {
            return;
        }

        $propPatch->handle([
            '{DAV:}displayname',
            '{urn:ietf:params:xml:ns:caldav}calendar-description',
            '{http://apple.com/ns/ical/}calendar-color',
        ], function (array $mutations) use ($calendar): bool {
            $dn = $mutations['{DAV:}displayname'] ?? null;
            if ($dn !== null) {
                $calendar->name = is_scalar($dn) ? (string) $dn : '';
            }
            $desc = $mutations['{urn:ietf:params:xml:ns:caldav}calendar-description'] ?? null;
            if ($desc !== null) {
                $calendar->description = is_scalar($desc) ? (string) $desc : null;
            }
            $color = $mutations['{http://apple.com/ns/ical/}calendar-color'] ?? null;
            if ($color !== null) {
                $calendar->color = is_scalar($color) ? substr((string) $color, 0, 9) : null;
            }
            $calendar->save();

            return true;
        });
    }

    /** @param  string  $calendarId */
    public function deleteCalendar($calendarId): void
    {
        if (! $this->ownsCalendar($calendarId)) {
            return;
        }
        Calendar::query()->withoutGlobalScopes()->whereKey($calendarId)->delete();
    }

    /**
     * @param  string  $calendarId
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarObjects($calendarId): array
    {
        if (! $this->ownsCalendar($calendarId)) {
            return [];
        }

        return CalendarEvent::where('calendar_id', $calendarId)->get()
            ->map(fn (CalendarEvent $e): array => $this->objectRow($e, includeData: false))->all();
    }

    /**
     * @param  string  $calendarId
     * @param  string  $objectUri
     * @return array<string, mixed>|null
     */
    public function getCalendarObject($calendarId, $objectUri): ?array
    {
        if (! $this->ownsCalendar($calendarId)) {
            return null;
        }
        $event = CalendarEvent::where('calendar_id', $calendarId)->where('uri', $objectUri)->first();

        return $event === null ? null : $this->objectRow($event, includeData: true);
    }

    /**
     * @param  string  $calendarId
     * @param  array<int, string>  $uris
     * @return array<int, array<string, mixed>>
     */
    public function getMultipleCalendarObjects($calendarId, array $uris): array
    {
        if (! $this->ownsCalendar($calendarId)) {
            return [];
        }

        return CalendarEvent::where('calendar_id', $calendarId)->whereIn('uri', $uris)->get()
            ->map(fn (CalendarEvent $e): array => $this->objectRow($e, includeData: true))->all();
    }

    /** @return array<string, mixed> */
    private function objectRow(CalendarEvent $e, bool $includeData): array
    {
        $row = [
            'id' => $e->id,
            'uri' => $e->uri,
            'lastmodified' => $e->updated_at?->getTimestamp(),
            'etag' => '"'.$e->etag.'"',
            'size' => strlen($e->ics),
            'component' => strtolower($e->component),
        ];
        if ($includeData) {
            $row['calendardata'] = $e->ics;
        }

        return $row;
    }

    /**
     * @param  string  $calendarId
     * @param  string  $objectUri
     * @param  string  $calendarData
     */
    public function createCalendarObject($calendarId, $objectUri, $calendarData): ?string
    {
        if (! $this->ownsCalendar($calendarId)) {
            return null;
        }
        $calendar = Calendar::query()->withoutGlobalScopes()->find($calendarId);
        if ($calendar === null) {
            return null;
        }
        $this->persister->persistNew($calendar, $objectUri, (string) $calendarData);

        return '"'.md5((string) $calendarData).'"';
    }

    /**
     * @param  string  $calendarId
     * @param  string  $objectUri
     * @param  string  $calendarData
     */
    public function updateCalendarObject($calendarId, $objectUri, $calendarData): ?string
    {
        if (! $this->ownsCalendar($calendarId)) {
            return null;
        }
        $event = CalendarEvent::where('calendar_id', $calendarId)->where('uri', $objectUri)->first();
        if ($event === null) {
            return null;
        }
        $this->persister->persistUpdate($event, (string) $calendarData);

        return '"'.md5((string) $calendarData).'"';
    }

    /**
     * @param  string  $calendarId
     * @param  string  $objectUri
     */
    public function deleteCalendarObject($calendarId, $objectUri): void
    {
        if (! $this->ownsCalendar($calendarId)) {
            return;
        }
        $deleted = CalendarEvent::where('calendar_id', $calendarId)->where('uri', $objectUri)->delete();
        if ($deleted) {
            $calendar = Calendar::query()->withoutGlobalScopes()->find($calendarId);
            if ($calendar !== null) {
                $this->changes->record($calendar, $objectUri, DavChangeOperation::Deleted);
            }
        }
    }

    /**
     * Owner-scoped calendar-query. The parent parses each object's ICS through
     * Sabre's CalendarQueryValidator (which handles time-range + recurrence), so
     * we only guard ownership and delegate the matching to the (correct) default.
     *
     * @param  string  $calendarId
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    public function calendarQuery($calendarId, array $filters): array
    {
        if (! $this->ownsCalendar($calendarId)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $uri): string => is_scalar($uri) ? (string) $uri : '',
            parent::calendarQuery($calendarId, $filters),
        ));
    }

    /**
     * @param  string  $calendarId
     * @return array<string, mixed>|null
     */
    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null): ?array
    {
        if (! $this->ownsCalendar($calendarId)) {
            return null;
        }
        $calendar = Calendar::query()->withoutGlobalScopes()->find($calendarId);
        if ($calendar === null) {
            return null;
        }

        $current = (int) $calendar->synctoken;

        if ($syncToken === null || $syncToken === '') {
            // Initial sync: every current event is "added".
            return [
                'syncToken' => (string) $current,
                'added' => CalendarEvent::where('calendar_id', $calendarId)->pluck('uri')->all(),
                'modified' => [],
                'deleted' => [],
            ];
        }

        // Stale/foreign or pruned-away token → null so Sabre triggers a full
        // resync (RFC 6578 valid-sync-token).
        if (! ctype_digit((string) $syncToken) || (int) $syncToken > $current) {
            return null;
        }
        $oldestKept = DB::table('calendar_changes')->where('calendar_id', $calendarId)->min('synctoken');
        if (is_numeric($oldestKept) && (int) $syncToken < (int) $oldestKept && (int) $syncToken < $current) {
            return null;
        }

        $rows = DB::table('calendar_changes')
            ->where('calendar_id', $calendarId)
            ->where('synctoken', '>=', (int) $syncToken)
            ->orderBy('synctoken')
            ->when($limit, fn ($q) => $q->limit((int) $limit))
            ->get(['uri', 'operation']);

        // Latest operation per uri wins.
        /** @var array<string, int> $latest */
        $latest = [];
        foreach ($rows as $row) {
            $uri = is_scalar($row->uri) ? (string) $row->uri : '';
            $latest[$uri] = is_numeric($row->operation) ? (int) $row->operation : 0;
        }

        $result = ['syncToken' => (string) $current, 'added' => [], 'modified' => [], 'deleted' => []];
        foreach ($latest as $uri => $op) {
            $result[match (DavChangeOperation::from($op)) {
                DavChangeOperation::Added => 'added',
                DavChangeOperation::Modified => 'modified',
                DavChangeOperation::Deleted => 'deleted',
            }][] = (string) $uri;
        }

        return $result;
    }

    /**
     * Resolve the principal to the authenticated user's id. The principal path is
     * always the caller's own (PrincipalBackend never exposes another), so this
     * only accepts the request's authenticated user.
     */
    private function userId(string $principalUri): ?int
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $key = $user->getKey();

        return basename($principalUri) === (string) $user->email && is_numeric($key) ? (int) $key : null;
    }
}
