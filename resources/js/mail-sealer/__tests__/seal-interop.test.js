// Conformance test: the Node mail-sealer's output MUST be decryptable by the
// EXISTING web client crypto. We deliberately open the sealed blob with the REAL
// client code — `ShareCrypto.decrypt` from vault.js (which takes a raw per-file
// key + the same framed secretstream) — and `hybridUnwrap` from pq-kem.js for the
// KEM half. If either drifts, the server could seal a mail the browser can't open.

import { describe, it, expect } from 'vitest';
import { execFileSync } from 'node:child_process';
import { sealMessage } from '../seal.mjs';
import { mlkemKeypair, hybridUnwrap } from '../../shared/pq-kem.js';
import { ShareCrypto } from '../../vault.js';
import sodium from 'libsodium-wrappers-sumo';

const b64 = (bytes) => sodium.to_base64(bytes, sodium.base64_variants.ORIGINAL);

describe('mail sealer ↔ client interop', () => {
    it('server-sealed message decrypts byte-exact with the real client crypto', async () => {
        await sodium.ready;
        // A recipient identity keypair, exactly as the client would hold it.
        const x = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();

        // Multi-chunk payload (> 4 MiB) so the chunk framing is genuinely exercised
        // through the real ShareCrypto.decrypt loop (multiple secretstream messages).
        const raw = sodium.from_string('From: a@b\r\nSubject: hi\r\n\r\nbody ✓ '.repeat(150000));
        expect(raw.length).toBeGreaterThan(4 * 1024 * 1024);

        const { sealedKey, blob } = await sealMessage(raw, b64(x.publicKey), ek);

        // sealedKey is the JSON suite-envelope the client's hybridUnwrap consumes.
        const env = JSON.parse(sealedKey);
        expect(env.suite).toBe(1);
        expect(env).toHaveProperty('epk');
        expect(env).toHaveProperty('kem_ct');
        expect(env).toHaveProperty('c');
        expect(env).toHaveProperty('n');

        // Client side: unwrap the per-message symmetric key, then open the blob with
        // the REAL client decrypt (not a copy).
        const key = await hybridUnwrap(env, b64(x.privateKey), seed, '');
        const opened = await ShareCrypto.decrypt(blob, key);

        expect(sodium.to_hex(opened)).toBe(sodium.to_hex(raw));
    });

    it('a small single-chunk message also round-trips byte-exact', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();
        const raw = sodium.from_string('tiny ✓');

        const { sealedKey, blob } = await sealMessage(raw, b64(x.publicKey), ek);
        const key = await hybridUnwrap(JSON.parse(sealedKey), b64(x.privateKey), seed, '');
        expect(sodium.to_hex(await ShareCrypto.decrypt(blob, key))).toBe(sodium.to_hex(raw));
    });

    it('an empty message round-trips (one final empty chunk)', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();
        const raw = new Uint8Array(0);

        const { sealedKey, blob } = await sealMessage(raw, b64(x.publicKey), ek);
        const key = await hybridUnwrap(JSON.parse(sealedKey), b64(x.privateKey), seed, '');
        expect((await ShareCrypto.decrypt(blob, key)).length).toBe(0);
    });

    it('CLI entry emits <sealedKey>\\n<blob> framing that round-trips byte-exact', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();

        // Multi-chunk (> 4 MiB) so the CLI framing the PHP wrapper parses is guarded
        // against a chunk boundary landing near the newline delimiter.
        const raw = sodium.from_string('CLI ✓ '.repeat(900000));
        expect(raw.length).toBeGreaterThan(4 * 1024 * 1024);

        const out = execFileSync(
            'node',
            ['resources/js/mail-sealer/seal.mjs', b64(x.publicKey), ek],
            { input: raw, maxBuffer: 1 << 26 },
        );

        // Framing: first line (up to the first 0x0A) = sealedKey JSON; rest = blob.
        const nl = out.indexOf(0x0a);
        expect(nl).toBeGreaterThan(0);
        const sealedKey = out.subarray(0, nl).toString('utf8');
        const blob = new Uint8Array(out.subarray(nl + 1));

        const key = await hybridUnwrap(JSON.parse(sealedKey), b64(x.privateKey), seed, '');
        const opened = await ShareCrypto.decrypt(blob, key);
        expect(sodium.to_hex(opened)).toBe(sodium.to_hex(raw));
    });

    it('fails closed: a WRONG ML-KEM seed cannot open the sealed key', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const { ek } = await mlkemKeypair();
        const wrong = await mlkemKeypair(); // attacker's unrelated identity

        const raw = sodium.from_string('secret mail body');
        const { sealedKey } = await sealMessage(raw, b64(x.publicKey), ek);

        // Right X25519 key, WRONG ML-KEM seed → hybrid derived key is wrong →
        // secretbox auth must fail (fail-closed, never returns the key).
        await expect(
            hybridUnwrap(JSON.parse(sealedKey), b64(x.privateKey), wrong.seed, ''),
        ).rejects.toThrow();
    });

    it('fails closed: a WRONG X25519 secret key cannot open the sealed key', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const attacker = sodium.crypto_box_keypair();
        const { ek, seed } = await mlkemKeypair();

        const raw = sodium.from_string('secret mail body');
        const { sealedKey } = await sealMessage(raw, b64(x.publicKey), ek);

        await expect(
            hybridUnwrap(JSON.parse(sealedKey), b64(attacker.privateKey), seed, ''),
        ).rejects.toThrow();
    });
});
