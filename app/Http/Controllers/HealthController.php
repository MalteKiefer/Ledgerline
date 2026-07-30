<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HealthEntry;
use App\Models\HealthFast;
use App\Models\HealthProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Plaintext-relational Health (pivot). Per-row CRUD in DB transactions,
 * owner-scoped by each model's global scope. Health values are GDPR Art. 9
 * special-category data: the readings/birthdate/notes columns carry a Laravel
 * `encrypted` cast (APP_KEY, kept out of DB dumps), while metric/ts/height stay
 * plaintext so the server can sort/filter/group for the client-side charts.
 *
 * The single-active-fast invariant is enforced by the DB (partial unique index),
 * so a concurrent start on two devices can never leave two active fasts.
 */
class HealthController extends Controller
{
    /** The six tracked metrics (bp carries systolic in v, diastolic in v2). */
    private const METRICS = 'weight,bp,pulse,spo2,temp,glucose';

    /** The health page: server-render the shell + inline the current data. */
    public function page(): View
    {
        return view('health.index', [
            'profile' => HealthProfile::query()->first(),
            'entries' => HealthEntry::query()->orderBy('ts')->get(),
            'fasts' => HealthFast::query()->orderByDesc('start_at')->get(),
        ]);
    }

    /** Combined snapshot (profile + entries + fasts) for API / client refresh. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'profile' => HealthProfile::query()->first(),
            'entries' => HealthEntry::query()->orderBy('ts')->get(),
            'fasts' => HealthFast::query()->orderByDesc('start_at')->get(),
        ]);
    }

    // --- Profile ---------------------------------------------------------

    public function saveProfile(Request $request): JsonResponse
    {
        $request->validate([
            'birthdate' => ['nullable', 'date'],
            'height_cm' => ['nullable', 'integer', 'min:1', 'max:300'],
            'sex' => ['nullable', 'in:male,female,other,'],
            'weight_goal_kg' => ['nullable', 'numeric'],
        ]);

        $payload = [
            'birthdate' => $request->filled('birthdate') ? $request->string('birthdate')->value() : null,
            'height_cm' => $request->filled('height_cm') ? $request->integer('height_cm') : null,
            'sex' => $request->filled('sex') ? $request->string('sex')->value() : null,
            'weight_goal_kg' => $request->filled('weight_goal_kg') ? $this->scalarString($request->input('weight_goal_kg')) : null,
        ];

        $uid = $this->requireUser($request)->id;
        $profile = DB::transaction(
            fn (): HealthProfile => HealthProfile::updateOrCreate(['user_id' => $uid], $payload)
        );

        return response()->json(['profile' => $profile]);
    }

    // --- Entries ---------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function entryRules(): array
    {
        return [
            'metric' => ['required', 'string', 'in:'.self::METRICS],
            'ts' => ['required', 'date'],
            'v' => ['required'],
            'v2' => ['nullable'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{metric: string, ts: Carbon|null, v: string, v2: string|null, note: string|null}
     */
    private function entryPayload(Request $request): array
    {
        return [
            'metric' => $request->string('metric')->value(),
            'ts' => $request->date('ts'),
            'v' => $this->scalarString($request->input('v')),
            'v2' => $request->filled('v2') ? $this->scalarString($request->input('v2')) : null,
            'note' => $request->filled('note') ? $request->string('note')->value() : null,
        ];
    }

    /** Entry listing, oldest first (chart order). Optional ?metric= filter. */
    public function entries(Request $request): JsonResponse
    {
        $metric = $request->string('metric')->value();
        $entries = HealthEntry::query()
            ->when($metric !== '', fn ($q) => $q->where('metric', $metric))
            ->orderBy('ts')
            ->get();

        return response()->json(['entries' => $entries]);
    }

    public function storeEntry(Request $request): JsonResponse
    {
        $request->validate($this->entryRules());
        $payload = $this->entryPayload($request);
        $entry = DB::transaction(fn (): HealthEntry => HealthEntry::create($payload));

        return response()->json(['entry' => $entry], 201);
    }

    /** Update one entry with optimistic concurrency (stale version → 409). */
    public function updateEntry(Request $request, HealthEntry $entry): JsonResponse
    {
        $request->validate($this->entryRules() + ['version' => ['sometimes', 'integer', 'min:0']]);
        $payload = $this->entryPayload($request);
        $expected = $request->has('version') ? $request->integer('version') : null;

        $result = DB::transaction(function () use ($entry, $payload, $expected): HealthEntry|bool|null {
            $fresh = HealthEntry::query()->lockForUpdate()->find($entry->id);
            if ($fresh === null) {
                return null;
            }
            if ($expected !== null && $fresh->version !== $expected) {
                return false; // conflict sentinel
            }
            $fresh->fill($payload);
            $fresh->version = $fresh->version + 1;
            $fresh->save();

            return $fresh;
        });

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = HealthEntry::query()->find($entry->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['entry' => $result]);
    }

    public function destroyEntry(HealthEntry $entry): JsonResponse
    {
        $entry->delete();

        return response()->json(['ok' => true]);
    }

    // --- Fasts -----------------------------------------------------------

    /** All fasts, newest first. */
    public function fasts(Request $request): JsonResponse
    {
        return response()->json(['fasts' => HealthFast::query()->orderByDesc('start_at')->get()]);
    }

    /** The current active fast (end_at null), or null. */
    public function activeFast(Request $request): JsonResponse
    {
        return response()->json(['fast' => HealthFast::query()->whereNull('end_at')->first()]);
    }

    /**
     * Start a fast now. The partial unique index (user_id WHERE end_at IS NULL)
     * makes a second concurrent active fast impossible: catch the violation and
     * return 409 with the fast that is actually running.
     */
    public function startFast(Request $request): JsonResponse
    {
        $request->validate([
            'target_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $payload = [
            'start_at' => now(),
            'end_at' => null,
            'target_hours' => $request->integer('target_hours'),
            'note' => $request->filled('note') ? $request->string('note')->value() : null,
        ];

        try {
            $fast = DB::transaction(fn (): HealthFast => HealthFast::create($payload));
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'error' => 'active_fast_exists',
                'fast' => HealthFast::query()->whereNull('end_at')->first(),
            ], 409);
        }

        return response()->json(['fast' => $fast], 201);
    }

    /** Stop a fast (set end_at now). */
    public function stopFast(HealthFast $fast): JsonResponse
    {
        if ($fast->end_at === null) {
            $fast->end_at = now();
            $fast->version = $fast->version + 1;
            $fast->save();
        }

        return response()->json(['fast' => $fast]);
    }

    /**
     * Edit a fast (start/end/target/note) with optimistic concurrency. Clearing
     * end_at while another fast is active hits the partial unique index → 409.
     */
    public function updateFast(Request $request, HealthFast $fast): JsonResponse
    {
        $request->validate([
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['nullable', 'date'],
            'target_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'note' => ['nullable', 'string', 'max:5000'],
            'version' => ['sometimes', 'integer', 'min:0'],
        ]);

        $payload = [];
        if ($request->has('start_at')) {
            $payload['start_at'] = $request->date('start_at');
        }
        if ($request->has('end_at')) {
            $payload['end_at'] = $request->filled('end_at') ? $request->date('end_at') : null;
        }
        if ($request->has('target_hours')) {
            $payload['target_hours'] = $request->integer('target_hours');
        }
        if ($request->has('note')) {
            $payload['note'] = $request->filled('note') ? $request->string('note')->value() : null;
        }
        $expected = $request->has('version') ? $request->integer('version') : null;

        try {
            $result = DB::transaction(function () use ($fast, $payload, $expected): HealthFast|bool|null {
                $fresh = HealthFast::query()->lockForUpdate()->find($fast->id);
                if ($fresh === null) {
                    return null;
                }
                if ($expected !== null && $fresh->version !== $expected) {
                    return false; // conflict sentinel
                }
                $fresh->fill($payload);
                $fresh->version = $fresh->version + 1;
                $fresh->save();

                return $fresh;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'error' => 'active_fast_exists',
                'fast' => HealthFast::query()->whereNull('end_at')->first(),
            ], 409);
        }

        if ($result === null) {
            abort(404);
        }
        if ($result === false) {
            $current = HealthFast::query()->find($fast->id);

            return response()->json(['error' => 'version_conflict', 'version' => (int) ($current?->version ?? 0)], 409);
        }

        return response()->json(['fast' => $result]);
    }

    public function destroyFast(HealthFast $fast): JsonResponse
    {
        $fast->delete();

        return response()->json(['ok' => true]);
    }

    /** Coerce a scalar request value to a string; non-scalars become ''. */
    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
