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

// How long a "trusted device" stays unlocked across browser restarts (days),
// configurable in Security settings; default 7.
function rememberDays() {
    const meta = document.querySelector('meta[name="vault-remember-days"]')?.getAttribute('content');
    return Number(meta) > 0 ? Number(meta) : 7;
}

// Per-login token the cached vault key is bound to. Empty when signed out. A
// cached key only counts if its stored owner matches the current login, so the
// key cannot survive a logout + new login (nor be reused by a different login).
function currentOwner() {
    return document.querySelector('meta[name="vault-owner"]')?.getAttribute('content') || '';
}

// Stable per-user tag for the trusted persisted key: survives a session refresh
// (so a 7-day stay-unlocked works) but not a different login on the browser.
function currentUser() {
    return document.querySelector('meta[name="vault-user"]')?.getAttribute('content') || '';
}

// ---- Persistent (trusted-device) key storage ----
// On a trusted device the vault key is kept across browser restarts for
// rememberDays(), wrapped with a NON-EXTRACTABLE AES-GCM key held in IndexedDB:
// a stolen disk yields only ciphertext plus an unusable key handle (unwrapping
// needs code execution in this origin). This trades some at-rest strength for a
// Proton-style stay-unlocked window; a "public computer" unlock skips it and
// keeps the session-only + idle-lock behaviour.
const IDB_NAME = 'll-vault';
const IDB_STORE = 'session';
function idbReq(req) { return new Promise((res, rej) => { req.onsuccess = () => res(req.result); req.onerror = () => rej(req.error); }); }
async function idb(mode, fn) {
    const db = await new Promise((res, rej) => {
        const r = indexedDB.open(IDB_NAME, 1);
        r.onupgradeneeded = () => r.result.createObjectStore(IDB_STORE);
        r.onsuccess = () => res(r.result);
        r.onerror = () => rej(r.error);
    });
    try { return await fn(db.transaction(IDB_STORE, mode).objectStore(IDB_STORE)); } finally { db.close(); }
}
async function idbGet(key) { try { return await idb('readonly', (s) => idbReq(s.get(key))); } catch (e) { return undefined; } }
async function idbPut(key, val) { try { await idb('readwrite', (s) => idbReq(s.put(val, key))); } catch (e) { /* best effort */ } }
async function idbDel(key) { try { await idb('readwrite', (s) => idbReq(s.delete(key))); } catch (e) { /* best effort */ } }

const b64 = (bytes) => sodium.to_base64(bytes, sodium.base64_variants.ORIGINAL);
const unb64 = (str) => sodium.from_base64(str, sodium.base64_variants.ORIGINAL);

// Secretstream message size for file content.
const CHUNK = 4 * 1024 * 1024;

/**
 * Padmé (Nikitin et al.): round n up so at most O(log log n) size bits leak.
 * Extracted as a module-level helper so both Vault and VaultShareCrypto can
 * use it without copy-pasting the algorithm.
 */
function padme(n) {
    if (n < 2) return n;
    const e = Math.floor(Math.log2(n));
    const s = Math.floor(Math.log2(e)) + 1;
    const step = Math.pow(2, e - s);
    return Math.ceil(n / step) * step;
}

// Little-endian uint32 length prefix framing each ciphertext chunk.
function u32le(n) {
    return new Uint8Array([n & 0xff, (n >>> 8) & 0xff, (n >>> 16) & 0xff, (n >>> 24) & 0xff]);
}

function readU32le(bytes, off) {
    return bytes[off] | (bytes[off + 1] << 8) | (bytes[off + 2] << 16) | (bytes[off + 3] << 24);
}

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

// libsodium (the ~400 KB sumo/WASM build) is the single heaviest dependency and
// is only needed once the vault is actually used, so it is code-split out of the
// initial bundle and loaded on the first crypto call. Every public Vault method
// awaits ready() before touching `sodium`, so nothing runs before it resolves.
async function ready() {
    if (! sodium) {
        const mod = await import('libsodium-wrappers-sumo');
        const s = mod.default ?? mod;
        await s.ready;
        sodium = s;
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

/**
 * Crypto for public share links, independent of the vault key. A share carries
 * its own random share key (SK) that lives only in the link fragment. At share
 * time the owner unwraps each blob's per-file key with the vault key and re-wraps
 * it under SK; a public visitor (who has SK from the fragment but no vault key)
 * unwraps it back and decrypts the blob. The server never sees SK.
 */
export const ShareCrypto = {
    async ready() { await ready(); },

    /** Fresh 32-byte share key, base64 — goes in the link fragment only. */
    async newKey() { await ready(); return b64(sodium.randombytes_buf(sodium.crypto_secretbox_KEYBYTES)); },

    /** Seal a raw per-file key under the share key → JSON {c,n} for the manifest. */
    async wrap(rawFk, skB64) {
        await ready();
        const s = seal(rawFk, unb64(skB64));
        return JSON.stringify({ c: s.cipher, n: s.nonce });
    },

    /** Recover a raw per-file key from a manifest entry using the share key. */
    async unwrap(sealedJson, skB64) {
        await ready();
        const w = JSON.parse(sealedJson);
        return open(w.c, w.n, unb64(skB64));
    },

    /** Decrypt a blob's framed secretstream ciphertext with a raw per-file key. */
    async decrypt(buffer, rawFk) {
        await ready();
        const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
        const H = sodium.crypto_secretstream_xchacha20poly1305_HEADERBYTES;
        const state = sodium.crypto_secretstream_xchacha20poly1305_init_pull(bytes.subarray(0, H), rawFk);
        const chunks = [];
        let off = H;
        for (;;) {
            const len = (bytes[off] | (bytes[off + 1] << 8) | (bytes[off + 2] << 16) | (bytes[off + 3] << 24)) >>> 0;
            off += 4;
            const res = sodium.crypto_secretstream_xchacha20poly1305_pull(state, bytes.subarray(off, off + len));
            if (res === false) throw new Error('decrypt failed');
            off += len;
            chunks.push(res.message);
            if (res.tag === sodium.crypto_secretstream_xchacha20poly1305_TAG_FINAL) break;
        }
        let total = 0; for (const c of chunks) total += c.length;
        const out = new Uint8Array(total);
        let p = 0; for (const c of chunks) { out.set(c, p); p += c.length; }
        return out;
    },
};

/**
 * Crypto primitives for ZK cross-user vault sharing. DISTINCT from ShareCrypto
 * (which handles public share links). This object handles:
 *   - identity keypairs (x25519) for wrapping per-vault keys to specific users
 *   - anonymous sealed-box wrap/unwrap (crypto_box_seal)
 *   - sealed vault manifests keyed by an arbitrary vault key (not the owner VK)
 *
 * All methods are async and call ready() internally, matching the ShareCrypto
 * convention so callers can await each call without managing sodium state.
 */
export const VaultShareCrypto = {
    /** Ensure libsodium is loaded. Called at the start of every method. */
    async ready() { await ready(); },

    /**
     * Generate a fresh x25519 identity keypair.
     * Returns { pub: base64, sk: Uint8Array } — sk is raw bytes kept in memory only.
     */
    async newIdentity() {
        await ready();
        const kp = sodium.crypto_box_keypair();
        return { pub: b64(kp.publicKey), sk: kp.privateKey };
    },

    /** Fresh 32-byte vault key, base64. */
    async newVaultKey() {
        await ready();
        return b64(sodium.randombytes_buf(sodium.crypto_secretbox_KEYBYTES));
    },

    /**
     * Seal a vault key for a recipient using anonymous sealed-box (crypto_box_seal).
     * The sender needs only the recipient's public key; the recipient unwraps with
     * their keypair. Returns base64 ciphertext.
     */
    async wrapVaultKeyFor(vkB64, recipientPubB64) {
        await ready();
        return b64(sodium.crypto_box_seal(unb64(vkB64), unb64(recipientPubB64)));
    },

    /**
     * Unseal a vault key wrapped with crypto_box_seal.
     * Requires both the recipient's public key and raw private key (Uint8Array).
     * Returns the vault key as base64.
     */
    async unwrapVaultKey(wrappedB64, ownPubB64, ownSkBytes) {
        await ready();
        const out = sodium.crypto_box_seal_open(unb64(wrappedB64), unb64(ownPubB64), ownSkBytes);
        if (out === false) {
            throw new Error('unwrap failed: wrong keypair or corrupted ciphertext');
        }
        return b64(out);
    },

    /**
     * Short deterministic fingerprint for TOFU display/verification.
     * Returns 32-character lowercase hex (16 bytes via BLAKE2b).
     */
    async fingerprint(pubB64) {
        await ready();
        return sodium.to_hex(sodium.crypto_generichash(16, unb64(pubB64)));
    },

    /**
     * Seal an object as a Padmé-padded manifest keyed by an arbitrary vault key
     * (not the owner's VK). Mirrors Vault.sealManifest but accepts any key bytes.
     * Returns a JSON string { c, n }.
     */
    async sealVaultManifest(obj, vkBytes) {
        await ready();
        let json = JSON.stringify(obj);
        const target = Math.max(4096, padme(json.length + 1));
        json += ' '.repeat(target - json.length);
        const m = seal(sodium.from_string(json), vkBytes);
        return JSON.stringify({ c: m.cipher, n: m.nonce });
    },

    /**
     * Open a sealed vault manifest string back into an object.
     * Mirrors Vault.openManifest but accepts any key bytes.
     */
    async openVaultManifest(str, vkBytes) {
        await ready();
        const { c, n } = JSON.parse(str);
        return JSON.parse(sodium.to_string(open(c, n, vkBytes)));
    },
};

export const Vault = {
    vk: null,
    mode: 'trusted', // 'trusted' (persist N days) | 'public' (session + idle lock)

    async boot() {
        // Trusted device: a persisted, wrapped key survives a browser restart for
        // the configured window — try it first (IndexedDB, async).
        if (await this._restoreTrusted()) {
            return;
        }
        // Public computer / older session: a session-only key that dies with the
        // tab and idle-locks. Nothing cached = never unlocked this tab; return
        // WITHOUT loading libsodium so vault-free pages don't pay for it.
        const cached = sessionStorage.getItem(CACHE_KEY);
        if (! cached) {
            return;
        }
        await ready();
        const owner = currentOwner();
        const expires = Number(sessionStorage.getItem(CACHE_EXPIRES) || 0);
        const cachedOwner = sessionStorage.getItem(CACHE_OWNER) || '';
        if (owner !== '' && cachedOwner === owner && expires > Date.now()) {
            this.vk = unb64(cached);
            this.mode = 'public';
            this.touch();
        } else {
            this.lock();
        }
    },

    unlocked() {
        return this.vk !== null;
    },

    // Apply the unlocked key according to the chosen device trust: persist on a
    // trusted device, or session-cache + idle-lock on a public one.
    async _apply(remember) {
        if (remember) {
            this.mode = 'trusted';
            this._clearPublic();
            await this._persistTrusted();
        } else {
            this.mode = 'public';
            await this._clearTrusted();
            this.cache();
        }
    },

    async _persistTrusted() {
        try {
            let wrapKey = await idbGet('wrapKey');
            if (! (wrapKey instanceof CryptoKey)) {
                wrapKey = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
                await idbPut('wrapKey', wrapKey);
            }
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const ct = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, wrapKey, this.vk));
            await idbPut('vk', { ct: b64(ct), iv: b64(iv), expires: Date.now() + rememberDays() * 86400000, owner: currentUser() });
        } catch (e) { /* persistence is best-effort; the tab still holds the key */ }
    },
    async _restoreTrusted() {
        try {
            const rec = await idbGet('vk');
            const wrapKey = await idbGet('wrapKey');
            if (! rec || ! (wrapKey instanceof CryptoKey)) return false;
            if (rec.owner !== currentUser() || Date.now() > rec.expires) { await this._clearTrusted(); return false; }
            await ready();
            this.vk = new Uint8Array(await crypto.subtle.decrypt({ name: 'AES-GCM', iv: unb64(rec.iv) }, wrapKey, unb64(rec.ct)));
            this.mode = 'trusted';
            return true;
        } catch (e) { return false; }
    },
    async _clearTrusted() { await idbDel('vk'); },
    _clearPublic() {
        sessionStorage.removeItem(CACHE_KEY);
        sessionStorage.removeItem(CACHE_EXPIRES);
        sessionStorage.removeItem(CACHE_OWNER);
    },

    cache() {
        sessionStorage.setItem(CACHE_KEY, b64(this.vk));
        sessionStorage.setItem(CACHE_OWNER, currentOwner());
        this.touch();
    },

    touch() {
        // Only the public (session) mode idle-locks; a trusted device stays open.
        if (this.vk && this.mode !== 'trusted') {
            sessionStorage.setItem(CACHE_EXPIRES, String(Date.now() + idleMs()));
        }
    },

    // When the session key idle-expires (ms epoch); 0 for a trusted device (no
    // idle lock). Lets the in-page watchdog auto-lock a public session.
    expiresAt() {
        if (this.mode === 'trusted') return 0;
        return Number(sessionStorage.getItem(CACHE_EXPIRES) || 0);
    },

    lock() {
        this.vk = null;
        this._idKeys = null;
        this.mode = 'trusted';
        this._clearPublic();
        this._clearTrusted();
    },

    // Just fetches the server's public KDF params — no crypto, so it must NOT
    // pull in libsodium (the vault store reads status() on every page).
    async status() {
        return api('GET');
    },

    /** First-time setup: create the vault, return the recovery code to show once. */
    async setup(passphrase, remember = true) {
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
        await this._apply(remember);

        // Grouped hex, easy to write down.
        return sodium.to_hex(recoveryBytes).replace(/(.{4})/g, '$1 ').trim();
    },

    /** Unlock with the passphrase. Throws if wrong. */
    async unlock(passphrase, remember = true) {
        await ready(); // libsodium is lazy-loaded; status() no longer forces it
        const v = await this.status();
        if (! v.configured) {
            throw new Error('not configured');
        }
        const kek = deriveKek(passphrase, unb64(v.salt), v.kdf_ops, v.kdf_mem);
        this.vk = open(v.wrapped_vault_key, v.wrap_nonce, kek); // throws on wrong passphrase
        await this._apply(remember);
    },

    /** Restore access with the recovery code (spaces ignored). */
    async recover(recoveryCode, remember = true) {
        await ready(); // libsodium is lazy-loaded; status() no longer forces it
        const v = await this.status();
        if (! v.configured || ! v.has_recovery) {
            throw new Error('no recovery');
        }
        const recoveryBytes = sodium.from_hex(recoveryCode.replace(/\s+/g, ''));
        const recoveryKey = sodium.crypto_generichash(sodium.crypto_secretbox_KEYBYTES, recoveryBytes);
        this.vk = open(v.wrapped_vault_key_recovery, v.recovery_nonce, recoveryKey); // throws on wrong code
        await this._apply(remember);
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
        await ready(); // libsodium is lazy-loaded; ensure it before any crypto
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

    // ---- Identity keypair (ZK vault sharing) ----

    /**
     * Ensure this user has an x25519 identity keypair registered on the server.
     *
     * Strategy:
     *   1. Return cached {pub, sk} if already loaded this session.
     *   2. Fetch GET /vaults/keys — if the server already has a keypair for this
     *      user, unwrap the stored wrapped_secret_key with the VK (secretbox open)
     *      to recover the private key. This handles the multi-device case: a second
     *      browser recovers the SAME keypair the first browser published.
     *   3. If no server keypair exists, generate a fresh x25519 keypair, wrap the
     *      private key under the VK, and publish via PUT /vaults/keys.
     *
     * Returns { pub: base64, sk: Uint8Array } — sk is raw bytes kept in memory only.
     */
    async ensureIdentityKeys() {
        await ready();

        // 1. In-memory cache — avoids redundant server round-trips.
        if (this._idKeys) {
            return this._idKeys;
        }

        if (! this.vk) {
            throw new Error('vault locked');
        }

        // 2. Try to recover an existing keypair from the server.
        const existing = await fetch('/vaults/keys', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (! existing.ok) {
            throw new Error('identity key fetch failed');
        }
        const data = await existing.json();

        if (data.public_key && data.wrapped_secret_key) {
            // Parse the wrapped secret key JSON {c, n} stored by a previous device.
            const wrapped = JSON.parse(data.wrapped_secret_key);
            const sk = open(wrapped.c, wrapped.n, this.vk);
            this._idKeys = { pub: data.public_key, sk };
            return this._idKeys;
        }

        // Inconsistent server state: public key present but wrapped secret key missing.
        if (data.public_key && ! data.wrapped_secret_key) {
            throw new Error('identity key state inconsistent');
        }

        // 3. No existing keypair — generate, wrap under VK, and publish.
        const kp = sodium.crypto_box_keypair();
        const wrapped = seal(kp.privateKey, this.vk);
        const pub = b64(kp.publicKey);
        const fingerprint = await VaultShareCrypto.fingerprint(pub);

        const res = await fetch('/vaults/keys', {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({
                public_key: pub,
                wrapped_secret_key: JSON.stringify({ c: wrapped.cipher, n: wrapped.nonce }),
                fingerprint,
            }),
        });

        if (! res.ok) {
            throw new Error('identity key publish failed');
        }

        this._idKeys = { pub, sk: kp.privateKey };
        return this._idKeys;
    },

    // ---- Data operations (used by later phases) ----

    encryptMeta(obj) {
        return seal(sodium.from_string(JSON.stringify(obj)), this.vk);
    },

    /**
     * Seal the whole opaque workspace manifest into a {c,n} JSON string. The JSON
     * is padded with trailing whitespace to a Padmé bucket (leaks only
     * O(log log n) bits — a bounded ~12% overhead — instead of a fixed 4 KiB grid
     * whose relative leak grows with the manifest), with a 4 KiB floor so small
     * manifests don't reveal fine-grained sizes. JSON.parse ignores the padding.
     */
    sealManifest(obj) {
        let json = JSON.stringify(obj);
        const target = Math.max(4096, this._padme(json.length + 1));
        json += ' '.repeat(target - json.length);
        const m = seal(sodium.from_string(json), this.vk);
        return JSON.stringify({ c: m.cipher, n: m.nonce });
    },

    /** Padmé (Nikitin et al.): round n up so at most O(log log n) size bits leak. */
    _padme(n) { return padme(n); },

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
    /**
     * Unwrap a blob's per-file key with the vault key. Cheap (a single
     * secretbox open of ~40 bytes); used to hand a raw per-blob key to the
     * decrypt worker pool so the vault key itself never leaves this thread.
     */
    unwrapContentKey(encFileKey) {
        const wrapped = JSON.parse(encFileKey);

        return open(wrapped.c, wrapped.n, this.vk);
    },

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
};
