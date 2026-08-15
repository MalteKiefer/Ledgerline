import { describe, it, expect } from 'vitest';
import { matchInvoices } from '../invoice-match';

describe('matchInvoices', () => {
  it('links an open (final) invoice to the transaction that quotes its number, and marks it paid', () => {
    const invoices = [{ id: 1, number: '2026-0006', status: 'final', gross: 959.5 }];
    const tx = [{ id: 349, amount: 959.5, purpose: '2026-0006', invoice_id: null }];
    const m = matchInvoices(invoices, tx);
    expect(m).toEqual([{ invoiceId: 1, txId: 349, reason: 'number_ref', markPaid: true }]);
  });

  it('backfills the link for an already-paid invoice without touching its status', () => {
    const invoices = [{ id: 1, number: '2026-001', status: 'paid', gross: 187.43 }];
    const tx = [{ id: 253, amount: 187.43, purpose: '2026-001', invoice_id: null }];
    const m = matchInvoices(invoices, tx);
    expect(m).toEqual([{ invoiceId: 1, txId: 253, reason: 'number_ref', markPaid: false }]);
  });

  it('never re-matches a paid invoice that already has a linked transaction', () => {
    const invoices = [{ id: 1, number: '2026-001', status: 'paid', gross: 187.43 }];
    const tx = [
      { id: 253, amount: 187.43, purpose: '2026-001', invoice_id: 1 },
      { id: 999, amount: 187.43, purpose: 'unrelated 2026-001 duplicate', invoice_id: null },
    ];
    expect(matchInvoices(invoices, tx)).toEqual([]);
  });

  it('normalises punctuation/spacing so "Rechnung Nr. 3" and "2026 0006" style references still match', () => {
    const invoices = [{ id: 1, number: '3', status: 'final', gross: 523.6 }];
    const tx = [{ id: 41, amount: 523.6, purpose: 'Rechnung Nr. 3', invoice_id: null }];
    expect(matchInvoices(invoices, tx)[0]?.txId).toBe(41);
  });

  it('rejects a purpose-substring hit when the paid amount clearly differs', () => {
    const invoices = [{ id: 1, number: '2026-001', status: 'final', gross: 187.43 }];
    const tx = [{ id: 253, amount: 50, purpose: '2026-001 (part payment)', invoice_id: null }];
    expect(matchInvoices(invoices, tx)).toEqual([]);
  });

  it('falls back to an unambiguous exact-amount match when no number reference is present', () => {
    const invoices = [{ id: 1, number: '2025-01', status: 'paid', gross: 73.78 }];
    const tx = [{ id: 69, amount: 73.78, purpose: 'Überweisung', invoice_id: null }];
    expect(matchInvoices(invoices, tx)[0]?.reason).toBe('exact_amount');
  });

  it('never guesses when two open invoices share the same gross amount', () => {
    const invoices = [
      { id: 1, number: '2025-3', status: 'paid', gross: 19 },
      { id: 2, number: '2025-6', status: 'paid', gross: 19 },
    ];
    const tx = [{ id: 1, amount: 19, purpose: 'Überweisung', invoice_id: null }];
    expect(matchInvoices(invoices, tx)).toEqual([]);
  });

  it('never guesses when two candidate transactions carry the same exact amount', () => {
    const invoices = [{ id: 1, number: '2025-9', status: 'final', gross: 133.88 }];
    const tx = [
      { id: 1, amount: 133.88, purpose: 'Überweisung A', invoice_id: null },
      { id: 2, amount: 133.88, purpose: 'Überweisung B', invoice_id: null },
    ];
    expect(matchInvoices(invoices, tx)).toEqual([]);
  });

  it('ignores a draft invoice entirely', () => {
    const invoices = [{ id: 1, number: '2026-0007', status: 'draft', gross: 100 }];
    const tx = [{ id: 1, amount: 100, purpose: '2026-0007', invoice_id: null }];
    expect(matchInvoices(invoices, tx)).toEqual([]);
  });

  it('ignores an outgoing (negative-amount) transaction', () => {
    const invoices = [{ id: 1, number: '2026-0006', status: 'final', gross: 959.5 }];
    const tx = [{ id: 1, amount: -959.5, purpose: '2026-0006', invoice_id: null }];
    expect(matchInvoices(invoices, tx)).toEqual([]);
  });

  it('ignores a transaction already linked to a different invoice', () => {
    const invoices = [{ id: 1, number: '2026-0006', status: 'final', gross: 959.5 }];
    const tx = [{ id: 1, amount: 959.5, purpose: '2026-0006', invoice_id: 42 }];
    expect(matchInvoices(invoices, tx)).toEqual([]);
  });
});
