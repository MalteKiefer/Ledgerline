<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Opaque reminder registration for the zero-knowledge calendar. The client expands
 * recurrences locally and PUTs the FULL upcoming set of fire timestamps (never the
 * event title/content). This replaces the caller's future, undelivered reminders —
 * delivered rows (history) are left untouched. The scheduler pushes a generic
 * notification at each time. Metadata trade-off documented in the security register.
 *
 * Guard-agnostic (`$request->user()` via requireUser): mounted on both the web
 * (session) and /api/v1 (Sanctum abilities:device) routes.
 */
class CalendarReminderController extends Controller
{
    private const MAX = 2000;

    public function update(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $request->validate([
            'reminders' => ['present', 'array', 'max:'.self::MAX],
            'reminders.*.event_id' => ['required', 'string', 'max:64'],
            'reminders.*.recurrence_id' => ['nullable', 'string', 'max:32'],
            'reminders.*.remind_at' => ['required', 'date'],
        ]);

        $now = Carbon::now();
        /** @var array<int, array<string, mixed>> $incoming */
        $incoming = (array) $request->input('reminders');

        DB::transaction(function () use ($user, $incoming, $now): void {
            // Only future, undelivered rows are the client's to replace.
            CalendarReminder::query()
                ->where('user_id', $user->id)
                ->whereNull('delivered_at')
                ->where('remind_at', '>', $now)
                ->delete();

            $rows = [];
            foreach ($incoming as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $eid = $r['event_id'] ?? null;
                $ra = $r['remind_at'] ?? null;
                if (! is_string($eid) || ! is_string($ra)) {
                    continue;
                }
                try {
                    $at = Carbon::parse($ra);
                } catch (\Throwable) {
                    continue;
                }
                if ($at->lessThanOrEqualTo($now)) {
                    continue; // never register a fire time in the past
                }
                $rid = $r['recurrence_id'] ?? null;
                $rows[] = [
                    'user_id' => $user->id,
                    'event_id' => mb_substr($eid, 0, 64),
                    'recurrence_id' => is_string($rid) ? mb_substr($rid, 0, 32) : null,
                    'remind_at' => $at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                CalendarReminder::query()->insert($chunk);
            }
        });

        $count = CalendarReminder::query()
            ->where('user_id', $user->id)
            ->whereNull('delivered_at')
            ->where('remind_at', '>', $now)
            ->count();

        return response()->json(['count' => $count]);
    }
}
