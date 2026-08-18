<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Remind finance users to upload a fresh bank-statement CSV every seven days.
 *
 * The last successful bulk import is represented by the newest transaction row.
 * Notification rows provide the throttle for accounts that have never imported a
 * statement. This remains per-user and idempotent when the scheduler runs daily.
 */
class RemindBankCsvImport extends Command
{
    protected $signature = 'finance:remind-bank-csv';

    protected $description = 'Remind finance users to upload a current bank CSV';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays(7);
        $sent = 0;

        foreach (User::query()->whereNull('blocked_at')->get() as $user) {
            if (! $user->canModule('finance')) {
                continue;
            }

            $latestImport = BankTransaction::query()
                ->where('user_id', $user->id)
                ->max('created_at');
            $latestReminder = AppNotification::query()
                ->where('user_id', $user->id)
                ->where('category', 'finance_bank_csv')
                ->max('created_at');

            $hasRecentImport = is_string($latestImport) && Carbon::parse($latestImport)->gte($cutoff);
            $hasRecentReminder = is_string($latestReminder) && Carbon::parse($latestReminder)->gte($cutoff);
            if ($hasRecentImport || $hasRecentReminder) {
                continue;
            }

            $previousLocale = app()->getLocale();
            $fallbackLocale = config('app.locale');
            $locale = is_string($user->locale) && in_array($user->locale, ['de', 'en', 'ru'], true)
                ? $user->locale
                : (is_string($fallbackLocale) ? $fallbackLocale : 'en');
            try {
                app()->setLocale($locale);
                AppNotification::record(
                    (int) $user->id,
                    'info',
                    (string) __('invoices.bank_csv_reminder_title'),
                    (string) __('invoices.bank_csv_reminder_body'),
                    'finance_bank_csv',
                );
                $sent++;
            } finally {
                app()->setLocale($previousLocale);
            }
        }

        $this->info($sent.' bank CSV reminder(s) sent.');

        return self::SUCCESS;
    }
}
