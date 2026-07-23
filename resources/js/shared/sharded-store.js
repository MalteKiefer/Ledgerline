// Content-addressed sharded sealed-store engine (Store v3 §4.1/§4.2/§5.1).
//
// LLGalleryStore and LLFilesStore are the same engine: a small sealed ROOT
// pointer table plus content-addressed, id-BUCKETED record shards (bucket derived
// from the record id, not its array position → no cascade, cross-client stable),
// plus one or more sibling COLLECTION blobs (gallery: albums + people; files:
// fileFolders). A save re-seals only the buckets whose canonical-JSON hash
// changed, plus any changed collection blobs, plus the tiny root. No v1/v2 paths.
//
// makeShardedStore({ prefix, recordKey, collections }) returns a store object
// whose public surface is IDENTICAL to the hand-written stores it replaces:
//   .data .version .ready .loaded .load() .touch() .flush() .reset() .newId()
//   .shardRefs() ._onError ._shards ._shardBits ._blank()
//
// `collections` is a declarative array, e.g. for the gallery:
//   [{ key:'albums', rootRef:'albumsRef', rootKey:'albumsKey', rootHash:'albumsHash' },
//    { key:'people', rootRef:'peopleRef', rootKey:'peopleKey', rootHash:'peopleHash' }]
// and for files: [{ key:'fileFolders', rootRef:'foldersRef', rootKey:'foldersKey', rootHash:'foldersHash' }].
//
// All crypto stays in window.Vault (via the injected helpers), exactly as before.

import { csrfToken, jsonHeaders, getJson } from './api';
import { newId as _newId } from './sealed-store';
import { fetchDecryptWorker, queueBlobDelete } from './blob-io';
import { padBlob } from './padme';
import { bucketize, shardHash, recommendedShardBits } from './shard';
import { canonicalJSON } from './canonical-json';

export function makeShardedStore({ prefix, recordKey, collections }) {
    return {
        data: null,
        version: 0,
        ready: false,
        loaded: false,
        _timer: null,
        _chain: null,
        _queued: false,
        _onError: null,
        _shardBits: 0,
        _shards: [], // [{ ref, key, hash, count, bucket }] descriptors from the last load/save
        _collDesc: {}, // { <collection.key>: { ref, key, hash } | null } for each collection blob
        degraded: false, // true when load() had to skip a permanently-missing (404) shard
        _missingShards: 0, // count of shards dropped during a degraded load

        _blank() {
            const b = { v: 3, [recordKey]: [] };
            for (const c of collections) b[c.key] = [];
            return b;
        },

        // Every live blob ref the reconcile MUST keep alive (§11): the record
        // shards AND every collection blob. A missing class here = data loss on
        // the next orphan sweep.
        shardRefs() {
            const refs = this._shards.map((s) => s.ref).filter(Boolean);
            for (const c of collections) {
                const d = this._collDesc[c.key];
                if (d?.ref) refs.push(d.ref);
            }
            return refs;
        },

        // Load a content-addressed collection blob → array (or []).
        async _loadCollection(ref, key) {
            if (! ref) return [];
            const b = await fetchDecryptWorker(prefix + '/raw', ref, key);
            const arr = JSON.parse(new TextDecoder().decode(b));
            return Array.isArray(arr) ? arr : [];
        },

        // Seal a collection array into its own content-addressed blob, reusing the
        // previous blob when the canonical bytes are unchanged; frees a replaced blob.
        async _buildCollection(arr, prev) {
            if (! arr.length) {
                if (prev?.ref) queueBlobDelete(prefix + '/blob/' + prev.ref, csrfToken());
                return null;
            }
            const hash = await shardHash(arr);
            if (prev && prev.hash === hash && prev.ref) return prev;
            const sealed = await this._sealBlob(new TextEncoder().encode(canonicalJSON(arr)));
            if (prev?.ref) queueBlobDelete(prefix + '/blob/' + prev.ref, csrfToken());
            return { ref: sealed.ref, key: sealed.key, hash };
        },

        // Seal raw bytes into a padded, content-addressed blob → { ref, key }.
        async _sealBlob(bytes) {
            const enc = window.Vault.encryptContent(bytes, { name: 'shard.enc', mime: 'application/octet-stream' });
            const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
            const fd = new FormData();
            fd.append('_token', csrfToken());
            fd.append('file', cipher, cipher.name);
            const res = await fetch(prefix + '/upload', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            if (! res.ok) throw new Error('shard upload failed');
            return { ref: (await res.json()).id, key: enc.encFileKey };
        },

        newId() { return _newId(); },

        async load() {
            const d = await getJson(prefix + '/store');
            this.version = d.version ?? 0;
            this._shards = [];
            this._collDesc = {};
            this._shardBits = 0;
            const root = d.ciphertext ? window.Vault.openManifest(d.ciphertext) : this._blank();

            // v3 only (clean slate — no v1/v2 read paths). Anything else = fresh store.
            if (root.v === 3 && Array.isArray(root.shards)) {
                this._shardBits = root.shardBits ?? 0;
                this.degraded = false;
                this._missingShards = 0;
                // Load + decrypt every record shard in parallel (immutable blob cache
                // makes repeats instant). A shard that is PERMANENTLY gone (HTTP 404 —
                // its blob was lost, e.g. a partial save that stored the root pointer
                // but never landed the shard upload) is skipped and the store enters a
                // `degraded` state: its records are unrecoverable anyway, so dropping
                // them loses nothing new, and the next save re-seals the root WITHOUT
                // the dead ref (self-heal). Any OTHER failure (429/network/decrypt)
                // still THROWS — that shard might recover, and saving a partial set
                // would free it and lose data for good.
                const kept = [];
                const parts = await Promise.all(root.shards.map((s) => fetchDecryptWorker(prefix + '/raw', s.ref, s.key)
                    .then((b) => { kept.push(s); return JSON.parse(new TextDecoder().decode(b)); })
                    .catch((e) => {
                        if (e && e.status === 404) { this.degraded = true; this._missingShards++; return null; }
                        throw e;
                    })));
                const records = [];
                for (const arr of parts) if (Array.isArray(arr)) records.push(...arr);
                const data = { v: 3, [recordKey]: records };
                for (const c of collections) {
                    const arr = await this._loadCollection(root[c.rootRef], root[c.rootKey]);
                    data[c.key] = arr;
                    this._collDesc[c.key] = root[c.rootRef] ? { ref: root[c.rootRef], key: root[c.rootKey], hash: root[c.rootHash] } : null;
                }
                this.data = data;
                // Only the shards we actually loaded survive into _shards, so the next
                // save's root omits the dead ones and shardRefs() never keeps them.
                this._shards = kept.map((s) => ({ ...s }));
            } else {
                this.data = this._blank();
            }

            this.loaded = true;
            this.ready = true;
            return this.data;
        },

        reset() {
            this.data = null; this.version = 0; this.ready = false; this.loaded = false;
            this._shards = []; this._collDesc = {}; this._shardBits = 0;
            this.degraded = false; this._missingShards = 0; clearTimeout(this._timer);
        },

        touch() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.flush(), 800);
        },

        // Serialised, awaitable, COALESCING save. Callers can `await flush()` and be
        // sure the CURRENT data was persisted. While a save is in flight, extra
        // flush() calls collapse into a single queued save (each _doFlush always
        // seals the latest in-memory data), so a burst of edits doesn't queue dozens
        // of racing PUTs that fight over the version counter and exhaust the 409
        // retry budget.
        flush() {
            if (! this.loaded) return Promise.resolve();
            if (this._queued) return this._chain; // a save is already scheduled after the running one
            this._queued = true;
            this._chain = (this._chain || Promise.resolve())
                .catch(() => {})
                .then(() => { this._queued = false; return this._doFlush(); })
                .catch(() => {});
            return this._chain;
        },

        // Split records into shards, (re-)seal only the ones whose contents changed,
        // free shards that vanished, and return the small root manifest. Buckets stay
        // stable for the common cases (append new / edit in place); only a mid-array
        // purge or a bits change cascades, which is rare.
        async _buildRoot() {
            const records = this.data[recordKey] || [];
            // Grow buckets to keep the mean shard small; a bits change re-buckets the
            // whole set (one-time, free under clean slate).
            const shardBits = recommendedShardBits(records.length);
            const rebucket = shardBits !== this._shardBits;
            const buckets = bucketize(records, shardBits); // Map<bucket, id-sorted records>
            const prevByBucket = new Map(this._shards.map((s) => [s.bucket, s]));

            const descriptors = [];
            for (const [bucket, recs] of buckets) {
                const hash = await shardHash(recs);
                const prev = rebucket ? null : prevByBucket.get(bucket);
                if (prev && prev.hash === hash && prev.ref) {
                    descriptors.push({ ...prev, count: recs.length }); // unchanged → reuse blob
                } else {
                    const sealed = await this._sealBlob(new TextEncoder().encode(canonicalJSON(recs)));
                    descriptors.push({ ref: sealed.ref, key: sealed.key, hash, count: recs.length, bucket });
                }
            }
            // Free shard blobs no longer referenced (shrunk/re-bucketed/changed).
            const live = new Set(descriptors.map((d) => d.ref));
            for (const old of this._shards) if (old.ref && ! live.has(old.ref)) queueBlobDelete(prefix + '/blob/' + old.ref, csrfToken());
            this._shards = descriptors;
            this._shardBits = shardBits;

            const root = {
                v: 3,
                suite: 1,
                shardBits,
                shards: descriptors.map(({ ref, key, hash, count, bucket }) => ({ ref, key, hash, count, bucket })),
                caps: {},
            };

            // Each collection as its own content-addressed collection blob.
            for (const c of collections) {
                const desc = await this._buildCollection(this.data[c.key] || [], this._collDesc[c.key]);
                this._collDesc[c.key] = desc;
                if (desc) { root[c.rootRef] = desc.ref; root[c.rootKey] = desc.key; root[c.rootHash] = desc.hash; }
            }
            return root;
        },

        async _doFlush(retry = 0) {
            if (! this.loaded || ! this.data) return;
            try {
                const root = await this._buildRoot();
                const body = JSON.stringify({ ciphertext: window.Vault.sealManifest(root), version: this.version });
                const res = await fetch(prefix + '/store', { method: 'PUT', headers: jsonHeaders(), body });
                if (res.status === 409) {
                    // Another writer (e.g. the background ML pass, or a second tab)
                    // advanced the version. Adopt it and re-seal our data (this tab holds
                    // the authoritative in-memory copy). Back off a touch so a burst of
                    // concurrent flushes doesn't livelock.
                    const cur = await fetch(prefix + '/store', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
                    this.version = cur.version ?? this.version;
                    if (retry < 8) { await new Promise((r) => setTimeout(r, Math.min(120 * 2 ** retry, 2000))); return this._doFlush(retry + 1); }
                    throw new Error('store save conflict');
                } else if (res.status === 429 && retry < 8) {
                    // Rate limited (e.g. a bulk empty-trash saturated the window). Back
                    // off and retry rather than dropping the save — otherwise a
                    // destructive edit like clearing the trash is silently lost and the
                    // now-deleted blobs 404 on the next load.
                    const ra = parseInt(res.headers.get('Retry-After') || '', 10);
                    await new Promise((r) => setTimeout(r, Number.isFinite(ra) && ra > 0 ? ra * 1000 : Math.min(500 * 2 ** retry, 8000)));
                    return this._doFlush(retry + 1);
                } else if (res.ok) {
                    this.version = (await res.json()).version ?? this.version + 1;
                } else {
                    throw new Error('store save failed');
                }
            } catch (e) {
                if (this._onError) this._onError();
                throw e;
            }
        },
    };
}
