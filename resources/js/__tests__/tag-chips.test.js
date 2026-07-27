import { describe, it, expect } from 'vitest';
import { parseTags, joinTags, addTags, removeTagFrom, popTag } from '../shared/tag-chips.js';

describe('tag chips', () => {
    it('parses a comma-joined string into trimmed, non-empty tags', () => {
        expect(parseTags('a, b ,c,,  ')).toEqual(['a', 'b', 'c']);
        expect(parseTags('')).toEqual([]);
        expect(parseTags(null)).toEqual([]);
    });

    it('round-trips through join', () => {
        expect(joinTags(['a', 'b', 'c'])).toBe('a, b, c');
        expect(parseTags(joinTags(['x', 'y']))).toEqual(['x', 'y']);
    });

    it('adds a draft tag, skipping duplicates', () => {
        expect(addTags('a, b', 'c')).toBe('a, b, c');
        expect(addTags('a, b', 'b')).toBe('a, b'); // already present
        expect(addTags('', 'first')).toBe('first');
    });

    it('adds multiple comma-split tags from one draft (paste)', () => {
        expect(addTags('a', 'b, c ,a')).toBe('a, b, c'); // a is a dup
    });

    it('removes a specific tag', () => {
        expect(removeTagFrom('a, b, c', 'b')).toBe('a, c');
        expect(removeTagFrom('a', 'a')).toBe('');
        expect(removeTagFrom('a, b', 'x')).toBe('a, b'); // not present
    });

    it('pops the last tag (backspace)', () => {
        expect(popTag('a, b, c')).toBe('a, b');
        expect(popTag('a')).toBe('');
        expect(popTag('')).toBe('');
    });
});
