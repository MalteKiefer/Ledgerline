<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The real ceiling for a single whole-request upload, derived from the live PHP
 * ini (upload_max_filesize / post_max_size). App-level "max upload" validation
 * is clamped to this so a misconfiguration (app allows more than PHP accepts)
 * degrades to a clean 413 instead of a confusing empty-request failure at the
 * PHP layer. Large files use the chunked path (8 MiB parts) and are unaffected.
 */
final class UploadLimits
{
    /**
     * The largest single uploaded file PHP will accept, in bytes (0 = unlimited).
     * That is upper-bounded by upload_max_filesize; post_max_size bounds the whole
     * request (file + fields) and is normally >= upload_max_filesize, so it only
     * binds when it is the smaller of the two.
     */
    public static function phpMaxBytes(): int
    {
        $upload = self::iniBytes('upload_max_filesize');
        $post = self::iniBytes('post_max_size');
        $values = array_filter([$upload, $post], static fn (int $v): bool => $v > 0);

        return $values === [] ? 0 : min($values);
    }

    /** Clamp a requested max (in KiB) to what PHP will actually accept for one file. */
    public static function clampKb(int $requestedKb): int
    {
        $php = self::phpMaxBytes();
        if ($php <= 0) {
            return $requestedKb;
        }
        $phpKb = max(1, (int) floor($php / 1024));

        return $requestedKb > 0 ? min($requestedKb, $phpKb) : $phpKb;
    }

    private static function iniBytes(string $key): int
    {
        $raw = trim((string) ini_get($key));
        if ($raw === '') {
            return 0;
        }
        $unit = strtolower($raw[strlen($raw) - 1]);
        $n = (int) $raw;

        return match ($unit) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
