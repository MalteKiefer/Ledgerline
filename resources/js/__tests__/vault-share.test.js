import { describe, it, expect } from 'vitest';
import { VaultShareCrypto } from '../vault';

describe('VaultShareCrypto', () => {
    describe('wrap/unwrap round-trip', () => {
        it('recovers the vault key after wrap→unwrap with the same keypair', async () => {
            const id = await VaultShareCrypto.newIdentity();
            const vk = await VaultShareCrypto.newVaultKey();
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vk, id.pub);
            const recovered = await VaultShareCrypto.unwrapVaultKey(wrapped, id.pub, id.sk);
            expect(recovered).toBe(vk);
        });

        it('produces a wrapped ciphertext distinct from the plaintext key', async () => {
            const id = await VaultShareCrypto.newIdentity();
            const vk = await VaultShareCrypto.newVaultKey();
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vk, id.pub);
            expect(wrapped).not.toBe(vk);
            // Wrapped form is longer (adds seal overhead)
            expect(atob(wrapped).length).toBeGreaterThan(atob(vk).length);
        });
    });

    describe('fingerprint', () => {
        it('is deterministic: same public key always produces the same fingerprint', async () => {
            const id = await VaultShareCrypto.newIdentity();
            const fp1 = await VaultShareCrypto.fingerprint(id.pub);
            const fp2 = await VaultShareCrypto.fingerprint(id.pub);
            expect(fp1).toBe(fp2);
        });

        it('produces a 32-character lowercase hex string (16 bytes)', async () => {
            const id = await VaultShareCrypto.newIdentity();
            const fp = await VaultShareCrypto.fingerprint(id.pub);
            expect(fp).toHaveLength(32);
            expect(fp).toMatch(/^[0-9a-f]+$/);
        });

        it('produces different fingerprints for different public keys', async () => {
            const id1 = await VaultShareCrypto.newIdentity();
            const id2 = await VaultShareCrypto.newIdentity();
            const fp1 = await VaultShareCrypto.fingerprint(id1.pub);
            const fp2 = await VaultShareCrypto.fingerprint(id2.pub);
            expect(fp1).not.toBe(fp2);
        });
    });

    describe('wrong keypair cannot unwrap', () => {
        it('throws when a different keypair tries to unwrap', async () => {
            const owner = await VaultShareCrypto.newIdentity();
            const attacker = await VaultShareCrypto.newIdentity();
            const vk = await VaultShareCrypto.newVaultKey();
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vk, owner.pub);
            await expect(
                VaultShareCrypto.unwrapVaultKey(wrapped, attacker.pub, attacker.sk),
            ).rejects.toThrow();
        });
    });

    describe('sealVaultManifest / openVaultManifest', () => {
        it('round-trips an arbitrary object', async () => {
            const id = await VaultShareCrypto.newIdentity();
            // Derive a raw key bytes: unwrap a freshly wrapped key to get Uint8Array
            const vkB64 = await VaultShareCrypto.newVaultKey();
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vkB64, id.pub);
            const recoveredB64 = await VaultShareCrypto.unwrapVaultKey(wrapped, id.pub, id.sk);
            // Convert base64 vault key to raw bytes for sealVaultManifest
            // We need raw bytes — use atob + Uint8Array
            const vkBytes = Uint8Array.from(atob(recoveredB64), (c) => c.charCodeAt(0));

            const obj = { items: ['a', 'b', 'c'], count: 3, nested: { x: true } };
            const sealed = await VaultShareCrypto.sealVaultManifest(obj, vkBytes);
            const opened = await VaultShareCrypto.openVaultManifest(sealed, vkBytes);
            expect(opened).toEqual(obj);
        });

        it('applies the 4096-byte Padmé floor: two small objects seal to the same bucket length', async () => {
            const id = await VaultShareCrypto.newIdentity();
            const vkB64 = await VaultShareCrypto.newVaultKey();
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vkB64, id.pub);
            const recoveredB64 = await VaultShareCrypto.unwrapVaultKey(wrapped, id.pub, id.sk);
            const vkBytes = Uint8Array.from(atob(recoveredB64), (c) => c.charCodeAt(0));

            const small1 = { a: 1 };
            const small2 = { b: 2, c: [1, 2, 3] };

            const sealed1 = await VaultShareCrypto.sealVaultManifest(small1, vkBytes);
            const sealed2 = await VaultShareCrypto.sealVaultManifest(small2, vkBytes);

            // Both small objects hit the 4096-floor bucket; their ciphertext JSON
            // strings should be the same length (same padded plaintext size).
            expect(sealed1.length).toBe(sealed2.length);
        });

        it('the sealed ciphertext is at least 4096 bytes of plaintext', async () => {
            const id = await VaultShareCrypto.newIdentity();
            const vkB64 = await VaultShareCrypto.newVaultKey();
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vkB64, id.pub);
            const recoveredB64 = await VaultShareCrypto.unwrapVaultKey(wrapped, id.pub, id.sk);
            const vkBytes = Uint8Array.from(atob(recoveredB64), (c) => c.charCodeAt(0));

            const sealed = await VaultShareCrypto.sealVaultManifest({ tiny: true }, vkBytes);
            const { c } = JSON.parse(sealed);
            // The base64-decoded ciphertext is plaintext_len + MACBYTES (16) + NONCEBYTES (24)
            // At minimum plaintext is 4096 bytes padded
            const ciphertextBytes = atob(c).length;
            expect(ciphertextBytes).toBeGreaterThanOrEqual(4096);
        });
    });
});
