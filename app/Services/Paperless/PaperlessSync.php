<?php

declare(strict_types=1);

namespace App\Services\Paperless;

use App\Models\PaperlessTerm;
use App\Models\UserSetting;
use Carbon\Carbon;

/**
 * Refreshes a user's local cache of Paperless tags, document types and
 * correspondents from their instance. Upserts current terms and drops any that
 * no longer exist (scoped to the user), then stamps the sync time.
 */
class PaperlessSync
{
    /**
     * @return array<string,int> counts per kind, e.g. ['tag' => 12, ...]
     */
    public function run(int $userId): array
    {
        $settings = UserSetting::for($userId);
        $client = PaperlessClient::fromUserSetting($settings);
        if (! $settings->paperless_enabled || $client === null) {
            return [];
        }

        $counts = [];
        foreach (PaperlessTerm::KINDS as $kind) {
            $terms = $client->list($kind);
            $keepIds = [];
            foreach ($terms as $t) {
                PaperlessTerm::withoutGlobalScopes()->updateOrCreate(
                    ['user_id' => $userId, 'kind' => $kind, 'paperless_id' => $t['paperless_id']],
                    ['name' => $t['name'], 'color' => $t['color']],
                );
                $keepIds[] = $t['paperless_id'];
            }
            // Drop terms deleted in Paperless since the last sync (this user only).
            PaperlessTerm::withoutGlobalScopes()->where('user_id', $userId)->where('kind', $kind)
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('paperless_id', $keepIds))
                ->delete();
            $counts[$kind] = count($terms);
        }

        $settings->update(['paperless_synced_at' => Carbon::now()]);

        return $counts;
    }
}
