// Sharded sealed-store client for the extension (merge-safety spec §3b). A faithful
// port of the web resources/js/shared/sharded-store.js write path, used for the
// passwords store so the extension and the web app read/write the SAME sharded layout
// (no split-brain). All crypto mirrors vault.js: the sealed root via sealManifest, the
// record-shard blobs via content-crypto (byte-identical, proven by the cross-boundary
// test). On a 409 it record-merges (mergeArrayById) so a concurrent writer's records
// in the same shard survive.
import { sealManifest, openManifest } from './crypto.js';
import { encryptContent, decryptContent } from './content-crypto.js';
import { canonicalJSON } from './canonical-json.js';
import { bucketize, shardHash, recommendedShardBits } from './shard.js';
import { mergeArrayById } from './manifest-merge.js';
import * as api from './api.js';

const clone = (v) => (v == null ? v : structuredClone(v));

// Fetch + decrypt the current server root into { version, data, shards, shardBits,
// collDesc }. `collections` = [{ key, rootRef, rootKey, rootHash }].
async function fetchState(base, token, prefix, recordKey, collections, vk) {
    const d = await api.getShardedRoot(base, token, prefix);
    const blank = { v: 3, [recordKey]: [] };
    for (const c of collections) blank[c.key] = [];
    const state = { version: d.version ?? 0, data: blank, shards: [], shardBits: 0, collDesc: {} };
    if (! d.ciphertext) return state;
    const root = await openManifest(d.ciphertext, vk);
    if (! (root.v === 3 && Array.isArray(root.shards))) return state;
    state.shardBits = root.shardBits ?? 0;
    const parts = await Promise.all(root.shards.map(async (s) => {
        const bytes = await api.rawShardBlob(base, token, prefix, s.ref);
        const plain = await decryptContent(bytes, s.key, vk);
        const arr = JSON.parse(new TextDecoder().decode(plain));
        return Array.isArray(arr) ? arr : [];
    }));
    const records = [];
    for (const arr of parts) records.push(...arr);
    state.data[recordKey] = records;
    for (const c of collections) {
        if (root[c.rootRef]) {
            const bytes = await api.rawShardBlob(base, token, prefix, root[c.rootRef]);
            const plain = await decryptContent(bytes, root[c.rootKey], vk);
            const arr = JSON.parse(new TextDecoder().decode(plain));
            state.data[c.key] = Array.isArray(arr) ? arr : [];
            state.collDesc[c.key] = { ref: root[c.rootRef], key: root[c.rootKey], hash: root[c.rootHash] };
        }
    }
    state.shards = root.shards.map((s) => ({ ...s }));
    return state;
}

// Seal raw bytes into a shard/collection blob → { ref, key } (never eager-deletes a
// superseded blob — the grace-gated server reconcile reclaims it).
async function sealBlob(base, token, prefix, bytes, vk) {
    const enc = await encryptContent(bytes, vk);
    const { id } = await api.uploadShardBlob(base, token, prefix, enc.blob);
    return { ref: id, key: enc.encFileKey };
}

// Build the small root manifest from the records + collections, re-sealing only the
// shards/collections whose canonical bytes changed. Mirrors web _buildRoot.
async function buildRoot(base, token, prefix, recordKey, collections, data, shards, collDesc, vk) {
    const records = data[recordKey] || [];
    const shardBits = recommendedShardBits(records.length);
    const rebucket = shardBits !== (shards._bits ?? 0);
    const buckets = bucketize(records, shardBits);
    const prevByBucket = new Map((shards.list || []).map((s) => [s.bucket, s]));
    const descriptors = [];
    for (const [bucket, recs] of buckets) {
        const hash = await shardHash(recs);
        const prev = rebucket ? null : prevByBucket.get(bucket);
        if (prev && prev.hash === hash && prev.ref) {
            descriptors.push({ ...prev, count: recs.length });
        } else {
            const sealed = await sealBlob(base, token, prefix, new TextEncoder().encode(canonicalJSON(recs)), vk);
            descriptors.push({ ref: sealed.ref, key: sealed.key, hash, count: recs.length, bucket });
        }
    }
    const root = { v: 3, suite: 1, shardBits, shards: descriptors.map(({ ref, key, hash, count, bucket }) => ({ ref, key, hash, count, bucket })), caps: {} };
    const nextColl = {};
    for (const c of collections) {
        const arr = data[c.key] || [];
        if (! arr.length) { nextColl[c.key] = null; continue; }
        const hash = await shardHash(arr);
        const prev = collDesc[c.key];
        const desc = (prev && prev.hash === hash && prev.ref)
            ? prev
            : { ...(await sealBlob(base, token, prefix, new TextEncoder().encode(canonicalJSON(arr)), vk)), hash };
        nextColl[c.key] = desc;
        root[c.rootRef] = desc.ref; root[c.rootKey] = desc.key; root[c.rootHash] = desc.hash;
    }
    const refs = descriptors.map((s) => s.ref).concat(Object.values(nextColl).filter(Boolean).map((d) => d.ref));
    return { root, descriptors, shardBits, nextColl, refs };
}

/**
 * Read the sharded store, apply the mutation, and write it back with the sharded
 * layout + optimistic-concurrency 409 rebase (record-level merge). fn(manifest)
 * mutates a manifest shaped { [recordKey]: [], <collections> }.
 *
 * @returns {Promise<{result: any, data: object}>} the fn() return value + final manifest.
 */
export async function mutateSharded(base, token, prefix, recordKey, collections, vk, fn) {
    const state = await fetchState(base, token, prefix, recordKey, collections, vk);
    let base0 = { [recordKey]: clone(state.data[recordKey]) };
    for (const c of collections) base0[c.key] = clone(state.data[c.key]);
    const result = fn(state.data);

    for (let attempt = 0; attempt < 8; attempt++) {
        const built = await buildRoot(base, token, prefix, recordKey, collections, state.data,
            { list: state.shards, _bits: state.shardBits }, state.collDesc, vk);
        const ciphertext = await sealManifest(built.root, vk);
        const res = await api.saveShardedRoot(base, token, prefix, ciphertext, state.version, built.refs);
        if (res.ok) {
            const body = await res.json().catch(() => ({}));
            state.version = body.version ?? state.version + 1;
            state.shards = built.descriptors;
            state.shardBits = built.shardBits;
            state.collDesc = built.nextColl;
            return { result: result ?? { ok: true }, data: state.data };
        }
        if (res.status === 409) {
            const server = await fetchState(base, token, prefix, recordKey, collections, vk);
            // Rebase: replay only our delta (base -> current) onto the winning records.
            const merged = mergeArrayById(base0[recordKey] || [], state.data[recordKey] || [], server.data[recordKey] || []);
            state.data[recordKey].splice(0, state.data[recordKey].length, ...merged);
            for (const c of collections) {
                if (! Array.isArray(state.data[c.key])) state.data[c.key] = [];
                const mc = mergeArrayById(base0[c.key] || [], state.data[c.key], server.data[c.key] || []);
                state.data[c.key].splice(0, state.data[c.key].length, ...mc);
            }
            state.version = server.version;
            state.shards = server.shards;
            state.shardBits = server.shardBits;
            state.collDesc = server.collDesc;
            base0 = { [recordKey]: clone(state.data[recordKey]) };
            for (const c of collections) base0[c.key] = clone(state.data[c.key]);
            continue;
        }
        throw new Error('sharded save failed: ' + res.status);
    }
    throw new Error('sharded save conflict');
}

/** Read-only load of the sharded store (for the extension's password list). */
export async function loadSharded(base, token, prefix, recordKey, collections, vk) {
    const state = await fetchState(base, token, prefix, recordKey, collections, vk);
    return state.data;
}

/** The passwords store shape (secrets shards + secretFolders collection). */
export const PASSWORDS = {
    prefix: 'passwords',
    recordKey: 'secrets',
    collections: [{ key: 'secretFolders', rootRef: 'foldersRef', rootKey: 'foldersKey', rootHash: 'foldersHash' }],
};
