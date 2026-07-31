// Pure helpers for the password manager, split out so they can be unit-tested
// (Vitest) independently of the Alpine component. No DOM, no `this`, no network.

/** Minimal RFC 4180-ish CSV parser (handles quotes, escaped quotes, CRLF). */
export function parseCsv(text) {
    const rows = []; let row = [], cur = '', q = false;
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (q) { if (ch === '"') { if (text[i + 1] === '"') { cur += '"'; i++; } else q = false; } else cur += ch; } else if (ch === '"') q = true;
        else if (ch === ',') { row.push(cur); cur = ''; } else if (ch === '\n') { row.push(cur); rows.push(row); row = []; cur = ''; } else if (ch !== '\r') cur += ch;
    }
    if (cur !== '' || row.length) { row.push(cur); rows.push(row); }
    return rows;
}

/** Guess a CSV export's origin from its (lower-cased) header row. */
export function detectCsv(h) {
    const has = (k) => h.includes(k);
    if (has('login_password') || has('login_uri')) return 'bitwarden_csv';
    if (has('grouping') && has('url') && has('username')) return 'lastpass';
    if (has('otpauth')) return 'onepassword';
    if ((has('title') || has('account')) && has('password') && has('group')) return 'keepass';
    if (has('title') && has('password') && has('url')) return 'onepassword';
    return 'generic';
}

/** Credit-card brand from the number (IIN/BIN ranges), or '' if unknown. */
export function cardBrand(number) {
    const n = String(number || '').replace(/\D/g, '');
    if (! n) return '';
    if (/^4/.test(n)) return 'Visa';
    if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/.test(n)) return 'Mastercard';
    if (/^3[47]/.test(n)) return 'Amex';
    if (/^(6011|65|64[4-9]|622)/.test(n)) return 'Discover';
    if (/^3(0[0-5]|[68])/.test(n)) return 'Diners Club';
    if (/^35/.test(n)) return 'JCB';
    if (/^(50|5[6-9]|6[0-9])/.test(n)) return 'Maestro';
    return '';
}

/** Reduce an otpauth:// URI to its base32 secret; pass a raw secret through. */
export function totpSecret(v) {
    v = String(v || '').trim(); if (! v) return '';
    if (/^otpauth:\/\//i.test(v)) { try { return new URL(v).searchParams.get('secret') || ''; } catch (e) { const m = v.match(/[?&]secret=([^&]+)/i); return m ? decodeURIComponent(m[1]) : ''; } }
    return v;
}

/** RFC 4648 base32 decode → Uint8Array (ignores spaces/padding/case). */
export function base32Decode(s) {
    const A = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    s = String(s || '').toUpperCase().replace(/[^A-Z2-7]/g, '');
    let bits = 0, val = 0; const out = [];
    for (const c of s) { const i = A.indexOf(c); if (i < 0) continue; val = (val << 5) | i; bits += 5; if (bits >= 8) { out.push((val >>> (bits - 8)) & 0xff); bits -= 8; } }
    return new Uint8Array(out);
}

/** RFC 6238 TOTP (SHA-1, 6 digits) via WebCrypto. */
export async function totp(secret, now, period = 30) {
    const key = base32Decode(secret); if (! key.length) return '';
    const counter = Math.floor(now / period);
    const buf = new ArrayBuffer(8); const dv = new DataView(buf);
    dv.setUint32(0, Math.floor(counter / 2 ** 32)); dv.setUint32(4, counter >>> 0);
    const ck = await crypto.subtle.importKey('raw', key, { name: 'HMAC', hash: 'SHA-1' }, false, ['sign']);
    const h = new Uint8Array(await crypto.subtle.sign('HMAC', ck, buf));
    const o = h[h.length - 1] & 0xf;
    const bin = ((h[o] & 0x7f) << 24) | ((h[o + 1] & 0xff) << 16) | ((h[o + 2] & 0xff) << 8) | (h[o + 3] & 0xff);
    return String(bin % 1000000).padStart(6, '0');
}

/** Rough password strength 0–4 (length + character-class variety). */
export function pwScore(pw) {
    pw = String(pw || ''); let s = 0;
    if (pw.length >= 8) s++;
    if (pw.length >= 12) s++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^a-zA-Z0-9]/.test(pw)) s++;
    return Math.min(4, s);
}
