<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Calendar;
use App\Services\Calendar\SubscriptionRefresher;
use Illuminate\Console\Command;

/**
 * Refreshes subscribed remote ICS feeds that are due per their interval. Runs
 * frequently; each feed decides for itself whether it is due (isDue). One feed
 * failing never stops the others.
 */
class RefreshCalendarSubscriptions extends Command
{
    protected $signature = 'calendar:refresh-subscriptions {--force : Refresh every subscription regardless of interval}';

    protected $description = 'Refresh subscribed remote calendar feeds that are due';

    public function handle(SubscriptionRefresher $refresher): int
    {
        $refreshed = 0;

        foreach (Calendar::whereNotNull('subscription_url')->get() as $calendar) {
            if (! $this->option('force') && ! $refresher->isDue($calendar)) {
                continue;
            }
            try {
                $refresher->refresh($calendar);
                $refreshed++;
            } catch (\Throwable $e) {
                $this->warn("Feed '{$calendar->name}' failed: {$e->getMessage()}");
            }
        }

        $this->info('Refreshed '.$refreshed.' subscription(s).');

        return self::SUCCESS;
    }
}
