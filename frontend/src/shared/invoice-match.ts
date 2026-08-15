// Auto-assign an invoice to the incoming bank transaction that settled it — the
// invoice-side mirror of receipt-match.ts. Unlike receipts (many documents can sum
// to one charge), an invoice is 1:1 with the payment that settled it, so no
// subset-sum is needed: this stays a pure client-side pass over the already-loaded
// invoices/transactions (no server round-trip). Pure + testable.

export interface MatchableInvoice {
  id: number;
  number: string | null;
  status: string;
  gross: number | string | null;
}

export interface MatchableInvoiceTx {
  id: number;
  amount: number | string | null; // signed; positive = incoming
  date?: string | null;
  purpose?: string | null;
  booking_text?: string | null;
  invoice_id?: number | null;
}

export interface InvoiceMatch {
  invoiceId: number;
  txId: number;
  reason: 'number_ref' | 'exact_amount';
  // false for an invoice that is already 'paid' (e.g. one marked paid on import,
  // whose settling transaction was never linked back) — the match only records the
  // link, it must NOT re-touch a status that is already terminal. true for a
  // 'final'/'sent' invoice, where applying the match also marks it paid.
  markPaid: boolean;
}

function toNum(v: number | string | null | undefined): number | null {
  if (v == null) return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

// Strip everything but letters/digits and upper-case, so "2026-0006", "2026 0006"
// and "Rechnung 2026-0006" all normalise to a comparable fragment.
function normRef(s: string): string {
  return s.toUpperCase().replace(/[^A-Z0-9]/g, '');
}

/**
 * Find the (invoice, transaction) pairs to auto-link. Candidate invoices are
 * 'final'/'sent' (issued, unpaid — a match marks them paid) OR already 'paid' but
 * with no transaction pointing at them yet (a match only backfills the link, e.g.
 * for invoices that were imported pre-marked-paid without ever being linked).
 * Candidate transactions are incoming (positive-amount) and not already linked to
 * any invoice. Two passes:
 *
 * 1. Number-in-purpose (strong): the invoice number appears, normalised, inside
 *    the transaction's purpose/booking text — real statements print it verbatim
 *    ("2026-0006", "2026002", "Rechnung Nr. 3"). If the invoice's gross is known
 *    it must also match the paid amount to the cent, so a numeric fragment that
 *    merely happens to overlap never wins over a genuine amount mismatch.
 * 2. Exact-amount fallback (weak, only when unambiguous): an invoice whose gross
 *    is unique among the still-open candidate invoices, matched to the single
 *    transaction in the residual pool carrying that exact amount. Two invoices
 *    sharing a total, or two candidate transactions, are left for manual linking.
 */
export function matchInvoices(invoices: MatchableInvoice[], transactions: MatchableInvoiceTx[]): InvoiceMatch[] {
  const linkedInvoiceIds = new Set(
    (transactions || []).filter((t) => t.invoice_id != null).map((t) => t.invoice_id as number),
  );
  const candidates = (invoices || []).filter((i) => {
    if (!i.number) return false;
    if (i.status === 'final' || i.status === 'sent') return true;
    return i.status === 'paid' && !linkedInvoiceIds.has(i.id);
  });
  const pool = (transactions || []).filter((t) => t.invoice_id == null && (toNum(t.amount) ?? 0) > 0);
  const claimed = new Set<number>();
  const matched = new Set<number>();
  const out: InvoiceMatch[] = [];

  for (const inv of candidates) {
    const ref = normRef(String(inv.number));
    if (!ref) continue;
    const gross = toNum(inv.gross);
    const hit = pool.find((t) => {
      if (claimed.has(t.id)) return false;
      const text = normRef(`${t.purpose ?? ''} ${t.booking_text ?? ''}`);
      if (!text.includes(ref)) return false;
      if (gross != null) {
        const amt = toNum(t.amount);
        if (amt != null && Math.abs(amt - gross) > 0.01) return false;
      }
      return true;
    });
    if (hit) {
      claimed.add(hit.id); matched.add(inv.id);
      out.push({ invoiceId: inv.id, txId: hit.id, reason: 'number_ref', markPaid: inv.status !== 'paid' });
    }
  }

  const grossCounts = new Map<number, number>();
  for (const inv of candidates) {
    const g = toNum(inv.gross);
    if (g != null) grossCounts.set(g, (grossCounts.get(g) ?? 0) + 1);
  }
  for (const inv of candidates) {
    if (matched.has(inv.id)) continue;
    const gross = toNum(inv.gross);
    if (gross == null || (grossCounts.get(gross) ?? 0) !== 1) continue; // ambiguous on the invoice side too
    const hitCandidates = pool.filter((t) => !claimed.has(t.id) && Math.abs((toNum(t.amount) ?? NaN) - gross) <= 0.01);
    if (hitCandidates.length === 1) {
      claimed.add(hitCandidates[0].id); matched.add(inv.id);
      out.push({ invoiceId: inv.id, txId: hitCandidates[0].id, reason: 'exact_amount', markPaid: inv.status !== 'paid' });
    }
  }

  return out;
}
