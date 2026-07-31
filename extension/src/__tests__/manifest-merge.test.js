import { describe, it, expect } from 'vitest';
import { mergeManifest, mergeArrayById } from '../manifest-merge.js';

// The extension has its own copy of the web rebase-merge. It MUST carry the same
// same-record deep-merge fix (v1.533.1) so a concurrent edit to the same password /
// bookmark record from the extension does not clobber the web's nested changes.
describe('extension mergeArrayById — same-record concurrent edit deep-merges', () => {
    it('unions a top-level record array (versions[]) edited on both clients', () => {
        const base = { secrets: [{ id: 'X', versions: [{ id: 'v1' }] }] };
        const ours = { secrets: [{ id: 'X', versions: [{ id: 'v1' }, { id: 'vB' }] }] };
        const server = { secrets: [{ id: 'X', versions: [{ id: 'v1' }, { id: 'vA' }] }] };
        const merged = mergeManifest(base, ours, server);
        const ids = merged.secrets[0].versions.map((v) => v.id).sort();
        expect(ids).toEqual(['v1', 'vA', 'vB']); // neither writer's version dropped
    });

    it('unions an id-array NESTED inside an object field (fields.passkeys[])', () => {
        const base = { secrets: [{ id: 'X', fields: { passkeys: [{ id: 'pk1' }] } }] };
        const ours = { secrets: [{ id: 'X', fields: { passkeys: [{ id: 'pk1' }, { id: 'pkB' }] } }] };
        const server = { secrets: [{ id: 'X', fields: { passkeys: [{ id: 'pk1' }, { id: 'pkA' }] } }] };
        const merged = mergeManifest(base, ours, server);
        const ids = merged.secrets[0].fields.passkeys.map((p) => p.id).sort();
        expect(ids).toEqual(['pk1', 'pkA', 'pkB']); // recursive object-key merge
    });

    it('takes ours wholesale when only we changed the record', () => {
        const base = { bookmarks: [{ id: 'B', title: 'old' }] };
        const ours = { bookmarks: [{ id: 'B', title: 'mine' }] };
        const server = { bookmarks: [{ id: 'B', title: 'old' }] };
        expect(mergeManifest(base, ours, server).bookmarks[0].title).toBe('mine');
    });

    it('merges divergent scalar fields per key on a shared record', () => {
        const merged = mergeArrayById(
            [{ id: '1', a: 1, b: 1 }],
            [{ id: '1', a: 2, b: 1 }],
            [{ id: '1', a: 1, b: 9 }],
        );
        expect(merged[0]).toMatchObject({ a: 2, b: 9 });
    });
});

describe('extension mergeArrayById — generalized keys + scalar union', () => {
    it('unions scalar url arrays', () => {
        const merged = mergeManifest(
            { s: [{ id: 'L', fields: { urls: ['a'] } }] },
            { s: [{ id: 'L', fields: { urls: ['a', 'b'] } }] },
            { s: [{ id: 'L', fields: { urls: ['a', 'c'] } }] },
        );
        expect(merged.s[0].fields.urls.sort()).toEqual(['a', 'b', 'c']);
    });
    it('unions passkeys by credentialId', () => {
        const merged = mergeArrayById(
            [{ credentialId: 'c1' }],
            [{ credentialId: 'c1' }, { credentialId: 'cB' }],
            [{ credentialId: 'c1' }, { credentialId: 'cA' }],
        );
        expect(merged.map((p) => p.credentialId).sort()).toEqual(['c1', 'cA', 'cB']);
    });
});
