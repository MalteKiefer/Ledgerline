<?php

declare(strict_types=1);

namespace App\Jobs\Contacts;

use App\Models\ContactSyncSource;
use App\Services\Contacts\ContactReplication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/** One locked worker per external source avoids racing ETags or duplicate writes. */
class SyncContactSource implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public string $sourceId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('contacts-carddav-'.$this->sourceId))->dontRelease()->expireAfter(660)];
    }

    public function handle(ContactReplication $replication): void
    {
        $source = ContactSyncSource::query()->find($this->sourceId);
        if ($source !== null && $source->enabled) {
            $replication->sync($source);
        }
    }
}
