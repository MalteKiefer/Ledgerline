// Content-addressed, id-bucketed sharding (Store v3, spec §5.1).
//
// A record's shard bucket is derived purely from its id, so every client agrees on
// the bucket regardless of load/insert order — no array-position cascade, no
// cross-client thrash. Any edit touches exactly one bucket.
//
//   shardCount = 2^shardBits
//   bucket(id) = uint32(hexPrefix8(id)) >>> (32 - shardBits)   // top shardBits bits
//
// ids are client-generated 128-bit CSPRNG hex (see shared/sealed-store.js newId),
// giving an even, deterministic distribution.

import { canonicalJSON } from './canonical-json.js';

/** Number of shards for a given shardBits. */
export function shardCount(shardBits) {
    return 2 ** shardBits;
}

/**
 * The shard bucket index for a record id at a given shardBits.
 * @param {string} id  hex id (>= 8 hex chars)
 * @param {number} shardBits  0..32
 * @returns {number} bucket index in [0, 2^shardBits)
 */
export function bucketOf(id, shardBits) {
    if (shardBits <= 0) return 0; // single shard; avoids the JS `>>> 32 === >>> 0` trap
    const prefix = Number.parseInt(String(id).slice(0, 8), 16);
    if (! Number.isFinite(prefix)) return 0;

    // >>> 0 keeps it an unsigned 32-bit value; take the top `shardBits` bits.
    return (prefix >>> 0) >>> (32 - shardBits);
}

/**
 * Group records by bucket for a given shardBits, each bucket sorted ascending by id
 * (the canonical serialization order per §5.1). Returns a Map<bucket, records[]>.
 *
 * @param {Array<{id: string}>} records
 * @param {number} shardBits
 */
export function bucketize(records, shardBits) {
    const buckets = new Map();
    for (const r of records) {
        const b = bucketOf(r.id, shardBits);
        let arr = buckets.get(b);
        if (! arr) { arr = []; buckets.set(b, arr); }
        arr.push(r);
    }
    for (const arr of buckets.values()) {
        arr.sort((a, b) => (a.id < b.id ? -1 : a.id > b.id ? 1 : 0));
    }

    return buckets;
}

/**
 * The dirty-detection hash of a shard: SHA-256 over the canonical JSON of its
 * (id-sorted) records, returned as lowercase hex. An unchanged hash lets the caller
 * reuse the existing shard descriptor without re-uploading (§5.1).
 *
 * Uses WebCrypto SubtleCrypto (available in browsers and Node ≥ 15 via globalThis).
 *
 * @param {Array<object>} records  already id-sorted (bucketize does this)
 * @returns {Promise<string>} 64-char lowercase hex
 */
export async function shardHash(records) {
    const bytes = new TextEncoder().encode(canonicalJSON(records));
    const digest = await crypto.subtle.digest('SHA-256', bytes);

    return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Recommended shardBits for a record count: keep mean ≈ 250 records/shard, split
 * (bits += 1) when mean would exceed ~500. Start at 0 for small libraries (§5.1).
 *
 * @param {number} count
 * @returns {number} shardBits
 */
export function recommendedShardBits(count) {
    let bits = 0;
    while (count / (2 ** bits) > 500) bits += 1;

    return bits;
}
