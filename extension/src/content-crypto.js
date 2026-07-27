// Per-blob content crypto for the extension — a byte-faithful port of vault.js
// encryptContentWith / decryptFileWith so a shard blob sealed by the web app opens
// here and vice versa. Used ONLY for the sharded passwords store's record-shard
// blobs. Chunked crypto_secretstream (4 MiB chunks, u32le length frames) under a
// fresh per-blob key, which is wrapped under the vault key via secretbox. The blob
// is Padmé-padded for length hiding; decrypt stops at TAG_FINAL, ignoring padding.
import _sodium from 'libsodium-wrappers-sumo';

const CHUNK = 4 * 1024 * 1024; // must match vault.js CHUNK exactly

let sodium = null;
async function ready() {
    if (! sodium) { await _sodium.ready; sodium = _sodium; }
    return sodium;
}

const b64 = (b) => sodium.to_base64(b, sodium.base64_variants.ORIGINAL);
const unb64 = (s) => sodium.from_base64(s, sodium.base64_variants.ORIGINAL);

function u32le(n) {
    const b = new Uint8Array(4);
    new DataView(b.buffer).setUint32(0, n, true);
    return b;
}
function readU32le(bytes, off) {
    return new DataView(bytes.buffer, bytes.byteOffset + off, 4).getUint32(0, true);
}

function seal(data, key) {
    const nonce = sodium.randombytes_buf(sodium.crypto_secretbox_NONCEBYTES);
    return { cipher: b64(sodium.crypto_secretbox_easy(data, nonce, key)), nonce: b64(nonce) };
}
function open(cipherB64, nonceB64, key) {
    const out = sodium.crypto_secretbox_open_easy(unb64(cipherB64), unb64(nonceB64), key);
    if (out === false) throw new Error('decrypt failed');
    return out;
}

// Padmé (byte-exact mirror of shared/padme.js padmeSize — float arithmetic, since a
// 32-bit bitwise mask overflows for blobs >= 2 GiB and would disable padding).
function padmeSize(n) {
    if (n < 2) return n;
    const e = Math.floor(Math.log2(n));
    const s = Math.floor(Math.log2(e)) + 1;
    const step = Math.pow(2, e - s);
    return Math.ceil(n / step) * step;
}

/**
 * Encrypt bytes into a Padmé-padded shard blob (Uint8Array) + the wrapped per-blob key.
 * @returns {Promise<{blob: Uint8Array, encFileKey: string}>}
 */
export async function encryptContent(bytes, vk) {
    await ready();
    const fk = sodium.crypto_secretstream_xchacha20poly1305_keygen();
    const { state, header } = sodium.crypto_secretstream_xchacha20poly1305_init_push(fk);
    const parts = [header];
    let raw = header.length;
    const total = bytes.length;
    for (let off = 0; off < total || off === 0;) {
        const end = Math.min(off + CHUNK, total);
        const last = end >= total;
        const cipher = sodium.crypto_secretstream_xchacha20poly1305_push(
            state, bytes.subarray(off, end), null,
            last ? sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL : sodium.crypto_secretstream_xchacha20poly1305_TAG_MESSAGE,
        );
        parts.push(u32le(cipher.length), cipher);
        raw += 4 + cipher.length;
        off = end;
        if (last) break;
    }
    // Concatenate + Padmé-pad with trailing zeros (ignored by the TAG_FINAL-bounded reader).
    const padded = new Uint8Array(padmeSize(raw));
    let o = 0;
    for (const p of parts) { padded.set(p, o); o += p.length; }
    const encFileKey = seal(fk, vk);
    return { blob: padded, encFileKey: JSON.stringify({ c: encFileKey.cipher, n: encFileKey.nonce }) };
}

/**
 * Decrypt a shard blob (ArrayBuffer/Uint8Array) with its wrapped key under the VK.
 * @returns {Promise<Uint8Array>}
 */
export async function decryptContent(buffer, encFileKey, vk) {
    await ready();
    const wrapped = JSON.parse(encFileKey);
    const fk = open(wrapped.c, wrapped.n, vk);
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    const H = sodium.crypto_secretstream_xchacha20poly1305_HEADERBYTES;
    const state = sodium.crypto_secretstream_xchacha20poly1305_init_pull(bytes.subarray(0, H), fk);
    const chunks = [];
    let off = H;
    for (;;) {
        const len = readU32le(bytes, off);
        off += 4;
        const res = sodium.crypto_secretstream_xchacha20poly1305_pull(state, bytes.subarray(off, off + len));
        if (res === false) throw new Error('decrypt failed');
        chunks.push(res.message);
        off += len;
        if (res.tag === sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL) break;
    }
    let size = 0;
    for (const c of chunks) size += c.length;
    const out = new Uint8Array(size);
    let p = 0;
    for (const c of chunks) { out.set(c, p); p += c.length; }
    return out;
}
