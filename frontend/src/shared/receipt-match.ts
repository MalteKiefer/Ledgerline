// Ranked booking suggestions for a SINGLE receipt (client-side, for the manual
// "assign a booking" picker and the immediate-capture auto-pick). A charge often
// posts a few days after the receipt date and may be in another currency, so
// matching here is fuzzy — a human reviews the result. The batch, cent-exact,
// many-receipts-to-one-charge matching (the Amazon split-order case) is server-side
// (GET /finance/receipt-matches, App\Services\Finance\ReceiptMatcher) since it needs
// to see every user's unlinked receipt, not just the one currently open. Ported from
// the pre-SPA `shared/receipt-match.js`. Pure + testable.

export interface MatchableTx {
  id: number;
  amount: number; // signed; negative = expense
  date?: string | null;
}

export interface MatchableReceipt {
  total: number | null;
  date?: string | null;
  currency?: string | null;
}

// Rough EUR conversion rates — deliberately approximate ("ungefähr"); only used to
// SUGGEST candidates in the assignment dialog, never to auto-attach.
export const FX_TO_EUR: Record<string, number> = { EUR: 1, USD: 0.92, GBP: 1.16, CHF: 1.04 };

/** Convert an amount in `currency` to a rough EUR value, or null if unknown. */
export function approxToEur(amount: number | null, currency?: string | null, rates: Record<string, number> = FX_TO_EUR): number | null {
  const n = Number(amount);
  if (!Number.isFinite(n)) return null;
  const r = rates[String(currency || 'EUR').toUpperCase()];
  return r ? Math.round(n * r * 100) / 100 : null;
}

const DAY = 86400000;
function dayDiff(a?: string | null, b?: string | null): number | null {
  if (!a || !b) return null;
  const da = Date.parse(a);
  const db = Date.parse(b);
  if (Number.isNaN(da) || Number.isNaN(db)) return null;
  return Math.abs(da - db) / DAY;
}

/** Transactions whose absolute amount equals the receipt total to the cent. */
export function exactMatches(total: number | null, transactions: MatchableTx[]): MatchableTx[] {
  if (total == null) return [];
  return (transactions || []).filter((t) => Math.abs(Math.abs(Number(t.amount) || 0) - total) < 0.005);
}

/**
 * The single unambiguous transaction to auto-attach at capture time, or null. One
 * exact-amount match wins; if several share the amount (recurring charges), the one
 * within `dayWindow` days of the receipt date wins only when it is unique. Fuzzy/FX
 * matches never auto-attach — only an unambiguous cent-exact hit does.
 */
export function autoPick(receipt: MatchableReceipt, transactions: MatchableTx[], dayWindow = 3): MatchableTx | null {
  const ex = exactMatches(receipt?.total ?? null, transactions);
  if (ex.length === 1) return ex[0];
  if (ex.length > 1 && receipt?.date) {
    const near = ex.filter((t) => { const d = dayDiff(receipt.date, t.date); return d != null && d <= dayWindow; });
    if (near.length === 1) return near[0];
  }
  return null;
}

export interface BookingSuggestion { t: MatchableTx; kind: 'exact' | 'fx'; dd: number | null; score: number }

/**
 * Ranked candidate transactions for a receipt: amount matches (exact to the cent →
 * strongest, else within `fxTol` of the rough EUR-converted total → weaker), ordered
 * so transactions within `dayWindow` days of the receipt date come first.
 */
export function suggestBookings(
  receipt: MatchableReceipt,
  transactions: MatchableTx[],
  opts: { dayWindow?: number; fxTol?: number; limit?: number; rates?: Record<string, number> } = {},
): BookingSuggestion[] {
  const { dayWindow = 3, fxTol = 0.04, limit = 12, rates } = opts;
  const total = receipt?.total ?? null;
  const rdate = receipt?.date || null;
  const eur = total != null ? approxToEur(total, receipt?.currency, rates) : null;
  const scored: BookingSuggestion[] = [];
  for (const t of transactions || []) {
    const amt = Math.abs(Number(t.amount) || 0);
    let amountScore = 0; let kind: 'exact' | 'fx' | '' = '';
    if (total != null) {
      if (Math.abs(amt - total) < 0.005) { amountScore = 2; kind = 'exact'; }
      else if (eur != null && amt > 0 && Math.abs(amt - eur) / Math.max(amt, eur) <= fxTol) { amountScore = 1; kind = 'fx'; }
    }
    if (amountScore === 0 || !kind) continue; // amount is the inclusion criterion; date only ranks
    let dScore = 0; let dd: number | null = null;
    if (rdate && t.date) { dd = dayDiff(rdate, t.date); if (dd != null && dd <= dayWindow) dScore = 1 - dd / (dayWindow + 1); }
    scored.push({ t, kind, dd, score: amountScore + dScore });
  }
  scored.sort((a, b) => b.score - a.score || ((a.dd ?? 1e9) - (b.dd ?? 1e9)));
  return scored.slice(0, limit);
}
