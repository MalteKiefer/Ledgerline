<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Inlines an email's embedded (cid:) images into its HTML as data: URIs, so
 * they render in the message body instead of appearing only as attachments.
 * Remote (http/https) images stay untouched — those are blocked/opt-in by the
 * renderer. Used by both the live reader and the archive.
 */
final class EmbeddedImages
{
    /** Skip inlining images larger than this (keep the HTML from ballooning). */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * The set of Content-IDs referenced by `cid:` in the HTML (lowercased).
     * An attachment whose id is in this set is an embedded image and should be
     * inlined into the body rather than listed as a separate attachment —
     * regardless of its Content-Disposition (Gmail marks them "attachment").
     *
     * @return list<string>
     */
    public static function referencedCids(?string $html): array
    {
        if (! is_string($html) || ! str_contains($html, 'cid:')) {
            return [];
        }

        preg_match_all('/cid:<?([a-z0-9!#$%&\'*+\/=?^_`{|}~.@-]+)>?/i', $html, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
    }

    /**
     * Replace every `cid:<id>` reference to the given attachment with a data:
     * URI built from its bytes. Returns the HTML unchanged if the part has no
     * usable content. $a is a webklex Attachment (loosely typed so it can be
     * stubbed in tests).
     */
    public static function embed(?string $html, object $a): ?string
    {
        if ($html === null) {
            return $html;
        }
        $id = trim((string) ($a->id ?? ''));
        if ($id === '') {
            return $html;
        }

        $content = $a->getContent();
        if (! is_string($content) || $content === '' || strlen($content) > self::MAX_BYTES) {
            return $html;
        }

        // An <img cid:> target is an image; fall back to image/png when the
        // part's declared type is missing/generic so the browser still renders.
        $mime = (string) ($a->getMimeType() ?? '');
        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/png';
        }

        $dataUri = 'data:'.$mime.';base64,'.base64_encode($content);

        return preg_replace('/cid:<?'.preg_quote($id, '/').'>?/i', $dataUri, $html);
    }
}
