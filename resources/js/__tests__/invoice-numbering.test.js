import { describe, it, expect } from 'vitest';
import { maxSeq, nextSeq, duplicateNumbers } from '../shared/invoice-numbering.js';

describe('invoice numbering (GoBD: unique, gapless)', () => {
    it('nextSeq derives from the real invoices, not just the scalar', () => {
        // A number issued on another device (seq 8) was merged in; the scalar lags at 5.
        const invoices = [{ id: 'a', seq: 3 }, { id: 'b', seq: 8 }];
        expect(nextSeq(invoices, 5, 1)).toBe(9);
    });

    it('honours the company floor when higher than the max', () => {
        expect(nextSeq([], 0, 42)).toBe(42);
        expect(nextSeq([{ id: 'a', seq: 5 }], 5, 42)).toBe(42);
        expect(nextSeq([{ id: 'a', seq: 50 }], 5, 42)).toBe(51);
    });

    it('never repeats a merged-in sequence (self-correcting)', () => {
        // Two devices concurrently issued seq 6; after merge both exist. The NEXT number
        // is 7 — no third duplicate.
        const merged = [{ id: 'a', seq: 6 }, { id: 'b', seq: 6 }];
        expect(nextSeq(merged, 6, 1)).toBe(7);
    });

    it('maxSeq ignores invoices without a seq (legacy) but keeps the scalar', () => {
        expect(maxSeq([{ id: 'a' }, { id: 'b', seq: 4 }], 9)).toBe(9);
        expect(maxSeq([{ id: 'a' }], 0)).toBe(0);
    });

    it('duplicateNumbers flags any number used more than once', () => {
        const invoices = [
            { id: 'a', number: '2026-0006' },
            { id: 'b', number: '2026-0006' }, // duplicate!
            { id: 'c', number: '2026-0007' },
            { id: 'd', number: null }, // draft, unassigned — ignored
        ];
        expect(duplicateNumbers(invoices)).toEqual(['2026-0006']);
    });

    it('no duplicates on a clean gapless sequence', () => {
        const invoices = [{ id: 'a', number: '1' }, { id: 'b', number: '2' }, { id: 'c', number: '3' }];
        expect(duplicateNumbers(invoices)).toEqual([]);
    });
});
