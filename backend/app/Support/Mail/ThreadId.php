<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * Derives a stable conversation id for an archived message so the reader can
 * group a thread. The root is the first References id (the thread's origin),
 * else In-Reply-To, else the message's own Message-Id — so a message and every
 * reply that chains back to it converge on the same id. When no message ids are
 * present at all it falls back to a normalised subject (Re:/Fwd:/AW:/WG:
 * prefixes stripped). Returns null only when nothing usable exists.
 */
final class ThreadId
{
    public static function for(?string $references, ?string $inReplyTo, ?string $messageId, ?string $subject): ?string
    {
        $root = self::firstToken($references) ?? self::clean($inReplyTo) ?? self::clean($messageId);
        if ($root !== null) {
            return 'mid:'.sha1($root);
        }

        $subj = self::normalizeSubject($subject);

        return $subj === '' ? null : 'subj:'.sha1($subj);
    }

    private static function firstToken(?string $references): ?string
    {
        if ($references === null) {
            return null;
        }
        if (preg_match('/<[^>]+>/', $references, $m) === 1) {
            return self::clean($m[0]);
        }

        return self::clean($references);
    }

    private static function clean(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $v = trim(trim($id), '<>');
        $v = trim($v);

        return $v === '' ? null : strtolower($v);
    }

    private static function normalizeSubject(?string $subject): string
    {
        $s = strtolower(trim((string) $subject));
        // Strip repeated reply/forward prefixes (EN + DE).
        while (preg_match('/^\s*(re|fwd|fw|aw|wg)\s*:\s*/i', $s) === 1) {
            $s = (string) preg_replace('/^\s*(re|fwd|fw|aw|wg)\s*:\s*/i', '', $s, 1);
        }

        return trim($s);
    }
}
