<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\PaperlessTerm;
use App\Models\User;
use App\Models\UserSetting;

/**
 * Paperless module contribution to per-user GDPR export and account erasure.
 * The integration is per-user: the instance URL, token and last-sync time live
 * on the user's UserSetting row (paperless_* columns), and terms synced from
 * that instance are cached in the paperless_terms table. The token is a secret
 * and is deliberately excluded from the export.
 */
class PaperlessData implements UserDataContributor
{
    public function key(): string
    {
        return 'paperless';
    }

    public function export(User $user): array
    {
        $setting = UserSetting::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first(['paperless_enabled', 'paperless_url', 'paperless_synced_at']);

        $terms = PaperlessTerm::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'kind', 'paperless_id', 'name', 'color'])
            ->toArray();

        return [
            'config' => [
                'enabled' => (bool) ($setting?->paperless_enabled ?? false),
                'url' => $setting?->paperless_url,
                'synced_at' => $setting?->paperless_synced_at,
            ],
            'terms' => $terms,
        ];
    }

    public function purge(User $user): void
    {
        UserSetting::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->update([
                'paperless_enabled' => false,
                'paperless_url' => null,
                'paperless_token' => null,
                'paperless_synced_at' => null,
            ]);

        PaperlessTerm::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->delete();
    }
}
