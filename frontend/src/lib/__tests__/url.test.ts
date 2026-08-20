/**
 * Contact and partner URLs reach the app from imported vCards and from CardDAV
 * replicas — that is, from other people's servers. Binding one to href without
 * a scheme check hands a crafted card script execution in the app's own origin.
 */
import { describe, expect, it } from 'vitest';
import { safeHref } from '../url';

describe('safeHref', () => {
    it.each([
        'javascript:alert(1)',
        'JaVaScRiPt:alert(1)',
        '  javascript:alert(1)',
        'vbscript:alert(1)',
        'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
        'file:///etc/passwd',
    ])('refuses %s', (value) => {
        expect(safeHref(value)).toBeUndefined();
    });

    it.each([
        ['https://example.com/x?a=1', 'https://example.com/x?a=1'],
        ['http://example.com', 'http://example.com'],
        ['mailto:a@example.com', 'mailto:a@example.com'],
        ['tel:+4917541828', 'tel:+4917541828'],
    ])('passes %s through unchanged', (value, expected) => {
        expect(safeHref(value)).toBe(expected);
    });

    it('upgrades a bare hostname, which is what people type into a website field', () => {
        expect(safeHref('example.com')).toBe('https://example.com');
        expect(safeHref('www.example.co.uk/path')).toBe('https://www.example.co.uk/path');
    });

    it('yields nothing for empty input, so the anchor stays inert', () => {
        expect(safeHref('')).toBeUndefined();
        expect(safeHref(null)).toBeUndefined();
        expect(safeHref(undefined)).toBeUndefined();
        expect(safeHref('   ')).toBeUndefined();
    });

    it('does not mistake a scheme-less oddity for a hostname', () => {
        expect(safeHref('not a url')).toBeUndefined();
        expect(safeHref('javascript:alert(1)//example.com')).toBeUndefined();
    });
});
