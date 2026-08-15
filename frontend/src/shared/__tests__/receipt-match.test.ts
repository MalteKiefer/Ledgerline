import { describe, it, expect } from 'vitest';
import { approxToEur, exactMatches, autoPick, suggestBookings } from '../receipt-match';

describe('approxToEur', () => {
  it('converts a known currency', () => { expect(approxToEur(100, 'USD')).toBe(92); });
  it('is null for an unknown currency', () => { expect(approxToEur(100, 'JPY')).toBeNull(); });
  it('defaults to EUR (1:1)', () => { expect(approxToEur(19.99, null)).toBe(19.99); });
});

describe('exactMatches', () => {
  it('finds transactions whose absolute amount equals the total to the cent', () => {
    const tx = [{ id: 1, amount: -19.99 }, { id: 2, amount: -5 }];
    expect(exactMatches(19.99, tx)).toEqual([tx[0]]);
  });
  it('returns nothing for a null total', () => { expect(exactMatches(null, [{ id: 1, amount: -1 }])).toEqual([]); });
});

describe('autoPick', () => {
  it('picks the single unambiguous exact-amount transaction', () => {
    const tx = [{ id: 1, amount: -19.99, date: '2026-06-30' }, { id: 2, amount: -5, date: '2026-06-30' }];
    expect(autoPick({ total: 19.99 }, tx)?.id).toBe(1);
  });
  it('never auto-picks an ambiguous amount without a unique date-window match', () => {
    const tx = [{ id: 1, amount: -19.99, date: '2026-01-01' }, { id: 2, amount: -19.99, date: '2026-06-01' }];
    expect(autoPick({ total: 19.99, date: '2026-06-30' }, tx)).toBeNull();
  });
  it('resolves a recurring-charge ambiguity by date proximity', () => {
    const tx = [{ id: 1, amount: -9.99, date: '2026-01-01' }, { id: 2, amount: -9.99, date: '2026-06-29' }];
    expect(autoPick({ total: 9.99, date: '2026-06-30' }, tx)?.id).toBe(2);
  });
  it('never auto-picks a fuzzy/FX-only match', () => {
    const tx = [{ id: 1, amount: -92, date: '2026-01-01' }]; // ~100 USD converted, not exact
    expect(autoPick({ total: 100, currency: 'USD' }, tx)).toBeNull();
  });
});

describe('suggestBookings', () => {
  it('ranks an exact match above a fuzzy FX match', () => {
    const tx = [
      { id: 1, amount: -92, date: '2026-06-30' }, // FX candidate (100 USD ~ 92 EUR)
      { id: 2, amount: -100, date: '2026-06-30' }, // exact
    ];
    const s = suggestBookings({ total: 100, date: '2026-06-30', currency: 'USD' }, tx);
    expect(s[0].t.id).toBe(2);
    expect(s[0].kind).toBe('exact');
  });
  it('excludes transactions with no amount signal at all', () => {
    const tx = [{ id: 1, amount: -3.5, date: '2026-06-30' }];
    expect(suggestBookings({ total: 100 }, tx)).toEqual([]);
  });
});
