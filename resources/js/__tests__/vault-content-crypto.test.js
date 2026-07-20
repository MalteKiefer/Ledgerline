import { beforeAll, describe, expect, it } from 'vitest';
import _sodium from 'libsodium-wrappers-sumo';
import { Vault } from '../vault.js';

let sodium;
beforeAll(async () => { await _sodium.ready; sodium = _sodium; await Vault.ready(); });

function freshVk() { return sodium.randombytes_buf(sodium.crypto_secretbox_KEYBYTES); }

describe('content crypto with explicit vault key', () => {
  it('round-trips bytes under an arbitrary folder key', async () => {
    const vk = freshVk();
    const bytes = sodium.randombytes_buf(9000);
    const { blob, encFileKey } = Vault.encryptContentWith(bytes, { name: 'a.bin', mime: 'application/octet-stream' }, vk);
    const buf = new Uint8Array(await blob.arrayBuffer());
    const out = Vault.decryptFileWith(buf, encFileKey, vk);
    expect(out).toEqual(bytes);
  });

  it('a key wrapped under folder VK cannot be opened with a different VK', async () => {
    const vk = freshVk();
    const other = freshVk();
    const { blob, encFileKey } = Vault.encryptContentWith(new Uint8Array([1, 2, 3]), { name: 'x', mime: 'text/plain' }, vk);
    const buf = new Uint8Array(await blob.arrayBuffer());
    expect(() => Vault.decryptFileWith(buf, encFileKey, other)).toThrow();
  });

  it('personal delegate equals the *With path for the same key', async () => {
    const vk = freshVk();
    Vault.vk = vk; // personal path uses this.vk
    const bytes = sodium.randombytes_buf(5000);
    const viaWith = Vault.encryptContentWith(bytes, { name: 'n', mime: 'application/octet-stream' }, vk);
    // decrypt the *With ciphertext through the personal decryptFile (this.vk == vk)
    const buf = new Uint8Array(await viaWith.blob.arrayBuffer());
    expect(Vault.decryptFile(buf, viaWith.encFileKey)).toEqual(bytes);
    Vault.vk = null;
  });
});
