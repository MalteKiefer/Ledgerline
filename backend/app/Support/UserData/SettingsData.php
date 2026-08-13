<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\User;
use App\Models\UserSetting;

/**
 * Settings & cross-cutting user-owned records contribution to per-user GDPR
 * export and account erasure. Carries the user's preference row (UserSetting).
 * On purge the whole UserSetting row is deleted, which supersedes
 * PaperlessData's paperless_* column reset.
 */
class SettingsData implements UserDataContributor
{
    public function key(): string
    {
        return 'settings';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        // Export the user's real settings (display prefs + company/invoice identity).
        // toArray() honours the model's #[Hidden], so the operative secrets (paperless
        // token, company SMTP creds) are never included in the GDPR export.
        $setting = UserSetting::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first();

        return [
            'setting' => $setting?->toArray(),
        ];
    }

    public function purge(User $user): void
    {
        UserSetting::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->delete();
    }
}
