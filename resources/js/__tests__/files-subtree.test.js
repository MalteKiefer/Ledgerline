import { describe, expect, it } from 'vitest';
import { collectSubtree } from '../components/files-subtree';

const folders = [
    { id: 'a', name: 'A', parent: null },
    { id: 'b', name: 'B', parent: 'a' },
    { id: 'c', name: 'C', parent: null },
];
const files = [
    { id: 'f1', name: 'r.txt', folder: 'a' },
    { id: 'f2', name: 's.txt', folder: 'b' },
    { id: 'f3', name: 'other.txt', folder: 'c' },
];

describe('collectSubtree', () => {
    it('collects the folder + descendants, re-roots the root parent to null', () => {
        const { folders: sf, files: ff } = collectSubtree(folders, files, 'a');
        expect(sf.map((x) => x.id).sort()).toEqual(['a', 'b']);
        expect(sf.find((x) => x.id === 'a').parent).toBe(null); // re-rooted
        expect(sf.find((x) => x.id === 'b').parent).toBe('a');   // internal link kept
        expect(ff.map((x) => x.id).sort()).toEqual(['f1', 'f2']);
    });
    it('excludes unrelated subtrees', () => {
        const { folders: sf, files: ff } = collectSubtree(folders, files, 'c');
        expect(sf.map((x) => x.id)).toEqual(['c']);
        expect(ff.map((x) => x.id)).toEqual(['f3']);
    });
});
