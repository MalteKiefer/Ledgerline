<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs an allow-listed artisan maintenance command on the queue, so a user can
 * trigger a long-running task (link check, subscription refresh, text re-index)
 * from the GUI without blocking the request. Only the fixed allow-list below can
 * ever be dispatched.
 */
class RunCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    private const ALLOWED = [
        'files:extract-text',
        'bookmarks:check-links',
        'calendar:refresh-subscriptions',
    ];

    /** @param array<string, mixed> $options */
    public function __construct(public string $command, public array $options = [])
    {
        abort_unless(in_array($command, self::ALLOWED, true), 403);
    }

    public function handle(): void
    {
        if (in_array($this->command, self::ALLOWED, true)) {
            Artisan::call($this->command, $this->options);
        }
    }
}
