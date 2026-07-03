<?php

declare(strict_types=1);

namespace App\Services\Paperless;

use App\Models\AppSettings;
use App\Models\PaperlessTerm;
use Carbon\Carbon;

/**
 * Refreshes the local cache of Paperless tags, document types and
 * correspondents from the live API. Upserts current terms and drops any that
 * no longer exist, then stamps the sync time on the settings row.
 */
class PaperlessSync
{
    /**
     * @return array<string,int> counts per kind, e.g. ['tag' => 12, ...]
     */
    public function run(?AppSettings $settings = null): array
    {
        $settings ??= AppSettings::current();
        $client = PaperlessClient::fromSettings($settings);
        if (! $settings->paperless_enabled || $client === null) {
            return [];
        }

        $counts = [];
        foreach (PaperlessTerm::KINDS as $kind) {
            $terms = $client->list($kind);
            $keepIds = [];
            foreach ($terms as $t) {
                PaperlessTerm::updateOrCreate(
                    ['kind' => $kind, 'paperless_id' => $t['paperless_id']],
                    ['name' => $t['name'], 'color' => $t['color']],
                );
                $keepIds[] = $t['paperless_id'];
            }
            // Drop terms deleted in Paperless since the last sync.
            PaperlessTerm::where('kind', $kind)
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('paperless_id', $keepIds))
                ->delete();
            $counts[$kind] = count($terms);
        }

        $settings->update(['paperless_synced_at' => Carbon::now()]);

        return $counts;
    }
}
