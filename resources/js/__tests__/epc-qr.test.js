import { describe, it, expect } from 'vitest';
import { buildEpcPayload, canEpcQr, normalizeIban } from '../shared/epc-qr.js';

describe('epc-qr', () => {
    it('normalizes IBAN', () => {
        expect(normalizeIban(' de89 3704 0044 0532 0130 00 ')).toBe('DE89370400440532013000');
    });
    it('gates on valid IBAN + positive EUR amount', () => {
        expect(canEpcQr({ iban: 'DE89370400440532013000', amount: 10, currency: 'EUR' })).toBe(true);
        expect(canEpcQr({ iban: 'DE89370400440532013000', amount: 10, currency: 'USD' })).toBe(false);
        expect(canEpcQr({ iban: 'DE89370400440532013000', amount: 0, currency: 'EUR' })).toBe(false);
        expect(canEpcQr({ iban: 'nonsense', amount: 10, currency: 'EUR' })).toBe(false);
    });
    it('builds an EPC069-12 payload', () => {
        const p = buildEpcPayload({ name: 'Acme GmbH', iban: 'DE89 3704 0044 0532 0130 00', bic: 'COBADEFFXXX', amount: 63.62, reference: 'Rechnung 2026-0001' });
        expect(p.split('\n')).toEqual([
            'BCD', '002', '1', 'SCT', 'COBADEFFXXX', 'Acme GmbH',
            'DE89370400440532013000', 'EUR63.62', '', '', 'Rechnung 2026-0001',
        ]);
    });
    it('returns null for a non-EUR or invalid invoice', () => {
        expect(buildEpcPayload({ iban: 'DE89370400440532013000', amount: 10, currency: 'USD' })).toBeNull();
        expect(buildEpcPayload({ iban: '', amount: 10 })).toBeNull();
    });
    it('caps name and remittance length', () => {
        const p = buildEpcPayload({ name: 'x'.repeat(90), iban: 'DE89370400440532013000', amount: 1, reference: 'y'.repeat(200) });
        const parts = p.split('\n');
        expect(parts[5].length).toBe(70);
        expect(parts[10].length).toBe(140);
    });
});
