<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Creates notification-centre rows (category=birthday) for contacts whose BDAY
 * (year-agnostic MM-DD) matches today — or any day within an optional lead window
 * — which then fan out to push via the AppNotification::record choke point.
 *
 * Throttled once per contact per calendar year (the cache key carries the year),
 * so a lead window fires a single reminder ahead of the day. Runs daily.
 */
class RemindBirthdays extends Command
{
    protected $signature = 'contacts:birthday-remind {--lead=0 : Also remind this many days ahead}';

    protected $description = 'Notify about contacts whose birthday is today';

    public function handle(): int
    {
        $today = Carbon::today();
        $lead = max(0, (int) $this->option('lead'));

        $targets = [];
        for ($i = 0; $i <= $lead; $i++) {
            $targets[] = $today->copy()->addDays($i)->format('m-d');
        }

        $contacts = Contact::query()->whereIn('bday', $targets)->with('addressBook')->get();

        $sent = 0;
        foreach ($contacts as $contact) {
            $userId = $contact->addressBook?->user_id;
            if ($userId === null) {
                continue;
            }
            // One reminder per contact per year (a lead window fires once, ahead).
            $key = 'contacts:birthday-remind:'.$contact->id.':'.$today->year;
            if (! Cache::add($key, 1, now()->addDays(3))) {
                continue;
            }
            try {
                AppNotification::record((int) $userId, 'info', __('notifications.birthday', ['name' => $contact->fn ?? '—']), null, 'birthday');
                $sent++;
            } catch (\Throwable) {
                Cache::forget($key);
            }
        }

        $this->info($sent.' birthday reminder(s) created.');

        return self::SUCCESS;
    }
}
