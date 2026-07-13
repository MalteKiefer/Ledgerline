<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Models\BackupRun;
use App\Models\ErrorEvent;
use App\Models\FileBlob;
use App\Models\GalleryBlob;
use App\Providers\AppServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Collects operational health signals — queue depth, storage use per module,
 * error counts, last backup, scheduler liveness — from a single place so the
 * System settings card and the Prometheus /metrics endpoint never drift.
 */
class SystemStatus
{
    /**
     * @return array{
     *   version: string,
     *   queue: array{pending: int, failed: int},
     *   storage: array{files: int, gallery: int, database: int, total: int},
     *   errors: array{unresolved: int, total: int, lastAt: ?string},
     *   backup: array{lastSuccessAt: ?string},
     *   scheduler: array{lastRunAt: ?string},
     *   disk: array{free: int, total: int}
     * }
     */
    public function snapshot(): array
    {
        $files = (int) FileBlob::sum('size');
        $gallery = (int) GalleryBlob::sum('size');
        $database = $this->databaseBytes();

        $lastError = ErrorEvent::whereNull('resolved_at')->max('last_seen_at');
        $lastBackup = BackupRun::where('status', 'success')->max('finished_at');
        $lastVerified = BackupRun::whereNotNull('verified_at')->orderByDesc('verified_at')->first();

        return [
            'version' => (string) config('app.version'),
            'queue' => [
                'pending' => $this->tableCount('jobs'),
                'failed' => $this->tableCount('failed_jobs'),
            ],
            'storage' => [
                'files' => $files,
                'gallery' => $gallery,
                'database' => $database,
                'total' => $files + $gallery + $database,
            ],
            'errors' => [
                'unresolved' => ErrorEvent::whereNull('resolved_at')->count(),
                'total' => (int) ErrorEvent::sum('count'),
                'lastAt' => $lastError ? CarbonImmutable::parse($lastError)->toIso8601String() : null,
            ],
            'backup' => [
                'lastSuccessAt' => $lastBackup ? CarbonImmutable::parse($lastBackup)->toIso8601String() : null,
                'lastVerifyStatus' => $lastVerified?->verify_status,
                'lastVerifyAt' => $lastVerified?->verified_at ? CarbonImmutable::parse($lastVerified->verified_at)->toIso8601String() : null,
            ],
            'scheduler' => [
                'lastRunAt' => $this->schedulerLastRun(),
            ],
            'disk' => [
                'free' => (int) (@disk_free_space(storage_path()) ?: 0),
                'total' => (int) (@disk_total_space(storage_path()) ?: 0),
            ],
        ];
    }

    private function tableCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** On-disk size of the application database (driver-aware, best effort). */
    private function databaseBytes(): int
    {
        try {
            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver");

            return match ($driver) {
                'pgsql' => (int) DB::selectOne('select pg_database_size(current_database()) as size')?->size,
                'mysql', 'mariadb' => (int) (DB::selectOne(
                    'select sum(data_length + index_length) as size from information_schema.tables where table_schema = database()'
                )?->size ?? 0),
                'sqlite' => (int) (@filesize((string) config("database.connections.{$connection}.database")) ?: 0),
                default => 0,
            };
        } catch (Throwable) {
            return 0;
        }
    }

    /** Latest recorded run across all scheduled maintenance tasks. */
    private function schedulerLastRun(): ?string
    {
        try {
            $latest = null;
            foreach (app(Schedule::class)->events() as $event) {
                $name = AppServiceProvider::cronName($event);
                $run = Cache::get(AppServiceProvider::cronRunKey($name));
                $at = $run['at'] ?? null;
                if ($at !== null && ($latest === null || $at > $latest)) {
                    $latest = $at;
                }
            }

            return $latest ? CarbonImmutable::parse($latest)->toIso8601String() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
