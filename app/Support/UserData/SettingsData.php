<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\Export;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\BlobStore;

/**
 * Settings & cross-cutting user-owned records contribution to per-user GDPR
 * export and account erasure. Carries the user's preference row (UserSetting).
 * On purge the whole UserSetting row is deleted, which supersedes
 * PaperlessData's paperless_* column reset.
 *
 * Exports are also cross-cutting: their zip parts live as blobs on the files
 * disk, so purge removes both the Export rows and those on-disk parts.
 */
class SettingsData implements UserDataContributor
{
    public function key(): string
    {
        return 'settings';
    }

    public function export(User $user): array
    {
        $setting = UserSetting::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first([
                'gallery_columns',
            ]);

        return [
            'setting' => $setting?->toArray(),
        ];
    }

    public function purge(User $user): void
    {
        UserSetting::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->delete();

        $disk = BlobStore::disk();

        Export::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->get(['id', 'files'])
            ->each(function (Export $export) use ($disk): void {
                foreach ($export->parts() as $part) {
                    $disk->delete($part['path']);
                }
                $export->delete();
            });
    }
}
