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

describe('mergeArrayById — same-record concurrent edit (D1/H1 deep-merge)', () => {
    it('unions nested id-arrays when BOTH writers changed the same record', () => {
        // Base: invoice X with one version v1.
        const base = { invoices: [{ id: 'X', versions: [{ id: 'v1' }] }] };
        // Ours: appended v2B.
        const ours = { invoices: [{ id: 'X', versions: [{ id: 'v1' }, { id: 'v2B' }] }] };
        // Server (winner): appended v2A to the SAME invoice.
        const server = { invoices: [{ id: 'X', versions: [{ id: 'v1' }, { id: 'v2A' }] }] };
        const merged = mergeManifest(base, ours, server);
        const vids = merged.invoices[0].versions.map((v) => v.id).sort();
        // Both nested versions survive — neither writer's PDF-bearing version is dropped.
        expect(vids).toEqual(['v1', 'v2A', 'v2B']);
    });

    it('merges nested receipts[] on the same transaction record', () => {
        const base = { transactions: [{ id: 'T', receipts: [] }] };
        const ours = { transactions: [{ id: 'T', receipts: [{ id: 'rB', blob: 'bB' }] }] };
        const server = { transactions: [{ id: 'T', receipts: [{ id: 'rA', blob: 'bA' }] }] };
        const merged = mergeManifest(base, ours, server);
        const rids = merged.transactions[0].receipts.map((r) => r.id).sort();
        expect(rids).toEqual(['rA', 'rB']);
    });

    it('takes ours wholesale when only WE changed the record (server == base)', () => {
        const base = { notes: [{ id: 'N', body: 'old' }] };
        const ours = { notes: [{ id: 'N', body: 'mine' }] };
        const server = { notes: [{ id: 'N', body: 'old' }] }; // server untouched
        const merged = mergeManifest(base, ours, server);
        expect(merged.notes[0].body).toBe('mine');
    });

    it('merges divergent scalar fields per key (ours-if-changed) on a shared record', () => {
        const base = { c: [{ id: '1', a: 1, b: 1 }] };
        const ours = { c: [{ id: '1', a: 2, b: 1 }] };     // we changed a
        const server = { c: [{ id: '1', a: 1, b: 9 }] };   // winner changed b
        const merged = mergeManifest(base, ours, server);
        expect(merged.c[0]).toMatchObject({ a: 2, b: 9 }); // both changes survive
    });
});

describe('mergeObjectByKey — recursive nested merge (fields.passkeys etc.)', () => {
    it('merges an id-array nested inside a changed object key', () => {
        const merged = mergeManifest(
            { s: [{ id: 'X', fields: { passkeys: [{ id: 'a' }] } }] },
            { s: [{ id: 'X', fields: { passkeys: [{ id: 'a' }, { id: 'ours' }] } }] },
            { s: [{ id: 'X', fields: { passkeys: [{ id: 'a' }, { id: 'srv' }] } }] },
        );
        expect(merged.s[0].fields.passkeys.map((p) => p.id).sort()).toEqual(['a', 'ours', 'srv']);
    });
    it('still drops a key we removed from the object', () => {
        const merged = mergeObjectByKey({ a: 1, b: 2 }, { a: 1 }, { a: 1, b: 2, c: 3 });
        expect(merged).toEqual({ a: 1, c: 3 });
    });
});
