<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\DocumentDeadline;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reminds about confirmed document deadlines before they land.
 *
 * Only CONFIRMED findings: an unconfirmed one is a guess from a pattern matcher,
 * and waking someone for a guess teaches them to ignore the notification that
 * matters. Confirming a finding is one click, and it is the click that turns it
 * into something worth being reminded about.
 *
 * Two windows rather than one, because both readings are useful and they mean
 * different things: a month out is when a notice period can still be met, a week
 * out is the last call. `reminded_at` keeps the second from repeating daily.
 */
class RemindDeadlines extends Command
{
    protected $signature = 'deadlines:remind {--lead=30 : Days ahead to start reminding}';

    protected $description = 'Notify the owner about confirmed document deadlines coming up';

    public function handle(): int
    {
        $lead = max(1, (int) $this->option('lead'));
        $today = Carbon::today();

        $due = DocumentDeadline::query()
            ->withoutGlobalScopes()
            ->whereNotNull('confirmed_at')
            ->whereNull('dismissed_at')
            ->whereDate('due_on', '>=', $today)
            ->whereDate('due_on', '<=', $today->copy()->addDays($lead))
            ->get();

        $sent = 0;
        foreach ($due as $deadline) {
            if ($this->remind($deadline, $today)) {
                $sent++;
            }
        }

        $this->info($sent.' deadline reminder(s) created.');

        return self::SUCCESS;
    }

    private function remind(DocumentDeadline $deadline, Carbon $today): bool
    {
        $dueOn = $deadline->due_on;
        if (! $dueOn instanceof Carbon) {
            return false;
        }
        $daysLeft = (int) $today->diffInDays($dueOn, false);

        // Send once per window. Re-arms if the date is edited, because the
        // stamp is compared against the date it was sent for.
        $last = $deadline->reminded_at;
        $window = $daysLeft <= 7 ? 'final' : 'early';
        if ($last instanceof Carbon && $last->greaterThan($dueOn->copy()->subDays($window === 'final' ? 7 : 400))) {
            return false;
        }

        try {
            $label = $deadline->label ?? '—';
            $title = __('notifications.deadline_due', ['what' => $label]);
            $body = __('notifications.deadline_on', ['date' => $dueOn->toDateString(), 'days' => $daysLeft]);
            AppNotification::record((int) $deadline->user_id, $daysLeft <= 7 ? 'warning' : 'info', $title, $body, 'deadline');
            $deadline->forceFill(['reminded_at' => now()])->save();

            return true;
        } catch (Throwable) {
            return false; // best-effort: a failed notification must not stop the run
        }
    }
}
