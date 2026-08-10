<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DavChangeOperation;
use App\Models\Calendar;
use App\Models\CalendarTodo;
use App\Services\Calendar\CalendarChangeLog;
use App\Services\Calendar\CalendarTodoImporter;
use App\Services\Calendar\CalendarTodoPersister;
use App\Services\Calendar\CalendarTodoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Task (VTODO) UI backend (reload-free JSON), the task-list twin of
 * CalendarController. All queries are owner-scoped (CalendarTodo carries OwnsUserData,
 * so a foreign task resolves to 404 via route binding); writes go through the shared
 * persister so the ICS, denormalised columns and CalDAV sync token stay consistent.
 * Optimistic concurrency is the DAV-native etag (409 on mismatch). Completing a
 * recurring task rolls its DUE/DTSTART to the next occurrence; a non-recurring task
 * is completed terminally.
 */
class CalendarTodoController extends Controller
{
    /** List tasks (optionally by calendar / status / due), owner-scoped. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'calendar_id' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'],
            'due_before' => ['nullable', 'date'],
            'expand' => ['nullable', 'boolean'],
        ]);

        $status = strtoupper(trim((string) $request->string('status')));
        $expand = $request->boolean('expand');

        $todos = CalendarTodo::query()
            ->when($request->filled('calendar_id'), fn ($q) => $q->where('calendar_id', $request->string('calendar_id')))
            ->when($status === 'OPEN', fn ($q) => $q->whereNotIn('status', ['COMPLETED', 'CANCELLED']))
            ->when(in_array($status, CalendarTodoService::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('due_before'), fn ($q) => $q
                ->whereNotNull('due')
                ->where('due', '<=', CarbonImmutable::parse((string) $request->string('due_before'))->utc()))
            ->orderBy('sort_order')
            ->orderByRaw('due is null')
            ->orderBy('due')
            ->orderBy('created_at')
            ->get();

        $colors = $this->colorMap();
        $service = app(CalendarTodoService::class);
        $now = CarbonImmutable::now()->utc();

        return response()->json([
            'todos' => $todos->map(fn (CalendarTodo $t): array => $this->present($t, $colors, $expand ? $service : null, $now))->all(),
        ]);
    }

    /** Full parsed editor data + ids + etag (mirror CalendarController@show). */
    public function show(Request $request, CalendarTodo $todo, CalendarTodoService $service): JsonResponse
    {
        return response()->json(array_merge(
            $service->parse($todo->ics),
            [
                'id' => $todo->id,
                'calendar' => $todo->calendar_id,
                'sort_order' => $todo->sort_order,
                'etag' => $todo->etag,
            ],
        ));
    }

    public function store(Request $request, CalendarTodoService $service, CalendarTodoPersister $persister): JsonResponse
    {
        $data = $this->validated($request, creating: true);
        $calendar = Calendar::query()->ownedBy($this->requireUser($request)->id)
            ->findOrFail((string) $request->string('calendar_id'));
        // Tasks live only in a VTODO calendar; refuse event/special calendars.
        abort_unless($calendar->isTaskList(), 422);

        $ics = $service->build($data);
        $todo = $persister->persistNew($calendar, Str::uuid().'.ics', $ics);

        return response()->json(['id' => $todo->id], 201);
    }

    public function update(Request $request, CalendarTodo $todo, CalendarTodoService $service, CalendarTodoPersister $persister): JsonResponse
    {
        $data = $this->validated($request);

        // Optimistic concurrency via the DAV-native etag: reject a stale write.
        $etag = trim((string) $request->string('etag'));
        if ($etag !== '' && $etag !== $todo->etag) {
            return response()->json(['error' => 'etag_conflict', 'etag' => $todo->etag], 409);
        }

        // Merge into the stored ICS so unmodelled properties and the UID survive.
        $ics = $service->rebuild($todo->ics, $data, (int) $todo->sequence + 1);
        $updated = $persister->persistUpdate($todo, $ics);

        return response()->json(['ok' => true, 'etag' => $updated->etag]);
    }

    public function destroy(CalendarTodo $todo, CalendarChangeLog $changes): JsonResponse
    {
        $calendar = $todo->calendar;
        $uri = $todo->uri;
        $todo->delete();
        if ($calendar !== null) {
            $changes->recordTodo($calendar, $uri, DavChangeOperation::Deleted);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Complete a task. A recurring task rolls its DUE/DTSTART to the next occurrence
     * and stays NEEDS-ACTION (the series continues); a non-recurring task — or one
     * whose series is exhausted — is completed terminally (STATUS=COMPLETED).
     */
    public function complete(Request $request, CalendarTodo $todo, CalendarTodoService $service, CalendarTodoPersister $persister): JsonResponse
    {
        $rolled = $service->rollForward($todo->ics);
        $ics = $rolled ?? $service->markCompleted($todo->ics);
        $updated = $persister->persistUpdate($todo, $ics);

        return response()->json([
            'ok' => true,
            'rolled' => $rolled !== null,
            'todo' => $this->present($updated, $this->colorMap(), null, CarbonImmutable::now()->utc()),
        ]);
    }

    /** Re-open a task: STATUS=NEEDS-ACTION, PERCENT-COMPLETE=0, drop COMPLETED. */
    public function uncomplete(Request $request, CalendarTodo $todo, CalendarTodoService $service, CalendarTodoPersister $persister): JsonResponse
    {
        $updated = $persister->persistUpdate($todo, $service->markUncompleted($todo->ics));

        return response()->json([
            'ok' => true,
            'todo' => $this->present($updated, $this->colorMap(), null, CarbonImmutable::now()->utc()),
        ]);
    }

    /**
     * Persist a manual ordering. Accepts an ordered list of task ids; only the
     * caller's own tasks are touched (foreign ids are ignored by the owner scope).
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array', 'max:5000'],
            'order.*' => ['string'],
        ]);
        $ids = array_values(array_filter((array) $request->input('order'), 'is_string'));
        foreach ($ids as $index => $id) {
            CalendarTodo::query()->whereKey($id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }

    /** Export the user's tasks (or one task list) as one .ics download. */
    public function export(Request $request): StreamedResponse
    {
        $todos = CalendarTodo::query()
            ->when($request->filled('calendar_id'), fn ($q) => $q->where('calendar_id', $request->string('calendar_id')))
            ->orderBy('due')
            ->get(['ics']);

        return response()->streamDownload(function () use ($todos): void {
            echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Ledgerline//Calendar//EN\r\n";
            foreach ($todos as $todo) {
                foreach ($this->vtodoBlocks($todo->ics) as $block) {
                    echo $block."\r\n";
                }
            }
            echo "END:VCALENDAR\r\n";
        }, 'tasks.ics', ['Content-Type' => 'text/calendar; charset=utf-8']);
    }

    /** Import an .ics (one or many VTODOs) into a task-list calendar; dedupe by UID. */
    public function import(Request $request, CalendarTodoImporter $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:512000'],
            'calendar_id' => ['required', 'string'],
        ]);
        $calendar = Calendar::query()->ownedBy($this->requireUser($request)->id)
            ->findOrFail((string) $request->string('calendar_id'));
        abort_unless($calendar->isTaskList(), 422);

        $file = $request->file('file');
        $path = is_array($file) ? null : $file?->getRealPath();
        $result = $importer->import($calendar, $path !== null && $path !== false ? (string) file_get_contents($path) : '');

        return response()->json($result);
    }

    /**
     * Owner-scoped calendar colour map (calendar_id → color) for task rendering.
     *
     * @return Collection<string, string|null>
     */
    private function colorMap(): Collection
    {
        /** @var Collection<string, string|null> $map */
        $map = Calendar::query()->get(['id', 'color'])->pluck('color', 'id');

        return $map;
    }

    /**
     * @param  Collection<string, string|null>  $colors
     * @return array<string, mixed>
     */
    private function present(CalendarTodo $todo, Collection $colors, ?CalendarTodoService $service, CarbonImmutable $now): array
    {
        $color = $colors[$todo->calendar_id] ?? null;

        $row = [
            'id' => $todo->id,
            'calendar' => $todo->calendar_id,
            'uid' => $todo->uid,
            'summary' => $todo->summary,
            'description' => $todo->description,
            'status' => $todo->status,
            'priority' => $todo->priority,
            'percent_complete' => $todo->percent_complete,
            'due' => $todo->due?->toIso8601ZuluString(),
            'dtstart' => $todo->dtstart?->toIso8601ZuluString(),
            'completed_at' => $todo->completed_at?->toIso8601ZuluString(),
            'all_day' => $todo->all_day,
            'rrule' => $todo->rrule,
            'categories' => $todo->categories ?? [],
            'related_to' => $todo->related_to,
            'sequence' => $todo->sequence,
            'sort_order' => $todo->sort_order,
            'color' => is_string($color) ? $color : null,
            'etag' => $todo->etag,
        ];

        if ($service !== null) {
            $row['next_due'] = $service->nextOccurrences($todo, $now, 1)[0] ?? null;
        }

        return $row;
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
            'dtstart' => ['nullable', 'date'],
            'due' => ['nullable', 'date'],
            'completed' => ['nullable', 'date'],
            'all_day' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:'.implode(',', CalendarTodoService::STATUSES)],
            'priority' => ['nullable', 'integer', 'between:0,9'],
            'percent_complete' => ['nullable', 'integer', 'between:0,100'],
            'rrule' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value !== '' && ! $this->rruleParses($value)) {
                    $fail(__('validation.string', ['attribute' => $attribute]));
                }
            }],
            'categories' => ['nullable', 'array', 'max:100'],
            'categories.*' => ['string', 'max:100'],
            'related_to' => ['nullable', 'string', 'max:255'],
            'alarm_minutes_before' => ['nullable', 'integer', 'between:0,40320'],
            'etag' => ['nullable', 'string', 'max:64'],
        ]);

        return $validated;
    }

    /** A syntactically valid RRULE parses back into a VTODO without throwing. */
    private function rruleParses(string $rrule): bool
    {
        try {
            $ics = app(CalendarTodoService::class)->build([
                'summary' => 'x', 'due' => '2020-01-01T00:00:00Z', 'rrule' => $rrule,
            ]);

            return str_contains($ics, 'RRULE');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Extract the VTODO block(s) from a single-task VCALENDAR so the export can
     * concatenate them under one VCALENDAR wrapper.
     *
     * @return list<string>
     */
    private function vtodoBlocks(string $ics): array
    {
        if (! preg_match_all('/BEGIN:VTODO.*?END:VTODO/s', $ics, $m)) {
            return [];
        }

        return array_map(fn (string $b): string => rtrim($b, "\r\n"), $m[0]);
    }
}
