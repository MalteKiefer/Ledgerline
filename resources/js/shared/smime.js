// S/MIME support for the mail archive, over the vendored node-forge build.
// Client-side only. Two operations: import a PKCS#12 (.p12/.pfx) bundle into a
// PEM private key + certificate (stored sealed in the mailkeys vault), and
// decrypt an S/MIME enveloped message (application/pkcs7-mime, enveloped-data).
// Reading-only. Vendored like OpenPGP.js (npm registry unreachable here).

let _forge = null;

async function forge() {
    if (!_forge) {
        const m = await import('../vendor/forge.min.js');
        _forge = m.default || m;
    }
    return _forge;
}

function bytesToBinaryString(bytes) {
    let s = '';
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) s += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
    return s;
}

function certFingerprint(f, cert) {
    const der = f.asn1.toDer(f.pki.certificateToAsn1(cert)).getBytes();
    const md = f.md.sha256.create();
    md.update(der);
    return md.digest().toHex();
}

export const SMIME_RSA_BITS = [2048, 3072, 4096];

/**
 * Generate a self-signed S/MIME certificate + RSA key entirely in the browser.
 * S/MIME certificates are X.509 with an rfc822Name (email) SAN and the
 * emailProtection extended-key-usage. A self-signed cert is enough to
 * DECRYPT mail encrypted to it and to SIGN — a CA chain only matters for a
 * counterparty verifying your signature against a trusted root.
 *
 * @returns {Promise<{privateKeyPem:string, certPem:string, subject:string, fingerprint:string}>}
 */
export async function generateSmime({ commonName = '', email = '', rsaBits = 3072, expiryDays = 730 }) {
    const f = await forge();
    const bits = SMIME_RSA_BITS.includes(Number(rsaBits)) ? Number(rsaBits) : 3072;
    const keys = f.pki.rsa.generateKeyPair({ bits, e: 0x10001 });

    const cert = f.pki.createCertificate();
    cert.publicKey = keys.publicKey;
    cert.serialNumber = '01' + f.util.bytesToHex(f.random.getBytesSync(8));
    cert.validity.notBefore = new Date();
    cert.validity.notAfter = new Date();
    cert.validity.notAfter.setDate(cert.validity.notBefore.getDate() + Math.max(1, Number(expiryDays) || 730));

    const attrs = [];
    if (commonName) attrs.push({ name: 'commonName', value: commonName });
    if (email) attrs.push({ name: 'emailAddress', value: email });
    if (!attrs.length) attrs.push({ name: 'commonName', value: email || 'S/MIME' });
    cert.setSubject(attrs);
    cert.setIssuer(attrs); // self-signed

    const exts = [
        { name: 'basicConstraints', cA: false },
        { name: 'keyUsage', digitalSignature: true, keyEncipherment: true, dataEncipherment: true },
        { name: 'extKeyUsage', emailProtection: true },
    ];
    if (email) exts.push({ name: 'subjectAltName', altNames: [{ type: 1, value: email }] }); // rfc822Name
    cert.setExtensions(exts);

    cert.sign(keys.privateKey, f.md.sha256.create());

    return {
        privateKeyPem: f.pki.privateKeyToPem(keys.privateKey),
        certPem: f.pki.certificateToPem(cert),
        subject: email || commonName || '',
        fingerprint: certFingerprint(f, cert),
    };
}

/**
 * Import an S/MIME identity from PEM text (a private key PEM + a certificate
 * PEM, in either order, possibly concatenated). Validates both and returns the
 * normalized pieces + fingerprint.
 *
 * @returns {Promise<{privateKeyPem:string, certPem:string, subject:string, fingerprint:string}>}
 */
export async function importSmimePem(pemText) {
    const f = await forge();
    const keyMatch = /-----BEGIN (?:RSA |EC )?PRIVATE KEY-----[\s\S]*?-----END (?:RSA |EC )?PRIVATE KEY-----/.exec(pemText);
    const certMatch = /-----BEGIN CERTIFICATE-----[\s\S]*?-----END CERTIFICATE-----/.exec(pemText);
    if (!keyMatch || !certMatch) throw new Error('need a private key PEM and a certificate PEM');

    const key = f.pki.privateKeyFromPem(keyMatch[0]);
    const cert = f.pki.certificateFromPem(certMatch[0]);
    let subject = '';
    try { subject = cert.subject.getField('CN')?.value || cert.subject.getField('E')?.value || ''; } catch { /* none */ }

    return {
        privateKeyPem: f.pki.privateKeyToPem(key),
        certPem: f.pki.certificateToPem(cert),
        subject,
        fingerprint: certFingerprint(f, cert),
    };
}

/** True if the raw mail carries an S/MIME cryptographic SIGNATURE. */
export function isSmimeSigned(rawText) {
    if (typeof rawText !== 'string') return false;
    const head = rawText.slice(0, 4000).toLowerCase();
    return (/content-type:\s*application\/(x-)?pkcs7-mime/.test(head) && head.includes('signed-data'))
        || (/content-type:\s*multipart\/signed/.test(head) && /application\/(x-)?pkcs7-signature/.test(head));
}

/** True if the raw mail is an S/MIME enveloped (encrypted) message. */
export function isSmimeEncrypted(rawText) {
    if (typeof rawText !== 'string') return false;
    const head = rawText.slice(0, 4000).toLowerCase();
    return /content-type:\s*application\/(x-)?pkcs7-mime/.test(head) && head.includes('enveloped-data');
}

/**
 * Import a PKCS#12 file (Uint8Array) with its passphrase.
 * @returns {Promise<{privateKeyPem:string, certPem:string, subject:string, fingerprint:string}>}
 */
export async function importP12(bytes, passphrase) {
    const f = await forge();
    const asn1 = f.asn1.fromDer(f.util.createBuffer(bytesToBinaryString(bytes)));
    const p12 = f.pkcs12.pkcs12FromAsn1(asn1, false, passphrase || '');

    let key = null;
    const shrouded = p12.getBags({ bagType: f.pki.oids.pkcs8ShroudedKeyBag })[f.pki.oids.pkcs8ShroudedKeyBag] || [];
    const plainKey = p12.getBags({ bagType: f.pki.oids.keyBag })[f.pki.oids.keyBag] || [];
    key = (shrouded[0] || plainKey[0])?.key || null;

    const certBags = p12.getBags({ bagType: f.pki.oids.certBag })[f.pki.oids.certBag] || [];
    const cert = certBags[0]?.cert || null;
    if (!key || !cert) throw new Error('p12 has no key/cert');

    let subject = '';
    try { subject = cert.subject.getField('CN')?.value || cert.subject.getField('E')?.value || ''; } catch { /* none */ }

    return {
        privateKeyPem: f.pki.privateKeyToPem(key),
        certPem: f.pki.certificateToPem(cert),
        subject,
        fingerprint: certFingerprint(f, cert),
    };
}

// Parse the enveloped CMS out of a raw mail into a forge PKCS7 message.
async function pkcs7FromMail(f, rawText) {
    const norm = rawText.replace(/\r\n/g, '\n');
    const body = norm.slice(norm.indexOf('\n\n') + 2).replace(/\s+/g, '');
    const pem = `-----BEGIN PKCS7-----\n${body.replace(/(.{64})/g, '$1\n')}\n-----END PKCS7-----\n`;
    return f.pkcs7.messageFromPem(pem);
}

/** True if the CMS message names the given certificate as a recipient. */
export async function recipientMatches(rawText, certPem) {
    const f = await forge();
    try {
        const p7 = await pkcs7FromMail(f, rawText);
        return !!p7.findRecipient(f.pki.certificateFromPem(certPem));
    } catch {
        return false;
    }
}

/**
 * Decrypt an S/MIME enveloped message. `rawText` is the full RFC822 (headers +
 * base64 CMS body). `keys` = [{privateKeyPem, certPem}]. Returns { text } where
 * text is the decrypted inner MIME message. Throws if no key matches.
 */
export async function decryptSmime(rawText, keys) {
    const f = await forge();
    const p7 = await pkcs7FromMail(f, rawText);

    for (const k of keys) {
        try {
            const priv = f.pki.privateKeyFromPem(k.privateKeyPem);
            const cert = f.pki.certificateFromPem(k.certPem);
            const recipient = p7.findRecipient(cert);
            if (!recipient) continue;
            p7.decrypt(recipient, priv);
            const content = p7.content?.getBytes ? p7.content.getBytes() : String(p7.content || '');
            return { text: content };
        } catch { /* try next key */ }
    }
    throw new Error('no usable S/MIME key');
}
