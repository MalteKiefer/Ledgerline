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

// Idle timeout (minutes) is configurable in Security settings; default 10.
function idleMs() {
    const meta = document.querySelector('meta[name="vault-idle-minutes"]')?.getAttribute('content');
    const minutes = Number(meta) > 0 ? Number(meta) : 10;
    return minutes * 60 * 1000;
}

const b64 = (bytes) => sodium.to_base64(bytes, sodium.base64_variants.ORIGINAL);
const unb64 = (str) => sodium.from_base64(str, sodium.base64_variants.ORIGINAL);

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
        // Restore a cached, non-expired vault key for this tab session.
        const expires = Number(sessionStorage.getItem(CACHE_EXPIRES) || 0);
        const cached = sessionStorage.getItem(CACHE_KEY);
        if (cached && expires > Date.now()) {
            this.vk = unb64(cached);
            this.touch();
        }
    },

    unlocked() {
        return this.vk !== null;
    },

    cache() {
        sessionStorage.setItem(CACHE_KEY, b64(this.vk));
        this.touch();
    },

    touch() {
        if (this.vk) {
            sessionStorage.setItem(CACHE_EXPIRES, String(Date.now() + idleMs()));
        }
    },

    lock() {
        this.vk = null;
        sessionStorage.removeItem(CACHE_KEY);
        sessionStorage.removeItem(CACHE_EXPIRES);
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

    // ---- Data operations (used by later phases) ----

    encryptMeta(obj) {
        return seal(sodium.from_string(JSON.stringify(obj)), this.vk);
    },

    decryptMeta(cipherB64, nonceB64) {
        return JSON.parse(sodium.to_string(open(cipherB64, nonceB64, this.vk)));
    },
};
