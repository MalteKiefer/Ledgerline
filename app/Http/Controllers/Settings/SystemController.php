<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * System / maintenance overview: shows every scheduled task and when it last
 * ran (recorded by the scheduler) so a silently dead cron is visible in-app.
 */
class SystemController extends Controller
{
    public function edit(Schedule $schedule): View
    {
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

        return view('settings.system.index', ['tasks' => $tasks]);
    }
}
