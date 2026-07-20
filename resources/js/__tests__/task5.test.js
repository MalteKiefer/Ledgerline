import { describe, it, expect } from 'vitest';
import { formatDate } from '../shared/dom';
import { SECRET_FIELDS } from '../components/passwords';

// formatDate tests
describe('formatDate', () => {
    it('returns empty string for falsy input', () => {
        expect(formatDate('')).toBe('');
        expect(formatDate(null)).toBe('');
        expect(formatDate(undefined)).toBe('');
    });
    it('returns empty string for invalid date', () => {
        expect(formatDate('not-a-date')).toBe('');
    });
    it('returns a non-empty string for a valid ISO date', () => {
        const result = formatDate('2024-01-15T10:30:00Z');
        expect(result).not.toBe('');
        expect(typeof result).toBe('string');
    });
    it('accepts custom options override', () => {
        const result = formatDate('2024-01-15T10:30:00Z', { year: 'numeric', month: 'long', day: 'numeric' });
        expect(result).not.toBe('');
    });
});

// SECRET_FIELDS tests
describe('SECRET_FIELDS', () => {
    it('contains the 6 base secret field keys', () => {
        expect(SECRET_FIELDS).toHaveLength(6);
        expect(SECRET_FIELDS).toContain('password');
        expect(SECRET_FIELDS).toContain('totp');
        expect(SECRET_FIELDS).toContain('cvv');
        expect(SECRET_FIELDS).toContain('pin');
        expect(SECRET_FIELDS).toContain('licensekey');
        expect(SECRET_FIELDS).toContain('privateKey');
    });
    it('versionDiff secret set is [...SECRET_FIELDS, passkeys, publicKey]', () => {
        const versionDiffSecret = [...SECRET_FIELDS, 'passkeys', 'publicKey'];
        expect(versionDiffSecret).toContain('passkeys');
        expect(versionDiffSecret).toContain('publicKey');
        expect(versionDiffSecret.length).toBe(SECRET_FIELDS.length + 2);
    });
});
