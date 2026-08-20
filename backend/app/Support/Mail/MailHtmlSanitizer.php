<?php

declare(strict_types=1);

namespace App\Support\Mail;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use Throwable;

/**
 * Server-side sanitiser for archived-mail HTML bodies. Runs at ingest and
 * stores the result in mail_messages.html_sanitized so the reader never touches
 * the raw HTML directly. It preserves safe email styling in a sandboxed iframe
 * while keeping remote content blocked unless the caller explicitly permits it
 * for one sandboxed reader request:
 *
 *   - Dangerous elements are removed WITH their subtree: script, iframe,
 *     object, embed, applet, frame(set), meta, base, link, form and all form
 *     controls, svg, math, template, noscript, title, head.
 *   - Every `on*` event-handler attribute is stripped.
 *   - href/src carrying javascript:/vbscript:/data: (except data:image/* on an
 *     <img>) are dropped — no script execution, no data-URI HTML smuggling.
 *   - REMOTE resource loads are neutralised: an http(s)/protocol-relative src on
 *     img/audio/video/source/track/input/poster is removed (tracking-pixel +
 *     leak protection; the body endpoint can permit it for one explicit reader
 *     request). cid: refs are left for the body endpoint to resolve.
 *   - Safe inline CSS and style blocks are retained after stripping external
 *     imports, font faces, url() fetches, and legacy executable CSS features.
 *   - Anchors are rewritten target=_blank rel="noopener noreferrer nofollow".
 *
 * Pure DOMDocument (ext-dom, always present) — no new dependency. Any parse
 * failure fails safe to null (the reader then shows the plaintext body).
 */
final class MailHtmlSanitizer
{
    /** Elements dropped together with their entire subtree. */
    private const STRIP_SUBTREE = [
        'script', 'iframe', 'object', 'embed', 'applet', 'frame', 'frameset',
        'meta', 'base', 'link', 'form', 'input', 'button', 'select', 'option', 'textarea',
        'svg', 'math', 'template', 'noscript', 'title', 'head',
    ];

    /** Attributes whose value must be a safe URL (or is dropped). */
    private const URL_ATTRS = ['href', 'src', 'poster', 'background', 'srcset', 'action', 'formaction'];

    /**
     * @param  bool  $allowRemote  Keep http(s)/protocol-relative resource src
     *                             for an explicit, sandboxed reader action only.
     * @param  array<string, string>  $cidMap  normalized Content-Id → data: URI;
     *                                         a `cid:<id>` img src is rewritten to its data:
     *                                         URI (or dropped when unresolved).
     */
    public function sanitize(?string $html, bool $allowRemote = false, array $cidMap = []): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        try {
            $dom = new DOMDocument;
            $prev = libxml_use_internal_errors(true);
            // Force UTF-8 interpretation; LIBXML_NONET blocks any external entity fetch.
            $loaded = $dom->loadHTML(
                '<?xml encoding="utf-8"?><body>'.$html.'</body>',
                LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
            );
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            if ($loaded === false) {
                return null;
            }

            // Email templates commonly keep presentation CSS in <head>, while
            // the stored fragment intentionally contains body children only.
            // Move styles into that fragment before dropping <head> itself.
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body instanceof DOMElement) {
                foreach (iterator_to_array($dom->getElementsByTagName('style')) as $style) {
                    if ($style instanceof DOMElement && strtolower($style->parentNode?->nodeName ?? '') === 'head') {
                        $body->insertBefore($style, $body->firstChild);
                    }
                }
            }

            $this->walk($dom->documentElement, $allowRemote, $cidMap);

            $out = '';
            if ($body !== null) {
                foreach (iterator_to_array($body->childNodes) as $child) {
                    $out .= $dom->saveHTML($child);
                }
            }

            $out = trim($out);

            return $out === '' ? null : $out;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param  array<string, string>  $cidMap */
    private function walk(?DOMNode $node, bool $allowRemote, array $cidMap): void
    {
        if (! $node instanceof DOMElement) {
            // Descend into non-element containers (e.g. the document node).
            if ($node !== null) {
                foreach (iterator_to_array($node->childNodes) as $child) {
                    $this->walk($child, $allowRemote, $cidMap);
                }
            }

            return;
        }

        // Snapshot children first — the list mutates as we remove nodes.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), self::STRIP_SUBTREE, true)) {
                $node->removeChild($child);

                continue;
            }
            $this->walk($child, $allowRemote, $cidMap);
        }

        if (strtolower($node->nodeName) === 'style') {
            $node->textContent = $this->sanitizeCss($node->textContent ?? '');
        }

        $this->scrubAttributes($node, $allowRemote, $cidMap);
    }

    /** @param  array<string, string>  $cidMap */
    private function scrubAttributes(DOMElement $el, bool $allowRemote, array $cidMap): void
    {
        $tag = strtolower($el->nodeName);
        $isImg = $tag === 'img';
        $remoteTag = in_array($tag, ['img', 'audio', 'video', 'source', 'track', 'input'], true);

        // Collect first — removing during iteration over $el->attributes is unsafe.
        $names = [];
        foreach (iterator_to_array($el->attributes) as $attr) {
            if ($attr instanceof DOMAttr) {
                $names[] = $attr->nodeName;
            }
        }

        foreach ($names as $name) {
            $lower = strtolower($name);
            $value = $el->getAttribute($name);

            // Event handlers are executable content.
            if (str_starts_with($lower, 'on')) {
                $el->removeAttribute($name);

                continue;
            }

            if ($lower === 'style') {
                $safeCss = $this->sanitizeCss($value);
                if ($safeCss === '') {
                    $el->removeAttribute($name);
                } else {
                    $el->setAttribute($name, $safeCss);
                }

                continue;
            }

            if (in_array($lower, self::URL_ATTRS, true)) {
                $resolved = $this->resolveUrl(
                    $value,
                    allowDataImage: $isImg && in_array($lower, ['src', 'poster'], true),
                    isRemoteResource: $remoteTag && in_array($lower, ['src', 'srcset', 'poster', 'background'], true),
                    allowRemote: $allowRemote,
                    cidMap: $isImg && in_array($lower, ['src', 'poster'], true) ? $cidMap : [],
                );
                if ($resolved === null) {
                    $el->removeAttribute($name);
                } elseif ($resolved !== $value) {
                    $el->setAttribute($name, $resolved);
                }
            }
        }

        // Harden anchors that survived.
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /**
     * Resolve a URL attribute value: return the value to keep (possibly
     * rewritten), or null to drop the attribute.
     *
     * @param  bool  $allowDataImage  data:image/* is allowed (inline <img>).
     * @param  bool  $isRemoteResource  true when this attribute triggers an
     *                                  automatic resource load (img/media src).
     * @param  bool  $allowRemote  keep remote http(s)/protocol-relative resource
     *                             loads (reader opted into remote content).
     * @param  array<string, string>  $cidMap  normalized Content-Id → data: URI
     *                                         for rewriting a `cid:` image src.
     */
    private function resolveUrl(string $value, bool $allowDataImage, bool $isRemoteResource, bool $allowRemote, array $cidMap): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        $scheme = $this->scheme($v);

        // Explicitly dangerous schemes — always drop.
        if (in_array($scheme, ['javascript', 'vbscript'], true)) {
            return null;
        }

        if ($scheme === 'data') {
            return ($allowDataImage && preg_match('#^data:image/(png|jpe?g|gif|webp|bmp)[;,]#i', $v) === 1) ? $v : null;
        }

        // cid: inline attachment reference. Rewrite to its data: URI when the
        // caller supplied one (the body endpoint); otherwise drop (a bare cid:
        // is meaningless to a browser).
        if ($scheme === 'cid') {
            $id = trim(substr($v, 4), '<>');
            $decoded = rawurldecode($id);

            return $cidMap[$id] ?? $cidMap[$decoded] ?? null;
        }

        // Remote auto-loading resource (tracking pixel / leak): neutralise unless
        // the reader opted in. Covers http(s) and protocol-relative.
        if ($isRemoteResource && ($scheme === 'http' || $scheme === 'https' || str_starts_with($v, '//'))) {
            return $allowRemote ? $v : null;
        }

        // Relative / anchor / mailto links and non-loading hrefs are kept.
        return $v;
    }

    private function scheme(string $url): ?string
    {
        if (preg_match('#^\s*([a-z][a-z0-9+.\-]*):#i', $url, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }

    /**
     * Remove CSS features that can fetch a remote resource or execute legacy
     * code. Keyword matching on CSS only holds once the two constructs that let
     * an author spell a keyword differently are gone first: CSS escapes (a
     * backslash-escaped url( still reads as a fetch to a browser) and comments
     * placed inside a property value. Backslashes are therefore dropped
     * outright - mail CSS has no legitimate need for an escape, and leaving one
     * in is what turns every filter below into a bypass. The reader's
     * default-src 'none' CSP stays the enforcing layer; this keeps the stored
     * markup from depending on it alone.
     */
    private function sanitizeCss(string $css): string
    {
        $css = $this->decodeCssEscapes($this->stripCssComments($css));
        // A decoded escape can spell a comment sequence; strip once more so the
        // keyword patterns below see the same text a browser tokenises.
        $css = $this->stripCssComments($css);
        // Resource-loading at-rules, with or without a terminating semicolon,
        // and with or without a block body.
        $css = preg_replace('#@(?:import|namespace|charset)[^;{}]*(?:;|$)#i', '', $css) ?? '';
        $css = preg_replace('#@font-face\s*\{[^{}]*\}?#is', '', $css) ?? '';
        $css = preg_replace('#(?:url|image-set|-webkit-image-set|element)\s*\(\s*[^)]*\)?#i', 'none', $css) ?? '';
        $css = preg_replace('#(?:expression\s*\(|-moz-binding\s*:|behavior\s*:)#i', '', $css) ?? '';

        return trim(str_replace(['<', '>'], '', $css));
    }

    private function stripCssComments(string $css): string
    {
        return preg_replace('#/\*.*?(?:\*/|$)#s', '', $css) ?? '';
    }

    /**
     * Resolve CSS escapes to the character a browser would see, so the keyword
     * patterns cannot be spelled around: the hex form is the interesting one,
     * since it can spell the "u" of url(. Anything outside ASCII cannot form a
     * keyword and is dropped rather than re-encoded.
     */
    private function decodeCssEscapes(string $css): string
    {
        $hex = preg_replace_callback(
            '/\x5c([0-9a-fA-F]{1,6})[ \t\r\n\f]?/',
            static function (array $m): string {
                $code = (int) hexdec($m[1]);

                return $code > 0 && $code < 128 ? chr($code) : '';
            },
            $css,
        ) ?? '';

        // Any remaining escape stands for the character that follows it.
        return preg_replace('/\x5c(.)/s', '$1', $hex) ?? '';
    }
}
