<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Server;
use App\Services\Servers\ServerTerminal;
use App\Support\TerminalChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hold one interactive shell open for the length of a session.
 *
 * On its OWN queue, and that is not incidental. This job occupies a worker for
 * as long as somebody is typing — up to half an hour. On the shared queue that
 * would stall the probes, the reachability checks and the backups, which is to
 * say it would break the monitoring this module exists for in order to serve a
 * terminal. The operator runs a separate worker for the `terminal` queue; until
 * they do, sessions never become ready and the UI says so rather than spinning.
 *
 * One try. A retry would silently open a second shell on someone's server.
 */
class RunServerTerminal implements ShouldQueue
{
    use Queueable;

    /** Comfortably past the session ceiling, so the channel decides when to stop, not the worker. */
    public int $timeout = TerminalChannel::MAX_LIFETIME + 60;

    public int $tries = 1;

    public function __construct(
        public int $serverId,
        public string $channelId,
        public int $cols,
        public int $rows,
    ) {
        $this->onQueue('terminal');
    }

    public function handle(ServerTerminal $terminal): void
    {
        $channel = new TerminalChannel($this->channelId);

        $server = Server::query()->withoutGlobalScopes()->whereKey($this->serverId)->first();
        if ($server === null || ! $server->enabled) {
            $channel->close('gone');

            return;
        }

        // The session was authorised when it was opened — the password step-up
        // happened there. This job only carries it out.
        $terminal->run($server, $channel, $this->cols, $this->rows);
    }
}
