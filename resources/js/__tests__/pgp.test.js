import { describe, it, expect } from 'vitest';
import { generateKey, keyInfo, decrypt, isPgpEncrypted } from '../shared/pgp.js';
import { openpgp } from '../shared/pgp.js';

describe('pgp (vendored OpenPGP.js)', () => {
    it('isPgpEncrypted detects an armored message', () => {
        expect(isPgpEncrypted('x\n-----BEGIN PGP MESSAGE-----\n...')).toBe(true);
        expect(isPgpEncrypted('plain text')).toBe(false);
    });

    it('generates a keypair, reports fingerprint, and round-trips decrypt', async () => {
        const kp = await generateKey({ name: 'Test', email: 't@example.com' });
        expect(kp.publicKey).toContain('BEGIN PGP PUBLIC KEY');
        expect(kp.privateKey).toContain('BEGIN PGP PRIVATE KEY');
        expect(kp.fingerprint).toMatch(/^[0-9a-f]{40}$/);

        const info = await keyInfo(kp.publicKey);
        expect(info.userId).toContain('t@example.com');
        expect(info.isPrivate).toBe(false);

        // Encrypt a message to the public key, then decrypt with the private key.
        const op = await openpgp();
        const pub = await op.readKey({ armoredKey: kp.publicKey });
        const armored = await op.encrypt({
            message: await op.createMessage({ text: 'secret body' }),
            encryptionKeys: pub,
            format: 'armored',
        });
        const out = await decrypt(armored, [{ privateKey: kp.privateKey }]);
        expect(out.text).toBe('secret body');
    }, 30000);

    it('generates across the full spectrum: multi-UID, expiry, signing subkey', async () => {
        const kp = await generateKey({
            type: 'ecc',
            curve: 'nistP256',
            userIDs: [{ name: 'Primary', email: 'a@example.com' }, { name: 'Alt', email: 'b@example.com' }],
            keyExpirationSeconds: 31536000,
            signSubkey: true,
        });
        const op = await openpgp();
        const key = await op.readKey({ armoredKey: kp.publicKey });
        // Two identities present.
        const uids = key.getUserIDs();
        expect(uids.some((u) => u.includes('a@example.com'))).toBe(true);
        expect(uids.some((u) => u.includes('b@example.com'))).toBe(true);
        // Expiration set (a Date, not Infinity).
        const exp = await key.getExpirationTime();
        expect(exp instanceof Date).toBe(true);
        // At least two subkeys (encryption + the extra signing subkey).
        expect(key.getSubkeys().length).toBeGreaterThanOrEqual(2);
    }, 30000);

    it('generates an RSA-2048 key', async () => {
        const kp = await generateKey({ type: 'rsa', rsaBits: 2048, userIDs: [{ email: 'rsa@example.com' }] });
        expect(kp.privateKey).toContain('BEGIN PGP PRIVATE KEY');
        expect(kp.fingerprint).toMatch(/^[0-9a-f]{40}$/);
    }, 60000);
});
