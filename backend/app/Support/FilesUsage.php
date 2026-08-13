<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FileEntry;
use App\Models\FileVersion;
use Illuminate\Support\Facades\DB;

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

    /**
     * Per-user stored bytes (files + versions), keyed by user id. One grouped
     * query per table so an admin listing does not run N queries.
     *
     * @return array<int, int>
     */
    public static function byUser(): array
    {
        $int = static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0;
        $out = [];

        $files = FileEntry::query()
            ->withoutGlobalScopes()
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(size) AS bytes')
            ->pluck('bytes', 'user_id');
        foreach ($files as $uid => $bytes) {
            $out[$int($uid)] = $int($bytes);
        }

        $versions = DB::table('file_versions')
            ->join('files', 'file_versions.file_id', '=', 'files.id')
            ->groupBy('files.user_id')
            ->selectRaw('files.user_id AS user_id, SUM(file_versions.size) AS bytes')
            ->pluck('bytes', 'user_id');
        foreach ($versions as $uid => $bytes) {
            $out[$int($uid)] = ($out[$int($uid)] ?? 0) + $int($bytes);
        }

        return $out;
    }

    private function __construct() {}
}
