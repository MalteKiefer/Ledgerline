/**
 * Contact phone numbers: the flag next to a number and its readable grouping.
 * Approximate by design (no libphonenumber), so the tests pin the rules the
 * module promises — longest-prefix wins, national numbers stay untouched — not
 * per-country area-code exactness it never claimed.
 */
import { describe, expect, it } from 'vitest';
import { flagOf, formatPhone, phoneCountry } from '../phone-country';

describe('phoneCountry', () => {
    it.each([
        ['+49 175 4182881', 'DE'],
        ['+1 415 555 1234', 'US'],
        ['+44 20 7946 0958', 'GB'],
        ['+41 44 668 18 00', 'CH'],
        ['+352 27 300', 'LU'],
    ])('resolves %s to %s', (input, iso) => {
        expect(phoneCountry(input)?.iso).toBe(iso);
    });

    it('accepts the 00 international prefix as well as +', () => {
        expect(phoneCountry('004917541828')?.iso).toBe('DE');
    });

    it('prefers the longest matching calling code', () => {
        // 35 is unassigned, 352 is Luxembourg: a naive shortest-prefix scan
        // would answer with whatever "3" or "35" happened to map to.
        expect(phoneCountry('+35227300')?.iso).toBe('LU');
        expect(phoneCountry('+3holder')).toBeNull();
    });

    it('returns null for a national number, where no guess is reliable', () => {
        expect(phoneCountry('0175 4182881')).toBeNull();
        expect(phoneCountry('4182881')).toBeNull();
        expect(phoneCountry('')).toBeNull();
    });

    it('carries the flag emoji for the resolved country', () => {
        expect(phoneCountry('+49 175 1')?.flag).toBe('🇩🇪');
    });
});

describe('flagOf', () => {
    it('builds the regional-indicator pair', () => {
        expect(flagOf('de')).toBe('🇩🇪');
        expect(flagOf('US')).toBe('🇺🇸');
    });

    it('returns nothing for a non-ISO input', () => {
        expect(flagOf('DEU')).toBe('');
        expect(flagOf('')).toBe('');
    });
});

describe('formatPhone', () => {
    it('groups an international number after its calling code', () => {
        expect(formatPhone('+4917541828 81')).toBe('+49 175 4182 881');
    });

    it('uses the canonical 3-3-4 grouping for NANP', () => {
        expect(formatPhone('+14155551234')).toBe('+1 415 555 1234');
    });

    it('keeps a national number national, including its leading zero', () => {
        expect(formatPhone('017541828 81')).toBe('0175 4182 881');
    });

    it('leaves a short number alone rather than inventing groups', () => {
        expect(formatPhone('+49 175')).toBe('+49 175');
    });

    it('drops an extension suffix from the formatted part', () => {
        expect(formatPhone('+14155551234x99')).toBe('+1 415 555 1234');
    });

    it('passes through what it cannot parse instead of mangling it', () => {
        expect(formatPhone('')).toBe('');
        expect(formatPhone('not a number')).toBe('not a number');
        expect(formatPhone('+999 123456')).toBe('+999 123456'); // unassigned code
    });
});
