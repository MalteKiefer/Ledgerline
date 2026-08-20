// @vitest-environment jsdom

/**
 * The note body is user-authored and rendered as HTML in the app origin, so the
 * DOMPurify allowlist in renderMarkdown is the only thing between a note and
 * script execution. It had no test at all: an edit to ALLOWED_TAGS/ATTR or the
 * URI regexp could open an XSS hole and every gate would stay green.
 */
import { describe, expect, it } from 'vitest';
import { renderMarkdown } from '../markdown';

const render = (md: string) => renderMarkdown(md);

describe('renderMarkdown strips script vectors', () => {
    it('drops a script element and its content', () => {
        const out = render('<script>alert(1)</script>hi');
        expect(out).not.toContain('<script');
        expect(out).not.toContain('alert(1)');
    });

    it.each([
        ['<img src=x onerror="alert(1)">', 'onerror'],
        ['<p onclick="alert(1)">x</p>', 'onclick'],
        ['<body onload="alert(1)">x</body>', 'onload'],
        ['<div onmouseover=alert(1)>x</div>', 'onmouseover'],
    ])('drops the event handler in %s', (input, handler) => {
        expect(render(input).toLowerCase()).not.toContain(handler);
    });

    it.each([
        '[click](javascript:alert(1))',
        '[click](JaVaScRiPt:alert(1))',
        '[click](vbscript:alert(1))',
        '[click](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)',
    ])('refuses the dangerous URI in %s', (md) => {
        const out = render(md).toLowerCase();
        expect(out).not.toContain('javascript:');
        expect(out).not.toContain('vbscript:');
        expect(out).not.toContain('data:text/html');
    });

    it.each(['iframe', 'form', 'object', 'embed', 'style', 'svg', 'math', 'base', 'meta', 'link'])(
        'drops the <%s> element',
        (tag) => {
            expect(render(`<${tag}>x</${tag}>`)).not.toContain(`<${tag}`);
        },
    );

    it('drops srcdoc and formaction, which are script vectors on allowed tags', () => {
        const out = render('<img src="/a.png" srcdoc="<script>alert(1)</script>" formaction="javascript:alert(1)">');
        expect(out).not.toContain('srcdoc');
        expect(out).not.toContain('formaction');
    });
});

describe('renderMarkdown keeps what notes need', () => {
    it('keeps an https link and a mailto link', () => {
        expect(render('[a](https://example.com)')).toContain('href="https://example.com"');
        expect(render('[a](mailto:x@example.com)')).toContain('href="mailto:x@example.com"');
    });

    it('keeps a same-origin attachment image, which is how note media is embedded', () => {
        expect(render('![a](/api/v1/notes/1/attachments/2/raw)'))
            .toContain('src="/api/v1/notes/1/attachments/2/raw"');
    });

    it('keeps an inline data:image but not any other data type', () => {
        expect(render('![a](data:image/png;base64,iVBORw0KGgo=)')).toContain('data:image/png');
        expect(render('![a](data:application/pdf;base64,AAAA)')).not.toContain('data:application/pdf');
    });

    it('renders GFM tables, task lists and strikethrough', () => {
        expect(render('| a | b |\n| - | - |\n| 1 | 2 |')).toContain('<table>');
        expect(render('- [x] done')).toContain('<input');
        expect(render('~~gone~~')).toContain('<del>');
    });

    it('highlights a fenced code block with the requested language', () => {
        const out = render('```js\nconst a = 1;\n```');
        expect(out).toContain('<pre>');
        expect(out).toContain('hljs');
    });
});

describe('renderMarkdown wikilinks', () => {
    it('resolves a known title to an internal note reference, never a URL', () => {
        const out = renderMarkdown('see [[Target]]', (t) => (t === 'Target' ? 7 : null));
        expect(out).toContain('data-note-id="7"');
        expect(out).not.toContain('href=');
    });

    it('marks an unresolved title instead of linking it', () => {
        const out = renderMarkdown('see [[Nope]]', () => null);
        expect(out).toContain('ll-wikilink-missing');
    });

    it('uses the alias as the label', () => {
        expect(renderMarkdown('[[Target|shown]]', () => 7)).toContain('shown');
    });

    it('escapes HTML inside a wikilink title so the label cannot inject markup', () => {
        const out = renderMarkdown('[[<img src=x onerror=alert(1)>]]', () => null);
        // The text survives as an inert attribute value and as escaped label text;
        // what must not happen is it becoming an element or a handler. Assert that
        // through a parse, not a substring match — the raw string legitimately
        // contains the characters inside a quoted attribute.
        const host = document.createElement('div');
        host.innerHTML = out;
        expect(host.querySelector('img')).toBeNull();
        expect(host.querySelectorAll('*')).toHaveLength(2); // <p> + <span>
        for (const el of Array.from(host.querySelectorAll('*'))) {
            for (const attr of Array.from(el.attributes)) {
                expect(attr.name.startsWith('on')).toBe(false);
            }
        }
    });
});
