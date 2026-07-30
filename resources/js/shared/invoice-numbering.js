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

/**
 * Invoice numbers assigned to more than one ACTIVE invoice within the same year — a real
 * GoBD violation to fix. Trashed invoices are ignored (a deleted-then-re-imported number is
 * not a duplicate), and the same bare number in two different years (each its own YYYY
 * series) is legitimate, so numbers are keyed by year. Returns the offending numbers.
 */
export function duplicateNumbers(invoices) {
    const seen = new Map();
    for (const iv of invoices || []) {
        if (! iv || iv.trashed || ! iv.number) continue;
        const key = invoiceYear(iv) + '|' + iv.number;
        const e = seen.get(key) || { n: 0, num: iv.number };
        e.n += 1; seen.set(key, e);
    }
    return [...seen.values()].filter((e) => e.n > 1).map((e) => e.num);
}

/** The numeric sequence value of an invoice number, or null if not a plain/dated integer. */
export function invoiceSeqNum(inv) {
    const n = String((inv && inv.number) || '').trim();
    let m = n.match(/^(?:\d{4}-)0*(\d+)$/); if (m) return parseInt(m[1], 10); // YYYY-NNNN
    m = n.match(/^0*(\d+)$/); if (m) return parseInt(m[1], 10); // bare integer
    return null; // e.g. R-2024-00042 or non-numeric → not gap-checked
}

/** Format a missing sequence number to match a sample number's shape (padding / YYYY- prefix). */
function formatSeq(sample, i) {
    const m = String(sample || '').match(/^(\d{4})-(\d+)$/);
    if (m) return `${m[1]}-${String(i).padStart(m[2].length, '0')}`;
    return String(i);
}

/**
 * Missing invoice numbers — gaps in the per-year integer sequence (GoBD: numbering must be
 * gapless). Considers all ACTIVE invoices (incl. imported historical ones — a gap there is
 * exactly what to flag). Only plain-integer / YYYY-NNNN numbers are gap-checked; prefixed
 * formats (R-…) are skipped. Returns the missing numbers formatted like their year's series
 * (e.g. importing 8 and 10 yields ['9']).
 */
export function missingNumbers(invoices) {
    const byYear = new Map();
    for (const iv of invoices || []) {
        if (! iv || iv.trashed) continue;
        const v = invoiceSeqNum(iv); if (v == null) continue;
        const y = invoiceYear(iv);
        if (! byYear.has(y)) byYear.set(y, { nums: new Set(), sample: iv.number });
        byYear.get(y).nums.add(v);
    }
    const missing = [];
    for (const g of byYear.values()) {
        const arr = [...g.nums]; if (arr.length < 2) continue;
        const min = Math.min(...arr), max = Math.max(...arr);
        if (max - min > 10000) continue; // guard pathological ranges
        for (let i = min; i <= max; i++) if (! g.nums.has(i)) missing.push(formatSeq(g.sample, i));
    }
    return missing;
}
