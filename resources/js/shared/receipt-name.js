// Build a human, filesystem-safe receipt filename from recognised fields:
//   "YYYYMMDD; Partner; Beleg"  — or "…; Rechnung <number>" when a number is known —
// keeping the original extension. Pure + testable; the invoices component supplies the
// localised nouns for "Beleg"/"Rechnung" and applies it on upload and on rescan.

function compactDate(d) {
    const m = String(d || '').match(/(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[1] + m[2] + m[3] : '';
}

// Drop characters that would break the "; " segmenting or a filename; collapse spaces.
function clean(s) { return String(s || '').replace(/[;/\\:*?"<>|]+/g, ' ').replace(/\s{2,}/g, ' ').trim(); }

export function buildReceiptName({ date, partner, number, belegWord = 'Beleg', invoiceWord = 'Rechnung', ext = '' } = {}) {
    const segs = [];
    const cd = compactDate(date); if (cd) segs.push(cd);
    const p = clean(partner); if (p) segs.push(p.slice(0, 60));
    const num = clean(number);
    segs.push(num ? `${clean(invoiceWord) || 'Rechnung'} ${num}` : (clean(belegWord) || 'Beleg'));
    let e = String(ext || '').trim();
    if (e && ! e.startsWith('.')) e = '.' + e;
    return segs.join('; ') + e;
}
