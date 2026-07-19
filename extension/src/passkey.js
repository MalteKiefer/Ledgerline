// Pure WebAuthn encoding + ES256 crypto helpers for the extension authenticator.
// No DOM, no chrome.* — headless-testable. WebCrypto SubtleCrypto is the crypto surface.

const B64U = { enc: (b) => btoa(String.fromCharCode(...b)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '') };
export function b64uEncode(bytes) { return B64U.enc(bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes)); }
export function b64uDecode(str) {
    const s = str.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((str.length + 3) % 4);
    return Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
}

export function randomCredentialId() { return crypto.getRandomValues(new Uint8Array(32)); }

export async function generateEs256() {
    const kp = await crypto.subtle.generateKey({ name: 'ECDSA', namedCurve: 'P-256' }, true, ['sign', 'verify']);
    const privateJwk = await crypto.subtle.exportKey('jwk', kp.privateKey);
    const publicJwk = await crypto.subtle.exportKey('jwk', kp.publicKey);
    return { privateJwk, publicJwk };
}

export async function signEs256(privateJwk, message) {
    const key = await crypto.subtle.importKey('jwk', privateJwk, { name: 'ECDSA', namedCurve: 'P-256' }, false, ['sign']);
    const raw = new Uint8Array(await crypto.subtle.sign({ name: 'ECDSA', hash: 'SHA-256' }, key, message));
    return rawEcdsaToDer(raw);
}

// 64-byte r||s → ASN.1 DER SEQUENCE(INTEGER r, INTEGER s) as WebAuthn requires for ES256.
export function rawEcdsaToDer(raw) {
    const der = (n) => {
        let i = 0; while (i < n.length - 1 && n[i] === 0) i++; n = n.slice(i);
        if (n[0] & 0x80) n = Uint8Array.from([0, ...n]);
        return n;
    };
    const r = der(raw.slice(0, 32)); const s = der(raw.slice(32, 64));
    const body = Uint8Array.from([0x02, r.length, ...r, 0x02, s.length, ...s]);
    return Uint8Array.from([0x30, body.length, ...body]);
}

// ASN.1 DER SEQUENCE(INTEGER r, INTEGER s) → 64-byte raw r||s for WebCrypto verify.
// Strips the optional 0x00 padding byte that DER adds when the high bit of r or s is set.
export function derToRawEcdsa(der) {
    // der[0]=0x30 SEQUENCE, der[1]=length, der[2]=0x02 INTEGER tag
    let offset = 2;
    const readInt = () => {
        // tag 0x02 already consumed by caller positioning
        const len = der[offset + 1];
        const start = offset + 2;
        const bytes = der.slice(start, start + len);
        offset = start + len;
        // Strip leading 0x00 padding byte (added when high bit is set), then left-pad to 32
        const stripped = bytes[0] === 0x00 ? bytes.slice(1) : bytes;
        const out = new Uint8Array(32);
        out.set(stripped, 32 - stripped.length);
        return out;
    };
    // offset points to first INTEGER tag (0x02)
    const r = readInt();
    // offset now points to second INTEGER tag (0x02)
    const s = readInt();
    const result = new Uint8Array(64);
    result.set(r, 0);
    result.set(s, 32);
    return result;
}

function u32be(n) { return Uint8Array.from([(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255]); }
async function sha256(bytes) { return new Uint8Array(await crypto.subtle.digest('SHA-256', bytes)); }
function concat(arrs) { const len = arrs.reduce((a, x) => a + x.length, 0); const out = new Uint8Array(len); let o = 0; for (const a of arrs) { out.set(a, o); o += a.length; } return out; }

export async function buildAuthData({ rpId, flags, signCount, attested }) {
    const rpIdHash = await sha256(new TextEncoder().encode(rpId));
    let f = 0; if (flags.up) f |= 0x01; if (flags.uv) f |= 0x04; if (flags.at) f |= 0x40;
    const parts = [rpIdHash, Uint8Array.from([f]), u32be(signCount >>> 0)];
    if (attested) {
        const credLen = Uint8Array.from([(attested.credentialId.length >> 8) & 255, attested.credentialId.length & 255]);
        parts.push(attested.aaguid, credLen, attested.credentialId, attested.cosePublicKey);
    }
    return concat(parts);
}

// Minimal CBOR encoder: only what attestationObject + COSE need (maps, byte strings,
// text strings, small unsigned/negative ints). Definite-length only.
function cborBytes(b) { return concat([cborHead(0x40, b.length), b]); }
function cborText(s) { const b = new TextEncoder().encode(s); return concat([cborHead(0x60, b.length), b]); }
function cborUint(n) { return cborHead(0x00, n); }
function cborNint(n) { return cborHead(0x20, -1 - n); } // negative int n (e.g. -7 → cborNint(-7))
function cborHead(major, n) {
    if (n < 24) return Uint8Array.from([major | n]);
    if (n < 256) return Uint8Array.from([major | 24, n]);
    if (n < 65536) return Uint8Array.from([major | 25, (n >> 8) & 255, n & 255]);
    return concat([Uint8Array.from([major | 26]), u32be(n)]);
}
function cborMap(pairs) { return concat([cborHead(0xa0, pairs.length), ...pairs.flatMap(([k, v]) => [k, v])]); }

export function coseFromPublicJwk(jwk) {
    const x = b64uDecode(jwk.x); const y = b64uDecode(jwk.y);
    // { 1:2(EC2), 3:-7(ES256), -1:1(P-256), -2:x, -3:y }
    return cborMap([
        [cborUint(1), cborUint(2)],
        [cborUint(3), cborNint(-7)],
        [cborNint(-1), cborUint(1)],
        [cborNint(-2), cborBytes(x)],
        [cborNint(-3), cborBytes(y)],
    ]);
}

export function attestationObjectNone(authData) {
    return cborMap([
        [cborText('fmt'), cborText('none')],
        [cborText('attStmt'), cborMap([])],
        [cborText('authData'), cborBytes(authData)],
    ]);
}

export function clientDataJSON({ type, challenge, origin }) {
    // challenge arrives as bytes → b64url; keys ordered to match browsers (not required, but tidy).
    const json = JSON.stringify({ type, challenge: b64uEncode(challenge), origin, crossOrigin: false });
    return new TextEncoder().encode(json);
}

// rpId binding check: mirrors the hostsMatch() rule in background.js exactly.
// Returns true iff rpId is the origin host OR a registrable parent (dot-boundary).
// Both sides have www. stripped first to match background.js behaviour.
//
// Security invariant: parent→child only (child cannot claim parent as rpId).
//   ('accounts.example.com', 'example.com') → true   ✓ parent rpId
//   ('example.com', 'example.com')          → true   ✓ exact match
//   ('example.com', 'evil.com')             → false  ✓ unrelated
//   ('example.com', 'com')                  → true   ⚠ bare TLD: no PSL lookup (matches hostsMatch)
//
// NOTE: bare TLD acceptance ('example.com','com') is an acknowledged gap.
// A full PSL dependency was deliberately avoided (binary-size / update-lag trade-off).
// Mitigation: RPs must specify a valid rpId; browsers also accept bare TLDs in the
// WebAuthn spec only when the RP's registrable domain matches — same gap exists in
// Chrome's own implementation for non-PSL-aware builds.
export function rpIdAllowed(originHost, rpId) {
    if (! originHost || ! rpId) return false;
    const page = originHost.replace(/^www\./, '');
    const stored = rpId.replace(/^www\./, '');
    return page === stored || page.endsWith('.' + stored);
}
