import { describe, it, expect } from 'vitest';
import { mergeManifest, mergeArrayById, mergeObjectByKey } from '../shared/manifest-merge.js';

const ids = (arr) => arr.map((r) => r.id).sort();

describe('mergeManifest — the module-store 409 rebase', () => {
    it('keeps both writers records on a concurrent add (the exact bug)', () => {
        const base = { v: 3, notes: [{ id: 'a', t: 'A' }] };
        const ours = { v: 3, notes: [{ id: 'a', t: 'A' }, { id: 'b', t: 'B' }] }; // we added b
        const server = { v: 3, notes: [{ id: 'a', t: 'A' }, { id: 'c', t: 'C' }] }; // winner added c
        const merged = mergeManifest(base, ours, server);
        expect(ids(merged.notes)).toEqual(['a', 'b', 'c']);
    });

    it('applies our unrelated edit while the winner added a record', () => {
        const base = { v: 3, tracks: [{ id: 't1' }], settings: { tol: 100 } };
        const ours = { v: 3, tracks: [{ id: 't1' }], settings: { tol: 250 } }; // we changed a setting
        const server = { v: 3, tracks: [{ id: 't1' }, { id: 't2' }], settings: { tol: 100 } }; // winner added a track
        const merged = mergeManifest(base, ours, server);
        expect(ids(merged.tracks)).toEqual(['t1', 't2']); // track survives
        expect(merged.settings.tol).toBe(250); // our edit applies
    });

    it('our delete applies while the winners add survives (delete vs add race)', () => {
        const base = { notes: [{ id: 'a' }, { id: 'b' }] };
        const ours = { notes: [{ id: 'a' }] }; // we deleted b
        const server = { notes: [{ id: 'a' }, { id: 'b' }, { id: 'c' }] }; // winner added c
        const merged = mergeManifest(base, ours, server);
        expect(ids(merged.notes)).toEqual(['a', 'c']); // b gone, c kept
    });

    it('modifying a record we own replaces it without dropping the winners add', () => {
        const base = { notes: [{ id: 'a', t: 'A' }] };
        const ours = { notes: [{ id: 'a', t: 'A2' }] }; // edited a
        const server = { notes: [{ id: 'a', t: 'A' }, { id: 'z', t: 'Z' }] }; // winner added z
        const merged = mergeManifest(base, ours, server);
        expect(merged.notes.find((n) => n.id === 'a').t).toBe('A2');
        expect(ids(merged.notes)).toEqual(['a', 'z']);
    });

    it('merges id-map objects (couplings/knownFingerprints) key by key', () => {
        const base = { couplings: {} };
        const ours = { couplings: { p1: { track: 't1' } } }; // we coupled p1
        const server = { couplings: { p2: { track: 't2' } } }; // winner coupled p2
        const merged = mergeManifest(base, ours, server);
        expect(Object.keys(merged.couplings).sort()).toEqual(['p1', 'p2']);
    });

    it('preserves foreign top-level keys the winner has that we never saw', () => {
        const base = { notes: [] };
        const ours = { notes: [{ id: 'a' }] };
        const server = { notes: [], extra: { keep: true } };
        const merged = mergeManifest(base, ours, server);
        expect(merged.extra).toEqual({ keep: true });
    });

    it('takes our scalar when we changed it, else the winners', () => {
        // we bumped the sequence
        expect(mergeManifest({ seq: 5 }, { seq: 6 }, { seq: 7 }).seq).toBe(6);
        // we did not touch it → keep the winners
        expect(mergeManifest({ seq: 5 }, { seq: 5 }, { seq: 7 }).seq).toBe(7);
    });

    it('does not mutate the inputs', () => {
        const base = { notes: [{ id: 'a' }] };
        const ours = { notes: [{ id: 'a' }, { id: 'b' }] };
        const server = { notes: [{ id: 'a' }, { id: 'c' }] };
        const oursCopy = structuredClone(ours);
        const serverCopy = structuredClone(server);
        mergeManifest(base, ours, server);
        expect(ours).toEqual(oursCopy);
        expect(server).toEqual(serverCopy);
    });
});

describe('mergeArrayById', () => {
    it('falls back to ours-if-changed when records lack ids', () => {
        const merged = mergeArrayById(['a'], ['a', 'b'], ['a', 'c']);
        expect(merged).toEqual(['a', 'b']); // no ids → cannot merge, our change wins
    });
});

describe('mergeObjectByKey', () => {
    it('drops keys we deleted and keeps the winners untouched keys', () => {
        const merged = mergeObjectByKey({ a: 1, b: 2 }, { a: 1 }, { a: 1, b: 2, c: 3 });
        expect(merged).toEqual({ a: 1, c: 3 }); // b deleted by us, c added by winner
    });
});
