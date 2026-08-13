<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\GalleryPhoto;
use App\Models\User;
use App\Services\Ops\StorageHistory;
use App\Services\Ops\SystemStatus;
use App\Support\FilesUsage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Admin overview dashboard (can:manage-global-settings): server status, versions,
 * resource usage, health probes, worker queue, scheduler liveness, errors, backup
 * and fleet counts. Read-only aggregate; polled by the SPA. Container CPU/mem is
 * fetched separately via the docker agent (DockerController).
 */
class DashboardController extends Controller
{
    public function show(Request $request, SystemStatus $status, StorageHistory $history): JsonResponse
    {
        $snap = $status->snapshot();
        $files = FilesUsage::total();
        $gallery = (int) GalleryPhoto::withoutGlobalScopes()->sum('size');
        $database = is_int($snap['storage']['database'] ?? null) ? $snap['storage']['database'] : 0;

        return response()->json([
            'versions' => [
                'app' => $snap['version'],
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'health' => [
                'database' => $this->pingDb(),
                'cache' => $this->pingCache(),
                'queue_driver' => is_string(config('queue.default')) ? config('queue.default') : 'sync',
            ],
            'resources' => [
                'disk' => $snap['disk'],
                'storage' => [
                    'files' => $files,
                    'gallery' => $gallery,
                    'database' => $database,
                    'total' => $files + $gallery + $database,
                ],
                'trend' => $history->trend(30),
            ],
            'queue' => ['pending' => $this->queuePending(), 'failed' => $this->failedCount()],
            'scheduler' => [
                'lastRunAt' => $snap['scheduler']['lastRunAt'] ?? null,
                'tasks' => $this->scheduledTasks(),
            ],
            'errors' => $snap['errors'],
            'backup' => $snap['backup'],
            'counts' => [
                'users' => User::query()->count(),
                'admins' => User::query()->where('role', 'admin')->count(),
                'blocked_users' => User::query()->whereNotNull('blocked_at')->count(),
                'web_sessions' => (int) DB::table('sessions')->whereNotNull('user_id')->count(),
                'device_tokens' => (int) DB::table('personal_access_tokens')->where('tokenable_type', User::class)->count(),
                'blocked_ips' => BlockedIp::query()->count(),
            ],
        ]);
    }

    private function pingDb(): string
    {
        try {
            DB::connection()->getPdo();

            return 'up';
        } catch (\Throwable) {
            return 'down';
        }
    }

    private function pingCache(): string
    {
        try {
            Cache::put('__health_ping__', '1', 10);

            return Cache::get('__health_ping__') === '1' ? 'up' : 'down';
        } catch (\Throwable) {
            return 'down';
        }
    }

    private function queuePending(): int
    {
        try {
            return (int) Queue::size();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function failedCount(): int
    {
        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Scheduled tasks with their last run (from the cron:last:* cache).
     *
     * @return list<array{name:string, expression:string, lastAt:?string, lastOk:?bool}>
     */
    private function scheduledTasks(): array
    {
        $schedule = app(Schedule::class);
        $out = [];
        $seen = [];
        foreach ($schedule->events() as $event) {
            $cmd = (string) ($event->command ?? $event->getSummaryForDisplay());
            $name = trim(str_replace(["'".PHP_BINARY."'", PHP_BINARY, "'artisan'", 'artisan'], '', $cmd));
            $name = $name !== '' ? $name : 'closure';
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $last = Cache::get('cron:last:'.$name);
            $out[] = [
                'name' => $name,
                'expression' => (string) $event->expression,
                'lastAt' => is_array($last) && isset($last['at']) && is_string($last['at']) ? $last['at'] : null,
                'lastOk' => is_array($last) && isset($last['ok']) ? (bool) $last['ok'] : null,
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $out;
    }
}
