<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailAccount;
use App\Services\Mail\MailArchiver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pulls every mail account into the local archive. Scheduled hourly; new mail
 * is fetched and mail deleted on the server is kept (archived), so the local
 * copy is a complete, restorable record.
 */
class SyncMailArchive extends Command
{
    protected $signature = 'mail:sync {--account= : Sync only this account id}';

    protected $description = 'Sync mail accounts into the local archive';

    public function handle(MailArchiver $archiver): int
    {
        $accounts = MailAccount::query()
            ->when($this->option('account'), fn ($q) => $q->whereKey($this->option('account')))
            ->get();

        foreach ($accounts as $account) {
            try {
                $r = $archiver->syncAccount($account);
                $this->info(sprintf('%s: %d new, %d archived across %d folder(s).', $account->name, $r['new'], $r['archived'], $r['folders']));
            } catch (\Throwable $e) {
                Log::warning('Mail sync failed', ['account' => $account->id, 'error' => $e->getMessage()]);
                $this->error(sprintf('%s: sync failed — %s', $account->name, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
