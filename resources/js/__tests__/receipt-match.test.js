import { describe, it, expect } from 'vitest';
import { approxToEur, exactMatches, autoPick, suggestBookings } from '../shared/receipt-match.js';

const TX = [
    { id: 'a', amount: -9.99, date: '2026-02-16' },
    { id: 'b', amount: -9.99, date: '2026-06-05' },
    { id: 'c', amount: -46.00, date: '2026-02-17' }, // ≈ 50 USD converted
    { id: 'd', amount: -100.0, date: '2026-02-14' },
];

describe('receipt matching', () => {
    it('converts to EUR with the provided rates, else null', () => {
        expect(approxToEur(100, 'USD', { USD: 0.9 })).toBe(90);
        expect(approxToEur(100, 'EUR')).toBe(100);
        expect(approxToEur(100, 'XYZ', { USD: 0.9 })).toBe(null);
    });
    it('finds exact-cent amount matches', () => {
        expect(exactMatches(9.99, TX).map((t) => t.id)).toEqual(['a', 'b']);
        expect(exactMatches(null, TX)).toEqual([]);
    });
    it('auto-picks a single exact match, else the one near the date', () => {
        expect(autoPick({ total: 100, date: '2026-02-14' }, TX)?.id).toBe('d');
        expect(autoPick({ total: 9.99, date: '2026-02-15' }, TX, 3)?.id).toBe('a'); // date disambiguates
        expect(autoPick({ total: 9.99, date: null }, TX)).toBe(null); // ambiguous, no date
    });
    it('suggests amount matches ranked by date proximity (±3 days)', () => {
        const s = suggestBookings({ total: 9.99, date: '2026-02-15' }, TX);
        expect(s[0].t.id).toBe('a'); // nearest date first
        expect(s.map((x) => x.t.id)).toContain('b');
    });
    it('suggests via a rough currency conversion (50 USD ≈ 46 EUR)', () => {
        const s = suggestBookings({ total: 50, date: '2026-02-16', currency: 'USD' }, TX, { rates: { EUR: 1, USD: 0.92 } });
        expect(s.some((x) => x.t.id === 'c' && x.kind === 'fx')).toBe(true);
    });
});
