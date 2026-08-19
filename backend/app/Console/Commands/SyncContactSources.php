<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Contacts\SyncContactSource;
use App\Models\ContactSyncSource;
use Illuminate\Console\Command;

/** Dispatch enabled CardDAV replicas on a safe, bounded interval. */
class SyncContactSources extends Command
{
    protected $signature = 'contacts:sync-sources';
    protected $description = 'Dispatch enabled external CardDAV contact replicas';

    public function handle(): int
    {
        $count = 0;
        ContactSyncSource::query()->where('enabled', true)->each(function (ContactSyncSource $source) use (&$count): void {
            SyncContactSource::dispatch($source->id);
            $count++;
        });
        $this->info("Dispatched {$count} CardDAV replica sync(s).");

        return self::SUCCESS;
    }
}
