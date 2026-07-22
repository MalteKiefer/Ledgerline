/**
 * Store v3 cross-client conformance (spec §17) — the web repo OWNS these fixtures;
 * iOS / Go CLI / Android consume the same JSON vectors in their own suites.
 *
 * Covers the contract primitives that must be byte/standard-identical across all
 * four clients before any of them may write a real library:
 *   - Canonical JSON (§5.2)         → exact bytes from the shared fixture.
 *   - Shard bucketing + hash (§5.1) → id → bucket, records → SHA-256.
 *   - Hybrid PQ-KEM (§6.3)          → ML-KEM-768 FIPS-203 KAT + wrap/unwrap round-trip.
 *   - `sig` (§6.5 / §4.1)           → file bytes → dedup signature string.
 *   - Blob-frame + Padmé (§17)      → frame layout / size + Padmé bucket + round-trip.
 *   - Share manifest (§17)          → album → sealed under a fixed share key sk.
 *
 * Byte-level Padmé cross-boundary equivalence (web ↔ extension) additionally lives
 * in padme-cross-boundary.test.js.
 */
import { describe, it, expect, beforeAll } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import { canonicalJSON, dec6 } from '../shared/canonical-json.js';
import { bucketOf, bucketize, shardHash, shardCount, recommendedShardBits } from '../shared/shard.js';
import { mlkemKeypair, hybridWrap, hybridUnwrap } from '../shared/pq-kem.js';
import { fileSig, SIG_CAP } from '../shared/file-sig.js';
import { padmeSize } from '../shared/padme.js';
import { Vault, ShareCrypto } from '../vault.js';
import { ml_kem768 } from '@noble/post-quantum/ml-kem.js';
import { sha256 } from '@noble/hashes/sha2.js';
import sodium from 'libsodium-wrappers-sumo';

const here = dirname(fileURLToPath(import.meta.url));
const fx = (name) => JSON.parse(readFileSync(join(here, 'fixtures/store-v3', name), 'utf8'));
const hex = (u) => [...u].map((b) => b.toString(16).padStart(2, '0')).join('');
const unhex = (s) => Uint8Array.from(s.match(/../g).map((h) => parseInt(h, 16)));

// Pure LCG byte generator — deterministic (no Date.now/random, per repo fixture
// rules). MUST match the generator that produced sig.json's expected values.
function lcgBytes(n, seed) {
    const out = new Uint8Array(n);
    let s = seed >>> 0;
    for (let i = 0; i < n; i++) { s = (s * 1664525 + 1013904223) >>> 0; out[i] = s & 0xff; }
    return out;
}

describe('canonical JSON (§5.2)', () => {
    const cases = fx('canonical-json.json');
    it.each(cases)('$name', ({ input, expected }) => {
        expect(canonicalJSON(input)).toBe(expected);
    });

    it('does not Unicode-normalize strings (decomposed != composed)', () => {
        // "café" written decomposed (e + U+0301 combining acute) must NOT be
        // folded to the precomposed form (é U+00E9). Byte-stability across
        // JS/Swift/Go/Kotlin (spec §5.2) requires strings pass through as-is.
        const decomposed = 'café';
        const composed = 'café';
        expect(decomposed).not.toBe(composed); // sanity: distinct code points

        const out = canonicalJSON({ w: decomposed });
        expect(out).toBe(`{"w":"${decomposed}"}`);
        expect(out).not.toBe(canonicalJSON({ w: composed }));
        expect(out).toContain('́'); // combining mark survived
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
        const { ek, seed } = await mlkemKeypair();

        const vk = sodium.randombytes_buf(32);
        const env = await hybridWrap(vk, xPub, ek, 'vault:conformance');
        expect(env.suite).toBe(1);
        expect(env).toHaveProperty('epk');
        expect(env).toHaveProperty('kem_ct');

        const out = await hybridUnwrap(env, xSk, seed, 'vault:conformance');
        expect(hex(out)).toBe(hex(vk));
    });

    it('fails closed on wrong context and unknown suite', async () => {
        await sodium.ready;
        const x = sodium.crypto_box_keypair();
        const xPub = sodium.to_base64(x.publicKey, sodium.base64_variants.ORIGINAL);
        const xSk = sodium.to_base64(x.privateKey, sodium.base64_variants.ORIGINAL);
        const { ek, seed } = await mlkemKeypair();
        const env = await hybridWrap(sodium.randombytes_buf(32), xPub, ek, 'ctx-a');

        await expect(hybridUnwrap(env, xSk, seed, 'ctx-b')).rejects.toThrow();
        await expect(hybridUnwrap({ ...env, suite: 2 }, xSk, seed, 'ctx-a')).rejects.toThrow(/suite/);
    });
});

describe('dedup signature `sig` (§6.5 / §4.1)', () => {
    const fixture = fx('sig.json');

    it.each(fixture.cases)('$name', async ({ size, seed, expected }) => {
        const bytes = lcgBytes(size, seed);
        expect(await fileSig(bytes)).toBe(expected);
    });

    it('format is "{size}:{sha256hex}" with 64 lowercase hex chars', async () => {
        const sig = await fileSig(lcgBytes(500, 7));
        expect(sig).toMatch(/^500:[0-9a-f]{64}$/);
    });

    it('empty buffer hashes the empty string (SHA-256 KAT)', async () => {
        // e3b0c442… is the well-known SHA-256 of zero bytes — head+tail are both
        // empty, so the signature must pin to it.
        expect(await fileSig(new Uint8Array(0)))
            .toBe('0:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
    });

    it('at/below the 1 MiB cap the tail is empty (no overlap re-hash)', async () => {
        // A <= cap buffer must hash exactly head=[0,size) with an EMPTY tail; if a
        // client mistakenly appended an overlapping tail the digest would differ.
        const size = SIG_CAP; // exactly 1 MiB → size NOT > cap → tail empty
        const bytes = lcgBytes(size, 3);
        const expectDig = hex(sha256(bytes)); // head only, no tail
        expect(await fileSig(bytes)).toBe(`${size}:${expectDig}`);
    });

    it('above the cap concatenates head‖tail (disjoint) before hashing', async () => {
        const size = SIG_CAP * 2 + 4096; // head and tail disjoint
        const bytes = lcgBytes(size, 5);
        const head = bytes.subarray(0, SIG_CAP);
        const tail = bytes.subarray(size - SIG_CAP);
        const cat = new Uint8Array(head.length + tail.length);
        cat.set(head, 0); cat.set(tail, head.length);
        expect(await fileSig(bytes)).toBe(`${size}:${hex(sha256(cat))}`);
    });
});

describe('blob-frame layout + Padmé (§17 blob crypto)', () => {
    const fixture = fx('blob-frame.json');
    let vkBytes;

    beforeAll(async () => {
        await sodium.ready;
        await Vault.ready(); // load the module-level sodium inside vault.js
        vkBytes = sodium.randombytes_buf(sodium.crypto_secretbox_KEYBYTES);
    });

    it('secretstream/suite constants match the fixture', () => {
        expect(sodium.crypto_secretstream_xchacha20poly1305_HEADERBYTES).toBe(fixture.HEADERBYTES);
        expect(sodium.crypto_secretstream_xchacha20poly1305_ABYTES).toBe(fixture.ABYTES);
    });

    it.each(fixture.frames)(
        'plaintext len $plaintextLen → frame size $frameSize, Padmé bucket $padmeSize',
        ({ plaintextLen, frameSize, padmeSize: padded }) => {
            expect(Vault.ciphertextSize(plaintextLen)).toBe(frameSize);
            expect(padmeSize(plaintextLen)).toBe(padded);
            // frameSize is HEADERBYTES + plaintextLen + chunks*(ABYTES+4).
            const CHUNK = fixture.CHUNK;
            const chunks = plaintextLen === 0 ? 1 : Math.ceil(plaintextLen / CHUNK);
            expect(frameSize).toBe(fixture.HEADERBYTES + plaintextLen + chunks * (fixture.ABYTES + 4));
        },
    );

    it('a real encrypt→decrypt round-trips and the frame starts with a HEADERBYTES header', async () => {
        // Nonce + per-file key are random, so raw frame bytes are not pinnable; we
        // assert the deterministic structure (size + a decodable header) and the
        // round-trip under a known key.
        const plaintext = lcgBytes(1000, 11);
        const { blob, encFileKey } = Vault.encryptContentWith(plaintext, { name: 'x', mime: 'application/octet-stream' }, vkBytes);
        const frame = new Uint8Array(await blob.arrayBuffer());
        expect(frame.length).toBe(Vault.ciphertextSize(plaintext.length));

        // Frame = header(HEADERBYTES) ‖ u32le(cipherLen) ‖ cipher; single chunk here.
        const H = fixture.HEADERBYTES;
        const cipherLen = frame[H] | (frame[H + 1] << 8) | (frame[H + 2] << 16) | (frame[H + 3] << 24);
        expect(cipherLen).toBe(plaintext.length + fixture.ABYTES);

        const out = Vault.decryptFileWith(frame, encFileKey, vkBytes);
        expect(hex(out)).toBe(hex(plaintext));
    });

    it('the sealed-manifest envelope carries suite=1 and is Padmé-padded', () => {
        // sealManifest reads this.vk; call it bound to a stand-in with a known key.
        const sealed = Vault.sealManifest.call({ vk: vkBytes, _padme: (n) => padmeSize(n) }, { hello: 'world' });
        const env = JSON.parse(sealed);
        expect(env.suite).toBe(fixture.suite);
        // padded plaintext = secretbox ciphertext − 16-byte MAC; small → 4096 floor.
        const cipher = sodium.from_base64(env.c, sodium.base64_variants.ORIGINAL);
        expect(cipher.length - sodium.crypto_secretbox_MACBYTES).toBe(4096);
    });
});

describe('share manifest under a fixed sk (§17)', () => {
    const fixture = fx('share-manifest.json');

    beforeAll(async () => { await sodium.ready; });

    it('the album serialises to the pinned canonical plaintext bytes', () => {
        const plaintext = JSON.stringify(fixture.manifest);
        expect(plaintext).toBe(fixture.plaintext);
        expect(hex(sodium.from_string(plaintext))).toBe(fixture.plaintextBytesHex);
    });

    it('the fixed sk decodes to the pinned raw key bytes', () => {
        const raw = sodium.from_base64(fixture.sk, sodium.base64_variants.ORIGINAL);
        expect(hex(raw)).toBe(fixture.skHex);
    });

    it('ShareCrypto.wrap→unwrap under sk round-trips the album plaintext', async () => {
        // secretbox uses a random nonce, so ciphertext bytes are not pinnable — the
        // cross-client contract is the sk-keyed format + a clean round-trip.
        const bytes = sodium.from_string(fixture.plaintext);
        const sealed = await ShareCrypto.wrap(bytes, fixture.sk);
        const wrapped = JSON.parse(sealed);
        expect(wrapped).toHaveProperty('c');
        expect(wrapped).toHaveProperty('n');

        const opened = await ShareCrypto.unwrap(sealed, fixture.sk);
        expect(sodium.to_string(opened)).toBe(fixture.plaintext);
        expect(JSON.parse(sodium.to_string(opened))).toEqual(fixture.manifest);
    });

    it('a wrong share key fails to unwrap (fail-closed)', async () => {
        const bytes = sodium.from_string(fixture.plaintext);
        const sealed = await ShareCrypto.wrap(bytes, fixture.sk);
        const wrongSk = sodium.to_base64(new Uint8Array(32).fill(0xff), sodium.base64_variants.ORIGINAL);
        await expect(ShareCrypto.unwrap(sealed, wrongSk)).rejects.toThrow();
    });
});
