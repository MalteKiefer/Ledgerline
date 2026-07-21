<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Revoke device bearer tokens that have gone idle past the configured window, or
 * that were flagged for a remote wipe more than the grace window ago (a backstop
 * for a client that never comes back to be revoked at request time). Sanctum's
 * own absolute `expiration` handles the hard lifetime; this closes the idle +
 * offline-wipe gaps a request-time check cannot.
 */
class PruneDeviceTokens extends Command
{
    protected $signature = 'devices:prune-tokens';

    protected $description = 'Revoke idle and wipe-flagged device tokens';

    public function handle(): int
    {
        $idleCfg = config('devices.idle_days', 0);
        $idleDays = is_numeric($idleCfg) ? (int) $idleCfg : 0;
        $graceCfg = config('devices.wipe_grace_minutes', 15);
        $graceMin = is_numeric($graceCfg) ? (int) $graceCfg : 15;

        $idle = 0;
        if ($idleDays > 0) {
            // A token that was paired but never used has last_used_at = NULL — fall
            // back to created_at so a leaked, never-touched token still dies.
            $cutoff = now()->subDays($idleDays);
            $idleResult = PersonalAccessToken::query()
                ->where(function ($q) use ($cutoff): void {
                    $q->where('last_used_at', '<', $cutoff)
                        ->orWhere(fn ($q2) => $q2->whereNull('last_used_at')->where('created_at', '<', $cutoff));
                })
                ->delete();
            $idle = is_numeric($idleResult) ? (int) $idleResult : 0;
        }

        $wipedResult = PersonalAccessToken::query()
            ->whereNotNull('wipe_requested_at')
            ->where('wipe_requested_at', '<', now()->subMinutes($graceMin))
            ->delete();
        $wiped = is_numeric($wipedResult) ? (int) $wipedResult : 0;

        $this->info("Pruned {$idle} idle + {$wiped} wiped device token(s).");

        return self::SUCCESS;
    }
}
