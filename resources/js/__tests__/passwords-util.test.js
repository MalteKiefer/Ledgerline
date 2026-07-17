import { describe, it, expect } from 'vitest';
import { parseCsv, detectCsv, cardBrand, totpSecret, base32Decode, totp, pwScore } from '../passwords-util';

describe('parseCsv', () => {
    it('parses quoted fields, escaped quotes and CRLF', () => {
        const rows = parseCsv('a,b,c\r\n"x,y","he said ""hi""",z\n');
        expect(rows[0]).toEqual(['a', 'b', 'c']);
        expect(rows[1]).toEqual(['x,y', 'he said "hi"', 'z']);
    });
    it('keeps newlines inside quotes', () => {
        expect(parseCsv('"line1\nline2",b')[0]).toEqual(['line1\nline2', 'b']);
    });
});

describe('detectCsv', () => {
    it('detects each format from headers', () => {
        expect(detectCsv(['folder', 'name', 'login_uri', 'login_password'])).toBe('bitwarden_csv');
        expect(detectCsv(['url', 'username', 'password', 'grouping'])).toBe('lastpass');
        expect(detectCsv(['title', 'username', 'password', 'otpauth'])).toBe('onepassword');
        expect(detectCsv(['group', 'title', 'username', 'password'])).toBe('keepass');
        expect(detectCsv(['name', 'url', 'username', 'password'])).toBe('generic');
    });
});

describe('cardBrand', () => {
    it('recognises the major networks and ignores spaces', () => {
        expect(cardBrand('4111 1111 1111 1111')).toBe('Visa');
        expect(cardBrand('5500000000000004')).toBe('Mastercard');
        expect(cardBrand('2221000000000009')).toBe('Mastercard');
        expect(cardBrand('340000000000009')).toBe('Amex');
        expect(cardBrand('6011000000000004')).toBe('Discover');
        expect(cardBrand('3530111333300000')).toBe('JCB');
        expect(cardBrand('')).toBe('');
    });
});

describe('totpSecret', () => {
    it('extracts the secret from an otpauth URI and passes raw secrets through', () => {
        expect(totpSecret('otpauth://totp/ACME:me?secret=JBSWY3DPEHPK3PXP&issuer=ACME')).toBe('JBSWY3DPEHPK3PXP');
        expect(totpSecret('JBSWY3DPEHPK3PXP')).toBe('JBSWY3DPEHPK3PXP');
        expect(totpSecret('')).toBe('');
    });
});

describe('base32Decode', () => {
    it('decodes to the expected bytes', () => {
        // "GEZDGNBVGY3TQOJQ" = ASCII "1234567890"
        expect(new TextDecoder().decode(base32Decode('GEZDGNBVGY3TQOJQ'))).toBe('1234567890');
    });
});

describe('totp (RFC 6238 SHA-1, 6-digit)', () => {
    const secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // ASCII "12345678901234567890"
    it('matches the RFC test vectors (last 6 digits)', async () => {
        expect(await totp(secret, 59)).toBe('287082');
        expect(await totp(secret, 1111111109)).toBe('081804');
        expect(await totp(secret, 1234567890)).toBe('005924');
    });
});

describe('pwScore', () => {
    it('scores weak vs strong', () => {
        expect(pwScore('abc')).toBeLessThan(3);
        expect(pwScore('P@ssw0rd-With-Length!')).toBeGreaterThanOrEqual(3);
    });
});
