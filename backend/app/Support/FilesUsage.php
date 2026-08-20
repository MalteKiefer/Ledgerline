<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FileEntry;
use App\Models\FileVersion;

/**
 * Storage-usage accounting for the plaintext-relational Files core (pivot). A
 * user's stored bytes are the sum of their current file bytes plus every prior
 * version's bytes (versions occupy disk until the file is force-deleted). Rows
 * are counted regardless of the per-user owner scope and soft-delete scope
 * (trashed files still hold disk bytes), so all queries drop the global scopes
 * and filter user_id explicitly.
 */
final class FilesUsage
{
    /** Total stored bytes (files + versions) for one user. */
    public static function forUser(int $userId): int
    {
        $files = (int) FileEntry::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->sum('size');

        $versions = (int) FileVersion::query()
            ->whereIn('file_id', FileEntry::query()->withoutGlobalScopes()->where('user_id', $userId)->select('id'))
            ->sum('size');

        return $files + $versions;
    }

    /** Total stored bytes (files + versions) across all users. */
    public static function total(): int
    {
        return (int) FileEntry::query()->withoutGlobalScopes()->sum('size')
            + (int) FileVersion::query()->sum('size');
    }

    private function __construct() {}
}
