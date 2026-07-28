import { describe, it, expect } from 'vitest';
import { normMerchant, matchPartner, learnedCategoryFor } from '../shared/merchant-learn.js';

describe('merchant learning', () => {
    it('normalises legal forms + punctuation to one key', () => {
        expect(normMerchant('netcup GmbH')).toBe('netcup');
        expect(normMerchant('NETCUP  Deutschland')).toBe('netcup');
        expect(normMerchant('Google Commerce Limited')).toBe('google commerce');
        expect(normMerchant('Da Mario e.K.')).toBe('da mario');
        expect(normMerchant('')).toBe('');
    });
    it('matches a partner by normalised name', () => {
        const partners = [{ id: '1', name: 'netcup GmbH', category: 'Software' }, { id: '2', name: 'Aral' }];
        expect(matchPartner(partners, 'NETCUP')?.id).toBe('1');
        expect(matchPartner(partners, 'aral tankstelle')).toBe(null); // not the same key
        expect(matchPartner(partners, 'Aral')?.id).toBe('2');
        expect(matchPartner([], 'netcup')).toBe(null);
        expect(matchPartner(partners, 'x')).toBe(null); // too-short key ignored
    });
    it('returns the learned category, else empty', () => {
        const partners = [{ id: '1', name: 'netcup GmbH', category: 'Software' }, { id: '2', name: 'Aral' }];
        expect(learnedCategoryFor(partners, 'netcup')).toBe('Software');
        expect(learnedCategoryFor(partners, 'Aral')).toBe(''); // partner exists but no category
        expect(learnedCategoryFor(partners, 'unknown')).toBe('');
    });
});
