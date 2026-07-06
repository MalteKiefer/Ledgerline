<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Bookmark;
use App\Support\OutboundUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Checks bookmark URLs for dead links. Oldest-checked first with a per-run
 * cap, so a big collection is swept incrementally by the weekly schedule.
 * A link only counts as dead on unambiguous failures (DNS/connect errors,
 * 404/410) — transient 5xx or bot-blocking 403s do not flag it.
 */
class CheckBookmarkLinks extends Command
{
    protected $signature = 'bookmarks:check-links {--limit=200}';

    protected $description = 'Check bookmark URLs and flag dead links';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $bookmarks = Bookmark::withoutGlobalScopes()
            ->orderByRaw('last_checked_at asc nulls first')
            ->limit($limit)
            ->get();

        $dead = 0;
        foreach ($bookmarks as $bookmark) {
            $bookmark->forceFill([
                'last_checked_at' => Carbon::now(),
                'dead_at' => $this->isDead($bookmark->url) ? ($bookmark->dead_at ?? Carbon::now()) : null,
            ])->saveQuietly();
            $dead += $bookmark->dead_at !== null ? 1 : 0;
        }

        $this->info('Checked '.$bookmarks->count().' bookmark(s), '.$dead.' dead.');

        return self::SUCCESS;
    }

    private function isDead(string $url): bool
    {
        if (! OutboundUrl::safe($url)) {
            return true; // unresolvable/blocked host
        }
        try {
            $res = OutboundUrl::client($url, 10)->head($url);
            // Some sites refuse HEAD; retry those with GET before judging.
            if (in_array($res->status(), [405, 501], true)) {
                $res = OutboundUrl::client($url, 10)->get($url);
            }

            return in_array($res->status(), [404, 410], true);
        } catch (Throwable) {
            return true; // connection-level failure
        }
    }
}
