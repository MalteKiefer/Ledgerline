<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\Server;
use App\Services\Servers\PackageManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Install the pending updates on one server — WORKER-ONLY.
 *
 * An upgrade takes minutes. A request that holds a worker for minutes is the
 * mistake this application has already made twice, so the endpoint answers 202
 * and the outcome arrives as a notification.
 *
 * One try, no retries: a half-finished dpkg run is not something to repeat
 * unattended, and the second attempt would more likely find a held lock than
 * fix anything.
 */
class ApplyServerUpdates implements ShouldQueue
{
    use Queueable;

    /** Above the package manager's own ceiling, with room for the handshake. */
    public int $timeout = 1900;

    public int $tries = 1;

    public function __construct(public int $serverId) {}

    public function handle(PackageManager $packages): void
    {
        $server = Server::query()->withoutGlobalScopes()->find($this->serverId);
        if ($server === null) {
            return;
        }

        $result = $packages->apply($server);

        // The tail of the host's own output, not a summary of it: an upgrade
        // that failed says why in its last lines, and paraphrasing that would
        // lose the one detail worth having.
        $detail = trim($result['output']);
        $detail = $detail === '' ? null : mb_substr($detail, -1500);

        AppNotification::record(
            $server->user_id,
            $result['ok'] ? 'info' : 'warning',
            $result['ok']
                ? __('servers.notify.updates_done', ['name' => $server->name])
                : __('servers.notify.updates_failed', ['name' => $server->name]),
            $detail,
            'server',
        );
    }
}
