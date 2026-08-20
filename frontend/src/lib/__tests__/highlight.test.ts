// @vitest-environment jsdom

/**
 * The output of highlightCode goes into the file preview through v-html, so the
 * claim that highlight.js escapes the source itself is a security property, not
 * a formatting detail. Pinned here alongside the filename-to-language mapping.
 */
import { describe, expect, it } from 'vitest';
import { highlightCode, languageForFilename } from '../highlight';

describe('languageForFilename', () => {
    it.each([
        ['app.ts', 'typescript'],
        ['app.tsx', 'typescript'],
        ['main.py', 'python'],
        ['index.html', 'xml'],
        ['App.vue', 'xml'],
        ['style.scss', 'scss'],
        ['deploy.sh', 'bash'],
        ['config.toml', 'ini'],
        ['notes.md', 'markdown'],
        ['change.patch', 'diff'],
    ])('maps %s to %s', (name, lang) => {
        expect(languageForFilename(name)).toBe(lang);
    });

    it('recognises the extensionless build files', () => {
        expect(languageForFilename('Dockerfile')).toBe('dockerfile');
        expect(languageForFilename('makefile')).toBe('makefile');
    });

    it('accepts a highlight.js language name used directly as an extension', () => {
        expect(languageForFilename('snippet.rust')).toBe('rust');
    });

    it('returns null for an unknown suffix so the caller auto-detects', () => {
        expect(languageForFilename('data.bin')).toBeNull();
        expect(languageForFilename('noextension')).toBeNull();
    });
});

describe('highlightCode', () => {
    // The escaped source legitimately still reads "onerror=alert(1)" as text, so
    // assert through a parse: nothing may become an element or an attribute.
    const onlyTokenSpans = (html: string) => {
        const host = document.createElement('div');
        host.innerHTML = html;
        const elements = Array.from(host.querySelectorAll('*'));
        expect(elements.every((el) => el.tagName === 'SPAN')).toBe(true);
        for (const el of elements) {
            for (const attr of Array.from(el.attributes)) expect(attr.name).toBe('class');
        }
        return host;
    };

    it('escapes source that looks like markup, since the result is rendered as HTML', () => {
        const host = onlyTokenSpans(highlightCode('const a = "<img src=x onerror=alert(1)>";', 'a.ts'));
        expect(host.querySelector('img')).toBeNull();
        expect(host.textContent).toContain('<img src=x onerror=alert(1)>');
    });

    it('escapes a script tag in an auto-detected file too', () => {
        const host = onlyTokenSpans(highlightCode('<script>alert(1)</script>', 'unknown.bin'));
        expect(host.querySelector('script')).toBeNull();
        expect(host.textContent).toContain('<script>alert(1)</script>');
    });

    it('emits highlight.js token markup for a known language', () => {
        expect(highlightCode('const a = 1;', 'a.ts')).toContain('hljs-keyword');
    });
});
