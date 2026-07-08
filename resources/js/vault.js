import _sodium from 'libsodium-wrappers-sumo';

/**
 * Zero-knowledge encryption vault (client-side only).
 *
 * Key hierarchy, all handled in the browser:
 *   passphrase --Argon2id--> KEK --unwrap--> Vault Key (VK)
 *   VK --wraps--> per-file keys, file metadata, folder names
 *
 * The server only ever receives ciphertext and the public KDF parameters. The
 * passphrase, recovery code and vault key never leave this module.
 */

let sodium = null;
const CACHE_KEY = 'vault.vk';
const CACHE_EXPIRES = 'vault.vk.expires';
const CACHE_OWNER = 'vault.vk.owner';

// Idle timeout (minutes) is configurable in Security settings; default 10.
function idleMs() {
    const meta = document.querySelector('meta[name="vault-idle-minutes"]')?.getAttribute('content');
    const minutes = Number(meta) > 0 ? Number(meta) : 10;
    return minutes * 60 * 1000;
}

// Per-login token the cached vault key is bound to. Empty when signed out. A
// cached key only counts if its stored owner matches the current login, so the
// key cannot survive a logout + new login (nor be reused by a different login).
function currentOwner() {
    return document.querySelector('meta[name="vault-owner"]')?.getAttribute('content') || '';
}

const b64 = (bytes) => sodium.to_base64(bytes, sodium.base64_variants.ORIGINAL);
const unb64 = (str) => sodium.from_base64(str, sodium.base64_variants.ORIGINAL);

// Secretstream message size for file content.
const CHUNK = 4 * 1024 * 1024;

// Little-endian uint32 length prefix framing each ciphertext chunk.
function u32le(n) {
    return new Uint8Array([n & 0xff, (n >>> 8) & 0xff, (n >>> 16) & 0xff, (n >>> 24) & 0xff]);
}

function readU32le(bytes, off) {
    return bytes[off] | (bytes[off + 1] << 8) | (bytes[off + 2] << 16) | (bytes[off + 3] << 24);
}

// Per-page in-memory memo of decrypted metadata, keyed by the ciphertext blob.
// It only lives in this JS context (never persisted), so the same folder or file
// name shown in several places — breadcrumb, row, tree — is decrypted once, and
// no extra plaintext is written to storage. Cleared when the vault locks.
const metaMemo = new Map();

function concat(chunks) {
    const size = chunks.reduce((n, c) => n + c.length, 0);
    const out = new Uint8Array(size);
    let off = 0;
    for (const c of chunks) {
        out.set(c, off);
        off += c.length;
    }
    return out;
}

async function ready() {
    if (! sodium) {
        await _sodium.ready;
        sodium = _sodium;
    }
    return sodium;
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function api(method, body = null) {
    const res = await fetch('/vault', {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } : {}),
        },
        body: body ? JSON.stringify(body) : null,
    });
    if (! res.ok && res.status !== 409 && res.status !== 404) {
        throw new Error('vault request failed');
    }
    return res.json();
}

/** Argon2id: passphrase + salt -> 32-byte key-encryption key. */
function deriveKek(passphrase, salt, ops, mem) {
    return sodium.crypto_pwhash(
        sodium.crypto_secretbox_KEYBYTES,
        passphrase,
        salt,
        ops,
        mem,
        sodium.crypto_pwhash_ALG_ARGON2ID13,
    );
}

function seal(data, key) {
    const nonce = sodium.randombytes_buf(sodium.crypto_secretbox_NONCEBYTES);
    return { cipher: b64(sodium.crypto_secretbox_easy(data, nonce, key)), nonce: b64(nonce) };
}

function open(cipherB64, nonceB64, key) {
    const out = sodium.crypto_secretbox_open_easy(unb64(cipherB64), unb64(nonceB64), key);
    if (out === false) {
        throw new Error('decrypt failed');
    }
    return out;
}

export const Vault = {
    vk: null,

    async boot() {
        await ready();

        const owner = currentOwner();
        const expires = Number(sessionStorage.getItem(CACHE_EXPIRES) || 0);
        const cached = sessionStorage.getItem(CACHE_KEY);
        const cachedOwner = sessionStorage.getItem(CACHE_OWNER) || '';

        // Only restore the key if it belongs to the current login and has not
        // expired. A signed-out page (empty owner) or a different/new login
        // (owner mismatch) drops the key — it can never outlive its login.
        if (cached && owner !== '' && cachedOwner === owner && expires > Date.now()) {
            this.vk = unb64(cached);
            this.touch();
        } else if (cached) {
            this.lock();
        }
    },

    unlocked() {
        return this.vk !== null;
    },

    cache() {
        sessionStorage.setItem(CACHE_KEY, b64(this.vk));
        sessionStorage.setItem(CACHE_OWNER, currentOwner());
        this.touch();
    },

    touch() {
        if (this.vk) {
            sessionStorage.setItem(CACHE_EXPIRES, String(Date.now() + idleMs()));
        }
    },

    // When the cached key is set to expire (ms epoch); 0 if none. Lets an in-page
    // idle watchdog auto-lock once this passes.
    expiresAt() {
        return Number(sessionStorage.getItem(CACHE_EXPIRES) || 0);
    },

    lock() {
        this.vk = null;
        metaMemo.clear();
        sessionStorage.removeItem(CACHE_KEY);
        sessionStorage.removeItem(CACHE_EXPIRES);
        sessionStorage.removeItem(CACHE_OWNER);
    },

    async status() {
        await ready();
        return api('GET');
    },

    /** First-time setup: create the vault, return the recovery code to show once. */
    async setup(passphrase) {
        await ready();
        const salt = sodium.randombytes_buf(sodium.crypto_pwhash_SALTBYTES);
        const ops = sodium.crypto_pwhash_OPSLIMIT_SENSITIVE;
        const mem = sodium.crypto_pwhash_MEMLIMIT_MODERATE;
        const kek = deriveKek(passphrase, salt, ops, mem);

        const vk = sodium.randombytes_buf(sodium.crypto_secretbox_KEYBYTES);
        const wrapped = seal(vk, kek);

        // A high-entropy recovery code (its own wrap of the same vault key).
        const recoveryBytes = sodium.randombytes_buf(32);
        const recoveryKey = sodium.crypto_generichash(sodium.crypto_secretbox_KEYBYTES, recoveryBytes);
        const wrappedRecovery = seal(vk, recoveryKey);

        await api('POST', {
            salt: b64(salt),
            kdf_ops: ops,
            kdf_mem: mem,
            wrapped_vault_key: wrapped.cipher,
            wrap_nonce: wrapped.nonce,
            wrapped_vault_key_recovery: wrappedRecovery.cipher,
            recovery_nonce: wrappedRecovery.nonce,
        });

        this.vk = vk;
        this.cache();

        // Grouped hex, easy to write down.
        return sodium.to_hex(recoveryBytes).replace(/(.{4})/g, '$1 ').trim();
    },

    /** Unlock with the passphrase. Throws if wrong. */
    async unlock(passphrase) {
        const v = await this.status();
        if (! v.configured) {
            throw new Error('not configured');
        }
        const kek = deriveKek(passphrase, unb64(v.salt), v.kdf_ops, v.kdf_mem);
        this.vk = open(v.wrapped_vault_key, v.wrap_nonce, kek); // throws on wrong passphrase
        this.cache();
    },

    /** Restore access with the recovery code (spaces ignored). */
    async recover(recoveryCode) {
        const v = await this.status();
        if (! v.configured || ! v.has_recovery) {
            throw new Error('no recovery');
        }
        const recoveryBytes = sodium.from_hex(recoveryCode.replace(/\s+/g, ''));
        const recoveryKey = sodium.crypto_generichash(sodium.crypto_secretbox_KEYBYTES, recoveryBytes);
        this.vk = open(v.wrapped_vault_key_recovery, v.recovery_nonce, recoveryKey); // throws on wrong code
        this.cache();
    },

    /**
     * Change the passphrase: verify the current one, then re-wrap the same vault
     * key under a key derived from the new passphrase (fresh salt). Files are
     * untouched — the vault key does not change. The recovery wrap is preserved.
     */
    async changePassphrase(currentPass, newPass) {
        await this.unlock(currentPass); // verifies the current passphrase, loads VK

        return this.setPassphrase(newPass);
    },

    /**
     * Re-wrap the (already-unlocked, in-memory) vault key under a NEW passphrase
     * and mint a fresh recovery code — no current passphrase needed. Used after a
     * recovery-code unlock to set a new passphrase, and by changePassphrase.
     * Returns the new recovery code to show once. Files are untouched (VK is the
     * same), so everything stays decryptable.
     */
    async setPassphrase(newPass) {
        if (! this.vk) {
            throw new Error('locked');
        }
        const salt = sodium.randombytes_buf(sodium.crypto_pwhash_SALTBYTES);
        const ops = sodium.crypto_pwhash_OPSLIMIT_SENSITIVE;
        const mem = sodium.crypto_pwhash_MEMLIMIT_MODERATE;
        const kek = deriveKek(newPass, salt, ops, mem);
        const wrapped = seal(this.vk, kek);

        // Mint a fresh recovery code (the old one no longer opens the vault).
        const recoveryBytes = sodium.randombytes_buf(32);
        const recoveryKey = sodium.crypto_generichash(sodium.crypto_secretbox_KEYBYTES, recoveryBytes);
        const wrappedRecovery = seal(this.vk, recoveryKey);

        await api('PUT', {
            salt: b64(salt),
            kdf_ops: ops,
            kdf_mem: mem,
            wrapped_vault_key: wrapped.cipher,
            wrap_nonce: wrapped.nonce,
            wrapped_vault_key_recovery: wrappedRecovery.cipher,
            recovery_nonce: wrappedRecovery.nonce,
        });

        this.cache();

        return sodium.to_hex(recoveryBytes).replace(/(.{4})/g, '$1 ').trim();
    },

    // ---- Data operations (used by later phases) ----

    encryptMeta(obj) {
        return seal(sodium.from_string(JSON.stringify(obj)), this.vk);
    },

    decryptMeta(cipherB64, nonceB64) {
        return JSON.parse(sodium.to_string(open(cipherB64, nonceB64, this.vk)));
    },

    /** Seal a folder name as a JSON {c,n} string (same shape as file metadata). */
    sealName(name) {
        const m = this.encryptMeta({ name });
        return JSON.stringify({ c: m.cipher, n: m.nonce });
    },

    /**
     * Seal the whole opaque workspace manifest into a {c,n} JSON string. The JSON
     * is padded with trailing whitespace to the next 4 KiB bucket so the stored
     * ciphertext size blurs the true content size (JSON.parse ignores the
     * padding). Returns the sealed string for the store API.
     */
    sealManifest(obj) {
        let json = JSON.stringify(obj);
        const bucket = 4096;
        const target = Math.ceil((json.length + 1) / bucket) * bucket;
        json += ' '.repeat(target - json.length);
        const m = seal(sodium.from_string(json), this.vk);
        return JSON.stringify({ c: m.cipher, n: m.nonce });
    },

    /** Open a sealed manifest string back into the workspace object. */
    openManifest(enc) {
        const { c, n } = JSON.parse(enc);
        return JSON.parse(sodium.to_string(open(c, n, this.vk)));
    },

    /** The framed-ciphertext size for a plaintext of `total` bytes: a stream
     *  header, plus each 4 MiB message's auth tag and a 4-byte length prefix.
     *  A zero-byte file still emits one final (empty) message. */
    ciphertextSize(total) {
        const H = sodium.crypto_secretstream_xchacha20poly1305_HEADERBYTES;
        const A = sodium.crypto_secretstream_xchacha20poly1305_ABYTES;
        const chunks = total === 0 ? 1 : Math.ceil(total / CHUNK);

        return H + total + chunks * (A + 4);
    },

    /**
     * Begin a streaming content encryption with a fresh per-file key. The caller
     * feeds plaintext one CHUNK at a time and streams each returned framed
     * ciphertext straight to storage, so neither the whole file nor the whole
     * ciphertext is ever held in memory (constant-memory upload of any size).
     * `chunkSize` is the plaintext slice size the caller must use.
     */
    newContentEncryptor() {
        const fk = sodium.crypto_secretstream_xchacha20poly1305_keygen();
        const { state, header } = sodium.crypto_secretstream_xchacha20poly1305_init_push(fk);
        const vk = this.vk;

        return {
            chunkSize: CHUNK,
            header,
            // Encrypt one plaintext slice → framed (u32 length + ciphertext).
            encryptChunk(slice, isLast) {
                const cipher = sodium.crypto_secretstream_xchacha20poly1305_push(
                    state, slice, null,
                    isLast ? sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL
                        : sodium.crypto_secretstream_xchacha20poly1305_TAG_MESSAGE,
                );
                const frame = new Uint8Array(4 + cipher.length);
                frame.set(u32le(cipher.length), 0);
                frame.set(cipher, 4);

                return frame;
            },
            // Wrap the per-file key with the vault key (JSON {c,n}).
            sealKey() {
                const w = seal(fk, vk);

                return JSON.stringify({ c: w.cipher, n: w.nonce });
            },
        };
    },

    /**
     * Encrypt a File for upload. Content is sealed with a fresh per-file key via
     * secretstream (chunked, TAG_FINAL on the last chunk); the file key is then
     * wrapped with the vault key, and name/mime/size sealed as metadata. Nothing
     * plaintext leaves the browser.
     *
     * @returns {Promise<{blob: Blob, encMeta: string, encFileKey: string}>}
     */
    async encryptFile(file) {
        const fk = sodium.crypto_secretstream_xchacha20poly1305_keygen();
        const { state, header } = sodium.crypto_secretstream_xchacha20poly1305_init_push(fk);

        const parts = [header];
        const total = file.size;
        // Read the plaintext one 4 MiB slice at a time instead of buffering the
        // WHOLE file into memory first — a large file no longer needs ~2-3x its
        // size in RAM (only one slice + the growing ciphertext).
        for (let off = 0; off < total || off === 0;) {
            const end = Math.min(off + CHUNK, total);
            const last = end >= total;
            const slice = new Uint8Array(await file.slice(off, end).arrayBuffer());
            const cipher = sodium.crypto_secretstream_xchacha20poly1305_push(
                state,
                slice,
                null,
                last ? sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL
                    : sodium.crypto_secretstream_xchacha20poly1305_TAG_MESSAGE,
            );
            parts.push(u32le(cipher.length), cipher);
            off = end;
            if (last) {
                break;
            }
        }

        const encFileKey = seal(fk, this.vk);
        const encMeta = this.encryptMeta({ name: file.name, mime: file.type || 'application/octet-stream', size: total });

        return {
            blob: new Blob(parts, { type: 'application/octet-stream' }),
            encMeta: JSON.stringify({ c: encMeta.cipher, n: encMeta.nonce }),
            encFileKey: JSON.stringify({ c: encFileKey.cipher, n: encFileKey.nonce }),
        };
    },

    /**
     * Seal raw bytes with a fresh per-file key (secretstream, chunked) and wrap
     * that key + the given metadata (name/mime + the byte length) with the vault
     * key. Shared by upload and by re-encrypting an edited file.
     *
     * @returns {{blob: Blob, encMeta: string, encFileKey: string}}
     */
    encryptContent(bytes, { name, mime }) {
        const fk = sodium.crypto_secretstream_xchacha20poly1305_keygen();
        const { state, header } = sodium.crypto_secretstream_xchacha20poly1305_init_push(fk);

        const parts = [header];
        const total = bytes.length;
        // 4 MiB messages; a zero-length file still emits one final chunk.
        for (let off = 0; off < total || off === 0; ) {
            const end = Math.min(off + CHUNK, total);
            const last = end >= total;
            const cipher = sodium.crypto_secretstream_xchacha20poly1305_push(
                state,
                bytes.subarray(off, end),
                null,
                last ? sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL
                    : sodium.crypto_secretstream_xchacha20poly1305_TAG_MESSAGE,
            );
            parts.push(u32le(cipher.length), cipher);
            off = end;
            if (last) {
                break;
            }
        }

        const encFileKey = seal(fk, this.vk);
        const encMeta = this.encryptMeta({ name, mime: mime || 'application/octet-stream', size: total });

        return {
            blob: new Blob(parts, { type: 'application/octet-stream' }),
            encMeta: JSON.stringify({ c: encMeta.cipher, n: encMeta.nonce }),
            encFileKey: JSON.stringify({ c: encFileKey.cipher, n: encFileKey.nonce }),
        };
    },

    /**
     * Begin a streaming decryption: unwrap the file key, then feed the framed
     * ciphertext (header first, then message frames) incrementally. Lets a large
     * download be decrypted + written to disk without holding it all in memory.
     */
    beginDecrypt(encFileKey) {
        const wrapped = JSON.parse(encFileKey);
        const fk = open(wrapped.c, wrapped.n, this.vk);
        let state = null;

        return {
            headerLen: sodium.crypto_secretstream_xchacha20poly1305_HEADERBYTES,
            start(header) { state = sodium.crypto_secretstream_xchacha20poly1305_init_pull(header, fk); },
            // Decrypt one ciphertext message → {message, final}.
            pull(cipherMsg) {
                const res = sodium.crypto_secretstream_xchacha20poly1305_pull(state, cipherMsg);
                if (res === false) {
                    throw new Error('decrypt failed');
                }

                return { message: res.message, final: res.tag === sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL };
            },
        };
    },

    /**
     * Decrypt ciphertext bytes (as produced by encryptFile) back to a Uint8Array.
     *
     * @param {ArrayBuffer|Uint8Array} buffer
     * @param {string} encFileKey  JSON {c,n} of the wrapped file key.
     */
    decryptFile(buffer, encFileKey) {
        const wrapped = JSON.parse(encFileKey);
        const fk = open(wrapped.c, wrapped.n, this.vk);
        const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);

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
    },

    /**
     * Decrypt a file/folder metadata blob (JSON {c,n}) to {name, mime, size}.
     * Memoised per page by the ciphertext so repeated lookups don't re-decrypt.
     */
    decryptFileMeta(encMeta) {
        const hit = metaMemo.get(encMeta);
        if (hit) {
            return hit;
        }
        const m = JSON.parse(encMeta);
        const out = this.decryptMeta(m.c, m.n);
        metaMemo.set(encMeta, out);
        return out;
    },
};
