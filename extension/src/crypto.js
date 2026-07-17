// Vault crypto for the extension — a faithful subset of the web app's vault.js,
// so a passphrase derives the exact same vault key and the sealed manifest opens
// identically. libsodium runs only here (in the background service worker).
import _sodium from 'libsodium-wrappers-sumo';

let sodium = null;
async function ready() {
    if (! sodium) { await _sodium.ready; sodium = _sodium; }
    return sodium;
}

const unb64 = (s) => sodium.from_base64(s, sodium.base64_variants.ORIGINAL);

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

function open(cipherB64, nonceB64, key) {
    const out = sodium.crypto_secretbox_open_easy(unb64(cipherB64), unb64(nonceB64), key);
    if (out === false) throw new Error('decrypt failed');
    return out;
}

/**
 * Derive the vault key from the passphrase + the server's KDF params, then
 * unwrap it. Throws on a wrong passphrase. Returns raw VK bytes (Uint8Array).
 * @param {object} vault  /api/v1/vault payload
 */
export async function deriveVaultKey(passphrase, vault) {
    await ready();
    const kek = deriveKek(passphrase, unb64(vault.salt), vault.kdf_ops, vault.kdf_mem);
    return open(vault.wrapped_vault_key, vault.wrap_nonce, kek); // throws => wrong passphrase
}

/** Open a sealed workspace manifest (the /store ciphertext) with the VK. */
export async function openManifest(ciphertext, vkBytes) {
    await ready();
    const { c, n } = JSON.parse(ciphertext);
    return JSON.parse(sodium.to_string(open(c, n, vkBytes)));
}

export async function b64(bytes) { await ready(); return sodium.to_base64(bytes, sodium.base64_variants.ORIGINAL); }
export async function fromB64(str) { await ready(); return sodium.from_base64(str, sodium.base64_variants.ORIGINAL); }
