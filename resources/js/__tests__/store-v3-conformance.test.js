/**
 * Store v3 cross-client conformance (spec §17) — the web repo OWNS these fixtures;
 * iOS / Go CLI / Android consume the same JSON vectors in their own suites.
 *
 * Covers the contract primitives that must be byte/standard-identical across all
 * four clients before any of them may write a real library:
 *   - Canonical JSON (§5.2)         → exact bytes from the shared fixture.
 *   - Shard bucketing + hash (§5.1) → id → bucket, records → SHA-256.
 *   - Hybrid PQ-KEM (§6.3)          → ML-KEM-768 FIPS-203 KAT + wrap/unwrap round-trip.
 *
 * Blob-frame/Padmé, `sig` and share-manifest fixtures are covered by the crypto/
 * store suites (padme-cross-boundary.test.js and the store phase).
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import { canonicalJSON, dec6 } from '../shared/canonical-json.js';
import { bucketOf, bucketize, shardHash, shardCount, recommendedShardBits } from '../shared/shard.js';
import { mlkemKeypair, hybridWrap, hybridUnwrap } from '../shared/pq-kem.js';
import { ml_kem768 } from '@noble/post-quantum/ml-kem.js';
import { sha256 } from '@noble/hashes/sha2.js';
import sodium from 'libsodium-wrappers-sumo';

const here = dirname(fileURLToPath(import.meta.url));
const fx = (name) => JSON.parse(readFileSync(join(here, 'fixtures/store-v3', name), 'utf8'));
const hex = (u) => [...u].map((b) => b.toString(16).padStart(2, '0')).join('');
const unhex = (s) => Uint8Array.from(s.match(/../g).map((h) => parseInt(h, 16)));

describe('canonical JSON (§5.2)', () => {
    const cases = fx('canonical-json.json');
    it.each(cases)('$name', ({ input, expected }) => {
        expect(canonicalJSON(input)).toBe(expected);
    });

    it('sorts keys by code point, not UTF-16 code unit', () => {
        // U+1F600 (astral) has a higher code point than "z" but a lower leading
        // surrogate; code-point order must place "z" before the astral key.
        const out = canonicalJSON({ '\u{1F600}': 1, z: 2 });
        expect(out).toBe('{"z":2,"\u{1F600}":1}');
    });

    it('dec6 renders fixed 6-dp decimal strings or null (no floats)', () => {
        expect(dec6(52.520008)).toBe('52.520008');
        expect(dec6('13.4')).toBe('13.400000');
        expect(dec6(null)).toBeNull();
        expect(dec6('')).toBeNull();
    });

    it('rejects non-finite numbers in sealed structures', () => {
        expect(() => canonicalJSON({ x: Infinity })).toThrow();
        expect(() => canonicalJSON({ x: NaN })).toThrow();
    });
});

describe('shard bucketing + hash (§5.1)', () => {
    it('bucket 0 for shardBits 0 (single shard; avoids the >>>32 trap)', () => {
        expect(bucketOf('ffffffffdeadbeef', 0)).toBe(0);
        expect(shardCount(0)).toBe(1);
    });

    it('takes the top shardBits of the hex prefix', () => {
        // prefix 0x80000000 → top bit set → bucket 1 at shardBits 1.
        expect(bucketOf('80000000aaaa', 1)).toBe(1);
        expect(bucketOf('7fffffffaaaa', 1)).toBe(0);
        // shardBits 4 → top nibble.
        expect(bucketOf('a1b2c3d4ffff', 4)).toBe(0xa);
    });

    it('is deterministic and independent of insertion order', () => {
        const recs = [{ id: 'a1000000x' }, { id: '01000000y' }, { id: 'f1000000z' }];
        const forward = bucketize(recs, 4);
        const reverse = bucketize([...recs].reverse(), 4);
        expect([...forward.keys()].sort()).toEqual([...reverse.keys()].sort());
        // within a bucket, records are id-sorted
        const mixed = bucketize([{ id: '10ff' }, { id: '1000' }, { id: '1055' }], 0);
        expect(mixed.get(0).map((r) => r.id)).toEqual(['1000', '1055', '10ff']);
    });

    it('shard hash is SHA-256 of canonical JSON of id-sorted records', async () => {
        const recs = [{ id: '1000', name: 'b' }, { id: '0fff', name: 'a' }];
        const sorted = [...recs].sort((a, b) => (a.id < b.id ? -1 : 1));
        const expected = hex(sha256(new TextEncoder().encode(canonicalJSON(sorted))));
        expect(await shardHash(sorted)).toBe(expected);
    });

    it('recommends shardBits keeping mean <= ~500/shard', () => {
        expect(recommendedShardBits(100)).toBe(0);
        expect(recommendedShardBits(600)).toBe(1);
        expect(recommendedShardBits(18000)).toBe(6); // 18000/64 ≈ 281
    });
});

describe('hybrid PQ-KEM (§6.3)', () => {
    it('reproduces the ML-KEM-768 FIPS-203 KAT (deterministic keygen + encaps)', () => {
        const kat = fx('mlkem768-kat.json');
        const kp = ml_kem768.keygen(unhex(kat.seed));
        expect(kp.publicKey.length).toBe(kat.ekLen);
        expect(kp.secretKey.length).toBe(kat.dkLen);
        expect(hex(sha256(kp.publicKey))).toBe(kat.ekSha256);
        expect(hex(sha256(kp.secretKey))).toBe(kat.dkSha256);

        const e = ml_kem768.encapsulate(kp.publicKey, unhex(kat.msgSeed));
        expect(e.cipherText.length).toBe(kat.ctLen);
        expect(hex(sha256(e.cipherText))).toBe(kat.ctSha256);
        expect(hex(e.sharedSecret)).toBe(kat.sharedSecret);

        // decaps interop: recovering the same shared secret closes the loop.
        expect(hex(ml_kem768.decapsulate(e.cipherText, kp.secretKey))).toBe(kat.sharedSecret);
    });

    it('wraps and unwraps a known VK (encaps/decaps interop)', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const xPub = sodium.to_base64(x.publicKey, sodium.base64_variants.ORIGINAL);
        const xSk = sodium.to_base64(x.privateKey, sodium.base64_variants.ORIGINAL);
        const { ek, dk } = await mlkemKeypair();

        const vk = sodium.randombytes_buf(32);
        const env = await hybridWrap(vk, xPub, ek, 'vault:conformance');
        expect(env.suite).toBe(1);
        expect(env).toHaveProperty('epk');
        expect(env).toHaveProperty('kem_ct');

        const out = await hybridUnwrap(env, xSk, dk, 'vault:conformance');
        expect(hex(out)).toBe(hex(vk));
    });

    it('fails closed on wrong context and unknown suite', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const xPub = sodium.to_base64(x.publicKey, sodium.base64_variants.ORIGINAL);
        const xSk = sodium.to_base64(x.privateKey, sodium.base64_variants.ORIGINAL);
        const { ek, dk } = await mlkemKeypair();
        const env = await hybridWrap(sodium.randombytes_buf(32), xPub, ek, 'ctx-a');

        await expect(hybridUnwrap(env, xSk, dk, 'ctx-b')).rejects.toThrow();
        await expect(hybridUnwrap({ ...env, suite: 2 }, xSk, dk, 'ctx-a')).rejects.toThrow(/suite/);
    });
});
