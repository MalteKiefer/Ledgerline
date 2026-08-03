<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Mail\SyncMailAccount;
use App\Models\MailAccount;
use Illuminate\Console\Command;

/**
 * Dispatches a sync producer (SyncMailAccount) for every ENABLED mail account.
 * Wired to the scheduler on the configured interval; the per-account
 * WithoutOverlapping lock keeps a slow sync from stacking up. The API (Task 8)
 * dispatches SyncMailAccount directly for an on-demand "sync now".
 */
class SyncMailAccounts extends Command
{
    protected $signature = 'mail:sync-accounts';

    protected $description = 'Dispatch a sync for every enabled mail account';

    public function handle(): int
    {
        $dispatched = 0;

        MailAccount::query()
            ->where('enabled', true)
            ->select('id')
            ->each(function (MailAccount $account) use (&$dispatched): void {
                SyncMailAccount::dispatch($account->id);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} mail account sync(s).");

        return self::SUCCESS;
    }
}
