<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\GalleryPhoto;

/**
 * Storage-usage accounting for the plaintext-relational Gallery core (pivot). A
 * user's stored gallery bytes are the sum of their photo `size` (original bytes)
 * across every photo they own — mirrors FilesUsage. Trashed photos still hold
 * disk bytes until force-deleted, and the metric must be user-agnostic in console
 * contexts, so all queries drop the global scopes (owner + soft-delete) and count
 * every row. (Rendition bytes — thumb/medium/motion — are small and not tracked
 * against quota, matching the original blob-ledger `size` accounting.)
 */
final class GalleryUsage
{
    /** Total stored gallery bytes for one user. */
    public static function forUser(int $userId): int
    {
        return (int) GalleryPhoto::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->sum('size');
    }

    /** Total stored gallery bytes across all users. */
    public static function total(): int
    {
        return (int) GalleryPhoto::query()->withoutGlobalScopes()->sum('size');
    }

    /**
     * Per-user stored gallery bytes, keyed by user id (one grouped query so an
     * admin listing does not run N queries).
     *
     * @return array<int, int>
     */
    public static function byUser(): array
    {
        $int = static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0;
        $out = [];

        $rows = GalleryPhoto::query()
            ->withoutGlobalScopes()
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(size) AS bytes')
            ->pluck('bytes', 'user_id');

        foreach ($rows as $uid => $bytes) {
            $out[$int($uid)] = $int($bytes);
        }

        return $out;
    }

    private function __construct() {}
}
