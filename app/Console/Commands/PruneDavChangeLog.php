<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Trims the WebDAV sync-collection change logs. Each collection keeps its most
 * recent KEEP tokens of history; clients older than that fall back to a full
 * resync (getChanges returns null → RFC 6578 valid-sync-token). Without this the
 * logs grow unbounded, amplified by every subscription/derived rebuild.
 */
class PruneDavChangeLog extends Command
{
    protected $signature = 'dav:prune-changes';

    protected $description = 'Trim old WebDAV sync-collection change-log rows';

    /** Tokens of history retained per collection. */
    private const KEEP = 10000;

    public function handle(): int
    {
        $deleted = $this->prune('calendar_changes', 'calendar_id')
            + $this->prune('dav_changes', 'address_book_id');

        $this->info("Pruned {$deleted} change-log row(s).");

        return self::SUCCESS;
    }

    private function prune(string $table, string $foreignKey): int
    {
        $deleted = 0;
        foreach (DB::table($table)->select($foreignKey)->distinct()->pluck($foreignKey) as $id) {
            $cutoff = (int) DB::table($table)->where($foreignKey, $id)->max('synctoken') - self::KEEP;
            if ($cutoff > 0) {
                $deleted += DB::table($table)->where($foreignKey, $id)->where('synctoken', '<', $cutoff)->delete();
            }
        }

        return $deleted;
    }
}
