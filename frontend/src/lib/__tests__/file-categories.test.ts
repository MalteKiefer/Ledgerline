/**
 * Drives the icon, the tint and the preview decision for every row in the file
 * browser. Pure mapping, so the cases worth pinning are the ambiguous ones and
 * the fallbacks, not the hundred obvious extensions.
 */
import { describe, expect, it } from 'vitest';
import { categoryMsym, categoryTint, fileCategory, formatBytes, isImage } from '../file-categories';

describe('fileCategory', () => {
    it.each([
        ['photo.JPG', '', 'IMAGE'],
        ['logo.svg', '', 'VECTOR'],
        ['clip.mkv', '', 'VIDEO'],
        ['song.flac', '', 'AUDIO'],
        ['invoice.pdf', '', 'PDF'],
        ['sheet.xlsx', '', 'SPREADSHEET'],
        ['deck.key', '', 'PRESENTATION'],
        ['backup.tar.gz', '', 'ARCHIVE'],
        ['ubuntu.iso', '', 'DISK'],
        ['notes.md', '', 'TEXT'],
        ['font.woff2', '', 'FONT'],
        ['book.epub', '', 'EBOOK'],
    ])('maps %s to %s', (name, mime, expected) => {
        expect(fileCategory(name, mime)).toBe(expected);
    });

    it('resolves the .ts collision by MIME, defaulting to code', () => {
        expect(fileCategory('client.ts', '')).toBe('CODE');
        expect(fileCategory('client.ts', 'text/plain')).toBe('CODE');
        expect(fileCategory('stream.ts', 'video/mp2t')).toBe('VIDEO');
    });

    it('falls back to the MIME type when the extension says nothing', () => {
        expect(fileCategory('blob', 'image/png')).toBe('IMAGE');
        expect(fileCategory('blob', 'image/svg+xml')).toBe('VECTOR');
        expect(fileCategory('blob', 'application/pdf')).toBe('PDF');
        expect(fileCategory('blob', 'audio/ogg')).toBe('AUDIO');
    });

    it('lands on OTHER when neither says anything', () => {
        expect(fileCategory('mystery', '')).toBe('OTHER');
        expect(fileCategory('', '')).toBe('OTHER');
    });

    it('ignores a leading dot, which is a hidden file and not an extension', () => {
        expect(fileCategory('.env', '')).toBe('OTHER');
    });

    it('gives every category an icon and a tint', () => {
        for (const name of ['a.jpg', 'a.svg', 'a.mp4', 'a.mp3', 'a.pdf', 'a.docx', 'a.xlsx', 'a.pptx', 'a.zip', 'a.iso', 'a.ts', 'a.txt', 'a.woff', 'a.epub', 'mystery']) {
            expect(categoryMsym(name, '')).toBeTruthy();
            expect(categoryTint(name, '')).toMatch(/^#[0-9a-f]{6}$/i);
        }
    });
});

describe('isImage', () => {
    it('covers rasters and vectors, the two the viewer renders inline', () => {
        expect(isImage('a.png', '')).toBe(true);
        expect(isImage('a.svg', '')).toBe(true);
        expect(isImage('a.pdf', '')).toBe(false);
        expect(isImage('a.mp4', '')).toBe(false);
    });
});

describe('formatBytes', () => {
    it.each([
        [0, '0 B'],
        [999, '999 B'],
        [1024, '1 KB'],
        [1536, '1.5 KB'],
        [1048576, '1 MB'],
        [1073741824, '1 GB'],
        [1234567, '1.18 MB'],
    ])('formats %i', (n, expected) => {
        expect(formatBytes(n)).toBe(expected);
    });
});
