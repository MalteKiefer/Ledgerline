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
        $idleDays = (int) config('devices.idle_days', 0);
        $graceMin = (int) config('devices.wipe_grace_minutes', 15);

        $idle = 0;
        if ($idleDays > 0) {
            $idle = PersonalAccessToken::query()
                ->whereNotNull('last_used_at')
                ->where('last_used_at', '<', now()->subDays($idleDays))
                ->delete();
        }

        $wiped = PersonalAccessToken::query()
            ->whereNotNull('wipe_requested_at')
            ->where('wipe_requested_at', '<', now()->subMinutes($graceMin))
            ->delete();

        $this->info("Pruned {$idle} idle + {$wiped} wiped device token(s).");

        return self::SUCCESS;
    }
}
