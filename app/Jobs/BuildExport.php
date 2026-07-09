<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AppSettings;
use App\Models\Export;
use App\Services\Export\ExportArchiver;
use App\Services\Notifications\ChannelNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Builds an export's zip part(s) on the queue and announces the result. Runs off
 * the request so large downloads never block the browser; the user collects the
 * finished file from the Downloads page.
 */
class BuildExport implements ShouldQueue
{
    use Queueable;

    /** Retention: exports are downloadable for this many days, then pruned. */
    public const RETENTION_DAYS = 7;

    /** Seconds a single build may run before the queue worker kills it. */
    public const TIMEOUT = 1800;

    public int $timeout = self::TIMEOUT;

    public int $tries = 1;

    public function __construct(public int $exportId) {}

    public function handle(ExportArchiver $archiver, ChannelNotifier $notifier): void
    {
        $export = Export::find($this->exportId);
        if ($export === null || $export->status !== 'queued') {
            return;
        }

        $export->forceFill(['status' => 'processing'])->save();

        try {
            $parts = $archiver->build($export, $this->maxBytes($export));

            $export->forceFill([
                'status' => 'ready',
                'files' => $parts,
                'part_count' => count($parts),
                'total_size' => array_sum(array_column($parts, 'size')),
                'payload' => null, // no longer needed; keep the row lean
                'expires_at' => Carbon::now()->addDays(self::RETENTION_DAYS),
            ])->save();

            $this->notify($notifier, true, $export);
        } catch (Throwable $e) {
            $export->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            $this->notify($notifier, false, $export);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Export::where('id', $this->exportId)->where('status', '!=', 'ready')
            ->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }

    private function maxBytes(Export $export): int
    {
        $mb = (int) AppSettings::current()->export_files_max_zip_mb;

        return $mb > 0 ? $mb * 1024 * 1024 : 0;
    }

    private function notify(ChannelNotifier $notifier, bool $ok, Export $export): void
    {
        $s = AppSettings::current();
        $channels = array_keys(array_filter([
            'desktop' => (bool) $s->export_notify_desktop,
            'ntfy' => (bool) $s->export_notify_ntfy,
            'mail' => (bool) $s->export_notify_mail,
            'webhook' => (bool) $s->export_notify_webhook,
        ]));

        if ($channels === []) {
            return;
        }

        $title = $ok ? __('downloads.notify.ready_title') : __('downloads.notify.failed_title');
        $body = $ok
            ? __('downloads.notify.ready_body', ['title' => $export->title])
            : __('downloads.notify.failed_body', ['title' => $export->title]);

        $notifier->send($channels, $title, $body, [
            'url' => route('downloads.index'),
            'category' => 'export',
            'user_id' => $export->user_id,
            'event' => $ok ? 'export.ready' : 'export.failed',
            'level' => $ok ? 'success' : 'error',
            'priority' => $ok ? 'default' : 'high',
        ]);
    }
}
