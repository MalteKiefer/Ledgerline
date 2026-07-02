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

    /** Ensure libsodium is initialised (used by pages without an unlocked vault). */
    async ensureReady() {
        await ready();
    },

    // ---- Note sharing (independent of the vault key) ----

    /**
     * Encrypt a snapshot object under a fresh random share key.
     *
     * @returns {{cipher: string, nonce: string, key: string}} key is base64.
     */
    shareEncrypt(obj) {
        const key = sodium.randombytes_buf(sodium.crypto_secretbox_KEYBYTES);
        const sealed = seal(sodium.from_string(JSON.stringify(obj)), key);
        return { cipher: sealed.cipher, nonce: sealed.nonce, key: b64(key) };
    },

    /**
     * Decrypt a share snapshot with its base64 share key. Throws if the key or
     * ciphertext is wrong.
     */
    shareDecrypt(cipherB64, nonceB64, keyB64) {
        return JSON.parse(sodium.to_string(open(cipherB64, nonceB64, unb64(keyB64))));
    },

    /**
     * Wrap a base64 share key with a key derived from a password (Argon2id).
     * Returns the fields the server stores so a recipient can re-derive it.
     */
    sharePasswordWrap(keyB64, password) {
        const salt = sodium.randombytes_buf(sodium.crypto_pwhash_SALTBYTES);
        const ops = sodium.crypto_pwhash_OPSLIMIT_INTERACTIVE;
        const mem = sodium.crypto_pwhash_MEMLIMIT_INTERACTIVE;
        const wrapped = seal(unb64(keyB64), deriveKek(password, salt, ops, mem));
        return {
            wrapped_key: wrapped.cipher,
            wrap_nonce: wrapped.nonce,
            wrap_salt: b64(salt),
            wrap_ops: ops,
            wrap_mem: mem,
        };
    },

    /**
     * Recover the base64 share key from a password wrap. Throws on wrong password.
     */
    sharePasswordUnwrap(fields, password) {
        const kek = deriveKek(password, unb64(fields.wrap_salt), fields.wrap_ops, fields.wrap_mem);
        return b64(open(fields.wrapped_key, fields.wrap_nonce, kek));
    },

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

    lock() {
        this.vk = null;
        metaMemo.clear();
        sessionStorage.removeItem(CACHE_KEY);
        sessionStorage.removeItem(CACHE_EXPIRES);
        sessionStorage.removeItem(CACHE_OWNER);
        this.cacheClearAll();
    },

    // ---- Encrypted client cache (localStorage, sealed with the vault key) ----
    //
    // Used for mail stats/headers so they show instantly and can be prefetched
    // in the background. Entries are ciphertext, namespaced by the current login
    // owner, and dropped on lock/logout.

    cacheKey(key) {
        return `mailcache:${currentOwner()}:${key}`;
    },

    cachePut(key, obj) {
        if (! this.vk) return;
        try {
            const sealed = seal(sodium.from_string(JSON.stringify(obj)), this.vk);
            localStorage.setItem(this.cacheKey(key), JSON.stringify(sealed));
        } catch (e) { /* quota or serialise error: skip caching */ }
    },

    cacheGet(key) {
        if (! this.vk) return null;
        try {
            const raw = localStorage.getItem(this.cacheKey(key));
            if (! raw) return null;
            const { cipher, nonce } = JSON.parse(raw);
            return JSON.parse(sodium.to_string(open(cipher, nonce, this.vk)));
        } catch (e) {
            return null;
        }
    },

    cacheClearAll() {
        try {
            const doomed = [];
            for (let i = 0; i < localStorage.length; i++) {
                const name = localStorage.key(i);
                if (name && name.startsWith('mailcache:')) doomed.push(name);
            }
            doomed.forEach((n) => localStorage.removeItem(n));
        } catch (e) { /* ignore */ }
    },

    async status() {
        await ready();
        return api('GET');
    },

    /** First-time setup: create the vault, return the recovery code to show once. */
    async setup(passphrase) {
        await ready();
        const salt = sodium.randombytes_buf(sodium.crypto_pwhash_SALTBYTES);
        const ops = sodium.crypto_pwhash_OPSLIMIT_MODERATE;
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

        const salt = sodium.randombytes_buf(sodium.crypto_pwhash_SALTBYTES);
        const ops = sodium.crypto_pwhash_OPSLIMIT_MODERATE;
        const mem = sodium.crypto_pwhash_MEMLIMIT_MODERATE;
        const kek = deriveKek(newPass, salt, ops, mem);
        const wrapped = seal(this.vk, kek);

        await api('PUT', {
            salt: b64(salt),
            kdf_ops: ops,
            kdf_mem: mem,
            wrapped_vault_key: wrapped.cipher,
            wrap_nonce: wrapped.nonce,
        });

        this.cache();
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
     * Encrypt a File for upload. Content is sealed with a fresh per-file key via
     * secretstream (chunked, TAG_FINAL on the last chunk); the file key is then
     * wrapped with the vault key, and name/mime/size sealed as metadata. Nothing
     * plaintext leaves the browser.
     *
     * @returns {Promise<{blob: Blob, encMeta: string, encFileKey: string}>}
     */
    async encryptFile(file) {
        const bytes = new Uint8Array(await file.arrayBuffer());
        return this.encryptContent(bytes, {
            name: file.name,
            mime: file.type || 'application/octet-stream',
        });
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

    // ---- Manifest model: one encrypted directory blob + anonymous content blobs ----

    /**
     * Pad a plaintext length to a size bucket so the stored blob length does not
     * reveal the real file size: at least 4 KiB, then powers of two up to 1 MiB,
     * above that the next 1 MiB multiple.
     */
    paddedSize(n) {
        const MIB = 1024 * 1024;
        if (n <= 4096) return 4096;
        if (n >= MIB) return Math.ceil(n / MIB) * MIB;
        let bucket = 4096;
        while (bucket < n) bucket *= 2;
        return bucket;
    },

    /**
     * Seal raw bytes into an uploadable blob: pad to a size bucket, then
     * secretstream-encrypt with a fresh per-file key. The key goes into the
     * manifest (itself sealed with the vault key), never to the server.
     *
     * @returns {{blob: Blob, key: string}}
     */
    encryptBlob(bytes) {
        const fk = sodium.crypto_secretstream_xchacha20poly1305_keygen();
        const { state, header } = sodium.crypto_secretstream_xchacha20poly1305_init_push(fk);

        const padded = new Uint8Array(this.paddedSize(bytes.length));
        padded.set(bytes);
        padded.set(sodium.randombytes_buf(padded.length - bytes.length), bytes.length);

        const parts = [header];
        const total = padded.length;
        for (let off = 0; off < total || off === 0; ) {
            const end = Math.min(off + CHUNK, total);
            const last = end >= total;
            const cipher = sodium.crypto_secretstream_xchacha20poly1305_push(
                state,
                padded.subarray(off, end),
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

        return { blob: new Blob(parts, { type: 'application/octet-stream' }), key: b64(fk) };
    },

    /**
     * Decrypt a blob's ciphertext with its manifest key and strip the padding.
     */
    decryptBlob(buffer, keyB64, realSize) {
        const fk = unb64(keyB64);
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

        return concat(chunks).subarray(0, realSize);
    },

    /**
     * Fetch and decrypt the directory manifest. An empty vault yields a fresh
     * structure. Returns {data, version} for optimistic-locked saves.
     */
    async loadManifest(name) {
        const res = await fetch(`/vault/manifest/${name}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (! res.ok) {
            throw new Error('manifest load failed');
        }
        const { cipher, nonce, version } = await res.json();
        if (! cipher) {
            return { data: { v: 1, folders: [], files: [] }, version };
        }

        return { data: this.decryptMeta(cipher, nonce), version };
    },

    /**
     * Seal and store the manifest. Throws {stale: true, version} when another
     * tab saved first — reload, reapply, retry.
     */
    async saveManifest(name, data, version) {
        const sealed = seal(sodium.from_string(JSON.stringify(data)), this.vk);
        const res = await fetch(`/vault/manifest/${name}`, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ cipher: sealed.cipher, nonce: sealed.nonce, version }),
        });
        if (res.status === 409) {
            const body = await res.json();
            throw Object.assign(new Error('stale manifest'), { stale: true, version: body.version });
        }
        if (! res.ok) {
            throw new Error('manifest save failed');
        }

        return (await res.json()).version;
    },
};
