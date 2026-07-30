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

/**
 * The ordinal sequence value of an invoice number = its LAST digit group, or null if the
 * number carries no digits. Robust across format changes: "42"→42, "R-00047"→47,
 * "2026-0001"→1, "RE-2019/58"→58. Lets a series that changed its cosmetic format over the
 * years still be read as one continuous run (the user's real case).
 */
export function invoiceSeqNum(inv) {
    const m = String((inv && inv.number) || '').trim().match(/(\d+)\s*$/);
    return m ? parseInt(m[1], 10) : null;
}

/** A YYYY-NNNN number restarts its sequence each year; anything else is one continuous run. */
function isYearPrefixed(n) { return /^\d{4}-\d+$/.test(String(n || '').trim()); }

/** Format a missing ordinal to match a sample number's shape (prefix + zero-padded digits + suffix). */
function formatSeq(sample, i) {
    const m = String(sample || '').match(/^(.*?)(\d+)(\D*)$/);
    if (! m) return String(i);
    // Zero-pad only for a fixed-width padded format (leading zero, e.g. "00047"); a plain
    // integer like "10" must yield "9", not "09".
    const digits = m[2];
    const val = digits.length > 1 && digits[0] === '0' ? String(i).padStart(digits.length, '0') : String(i);
    return m[1] + val + m[3];
}

/**
 * Missing invoice numbers — gaps in the sequence (GoBD: numbering must be gapless). Considers
 * all ACTIVE invoices (incl. imported historical ones — a gap there is exactly what to flag).
 * TWO regimes, so a format change mid-history doesn't hide a gap:
 *  - YYYY-NNNN numbers reset each year → gap-checked PER YEAR.
 *  - everything else with a trailing integer (bare "48", "R-00049", …) is treated as ONE
 *    continuous run by its ordinal, regardless of prefix — so R-00047, 48 and R-00049 are the
 *    same sequence and a jump R-00047 → R-00049 (no 48) is flagged.
 * Missing numbers are formatted like the series' most-recent (highest-ordinal) sample, e.g.
 * importing 8 and 10 yields ['9']; R-00057 then R-00061 yields R-00058..R-00060.
 */
export function missingNumbers(invoices) {
    const buckets = new Map(); // key → { nums:Set, sampleNum, sample }
    const add = (key, ord, number) => {
        if (! buckets.has(key)) buckets.set(key, { nums: new Set(), sampleNum: ord, sample: number });
        const b = buckets.get(key);
        b.nums.add(ord);
        if (ord >= b.sampleNum) { b.sampleNum = ord; b.sample = number; } // newest format wins for display
    };
    for (const iv of invoices || []) {
        if (! iv || iv.trashed) continue;
        const ord = invoiceSeqNum(iv); if (ord == null) continue;
        if (isYearPrefixed(iv.number)) add('y:' + invoiceYear(iv), ord, iv.number);
        else add('seq', ord, iv.number); // one continuous run across format changes
    }
    const missing = [];
    for (const b of buckets.values()) {
        const arr = [...b.nums]; if (arr.length < 2) continue;
        const min = Math.min(...arr), max = Math.max(...arr);
        if (max - min > 10000) continue; // guard pathological ranges
        for (let i = min; i <= max; i++) if (! b.nums.has(i)) missing.push(formatSeq(b.sample, i));
    }
    return missing;
}
