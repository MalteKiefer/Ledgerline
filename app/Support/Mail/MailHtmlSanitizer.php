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
 * the raw HTML directly. Conservative by design (Phase 1 renders this inline;
 * the richer sandboxed-iframe + remote-content gating is Phase 2):
 *
 *   - Dangerous elements are removed WITH their subtree: script, style, iframe,
 *     object, embed, applet, frame(set), meta, base, link, form and all form
 *     controls, svg, math, template, noscript, title, head.
 *   - Every `on*` event-handler attribute is stripped.
 *   - href/src carrying javascript:/vbscript:/data: (except data:image/* on an
 *     <img>) are dropped — no script execution, no data-URI HTML smuggling.
 *   - REMOTE resource loads are neutralised: an http(s)/protocol-relative src on
 *     img/audio/video/source/track/input/poster is removed (tracking-pixel +
 *     leak protection; remote loading is re-enabled behind an explicit opt-in in
 *     Phase 2's body iframe). cid: refs are left for Phase 2 to resolve.
 *   - `style` attributes and the `<style>` element are stripped (CSS url()
 *     remote-fetch / exfil vector).
 *   - Anchors are rewritten target=_blank rel="noopener noreferrer nofollow".
 *
 * Pure DOMDocument (ext-dom, always present) — no new dependency. Any parse
 * failure fails safe to null (the reader then shows the plaintext body).
 */
final class MailHtmlSanitizer
{
    /** Elements dropped together with their entire subtree. */
    private const STRIP_SUBTREE = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet', 'frame', 'frameset',
        'meta', 'base', 'link', 'form', 'input', 'button', 'select', 'option', 'textarea',
        'svg', 'math', 'template', 'noscript', 'title', 'head',
    ];

    /** Attributes whose value must be a safe URL (or is dropped). */
    private const URL_ATTRS = ['href', 'src', 'poster', 'background', 'srcset', 'action', 'formaction'];

    public function sanitize(?string $html): ?string
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

            $this->walk($dom->documentElement);

            $body = $dom->getElementsByTagName('body')->item(0);
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

    private function walk(?DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            // Descend into non-element containers (e.g. the document node).
            if ($node !== null) {
                foreach (iterator_to_array($node->childNodes) as $child) {
                    $this->walk($child);
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
            $this->walk($child);
        }

        $this->scrubAttributes($node);
    }

    private function scrubAttributes(DOMElement $el): void
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

            // Event handlers + inline CSS.
            if (str_starts_with($lower, 'on') || $lower === 'style') {
                $el->removeAttribute($name);

                continue;
            }

            if (in_array($lower, self::URL_ATTRS, true)) {
                if (! $this->safeUrl($value, $isImg && in_array($lower, ['src', 'poster'], true), $remoteTag && in_array($lower, ['src', 'srcset', 'poster', 'background'], true))) {
                    $el->removeAttribute($name);
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
     * Whether a URL attribute value is safe to keep.
     *
     * @param  bool  $allowDataImage  data:image/* is allowed (inline <img>).
     * @param  bool  $isRemoteResource  true when this attribute triggers an
     *                                  automatic resource load (img/media src) —
     *                                  remote http(s)/protocol-relative is stripped.
     */
    private function safeUrl(string $value, bool $allowDataImage, bool $isRemoteResource): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }

        $scheme = $this->scheme($v);

        // Explicitly dangerous schemes — always drop.
        if (in_array($scheme, ['javascript', 'vbscript'], true)) {
            return false;
        }

        if ($scheme === 'data') {
            return $allowDataImage && preg_match('#^data:image/(png|jpe?g|gif|webp|bmp)[;,]#i', $v) === 1;
        }

        // Remote auto-loading resource (tracking pixel / leak): neutralise unless
        // the reader later opts in (Phase 2). Covers http(s) and protocol-relative.
        if ($isRemoteResource && ($scheme === 'http' || $scheme === 'https' || str_starts_with($v, '//'))) {
            return false;
        }

        // cid: (inline attachment ref) and relative/anchor/mailto links are kept.
        return true;
    }

    private function scheme(string $url): ?string
    {
        if (preg_match('#^\s*([a-z][a-z0-9+.\-]*):#i', $url, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }
}
