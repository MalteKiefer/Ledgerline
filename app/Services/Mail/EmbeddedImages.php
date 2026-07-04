<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Webklex\PHPIMAP\Attachment;

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
     * Replace `cid:<id>` references in $html with data: URIs built from the
     * matching embedded image parts.
     *
     * @param  iterable<Attachment>  $attachments
     */
    public static function inline(?string $html, iterable $attachments): ?string
    {
        if ($html === null || $html === '' || ! str_contains($html, 'cid:')) {
            return $html;
        }

        foreach ($attachments as $a) {
            $id = trim((string) ($a->id ?? ''));
            $mime = (string) ($a->getMimeType() ?? '');
            if ($id === '' || ! str_starts_with($mime, 'image/')) {
                continue;
            }

            $content = $a->getContent();
            if (! is_string($content) || $content === '' || strlen($content) > self::MAX_BYTES) {
                continue;
            }

            $dataUri = 'data:'.$mime.';base64,'.base64_encode($content);
            $html = str_replace(['cid:'.$id, 'cid:<'.$id.'>'], $dataUri, $html);
        }

        return $html;
    }

    /**
     * Whether an attachment is an embedded inline image (rendered in the body,
     * so it should not also be listed as a downloadable attachment).
     */
    public static function isInlineImage(Attachment $a): bool
    {
        return $a->disposition === 'inline'
            && str_starts_with((string) ($a->getMimeType() ?? ''), 'image/');
    }
}
