/**
 * Extension crypto round-trip tests.
 *
 * Guards the ZK invariant: extension/src/crypto.js must be a faithful mirror of
 * vault.js — same KDF, same secretbox scheme, same padding algorithm. Any drift
 * would silently break cross-client reads.
 *
 * All tests use fast KDF parameters (OPSLIMIT_MIN / MEMLIMIT_MIN) so the suite
 * runs in milliseconds. The deterministic-fixture test uses pre-computed values
 * that were produced with the same params and can be reproduced by anyone running
 * the commented node command at the top of each describe block.
 */
import { describe, it, expect, beforeAll } from 'vitest';
import _sodium from 'libsodium-wrappers-sumo';
import {
    deriveVaultKey,
    sealManifest,
    openManifest,
    unwrapVaultKey,
    unwrapIdentitySecret,
    unwrapMlkemSecret,
} from '../crypto.js';
// The web app is the wrapper (Store v3 §6.3 hybrid KEM); the extension only
// unwraps. Import the web's wrap side to prove web→extension interop.
import { hybridWrap, mlkemKeypair } from '../../../resources/js/shared/pq-kem.js';

// ---- sodium bootstrap -------------------------------------------------------
// crypto.js initialises libsodium lazily inside its own ready() call.
// We also need it here to build fixtures (wrap operations) without going through
// the higher-level API. We await it once and reuse the reference.
let s;
beforeAll(async () => {
    await _sodium.ready;
    s = _sodium;
});

// ---- helpers ----------------------------------------------------------------
const b64 = (bytes) => s.to_base64(bytes, s.base64_variants.ORIGINAL);
const fromB64 = (str) => s.from_base64(str, s.base64_variants.ORIGINAL);

// Build a synthetic vault object exactly as the server stores it:
// derive KEK with given params, seal a random (or fixed) VK under it.
function buildVaultObject(passphrase, saltBytes, ops, mem, vkBytes) {
    const kek = s.crypto_pwhash(
        s.crypto_secretbox_KEYBYTES,
        passphrase,
        saltBytes,
        ops,
        mem,
        s.crypto_pwhash_ALG_ARGON2ID13,
    );
    const nonce = s.randombytes_buf(s.crypto_secretbox_NONCEBYTES);
    const cipher = s.crypto_secretbox_easy(vkBytes, nonce, kek);
    return {
        salt: b64(saltBytes),
        kdf_ops: ops,
        kdf_mem: mem,
        wrapped_vault_key: b64(cipher),
        wrap_nonce: b64(nonce),
    };
}

// ---- deriveVaultKey ---------------------------------------------------------

describe('deriveVaultKey', () => {
    // Deterministic fixture computed with:
    //   passphrase = 'test-passphrase'
    //   salt       = 16 zero bytes
    //   ops/mem    = OPSLIMIT_MIN (1) / MEMLIMIT_MIN (8192)
    //   VK         = 32 bytes of 0xAB
    //
    //   node -e "
    //     const s = require('libsodium-wrappers-sumo');
    //     s.ready.then(() => {
    //       const kek = s.crypto_pwhash(32, 'test-passphrase',
    //         new Uint8Array(16), 1, 8192, s.crypto_pwhash_ALG_ARGON2ID13);
    //       const nonce = new Uint8Array(s.crypto_secretbox_NONCEBYTES); // zeros
    //       const vk = new Uint8Array(32).fill(0xAB);
    //       const cipher = s.crypto_secretbox_easy(vk, nonce, kek);
    //       const b64 = b => s.to_base64(b, s.base64_variants.ORIGINAL);
    //       console.log(JSON.stringify({
    //         salt:             b64(new Uint8Array(16)),
    //         wrap_nonce:       b64(nonce),
    //         wrapped_vault_key:b64(cipher),
    //       }));
    //     });"
    const FIXTURE_VAULT = {
        salt: 'AAAAAAAAAAAAAAAAAAAAAA==',
        kdf_ops: 1,   // OPSLIMIT_MIN — fast, not production strength
        kdf_mem: 8192, // MEMLIMIT_MIN — fast, not production strength
        wrapped_vault_key: 'OjQxjKcEkjwVWyXIs6M+jZG1MomI0bZpwIy9jk5FSGMJSLFHM6zXnX8/yuK/ZxUi',
        wrap_nonce: 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
    };
    const EXPECTED_VK_HEX = 'abababababababababababababababababababababababababababababababab';

    it('produces the pre-computed deterministic vault key for the zero-salt fixture', async () => {
        const vkBytes = await deriveVaultKey('test-passphrase', FIXTURE_VAULT);
        expect(s.to_hex(vkBytes)).toBe(EXPECTED_VK_HEX);
    });

    it('returns a Uint8Array of 32 bytes (crypto_secretbox_KEYBYTES)', async () => {
        const vkBytes = await deriveVaultKey('test-passphrase', FIXTURE_VAULT);
        expect(vkBytes).toBeInstanceOf(Uint8Array);
        expect(vkBytes.length).toBe(32);
    });

    it('throws on a wrong passphrase (auth-tag mismatch)', async () => {
        await expect(deriveVaultKey('wrong-passphrase', FIXTURE_VAULT)).rejects.toThrow();
    });

    it('round-trip: derive VK, use it to re-seal a manifest, then open', async () => {
        // Build a fresh vault object with random VK and random salt
        const vkBytes = s.randombytes_buf(32);
        const saltBytes = s.randombytes_buf(16);
        const vault = buildVaultObject(
            'round-trip-passphrase',
            saltBytes,
            s.crypto_pwhash_OPSLIMIT_MIN,
            s.crypto_pwhash_MEMLIMIT_MIN,
            vkBytes,
        );

        const recovered = await deriveVaultKey('round-trip-passphrase', vault);
        expect(s.to_hex(recovered)).toBe(s.to_hex(vkBytes));
    });

    it('two different passphrases yield different vault keys', async () => {
        const vkBytes = s.randombytes_buf(32);
        const saltBytes = s.randombytes_buf(16);
        const vault = buildVaultObject(
            'pass-a',
            saltBytes,
            s.crypto_pwhash_OPSLIMIT_MIN,
            s.crypto_pwhash_MEMLIMIT_MIN,
            vkBytes,
        );

        // Only 'pass-a' can unwrap; 'pass-b' throws
        const recoveredA = await deriveVaultKey('pass-a', vault);
        expect(s.to_hex(recoveredA)).toBe(s.to_hex(vkBytes));
        await expect(deriveVaultKey('pass-b', vault)).rejects.toThrow();
    });
});

// ---- sealManifest / openManifest --------------------------------------------

describe('sealManifest / openManifest', () => {
    it('round-trips a simple object', async () => {
        const vk = s.randombytes_buf(32);
        const obj = { items: ['x', 'y'], count: 2, nested: { ok: true } };

        const sealed = await sealManifest(obj, vk);
        const opened = await openManifest(sealed, vk);

        expect(opened).toEqual(obj);
    });

    it('round-trips an empty object', async () => {
        const vk = s.randombytes_buf(32);
        const sealed = await sealManifest({}, vk);
        expect(await openManifest(sealed, vk)).toEqual({});
    });

    it('round-trips a deeply nested object', async () => {
        const vk = s.randombytes_buf(32);
        const obj = { a: { b: { c: { d: [1, 2, 3] } } }, x: null };
        const sealed = await sealManifest(obj, vk);
        expect(await openManifest(sealed, vk)).toEqual(obj);
    });

    it('sealed output is a valid {c, n} JSON string', async () => {
        const vk = s.randombytes_buf(32);
        const sealed = await sealManifest({ test: true }, vk);
        const parsed = JSON.parse(sealed);
        expect(typeof parsed.c).toBe('string');
        expect(typeof parsed.n).toBe('string');
    });

    it('applying 4 KiB Padmé floor: two small objects produce same ciphertext JSON length', async () => {
        const vk = s.randombytes_buf(32);
        const s1 = await sealManifest({ a: 1 }, vk);
        const s2 = await sealManifest({ b: 2, c: [1, 2, 3, 4] }, vk);
        // Both small objects hit the 4096-byte floor — ciphertext JSON length must match
        expect(s1.length).toBe(s2.length);
    });

    it('ciphertext is at least 4096 bytes of plaintext (MACBYTES included)', async () => {
        const vk = s.randombytes_buf(32);
        const sealed = await sealManifest({ tiny: true }, vk);
        const { c } = JSON.parse(sealed);
        // base64-decoded cipher bytes = padded_plaintext + 16 (MACBYTES)
        // Minimum padded_plaintext is 4096 bytes
        const cipherBytes = fromB64(c).length;
        expect(cipherBytes).toBeGreaterThanOrEqual(4096 + 16);
    });

    it('throws when decrypting with a different key', async () => {
        const vk1 = s.randombytes_buf(32);
        const vk2 = s.randombytes_buf(32);
        const sealed = await sealManifest({ secret: 'data' }, vk1);
        await expect(openManifest(sealed, vk2)).rejects.toThrow();
    });

    it('throws when ciphertext is tampered', async () => {
        const vk = s.randombytes_buf(32);
        const sealed = await sealManifest({ x: 1 }, vk);
        const parsed = JSON.parse(sealed);
        // Flip the first byte of the base64-encoded ciphertext
        const cBytes = fromB64(parsed.c);
        cBytes[0] ^= 0xff;
        parsed.c = b64(cBytes);
        await expect(openManifest(JSON.stringify(parsed), vk)).rejects.toThrow();
    });
});

// ---- unwrapVaultKey ---------------------------------------------------------

describe('unwrapVaultKey (hybrid X25519+ML-KEM-768, §6.3)', () => {
    // The web wraps VK_vault to the recipient identity via hybridWrap; the
    // extension unwraps with its X25519 sk + ML-KEM dk. Context '' (prod default).
    async function identity() {
        const x = s.crypto_box_keypair();          // X25519 identity
        const ml = await mlkemKeypair();            // { ek: b64, seed: 64 bytes }
        return { x, ml };
    }

    it('recovers a vault key hybrid-wrapped by the web app', async () => {
        const { x, ml } = await identity();
        const vkBytes = new Uint8Array(32).fill(0x77);

        const env = await hybridWrap(vkBytes, b64(x.publicKey), ml.ek);
        const recovered = await unwrapVaultKey(JSON.stringify(env), x.privateKey, ml.seed);

        expect(s.to_hex(recovered)).toBe(s.to_hex(vkBytes));
        expect(recovered).toBeInstanceOf(Uint8Array);
    });

    it('round-trips any 32-byte key (envelope as object or JSON string)', async () => {
        const { x, ml } = await identity();
        for (const fill of [0x00, 0x01, 0xab, 0xff]) {
            const vkBytes = new Uint8Array(32).fill(fill);
            const env = await hybridWrap(vkBytes, b64(x.publicKey), ml.ek);
            const recovered = await unwrapVaultKey(env, x.privateKey, ml.seed);
            expect(s.to_hex(recovered)).toBe(s.to_hex(vkBytes));
        }
    });

    it('throws when the wrong identity tries to unwrap', async () => {
        const owner = await identity();
        const attacker = await identity();
        const vkBytes = s.randombytes_buf(32);
        const env = await hybridWrap(vkBytes, b64(owner.x.publicKey), owner.ml.ek);
        await expect(unwrapVaultKey(env, attacker.x.privateKey, attacker.ml.seed)).rejects.toThrow();
    });

    it('fails closed on an unknown suite', async () => {
        const { x, ml } = await identity();
        const env = await hybridWrap(s.randombytes_buf(32), b64(x.publicKey), ml.ek);
        await expect(unwrapVaultKey({ ...env, suite: 2 }, x.privateKey, ml.seed)).rejects.toThrow();
    });
});

// ---- unwrapIdentitySecret ---------------------------------------------------

describe('unwrapIdentitySecret', () => {
    // vault.js stores the identity secret as seal(kp.privateKey, this.vk):
    //   { c: b64(cipher), n: b64(nonce) }  — same JSON shape as openManifest's {c,n}
    it('recovers the identity secret key from a VK-secretbox-sealed JSON blob', async () => {
        const vk = s.randombytes_buf(32);
        const idSk = s.randombytes_buf(32); // simulated X25519 private key bytes

        // Reproduce what vault.js ensureIdentityKeys() does: seal(sk, vk)
        const nonce = s.randombytes_buf(s.crypto_secretbox_NONCEBYTES);
        const cipher = s.crypto_secretbox_easy(idSk, nonce, vk);
        const wrappedJson = JSON.stringify({ c: b64(cipher), n: b64(nonce) });

        const recovered = await unwrapIdentitySecret(wrappedJson, vk);

        expect(s.to_hex(recovered)).toBe(s.to_hex(idSk));
        expect(recovered).toBeInstanceOf(Uint8Array);
    });

    it('round-trips a real X25519 private key', async () => {
        const vk = s.randombytes_buf(32);
        const kp = s.crypto_box_keypair();

        const nonce = s.randombytes_buf(s.crypto_secretbox_NONCEBYTES);
        const cipher = s.crypto_secretbox_easy(kp.privateKey, nonce, vk);
        const wrappedJson = JSON.stringify({ c: b64(cipher), n: b64(nonce) });

        const recovered = await unwrapIdentitySecret(wrappedJson, vk);
        expect(s.to_hex(recovered)).toBe(s.to_hex(kp.privateKey));
    });

    it('throws when unwrapped with a different vault key', async () => {
        const vk = s.randombytes_buf(32);
        const wrongVk = s.randombytes_buf(32);
        const sk = s.randombytes_buf(32);

        const nonce = s.randombytes_buf(s.crypto_secretbox_NONCEBYTES);
        const cipher = s.crypto_secretbox_easy(sk, nonce, vk);
        const wrappedJson = JSON.stringify({ c: b64(cipher), n: b64(nonce) });

        await expect(unwrapIdentitySecret(wrappedJson, wrongVk)).rejects.toThrow();
    });

    it('recovered identity secrets open a hybrid envelope (full accept-flow chain)', async () => {
        // Simulates the accept-flow: inviter hybrid-wraps vkVault to the invitee's
        // published identity; invitee recovers BOTH secret keys from VK-sealed blobs
        // (unwrapIdentitySecret + unwrapMlkemSecret) and unwraps the vault key.
        const vk = s.randombytes_buf(32);
        const idKp = s.crypto_box_keypair();          // X25519 identity
        const ml = await mlkemKeypair();              // ML-KEM identity { ek, seed }

        // Store both identity secrets sealed under VK (as vault.js ensureIdentityKeys does)
        const seal = (bytes) => {
            const nonce = s.randombytes_buf(s.crypto_secretbox_NONCEBYTES);
            return JSON.stringify({ c: b64(s.crypto_secretbox_easy(bytes, nonce, vk)), n: b64(nonce) });
        };
        const wrappedSkJson = seal(idKp.privateKey);
        const wrappedMlkemJson = seal(ml.seed);

        // Inviter hybrid-wraps a vault key to the invitee's published identity
        const vkVault = s.randombytes_buf(32);
        const wrappedVaultKey = JSON.stringify(await hybridWrap(vkVault, b64(idKp.publicKey), ml.ek));

        // Invitee recovers both identity secrets, then unwraps the vault key
        const recoveredSk = await unwrapIdentitySecret(wrappedSkJson, vk);
        const recoveredMlkemDk = await unwrapMlkemSecret(wrappedMlkemJson, vk);
        const recoveredVkVault = await unwrapVaultKey(wrappedVaultKey, recoveredSk, recoveredMlkemDk);

        expect(s.to_hex(recoveredVkVault)).toBe(s.to_hex(vkVault));
    });
});
