<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppSettings;
use App\Models\Invoice;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Reminds the BUSINESS OWNER (not the customer) about unpaid, overdue invoices
 * so they can chase payment. Runs daily.
 *
 * An invoice is picked when it is issued but unpaid (status='sent'), past its due
 * date, its reminder marker is stale (never reminded OR reminded before the
 * current due date — a re-arm when the due date is pushed out), and it has not
 * been reminded within the last 7 days (throttle). Delivery state lives on the
 * invoice (reminded_at / reminder_count), advanced only AFTER a send attempt so a
 * transient failure retries; each invoice is best-effort (a throw never stops the
 * rest). The owner scope is applied explicitly (no web auth in a scheduled run).
 */
class RemindInvoices extends Command
{
    protected $signature = 'invoices:remind';

    protected $description = 'Remind the owner about unpaid, overdue invoices';

    public function handle(ChannelNotifier $notifier): int
    {
        $today = Carbon::today();
        $reArmCutoff = Carbon::now()->subDays(7);

        $due = Invoice::query()
            ->where('status', 'sent')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->where(function ($q): void {
                // Never reminded, or reminded before the current due date (re-arm).
                $q->whereNull('reminded_at')->orWhereColumn('reminded_at', '<', 'due_date');
            })
            ->where(function ($q) use ($reArmCutoff): void {
                // Not reminded within the last 7 days.
                $q->whereNull('reminded_at')->orWhere('reminded_at', '<', $reArmCutoff);
            })
            ->get();

        if ($due->isEmpty()) {
            $this->info('No overdue invoices to remind about.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($due as $invoice) {
            try {
                $this->remind($notifier, $invoice, $today);
                $invoice->forceFill([
                    'reminded_at' => Carbon::now(),
                    'reminder_count' => (int) $invoice->reminder_count + 1,
                ])->saveQuietly();
                $sent++;
            } catch (\Throwable) {
                // Best-effort per invoice — a delivery failure must not stop the rest.
            }
        }

        $this->info($sent.' overdue reminder(s) sent.');

        return self::SUCCESS;
    }

    private function remind(ChannelNotifier $notifier, Invoice $invoice, Carbon $today): void
    {
        $userId = (int) $invoice->user_id;
        $due = $invoice->due_date;
        $days = $due instanceof Carbon && $due->lt($today) ? (int) $due->diffInDays($today) : 0;

        $customer = is_array($invoice->customer) ? $invoice->customer : [];
        $name = is_string($customer['name'] ?? null) && $customer['name'] !== '' ? $customer['name'] : '—';
        $number = is_string($invoice->number) && $invoice->number !== '' ? $invoice->number : (string) $invoice->id;
        $gross = number_format((float) ($invoice->gross ?? 0), 2).' '.(is_string($invoice->currency) ? $invoice->currency : 'EUR');

        $title = __('invoices.invoice_reminder_subject', ['number' => $number]);
        $body = __('invoices.invoice_reminder_body', [
            'number' => $number,
            'customer' => $name,
            'days' => $days,
            'gross' => $gross,
        ]);

        $notifier->send($this->channels(), (string) $title, (string) $body, [
            'event' => 'reminder',
            'priority' => 'high',
            'level' => 'warning',
            'category' => 'reminder',
            'user_id' => $userId,
        ]);
    }

    /**
     * The owner's in-app bell plus every globally-enabled notification channel
     * (mirrors AlertErrors' channel resolution + the per-user desktop target).
     *
     * @return list<string>
     */
    private function channels(): array
    {
        $s = AppSettings::current();

        return array_values(array_filter([
            'desktop',
            $s->ntfy_enabled ? 'ntfy' : null,
            $s->webhook_enabled ? 'webhook' : null,
            $s->mail_enabled ? 'mail' : null,
        ]));
    }
}
