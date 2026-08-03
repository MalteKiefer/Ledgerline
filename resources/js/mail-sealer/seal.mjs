// Server-side mail sealer (Node, ENCRYPT-ONLY).
//
// Ledgerline is zero-knowledge: for the mail-archive feature the SERVER fetches a
// user's email and seals it TO that user's public identity key, so the server can
// WRITE the ciphertext but never READ the plaintext. Only the unlocked browser
// client (which holds the matching secret keys) can decrypt.
//
// This module produces output that the EXISTING web client decrypts unchanged:
//   - the per-message symmetric key is hybrid-wrapped with the SAME KEM as
//     cross-user sharing — `hybridWrap` from shared/pq-kem.js (X25519 + ML-KEM-768,
//     suite-envelope {suite:1,epk,kem_ct,c,n}); the client opens it with
//     `hybridUnwrap` / VaultShareCrypto.unwrapVaultKey.
//   - the message body is sealed with libsodium secretstream
//     (crypto_secretstream_xchacha20poly1305) in the SAME framing vault.js uses:
//     `header ‖ [u32le(cipherLen) ‖ cipher]*`, 4 MiB plaintext chunks, TAG_FINAL on
//     the last chunk. The client opens it with vault.js `decryptFileWith` /
//     ShareCrypto.decrypt.
//
// The recipient's SECRET keys are NEVER used here — this only ever takes the
// public X25519 key + ML-KEM encapsulation key. Byte-exact interop with the
// client is asserted by __tests__/seal-interop.test.js.

import sodium from 'libsodium-wrappers-sumo';
import { hybridWrap } from '../shared/pq-kem.js';

// Secretstream plaintext chunk size — MUST match vault.js `CHUNK` (4 MiB) so the
// client's fixed-size streaming decrypt frames line up.
const CHUNK = 4 * 1024 * 1024;

// Little-endian uint32 length prefix framing each ciphertext chunk — identical to
// vault.js `u32le`.
function u32le(n) {
    return new Uint8Array([n & 0xff, (n >>> 8) & 0xff, (n >>> 16) & 0xff, (n >>> 24) & 0xff]);
}

function concat(parts) {
    const size = parts.reduce((n, p) => n + p.length, 0);
    const out = new Uint8Array(size);
    let off = 0;
    for (const p of parts) {
        out.set(p, off);
        off += p.length;
    }
    return out;
}

/**
 * Seal raw message bytes to a recipient's public identity.
 *
 * Generates a fresh per-message secretstream key, encrypts the bytes with it in
 * fixed 4 MiB chunks (constant memory relative to chunk size), and hybrid-wraps
 * that key to the recipient's public keys. Nothing here needs a secret key.
 *
 * @param {Uint8Array} rawBytes        the plaintext message (e.g. a raw .eml)
 * @param {string} x25519PubB64        recipient X25519 public key, base64 (ORIGINAL)
 * @param {string} mlkemEkB64          recipient ML-KEM-768 encapsulation key, base64
 * @returns {Promise<{sealedKey: string, blob: Uint8Array}>}
 *          sealedKey = JSON.stringify of the hybridWrap suite-envelope over the
 *          per-message key; blob = secretstream header + framed chunks.
 */
export async function sealMessage(rawBytes, x25519PubB64, mlkemEkB64) {
    await sodium.ready;

    const bytes = rawBytes instanceof Uint8Array ? rawBytes : new Uint8Array(rawBytes);

    // Fresh per-message symmetric key (32 bytes) — this is the secret we wrap.
    const key = sodium.crypto_secretstream_xchacha20poly1305_keygen();
    const { state, header } = sodium.crypto_secretstream_xchacha20poly1305_init_push(key);

    // header ‖ [u32le(len) ‖ cipher]* — mirror vault.js encryptContentWith exactly.
    // A zero-length message still emits exactly one final (empty) chunk.
    const parts = [header];
    const total = bytes.length;
    for (let off = 0; off < total || off === 0;) {
        const end = Math.min(off + CHUNK, total);
        const last = end >= total;
        const cipher = sodium.crypto_secretstream_xchacha20poly1305_push(
            state,
            bytes.subarray(off, end),
            null,
            last
                ? sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL
                : sodium.crypto_secretstream_xchacha20poly1305_TAG_MESSAGE,
        );
        parts.push(u32le(cipher.length), cipher);
        off = end;
        if (last) break;
    }

    // Hybrid-wrap the per-message key to the recipient. Empty context '' matches
    // the production wrap/unwrap context used across the client (per-vault HKDF
    // context is wired but unused everywhere — see CLAUDE.md open points).
    const env = await hybridWrap(key, x25519PubB64, mlkemEkB64, '');

    return { sealedKey: JSON.stringify(env), blob: concat(parts) };
}

// ---- CLI entry (for the PHP wrapper to shell) ----------------------------------
//
// Usage:  node seal.mjs <x25519PubB64> <mlkemEkB64>   < raw-message-bytes
//
// Reads the raw message bytes from stdin, seals them, and writes the result to
// stdout in this simple framing (documented for the PHP reader):
//
//   <sealedKey JSON>\n<blob bytes ...>
//
// i.e. the first line (terminated by a single '\n') is the sealedKey JSON
// envelope (which never itself contains a newline — it is compact JSON of base64
// fields), and EVERYTHING after that first newline is the raw binary blob. The
// reader splits on the first 0x0A byte: bytes before it (UTF-8) = sealedKey,
// bytes after it = blob. Exit code 0 on success, 1 on error.

async function main() {
    const x25519PubB64 = process.argv[2];
    const mlkemEkB64 = process.argv[3];
    if (! x25519PubB64 || ! mlkemEkB64) {
        process.stderr.write('usage: node seal.mjs <x25519PubB64> <mlkemEkB64> < raw-bytes\n');
        process.exit(1);
    }

    const stdin = [];
    for await (const chunk of process.stdin) {
        stdin.push(chunk);
    }
    const rawBytes = new Uint8Array(Buffer.concat(stdin));

    const { sealedKey, blob } = await sealMessage(rawBytes, x25519PubB64, mlkemEkB64);

    process.stdout.write(sealedKey + '\n');
    process.stdout.write(Buffer.from(blob));
}

if (import.meta.url === `file://${process.argv[1]}`) {
    main().catch((err) => {
        process.stderr.write(String(err && err.stack ? err.stack : err) + '\n');
        process.exit(1);
    });
}
