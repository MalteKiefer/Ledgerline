<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\Export;
use App\Models\PublicShare;
use App\Models\ResourceShare;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\BlobStore;

/**
 * Settings & cross-cutting user-owned records contribution to per-user GDPR
 * export and account erasure. Carries the user's preference row (UserSetting)
 * and the shares they created — private resource shares and public tokenised
 * links. Secrets are deliberately excluded from the export: the paperless
 * token/URL (encrypted), the resource_shares are non-secret, and the public
 * share password is hashed. On purge the whole UserSetting row is deleted,
 * which supersedes PaperlessData's paperless_* column reset. Purge also removes
 * resource shares where the erased user is the recipient (shared_with_user_id),
 * not just the owner, so no grant dangles against the deleted user.
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

        $resourceShares = ResourceShare::query()
            ->where('owner_id', $user->id)
            ->orderBy('id')
            ->get([
                'id',
                'shareable_type',
                'shareable_id',
                'shared_with_user_id',
                'permission',
                'created_at',
                'updated_at',
            ])
            ->toArray();

        // Never export the raw token: it is a live 48-char bearer credential that
        // grants access to the shared resource. Emit a non-reversible reference
        // and a has_password flag instead.
        $publicShares = PublicShare::query()
            ->where('owner_id', $user->id)
            ->orderBy('id')
            ->get([
                'id',
                'shareable_type',
                'shareable_id',
                'expires_at',
                'password',
                'created_at',
                'updated_at',
            ])
            ->map(fn (PublicShare $share): array => [
                'id' => $share->id,
                'shareable_type' => $share->shareable_type,
                'shareable_id' => $share->shareable_id,
                'expires_at' => $share->expires_at,
                'has_password' => $share->password !== null,
                'created_at' => $share->created_at,
                'updated_at' => $share->updated_at,
            ])
            ->toArray();

        return [
            'setting' => $setting?->toArray(),
            'resource_shares' => $resourceShares,
            'public_shares' => $publicShares,
        ];
    }

    public function purge(User $user): void
    {
        UserSetting::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->delete();

        ResourceShare::query()
            ->where('owner_id', $user->id)
            ->delete();

        // Grants where the erased user is the recipient of someone else's
        // resource; leaving them would dangle against a deleted user.
        ResourceShare::query()
            ->where('shared_with_user_id', $user->id)
            ->delete();

        PublicShare::query()
            ->where('owner_id', $user->id)
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
