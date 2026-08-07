import { describe, it, expect } from 'vitest';
import { invoiceTotals, realizedInvoices, vatReturn, revenueByCustomer, monthlyRevenue, yearKpis, activeYears, grossToNetVat, accountVatSummary, discountAmount } from '../shared/finance-stats.js';

const inv = (o) => ({ status: 'paid', trashed: false, lines: [], ...o });
const line = (qty, price, rate = 19) => ({ qty, unitPrice: price, vatRate: rate });

const data = [
    inv({ number: '2026-001', issueDate: '2026-02-02', customer: { name: 'IntellyTec GmbH' }, lines: [line(2.5, 45), line(1, 45)] }), // net 157.5
    inv({ number: '2026-002', issueDate: '2026-05-31', customer: { name: 'IntellyTec GmbH' }, lines: [line(20, 45)], status: 'sent' }), // net 900 (Q2)
    inv({ number: '2026-003', issueDate: '2026-08-10', customer: { name: 'Acme AG' }, lines: [line(10, 50, 7)] }), // net 500 @7% (Q3)
    inv({ number: '2025-009', issueDate: '2025-11-01', customer: { name: 'Acme AG' }, lines: [line(10, 40)] }), // net 400, prior year
    inv({ number: 'x', issueDate: '2026-03-03', customer: { name: 'Trashed' }, lines: [line(1, 999)], trashed: true }), // ignored
    inv({ number: 'd', issueDate: '2026-03-03', customer: { name: 'Draft' }, lines: [line(1, 999)], status: 'draft' }), // ignored
];

describe('finance stats', () => {
    it('computes single-invoice totals with VAT by rate', () => {
        expect(invoiceTotals(data[0])).toMatchObject({ net: 157.5, vat: 29.93, gross: 187.43 });
        expect(invoiceTotals(data[2])).toMatchObject({ net: 500, vat: 35, gross: 535 });
    });
    it('imported invoice keeps the exact printed gross (no cent round-trip)', () => {
        // 70,93 gross @ 19% must stay 70,93, not become 70,94; net + VAT reconstruct the gross.
        const inv = { imported: true, gross: 70.93, vatRate: 19, lines: [{ qty: 1, unitPrice: 59.61, vatRate: 19 }] };
        const t = invoiceTotals(inv);
        expect(t.gross).toBe(70.93);
        expect(Math.round((t.net + t.vat) * 100) / 100).toBe(70.93);
    });
    it('realized = issued and not trashed', () => {
        expect(realizedInvoices(data).map((i) => i.number)).toEqual(['2026-001', '2026-002', '2026-003', '2025-009']);
    });
    it("realized set matches the server (final|sent|paid), excludes draft/trashed", () => {
        const set = [
            inv({ number: 'f', status: 'final' }),
            inv({ number: 's', status: 'sent' }),
            inv({ number: 'p', status: 'paid' }),
            inv({ number: 'd', status: 'draft' }),
            inv({ number: 't', status: 'sent', trashed: true }),
        ];
        expect(realizedInvoices(set).map((i) => i.number)).toEqual(['f', 's', 'p']);
    });
    it('VAT advance return: net/VAT by rate and by quarter', () => {
        const r = vatReturn(data, 2026);
        expect(r.net).toBe(1557.5);   // 157.5 + 900 + 500
        expect(r.vat).toBe(235.93);   // 29.93 + 171 + 35
        expect(r.count).toBe(3);
        const q1 = r.quarters.find((q) => q.q === 1);
        const q2 = r.quarters.find((q) => q.q === 2);
        const q3 = r.quarters.find((q) => q.q === 3);
        expect(q1.net).toBe(157.5);
        expect(q2.net).toBe(900);
        expect(q3.net).toBe(500);
        expect(r.byRate.map((b) => b.rate)).toEqual([7, 19]);
        expect(r.byRate.find((b) => b.rate === 7).vat).toBe(35);
    });
    it('VAT advance return: §19 Kleinunternehmer books turnover GROSS into the 0% bucket, zero VAT', () => {
        const r = vatReturn(data, 2026, true);
        expect(r.net).toBe(1793.43);   // sum of gross: 187.43 + 1071 + 535
        expect(r.vat).toBe(0);
        expect(r.gross).toBe(1793.43);
        expect(r.byRate).toEqual([{ rate: 0, net: 1793.43, vat: 0 }]);
        const q3 = r.quarters.find((q) => q.q === 3);
        expect(q3.net).toBe(535);      // gross of the 7% invoice
        expect(q3.vat).toBe(0);
    });
    it('revenue by customer, highest first', () => {
        const c = revenueByCustomer(data, 2026);
        expect(c[0]).toMatchObject({ name: 'IntellyTec GmbH', net: 1057.5, count: 2 });
        expect(c[1]).toMatchObject({ name: 'Acme AG', net: 500, count: 1 });
    });
    it('monthly revenue', () => {
        const m = monthlyRevenue(data, 2026);
        expect(m[1].net).toBe(157.5);  // Feb
        expect(m[4].net).toBe(900);    // May
        expect(m[7].net).toBe(500);    // Aug
        expect(m[0].net).toBe(0);      // Jan
    });
    it('KPIs with year-over-year growth', () => {
        const k = yearKpis(data, 2026);
        expect(k.net).toBe(1557.5);
        expect(k.count).toBe(3);
        expect(k.customers).toBe(2);
        expect(k.prevNet).toBe(400);
        expect(k.growthPct).toBe(289.38); // (1557.5-400)/400*100
    });
    it('lists active years, newest first', () => {
        expect(activeYears(data)).toEqual([2026, 2025]);
    });

    it('splits a gross amount into net + VAT', () => {
        expect(grossToNetVat(119, 19)).toEqual({ net: 100, vat: 19 });
        expect(grossToNetVat(107, 7)).toEqual({ net: 100, vat: 7 });
        expect(grossToNetVat(100, 0)).toEqual({ net: 100, vat: 0 });
    });

    it('summarises account VAT by category, excluding private/undecided', () => {
        const txns = [
            { amount: 1190, vatCat: '19' },   // income, net 1000 / vat 190
            { amount: -119, vatCat: '19' },   // expense, net 100 / vat 19 (input)
            { amount: -107, vatCat: '7' },    // expense, net 100 / vat 7 (input)
            { amount: 500, vatCat: 'private' }, // deposit — excluded
            { amount: -50, vatCat: '' },       // undecided — excluded, counted
        ];
        const s = accountVatSummary(txns);
        expect(s.outputVat).toBe(190);
        expect(s.inputVat).toBe(26);       // 19 + 7
        expect(s.payable).toBe(164);       // 190 - 26
        expect(s.privateSum).toBe(500);
        expect(s.undecided).toBe(1);
        expect(s.income).toEqual([{ rate: '19', net: 1000, vat: 190 }]);
        expect(s.expense.map((r) => r.rate)).toEqual(['19', '7']);
    });
});

describe('invoice discount + credit notes', () => {
    const line = (qty, price, rate = 19) => ({ qty, unitPrice: price, vatRate: rate });

    it('discountAmount: percent / amount / clamps to base', () => {
        expect(discountAmount({ discountType: 'percent', discountValue: 10 }, 100)).toBe(10);
        expect(discountAmount({ discountType: 'amount', discountValue: 20 }, 100)).toBe(20);
        expect(discountAmount({}, 100)).toBe(0);
        // never exceeds the base in magnitude
        expect(discountAmount({ discountType: 'amount', discountValue: 500 }, 100)).toBe(100);
        // credit note (negative base): the discount is negated so it reverses exactly
        expect(discountAmount({ discountType: 'amount', discountValue: 20 }, -100)).toBe(-20);
    });

    it('percent discount reduces net + VAT proportionally', () => {
        const inv = { status: 'sent', lines: [line(1, 100, 19)], discountType: 'percent', discountValue: 10 };
        const t = invoiceTotals(inv);
        expect(t.net).toBe(90);      // 100 − 10%
        expect(t.vat).toBe(17.1);    // 90 × 19%
        expect(t.gross).toBe(107.1);
    });

    it('amount discount spread across mixed VAT rates by net share', () => {
        // net: 100 @19% + 100 @7% = 200; a 20 amount discount → factor 0.9.
        const inv = { status: 'sent', lines: [line(1, 100, 19), line(1, 100, 7)], discountType: 'amount', discountValue: 20 };
        const t = invoiceTotals(inv);
        expect(t.net).toBe(180);
        expect(t.vat).toBe(23.4);    // 90×19% + 90×7% = 17.1 + 6.3
        expect(t.gross).toBe(203.4);
    });

    it('a credit note (negated lines + same discount) exactly reverses the original', () => {
        const orig = { status: 'sent', lines: [line(2, 50, 19)], discountType: 'percent', discountValue: 10 };
        const credit = { status: 'sent', lines: [line(2, -50, 19)], discountType: 'percent', discountValue: 10 };
        const o = invoiceTotals(orig);
        const c = invoiceTotals(credit);
        expect(c.net).toBe(-o.net);
        expect(c.vat).toBe(-o.vat);
        expect(c.gross).toBe(-o.gross);
    });

    it('a credit note reduces revenue + output VAT in the VAT return', () => {
        const data = [
            { number: '1', status: 'sent', trashed: false, issueDate: '2026-02-01', customer: { name: 'ACME' }, lines: [line(1, 100, 19)] },
            { number: '2', status: 'sent', trashed: false, issueDate: '2026-03-01', type: 'credit_note', customer: { name: 'ACME' }, lines: [line(1, -100, 19)] },
        ];
        const r = vatReturn(data, 2026);
        expect(r.net).toBe(0);
        expect(r.vat).toBe(0);
        expect(r.count).toBe(2);
    });
});
