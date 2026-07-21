/**
 * Canonical-JSON + suite-tag cross-boundary equivalence tests.
 *
 * Guards the Store v3 (§5.2/§6.1) ZK invariant that the browser extension
 * (extension/src/crypto.js sealManifest) and the web app (vault.js seal path)
 * produce BYTE-IDENTICAL sealed plaintext for the `passwords`/`bookmarks` module
 * stores. Both must:
 *   - serialise over canonical JSON (keys sorted by Unicode code point, compact
 *     separators, strings AS-IS with no NFC normalisation),
 *   - carry a `suite: 1` envelope tag,
 *   - fail closed on an unknown suite.
 *
 * We cannot compare the sealed {c,n} strings directly (the nonce is random), so
 * we assert the CANONICAL PLAINTEXT bytes are identical (the exact bytes fed to
 * secretbox, before Padmé padding) across the extension and web canonical-json
 * implementations, plus verify the suite tag and fail-closed behaviour.
 */
import { describe, it, expect, beforeAll } from 'vitest';
import { canonicalJSON as webCanonical } from '../shared/canonical-json.js';
import { canonicalJSON as extCanonical } from '../../../extension/src/canonical-json.js';
import { VaultShareCrypto } from '../vault.js';
import { sealManifest as extSeal, openManifest as extOpen } from '../../../extension/src/crypto.js';
import _sodium from 'libsodium-wrappers-sumo';

let s;
beforeAll(async () => {
    await _sodium.ready;
    s = _sodium;
});

function vkB64ToBytes(vkB64) {
    return s.from_base64(vkB64, s.base64_variants.ORIGINAL);
}

const enc = new TextEncoder();

// Representative objects covering the two required cases plus a nested store.
const CASES = {
    // (a) out-of-order keys — canonicalisation must sort them by code point.
    outOfOrder: { zebra: 1, apple: 2, mango: 3, Zulu: 4, _leading: 5, 'á-key': 6 },
    // (b) non-ASCII / decomposed string — must be emitted AS-IS (no NFC).
    //     'é' as decomposed 'e' + U+0301 (combining acute accent).
    decomposed: { name: 'café', city: 'München', emoji: '🔐' },
    // A password-store-shaped nested object with mixed types.
    store: {
        v: 3,
        secrets: [
            { id: 'b', name: 'GitHub', fields: { username: 'a', url: 'https://x' } },
            { id: 'a', name: 'Ärzte', fields: { note: 'näher' } },
        ],
        secretFolders: [{ id: 'f1', name: 'Privat' }],
    },
};

describe('extension canonical-json ↔ web canonical-json produce identical bytes', () => {
    for (const [label, obj] of Object.entries(CASES)) {
        it(`${label}: canonicalJSON strings are byte-identical`, () => {
            const webStr = webCanonical(obj);
            const extStr = extCanonical(obj);
            expect(extStr).toBe(webStr);

            // And their UTF-8 byte encodings — the exact secretbox input — match.
            expect(Array.from(enc.encode(extStr))).toEqual(Array.from(enc.encode(webStr)));
        });
    }

    it('out-of-order keys are emitted in code-point-sorted order', () => {
        // Uppercase 'Z'(0x5A) and 'Z'ulu sort before lowercase; '_'(0x5F) sits
        // between uppercase and lowercase; the accented key sorts last.
        expect(webCanonical(CASES.outOfOrder))
            .toBe('{"Zulu":4,"_leading":5,"apple":2,"mango":3,"zebra":1,"á-key":6}');
        expect(extCanonical(CASES.outOfOrder)).toBe(webCanonical(CASES.outOfOrder));
    });

    it('decomposed string is preserved verbatim (NO NFC normalisation)', () => {
        // If either side normalised to NFC, 'café' would collapse to 'café'
        // (precomposed U+00E9) and the byte lengths would differ.
        const str = extCanonical({ name: 'café' });
        expect(str).toContain('café'); // combining mark still present
        expect(str).not.toContain('é'); // NOT collapsed to precomposed é
        expect(str).toBe(webCanonical({ name: 'café' }));
    });
});

describe('extension sealManifest carries suite:1 and canonical plaintext', () => {
    it('sealed envelope tags suite=1', async () => {
        const vkBytes = vkB64ToBytes(await VaultShareCrypto.newVaultKey());
        const sealed = await extSeal(CASES.store, vkBytes);
        const env = JSON.parse(sealed);
        expect(env.suite).toBe(1);
        expect(typeof env.c).toBe('string');
        expect(typeof env.n).toBe('string');
    });

    it('sealed plaintext (before padding) equals the canonical bytes both sides compute', async () => {
        const vkBytes = vkB64ToBytes(await VaultShareCrypto.newVaultKey());
        const sealed = await extSeal(CASES.decomposed, vkBytes);
        const { c, n } = JSON.parse(sealed);

        // Recover the padded plaintext, then strip the trailing-space padding.
        const cipher = s.from_base64(c, s.base64_variants.ORIGINAL);
        const nonce = s.from_base64(n, s.base64_variants.ORIGINAL);
        const plain = s.crypto_secretbox_open_easy(cipher, nonce, vkBytes);
        const padded = s.to_string(plain);
        const unpadded = padded.replace(/ +$/, '');

        // The unpadded plaintext is exactly the canonical JSON both sides produce.
        expect(unpadded).toBe(webCanonical(CASES.decomposed));
        expect(unpadded).toBe(extCanonical(CASES.decomposed));
    });

    it('vault.js seal path yields the same canonical plaintext as the extension', async () => {
        const vkBytes = vkB64ToBytes(await VaultShareCrypto.newVaultKey());
        const vaultSealed = await VaultShareCrypto.sealVaultManifest(CASES.store, vkBytes);
        const { suite, c, n } = JSON.parse(vaultSealed);
        expect(suite).toBe(1);

        const cipher = s.from_base64(c, s.base64_variants.ORIGINAL);
        const nonce = s.from_base64(n, s.base64_variants.ORIGINAL);
        const unpadded = s.to_string(
            s.crypto_secretbox_open_easy(cipher, nonce, vkBytes),
        ).replace(/ +$/, '');

        expect(unpadded).toBe(extCanonical(CASES.store));
        expect(unpadded).toBe(webCanonical(CASES.store));
    });
});

describe('openManifest fail-closed on unknown suite', () => {
    it('extension openManifest rejects an unknown suite before decrypt', async () => {
        const vkBytes = vkB64ToBytes(await VaultShareCrypto.newVaultKey());
        // Seal legitimately, then rewrite the suite tag to an unknown value.
        const sealed = JSON.parse(await extSeal({ x: 1 }, vkBytes));
        const tampered = JSON.stringify({ ...sealed, suite: 2 });
        await expect(extOpen(tampered, vkBytes)).rejects.toThrow(/unknown sealed-manifest suite/);
    });

    it('extension openManifest still accepts a MISSING suite (forward-safety)', async () => {
        const vkBytes = vkB64ToBytes(await VaultShareCrypto.newVaultKey());
        const sealed = JSON.parse(await extSeal({ x: 1, y: 'z' }, vkBytes));
        delete sealed.suite;
        const legacy = JSON.stringify(sealed);
        await expect(extOpen(legacy, vkBytes)).resolves.toEqual({ x: 1, y: 'z' });
    });
});
