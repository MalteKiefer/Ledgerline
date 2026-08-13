<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ErrorEvent;
use App\Providers\AppServiceProvider;
use App\Services\Ops\StorageHistory;
use App\Services\Ops\SystemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * JSON mirror of the web Settings/SystemController (admin, read-only): the
 * operational status snapshot (queue/storage/backup/scheduler/disk), scheduled
 * task liveness, the in-app error log and recent audit trail. Admin-gated at the
 * route level. Metadata only — no secrets.
 */
class SystemController extends Controller
{
    public function show(Schedule $schedule, SystemStatus $status, StorageHistory $history): JsonResponse
    {
        // Keep at least today's data point so the trend is never empty before
        // the first scheduled snapshot (idempotent — one row per day).
        $history->capture();

        $tasks = collect($schedule->events())
            ->map(function ($event): array {
                $name = AppServiceProvider::cronName($event);
                $last = Cache::get(AppServiceProvider::cronRunKey($name));
                $last = is_array($last) ? $last : [];

                return [
                    'name' => $name,
                    'expression' => (string) $event->expression,
                    'lastAt' => $last['at'] ?? null,
                    'lastOk' => $last['ok'] ?? null,
                ];
            })
            ->unique('name')
            ->sortBy('name')
            ->values()
            ->all();

        $errors = ErrorEvent::orderByRaw('resolved_at is null desc')
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->get()
            ->map(fn (ErrorEvent $e): array => [
                'id' => $e->getKey(),
                'level' => $e->level,
                'exception' => $e->exception,
                'message' => $e->message,
                'file' => $e->file,
                'line' => $e->line,
                'count' => $e->count,
                'first_seen_at' => $this->iso($e->first_seen_at),
                'last_seen_at' => $this->iso($e->last_seen_at),
                'resolved_at' => $this->iso($e->resolved_at),
            ])
            ->all();

        $audit = AuditLog::with('actor')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (AuditLog $a): array => [
                'id' => $a->id,
                'action' => $a->action,
                'actor' => $a->actor?->name,
                'ip' => $a->ip,
                'meta' => $a->meta,
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->all();

        return response()->json([
            'tasks' => $tasks,
            'status' => $status->snapshot(),
            'trend' => $history->trend(30),
            'errors' => $errors,
            'audit' => $audit,
        ]);
    }

    /** Mark a recorded error as resolved (it reappears if it recurs). */
    public function resolveError(ErrorEvent $error): JsonResponse
    {
        $error->update(['resolved_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** Convert a mixed datetime attribute to an ISO-8601 string, or null. */
    private function iso(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->toIso8601String();
        }

        return null;
    }
}
