import { describe, it, expect } from 'vitest';
import { maxSeq, nextSeq, duplicateNumbers, missingNumbers, maxSeqForYear, nextSeqForYear, invoicesInYear, invoiceYear } from '../shared/invoice-numbering.js';

describe('missingNumbers — gapless-sequence gaps (GoBD)', () => {
    it('flags the missing number between two imported invoices (8 and 10 → 9)', () => {
        const inv = [
            { id: 'a', number: '8', issueDate: '2024-03-01', imported: true },
            { id: 'b', number: '10', issueDate: '2024-03-05', imported: true },
        ];
        expect(missingNumbers(inv)).toEqual(['9']);
    });
    it('no gaps on a contiguous run', () => {
        expect(missingNumbers([{ id: 'a', number: '1', issueDate: '2024-01-01' }, { id: 'b', number: '2', issueDate: '2024-01-02' }])).toEqual([]);
    });
    it('gaps are per year and match the YYYY-NNNN shape', () => {
        const inv = [
            { id: 'a', number: '2026-0001', issueDate: '2026-01-01' },
            { id: 'b', number: '2026-0003', issueDate: '2026-01-03' },
            { id: 'c', number: '1', issueDate: '2024-01-01' },
            { id: 'd', number: '3', issueDate: '2024-01-03' },
        ];
        expect(missingNumbers(inv).sort()).toEqual(['2', '2026-0002']);
    });
    it('ignores trashed invoices and non-integer (R-…) numbers', () => {
        const inv = [
            { id: 'a', number: '5', issueDate: '2024-01-01' },
            { id: 'b', number: '7', issueDate: '2024-01-07', trashed: '2026-01-01T00:00:00Z' },
            { id: 'c', number: 'R-2024-0009', issueDate: '2024-01-09' },
        ];
        expect(missingNumbers(inv)).toEqual([]); // only one integer in range → no gap
    });
});

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

    it('ignores trashed invoices (deleted-then-reimported is not a duplicate)', () => {
        const invoices = [
            { id: 'old', number: '2', issueDate: '2014-04-25', trashed: '2026-07-30T00:00:00Z' },
            { id: 'new', number: '2', issueDate: '2014-04-25' },
            { id: 'a', number: '1', issueDate: '2014-04-25' },
        ];
        expect(duplicateNumbers(invoices)).toEqual([]);
    });

    it('same bare number in two different years is not a duplicate', () => {
        const invoices = [
            { id: 'a', number: '1', issueDate: '2014-04-25' },
            { id: 'b', number: '1', issueDate: '2015-01-10' },
        ];
        expect(duplicateNumbers(invoices)).toEqual([]);
    });

    it('numbers per year: each year has its own sequence', () => {
        const invoices = [
            { id: 'a', seq: 3, issueDate: '2025-12-01' },
            { id: 'b', seq: 12, issueDate: '2025-06-01' },
            { id: 'c', seq: 2, issueDate: '2026-02-01' },
        ];
        expect(maxSeqForYear(invoices, 2025)).toBe(12);
        expect(maxSeqForYear(invoices, 2026)).toBe(2);
        expect(nextSeqForYear(invoices, 2026, 1)).toBe(3);   // 2026 continues at 3
        expect(nextSeqForYear(invoices, 2027, 1)).toBe(1);   // a fresh year restarts at 1
    });

    it('a fresh year restarts at the floor after a reset (no other-year seq bleeds in)', () => {
        const only2025 = [{ id: 'a', seq: 40, issueDate: '2025-09-01' }];
        // 2026 has no invoices (e.g. after a cycle reset) → starts at 1, not 41.
        expect(nextSeqForYear(only2025, 2026, 1)).toBe(1);
    });

    it('invoicesInYear returns active invoices dated in that year', () => {
        const invoices = [
            { id: 'a', issueDate: '2026-02-01' },
            { id: 'b', issueDate: '2026-05-01', trashed: '2026-06-01' },
            { id: 'c', issueDate: '2025-02-01' },
        ];
        expect(invoicesInYear(invoices, 2026).map((i) => i.id)).toEqual(['a']);
        expect(invoiceYear(invoices[2])).toBe('2025');
    });
});
