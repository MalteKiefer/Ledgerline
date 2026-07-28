import { describe, it, expect } from 'vitest';
import { matchInvoice } from '../shared/invoice-match.js';

// gross = qty*price*(1+rate/100). 1000 net @19% → 1190 gross.
const inv = (o) => ({ trashed: false, status: 'sent', lines: [{ qty: 1, unitPrice: 1000, vatRate: 19 }], ...o });

describe('invoice matching', () => {
    const invoices = [
        inv({ id: 'a', number: '2026-001', customer: { name: 'IntellyTec GmbH' } }), // gross 1190
        inv({ id: 'b', number: '2026-002', customer: { name: 'Acme AG' }, lines: [{ qty: 1, unitPrice: 500, vatRate: 19 }] }), // 595
        inv({ id: 'c', number: '2026-003', customer: { name: 'Globus' }, status: 'paid', lines: [{ qty: 1, unitPrice: 2000, vatRate: 19 }] }), // 2380, paid → excluded
    ];

    it('ignores non-income transactions', () => {
        expect(matchInvoice({ amount: -1190, purpose: '2026-001' }, invoices)).toBe(null);
    });

    it('matches by invoice number + amount', () => {
        expect(matchInvoice({ amount: 1190, purpose: 'Rechnung 2026-001 danke' }, invoices)?.id).toBe('a');
    });

    it('matches by customer name + amount', () => {
        expect(matchInvoice({ amount: 595, counterparty: 'Acme AG', purpose: 'Zahlung' }, invoices)?.id).toBe('b');
    });

    it('matches a unique exact amount even without name/number', () => {
        expect(matchInvoice({ amount: 595, purpose: 'unspecified' }, invoices)?.id).toBe('b');
    });

    it('does not match an ambiguous amount', () => {
        const two = [inv({ id: 'x', number: '1' }), inv({ id: 'y', number: '2' })]; // both 1190
        expect(matchInvoice({ amount: 1190, purpose: 'zahlung' }, two)).toBe(null);
    });

    it('excludes already-paid invoices', () => {
        expect(matchInvoice({ amount: 2380, purpose: '2026-003' }, invoices)).toBe(null);
    });

    it('requires the amount to match to the cent', () => {
        expect(matchInvoice({ amount: 1189.5, purpose: '2026-001' }, invoices)).toBe(null);
    });
});
