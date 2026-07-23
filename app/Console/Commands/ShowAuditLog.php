<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Read-only audit-log inspector for fast server-side diagnosis, e.g.
 *   php artisan audit:show --action=device.* --user=1 --since=-24h --limit=50
 *   php artisan audit:show --action=auth.unauthorized --json
 * Never mutates. Prints a table by default, or newline-delimited JSON with --json.
 */
class ShowAuditLog extends Command
{
    protected $signature = 'audit:show
        {--user= : Filter by user id}
        {--action= : Filter by action; a trailing .* matches a prefix (e.g. device.*)}
        {--since= : Only entries after this time (a date, or a relative like -24h / -7d)}
        {--limit=50 : Max rows}
        {--json : Emit newline-delimited JSON instead of a table}';

    protected $description = 'Inspect the security audit log (read-only)';

    public function handle(): int
    {
        $query = AuditLog::query()->with('actor')->orderByDesc('created_at');

        if (is_numeric($this->option('user'))) {
            $query->where('user_id', (int) $this->option('user'));
        }

        $action = $this->option('action');
        if (is_string($action) && $action !== '') {
            if (str_ends_with($action, '.*')) {
                $query->where('action', 'like', str_replace('.*', '.', $action).'%');
            } else {
                $query->where('action', $action);
            }
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

        $limit = is_numeric($this->option('limit')) ? max(1, (int) $this->option('limit')) : 50;
        $rows = $query->limit($limit)->get();

        if ($this->option('json')) {
            foreach ($rows as $r) {
                $this->line((string) json_encode([
                    'at' => $r->created_at?->toIso8601String(),
                    'user_id' => $r->user_id,
                    'actor' => $r->actor?->name,
                    'action' => $r->action,
                    'ip' => $r->ip,
                    'meta' => $r->meta,
                ], JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        }

        $this->table(
            ['When', 'User', 'Action', 'IP', 'Meta'],
            $rows->map(fn (AuditLog $r): array => [
                $r->created_at?->format('Y-m-d H:i:s') ?? '',
                $r->actor?->name ?? (string) ($r->user_id ?? '—'),
                $r->action,
                $r->ip ?? '',
                mb_strimwidth((string) json_encode($r->meta, JSON_UNESCAPED_SLASHES), 0, 60, '…'),
            ])->all(),
        );
        $this->info($rows->count().' entr'.($rows->count() === 1 ? 'y' : 'ies').' shown.');

        return self::SUCCESS;
    }

    /** Convert a "-24h" / "-7d" token into a CarbonInterval-compatible duration. */
    private static function relative(string $token): \DateInterval
    {
        $n = (int) preg_replace('/\D/', '', $token);
        $unit = substr($token, -1);
        $map = ['s' => "PT{$n}S", 'm' => "PT{$n}M", 'h' => "PT{$n}H", 'd' => "P{$n}D", 'w' => 'P'.($n * 7).'D'];

        return new \DateInterval($map[$unit] ?? "PT{$n}H");
    }
}
