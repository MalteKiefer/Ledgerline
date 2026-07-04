<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * The set of user ids that own per-user calendars. Single source of truth for
 * the generated calendars (birthdays/anniversaries/holidays) so they are built
 * for every workspace user — not only those who happen to have a CardDAV address
 * book. This is a single-tenant app, so "owners" = all users.
 */
final class WorkspaceOwners
{
    /** @return list<int> */
    public static function userIds(): array
    {
        return User::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
