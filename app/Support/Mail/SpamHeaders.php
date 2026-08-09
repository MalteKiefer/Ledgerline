<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * Server-side spam detection from RFC822 headers — reads only the standard
 * spam-marker headers a receiving mail server stamps (SpamAssassin / Rspamd /
 * generic X-Spam-*). Never inspects message content.
 */
class SpamHeaders
{
    /**
     * Parse just the header block of a raw RFC822 message into a lower-cased
     * name => value map (first occurrence wins, continuations unfolded).
     *
     * @return array<string, string>
     */
    public static function parse(string $raw): array
    {
        $norm = str_replace("\r\n", "\n", $raw);
        $sep = strpos($norm, "\n\n");
        $block = $sep === false ? $norm : substr($norm, 0, $sep);
        // Unfold continuation lines (leading space/tab continues the previous).
        $block = preg_replace('/\n[ \t]+/', ' ', $block) ?? $block;

        $headers = [];
        foreach (explode("\n", $block) as $line) {
            $idx = strpos($line, ':');
            if ($idx === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $idx)));
            if ($name === '' || isset($headers[$name])) {
                continue;
            }
            $headers[$name] = trim(substr($line, $idx + 1));
        }

        return $headers;
    }

    /** @param array<string, string> $headers */
    public static function isSpam(array $headers): bool
    {
        if (strtolower($headers['x-spam-flag'] ?? '') === 'yes') {
            return true;
        }
        if (preg_match('/^\s*yes\b/i', $headers['x-spam-status'] ?? '') === 1) {
            return true;
        }
        if (str_contains(strtolower($headers['x-spamd-result'] ?? ''), 'default: true')) {
            return true;
        }
        if (preg_match('/\bjunk\b/i', $headers['x-spam'] ?? '') === 1) {
            return true;
        }

        return false;
    }

    /** Convenience: detect spam directly from raw RFC822 bytes/string. */
    public static function isSpamRaw(string $raw): bool
    {
        return self::isSpam(self::parse($raw));
    }
}
