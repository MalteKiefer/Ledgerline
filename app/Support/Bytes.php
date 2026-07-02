<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Human-readable byte sizes.
 */
class Bytes
{
    /**
     * Format a byte count in the largest unit that keeps the value at or above
     * 1 (stepping up only at a full 1024), with up to two decimals and trailing
     * zeros trimmed. Bytes render as a whole number; null renders as an em dash.
     *
     * e.g. 1023 => "1023 B", 1024 => "1 KB", 1536 => "1.5 KB", 1234567890 => "1.15 GB".
     */
    public static function format(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        $number = $unit === 0
            ? number_format($value, 0)
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $number.' '.$units[$unit];
    }
}
