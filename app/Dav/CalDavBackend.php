<?php

declare(strict_types=1);

namespace App\Dav;

use App\Enums\CalendarUri;
use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Models\DavCredential;
use App\Services\Calendar\CalendarObjectPersister;
use App\Services\Calendar\TodoVtodoBridge;
use App\Services\Contacts\DavChangeLog;
use Illuminate\Support\Facades\DB;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\PropPatch;

/**
 * CalDAV storage backed by Eloquent, mirroring AddressBookBackend: per-user
 * ownership via DavContext, sync-collection change log, and read-only guards for
 * subscription calendars. The raw ICS is authoritative.
 */
class CalDavBackend extends AbstractBackend implements SyncSupport
{
    public function __construct(
        private readonly DavContext $context,
        private readonly DavChangeLog $changes,
        private readonly CalendarObjectPersister $persister,
        private readonly TodoVtodoBridge $todos,
    ) {}

    private function ownsCalendar(string $calendarId): bool
    {
        $userId = $this->context->userId();

        return $userId !== null && Calendar::where('id', $calendarId)->where('user_id', $userId)->exists();
    }

    public function getCalendarsForUser($principalUri): array
    {
        $userId = $this->userId($principalUri);
        if ($userId === null) {
            return [];
        }

        return Calendar::where('user_id', $userId)->get()->map(fn (Calendar $c): array => [
            'id' => $c->id,
            'uri' => $c->uri,
            'principaluri' => $principalUri,
            '{DAV:}displayname' => $c->name,
            '{urn:ietf:params:xml:ns:caldav}calendar-description' => (string) $c->description,
            '{http://apple.com/ns/ical/}calendar-color' => (string) ($c->color ?: Calendar::DEFAULT_COLOR),
            '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new SupportedCalendarComponentSet($c->components ?: ['VEVENT']),
            '{http://sabredav.org/ns}sync-token' => (string) $c->synctoken,
        ])->all();
    }

    public function createCalendar($principalUri, $calendarUri, array $properties): void
    {
        $userId = $this->userId($principalUri);
        if ($userId === null || CalendarUri::isReserved($calendarUri)) {
            return; // don't let a client collide with a managed/virtual collection
        }
        $components = ['VEVENT'];
        if (isset($properties['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'])) {
            $components = $properties['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set']->getValue();
        }

        Calendar::create([
            'user_id' => $userId,
            'uri' => $calendarUri,
            'name' => (string) ($properties['{DAV:}displayname'] ?? $calendarUri),
            'color' => $properties['{http://apple.com/ns/ical/}calendar-color'] ?? null,
            'description' => $properties['{urn:ietf:params:xml:ns:caldav}calendar-description'] ?? null,
            'components' => $components,
            'synctoken' => 1,
        ]);
    }

    public function updateCalendar($calendarId, PropPatch $propPatch): void
    {
        if (! $this->ownsCalendar($calendarId)) {
            return;
        }
        $calendar = Calendar::find($calendarId);
        if ($calendar === null) {
            return;
        }
        $propPatch->handle([
            '{DAV:}displayname', '{urn:ietf:params:xml:ns:caldav}calendar-description', '{http://apple.com/ns/ical/}calendar-color',
        ], function (array $m) use ($calendar): bool {
            if (isset($m['{DAV:}displayname'])) {
                $calendar->name = (string) $m['{DAV:}displayname'];
            }
            if (isset($m['{urn:ietf:params:xml:ns:caldav}calendar-description'])) {
                $calendar->description = (string) $m['{urn:ietf:params:xml:ns:caldav}calendar-description'];
            }
            if (isset($m['{http://apple.com/ns/ical/}calendar-color'])) {
                // Apple sends #rrggbbaa; keep only a valid hex colour.
                $color = substr((string) $m['{http://apple.com/ns/ical/}calendar-color'], 0, 9);
                if (preg_match('/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $color) === 1) {
                    $calendar->color = $color;
                }
            }
            $calendar->save();

            return true;
        });
    }

    public function deleteCalendar($calendarId): void
    {
        if (! $this->ownsCalendar($calendarId)) {
            return;
        }
        // The app manages default/tasks/derived collections; don't let a client
        // delete them out from under it.
        if (Calendar::find($calendarId)?->isUndeletable()) {
            return;
        }
        Calendar::where('id', $calendarId)->delete();
    }

    public function getCalendarObjects($calendarId): array
    {
        if (! $this->ownsCalendar($calendarId)) {
            return [];
        }
        if (Calendar::find($calendarId)?->isTasks()) {
            return $this->todos->rows($calendarId);
        }

        return CalendarObject::where('calendar_id', $calendarId)->get()->map(fn (CalendarObject $o): array => $this->row($o))->all();
    }

    public function getCalendarObject($calendarId, $objectUri): ?array
    {
        if (! $this->ownsCalendar($calendarId)) {
            return null;
        }
        if (Calendar::find($calendarId)?->isTasks()) {
            return $this->todos->get($calendarId, $objectUri);
        }
        $object = CalendarObject::where('calendar_id', $calendarId)->where('uri', $objectUri)->first();

        return $object !== null ? $this->row($object, true) : null;
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData): ?string
    {
        $calendar = Calendar::find($calendarId);
        if ($calendar === null || ! $this->ownsCalendar($calendarId) || $calendar->isReadOnly()) {
            return null;
        }
        $this->assertWithinLimit($calendarData);
        if ($calendar->isTasks()) {
            // To-dos are the source of truth and are created inside the app;
            // client-initiated VTODO creates would live at a URI we cannot honour
            // (we expose todo-<id>.ics), so reject them. Edits/completions of
            // existing to-dos go through updateCalendarObject.
            return null;
        }
        $this->persister->persistNew($calendar, $objectUri, $calendarData);

        return '"'.md5($calendarData).'"';
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData): ?string
    {
        $calendar = Calendar::find($calendarId);
        if ($calendar === null || ! $this->ownsCalendar($calendarId) || $calendar->isReadOnly()) {
            return null;
        }
        $this->assertWithinLimit($calendarData);
        if ($calendar->isTasks()) {
            return $this->todos->write($objectUri, $calendarData);
        }
        $object = CalendarObject::where('calendar_id', $calendarId)->where('uri', $objectUri)->first();
        if ($object === null) {
            return null;
        }
        $this->persister->persistUpdate($object, $calendarData);

        return '"'.md5($calendarData).'"';
    }

    /** Reject oversized objects (memory-exhaustion guard). */
    private function assertWithinLimit(string $calendarData): void
    {
        if (! CalendarObjectPersister::withinLimit($calendarData)) {
            throw new Forbidden('Calendar object exceeds the maximum allowed size.');
        }
    }

    public function deleteCalendarObject($calendarId, $objectUri): void
    {
        $calendar = Calendar::find($calendarId);
        if ($calendar === null || ! $this->ownsCalendar($calendarId) || $calendar->isReadOnly()) {
            return;
        }
        if ($calendar->isTasks()) {
            $this->todos->delete($objectUri);

            return;
        }
        if (CalendarObject::where('calendar_id', $calendarId)->where('uri', $objectUri)->delete()) {
            $this->changes->recordCalendar($calendar, $objectUri, DavChangeOperation::Deleted);
        }
    }

    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null): ?array
    {
        if (! $this->ownsCalendar($calendarId)) {
            return null;
        }
        $calendar = Calendar::find($calendarId);
        if ($calendar === null) {
            return null;
        }
        $current = (int) $calendar->synctoken;

        if ($syncToken === null || $syncToken === '') {
            $added = $calendar->isTasks()
                ? array_column($this->todos->rows($calendarId), 'uri')
                : CalendarObject::where('calendar_id', $calendarId)->pluck('uri')->all();

            return [
                'syncToken' => (string) $current,
                'added' => $added,
                'modified' => [],
                'deleted' => [],
            ];
        }

        // A non-numeric or future token is stale/foreign: return null so Sabre
        // answers with a 'valid-sync-token' precondition and the client falls
        // back to a full sync (RFC 6578). Pruned-away history lands here too.
        if (! ctype_digit((string) $syncToken) || (int) $syncToken > $current) {
            return null;
        }
        $oldestKept = DB::table('calendar_changes')->where('calendar_id', $calendarId)->min('synctoken');
        if ($oldestKept !== null && (int) $syncToken < (int) $oldestKept && (int) $syncToken < $current) {
            return null;
        }

        $latest = [];
        foreach (DB::table('calendar_changes')->where('calendar_id', $calendarId)->where('synctoken', '>=', (int) $syncToken)->orderBy('synctoken')->when($limit, fn ($q) => $q->limit((int) $limit))->get(['uri', 'operation']) as $row) {
            $latest[$row->uri] = $row->operation;
        }

        $result = ['syncToken' => (string) $current, 'added' => [], 'modified' => [], 'deleted' => []];
        foreach ($latest as $uri => $op) {
            $result[match (DavChangeOperation::from((int) $op)) {
                DavChangeOperation::Added => 'added',
                DavChangeOperation::Modified => 'modified',
                DavChangeOperation::Deleted => 'deleted',
            }][] = $uri;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(CalendarObject $o, bool $withData = false): array
    {
        $row = [
            'id' => $o->id,
            // 'calendarid' lets Sabre's AbstractBackend::calendarQuery() fallback
            // re-fetch the object when running filters (calendar-query /
            // free-busy REPORTs); omitting it fatals on those reports.
            'calendarid' => $o->calendar_id,
            'uri' => $o->uri,
            'lastmodified' => $o->updated_at?->getTimestamp(),
            'etag' => '"'.$o->etag.'"',
            'size' => strlen($o->ics),
            'component' => strtolower($o->component),
        ];
        if ($withData) {
            $row['calendardata'] = $o->ics;
        }

        return $row;
    }

    private function userId(string $principalUri): ?int
    {
        return DavCredential::where('username', basename($principalUri))->value('user_id');
    }
}
