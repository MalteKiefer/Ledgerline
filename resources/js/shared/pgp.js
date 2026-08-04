// PGP support for the mail archive — thin wrappers over the vendored OpenPGP.js
// build. All operations run client-side; private keys live only in the sealed
// mailkeys vault store and in memory. Reading-only: generate/import + decrypt +
// signature status. No signing/sending.
//
// OpenPGP.js is vendored (resources/js/vendor/openpgp.min.mjs) and lazy-loaded
// as its own chunk — the npm registry is unreachable in this environment, so it
// is fetched from the CDN and pinned by vendoring (see .gitleaks.toml).

let _op = null;

export async function openpgp() {
    if (!_op) _op = await import('../vendor/openpgp.min.mjs');
    return _op;
}

/** True if the given text carries an inline/armored PGP encrypted message. */
export function isPgpEncrypted(text) {
    return typeof text === 'string' && text.includes('-----BEGIN PGP MESSAGE-----');
}

/** Extract the armored PGP MESSAGE block from a raw mail (inline or PGP/MIME). */
export function extractPgpMessage(text) {
    const m = /-----BEGIN PGP MESSAGE-----[\s\S]*?-----END PGP MESSAGE-----/.exec(text || '');
    return m ? m[0] : null;
}

/** Read public metadata (fingerprint, primary user id, private?) from armor. */
export async function keyInfo(armored) {
    const op = await openpgp();
    const key = await op.readKey({ armoredKey: armored });
    let userId = '';
    try { userId = (await key.getPrimaryUser())?.user?.userID?.userID || ''; } catch { /* no uid */ }
    return {
        fingerprint: key.getFingerprint(),
        userId,
        isPrivate: typeof key.isPrivate === 'function' ? key.isPrivate() : false,
    };
}

/** Derive the armored public key from an armored private key. */
export async function publicFromPrivate(armoredPrivate) {
    const op = await openpgp();
    const pk = await op.readPrivateKey({ armoredKey: armoredPrivate });
    return pk.toPublic().armor();
}

/** Generate a fresh ECC (curve25519) PGP keypair, armored. */
// Curves OpenPGP.js accepts for ECC key generation.
export const PGP_CURVES = ['curve25519', 'nistP256', 'nistP384', 'nistP521', 'brainpoolP256r1', 'brainpoolP384r1', 'brainpoolP512r1'];
export const PGP_RSA_BITS = [2048, 3072, 4096];

/**
 * Generate a PGP keypair across the full spectrum OpenPGP.js supports:
 *  - type: 'ecc' (curve) or 'rsa' (rsaBits)
 *  - userIDs: one or more { name, email } identities on the primary key
 *  - keyExpirationSeconds: 0 = never expires, else seconds from now
 *  - passphrase: protects the private key at rest (in addition to the vault)
 *  - subkeys: the primary key always certifies + signs; a dedicated ENCRYPTION
 *    subkey is always created; an extra SIGNING subkey is added when signSubkey
 *    is true (a common "separate signing subkey" setup).
 *
 * Note: OpenPGP.js does not expose an SSH-style AUTHENTICATION-usage subkey in
 * generateKey; for mail (sign + encrypt) the primary key's certify/sign
 * capability plus the encryption subkey cover the use case.
 */
export async function generateKey({ type = 'ecc', curve = 'curve25519', rsaBits = 3072, userIDs, name, email, passphrase, keyExpirationSeconds = 0, signSubkey = false }) {
    const op = await openpgp();
    const ids = (Array.isArray(userIDs) && userIDs.length)
        ? userIDs.map((u) => ({ name: u.name || '', email: u.email || '' })).filter((u) => u.name || u.email)
        : [{ name: name || '', email: email || '' }];

    // One encryption subkey by default; optionally an additional signing subkey.
    const subkeys = signSubkey ? [{}, { sign: true }] : [{}];

    const opts = {
        type,
        userIDs: ids.length ? ids : [{ name: '', email: '' }],
        passphrase: passphrase || undefined,
        keyExpirationTime: Math.max(0, Number(keyExpirationSeconds) || 0),
        subkeys,
        format: 'armored',
    };
    if (type === 'rsa') opts.rsaBits = PGP_RSA_BITS.includes(Number(rsaBits)) ? Number(rsaBits) : 3072;
    else opts.curve = PGP_CURVES.includes(curve) ? curve : 'curve25519';

    const { privateKey, publicKey } = await op.generateKey(opts);
    const info = await keyInfo(publicKey);
    return { publicKey, privateKey, ...info };
}

/**
 * Decrypt an armored PGP message with the given armored private keys (each with
 * its optional passphrase). Returns { text }. Throws if none of the keys work.
 *
 * @param {string} pgpText armored PGP message
 * @param {Array<{privateKey:string, passphrase?:string}>} keys
 */
export async function decrypt(pgpText, keys) {
    const op = await openpgp();
    const message = await op.readMessage({ armoredMessage: pgpText });
    const decryptionKeys = [];
    for (const k of keys) {
        try {
            let pk = await op.readPrivateKey({ armoredKey: k.privateKey });
            if (!pk.isDecrypted() && k.passphrase) pk = await op.decryptKey({ privateKey: pk, passphrase: k.passphrase });
            decryptionKeys.push(pk);
        } catch { /* skip a key that won't load */ }
    }
    if (!decryptionKeys.length) throw new Error('no usable private key');
    const { data } = await op.decrypt({ message, decryptionKeys });
    return { text: typeof data === 'string' ? data : new TextDecoder().decode(data) };
}
