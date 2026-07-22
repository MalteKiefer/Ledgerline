// Post-quantum hybrid KEM — extension subset (Store v3, spec §6.3).
//
// The extension only ever UNWRAPS (reads shared-vault keys wrapped to its own
// identity by the web app); it never wraps. Faithful mirror of the web's
// resources/js/shared/pq-kem.js hybridUnwrap: X25519 + ML-KEM-768 combined via
// HKDF-SHA256 over `ss_ec ‖ ss_pq`, info = "ledgerline/kem/v1"‖context. Interop
// is by standard (FIPS 203 + HKDF-SHA256 + secretbox), validated by the §17 KAT.

import { ml_kem768 } from '@noble/post-quantum/ml-kem.js';
import { hkdf } from '@noble/hashes/hkdf.js';
import { sha256 } from '@noble/hashes/sha2.js';
import _sodium from 'libsodium-wrappers-sumo';

const KEM_INFO_PREFIX = 'ledgerline/kem/v1';
const WRAP_KEY_LEN = 32;

let sodium = null;
async function ready() {
    if (! sodium) { await _sodium.ready; sodium = _sodium; }
    return sodium;
}

const unb64 = (s) => sodium.from_base64(s, sodium.base64_variants.ORIGINAL);

/** HKDF-SHA256(ss_ec ‖ ss_pq, info = "ledgerline/kem/v1"‖context) → 32-byte wrap key. */
function deriveWrapKey(ssEc, ssPq, context) {
    const ikm = new Uint8Array(ssEc.length + ssPq.length);
    ikm.set(ssEc, 0);
    ikm.set(ssPq, ssEc.length);
    const info = new TextEncoder().encode(KEM_INFO_PREFIX + (context ?? ''));

    // Empty salt (standard HKDF default) — the ikm already has full entropy.
    return hkdf(sha256, ikm, new Uint8Array(0), info, WRAP_KEY_LEN);
}

/**
 * Hybrid-unwrap an envelope with the recipient's secret identity keys.
 *
 * @param {object} envelope         { suite, epk, kem_ct, c, n } (all base64)
 * @param {string} ownX25519Sk      recipient x25519 secret key, base64
 * @param {Uint8Array} ownMlkemSeed recipient ML-KEM-768 64-byte seed, raw bytes
 * @param {string} [context]        must match the wrap context
 * @returns {Promise<Uint8Array>} the recovered payload
 * @throws on unknown suite or authentication failure (fail-closed)
 */
export async function hybridUnwrap(envelope, ownX25519Sk, ownMlkemSeed, context = '') {
    await ready();
    if (! envelope || envelope.suite !== 1) {
        throw new Error('hybridUnwrap: unknown or missing suite');
    }

    // Regenerate the ML-KEM secret key deterministically from the stored seed
    // (FIPS-203 keygen(seed) — the seed is the portable canonical secret).
    const { secretKey: dk } = ml_kem768.keygen(ownMlkemSeed);
    const ssPq = ml_kem768.decapsulate(unb64(envelope.kem_ct), dk);
    const ssEc = sodium.crypto_scalarmult(unb64(ownX25519Sk), unb64(envelope.epk));

    const wrapKey = deriveWrapKey(ssEc, ssPq, context);
    const out = sodium.crypto_secretbox_open_easy(unb64(envelope.c), unb64(envelope.n), wrapKey);
    if (! out) {
        throw new Error('hybridUnwrap: authentication failed (wrong keys or corrupt envelope)');
    }

    return out;
}
