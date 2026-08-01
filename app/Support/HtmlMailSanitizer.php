<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;

/**
 * Minimal server-side HTML sanitiser for the outgoing invoice mail body/signature.
 * The client already DOMPurify-sanitises before submit; this is defence-in-depth so
 * a crafted PUT can never put <script>/event-handlers/javascript: into the e-mail we
 * send. Allowlist of formatting tags + safe href/src only. Not a general-purpose
 * HTML purifier — it only needs to cover the simple rich-text a signature contains.
 */
class HtmlMailSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'strong', 'i', 'em', 'u', 's', 'br', 'p', 'div', 'span',
        'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'img', 'hr',
    ];

    private const ALLOWED_ATTRS = ['href', 'src', 'alt', 'title', 'style'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument;
        // Parse as a fragment; suppress libxml warnings on partial markup.
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if (! $root instanceof DOMElement) {
            return '';
        }
        self::scrub($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function scrub(DOMElement $el): void
    {
        // Iterate a static copy — we mutate the tree while walking it.
        foreach (iterator_to_array($el->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->nodeName);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Drop the tag but keep its (scrubbed) text/children.
                self::scrub($node);
                while ($node->firstChild) {
                    $el->insertBefore($node->firstChild, $node);
                }
                $el->removeChild($node);

                continue;
            }
            // Strip every attribute not on the allowlist, and neutralise unsafe URLs.
            foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                $name = strtolower($attr->name);
                if (! in_array($name, self::ALLOWED_ATTRS, true)) {
                    $node->removeAttribute($attr->name);

                    continue;
                }
                if (($name === 'href' || $name === 'src') && ! self::safeUrl((string) $attr->value)) {
                    $node->removeAttribute($attr->name);
                }
                if ($name === 'style' && preg_match('/expression|url\s*\(|javascript:/i', (string) $attr->value)) {
                    $node->removeAttribute($attr->name);
                }
            }
            self::scrub($node);
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim($url);

        return $url !== '' && (bool) preg_match('#^(https?:|mailto:|tel:|/|\#|data:image/)#i', $url);
    }
}
