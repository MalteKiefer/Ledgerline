/**
 * Shard content-crypto cross-boundary equivalence: a passwords/notes record-shard
 * blob sealed by the WEB app (vault.js encryptContentWith) MUST open in the EXTENSION
 * (content-crypto.js decryptContent) and vice versa — otherwise the extension would
 * corrupt or fail to read the sharded passwords store. Guards the ZK crypto contract
 * for §3b's extension port, exactly like the padme/canonical cross-boundary tests.
 */
import { describe, it, expect, beforeAll } from 'vitest';
import _sodium from 'libsodium-wrappers-sumo';
import { Vault, VaultShareCrypto } from '../vault';
import { encryptContent, decryptContent } from '../../../extension/src/content-crypto.js';

let sodium;
beforeAll(async () => {
    await _sodium.ready;
    sodium = _sodium;
    await VaultShareCrypto.ready(); // initialise vault.js's shared libsodium
});

const vk = () => sodium.randombytes_buf(sodium.crypto_secretbox_KEYBYTES);
const dec = (b) => new TextDecoder().decode(b);

describe('shard content crypto (web vault.js <-> extension)', () => {
    it('extension seals a shard, vault.js opens it', async () => {
        const key = vk();
        const bytes = new TextEncoder().encode(JSON.stringify([{ id: 'a', title: 'secret', password: 'hunter2' }]));
        const { blob, encFileKey } = await encryptContent(bytes, key);
        const out = Vault.decryptFileWith(blob, encFileKey, key);
        expect(dec(out)).toBe(dec(bytes));
    });

    it('vault.js seals a shard, the extension opens it', async () => {
        const key = vk();
        const bytes = new TextEncoder().encode('x'.repeat(5000));
        const enc = Vault.encryptContentWith(bytes, { name: 'shard.enc', mime: 'application/octet-stream' }, key);
        const buf = new Uint8Array(await enc.blob.arrayBuffer());
        const out = await decryptContent(buf, enc.encFileKey, key);
        expect(dec(out)).toBe(dec(bytes));
    });

    it('extension round-trips a larger blob (multi-chunk-safe framing)', async () => {
        const key = vk();
        const bytes = sodium.randombytes_buf(200000);
        const { blob, encFileKey } = await encryptContent(bytes, key);
        const out = await decryptContent(blob, encFileKey, key);
        expect(Array.from(out)).toEqual(Array.from(bytes));
    });

    it('a wrong vault key fails closed', async () => {
        const bytes = new TextEncoder().encode('secret');
        const { blob, encFileKey } = await encryptContent(bytes, vk());
        await expect(decryptContent(blob, encFileKey, vk())).rejects.toThrow();
    });
});
