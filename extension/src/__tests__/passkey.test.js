import { describe, it, expect } from 'vitest';
import * as pk from '../passkey.js';

describe('base64url', () => {
    it('round-trips', () => {
        const b = new Uint8Array([0, 1, 2, 250, 255]);
        expect(Array.from(pk.b64uDecode(pk.b64uEncode(b)))).toEqual(Array.from(b));
    });
    it('emits no padding and url alphabet', () => {
        expect(pk.b64uEncode(new Uint8Array([255, 255, 255]))).not.toContain('=');
        expect(pk.b64uEncode(new Uint8Array([251, 255]))).toMatch(/^[A-Za-z0-9_-]+$/);
    });
});

describe('rawEcdsaToDer', () => {
    it('wraps a 64-byte r||s into a DER SEQUENCE of two INTEGERs', () => {
        const raw = new Uint8Array(64).fill(0x01);
        const der = pk.rawEcdsaToDer(raw);
        expect(der[0]).toBe(0x30); // SEQUENCE
        expect(der[2]).toBe(0x02); // INTEGER (r)
    });
    it('adds a 0x00 prefix when the high bit of r or s is set', () => {
        const raw = new Uint8Array(64); raw[0] = 0x80; raw[32] = 0x80;
        const der = pk.rawEcdsaToDer(raw);
        // r integer content is 33 bytes (0x00 + 32) => length byte 0x21
        expect(der[3]).toBe(0x21);
        // s integer starts at 4 + 33 = 37: tag 0x02 at 37, length at 38 => also 0x21
        expect(der[38]).toBe(0x21);
    });
});

describe('derToRawEcdsa', () => {
    it('round-trips rawEcdsaToDer → derToRawEcdsa, recovering the 64-byte form', () => {
        // Use a value with no high-bit set so no 0x00-padding is added
        const original = new Uint8Array(64).fill(0x01);
        const der = pk.rawEcdsaToDer(original);
        const recovered = pk.derToRawEcdsa(der);
        expect(recovered.length).toBe(64);
        expect(Array.from(recovered)).toEqual(Array.from(original));
    });
    it('strips the 0x00 padding byte when the high bit is set', () => {
        // r[0]=0x80 and s[0]=0x80 → rawEcdsaToDer adds 0x00 prefix for each;
        // derToRawEcdsa must strip those prefixes, returning the original 64 bytes.
        const raw = new Uint8Array(64);
        raw[0] = 0x80; // high bit set on r → DER adds 0x00 prefix
        raw[32] = 0x80; // high bit set on s → DER adds 0x00 prefix
        const der = pk.rawEcdsaToDer(raw);
        const recovered = pk.derToRawEcdsa(der);
        expect(recovered.length).toBe(64);
        expect(recovered[0]).toBe(0x80);
        expect(recovered[32]).toBe(0x80);
        expect(Array.from(recovered)).toEqual(Array.from(raw));
    });
});

describe('authenticatorData', () => {
    it('lays out rpIdHash(32) + flags(1) + signCount(4) with attested data', async () => {
        const ad = await pk.buildAuthData({
            rpId: 'example.com',
            flags: { up: true, uv: true, at: true },
            signCount: 0,
            attested: { aaguid: new Uint8Array(16), credentialId: new Uint8Array(32).fill(7), cosePublicKey: new Uint8Array([0xa1, 0x01, 0x02]) },
        });
        expect(ad.length).toBe(32 + 1 + 4 + 16 + 2 + 32 + 3);
        expect(ad[32]).toBe(0x45); // UP(0x01)|UV(0x04)|AT(0x40)
        expect(Array.from(ad.slice(33, 37))).toEqual([0, 0, 0, 0]); // signCount 0
    });
    it('omits attested data and sets AT=0 for an assertion', async () => {
        const ad = await pk.buildAuthData({ rpId: 'example.com', flags: { up: true, uv: true, at: false }, signCount: 0 });
        expect(ad.length).toBe(37);
        expect(ad[32]).toBe(0x05); // UP|UV, no AT
    });
});

describe('ES256 sign/verify round-trip', () => {
    it('produces a DER signature that WebCrypto verifies', async () => {
        const { privateJwk, publicJwk } = await pk.generateEs256();
        const msg = new TextEncoder().encode('hello');
        const der = await pk.signEs256(privateJwk, msg);

        // Structural assertions (cheap, catch layout regressions)
        expect(der[0]).toBe(0x30); // SEQUENCE tag
        expect(der[2]).toBe(0x02); // first INTEGER tag (r)
        expect(publicJwk.crv).toBe('P-256');
        expect(publicJwk.x).toBeTruthy();
        expect(privateJwk.d).toBeTruthy();

        // Real cryptographic round-trip: import public key and verify.
        // subtle.verify({name:'ECDSA',hash:'SHA-256'}) expects raw r||s (64 bytes),
        // so we must convert our DER output back to raw first.
        const pubKey = await crypto.subtle.importKey(
            'jwk', publicJwk,
            { name: 'ECDSA', namedCurve: 'P-256' },
            false,
            ['verify'],
        );
        const rawSig = pk.derToRawEcdsa(der);
        const valid = await crypto.subtle.verify(
            { name: 'ECDSA', hash: 'SHA-256' },
            pubKey, rawSig, msg,
        );
        expect(valid).toBe(true);

        // Tampered message must NOT verify
        const tampered = new TextEncoder().encode('HELLO');
        const invalid = await crypto.subtle.verify(
            { name: 'ECDSA', hash: 'SHA-256' },
            pubKey, rawSig, tampered,
        );
        expect(invalid).toBe(false);
    });
});

describe('COSE key', () => {
    it('encodes an EC2 P-256 key: kty=2, alg=-7, crv=1, x/y 32 bytes', () => {
        const jwk = { kty: 'EC', crv: 'P-256', x: pk.b64uEncode(new Uint8Array(32).fill(1)), y: pk.b64uEncode(new Uint8Array(32).fill(2)) };
        const cose = pk.coseFromPublicJwk(jwk);
        expect(cose[0]).toBe(0xa5); // CBOR map of 5 entries
    });
});

describe('rpIdAllowed', () => {
    // Mirrors hostsMatch() in background.js: page===stored OR page.endsWith('.'+stored),
    // both sides www.-stripped. Parent→child only (child cannot claim parent as rpId).

    it('allows exact match', () => {
        expect(pk.rpIdAllowed('example.com', 'example.com')).toBe(true);
    });
    it('allows parent rpId for a subdomain origin', () => {
        expect(pk.rpIdAllowed('accounts.example.com', 'example.com')).toBe(true);
    });
    it('rejects unrelated domain', () => {
        expect(pk.rpIdAllowed('example.com', 'evil.com')).toBe(false);
    });
    it('rejects child-as-rpId for a parent origin (never reverse direction)', () => {
        // example.com origin cannot use accounts.example.com as rpId
        expect(pk.rpIdAllowed('example.com', 'accounts.example.com')).toBe(false);
    });
    it('rejects bare TLD (no dot in rpId) — credential-scope vulnerability', () => {
        // A bare TLD rpId ('com', 'net') would match every site → reject unconditionally.
        expect(pk.rpIdAllowed('example.com', 'com')).toBe(false);
        expect(pk.rpIdAllowed('example.co.uk', 'co.uk')).toBe(true); // co.uk has a dot → allowed
    });
    it('rejects empty origin or rpId', () => {
        expect(pk.rpIdAllowed('', 'example.com')).toBe(false);
        expect(pk.rpIdAllowed('example.com', '')).toBe(false);
        expect(pk.rpIdAllowed(null, 'example.com')).toBe(false);
    });
    it('strips www. from both sides', () => {
        expect(pk.rpIdAllowed('www.example.com', 'example.com')).toBe(true);
        expect(pk.rpIdAllowed('accounts.example.com', 'www.example.com')).toBe(true);
    });
    it('does not match on suffix without dot boundary', () => {
        // 'notexample.com' must not match rpId 'example.com'
        expect(pk.rpIdAllowed('notexample.com', 'example.com')).toBe(false);
    });
});
