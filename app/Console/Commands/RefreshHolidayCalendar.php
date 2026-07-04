<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Calendar\HolidayCalendarBuilder;
use Illuminate\Console\Command;

/**
 * Rebuilds the holidays calendar for the selected countries. Runs daily so the
 * rolling window of years advances automatically at year rollover.
 */
class RefreshHolidayCalendar extends Command
{
    protected $signature = 'calendar:refresh-holidays';

    protected $description = 'Rebuild the holidays calendar for the selected countries';

    public function handle(HolidayCalendarBuilder $builder): int
    {
        $builder->sync();
        $this->info('Holidays calendar rebuilt.');

        return self::SUCCESS;
    }
}
