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
export async function generateKey({ name, email, passphrase }) {
    const op = await openpgp();
    const { privateKey, publicKey } = await op.generateKey({
        type: 'ecc',
        curve: 'curve25519',
        userIDs: [{ name: name || '', email: email || '' }],
        passphrase: passphrase || undefined,
        format: 'armored',
    });
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
