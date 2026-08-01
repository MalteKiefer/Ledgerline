// EPC069-12 (GiroCode / EPC QR) payload for a SEPA credit transfer. Pure — the
// caller encodes the returned string to a QR image. SEPA/EUR only: a GiroCode is
// undefined for non-EUR, so callers must gate on currency === 'EUR'.

/** Strip spaces from an IBAN and upper-case it. */
export function normalizeIban(iban) {
    return String(iban || '').replace(/\s+/g, '').toUpperCase();
}

/** True if a GiroCode can be built (valid-ish IBAN, positive EUR amount within range). */
export function canEpcQr({ iban, amount, currency } = {}) {
    const ib = normalizeIban(iban);
    const amt = Number(amount);
    return /^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(ib) && (currency || 'EUR') === 'EUR' && amt >= 0.01 && amt <= 999999999.99;
}

/**
 * Build the EPC069-12 v2 payload (service tag SCT). Fields are LF-separated.
 * BIC is optional for EUR SEPA (version 002). Name capped 70, remittance 140.
 */
export function buildEpcPayload({ name, iban, bic, amount, currency = 'EUR', reference = '' } = {}) {
    const amt = Number(amount);
    if (! canEpcQr({ iban, amount: amt, currency })) return null;
    const clip = (s, n) => String(s || '').replace(/[\r\n]+/g, ' ').trim().slice(0, n);
    return [
        'BCD',
        '002',
        '1',
        'SCT',
        clip(bic, 11),
        clip(name, 70),
        normalizeIban(iban),
        'EUR' + amt.toFixed(2),
        '',            // purpose code
        '',            // structured creditor reference
        clip(reference, 140), // unstructured remittance (e.g. invoice number)
    ].join('\n');
}
