<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\CalendarObject;
use App\Services\Calendar\CalendarFeedFetcher;
use App\Services\Calendar\CalendarImporter;
use App\Services\Calendar\CalendarWriter;
use App\Services\Calendar\ICalService;
use App\Services\Calendar\SubscriptionRefresher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Sabre\VObject\Reader;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Calendar UI backend (reload-free JSON). All queries are scoped to the
 * authenticated user's calendars; writes go through CalendarWriter so the ICS,
 * denormalised columns and CalDAV sync token stay consistent.
 */
class CalendarController extends Controller
{
    public function index(): View
    {
        return view('calendar.index');
    }

    /** Calendars + event instances overlapping the [from, to] window. */
    public function data(Request $request, ICalService $ical): JsonResponse
    {
        $userId = $request->user()->id;
        // The 'tasks' calendar is a VTODO mirror exposed over CalDAV only.
        $calendars = Calendar::where('user_id', $userId)->where('uri', '!=', 'tasks')->orderBy('name')->get();

        $from = $this->parseDate($request->query('from'), '-1 month');
        $to = $this->parseDate($request->query('to'), '+2 months');

        $events = [];
        $objects = CalendarObject::whereIn('calendar_id', $calendars->pluck('id'))
            ->where('component', 'VEVENT')
            ->where(fn ($q) => $q
                ->whereNotNull('rrule')
                ->orWhere(fn ($w) => $w->where('starts_at', '<=', $to)->where(fn ($e) => $e
                    ->where('ends_at', '>=', $from)->orWhereNull('ends_at'))))
            ->get();

        $colors = $calendars->pluck('color', 'id');
        foreach ($objects as $object) {
            foreach ($ical->expand($object->ics, $from, $to) as $i => $instance) {
                $events[] = [
                    'id' => $object->id,
                    'instance' => $i,
                    'calendar_id' => $object->calendar_id,
                    'title' => $object->summary,
                    'start' => $instance['start'],
                    'end' => $instance['end'],
                    'all_day' => $object->all_day,
                    'recurring' => $object->rrule !== null,
                    'color' => $colors[$object->calendar_id] ?? '#3366cc',
                ];
            }
        }

        return response()->json([
            'calendars' => $calendars->map(fn (Calendar $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color ?: '#3366cc',
                'read_only' => $c->isReadOnly(),
                'subscription_url' => $c->subscription_url,
            ]),
            'events' => $events,
        ]);
    }

    /** Full ICS-derived detail for the event editor. */
    public function show(CalendarObject $object, ICalService $ical): JsonResponse
    {
        $this->authorizeObject($object);

        return response()->json(array_merge($this->detail($object, $ical), [
            'id' => $object->id,
            'calendar_id' => $object->calendar_id,
        ]));
    }

    public function store(Request $request, CalendarWriter $writer): JsonResponse
    {
        $data = $this->validated($request);
        $calendar = $this->ownedCalendar($request, $data['calendar_id']);
        abort_if($calendar->isReadOnly(), 403);

        $object = $writer->create($calendar, $data);

        return response()->json(['id' => $object->id], 201);
    }

    public function update(Request $request, CalendarObject $object, CalendarWriter $writer): JsonResponse
    {
        $this->authorizeObject($object);
        abort_if($object->calendar->isReadOnly(), 403);
        $data = $this->validated($request);

        // Allow moving the event to another owned, writable calendar.
        if ($data['calendar_id'] !== $object->calendar_id) {
            $target = $this->ownedCalendar($request, $data['calendar_id']);
            abort_if($target->isReadOnly(), 403);
            $writer->delete($object);

            return response()->json(['id' => $writer->create($target, $data)->id]);
        }

        $writer->update($object, $data);

        return response()->json(['ok' => true]);
    }

    public function destroy(CalendarObject $object, CalendarWriter $writer): JsonResponse
    {
        $this->authorizeObject($object);
        abort_if($object->calendar->isReadOnly(), 403);
        $writer->delete($object);

        return response()->json(['ok' => true]);
    }

    /** Export a calendar (or all the user's events) as one .ics download. */
    public function export(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;
        $calendars = Calendar::where('user_id', $userId)
            ->when($request->query('calendar'), fn ($q) => $q->where('id', $request->query('calendar')))
            ->pluck('id');

        return response()->streamDownload(function () use ($calendars): void {
            echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Ledgerline//Calendar//EN\r\n";
            CalendarObject::whereIn('calendar_id', $calendars)->orderBy('starts_at')->chunk(200, function ($chunk): void {
                foreach ($chunk as $object) {
                    foreach ($this->veventBlocks($object->ics) as $block) {
                        echo $block."\r\n";
                    }
                }
            });
            echo "END:VCALENDAR\r\n";
        }, 'calendar.ics', ['Content-Type' => 'text/calendar; charset=utf-8']);
    }

    /** One-off import of a public ICS URL into a writable calendar (SSRF-guarded). */
    public function importUrl(Request $request, CalendarFeedFetcher $fetcher, CalendarImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'calendar_id' => ['required', 'string'],
        ]);
        $calendar = $this->ownedCalendar($request, $data['calendar_id']);
        abort_if($calendar->isReadOnly(), 403);

        try {
            $body = $fetcher->fetch($data['url']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($importer->import($calendar, $body));
    }

    /** Subscribe to a remote ICS feed as a new read-only, auto-refreshing calendar. */
    public function subscribe(Request $request, SubscriptionRefresher $refresher): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:9'],
            'refresh_minutes' => ['nullable', 'integer', 'min:15', 'max:10080'],
        ]);

        $calendar = Calendar::create([
            'user_id' => $request->user()->id,
            'uri' => (string) Str::uuid(),
            'name' => $data['name'],
            'color' => $data['color'] ?? '#7c3aed',
            'components' => ['VEVENT'],
            'synctoken' => 1,
            'subscription_url' => $data['url'],
            'read_only' => true,
            'refresh_minutes' => $data['refresh_minutes'] ?? 60,
        ]);

        try {
            $refresher->refresh($calendar);
        } catch (\RuntimeException $e) {
            $calendar->delete();

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $calendar->id], 201);
    }

    /** Import an .ics (one or many events) into a calendar; dedupe by UID. */
    public function import(Request $request, CalendarImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'calendar_id' => ['required', 'string'],
        ]);
        $calendar = $this->ownedCalendar($request, $data['calendar_id']);
        abort_if($calendar->isReadOnly(), 403);

        $result = $importer->import($calendar, (string) file_get_contents($request->file('file')->getRealPath()));

        return response()->json($result);
    }

    public function storeCalendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:9'],
        ]);

        $calendar = Calendar::create([
            'user_id' => $request->user()->id,
            'uri' => (string) Str::uuid(),
            'name' => $data['name'],
            'color' => $data['color'] ?? '#3366cc',
            'components' => ['VEVENT'],
            'synctoken' => 1,
        ]);

        return response()->json(['id' => $calendar->id], 201);
    }

    public function updateCalendar(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorizeCalendar($calendar);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:9'],
        ]);
        $calendar->forceFill(['name' => $data['name'], 'color' => $data['color'] ?? $calendar->color])->save();

        return response()->json(['ok' => true]);
    }

    public function destroyCalendar(Calendar $calendar): JsonResponse
    {
        $this->authorizeCalendar($calendar);
        abort_if(in_array($calendar->uri, ['default', 'tasks'], true), 422, 'This calendar cannot be deleted.');
        $calendar->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Serialize each inner component (VEVENT/VTODO/VTIMEZONE) of a stored
     * VCALENDAR so it can be concatenated into one export document.
     *
     * @return list<string>
     */
    private function veventBlocks(string $ics): array
    {
        try {
            $vcal = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (\Throwable) {
            return [];
        }
        $blocks = [];
        foreach ($vcal->getComponents() as $component) {
            $blocks[] = rtrim($component->serialize(), "\r\n");
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(CalendarObject $object, ICalService $ical): array
    {
        $vcal = Reader::read($object->ics, Reader::OPTION_FORGIVING);
        $vevent = $vcal->VEVENT[0] ?? null;

        return [
            'summary' => $object->summary,
            'start' => $object->starts_at?->format('Y-m-d\TH:i'),
            'end' => $object->ends_at?->format('Y-m-d\TH:i'),
            'all_day' => $object->all_day,
            'location' => isset($vevent->LOCATION) ? (string) $vevent->LOCATION : null,
            'description' => isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : null,
            'rrule' => $object->rrule,
            'reminder_minutes' => $object->alarm_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'calendar_id' => ['required', 'string'],
            'summary' => ['required', 'string', 'max:255'],
            'start' => ['required', 'string', 'max:32'],
            'end' => ['nullable', 'string', 'max:32'],
            'all_day' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'rrule' => ['nullable', 'string', 'max:255'],
            'reminder_minutes' => ['nullable', 'integer', 'min:0', 'max:40320'],
        ]);
    }

    private function parseDate(mixed $value, string $fallback): \DateTimeImmutable
    {
        try {
            return is_string($value) && $value !== '' ? new \DateTimeImmutable($value) : new \DateTimeImmutable($fallback);
        } catch (\Throwable) {
            return new \DateTimeImmutable($fallback);
        }
    }

    private function ownedCalendar(Request $request, string $id): Calendar
    {
        return Calendar::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function authorizeCalendar(Calendar $calendar): void
    {
        abort_unless($calendar->user_id === auth()->id(), 403);
    }

    private function authorizeObject(CalendarObject $object): void
    {
        abort_unless($object->calendar->user_id === auth()->id(), 403);
    }
}
