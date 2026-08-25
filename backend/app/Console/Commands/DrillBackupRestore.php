<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppSettings;
use App\Models\BackupJob;
use App\Services\Backup\RestoreDrill;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Console\Command;

/**
 * Weekly restore drill: actually replay the newest backup and compare a random
 * sample of mirrored blobs against the live copies. Distinct from the daily
 * `backups:verify`, which only proves the archive is readable — this proves it
 * restores. Runs weekly because it downloads and rehashes real data.
 *
 * Exits non-zero on any mismatch or error so a scheduler/CI wrapper notices.
 */
class DrillBackupRestore extends Command
{
    protected $signature = 'backup:drill {--job= : Drill only this job (id or name)} {--files=10 : Mirrored blobs to sample per source}';

    protected $description = 'Restore the latest backup into a throwaway target and verify sampled blobs against the live copies';

    public function handle(RestoreDrill $drill, ChannelNotifier $notifier): int
    {
        $sample = (int) $this->option('files');
        if ($sample < 1) {
            $sample = 1;
        }

        $jobs = $this->jobs();
        if ($jobs === []) {
            $this->warn('No matching backup job.');

            return self::FAILURE;
        }

        $problems = [];
        foreach ($jobs as $job) {
            $r = $drill->run($job, $sample);
            $this->report($r);

            if (! $r['ok']) {
                $lines = array_merge($r['errors'], $r['blobs']['mismatches'], $r['blobs']['errors']);
                if (! $r['database']['ok']) {
                    $lines[] = 'database: '.$r['database']['message'];
                }
                $problems[] = $r['job'].': '.implode(' | ', $lines);
            }
        }

        if ($problems === []) {
            $this->info('Restore drill passed.');

            return self::SUCCESS;
        }

        $body = implode("\n", array_map(static fn (string $p): string => '• '.$p, $problems));
        $this->error($body);

        $channels = $this->channels();
        if ($channels !== []) {
            $notifier->send(
                $channels,
                __('settings.backup_verify_alert_title'),
                $body,
                ['event' => 'backup', 'priority' => 'high'],
            );
        }

        return self::FAILURE;
    }

    /**
     * @param  array{
     *     ok: bool,
     *     job: string,
     *     run_id: int|null,
     *     duration_ms: int,
     *     database: array{checked: bool, ok: bool, message: string, tables: int, rows: int|null},
     *     blobs: array{checked: int, matched: int, mismatched: int, mismatches: list<string>, errors: list<string>},
     *     errors: list<string>,
     * }  $r
     */
    private function report(array $r): void
    {
        $this->line(sprintf(
            '%s (run %s) — %s in %d ms',
            $r['job'],
            $r['run_id'] === null ? 'n/a' : (string) $r['run_id'],
            $r['ok'] ? 'OK' : 'PROBLEMS',
            $r['duration_ms'],
        ));

        if ($r['database']['checked']) {
            $this->line('  database: '.$r['database']['message']);
        }
        $b = $r['blobs'];
        if ($b['checked'] > 0) {
            $this->line(sprintf('  blobs: %d sampled, %d matched, %d mismatched.', $b['checked'], $b['matched'], $b['mismatched']));
        }
        foreach (array_merge($r['errors'], $b['mismatches'], $b['errors']) as $line) {
            $this->warn('  '.$line);
        }
    }

    /**
     * The jobs to drill: all of them, or the one named by --job (id or name).
     *
     * @return list<BackupJob>
     */
    private function jobs(): array
    {
        $selector = $this->option('job');
        if (! is_string($selector) || $selector === '') {
            return array_values(BackupJob::all()->all());
        }

        $query = BackupJob::query();
        if (ctype_digit($selector)) {
            $query->whereKey((int) $selector);
        } else {
            $query->where('name', $selector);
        }

        return array_values($query->get()->all());
    }

    /**
     * Globally enabled notification channels (same set the daily verification uses).
     *
     * @return list<string>
     */
    private function channels(): array
    {
        $s = AppSettings::current();

        return array_values(array_filter([
            $s->ntfy_enabled ? 'ntfy' : null,
            $s->webhook_enabled ? 'webhook' : null,
            $s->mail_enabled ? 'mail' : null,
        ]));
    }
}
