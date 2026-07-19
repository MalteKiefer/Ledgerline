/**
 * Client-side vault export helpers.
 *
 * All functions are pure and headless-testable (no DOM / Alpine deps).
 * Crypto uses vault.js primitives (Argon2id + XChaCha20-Poly1305).
 *
 * Export scope v1: personal-vault items only.
 * Shared-vault items are intentionally excluded (personal manifest only).
 */

import { vaultCryptoPrimitives } from '../vault';

// ---- Bitwarden type map ----
// https://bitwarden.com/help/encrypted-export/
const BW_TYPES = {
    login: 1,
    secure_note: 2,
    card: 3,
    identity: 4,
    // Ledgerline-specific types that have no direct Bitwarden equivalent:
    // keep as login (1) with a custom field indicating the original type.
    password: 1,
    wifi: 1,
    license: 1,
    server: 1,
    passkey: 1,
};

/**
 * Convert a Ledgerline items array + folders array into a Bitwarden-compatible
 * unencrypted export object.
 *
 * @param {Array} items  - Decrypted Ledgerline items (non-trashed, personal only)
 * @param {Array} folders - Ledgerline folder/vault objects [{id, name}]
 * @returns {object}  Bitwarden export shape
 */
export function buildBitwardenJson(items, folders) {
    const bwFolders = folders.map((f) => ({ id: f.id, name: f.name }));

    const bwItems = items
        .filter((x) => ! x.trashed)
        .map((x) => {
            const type = BW_TYPES[x.type] ?? 1;
            const fields = x.fields || {};
            const custom = (x.custom || []).map((c) => ({
                name: c.label || '',
                value: c.value || '',
                type: c.kind === 'secret' ? 1 : (c.kind === 'url' ? 3 : 0),
            }));

            const bwItem = {
                id: x.id,
                organizationId: null,
                folderId: x.folder || null,
                type,
                name: x.title || '',
                notes: fields.note || null,
                favorite: x.favorite ? true : false,
                fields: custom.length ? custom : [],
                reprompt: 0,
            };

            // Inject original type as a custom field when it's not a native Bitwarden type
            const nativeBwTypes = ['login', 'secure_note', 'card', 'identity'];
            if (! nativeBwTypes.includes(x.type)) {
                bwItem.fields = [{ name: '_ledgerline_type', value: x.type, type: 0 }, ...bwItem.fields];
            }

            if (type === 1) {
                // Login
                const urls = (fields.urls || []).filter(Boolean).map((u) => ({ match: null, uri: u }));
                bwItem.login = {
                    username: fields.username || fields.ssid || fields.host || fields.product || null,
                    password: fields.password || fields.licensekey || null,
                    uris: urls.length ? urls : null,
                    totp: fields.totp || null,
                };
                // Passkeys attached to login items
                if ((fields.passkeys || []).length) {
                    bwItem.login.fido2Credentials = (fields.passkeys || []).map((pk) => ({
                        credentialId: pk.credentialId || '',
                        keyType: 'public-key',
                        keyAlgorithm: 'ECDSA',
                        keyCurve: 'P-256',
                        keyValue: pk.privateKey || '',
                        rpId: pk.rpId || '',
                        rpName: pk.rpId || '',
                        userHandle: pk.userHandle || '',
                        userName: pk.userName || '',
                        userDisplayName: pk.userDisplayName || '',
                        discoverable: true,
                        creationDate: pk.createdAt || null,
                    }));
                }
                // Standalone passkey type: store as fido2Credential
                if (x.type === 'passkey') {
                    bwItem.login.fido2Credentials = [{
                        credentialId: fields.credentialId || '',
                        keyType: 'public-key',
                        keyAlgorithm: 'ECDSA',
                        keyCurve: 'P-256',
                        keyValue: fields.privateKey || '',
                        rpId: fields.rpId || '',
                        rpName: fields.rpId || '',
                        userHandle: fields.userHandle || '',
                        userName: fields.userName || '',
                        userDisplayName: fields.userDisplayName || '',
                        discoverable: true,
                        creationDate: fields.createdAt || null,
                    }];
                }
            } else if (type === 2) {
                // Secure note
                bwItem.secureNote = { type: 0 };
            } else if (type === 3) {
                // Card
                bwItem.card = {
                    cardholderName: fields.cardholder || null,
                    brand: null,
                    number: fields.number || null,
                    expMonth: fields.expiry ? (fields.expiry.split('/')[0] || null) : null,
                    expYear: fields.expiry ? (fields.expiry.split('/')[1] || null) : null,
                    code: fields.cvv || null,
                };
            } else if (type === 4) {
                // Identity
                bwItem.identity = {
                    title: null,
                    firstName: fields.firstName || null,
                    middleName: null,
                    lastName: fields.lastName || null,
                    address1: fields.street || null,
                    address2: null,
                    address3: null,
                    city: fields.city || null,
                    state: fields.state || null,
                    postalCode: fields.zip || null,
                    country: fields.country || null,
                    company: fields.company || null,
                    email: fields.email || null,
                    phone: fields.phone || null,
                    ssn: null,
                    username: null,
                    passportNumber: null,
                    licenseNumber: null,
                };
            }

            return bwItem;
        });

    return {
        encrypted: false,
        folders: bwFolders,
        items: bwItems,
    };
}

// ---- CSV export ----

function csvEscape(val) {
    const str = String(val ?? '');
    if (str.includes('"') || str.includes(',') || str.includes('\n') || str.includes('\r')) {
        return '"' + str.replaceAll('"', '""') + '"';
    }
    return str;
}

/**
 * Build a generic CSV export string.
 * Header: name,username,password,url,notes,totp
 *
 * @param {Array} items - Decrypted Ledgerline items
 * @returns {string}
 */
export function buildCsv(items) {
    const header = ['name', 'username', 'password', 'url', 'notes', 'totp'];
    const rows = [header.join(',')];

    for (const x of items) {
        if (x.trashed) continue;
        const fields = x.fields || {};
        const url = (fields.urls || []).filter(Boolean)[0] || '';
        rows.push([
            csvEscape(x.title || ''),
            csvEscape(fields.username || ''),
            csvEscape(fields.password || ''),
            csvEscape(url),
            csvEscape(fields.note || ''),
            csvEscape(fields.totp || ''),
        ].join(','));
    }

    return rows.join('\n');
}

// ---- Encrypted export ----

/**
 * Encrypt a JSON string with a user passphrase.
 * Envelope: {format, kdf, ops, mem, salt, nonce, cipher} — all base64 fields.
 *
 * KDF params match the vault: Argon2id ops=4 (SENSITIVE), mem=268435456 (256 MiB).
 *
 * @param {string} jsonString - The plaintext to protect
 * @param {string} passphrase - User-supplied passphrase
 * @returns {Promise<string>} JSON envelope string
 */
export async function encryptExport(jsonString, passphrase) {
    const p = await vaultCryptoPrimitives();

    const salt = p.randomBytes(p.SALTBYTES);
    const ops = p.OPSLIMIT_SENSITIVE; // 4
    const mem = p.MEMLIMIT_MODERATE;  // 268435456 (256 MiB)

    const key = p.deriveKek(passphrase, salt, ops, mem);
    const { cipher, nonce } = p.seal(p.fromString(jsonString), key);

    return JSON.stringify({
        format: 'ledgerline-export-v1',
        kdf: 'argon2id',
        ops,
        mem,
        salt: p.b64(salt),
        nonce,
        cipher,
    });
}

/**
 * Decrypt a ledgerline-export-v1 envelope.
 * Throws an Error if the passphrase is wrong or the envelope is malformed.
 *
 * @param {string} envelopeString - JSON envelope produced by encryptExport
 * @param {string} passphrase - User-supplied passphrase
 * @returns {Promise<string>} The original JSON string
 */
export async function decryptExport(envelopeString, passphrase) {
    const p = await vaultCryptoPrimitives();
    const env = JSON.parse(envelopeString);

    if (env.format !== 'ledgerline-export-v1' || env.kdf !== 'argon2id') {
        throw new Error('unsupported export format');
    }

    if (env.ops !== 4 || env.mem !== 268435456) {
        throw new Error('unsupported export parameters');
    }

    const salt = p.unb64(env.salt);
    const key = p.deriveKek(passphrase, salt, env.ops, env.mem);

    // open() throws 'decrypt failed' on wrong passphrase
    const plain = p.open(env.cipher, env.nonce, key);
    return p.toString(plain);
}
