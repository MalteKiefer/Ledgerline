<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Mail\SyncMailAccount;
use App\Models\MailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Dispatches a sync producer (SyncMailAccount) for every ENABLED mail account
 * that is DUE for a fetch. Wired to the scheduler once a minute; each account
 * decides due-ness from its own effective interval (per-account override or the
 * workspace default), so the fetch cadence is per-account. The per-account
 * WithoutOverlapping lock keeps a slow sync from stacking up. The API dispatches
 * SyncMailAccount directly for an on-demand "sync now" (bypasses the due check).
 */
class SyncMailAccounts extends Command
{
    protected $signature = 'mail:sync-accounts';

    protected $description = 'Dispatch a sync for every enabled mail account that is due';

    public function handle(): int
    {
        $now = Carbon::now();
        $dispatched = 0;

        MailAccount::query()
            ->where('enabled', true)
            ->each(function (MailAccount $account) use (&$dispatched, $now): void {
                if (! $account->isDueForSync($now)) {
                    return;
                }
                SyncMailAccount::dispatch($account->id);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} mail account sync(s).");

        return self::SUCCESS;
    }
}
