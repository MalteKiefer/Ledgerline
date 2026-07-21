<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserSetting;
use App\Services\Paperless\PaperlessSync;
use Illuminate\Console\Command;

/**
 * Refreshes each user's cached Paperless tags, document types and
 * correspondents from their own instance. Scheduled hourly; users without
 * Paperless configured are skipped.
 */
class SyncPaperless extends Command
{
    protected $signature = 'paperless:sync';

    protected $description = 'Refresh cached Paperless terms for every user with Paperless configured';

    public function handle(PaperlessSync $sync): int
    {
        $userIds = UserSetting::where('paperless_enabled', true)->pluck('user_id');
        $synced = 0;
        $total = ['tag' => 0, 'document_type' => 0, 'correspondent' => 0];

        foreach ($userIds as $userId) {
            if (! is_numeric($userId)) {
                continue;
            }
            $userId = (int) $userId;
            try {
                $counts = $sync->run($userId);
            } catch (\Throwable $e) {
                $this->error("Paperless sync failed for user {$userId}: ".$e->getMessage());

                continue;
            }
            if ($counts !== []) {
                $synced++;
                foreach ($counts as $kind => $n) {
                    $total[$kind] += $n;
                }
            }
        }

        if ($synced === 0) {
            $this->info('No users with Paperless configured — nothing to sync.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Synced %d user(s): %d tag(s), %d document type(s), %d correspondent(s).',
            $synced, $total['tag'], $total['document_type'], $total['correspondent'],
        ));

        return self::SUCCESS;
    }
}
