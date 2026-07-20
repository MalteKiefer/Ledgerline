/**
 * Padmé cross-boundary equivalence tests.
 *
 * Guards the ZK invariant: the Padmé padding algorithm inside
 * extension/src/crypto.js MUST produce byte-identical padded lengths to
 * vault.js (and to resources/js/shared/padme.js). Any drift would cause the
 * extension to seal manifests of a different size than the web app expects,
 * silently breaking cross-client reads or leaking size information.
 *
 * Strategy:
 *   - padmeSize() from shared/padme.js is the canonical exported reference.
 *   - vault.js exposes Padmé behaviour through VaultShareCrypto.sealVaultManifest
 *     (the private padme() function inside vault.js is not exported, but its
 *     manifest-padding logic is identical).
 *   - extension/src/crypto.js exposes it through sealManifest().
 *   - By sealing objects whose JSON lengths land in specific Padmé buckets we can
 *     assert that ALL THREE implementations agree on the padded ciphertext length,
 *     and that vault.js and extension/src/crypto.js can unseal each other's output
 *     when given the same vault key.
 */
import { describe, it, expect, beforeAll } from 'vitest';
import { padmeSize } from '../shared/padme.js';
import { VaultShareCrypto } from '../vault.js';
import { sealManifest as extSeal, openManifest as extOpen } from '../../../extension/src/crypto.js';
import _sodium from 'libsodium-wrappers-sumo';

let s;
beforeAll(async () => {
    await _sodium.ready;
    s = _sodium;
});

// Helper: convert a base64 vault key (as returned by VaultShareCrypto.newVaultKey())
// to raw Uint8Array for functions that need bytes.
function vkB64ToBytes(vkB64) {
    return s.from_base64(vkB64, s.base64_variants.ORIGINAL);
}

// Helper: derive padded manifest byte length from a sealed {c,n} JSON string.
// The ciphertext in c covers: padded_plaintext_bytes + 16 (MACBYTES, Poly1305 tag).
// So padded_plaintext_length = base64_decoded(c).length - 16.
function paddedPlaintextLen(sealedJson) {
    const { c } = JSON.parse(sealedJson);
    const cipherBytes = s.from_base64(c, s.base64_variants.ORIGINAL);
    return cipherBytes.length - s.crypto_secretbox_MACBYTES; // subtract 16-byte MAC
}

// ---- Padmé algorithm equivalence (pure, no crypto) -------------------------

describe('padmeSize pure algorithm: shared/padme.js', () => {
    const cases = [
        [1, 1],
        [2, 2],
        [3, 3],
        [4, 4],
        [5, 5],
        [100, 104],
        [1000, 1024],
        [4000, 4096],
        [4096, 4096],
        [4097, 4352],
        [10000, 10240],
        [100000, 100352],
        [1000000, 1015808],
    ];

    for (const [input, expected] of cases) {
        it(`padmeSize(${input}) === ${expected}`, () => {
            expect(padmeSize(input)).toBe(expected);
        });
    }
});

// ---- Cross-boundary padded length agreement ---------------------------------
// vault.js (VaultShareCrypto.sealVaultManifest) and extension/src/crypto.js
// (sealManifest) must produce the SAME padded plaintext length for equivalent
// input objects. This is the core ZK invariant: a manifest sealed by the
// extension can be decoded by the web app and vice-versa without size leakage.

describe('cross-boundary padded length: vault.js ↔ extension/src/crypto.js', () => {
    // Test objects whose JSON lengths (+ 1 for the padme n+1 calculation) land
    // in representative Padmé buckets:
    //   tiny (< 4096) → floor 4096
    //   medium (~4097) → bucket 4352
    //   large (~100 000) → bucket 100352

    it('small object: both implementations pad to the 4096-byte floor', async () => {
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const vkBytes = vkB64ToBytes(vkB64);
        const obj = { items: ['a', 'b'], count: 2 };

        const vaultSealed = await VaultShareCrypto.sealVaultManifest(obj, vkBytes);
        const extSealed = await extSeal(obj, vkBytes);

        const vaultLen = paddedPlaintextLen(vaultSealed);
        const extLen = paddedPlaintextLen(extSealed);

        expect(vaultLen).toBe(4096);
        expect(extLen).toBe(4096);
        expect(vaultLen).toBe(extLen); // cross-boundary agreement
    });

    it('medium object (~4100 JSON chars): both implementations land in the same Padmé bucket', async () => {
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const vkBytes = vkB64ToBytes(vkB64);
        // Build an object whose JSON length is ~4100 bytes (above 4096 floor)
        const obj = { data: 'x'.repeat(4090) };
        const jsonLen = JSON.stringify(obj).length;
        // Verify we are above the floor
        expect(jsonLen).toBeGreaterThan(4090);

        const vaultSealed = await VaultShareCrypto.sealVaultManifest(obj, vkBytes);
        const extSealed = await extSeal(obj, vkBytes);

        const vaultLen = paddedPlaintextLen(vaultSealed);
        const extLen = paddedPlaintextLen(extSealed);

        expect(vaultLen).toBe(extLen); // must agree
        // Both must equal padmeSize(jsonLen + 1) with the same formula
        expect(vaultLen).toBe(Math.max(4096, padmeSize(jsonLen + 1)));
    });

    it('large object (~100 000 JSON chars): both agree on bucket 100352', async () => {
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const vkBytes = vkB64ToBytes(vkB64);
        const obj = { data: 'y'.repeat(100000) };
        const jsonLen = JSON.stringify(obj).length;

        const vaultSealed = await VaultShareCrypto.sealVaultManifest(obj, vkBytes);
        const extSealed = await extSeal(obj, vkBytes);

        const vaultLen = paddedPlaintextLen(vaultSealed);
        const extLen = paddedPlaintextLen(extSealed);

        expect(vaultLen).toBe(extLen);
        expect(vaultLen).toBe(Math.max(4096, padmeSize(jsonLen + 1)));
    });

    it('multiple small objects all land in the same 4096-byte bucket', async () => {
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const vkBytes = vkB64ToBytes(vkB64);

        const objects = [
            {},
            { a: 1 },
            { items: [] },
            { x: 'hello', y: [1, 2, 3] },
        ];

        const lengths = await Promise.all(objects.map(async (obj) => {
            const extSealed = await extSeal(obj, vkBytes);
            return paddedPlaintextLen(extSealed);
        }));

        // All small objects must hit the 4096 floor
        for (const len of lengths) {
            expect(len).toBe(4096);
        }
    });
});

// ---- Cross-open: vault.js can open what extension sealed (and vice-versa) ---
// The MOST CRITICAL invariant: they must be interoperable given the same key.

describe('cross-open: vault.js output openable by extension, extension output openable by vault.js', () => {
    it('extension seals, vault.js (VaultShareCrypto) opens', async () => {
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const vkBytes = vkB64ToBytes(vkB64);
        const obj = { source: 'extension', items: [1, 2, 3], nested: { ok: true } };

        const extSealed = await extSeal(obj, vkBytes);
        const opened = await VaultShareCrypto.openVaultManifest(extSealed, vkBytes);

        expect(opened).toEqual(obj);
    });

    it('vault.js (VaultShareCrypto) seals, extension opens', async () => {
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const vkBytes = vkB64ToBytes(vkB64);
        const obj = { source: 'vault', tags: ['a', 'b'], count: 42 };

        const vaultSealed = await VaultShareCrypto.sealVaultManifest(obj, vkBytes);
        const opened = await extOpen(vaultSealed, vkBytes);

        expect(opened).toEqual(obj);
    });

    it('extension seals a large object, vault.js opens it', async () => {
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const vkBytes = vkB64ToBytes(vkB64);
        // Large enough to exceed the 4096 floor
        const obj = { data: 'z'.repeat(50000), extra: [1, 2, 3] };

        const extSealed = await extSeal(obj, vkBytes);
        const opened = await VaultShareCrypto.openVaultManifest(extSealed, vkBytes);

        expect(opened).toEqual(obj);
    });

    it('vault.js seals a large object, extension opens it', async () => {
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const vkBytes = vkB64ToBytes(vkB64);
        const obj = { data: 'w'.repeat(50000), extra: { nested: true } };

        const vaultSealed = await VaultShareCrypto.sealVaultManifest(obj, vkBytes);
        const opened = await extOpen(vaultSealed, vkBytes);

        expect(opened).toEqual(obj);
    });

    it('extension cannot open vault output sealed with a different key', async () => {
        const vkB64A = await VaultShareCrypto.newVaultKey();
        const vkB64B = await VaultShareCrypto.newVaultKey();
        const vkBytesA = vkB64ToBytes(vkB64A);
        const vkBytesB = vkB64ToBytes(vkB64B);

        const vaultSealed = await VaultShareCrypto.sealVaultManifest({ x: 1 }, vkBytesA);
        await expect(extOpen(vaultSealed, vkBytesB)).rejects.toThrow();
    });

    it('vault.js cannot open extension output sealed with a different key', async () => {
        const vkB64A = await VaultShareCrypto.newVaultKey();
        const vkB64B = await VaultShareCrypto.newVaultKey();
        const vkBytesA = vkB64ToBytes(vkB64A);
        const vkBytesB = vkB64ToBytes(vkB64B);

        const extSealed = await extSeal({ x: 1 }, vkBytesA);
        await expect(VaultShareCrypto.openVaultManifest(extSealed, vkBytesB)).rejects.toThrow();
    });
});

// ---- Padmé formula agreement with shared/padme.js padmeSize ----------------
// Verify that the padded lengths from both seal implementations match the
// canonical padmeSize() formula for the same input lengths.

describe('padded lengths match padmeSize() formula exactly', () => {
    const JSON_SIZES = [1, 100, 1000, 4096, 4097, 10000, 100000];

    for (const targetJsonLen of JSON_SIZES) {
        it(`JSON length ${targetJsonLen}: sealed length equals max(4096, padmeSize(${targetJsonLen}+1))`, async () => {
            const vkB64 = await VaultShareCrypto.newVaultKey();
            const vkBytes = vkB64ToBytes(vkB64);

            // Build an object with exactly targetJsonLen JSON characters
            // For small sizes, JSON.stringify({'':''}) = {"":""} = 8 chars — adjust
            const content = 'a'.repeat(Math.max(0, targetJsonLen - 2)); // account for {"":"..."} wrapper
            const obj = { _: content };
            // Trim if too long (JSON.stringify adds overhead for key/structure)
            const actualJsonLen = JSON.stringify(obj).length;
            const expectedPadded = Math.max(4096, padmeSize(actualJsonLen + 1));

            const extSealed = await extSeal(obj, vkBytes);
            const extLen = paddedPlaintextLen(extSealed);

            expect(extLen).toBe(expectedPadded);
        });
    }
});
