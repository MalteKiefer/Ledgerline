<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralised credential scrubber for log messages, error traces and backup
 * command output. The union of patterns covers both the error-recorder and
 * the backup-manager contexts.
 *
 * The one intentional behaviour change over the legacy per-class methods:
 * backup log messages now also have Bearer/token/key patterns stripped,
 * closing a potential credential-leak path.
 */
final class Redactor
{
    /**
     * Replace recognisable credential patterns in $text with ***.
     *
     * Patterns (applied in order):
     *   1. --password[=|\s]VALUE  — mysqldump long flag
     *   2. \s-pVALUE              — mysqldump short flag
     *   3. password[= :]VALUE     — key=value / JSON / URI-param style
     *   4. secret|token|key|passphrase[= :]VALUE
     *   5. scheme://user:PASS@    — URI credentials
     *   6. Bearer TOKEN           — HTTP Authorization header
     */
    public static function redact(string $text): string
    {
        $patterns = [
            '/(--password[=\s]+)\S+/i' => '$1***',
            '/(\s-p)\S+/' => '$1***',
            '/(password["\']?\s*[:=]\s*["\']?)[^"\'\s,&]+/i' => '$1***',
            '/(secret|token|key|passphrase)(["\']?\s*[:=]\s*["\']?)[^"\'\s,&]+/i' => '$1$2***',
            '/([a-z][a-z0-9+.\-]*:\/\/[^:\/\s@]+:)[^@\/\s]+@/i' => '$1***@',
            '/(Bearer\s+)[A-Za-z0-9._\-]+/i' => '$1***',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $text);
    }
}
