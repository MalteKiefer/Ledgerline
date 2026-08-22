<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\GalleryPhoto;

/**
 * Combined Files+Gallery storage-usage accounting against the one workspace-wide
 * cap (config('files.quota_mb'), overlaid from app_settings — there is no
 * per-user quota anymore, see the 2026_12_04_160000 drop migration). Both
 * modules share a single budget, so "used" must sum both regardless of which
 * controller is asking; FilesController and GalleryController each had their
 * own copy of this formula before (FilesController's omitted Gallery bytes
 * entirely, silently under-enforcing the cap on the Files upload path — fixed
 * by having both delegate here instead of duplicating it a third time).
 */
final class StorageUsage
{
    /** Bytes occupied by a user's files (incl. version history) plus gallery photos, all incl. trashed. */
    public static function bytesForUser(int $userId): int
    {
        return FilesUsage::forUser($userId) + self::galleryBytesForUser($userId);
    }

    /** Bytes occupied by a user's gallery photos and videos, incl. trashed. */
    public static function galleryBytesForUser(int $userId): int
    {
        return (int) GalleryPhoto::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $userId)->sum('size');
    }

    /** The configured combined cap in bytes, or null when unlimited. */
    public static function quotaBytes(): ?int
    {
        $mb = config('files.quota_mb');
        $mb = is_numeric($mb) ? (int) $mb : 0;

        return $mb <= 0 ? null : $mb * 1024 * 1024;
    }

    /**
     * The usage snapshot shape used across API/page payloads.
     *
     * `used` is what the quota applies to; `files` and `gallery` break it down,
     * because "how much is the gallery using" is a question the total cannot
     * answer and a client would otherwise have to guess at (the desktop client
     * did guess, and showed 0 B for both). Both queries already ran to produce
     * the total, so the breakdown costs nothing extra.
     *
     * @return array{used: int, files: int, gallery: int, quota: int|null}
     */
    public static function snapshotForUser(int $userId): array
    {
        $files = FilesUsage::forUser($userId);
        $gallery = self::galleryBytesForUser($userId);

        return [
            'used' => $files + $gallery,
            'files' => $files,
            'gallery' => $gallery,
            'quota' => self::quotaBytes(),
        ];
    }

    /** True (with a 413 already built) when `$incoming` more bytes would exceed the cap. */
    public static function wouldExceed(int $userId, int $incoming): bool
    {
        $quota = self::quotaBytes();

        return $quota !== null && self::bytesForUser($userId) + $incoming > $quota;
    }

    private function __construct() {}
}
