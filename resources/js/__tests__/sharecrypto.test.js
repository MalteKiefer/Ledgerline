import { describe, it, expect } from 'vitest';
import { ShareCrypto } from '../vault';

describe('ShareCrypto', () => {
    it('wraps and unwraps a key round-trip', async () => {
        const sk = await ShareCrypto.newKey();
        const fk = new Uint8Array(32).map((_, i) => (i * 7) & 0xff);
        const sealed = await ShareCrypto.wrap(fk, sk);
        const back = await ShareCrypto.unwrap(sealed, sk);
        expect(Array.from(back)).toEqual(Array.from(fk));
    });

    it('produces a distinct 32-byte base64 key each time', async () => {
        const a = await ShareCrypto.newKey();
        const b = await ShareCrypto.newKey();
        expect(a).not.toBe(b);
        expect(atob(a).length).toBe(32);
    });

    it('fails to unwrap with the wrong share key', async () => {
        const sk = await ShareCrypto.newKey();
        const other = await ShareCrypto.newKey();
        const sealed = await ShareCrypto.wrap(new Uint8Array(32), sk);
        await expect(ShareCrypto.unwrap(sealed, other)).rejects.toThrow();
    });

    it('round-trips arbitrary bytes (used for the sealed manifest)', async () => {
        const sk = await ShareCrypto.newKey();
        const msg = new TextEncoder().encode(JSON.stringify({ name: 'Album', photos: [1, 2, 3] }));
        const sealed = await ShareCrypto.wrap(msg, sk);
        const back = await ShareCrypto.unwrap(sealed, sk);
        expect(new TextDecoder().decode(back)).toBe('{"name":"Album","photos":[1,2,3]}');
    });
});
