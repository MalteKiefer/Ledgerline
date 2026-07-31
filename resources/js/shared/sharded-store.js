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
import { fetchDecryptWorker } from './blob-io';
import { padBlob } from './padme';
import { bucketize, shardHash, recommendedShardBits } from './shard';
import { canonicalJSON } from './canonical-json';
import { mergeArrayById } from './manifest-merge';

const clone = (v) => (v == null ? v : structuredClone(v));

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
        degraded: false, // true when load() found a shard blob missing (404 after retries)
        _missingShards: 0, // count of shards that failed to load in a degraded load
        _missingRefs: [], // refs of the missing shards (kept in the root for recovery)
        // Last loaded/committed record + collection set. A 409 rebase replays only
        // our delta (base -> current) onto the winning server records so a concurrent
        // writer's records in the SAME shard survive (merge-safety spec §2/§3b).
        _base: null,

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
        // previous blob when the canonical bytes are unchanged. A REPLACED blob is
        // NOT deleted here — a concurrent writer (second tab / mobile) may still
        // reference it in its own root; eager deletion is what dangled a live root
        // and lost data. Superseded blobs are reclaimed by the grace-gated reconcile
        // (24h), by which time all writers have converged on one root.
        async _buildCollection(arr, prev) {
            if (! arr.length) return null;
            const hash = await shardHash(arr);
            if (prev && prev.hash === hash && prev.ref) return prev;
            const sealed = await this._sealBlob(new TextEncoder().encode(canonicalJSON(arr)));
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

        // Fetch + decrypt the current server root into a plain state object WITHOUT
        // touching this.data — reused by load() and the 409 rebase. Sets the store's
        // shard descriptors + degraded flags (a load-like read). Returns
        // { version, data:{v,recordKey:[],<collections>}, shards, shardBits, collDesc }.
        async _fetchServerState() {
            const d = await getJson(prefix + '/store');
            const root = d.ciphertext ? window.Vault.openManifest(d.ciphertext) : this._blank();
            const state = { version: d.version ?? 0, data: this._blank(), shards: [], shardBits: 0, collDesc: {} };
            if (! (root.v === 3 && Array.isArray(root.shards))) {
                return state;
            }
            state.shardBits = root.shardBits ?? 0;
            this.degraded = false;
            this._missingShards = 0;
            this._missingRefs = [];
            // Load + decrypt every record shard in parallel (immutable blob cache makes
            // repeats instant). A 404 is RETRIED first: shards live on eventually-
            // consistent object storage (B2) where a fresh write can 404 briefly —
            // treating that as permanent would erase live data. Only after retries do
            // we mark `degraded` and keep the missing shard's descriptor (no self-
            // erasing rewrite). Any OTHER failure THROWS (may recover; a partial set
            // would lose data for good).
            const fetchShard = async (s) => {
                for (let attempt = 0; ; attempt++) {
                    try {
                        const b = await fetchDecryptWorker(prefix + '/raw', s.ref, s.key);
                        return JSON.parse(new TextDecoder().decode(b));
                    } catch (e) {
                        if (e && e.status === 404) {
                            if (attempt < 3) { await new Promise((r) => setTimeout(r, 500 * 2 ** attempt)); continue; }
                            this.degraded = true; this._missingShards++; this._missingRefs.push(s.ref);
                            return null;
                        }
                        throw e;
                    }
                }
            };
            const parts = await Promise.all(root.shards.map((s) => fetchShard(s)));
            const records = [];
            for (const arr of parts) if (Array.isArray(arr)) records.push(...arr);
            state.data[recordKey] = records;
            for (const c of collections) {
                state.data[c.key] = await this._loadCollection(root[c.rootRef], root[c.rootKey]);
                state.collDesc[c.key] = root[c.rootRef] ? { ref: root[c.rootRef], key: root[c.rootKey], hash: root[c.rootHash] } : null;
            }
            state.shards = root.shards.map((s) => ({ ...s }));
            return state;
        },

        // A snapshot of the record + collection slices of `src`, used as the rebase
        // base. The base must reflect what is actually COMMITTED (what we sent, or the
        // server's copy) — never the live this.data, which may hold un-pushed edits.
        _snapshotFrom(src) {
            const base = { [recordKey]: clone(src?.[recordKey] ?? []) };
            for (const c of collections) base[c.key] = clone(src?.[c.key] ?? []);
            return base;
        },

        _snapshotBase() {
            return this._snapshotFrom(this.data);
        },

        async load() {
            const s = await this._fetchServerState();
            this.version = s.version;
            this._shardBits = s.shardBits;
            this._shards = s.shards;
            this._collDesc = s.collDesc;
            this.data = s.data;
            this._base = this._snapshotBase();
            this.loaded = true;
            this.ready = true;
            return this.data;
        },

        reset() {
            this.data = null; this.version = 0; this.ready = false; this.loaded = false;
            this._shards = []; this._collDesc = {}; this._shardBits = 0; this._base = null;
            this.degraded = false; this._missingShards = 0; this._missingRefs = []; clearTimeout(this._timer);
        },

        // Proactively pull the latest server records and rebase our pending in-memory
        // delta onto them IN PLACE (bound refs stay live) — like the 409 rebase but
        // without a write. Used where a caller must observe another device's records
        // before acting (e.g. invoice numbering must see invoices issued elsewhere to
        // avoid a duplicate number). Best-effort + non-destructive: offline keeps the
        // in-memory copy; a degraded (missing-shard) server view is never merged.
        async refresh() {
            if (! this.loaded || this.degraded) return;
            let s;
            try { s = await this._fetchServerState(); } catch (e) { return; }
            if (this.degraded) return; // a shard went missing — freeze, don't merge a partial view
            const base = this._base ?? this._snapshotBase();
            const recs = mergeArrayById(base[recordKey] ?? [], this.data[recordKey] || [], s.data[recordKey] || []);
            this.data[recordKey].splice(0, this.data[recordKey].length, ...recs);
            for (const c of collections) {
                const m = mergeArrayById(base[c.key] ?? [], this.data[c.key] || [], s.data[c.key] || []);
                (this.data[c.key] ||= []).splice(0, this.data[c.key].length, ...m);
            }
            this.version = s.version;
            this._shardBits = s.shardBits; this._shards = s.shards; this._collDesc = s.collDesc;
            // The committed state we rebased onto is the SERVER's, not our merged copy;
            // baking our still-un-pushed delta into _base would hide it from the next
            // 409 rebase and drop it.
            this._base = this._snapshotFrom(s.data);
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
            // FROZEN while degraded: a shard blob is missing, so the root must NOT be
            // rewritten (that would drop the missing shard's slot and make the loss
            // permanent) and no reconcile may run. The store is read-only until the
            // missing blob is restored and a clean reload clears `degraded`.
            if (this.degraded) return Promise.resolve();
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
            // A shard blob replaced by this save is deliberately NOT deleted here.
            // Eager deletion is the race that lost data: a concurrent writer (second
            // tab / mobile) may still reference this exact shard ref in its own root,
            // and deleting it dangles that root → 404 → corrupt index. Superseded
            // shards become orphans reclaimed by the grace-gated reconcile (24h),
            // long after every writer has converged on one root.
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
                // Snapshot the slices that go into this root BEFORE any await. A record
                // added to this.data while _buildRoot / the PUT is in flight is not in
                // the root we send, so it must stay OUT of _base — otherwise the next
                // 409 rebase sees no delta for it and drops it. _buildRoot reads
                // this.data[recordKey] synchronously first, so this matches what it seals.
                const sent = this._snapshotFrom(this.data);
                const root = await this._buildRoot();
                // Send the shard/collection blob refs the new root points at. The
                // server rejects (422) a root referencing a blob with no ledger row —
                // the integrity guard that makes a dangling-shard save impossible. The
                // refs are non-secret UUIDs (already in the ledger / raw URLs), so this
                // leaks nothing about content.
                const body = JSON.stringify({
                    ciphertext: window.Vault.sealManifest(root),
                    version: this.version,
                    shards: this.shardRefs(),
                });
                const res = await fetch(prefix + '/store', { method: 'PUT', headers: jsonHeaders(), body });
                if (res.status === 409) {
                    // A concurrent writer advanced the version. REBASE: fetch the winning
                    // records and replay ONLY our delta (base -> current) onto them, per
                    // record id — so a concurrent writer's records in the SAME shard
                    // survive instead of being overwritten by our stale in-memory copy
                    // (merge-safety spec §2/§3b). Merge in place to keep bound refs live.
                    const server = await this._fetchServerState();
                    // If the winning root is missing a shard (404), its record set is
                    // INCOMPLETE — merging + re-sealing would drop those records. Abort
                    // and surface: writes stay frozen until the blob is restored.
                    if (this.degraded) throw new Error('store save conflict (degraded)');
                    const merged = mergeArrayById(this._base?.[recordKey] ?? [], this.data[recordKey] || [], server.data[recordKey] || []);
                    this.data[recordKey].splice(0, this.data[recordKey].length, ...merged);
                    for (const c of collections) {
                        if (! Array.isArray(this.data[c.key])) this.data[c.key] = [];
                        const mc = mergeArrayById(this._base?.[c.key] ?? [], this.data[c.key], server.data[c.key] || []);
                        this.data[c.key].splice(0, this.data[c.key].length, ...mc);
                    }
                    this.version = server.version;
                    this._shards = server.shards;
                    this._shardBits = server.shardBits;
                    this._collDesc = server.collDesc;
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
                    // Committed: the base is exactly what we SENT (captured pre-await),
                    // not the live this.data — see the `sent` snapshot above.
                    this._base = sent;
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
