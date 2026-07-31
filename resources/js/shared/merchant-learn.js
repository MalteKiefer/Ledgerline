// Merchant→category learning (client-side, ZK). The user's manual category choices are
// remembered on the merchant's business partner (the partner list doubles as the rule
// store — no new collection, so the cross-client store shape is unchanged). Pure +
// testable: the invoices component wires these into upload/recognition + the settings UI.

// Normalise a company/merchant name for matching: drop legal forms + punctuation so
// "netcup GmbH", "netcup", "NETCUP  Deutschland" all collapse to the same key.
export function normMerchant(s) {
    return String(s || '')
        .toLowerCase()
        .replace(/\b(gmbh|mbh|ug|ag|kg|ohg|gbr|e\.?k\.?|co\.?|deutschland|ltd|limited|llc|inc|international|distribution)\b/g, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

// The partner (rule holder) matching a merchant name, or null. Ignores too-short keys.
export function matchPartner(partners, name) {
    const nk = normMerchant(name);
    if (nk.length < 2) return null;
    return (partners || []).find((p) => normMerchant(p.name) === nk) || null;
}

// The category the user has taught for this merchant, or '' if none.
export function learnedCategoryFor(partners, name) {
    const p = matchPartner(partners, name);
    return (p && p.category) ? p.category : '';
}
