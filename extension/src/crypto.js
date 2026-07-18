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

/** Open a sealed workspace manifest (the /store ciphertext) with the VK.
 *  Shared-vault stores are sealed identically (sealVaultManifest mirrors
 *  sealManifest), so the same opener works with a per-vault key. */
export async function openManifest(ciphertext, vkBytes) {
    await ready();
    const { c, n } = JSON.parse(ciphertext);
    return JSON.parse(sodium.to_string(open(c, n, vkBytes)));
}

/** Recover our X25519 identity secret key: it is stored as a VK-sealed
 *  {c,n} JSON blob (vault.js ensureIdentityKeys). Returns raw sk bytes. */
export async function unwrapIdentitySecret(wrappedJson, vkBytes) {
    await ready();
    const { c, n } = JSON.parse(wrappedJson);
    return open(c, n, vkBytes);
}

/** crypto_box_seal_open: recover a per-vault key wrapped to our X25519
 *  public key. Throws if it wasn't sealed to us. Returns raw VK_vault bytes. */
export async function unwrapVaultKey(wrappedB64, ownPubBytes, ownSkBytes) {
    await ready();
    const out = sodium.crypto_box_seal_open(unb64(wrappedB64), ownPubBytes, ownSkBytes);
    if (out === false) throw new Error('vault key unwrap failed');
    return out;
}

/** Padmé (Nikitin et al.) — mirrors vault.js so manifests we write match. */
function padme(n) {
    if (n < 2) return n;
    const e = Math.floor(Math.log2(n));
    const s = Math.floor(Math.log2(e)) + 1;
    const step = Math.pow(2, e - s);
    return Math.ceil(n / step) * step;
}

/**
 * Seal the whole workspace manifest back into a {c,n} JSON string, identical to
 * vault.js sealManifest: Padmé-padded JSON (4 KiB floor), XChaCha20 secretbox.
 */
export async function sealManifest(obj, vkBytes) {
    await ready();
    let json = JSON.stringify(obj);
    const target = Math.max(4096, padme(json.length + 1));
    json += ' '.repeat(target - json.length);
    const nonce = sodium.randombytes_buf(sodium.crypto_secretbox_NONCEBYTES);
    const cipher = sodium.crypto_secretbox_easy(sodium.from_string(json), nonce, vkBytes);
    return JSON.stringify({
        c: sodium.to_base64(cipher, sodium.base64_variants.ORIGINAL),
        n: sodium.to_base64(nonce, sodium.base64_variants.ORIGINAL),
    });
}

export async function b64(bytes) { await ready(); return sodium.to_base64(bytes, sodium.base64_variants.ORIGINAL); }
export async function fromB64(str) { await ready(); return sodium.from_base64(str, sodium.base64_variants.ORIGINAL); }
