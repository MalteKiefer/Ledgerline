/**
 * The printed invoice is the document that goes to the customer and to the tax
 * office, and these are the numbers on it. The backend has its own port of this
 * arithmetic (FinanceReports); this side had none, so a drift in either would
 * have shown up as a wrong total on paper rather than as a failing test.
 */
import { describe, expect, it } from 'vitest';
import {
    computeTotals, discountAmount, hasDiscount, legacyPrintTerms, lineNet, skontoDate, vatRatesOf,
} from '../invoice-print';
import type { PrintInvoice } from '../invoice-print';

const invoice = (over: Partial<PrintInvoice> = {}): PrintInvoice => ({
    lines: [{ desc: 'Work', qty: 1, unitPrice: 100, vatRate: 19 }],
    ...over,
} as PrintInvoice);

describe('lineNet', () => {
    it('multiplies quantity by unit price', () => {
        expect(lineNet({ desc: '', qty: 3, unitPrice: 12.5, vatRate: 19 })).toBe(37.5);
    });

    it('accepts numeric strings, as the editor hands them over', () => {
        expect(lineNet({ desc: '', qty: '2', unitPrice: '10.50', vatRate: 19 })).toBe(21);
    });

    it('treats an unparseable field as zero rather than NaN', () => {
        expect(lineNet({ desc: '', qty: '', unitPrice: 'x', vatRate: 19 })).toBe(0);
    });
});

describe('computeTotals', () => {
    it('sums net, VAT and gross for a single rate', () => {
        const t = computeTotals(invoice());
        expect(t.net).toBe(100);
        expect(t.vat).toBeCloseTo(19, 2);
        expect(t.gross).toBeCloseTo(119, 2);
    });

    it('groups VAT by rate', () => {
        const t = computeTotals(invoice({
            lines: [
                { desc: 'a', qty: 1, unitPrice: 100, vatRate: 19 },
                { desc: 'b', qty: 1, unitPrice: 100, vatRate: 7 },
            ],
        }));
        expect(t.vatByRate[19]).toBeCloseTo(19, 2);
        expect(t.vatByRate[7]).toBeCloseTo(7, 2);
        expect(t.gross).toBeCloseTo(226, 2);
    });

    it('returns zeros for a null invoice instead of throwing', () => {
        expect(computeTotals(null)).toMatchObject({ net: 0, vat: 0, gross: 0 });
    });

    it('takes the stored gross of an imported invoice as authoritative', () => {
        // An imported invoice must print the exact amount its PDF shows; deriving
        // it from a synthetic line would move it by a cent.
        const t = computeTotals(invoice({ imported: true, gross: 70.93, vatRate: 19, lines: [] }));
        expect(t.gross).toBe(70.93);
        expect(t.net + t.vat).toBeCloseTo(70.93, 2);
        expect(t.vat).toBeCloseTo(11.32, 2);
    });
});

describe('discount', () => {
    it('maps a persisted fixed discount into both invoice print paths', () => {
        const printable = invoice({
            ...legacyPrintTerms({ discount_type: 'amount', discount_value: '25.00' }),
        });

        expect(printable).toMatchObject({ discountType: 'amount', discountValue: 25 });
        expect(computeTotals(printable)).toMatchObject({ discount: 25, net: 75 });
    });

    it('maps a persisted percentage discount into both invoice print paths', () => {
        const printable = invoice({
            ...legacyPrintTerms({ discount_type: 'percent', discount_value: '10.00' }),
        });

        expect(printable).toMatchObject({ discountType: 'percent', discountValue: 10 });
        expect(computeTotals(printable)).toMatchObject({ discount: 10, net: 90 });
    });

    it('applies a percentage to the taxable base', () => {
        const t = computeTotals(invoice({ discountType: 'percent', discountValue: 10 }));
        expect(t.discount).toBe(10);
        expect(t.net).toBe(90);
        expect(t.vat).toBeCloseTo(17.1, 2);
    });

    it('applies a fixed amount', () => {
        const t = computeTotals(invoice({ discountType: 'amount', discountValue: 25 }));
        expect(t.net).toBe(75);
        expect(t.vat).toBeCloseTo(14.25, 2);
    });

    it('splits a discount across rates in proportion to their share', () => {
        const t = computeTotals(invoice({
            lines: [
                { desc: 'a', qty: 1, unitPrice: 100, vatRate: 19 },
                { desc: 'b', qty: 1, unitPrice: 100, vatRate: 7 },
            ],
            discountType: 'percent',
            discountValue: 50,
        }));
        expect(t.net).toBe(100);
        expect(t.vatByRate[19]).toBeCloseTo(9.5, 2);
        expect(t.vatByRate[7]).toBeCloseTo(3.5, 2);
    });

    it('never exceeds the base and never goes negative', () => {
        expect(discountAmount(invoice({ discountType: 'amount', discountValue: 500 }), 100)).toBe(100);
        expect(discountAmount(invoice({ discountType: 'amount', discountValue: -5 }), 100)).toBe(0);
    });

    it('is inert without a type or a positive value', () => {
        expect(hasDiscount(invoice())).toBe(false);
        expect(hasDiscount(invoice({ discountType: 'percent', discountValue: 0 }))).toBe(false);
        expect(computeTotals(invoice({ discountType: 'percent', discountValue: 0 })).net).toBe(100);
    });
});

describe('VAT rows and skonto', () => {
    it('lists the used rates in ascending order', () => {
        expect(vatRatesOf(invoice({
            lines: [
                { desc: 'a', qty: 1, unitPrice: 1, vatRate: 19 },
                { desc: 'b', qty: 1, unitPrice: 1, vatRate: 7 },
            ],
        }), false)).toEqual([7, 19]);
    });

    it('prints no VAT rows for a small business (§19)', () => {
        expect(vatRatesOf(invoice(), true)).toEqual([]);
    });

    it('computes the early-payment date from the issue date', () => {
        expect(skontoDate(invoice({ issueDate: '2026-03-05', skontoDays: 14, skontoPercent: 2 })))
            .toBe('2026-03-19');
    });

    it('crosses a month boundary correctly', () => {
        expect(skontoDate(invoice({ issueDate: '2026-01-25', skontoDays: 10, skontoPercent: 2 })))
            .toBe('2026-02-04');
    });

    it('returns nothing without skonto terms', () => {
        expect(skontoDate(invoice({ issueDate: '2026-03-05' }))).toBe('');
    });
});
