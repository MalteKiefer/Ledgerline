// Conformance test: the Node mail-sealer's output MUST be decryptable by the
// EXISTING web client crypto (pq-kem.js hybridUnwrap + vault.js secretstream).
// If this drifts, the server can seal a mail the browser can never open.

import { describe, it, expect } from 'vitest';
import { sealMessage } from '../seal.mjs';
import { mlkemKeypair, hybridUnwrap } from '../../shared/pq-kem.js';
import sodium from 'libsodium-wrappers-sumo';

/**
 * Open a secretstream blob (header ‖ [u32le(len) ‖ cipher]*) with a raw key.
 * This is a faithful mirror of vault.js `decryptFileWith` / `ShareCrypto.decrypt`
 * — the true inverse of the sealer's framing. It must not diverge from vault.js.
 */
function secretstreamOpen(blob, key) {
    const bytes = blob instanceof Uint8Array ? blob : new Uint8Array(blob);
    const H = sodium.crypto_secretstream_xchacha20poly1305_HEADERBYTES;
    const state = sodium.crypto_secretstream_xchacha20poly1305_init_pull(bytes.subarray(0, H), key);
    const chunks = [];
    let off = H;
    for (;;) {
        const len = (bytes[off] | (bytes[off + 1] << 8) | (bytes[off + 2] << 16) | (bytes[off + 3] << 24)) >>> 0;
        off += 4;
        const res = sodium.crypto_secretstream_xchacha20poly1305_pull(state, bytes.subarray(off, off + len));
        if (res === false) throw new Error('decrypt failed');
        off += len;
        chunks.push(res.message);
        if (res.tag === sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL) break;
    }
    let total = 0;
    for (const c of chunks) total += c.length;
    const out = new Uint8Array(total);
    let p = 0;
    for (const c of chunks) { out.set(c, p); p += c.length; }
    return out;
}

describe('mail sealer ↔ client interop', () => {
    it('server-sealed message decrypts byte-exact with the client crypto', async () => {
        await sodium.ready;
        // A recipient identity keypair, exactly as the client would hold it.
        const x = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();

        // Multi-chunk payload: 5000 × ~40 bytes ≈ 200 KB. Bump past 4 MiB so the
        // chunk framing is genuinely exercised (multiple secretstream messages).
        const raw = sodium.from_string('From: a@b\r\nSubject: hi\r\n\r\nbody ✓ '.repeat(150000));
        expect(raw.length).toBeGreaterThan(4 * 1024 * 1024);

        const { sealedKey, blob } = await sealMessage(
            raw,
            sodium.to_base64(x.publicKey, sodium.base64_variants.ORIGINAL),
            ek,
        );

        // sealedKey is the JSON suite-envelope the client's hybridUnwrap consumes.
        const env = JSON.parse(sealedKey);
        expect(env.suite).toBe(1);
        expect(env).toHaveProperty('epk');
        expect(env).toHaveProperty('kem_ct');
        expect(env).toHaveProperty('c');
        expect(env).toHaveProperty('n');

        // Client side: unwrap the per-message symmetric key, then open the blob.
        const key = await hybridUnwrap(env, sodium.to_base64(x.privateKey, sodium.base64_variants.ORIGINAL), seed, '');
        const opened = secretstreamOpen(blob, key);

        expect(sodium.to_hex(opened)).toBe(sodium.to_hex(raw));
    });

    it('a small single-chunk message also round-trips byte-exact', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();
        const raw = sodium.from_string('tiny ✓');

        const { sealedKey, blob } = await sealMessage(
            raw,
            sodium.to_base64(x.publicKey, sodium.base64_variants.ORIGINAL),
            ek,
        );
        const key = await hybridUnwrap(JSON.parse(sealedKey), sodium.to_base64(x.privateKey, sodium.base64_variants.ORIGINAL), seed, '');
        expect(sodium.to_hex(secretstreamOpen(blob, key))).toBe(sodium.to_hex(raw));
    });

    it('an empty message round-trips (one final empty chunk)', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();
        const raw = new Uint8Array(0);

        const { sealedKey, blob } = await sealMessage(
            raw,
            sodium.to_base64(x.publicKey, sodium.base64_variants.ORIGINAL),
            ek,
        );
        const key = await hybridUnwrap(JSON.parse(sealedKey), sodium.to_base64(x.privateKey, sodium.base64_variants.ORIGINAL), seed, '');
        expect(secretstreamOpen(blob, key).length).toBe(0);
    });

    it('fails closed: a WRONG ML-KEM seed cannot open the sealed key', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const { ek } = await mlkemKeypair();
        const wrong = await mlkemKeypair(); // attacker's unrelated identity

        const raw = sodium.from_string('secret mail body');
        const { sealedKey } = await sealMessage(
            raw,
            sodium.to_base64(x.publicKey, sodium.base64_variants.ORIGINAL),
            ek,
        );

        // Right X25519 key, WRONG ML-KEM seed → hybrid derived key is wrong →
        // secretbox auth must fail (fail-closed, never returns the key).
        await expect(
            hybridUnwrap(JSON.parse(sealedKey), sodium.to_base64(x.privateKey, sodium.base64_variants.ORIGINAL), wrong.seed, ''),
        ).rejects.toThrow();
    });

    it('fails closed: a WRONG X25519 secret key cannot open the sealed key', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const attacker = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();

        const raw = sodium.from_string('secret mail body');
        const { sealedKey } = await sealMessage(
            raw,
            sodium.to_base64(x.publicKey, sodium.base64_variants.ORIGINAL),
            ek,
        );

        await expect(
            hybridUnwrap(JSON.parse(sealedKey), sodium.to_base64(attacker.privateKey, sodium.base64_variants.ORIGINAL), seed, ''),
        ).rejects.toThrow();
    });
});
