import { describe, it, expect } from 'vitest';
import {
    PAYMENT_TYPES, paymentIcon, paymentTint, isPaymentType, blankPaymentMethod,
    normalizeIban, formatIban, last4, maskIban, cardDigits, maskCard, cardNetworkOf,
    paymentSubtitle, isValidPaymentMethod, sortedPaymentMethods,
} from '../shared/payment-methods.js';

describe('payment methods', () => {
    it('exposes a type registry with icon + tint', () => {
        expect(PAYMENT_TYPES.map((t) => t.type)).toEqual(['bank', 'card', 'paypal', 'cash', 'other']);
        expect(paymentIcon('card')).toBe('credit-card');
        expect(paymentTint('bank')).toBe('#3b9fd6');
        expect(paymentIcon('nope')).toBe('wallet'); // falls back to other
        expect(isPaymentType('paypal')).toBe(true);
        expect(isPaymentType('crypto')).toBe(false);
    });
    it('blank record respects the type', () => {
        expect(blankPaymentMethod('card').type).toBe('card');
        expect(blankPaymentMethod('bogus').type).toBe('other');
        expect(blankPaymentMethod().type).toBe('bank');
    });
    it('normalises + formats IBANs', () => {
        expect(normalizeIban('de89 3704 0044 0532 0130 00')).toBe('DE89370400440532013000');
        expect(formatIban('DE89370400440532013000')).toBe('DE89 3704 0044 0532 0130 00');
        expect(last4('DE89370400440532013000')).toBe('3000');
    });
    it('masks IBANs keeping prefix + last four', () => {
        expect(maskIban('DE89370400440532013000')).toBe('DE89 •••• •••• •••• •••• 3000');
        expect(maskIban('')).toBe('');
    });
    it('masks card numbers + guesses network', () => {
        expect(cardDigits('4242 4242 4242 4242')).toBe('4242424242424242');
        expect(maskCard('4242 4242 4242 4242')).toBe('•••• •••• •••• 4242');
        expect(cardNetworkOf('4242424242424242')).toBe('visa');
        expect(cardNetworkOf('5500000000000004')).toBe('mastercard');
        expect(cardNetworkOf('340000000000009')).toBe('amex');
        expect(cardNetworkOf('6011000000000004')).toBe('other');
    });
    it('builds a subtitle per type', () => {
        expect(paymentSubtitle({ type: 'bank', iban: 'DE89370400440532013000' })).toBe('DE89 •••• •••• •••• •••• 3000');
        expect(paymentSubtitle({ type: 'card', cardNetwork: 'visa', cardNumber: '4242424242424242' })).toBe('Visa · •••• •••• •••• 4242');
        expect(paymentSubtitle({ type: 'paypal', email: 'me@example.com' })).toBe('me@example.com');
        expect(paymentSubtitle({ type: 'cash' })).toBe('');
        expect(paymentSubtitle({ type: 'other', note: 'Petty cash' })).toBe('Petty cash');
    });
    it('validates the minimum fields per type', () => {
        expect(isValidPaymentMethod({ type: 'bank', label: 'Giro', iban: 'DE89' })).toBe(true);
        expect(isValidPaymentMethod({ type: 'bank', label: 'Giro' })).toBe(false);
        expect(isValidPaymentMethod({ type: 'card', label: 'Visa', cardNumber: '4242' })).toBe(true);
        expect(isValidPaymentMethod({ type: 'paypal', label: 'PP', email: 'a@b.de' })).toBe(true);
        expect(isValidPaymentMethod({ type: 'cash', label: 'Kasse' })).toBe(true);
        expect(isValidPaymentMethod({ type: 'cash', label: '' })).toBe(false);
    });
    it('sorts active methods bank→card→…, then by label', () => {
        const list = [
            { type: 'card', label: 'B', trashed: false },
            { type: 'bank', label: 'Z', trashed: false },
            { type: 'bank', label: 'A', trashed: false },
            { type: 'cash', label: 'X', trashed: true },
        ];
        expect(sortedPaymentMethods(list).map((p) => p.label)).toEqual(['A', 'Z', 'B']);
    });
});
