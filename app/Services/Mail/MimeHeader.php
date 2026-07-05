<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Decodes RFC 2047 "encoded-word" header values (e.g. subject / display names)
 * into plain UTF-8 text.
 *
 * The webklex header decoder does not reliably decode multi-word encoded-word
 * runs (e.g. two adjacent =?UTF-8?Q?...?= tokens produced by many mailers), and
 * when a message is parsed from a raw .eml the subject/display names can surface
 * as the raw "=?UTF-8?Q?...?=" text. This normalises them.
 *
 * It only touches strings that actually contain an encoded-word — running
 * iconv_mime_decode() over already-decoded UTF-8 would strip multibyte
 * characters, so guarding on the encoded-word marker is required for
 * correctness (and makes the call idempotent).
 */
final class MimeHeader
{
    /** Matches an RFC 2047 encoded-word: =?charset?B|Q?text?= */
    private const ENCODED_WORD = '/=\?[^?]+\?[bBqQ]\?[^?]*\?=/';

    public static function decode(?string $raw): string
    {
        $raw = (string) $raw;
        if ($raw === '' || ! preg_match(self::ENCODED_WORD, $raw)) {
            return $raw;
        }

        // CONTINUE_ON_ERROR keeps any non-encoded tail; folded runs and adjacent
        // encoded-words are joined per RFC 2047.
        $decoded = @iconv_mime_decode($raw, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if ($decoded === false || $decoded === '') {
            $decoded = mb_decode_mimeheader($raw);
        }

        return $decoded;
    }
}
