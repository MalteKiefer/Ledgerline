import { describe, it, expect } from 'vitest';
import { buildReceiptName } from '../shared/receipt-name.js';
import { amountMatches } from '../shared/amount-search.js';

describe('receipt filename', () => {
    it('builds YYYYMMDD; Partner; Beleg with the extension', () => {
        expect(buildReceiptName({ date: '2026-07-25', partner: 'Google', ext: '.pdf' }))
            .toBe('20260725; Google; Beleg.pdf');
    });
    it('uses "Rechnung <number>" when a number is known', () => {
        expect(buildReceiptName({ date: '2026-06-25', partner: 'Telekom Deutschland GmbH', number: 'R-2026-006', ext: '.pdf' }))
            .toBe('20260625; Telekom Deutschland GmbH; Rechnung R-2026-006.pdf');
    });
    it('localises the nouns', () => {
        expect(buildReceiptName({ date: '2026-01-02', partner: 'X', belegWord: 'Receipt', ext: '.png' }))
            .toBe('20260102; X; Receipt.png');
    });
    it('omits the date segment when there is none', () => {
        expect(buildReceiptName({ partner: 'Aral', ext: '.pdf' })).toBe('Aral; Beleg.pdf');
    });
    it('strips separators from partner/number so the pattern stays intact', () => {
        expect(buildReceiptName({ date: '2026-07-01', partner: 'A/B; Co', number: '12/34', ext: '.pdf' }))
            .toBe('20260701; A B Co; Rechnung 12 34.pdf');
    });
    it('adds a leading dot to a bare extension', () => {
        expect(buildReceiptName({ date: '2026-07-01', partner: 'X', ext: 'pdf' })).toBe('20260701; X; Beleg.pdf');
    });
});

describe('amount search', () => {
    it('matches a signed prefix', () => {
        expect(amountMatches(-9.88, '-9')).toBe(true);
        expect(amountMatches(-20.28, '-20')).toBe(true);
        expect(amountMatches(133.88, '-9')).toBe(false);
    });
    it('accepts comma or dot as the decimal separator', () => {
        expect(amountMatches(9.88, '9,88')).toBe(true);
        expect(amountMatches(9.88, '9.88')).toBe(true);
    });
    it('matches the absolute value (receipt gross vs outgoing booking)', () => {
        expect(amountMatches(-45, '45')).toBe(true);
        expect(amountMatches(1071, '1071')).toBe(true);
    });
    it('ignores spaces and currency symbols', () => {
        expect(amountMatches(60, '60 €')).toBe(true);
        expect(amountMatches(60, '60 eur')).toBe(true);
    });
    it('returns false for a non-numeric query', () => {
        expect(amountMatches(9.88, 'abc')).toBe(false);
        expect(amountMatches(9.88, '')).toBe(false);
    });
});
