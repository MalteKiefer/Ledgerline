// Post-quantum hybrid KEM for asymmetric key-wraps (Store v3, spec §6.3).
//
// Used ONLY for cross-user sharing + identity — wrapping a shared VK_vault /
// VK_folder to a recipient. Personal gallery/files stay symmetric (VK secretbox).
//
// Hybrid = X25519 (classical) + ML-KEM-768 (FIPS 203, post-quantum), combined via
// HKDF-SHA256 over `ss_ec ‖ ss_pq`. Confidentiality holds unless BOTH primitives
// fall (PQXDH-style). Interop is by standard, not byte-identical code: ML-KEM-768
// is FIPS 203, X25519 + HKDF-SHA256 + secretbox are all standard — validated by the
// §17 NIST KAT + encaps/decaps interop fixtures.
//
// Libraries: @noble/post-quantum (ML-KEM-768), @noble/hashes (HKDF-SHA256),
// libsodium-wrappers-sumo (X25519 scalarmult + secretbox). Exact-pinned.

import { ml_kem768 } from '@noble/post-quantum/ml-kem.js';
import { hkdf } from '@noble/hashes/hkdf.js';
import { sha256 } from '@noble/hashes/sha2.js';
import sodium from 'libsodium-wrappers-sumo';

const KEM_INFO_PREFIX = 'ledgerline/kem/v1';
const WRAP_KEY_LEN = 32;

let _ready = null;
function ready() {
    _ready ||= sodium.ready;
    return _ready;
}

function b64(bytes) {
    return sodium.to_base64(bytes, sodium.base64_variants.ORIGINAL);
}
function unb64(s) {
    return sodium.from_base64(s, sodium.base64_variants.ORIGINAL);
}

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
 * Generate an ML-KEM-768 identity keypair.
 * @returns {Promise<{ek: string, dk: Uint8Array}>} ek base64 (public, non-secret);
 *          dk raw bytes (secret — caller seals it under VK).
 */
export async function mlkemKeypair() {
    await ready();
    const kp = ml_kem768.keygen();

    return { ek: b64(kp.publicKey), dk: kp.secretKey };
}

/**
 * Hybrid-wrap a payload (e.g. a raw VK) to a recipient's public identity.
 *
 * @param {Uint8Array} payload         plaintext to protect (raw key bytes)
 * @param {string} recipientX25519Pub  recipient x25519 public key, base64
 * @param {string} recipientMlkemEk    recipient ML-KEM-768 encapsulation key, base64
 * @param {string} [context]           domain-separation context appended to HKDF info
 * @returns {Promise<object>} envelope { suite:1, epk, kem_ct, c, n } (all base64)
 */
export async function hybridWrap(payload, recipientX25519Pub, recipientMlkemEk, context = '') {
    await ready();

    // PQ leg: ML-KEM-768 encapsulation to the recipient's ek.
    const { cipherText: kemCt, sharedSecret: ssPq } = ml_kem768.encapsulate(unb64(recipientMlkemEk));

    // Classical leg: ephemeral X25519 DH against the recipient's x25519 pub.
    const eph = sodium.crypto_box_keypair();
    const ssEc = sodium.crypto_scalarmult(eph.privateKey, unb64(recipientX25519Pub));

    const wrapKey = deriveWrapKey(ssEc, ssPq, context);
    const nonce = sodium.randombytes_buf(sodium.crypto_secretbox_NONCEBYTES);
    const c = sodium.crypto_secretbox_easy(payload, nonce, wrapKey);

    return {
        suite: 1,
        epk: b64(eph.publicKey),
        kem_ct: b64(kemCt),
        c: b64(c),
        n: b64(nonce),
    };
}

/**
 * Hybrid-unwrap an envelope with the recipient's secret identity keys.
 *
 * @param {object} envelope            { suite, epk, kem_ct, c, n }
 * @param {string} ownX25519Sk         recipient x25519 secret key, base64
 * @param {Uint8Array} ownMlkemDk      recipient ML-KEM-768 secret key, raw bytes
 * @param {string} [context]           must match the wrap context
 * @returns {Promise<Uint8Array>} the recovered payload
 * @throws on unknown suite or authentication failure (fail-closed)
 */
export async function hybridUnwrap(envelope, ownX25519Sk, ownMlkemDk, context = '') {
    await ready();
    if (! envelope || envelope.suite !== 1) {
        throw new Error('hybridUnwrap: unknown or missing suite');
    }

    const ssPq = ml_kem768.decapsulate(unb64(envelope.kem_ct), ownMlkemDk);
    const ssEc = sodium.crypto_scalarmult(unb64(ownX25519Sk), unb64(envelope.epk));

    const wrapKey = deriveWrapKey(ssEc, ssPq, context);
    const out = sodium.crypto_secretbox_open_easy(unb64(envelope.c), unb64(envelope.n), wrapKey);
    if (! out) {
        throw new Error('hybridUnwrap: authentication failed (wrong keys or corrupt envelope)');
    }

    return out;
}
