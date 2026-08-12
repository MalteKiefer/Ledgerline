<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Calendar\CalendarEventService;
use App\Services\Calendar\CalendarImporter;
use App\Services\Calendar\CalendarWriter;
use App\Services\Calendar\OpenHolidaysClient;
use App\Services\Calendar\SpecialCalendarGenerator;
use App\Support\CalendarAccess;
use App\Support\FreeBusy;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Calendar UI backend (reload-free JSON). All queries are scoped to the
 * authenticated user's calendars; writes go through CalendarWriter so the ICS,
 * denormalised columns and CalDAV sync token stay consistent. Optimistic
 * concurrency is expressed via the DAV-native etag (409 on mismatch), mirroring
 * the contacts controller's guard-agnostic (web session + Sanctum) shape.
 */
class CalendarController extends Controller
{
    /** Module entry: the calendar UI lives in the SPA. */
    public function index(Request $request): RedirectResponse
    {
        $this->ensureCalendar($this->requireUser($request)->id);

        return redirect('/spa/calendar');
    }

    /** Guarantee the user has at least one calendar (events need a home). */
    private function ensureCalendar(int $userId): Calendar
    {
        $existing = Calendar::query()->ownedBy($userId)->first();
        if ($existing !== null) {
            return $existing;
        }

        return Calendar::create([
            'user_id' => $userId,
            'name' => __('calendar.ui.default_calendar'),
            'uri' => 'calendar-'.Str::lower(Str::random(6)),
            'color' => '#6750a4',
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);
    }

    /** Calendars + per-user view settings (mirror ContactController@data). */
    public function data(Request $request): JsonResponse
    {
        $userId = $this->requireUser($request)->id;
        $this->ensureCalendar($userId);
        $settings = UserSetting::for($userId);

        // Own calendars + calendars shared TO the user (viewer/editor).
        $roles = CalendarAccess::roles($userId);
        $calendars = Calendar::query()->withoutGlobalScopes()
            ->whereIn('id', array_keys($roles))
            ->orderBy('name')->get(['id', 'name', 'uri', 'color', 'kind', 'component', 'country', 'subdivision', 'user_id']);

        return response()->json([
            'calendars' => $calendars
                ->map(fn (Calendar $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'uri' => $c->uri,
                    'color' => $c->color,
                    'kind' => $c->kind,
                    'component' => $c->component,
                    'country' => $c->country,
                    'subdivision' => $c->subdivision,
                    'owned' => ($roles[(string) $c->id] ?? null) === 'owner',
                    'role' => $roles[(string) $c->id] ?? 'owner',
                    'writable' => in_array($roles[(string) $c->id] ?? '', ['owner', 'editor'], true),
                ]),
            'settings' => [
                'default_view' => (string) ($settings->calendar_default_view ?? 'month'),
                'week_start' => (int) ($settings->calendar_week_start ?? 1),
            ],
        ]);
    }

    /** Recurrence-expanded occurrences in [from,to] across the user's calendars. */
    public function events(Request $request, CalendarEventService $service): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'calendar' => ['nullable', 'string'],
        ]);
        $from = CarbonImmutable::parse((string) $request->query('from'))->utc();
        $to = CarbonImmutable::parse((string) $request->query('to'))->utc();

        // Cap the requested span: recurrence expansion is O(events · occurrences),
        // so an unbounded window (e.g. 1970→3000) is a self-DoS. A calendar UI never
        // needs more than a wide year around the view; reject anything larger.
        if ($from->diffInDays($to) > 400) {
            return response()->json(['error' => 'range_too_large'], 422);
        }

        // Accessible calendar ids (own + shared) + colour map.
        $readable = CalendarAccess::readableIds((int) $this->requireUser($request)->id);
        $calendars = Calendar::query()->withoutGlobalScopes()
            ->whereIn('id', $readable)
            ->when($request->query('calendar'), fn ($q) => $q->whereKey($request->query('calendar')))
            ->get(['id', 'color']);
        $colors = $calendars->pluck('color', 'id');
        $calendarIds = $calendars->pluck('id')->all();

        // Coarse prefilter: the master starts before the window ends, and either
        // it ends after the window starts OR it recurs (exact filtering is done in
        // expand()). Bounds the rows before the PHP recurrence expansion.
        $candidates = CalendarEvent::query()
            ->whereIn('calendar_id', $calendarIds)
            ->whereNotNull('dtstart')
            ->where('dtstart', '<', $to)
            ->where(fn ($w) => $w->whereNull('dtend')->orWhere('dtend', '>', $from)->orWhereNotNull('rrule'))
            ->get();

        $out = [];
        foreach ($candidates as $event) {
            $color = $colors[$event->calendar_id] ?? null;
            foreach ($service->expand($event, $from, $to) as $occ) {
                $out[] = array_merge($occ, [
                    'id' => $event->id,
                    'calendar' => $event->calendar_id,
                    'color' => is_string($color) ? $color : null,
                ]);
            }
        }

        return response()->json(['events' => $out]);
    }

    /** Busy intervals for the current user across their readable calendars. */
    public function freeBusy(Request $request, FreeBusy $fb): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after:from']]);
        $from = CarbonImmutable::parse((string) $request->query('from'))->utc();
        $to = CarbonImmutable::parse((string) $request->query('to'))->utc();
        if ($from->diffInDays($to) > 400) {
            return response()->json(['error' => 'range_too_large'], 422);
        }
        $busy = array_map(
            static fn (array $b): array => ['start' => $b['start']->toIso8601ZuluString(), 'end' => $b['end']->toIso8601ZuluString()],
            $fb->busy(CalendarAccess::readableIds($uid), $from, $to),
        );

        return response()->json(['busy' => $busy]);
    }

    /**
     * Find common free slots for the current user and any named local attendees
     * (whose availability comes from calendars they have shared with the caller).
     */
    public function slots(Request $request, FreeBusy $fb): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:1440'],
            'day_start' => ['nullable', 'integer', 'min:0', 'max:23'],
            'day_end' => ['nullable', 'integer', 'min:1', 'max:24'],
            'attendees' => ['nullable', 'array', 'max:20'],
            'attendees.*' => ['email'],
        ]);
        $from = CarbonImmutable::parse((string) $request->string('from'))->utc();
        $to = CarbonImmutable::parse((string) $request->string('to'))->utc();
        if ($from->diffInDays($to) > 62) {
            return response()->json(['error' => 'range_too_large'], 422);
        }

        // The caller's readable calendars, plus calendars each attendee has
        // shared with the caller (already a subset of the readable set).
        $roles = CalendarAccess::roles($uid);
        $calIds = array_keys($roles);
        $unknown = [];
        foreach ((array) $request->input('attendees', []) as $email) {
            if (! is_string($email)) {
                continue;
            }
            $att = User::query()->where('email', $email)->first();
            $sharedFromAtt = $att instanceof User
                ? Calendar::query()->withoutGlobalScopes()->whereIn('id', array_keys($roles))->where('user_id', $att->id)->exists()
                : false;
            if (! $sharedFromAtt) {
                $unknown[] = $email; // no shared availability for this attendee
            }
        }

        $busy = $fb->busy($calIds, $from, $to);
        $slots = $fb->freeSlots(
            $busy, $from, $to,
            (int) $request->integer('duration_min'),
            $request->filled('day_start') ? (int) $request->integer('day_start') : 8,
            $request->filled('day_end') ? (int) $request->integer('day_end') : 18,
        );

        return response()->json(['slots' => $slots, 'unknown_attendees' => $unknown]);
    }

    /** Full parsed editor data + ids + etag (mirror ContactController@show). */
    public function show(CalendarEvent $event, CalendarEventService $service): JsonResponse
    {
        $this->authorizeEvent($event);

        return response()->json(array_merge(
            $service->parse($event->ics),
            [
                'id' => $event->id,
                'calendar' => $event->calendar_id,
                'etag' => $event->etag,
            ],
        ));
    }

    public function store(Request $request, CalendarWriter $writer): JsonResponse
    {
        $data = $this->validated($request, creating: true);
        $uid = (int) $this->requireUser($request)->id;
        $calendarId = $request->string('calendar_id')->value();
        // Own or editor-shared calendar only. Hide non-accessible calendars (404),
        // 403 only when readable-but-not-writable (viewer share).
        abort_unless(CalendarAccess::canRead($uid, $calendarId), 404);
        abort_unless(CalendarAccess::canWrite($uid, $calendarId), 403);
        $calendar = Calendar::query()->withoutGlobalScopes()->findOrFail($calendarId);
        // Special calendars are generated + read-only; reject manual event writes.
        abort_if($calendar->isSpecial(), 422);
        $event = $writer->create($calendar, $data);

        return response()->json(['id' => $event->id], 201);
    }

    public function update(Request $request, CalendarEvent $event, CalendarWriter $writer): JsonResponse
    {
        $this->authorizeEvent($event, write: true);
        // Generated events in a special calendar are read-only.
        abort_if($this->eventCalendar($event)?->isSpecial() ?? false, 422);
        $data = $this->validated($request);

        // Optimistic concurrency via the DAV-native etag: reject a stale write.
        $etag = trim((string) $request->string('etag'));
        if ($etag !== '' && $etag !== $event->etag) {
            return response()->json(['error' => 'etag_conflict', 'etag' => $event->etag], 409);
        }

        $updated = $writer->update($event, $data);

        return response()->json(['ok' => true, 'etag' => $updated->etag]);
    }

    public function destroy(CalendarEvent $event, CalendarWriter $writer): JsonResponse
    {
        $this->authorizeEvent($event, write: true);
        $writer->delete($event);

        return response()->json(['ok' => true]);
    }

    /** Delete a single occurrence of a recurring event (EXDATE on the master). */
    public function excludeOccurrence(Request $request, CalendarEvent $event, CalendarWriter $writer): JsonResponse
    {
        $this->authorizeEvent($event, write: true);
        abort_if($this->eventCalendar($event)?->isSpecial() ?? false, 422);
        $request->validate(['start' => ['required', 'date']]);
        $updated = $writer->excludeOccurrence($event, (string) $request->string('start'));

        return response()->json(['ok' => true, 'etag' => $updated->etag]);
    }

    /** Edit a single occurrence of a recurring event (RECURRENCE-ID override). */
    public function overrideOccurrence(Request $request, CalendarEvent $event, CalendarWriter $writer): JsonResponse
    {
        $this->authorizeEvent($event, write: true);
        abort_if($this->eventCalendar($event)?->isSpecial() ?? false, 422);
        $request->validate(['recurrence_id' => ['required', 'date']]);
        $data = $this->validated($request);
        $updated = $writer->overrideOccurrence($event, (string) $request->string('recurrence_id'), $data);

        return response()->json(['ok' => true, 'etag' => $updated->etag]);
    }

    /** Persist the user's calendar view preferences (default view + week start). */
    public function settings(Request $request): JsonResponse
    {
        $request->validate([
            'default_view' => ['required', 'in:month,week,agenda'],
            'week_start' => ['required', 'integer', 'in:0,1'],
        ]);

        UserSetting::for($this->requireUser($request)->id)->update([
            'calendar_default_view' => (string) $request->string('default_view'),
            'calendar_week_start' => $request->integer('week_start'),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Create a SPECIAL (generated, read-only) calendar — "holidays" (public
     * holidays for a country + optional region), "school_holidays" (Ferien) or
     * "birthdays" (from contacts) — and populate it. The country/subdivision are
     * persisted so a later regenerate re-queries the same region. Normal calendars
     * keep going through CalendarBookController@store.
     */
    public function storeSpecial(Request $request, SpecialCalendarGenerator $generator): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:9'],
            'kind' => ['required', 'in:'.implode(',', Calendar::SPECIAL_KINDS)],
            'country' => ['nullable', 'string', 'regex:/^[A-Za-z-]{2,8}$/'],
            'subdivision' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{2,16}$/'],
        ]);
        $kind = $request->string('kind')->toString();
        // country/subdivision only apply to the holiday kinds; birthdays ignore them.
        $regional = in_array($kind, [Calendar::KIND_HOLIDAYS, Calendar::KIND_SCHOOL_HOLIDAYS], true);
        $country = $regional && $request->filled('country') ? strtoupper((string) $request->string('country')) : null;
        $subdivision = $regional && $request->filled('subdivision') ? (string) $request->string('subdivision') : null;

        // The SPA sends a predefined name; derive a safe server-side default if a
        // client omits it, so a special calendar is never nameless.
        $name = trim((string) $request->string('name'));
        if ($name === '') {
            $name = $this->defaultSpecialName($kind, $country, $subdivision);
        }

        // Str::slug is empty for non-latin names (e.g. Cyrillic); keep a uri stem.
        $stem = Str::slug($name);
        if ($stem === '') {
            $stem = $kind;
        }

        $calendar = Calendar::create([
            'user_id' => $this->requireUser($request)->id,
            'name' => $name,
            'uri' => $stem.'-'.Str::lower(Str::random(4)),
            'color' => $request->filled('color') ? (string) $request->string('color') : null,
            'kind' => $kind,
            'country' => $country,
            'subdivision' => $subdivision,
            'timezone' => 'UTC',
            'synctoken' => 1,
        ]);

        $created = $generator->regenerate($calendar);

        return response()->json(['id' => $calendar->id, 'created' => $created], 201);
    }

    /**
     * Predefined name for a special calendar when the client omits one — the
     * localized base ("Birthdays"/"Public holidays"/"School holidays") plus the
     * region or country for the holiday kinds. Mirrors the SPA's client-side scheme.
     */
    private function defaultSpecialName(string $kind, ?string $country, ?string $subdivision): string
    {
        $key = match ($kind) {
            Calendar::KIND_BIRTHDAYS => 'calendar.ui.name_birthdays',
            Calendar::KIND_HOLIDAYS => 'calendar.ui.name_holidays',
            Calendar::KIND_SCHOOL_HOLIDAYS => 'calendar.ui.name_school_holidays',
            default => 'calendar.ui.default_calendar',
        };
        $translated = __($key);
        $base = is_string($translated) ? $translated : $key;
        $suffix = ($subdivision !== null && $subdivision !== '') ? $subdivision : $country;

        return ($suffix !== null && $suffix !== '') ? $base.' · '.$suffix : $base;
    }

    /**
     * Proxy the OpenHolidays country list so the SPA can populate the country
     * select under CSP connect-src 'self'. Cached a day to avoid hammering the API;
     * an upstream failure degrades to an empty list (the SPA keeps DE as default).
     */
    public function holidayCountries(Request $request, OpenHolidaysClient $client): JsonResponse
    {
        $lang = $this->uiLang();
        try {
            $countries = Cache::remember(
                "openholidays.countries.{$lang}",
                now()->addDay(),
                static fn (): array => $client->countries($lang),
            );
        } catch (Throwable) {
            $countries = [];
        }

        return response()->json(['countries' => $countries]);
    }

    /** Proxy the OpenHolidays subdivisions (Bundesländer/regions) for a country. */
    public function holidaySubdivisions(Request $request, OpenHolidaysClient $client): JsonResponse
    {
        $request->validate(['country' => ['required', 'string', 'regex:/^[A-Za-z-]{2,8}$/']]);
        $country = strtoupper((string) $request->string('country'));
        $lang = $this->uiLang();
        try {
            $subdivisions = Cache::remember(
                "openholidays.subdivisions.{$country}.{$lang}",
                now()->addDay(),
                static fn (): array => $client->subdivisions($country, $lang),
            );
        } catch (Throwable) {
            $subdivisions = [];
        }

        return response()->json(['subdivisions' => $subdivisions]);
    }

    /** The 2-letter uppercase UI language for OpenHolidays name localization. */
    private function uiLang(): string
    {
        $lang = strtoupper(substr(app()->getLocale(), 0, 2));

        return $lang !== '' ? $lang : 'EN';
    }

    /** Rebuild a special calendar's generated events from the current source. */
    public function regenerate(Request $request, Calendar $calendar, SpecialCalendarGenerator $generator): JsonResponse
    {
        $this->authorizeCalendar($calendar);
        // Only special calendars are generated; a normal one has nothing to rebuild.
        abort_unless($calendar->isSpecial(), 422);

        $created = $generator->regenerate($calendar);

        return response()->json(['ok' => true, 'created' => $created]);
    }

    /** Export a calendar (or all the user's calendars) as one .ics download. */
    public function export(Request $request): StreamedResponse
    {
        $calendarIds = Calendar::query()
            ->when($request->query('calendar'), fn ($q) => $q->whereKey($request->query('calendar')))
            ->pluck('id');

        return response()->streamDownload(function () use ($calendarIds): void {
            echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Ledgerline//Calendar//EN\r\n";
            CalendarEvent::whereIn('calendar_id', $calendarIds)->orderBy('dtstart')
                ->chunk(200, function ($chunk): void {
                    foreach ($chunk as $event) {
                        foreach ($this->veventBlocks($event->ics) as $block) {
                            echo $block."\r\n";
                        }
                    }
                });
            echo "END:VCALENDAR\r\n";
        }, 'calendar.ics', ['Content-Type' => 'text/calendar; charset=utf-8']);
    }

    /** Import an .ics (one or many VEVENTs) into a calendar; dedupe by UID. */
    public function import(Request $request, CalendarImporter $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:512000'],
            'calendar_id' => ['required', 'string'],
        ]);
        $calendar = Calendar::query()->ownedBy($this->requireUser($request)->id)
            ->findOrFail((string) $request->string('calendar_id'));
        // Special calendars are generated + read-only; refuse imports into them.
        abort_if($calendar->isSpecial(), 422);

        $result = $importer->import($calendar, (string) file_get_contents($request->file('file')->getRealPath()));

        return response()->json($result);
    }

    /**
     * Extract the VEVENT block(s) from a single-event VCALENDAR so the export can
     * concatenate them under one VCALENDAR wrapper.
     *
     * @return list<string>
     */
    private function veventBlocks(string $ics): array
    {
        if (! preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $ics, $m)) {
            return [];
        }

        return array_map(fn (string $b): string => rtrim($b, "\r\n"), $m[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating = false): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'calendar_id' => [$creating ? 'required' : 'sometimes', 'string'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            // Coordinate of the picked location (LOCATION stays the human address).
            'geo_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'geo_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'dtstart' => ['required', 'date'],
            'dtend' => ['nullable', 'date', 'after_or_equal:dtstart'],
            'all_day' => ['nullable', 'boolean'],
            'rrule' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value !== '' && ! $this->rruleParses($value)) {
                    $fail(__('validation.string', ['attribute' => $attribute]));
                }
            }],
            'status' => ['nullable', 'in:CONFIRMED,TENTATIVE,CANCELLED'],
            'alarm_minutes_before' => ['nullable', 'integer', 'between:0,40320'],
            'etag' => ['nullable', 'string', 'max:64'],
        ]);

        return $validated;
    }

    /** A syntactically valid RRULE parses back into a VEVENT without throwing. */
    private function rruleParses(string $rrule): bool
    {
        try {
            $ics = app(CalendarEventService::class)->build([
                'summary' => 'x', 'dtstart' => '2020-01-01T00:00:00Z', 'rrule' => $rrule,
            ]);

            return str_contains($ics, 'RRULE');
        } catch (Throwable) {
            return false;
        }
    }

    private function authorizeEvent(CalendarEvent $event, bool $write = false): void
    {
        // Owner OR a calendar shared to the caller (write requires editor). The
        // calendar is resolved via the access map (the belongsTo relation is
        // owner-scoped, so a shared calendar would otherwise read as null).
        $uid = (int) auth()->id();
        $ok = $write
            ? CalendarAccess::canWrite($uid, (string) $event->calendar_id)
            : CalendarAccess::canRead($uid, (string) $event->calendar_id);
        abort_unless($ok, 403);
    }

    /** The event's calendar resolved without the owner scope (may be shared). */
    private function eventCalendar(CalendarEvent $event): ?Calendar
    {
        return Calendar::query()->withoutGlobalScopes()->find($event->calendar_id);
    }

    private function authorizeCalendar(Calendar $calendar): void
    {
        abort_unless($calendar->user_id === auth()->id(), 403);
    }
}
