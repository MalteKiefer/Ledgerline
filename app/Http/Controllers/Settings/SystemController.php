<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ErrorEvent;
use App\Providers\AppServiceProvider;
use App\Services\Ops\StorageHistory;
use App\Services\Ops\SystemStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

/**
 * System / maintenance overview: operational status (queue, storage, backups,
 * scheduler liveness), the in-app error log, and every scheduled task with when
 * it last ran — so a silently dead cron or a recurring fault is visible in-app.
 */
class SystemController extends Controller
{
    public function edit(Schedule $schedule, SystemStatus $status, StorageHistory $history): View
    {
        // Keep at least today's data point so the trend is never empty before
        // the first scheduled snapshot (idempotent — one row per day).
        $history->capture();

        $tasks = collect($schedule->events())
            ->map(function ($event): array {
                $name = AppServiceProvider::cronName($event);
                $last = Cache::get(AppServiceProvider::cronRunKey($name));

                return [
                    'name' => $name,
                    'expression' => (string) ($event->expression ?? ''),
                    'lastAt' => $last['at'] ?? null,
                    'lastOk' => $last['ok'] ?? null,
                ];
            })
            ->unique('name')
            ->sortBy('name')
            ->values()
            ->all();

        return view('settings.system.index', [
            'tasks' => $tasks,
            'status' => $status->snapshot(),
            'trend' => $history->trend(30),
            'errors' => ErrorEvent::orderByRaw('resolved_at is null desc')
                ->orderByDesc('last_seen_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /** Mark a recorded error as resolved (it reappears if it recurs). */
    public function resolveError(ErrorEvent $error): RedirectResponse
    {
        $error->update(['resolved_at' => now()]);

        return back()->with('status', __('settings.system_error_resolved'));
    }
}
