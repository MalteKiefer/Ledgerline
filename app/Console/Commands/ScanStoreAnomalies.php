<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BlobAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Turns the passive root_write forensic trail into an active data-loss detector.
 *
 * Every sealed-store save records its per-slice record counts (non-secret
 * cardinality) in blob_audit_log. This walks the trail per (user, module),
 * compares consecutive versions, and records a `store.count_regression` audit
 * event for any version where the total record count DROPPED — the strongest
 * automatic signal that a record was silently lost (e.g. a bad 409 rebase).
 *
 * A regression is a SIGNAL, not proof: a legitimate delete / empty-trash also
 * drops the count. The value is a timestamped, device-attributed shortlist to
 * investigate the moment a user reports "X vanished". Zero-knowledge: it reads
 * only counts + version + install id, never content.
 */
class ScanStoreAnomalies extends Command
{
    protected $signature = 'store:anomaly-scan {--since=48h : Look back window (e.g. 24h, 7d, or an ISO date)} {--dry : Report only, do not write audit events} {--json : Machine-readable output}';

    protected $description = 'Flag sealed-store versions where the record count regressed (potential silent data loss)';

    public function handle(): int
    {
        $since = $this->parseSince((string) $this->option('since'));

        // Groups (user, module) that had a root_write in the window — only these can
        // have a fresh regression to report.
        $groups = BlobAuditLog::query()
            ->where('action', 'root_write')
            ->where('created_at', '>=', $since)
            ->select('user_id', 'module')
            ->distinct()
            ->get();

        $found = [];
        foreach ($groups as $g) {
            $uid = $g->user_id;
            $module = (string) $g->module;
            if ($uid === null) {
                continue;
            }

            // The group's full version history, oldest first. One row per save, so
            // even a long-lived store is a bounded, cheap read.
            $rows = BlobAuditLog::query()
                ->where('action', 'root_write')
                ->where('user_id', $uid)
                ->where('module', $module)
                ->orderBy('id')
                ->get(['meta', 'created_at']);

            $prev = null;
            foreach ($rows as $row) {
                $meta = is_array($row->meta) ? $row->meta : [];
                $total = $meta['count_total'] ?? null;
                $version = $meta['version'] ?? null;
                $cur = ['total' => is_numeric($total) ? (int) $total : null, 'version' => $version, 'meta' => $meta, 'at' => $row->created_at];

                if (
                    $prev !== null
                    && $prev['total'] !== null && $cur['total'] !== null
                    && $cur['total'] < $prev['total']
                    // Only a transition whose LATER version landed in the window is fresh.
                    && $cur['at'] !== null && $cur['at']->gte($since)
                ) {
                    $found[] = $this->buildRegression($uid, $module, $prev, $cur);
                }
                $prev = $cur;
            }
        }

        // Dedup against already-recorded regressions (idempotent re-runs).
        $found = $this->dropAlreadyRecorded($found);

        if (! $this->option('dry')) {
            foreach ($found as $r) {
                AuditLog::record('store.count_regression', null, $r['meta'], $r['user_id']);
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(array_map(static fn (array $r): array => $r['meta'] + ['user_id' => $r['user_id']], $found)));

            return self::SUCCESS;
        }

        if ($found === []) {
            $this->info('No store count regressions in the window.');

            return self::SUCCESS;
        }

        $this->warn(count($found).' store count regression(s) found'.($this->option('dry') ? ' (dry run — not recorded)' : ' (recorded)').':');
        foreach ($found as $r) {
            $m = $r['meta'];
            $drops = [];
            if (is_array($m['drops'] ?? null)) {
                foreach ($m['drops'] as $k => $v) {
                    $drops[(string) $k] = is_numeric($v) ? (int) $v : 0;
                }
            }
            $this->line(sprintf(
                '  user %d  %s  v%s→v%s  total %d→%d  (%s)  device=%s',
                $r['user_id'], $this->str($m['module'] ?? ''), $this->str($m['from_version'] ?? ''), $this->str($m['to_version'] ?? ''),
                $this->intOr($m['from_total'] ?? 0), $this->intOr($m['to_total'] ?? 0), $this->fmtDrops($drops), $this->str($m['install_id'] ?? '—'),
            ));
        }

        return self::SUCCESS;
    }

    private function str(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }

    private function intOr(mixed $v): int
    {
        return is_numeric($v) ? (int) $v : 0;
    }

    /**
     * @param  array{total: ?int, version: mixed, meta: array<string, mixed>, at: ?Carbon}  $prev
     * @param  array{total: ?int, version: mixed, meta: array<string, mixed>, at: ?Carbon}  $cur
     * @return array{user_id: int, meta: array<string, mixed>}
     */
    private function buildRegression(int $uid, string $module, array $prev, array $cur): array
    {
        $prevCounts = is_array($prev['meta']['counts'] ?? null) ? $prev['meta']['counts'] : [];
        $curCounts = is_array($cur['meta']['counts'] ?? null) ? $cur['meta']['counts'] : [];
        $drops = [];
        foreach ($prevCounts as $k => $pv) {
            $cv = $curCounts[$k] ?? 0;
            if (is_numeric($pv) && is_numeric($cv) && (int) $cv < (int) $pv) {
                $drops[(string) $k] = (int) $cv - (int) $pv; // negative delta
            }
        }

        return [
            'user_id' => $uid,
            'meta' => [
                'module' => $module,
                'from_version' => $prev['version'],
                'to_version' => $cur['version'],
                'from_total' => $prev['total'],
                'to_total' => $cur['total'],
                'drops' => $drops,
                'from_bytes' => $prev['meta']['bytes'] ?? null,
                'to_bytes' => $cur['meta']['bytes'] ?? null,
                'install_id' => $cur['meta']['install_id'] ?? null,
                'app_version' => $cur['meta']['app_version'] ?? null,
            ],
        ];
    }

    /**
     * @param  list<array{user_id: int, meta: array<string, mixed>}>  $found
     * @return list<array{user_id: int, meta: array<string, mixed>}>
     */
    private function dropAlreadyRecorded(array $found): array
    {
        if ($found === []) {
            return [];
        }
        $uids = array_values(array_unique(array_map(static fn (array $r): int => $r['user_id'], $found)));
        $existing = AuditLog::query()
            ->where('action', 'store.count_regression')
            ->whereIn('user_id', $uids)
            ->get(['user_id', 'meta']);
        $seen = [];
        foreach ($existing as $e) {
            $m = is_array($e->meta) ? $e->meta : [];
            $seen[$e->user_id.'|'.$this->str($m['module'] ?? '').'|'.$this->str($m['to_version'] ?? '')] = true;
        }

        return array_values(array_filter($found, function (array $r) use ($seen): bool {
            $key = $r['user_id'].'|'.$this->str($r['meta']['module'] ?? '').'|'.$this->str($r['meta']['to_version'] ?? '');

            return ! isset($seen[$key]);
        }));
    }

    /** @param array<string, int> $drops */
    private function fmtDrops(array $drops): string
    {
        if ($drops === []) {
            return 'total';
        }
        $parts = [];
        foreach ($drops as $k => $d) {
            $parts[] = $k.' '.$d;
        }

        return implode(', ', $parts);
    }

    private function parseSince(string $s): Carbon
    {
        $s = trim($s);
        if (preg_match('/^(\d+)([hd])$/', $s, $m) === 1) {
            $n = (int) $m[1];

            return $m[2] === 'h' ? Carbon::now()->subHours($n) : Carbon::now()->subDays($n);
        }
        try {
            return Carbon::parse($s);
        } catch (\Throwable) {
            return Carbon::now()->subHours(48);
        }
    }
}
