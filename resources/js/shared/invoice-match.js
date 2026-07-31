// Match an incoming bank transaction to an issued invoice (client-side, ZK). Used to
// auto-mark invoices paid and attach the invoice to the transaction as a locked receipt.
// Pure + testable.

import { invoiceTotals } from './finance-stats.js';

const norm = (s) => String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();

/**
 * Candidate issued invoices: not trashed, numbered, not a draft, and NOT already linked
 * to a payment. Paid invoices stay eligible — imported invoices are created "paid", so
 * excluding paid ones would leave them unmatchable; the guard is the payment link.
 */
function candidates(invoices) {
    return (invoices || []).filter((inv) => ! inv.trashed && inv.number && inv.status !== 'draft' && ! inv.paymentTxId);
}

/**
 * Find the issued invoice an income transaction most likely settles, or null.
 * Only positive-amount transactions match. Strongest signal first:
 *   1) the invoice NUMBER appears in the purpose/reference AND the gross matches,
 *   2) the customer name appears in the counterparty/purpose AND the gross matches,
 *   3) a unique invoice with the exact gross amount.
 * Amounts must match to the cent.
 */
export function matchInvoice(tx, invoices) {
    if (! tx || ! (Number(tx.amount) > 0)) return null;
    const gross = Math.round(Number(tx.amount) * 100) / 100;
    const hay = norm(`${tx.purpose || ''} ${tx.eref || ''} ${tx.counterparty || ''}`);
    const cands = candidates(invoices);
    const amountEq = (inv) => Math.abs(invoiceTotals(inv).gross - gross) < 0.005;

    // 1) number in text + amount
    let hit = cands.find((inv) => hay.includes(norm(inv.number)) && amountEq(inv));
    if (hit) return hit;

    // 2) customer name in text + amount
    hit = cands.find((inv) => {
        const name = norm(inv.customer && inv.customer.name);
        return name.length >= 3 && hay.includes(name) && amountEq(inv);
    });
    if (hit) return hit;

    // 3) unique exact-amount match
    const byAmount = cands.filter(amountEq);
    return byAmount.length === 1 ? byAmount[0] : null;
}
