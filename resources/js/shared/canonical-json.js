// Canonical JSON (Store v3, spec §5.2) — byte-identical across JS/Swift/Go/Kotlin.
//
// Rules:
//   - Object keys sorted ascending by Unicode scalar value (code point, not UTF-16
//     code unit) so astral-plane keys order the same as in Swift/Go/Kotlin.
//   - Compact separators (`,` `:`), no insignificant whitespace.
//   - Strings NFC-normalised, minimal JSON escaping, UTF-8.
//   - Numbers: integers serialise as integers. Hot records carry NO floats
//     (decimals are fixed-6dp decimal strings or null); floats appear only in cold
//     meta blobs, which are per-photo, immutable and never hashed for dirty-detection.
//   - Booleans/null literal; arrays keep their order.
//
// Anything sealed or hashed in v3 goes through this. Gated by the §17 fixture
// (`resources/js/__tests__/fixtures/store-v3/canonical-json.json`).

/** Compare two strings by Unicode code point (not UTF-16 code unit). */
function compareCodePoints(a, b) {
    const ia = a[Symbol.iterator]();
    const ib = b[Symbol.iterator]();
    for (;;) {
        const na = ia.next();
        const nb = ib.next();
        if (na.done && nb.done) return 0;
        if (na.done) return -1;
        if (nb.done) return 1;
        const ca = na.value.codePointAt(0);
        const cb = nb.value.codePointAt(0);
        if (ca !== cb) return ca < cb ? -1 : 1;
    }
}

function encodeString(s) {
    // NFC first, then minimal JSON escaping. JSON.stringify emits exactly the
    // minimal escape set (", \, and control chars as \uXXXX / \n\r\t\b\f) and
    // leaves all other UTF-8 code points literal — which is what the contract wants.
    return JSON.stringify(s.normalize('NFC'));
}

function encodeNumber(n) {
    if (! Number.isFinite(n)) {
        throw new TypeError('canonicalJSON: non-finite number is not serialisable');
    }
    // Integers must render without a decimal point / exponent. JS renders safe
    // integers plainly; guard against floats sneaking into a hashed structure is
    // the data model's job (hot records are integer-only per §5.2).
    return String(n);
}

/**
 * Serialise a value to canonical JSON (spec §5.2). `undefined` object properties
 * are dropped (matching JSON semantics); `undefined` array entries become `null`.
 *
 * @param {*} value
 * @returns {string}
 */
export function canonicalJSON(value) {
    if (value === null) return 'null';

    switch (typeof value) {
        case 'boolean':
            return value ? 'true' : 'false';
        case 'number':
            return encodeNumber(value);
        case 'bigint':
            return value.toString();
        case 'string':
            return encodeString(value);
        case 'object':
            break;
        default:
            throw new TypeError(`canonicalJSON: unsupported type ${typeof value}`);
    }

    if (Array.isArray(value)) {
        const parts = value.map((v) => (v === undefined ? 'null' : canonicalJSON(v)));
        return `[${parts.join(',')}]`;
    }

    // Plain object: sort keys by code point, drop undefined values.
    const keys = Object.keys(value).filter((k) => value[k] !== undefined);
    keys.sort(compareCodePoints);
    const parts = keys.map((k) => `${encodeString(k)}:${canonicalJSON(value[k])}`);
    return `{${parts.join(',')}}`;
}

/** UTF-8 bytes of the canonical JSON encoding — the exact bytes that get sealed/hashed. */
export function canonicalBytes(value) {
    return new TextEncoder().encode(canonicalJSON(value));
}

/**
 * Format a decimal number as a fixed 6-dp decimal STRING (lat/lng in hot records,
 * §4.1/§5.2), or null. No trailing-zero trimming — the fixed width is what makes it
 * canonical and float-free across clients.
 *
 * @param {number|string|null|undefined} n
 * @returns {string|null}
 */
export function dec6(n) {
    if (n === null || n === undefined || n === '') return null;
    const f = typeof n === 'string' ? Number.parseFloat(n) : n;
    if (! Number.isFinite(f)) return null;

    return f.toFixed(6);
}
