// Does a booking amount match a free-text search query? Accepts '.' or ',' as the decimal
// separator and an optional leading '-' (sign), and ignores spaces/currency. Substring
// match against both the signed and absolute two-decimal rendering, so "-9" matches -9.88,
// "9,88" matches 9.88, and "-20" matches -20.28 but not 133.88. Pure + testable.
export function amountMatches(amount, query) {
    let q = String(query || '').toLowerCase().replace(/eur/g, '').replace(/[\s€]/g, '').replace(',', '.');
    q = q.replace(/[^0-9.-]/g, '');
    if (! /[0-9]/.test(q)) return false;
    const n = Number(amount);
    if (! Number.isFinite(n)) return false;
    return n.toFixed(2).includes(q) || Math.abs(n).toFixed(2).includes(q);
}
