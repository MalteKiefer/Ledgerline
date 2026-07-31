// Ranked booking suggestions for a receipt (client-side). A charge often posts a few
// days after the receipt date and may be in another currency, so matching is fuzzy: prefer
// an exact-cent amount, else an approximate amount (rough FX conversion or a few percent
// off), and rank matches nearest the receipt date first. Pure + testable.

// Rough EUR conversion rates — deliberately approximate ("ungefähr"); only used to SUGGEST
// candidates in the assignment dialog, never to auto-attach.
export const FX_TO_EUR = { EUR: 1, USD: 0.92, GBP: 1.16, CHF: 1.04 };

/** Convert an amount in `currency` to a rough EUR value, or null if unknown. */
export function approxToEur(amount, currency, rates = FX_TO_EUR) {
    const n = Number(amount);
    if (! Number.isFinite(n)) return null;
    const r = rates[String(currency || 'EUR').toUpperCase()];
    return r ? Math.round(n * r * 100) / 100 : null;
}

const DAY = 86400000;
function dayDiff(a, b) {
    const da = Date.parse(a), db = Date.parse(b);
    if (Number.isNaN(da) || Number.isNaN(db)) return null;
    return Math.abs(da - db) / DAY;
}

/** Bookings whose absolute amount equals the receipt total to the cent. */
export function exactMatches(total, transactions) {
    if (total == null) return [];
    return (transactions || []).filter((t) => Math.abs(Math.abs(Number(t.amount) || 0) - total) < 0.005);
}

/**
 * The single unambiguous booking to auto-attach, or null. One exact-amount match wins;
 * if several share the amount (recurring charges), the one within `dayWindow` days of the
 * receipt date wins only when it is unique. Fuzzy/FX matches never auto-attach.
 */
export function autoPick(receipt, transactions, dayWindow = 3) {
    const ex = exactMatches(receipt?.total, transactions);
    if (ex.length === 1) return ex[0];
    if (ex.length > 1 && receipt?.date) {
        const near = ex.filter((t) => { const d = dayDiff(receipt.date, t.date); return d != null && d <= dayWindow; });
        if (near.length === 1) return near[0];
    }
    return null;
}

/**
 * Ranked candidate bookings for a receipt: amount matches (exact to the cent → strongest,
 * else within `fxTol` of the rough EUR-converted total → weaker), ordered so bookings
 * within `dayWindow` days of the receipt date come first. Returns `[{ t, kind, dd }]`
 * (kind: 'exact' | 'fx'); date proximity only ranks, it does not include a non-amount row.
 */
export function suggestBookings(receipt, transactions, opts = {}) {
    const { dayWindow = 3, fxTol = 0.04, limit = 12, rates } = opts;
    const total = receipt?.total;
    const rdate = receipt?.date || null;
    const eur = total != null ? approxToEur(total, receipt?.currency || 'EUR', rates) : null;
    const scored = [];
    for (const t of transactions || []) {
        const amt = Math.abs(Number(t.amount) || 0);
        let amountScore = 0, kind = '';
        if (total != null) {
            if (Math.abs(amt - total) < 0.005) { amountScore = 2; kind = 'exact'; }
            else if (eur != null && amt > 0 && Math.abs(amt - eur) / Math.max(amt, eur) <= fxTol) { amountScore = 1; kind = 'fx'; }
        }
        if (amountScore === 0) continue; // amount is the inclusion criterion; date only ranks
        let dScore = 0, dd = null;
        if (rdate && t.date) { dd = dayDiff(rdate, t.date); if (dd != null && dd <= dayWindow) dScore = 1 - dd / (dayWindow + 1); }
        scored.push({ t, kind, dd, score: amountScore + dScore });
    }
    scored.sort((a, b) => b.score - a.score || ((a.dd ?? 1e9) - (b.dd ?? 1e9)));
    return scored.slice(0, limit);
}
