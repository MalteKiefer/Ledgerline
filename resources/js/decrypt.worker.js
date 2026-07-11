// Off-main-thread content decryption for the gallery grid. Decrypting every
// thumbnail with libsodium secretstream on the main thread janks the grid on a
// large first load. This worker runs ONLY the secretstream pull: it receives an
// already-unwrapped per-blob key (a per-file symmetric key — NEVER the vault
// key) plus the framed ciphertext, and returns the plaintext bytes. No vault
// key, no DOM, no network.

let sodium = null;

async function ready() {
    if (! sodium) {
        const mod = await import('libsodium-wrappers-sumo');
        const s = mod.default ?? mod;
        await s.ready;
        sodium = s;
    }

    return sodium;
}

function readU32le(bytes, off) {
    return (bytes[off] | (bytes[off + 1] << 8) | (bytes[off + 2] << 16) | (bytes[off + 3] << 24)) >>> 0;
}

function concat(chunks) {
    let total = 0;
    for (const c of chunks) total += c.length;
    const out = new Uint8Array(total);
    let off = 0;
    for (const c of chunks) { out.set(c, off); off += c.length; }

    return out;
}

// Mirror of Vault.decryptFile's stream loop, given a raw per-file key. Trailing
// bytes after TAG_FINAL (size padding) are ignored, as on the main thread.
function decryptStream(bytes, fk) {
    const H = sodium.crypto_secretstream_xchacha20poly1305_HEADERBYTES;
    const state = sodium.crypto_secretstream_xchacha20poly1305_init_pull(bytes.subarray(0, H), fk);

    const chunks = [];
    let off = H;
    for (;;) {
        const len = readU32le(bytes, off);
        off += 4;
        const res = sodium.crypto_secretstream_xchacha20poly1305_pull(state, bytes.subarray(off, off + len));
        if (res === false) {
            throw new Error('decrypt failed');
        }
        off += len;
        chunks.push(res.message);
        if (res.tag === sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL) {
            break;
        }
    }

    return concat(chunks);
}

self.onmessage = async (e) => {
    const { id, buffer, fk } = e.data;
    try {
        await ready();
        const out = decryptStream(new Uint8Array(buffer), fk);
        self.postMessage({ id, ok: true, buffer: out.buffer }, [out.buffer]);
    } catch (err) {
        self.postMessage({ id, ok: false, error: String((err && err.message) || err) });
    }
};
