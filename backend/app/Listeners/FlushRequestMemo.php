<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\RequestMemo;

/**
 * Octane resets much of the application container between requests, but the
 * per-request memo store (App\Support\RequestMemo — the workspace AppSettings and
 * each user's UserSetting row) is plain static state that Octane does not know
 * about. Clear it at the START of every request so a persistent worker never
 * serves one request's memoised rows to the next (stale settings / unbounded
 * per-user growth). Registered on Octane's RequestReceived + TaskReceived events.
 */
class FlushRequestMemo
{
    public function handle(object $event): void
    {
        RequestMemo::flush();
    }
}
