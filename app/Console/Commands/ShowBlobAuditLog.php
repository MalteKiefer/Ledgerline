<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlobAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Read-only inspector for the blob/shard forensic trail — the first tool to reach for
 * when reconstructing a data-loss event. Examples:
 *   php artisan blob-audit:show --blob=<uuid>            # full lifecycle of one blob
 *   php artisan blob-audit:show --module=gallery --action=root_write --limit=30
 *   php artisan blob-audit:show --action=reconcile_delete --since=-24h
 *   php artisan blob-audit:show --user=1 --json
 * Never mutates.
 */
class ShowBlobAuditLog extends Command
{
    protected $signature = 'blob-audit:show
        {--blob= : Filter by blob ref (UUID) — the full history of one shard/blob}
        {--module= : Filter by module (gallery|files|shared-folders)}
        {--action= : Filter by action; a trailing .* matches a prefix}
        {--user= : Filter by user id}
        {--since= : Only entries after this time (a date, or a relative like -24h / -7d)}
        {--limit=100 : Max rows}
        {--json : Emit newline-delimited JSON instead of a table}';

    protected $description = 'Inspect the blob/shard forensic trail (read-only)';

    public function handle(): int
    {
        $query = BlobAuditLog::query()->orderBy('created_at');

        $blob = $this->option('blob');
        if (is_string($blob) && $blob !== '') {
            $query->where('blob', $blob);
        }

        $module = $this->option('module');
        if (is_string($module) && $module !== '') {
            $query->where('module', $module);
        }

        $action = $this->option('action');
        if (is_string($action) && $action !== '') {
            if (str_ends_with($action, '.*')) {
                $query->where('action', 'like', str_replace('.*', '', $action).'%');
            } else {
                $query->where('action', $action);
            }
        }

        if (is_numeric($this->option('user'))) {
            $query->where('user_id', (int) $this->option('user'));
        }

        $since = $this->option('since');
        if (is_string($since) && $since !== '') {
            try {
                $ts = preg_match('/^-\d+[smhdw]$/', $since) === 1
                    ? Carbon::now()->sub(self::relative($since))
                    : Carbon::parse($since);
                $query->where('created_at', '>=', $ts);
            } catch (\Throwable) {
                $this->warn('Could not parse --since; ignoring.');
            }
        }

        $limit = is_numeric($this->option('limit')) ? max(1, (int) $this->option('limit')) : 100;
        $rows = $query->limit($limit)->get();

        if ($this->option('json')) {
            foreach ($rows as $r) {
                $this->line((string) json_encode([
                    'at' => $r->created_at?->toIso8601String(),
                    'user_id' => $r->user_id,
                    'module' => $r->module,
                    'action' => $r->action,
                    'blob' => $r->blob,
                    'size' => $r->size,
                    'sha256' => $r->sha256,
                    'source' => $r->source,
                    'reason' => $r->reason,
                    'result' => $r->result,
                    'meta' => $r->meta,
                ], JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        }

        $this->table(
            ['When', 'User', 'Module', 'Action', 'Blob', 'Size', 'sha256', 'Src', 'Reason', 'Result'],
            $rows->map(fn (BlobAuditLog $r): array => [
                $r->created_at?->format('Y-m-d H:i:s') ?? '',
                (string) ($r->user_id ?? '—'),
                $r->module,
                $r->action,
                $r->blob !== null ? substr($r->blob, 0, 8) : '—',
                $r->size !== null ? (string) $r->size : '—',
                $r->sha256 !== null ? substr($r->sha256, 0, 12) : '—',
                $r->source ?? '',
                $r->reason ?? '',
                $r->result,
            ])->all(),
        );
        $this->info($rows->count().' entr'.($rows->count() === 1 ? 'y' : 'ies').' shown.');

        return self::SUCCESS;
    }

    /** Convert a "-24h" / "-7d" token into a DateInterval. */
    private static function relative(string $token): \DateInterval
    {
        $n = (int) preg_replace('/\D/', '', $token);
        $unit = substr($token, -1);
        $map = ['s' => "PT{$n}S", 'm' => "PT{$n}M", 'h' => "PT{$n}H", 'd' => "P{$n}D", 'w' => 'P'.($n * 7).'D'];

        return new \DateInterval($map[$unit] ?? "PT{$n}H");
    }
}
