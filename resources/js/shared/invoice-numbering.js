// Pure, testable invoice-numbering logic (GoBD: unique, gapless numbers). The next
// sequence is derived from the ACTUAL invoices (each records its `seq`) plus the
// legacy scalar counter and the company floor — self-correcting, so a number issued
// on another device (merged into the store) is always accounted for.

/** The highest issued sequence across the invoices + the legacy scalar. */
export function maxSeq(invoices, scalar = 0) {
    let max = Number.isFinite(scalar) ? scalar : 0;
    for (const iv of invoices || []) {
        if (Number.isFinite(iv.seq)) max = Math.max(max, iv.seq);
    }
    return max;
}

/** The next sequence to assign: one past the max, never below the company floor. */
export function nextSeq(invoices, scalar, floor) {
    const f = Number.isFinite(floor) && floor > 0 ? floor : 1;
    return Math.max(maxSeq(invoices, scalar) + 1, f);
}

/** The year (YYYY string) an invoice is dated in. */
export function invoiceYear(inv) { return String(inv && inv.issueDate || '').slice(0, 4); }

/** Highest issued sequence among invoices dated in `year`. */
export function maxSeqForYear(invoices, year) {
    let max = 0;
    const y = String(year);
    for (const iv of invoices || []) {
        if (invoiceYear(iv) === y && Number.isFinite(iv.seq)) max = Math.max(max, iv.seq);
    }
    return max;
}

/**
 * The next sequence to assign for a given year: one past that year's max, never below
 * the company floor. Per-year so numbering restarts each year (YYYY-NNNN) and a cycle
 * reset (deleting the year's invoices) legitimately restarts at the floor.
 */
export function nextSeqForYear(invoices, year, floor) {
    const f = Number.isFinite(floor) && floor > 0 ? floor : 1;
    return Math.max(maxSeqForYear(invoices, year) + 1, f);
}

/** Active (non-trashed) invoices dated in `year`. */
export function invoicesInYear(invoices, year) {
    const y = String(year);
    return (invoices || []).filter((iv) => ! iv.trashed && invoiceYear(iv) === y);
}

/** Invoice numbers assigned to more than one invoice (a GoBD violation to fix). */
export function duplicateNumbers(invoices) {
    const seen = new Map();
    for (const iv of invoices || []) {
        if (iv.number) seen.set(iv.number, (seen.get(iv.number) || 0) + 1);
    }
    return [...seen.entries()].filter(([, n]) => n > 1).map(([num]) => num);
}
