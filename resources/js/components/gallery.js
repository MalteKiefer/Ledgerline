// Gallery module (ZK photos/albums/people/search/map). Extracted from app.js.
import { getJson, postForm } from '../shared/api';
import { fetchDecrypt, fetchDecryptWorker, queueBlobDelete, thumbLane } from '../shared/blob-io';
import { padBlob } from '../shared/padme';
import { formatBytes } from '../shared/file-categories';
import { loadLeaflet } from '../shared/lazy-loaders';
import { bootStore, bootGalleryStore } from '../shared/zk-module';
import { formatDate } from '../shared/dom';
import { contactNameParts, contactDisplayName, contactsSortPref } from '../shared/contact-utils';
import { dec6 } from '../shared/canonical-json';
import { fileSigFromBlob } from '../shared/file-sig';
import {
    pickDerivationPath, scaleDownSize, readJpegExif,
    THUMB_WIDTH, MEDIUM_WIDTH, THUMB_QUALITY, MEDIUM_QUALITY,
} from '../shared/gallery-derive';

export default (config = {}, labels = {}) => {
// Non-reactive caches. Decrypted CLIP/face embeddings are large float arrays;
// keeping them OFF the Alpine component (out of the reactive proxy) is critical
// — proxying thousands of 512-float vectors and reading them through get-traps
// during the O(n²) duplicate/face passes freezes the tab even for a few dozen
// photos. These live in the factory closure, one set per component instance.
const metaCache = {};   // photoId -> decrypted { exif, embedding, phash, faces, ... }
const searchEmb = {};   // photoId -> normalised Float32Array (CLIP), for cosine
return {
    state: 'boot', // boot | locked | ready | error
    index: { v: 1, photos: [], albums: [], people: [] },
    view: 'library',
    // Incremental render window: only the newest N library photos are put in the
    // DOM; a scroll sentinel raises this so the grid never builds thousands of
    // tiles at once (the killer at 10k+ photos). Grows as you scroll.
    renderLimit: 300,
    _renderStep: 300,
    error: '',
    busy: 0,
    uploads: [], // { name, state, progress }
    // Settled (finished) uploads — for the always-visible overall counter.
    uploadDone() { return this.uploads.filter((u) => u.state === 'done' || u.state === 'duplicate' || u.state === 'error').length; },
    uploadBatches: 0,
    progress: { active: false, done: 0, total: 0 }, // backlog processing
    thumbs: {}, // photoId -> objectURL (decrypted, in-memory cache)
    _thumbPending: {}, // photoId -> in-flight thumbFor promise (dedupe)
    viewer: { open: false, kind: 'none', src: '', photo: null },
    usage: { used: 0, quota: 0 },

    _track(p) { this.busy++; return Promise.resolve(p).finally(() => { this.busy = Math.max(0, this.busy - 1); }); },

    async init() {
        this.initDropzone();
        await this.load();
        this.$watch('$store.vault.unlocked', (on) => {
            if (on && this.state !== 'ready') this.load();
            if (! on) { this.state = 'locked'; this._revokeThumbs(); window.LLGalleryStore.reset(); }
        });
        // Leaving the library clears the selection + any active search.
        this.$watch('view', (v) => {
            this.selected = [];
            if (v !== 'library') this.clearSearch();
            if (v === 'people' || v === 'person') this._loadPeopleContacts();
        });
    },

    async load() {
        this.state = 'boot';
        try {
            if (! await bootGalleryStore(this.$store)) { this.state = 'locked'; return; }
        } catch (e) { this.state = 'error'; return; }
        this.index = window.LLGalleryStore.data;
        this.renderLimit = this._renderStep; // start with a small render window
        this.state = 'ready';
        // Deep link from a linked contact (?person=<id>) → open that person.
        const params = new URLSearchParams(location.search);
        const pid = params.get('person');
        if (pid && (this.index.people || []).some((pp) => pp.id === pid)) { this.activePerson = pid; this.view = 'person'; }
        // Deep link to a single photo (?photo=<id>, e.g. from the dashboard
        // "on this day" widget) → open it directly in the viewer.
        const photoId = params.get('photo');
        if (photoId) {
            const target = (this.index.photos || []).find((pp) => pp.id === photoId);
            if (target) this.openViewer(target);
        }
        this.refreshUsage();
        // Process pending uploads (its finally pairs Live Photos); also run a pair
        // pass for the backfill case where nothing is pending but split Live Photos
        // already exist. The guard makes a double call a no-op.
        this.runPipeline(false); // catch up unprocessed thumbnails; no auto face/dup scan on plain load
    },

    _save() { this._mut++; if (this.state === 'ready') window.LLGalleryStore.touch(); },

    /* ---- Derived-data memoisation. Getters like libraryPhotos / people are read
       many times per reactive frame; at a few thousand photos, recomputing a sort
       + a photoId map on every read froze the tab. Cache by a mutation counter
       (bumped on every _save) so each is computed at most once between changes. --- */
    _mut: 0,
    _memo: {},
    _cache(key, fn) {
        const sig = this._mut + '|' + this.index.photos.length + '|' + ((this.index.people || []).length);
        const hit = this._memo[key];
        if (hit && hit.sig === sig) return hit.val;
        const val = fn();
        this._memo[key] = { sig, val };
        return val;
    },
    _photoIndex() { return this._cache('idx', () => new Map(this.libraryPhotos.map((p) => [p.id, p]))); },

    initDropzone() {
        let depth = 0;
        this.dragging = false;
        window.addEventListener('dragenter', (e) => { if (e.dataTransfer?.types?.includes('Files')) { depth++; this.dragging = true; } });
        window.addEventListener('dragleave', () => { depth = Math.max(0, depth - 1); if (! depth) this.dragging = false; });
        window.addEventListener('drop', () => { depth = 0; this.dragging = false; });
    },
    dragging: false,
    async drop(event) {
        this.dragging = false;
        if (this.state !== 'ready') return;
        // A dropped FOLDER isn't expanded by dataTransfer.files — walk the entry
        // tree so nested files aren't silently missed. Capture the entries before
        // any await (the item list is cleared once the handler returns).
        const items = event.dataTransfer.items;
        const entries = items && items.length && items[0].webkitGetAsEntry
            ? [...items].map((it) => it.webkitGetAsEntry?.()).filter(Boolean)
            : [];
        if (entries.length) {
            const files = await this._filesFromEntries(entries);
            if (files.length) return this.upload(files);
        }
        this.upload(event.dataTransfer.files);
    },
    // Recursively collect every file under the dropped entries (files + folders).
    async _filesFromEntries(entries) {
        const out = [];
        const walk = async (entry) => {
            if (! entry) return;
            if (entry.isFile) {
                await new Promise((res) => entry.file((f) => { out.push(f); res(); }, () => res()));
            } else if (entry.isDirectory) {
                const reader = entry.createReader();
                // readEntries returns at most ~100 per call — drain it fully.
                for (;;) {
                    const batch = await new Promise((res) => reader.readEntries(res, () => res([])));
                    if (! batch.length) break;
                    for (const e of batch) await walk(e);
                }
            }
        };
        for (const e of entries) await walk(e);
        return out;
    },

    /* ---- Derived views ---- */
    get libraryPhotos() {
        return this._cache('lib', () => this.index.photos
            .filter((p) => ! p.trashed)
            .sort((a, b) => new Date(b.taken_at || b.created || 0) - new Date(a.taken_at || a.created || 0)));
    },
    // The main timeline hides archived photos (they stay in search/map/albums,
    // like the trash-vs-timeline split — archived ≠ deleted, just out of the way).
    get timelinePhotos() { return this._cache('timeline', () => this.libraryPhotos.filter((p) => ! p.archived)); },
    get favoritePhotos() { return this._cache('fav', () => this.libraryPhotos.filter((p) => p.favorite)); },
    favoriteCount() { return this.favoritePhotos.length; },
    get archivedPhotos() {
        return this._cache('arch', () => this.libraryPhotos.filter((p) => p.archived)
            .sort((a, b) => new Date(b.archived || 0) - new Date(a.archived || 0)));
    },
    archiveCount() { return this.archivedPhotos.length; },
    // "On this day": timeline photos whose capture month+day match today, grouped
    // by year (past years only). Purely client-side over the decrypted index.
    get memories() {
        return this._cache('memories', () => {
            const now = new Date();
            const md = (d) => d.getMonth() + '-' + d.getDate();
            const today = md(now), thisYear = now.getFullYear();
            const byYear = new Map();
            for (const p of this.timelinePhotos) {
                const t = p.taken_at || p.created;
                if (! t) continue;
                const d = new Date(t);
                if (isNaN(d.getTime()) || md(d) !== today || d.getFullYear() >= thisYear) continue;
                const y = d.getFullYear();
                if (! byYear.has(y)) byYear.set(y, []);
                byYear.get(y).push(p);
            }
            return [...byYear.entries()].sort((a, b) => b[0] - a[0])
                .map(([year, photos]) => ({ year, yearsAgo: thisYear - year, photos }));
        });
    },
    memoryCount() { return this._cache('memN', () => this.memories.reduce((n, g) => n + g.photos.length, 0)); },
    get pendingCount() { return this._cache('pending', () => this.index.photos.filter((p) => ! p.trashed && ! p.thumbRef && ! p.failed).length); },
    photoCount() { return this.timelinePhotos.length; },
    trashCount() { return this._cache('trashN', () => this.index.photos.filter((p) => p.trashed).length); },
    get trashedPhotos() {
        return this._cache('trashed', () => this.index.photos.filter((p) => p.trashed)
            .sort((a, b) => new Date(b.trashed || 0) - new Date(a.trashed || 0)));
    },
    // True while there are still older photos not yet put in the DOM.
    get hasMore() { return this.searchResults === null && this.renderLimit < this.timelinePhotos.length; },
    // Scroll sentinel handler: reveal the next page of tiles.
    loadMore() { if (this.hasMore) this.renderLimit += this._renderStep; },
    // Library photos grouped by capture day (newest first) for the timeline —
    // only the current render window, so the DOM never holds the whole library.
    get groupedPhotos() {
        const groups = new Map();
        for (const p of this.timelinePhotos.slice(0, this.renderLimit)) {
            const d = new Date(p.taken_at || p.created || 0);
            const day = isNaN(d.getTime()) ? 'unknown' : d.toISOString().slice(0, 10);
            if (! groups.has(day)) groups.set(day, []);
            groups.get(day).push(p);
        }
        return [...groups.entries()].map(([day, photos]) => ({ day, label: this.dayLabel(day), photos }));
    },
    dayLabel(day) {
        if (day === 'unknown') return '—';
        try { return new Date(day + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }); } catch (e) { return day; }
    },

    /* ---- Upload ---- */
    upload(fileList) {
        // Accept images/videos by MIME, plus HEIC/HEIF/MOV by extension (the OS
        // often reports an empty MIME type for those).
        const rawCount = [...fileList].length;
        const files = [...fileList].filter((f) => /^image\/|^video\//.test(f.type) || /\.(heic|heif|avif|mov|mp4|m4v)$/i.test(f.name));
        if (! files.length) return;
        if (this.uploadBatches === 0) this.uploads = [];
        this.uploadBatches++;
        return this._track((async () => {
            // Show the whole batch immediately, then encrypt+upload a few in
            // parallel so the tray doesn't sit at 0% while one large HEIC encrypts.
            // Live Photos picked as two files (IMG_x.HEIC + IMG_x.MOV, same base
            // name) are paired up front: the still becomes the photo and the .MOV
            // its motion clip, so they upload as ONE entry instead of two-then-merge.
            // (Separately-uploaded pairs are still merged later by content_id.)
            const base = (n) => n.replace(/\.[^.]+$/, '').toLowerCase();
            const isVid = (f) => f.type.startsWith('video/') || /\.(mov|mp4|m4v)$/i.test(f.name);
            const byBase = {};
            for (const f of files) { const b = base(f.name); (byBase[b] = byBase[b] || []).push(f); }
            const motionFor = new Map();
            const consumed = new Set();
            for (const g of Object.values(byBase)) {
                if (g.length !== 2) continue;
                const still = g.find((f) => ! isVid(f));
                const clip = g.find(isVid);
                if (still && clip) { motionFor.set(still, clip); consumed.add(clip); }
            }
            const queue = files.filter((f) => ! consumed.has(f)); // .MOV partners ride along with their still

            // Transparency: a big drop that yields fewer photos than files is almost
            // always Live Photos (still+.MOV folded into one) or non-media files
            // filtered out — report it so the number isn't a mystery.
            const skipped = rawCount - files.length;
            if (consumed.size || skipped) {
                const parts = [(labels.uploadAdded || ':n photos').replace(':n', queue.length)];
                if (consumed.size) parts.push((labels.uploadMerged || ':n Live Photo videos merged').replace(':n', consumed.size));
                if (skipped) parts.push((labels.uploadSkipped || ':n skipped').replace(':n', skipped));
                window.llToast?.(parts.join(' · '));
            }

            const start = this.uploads.length;
            for (const f of queue) this.uploads.push({ name: f.name, state: 'pending', progress: 0 });
            let next = 0;
            // Exact-duplicate guard: skip re-uploading a byte-identical file. The
            // signature (size + hash of head/tail) is computed client-side before
            // encryption, so an identical file is never uploaded in the first place.
            const seen = new Set(this.index.photos.filter((p) => ! p.trashed && p.sig).map((p) => p.sig));
            const uploadOne = async (file, entry) => {
                const enc = await window.Vault.encryptFile(file);
                const cipher = new File([await this._padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
                const id = await this._uploadBlob(cipher, entry);
                return { id, key: enc.encFileKey };
            };
            const worker = async () => {
                while (next < queue.length) {
                    const idx = next++;
                    const file = queue[idx];
                    const entry = this.uploads[start + idx];
                    try {
                        const sig = await this._fileSig(file);
                        if (sig && seen.has(sig)) { entry.state = 'duplicate'; continue; }
                        if (sig) seen.add(sig);
                        entry.state = 'uploading';
                        const orig = await uploadOne(file, entry);
                        const photo = {
                            id: window.LLGalleryStore.newId(),
                            originalRef: orig.id, originalKey: orig.key,
                            name: file.name, mime: file.type || 'application/octet-stream', size: file.size,
                            media_type: isVid(file) ? 'video' : 'image',
                            sig,
                            created: new Date().toISOString(),
                        };
                        // Upload the paired .MOV as this still's motion clip.
                        const clip = motionFor.get(file);
                        if (clip) { const m = await uploadOne(clip, null); photo.motionRef = m.id; photo.motionKey = m.key; }
                        // A11: derive thumb/medium/dims ON-DEVICE for browser-decodable
                        // formats (canvas + WebP) so the full-resolution original never
                        // leaves the browser. HEIC/HEIF + video defer to /process
                        // (thumbPending) via the backlog. ML (CLIP/faces) always server.
                        if (photo.media_type !== 'video') await this._deriveOnDevice(photo, file);
                        this.index.photos.unshift(photo);
                        entry.state = 'done'; entry.progress = 100;
                        this._save();
                    } catch (e) { entry.state = 'error'; entry.error = this._uploadErrorText(e); }
                }
            };
            await Promise.all(Array.from({ length: Math.min(2, queue.length) }, worker));
            this.uploadBatches--;
            this.refreshUsage();
            // Full pipeline after an upload: process → retry → faces → duplicates.
            this.runPipeline();
            // Auto-clear once every entry finished cleanly (keep errors/dupes visible).
            if (this.uploads.every((u) => u.state === 'done')) {
                setTimeout(() => { if (! this.uploading) this.uploads = []; }, 4000);
            }
        })());
    },

    _uploadBlob(file, entry) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', config.token);
            data.append('file', file, file.name);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', config.uploadUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 300000;
            if (entry) xhr.upload.onprogress = (ev) => { if (ev.lengthComputable) entry.progress = Math.round((ev.loaded / ev.total) * 100); };
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText).id); } catch (e) { reject(new Error('bad_response')); }
                    return;
                }
                if (xhr.status === 413) { reject(new Error('quota')); return; }
                // Surface the server's own reason (Laravel returns {message} for JSON).
                let detail = '';
                try { detail = JSON.parse(xhr.responseText).message || ''; } catch (e) { /* not JSON */ }
                reject(new Error(detail ? 'server:' + detail : 'http:' + xhr.status));
            };
            xhr.onerror = () => reject(new Error('network'));
            xhr.ontimeout = () => reject(new Error('timeout'));
            xhr.send(data);
        });
    },
    // Turn an upload error into a human reason for the tray (the server already
    // sends a localized {message} for most failures; we pass it through).
    _uploadErrorText(e) {
        const m = (e && e.message) || '';
        if (m === 'quota') return labels.uploadErrQuota || 'Storage quota exceeded';
        if (m === 'network') return labels.uploadErrNetwork || 'Network error';
        if (m === 'timeout') return labels.uploadErrTimeout || 'Timed out';
        if (m.startsWith('server:')) return m.slice(7);
        if (m.startsWith('http:')) return `${labels.uploadErrFailed || 'Upload failed'} (${m.slice(5)})`;
        if (m === 'bad_response') return labels.uploadErrFailed || 'Upload failed';
        return m || labels.uploadErrGeneric || 'Error';
    },

    /* ---- Backlog: process un-processed uploads (transient plaintext) ---- */
    _backlogRunning: false,
    async runBacklog() {
        if (this._backlogRunning || this.state !== 'ready') return;
        const pending = () => this.index.photos.filter((p) => ! p.trashed && ! p.thumbRef && ! p.failed);
        if (! pending().length) return;
        this._backlogRunning = true;
        this.progress = { active: true, done: 0, total: pending().length };
        let sinceFlush = 0;
        try {
            for (;;) {
                const p = pending()[0];
                if (! p) break;
                try {
                    await this._processOne(p);
                } catch (e) {
                    p._tries = (p._tries || 0) + 1;
                    p.failed = true; // isolate the bad record; never blocks the rest
                    p.procError = this._procErrorText(e);
                }
                this.progress.done++;
                this._save();
                // Persist in batches rather than re-sealing + PUTting the whole
                // manifest after every single photo (which hammered the store-save
                // rate limit on a large import). A crash only re-processes the few
                // since the last flush — pending() already makes that idempotent.
                if (++sinceFlush >= 8) { sinceFlush = 0; await window.LLGalleryStore.flush(); }
                // Batched: a short breather every 10 photos so a big import doesn't
                // hammer /gallery/process into timeouts/502s.
                if (this.progress.done % 10 === 0) await new Promise((r) => setTimeout(r, 700));
            }
        } finally {
            // Flush the tail so the last (< 8) photos are persisted immediately.
            try { await window.LLGalleryStore.flush(); } catch (e) { /* debounce backstop */ }
            this._backlogRunning = false;
            this.progress.active = false;
            this.refreshUsage();
            // Merge Apple Live Photos uploaded as separate HEIC + MOV files.
            await this.pairLivePhotos();
        }
    },
    get failedCount() { return this.index.photos.filter((p) => p.failed).length; },
    // Reprocess failed photos. Auto retries only those under the try cap (so it
    // converges); a manual retry resets the cap and re-tries everything failed.
    // Returns the backlog promise so the pipeline can await it.
    retryFailed(auto = false) {
        let any = false;
        for (const p of this.index.photos) {
            if (! p.failed) continue;
            if (auto && (p._tries || 0) >= 2) continue;
            if (! auto) p._tries = 0;
            p.failed = false; delete p.procError; any = true;
        }
        if (! any) return Promise.resolve();
        this._save();
        return this.runBacklog();
    },
    // Full post-upload pipeline, run sequentially to spread load: process the
    // thumbnail backlog (batched) → retry transient failures once → cluster faces
    // → detect duplicates. `withScans=false` (on plain page load) skips the heavy
    // face/duplicate passes and only catches up unprocessed thumbnails.
    _pipelineRunning: false,
    async runPipeline(withScans = true) {
        if (this._pipelineRunning || this.state !== 'ready') return;
        this._pipelineRunning = true;
        try {
            await this.runBacklog();
            if (this.index.photos.some((p) => p.failed && (p._tries || 0) < 2)) {
                await new Promise((r) => setTimeout(r, 4000));
                await this.retryFailed(true);
            }
            // Deferred vision pass: fills in embeddings + faces that the fast
            // upload skipped, so photos appear first and ML catches up after.
            const mlDone = await this.runMlBacklog();
            // Cluster if asked to, OR whenever the ML pass just detected faces on
            // new photos — otherwise a reload that finishes the backlog would fill
            // faces into the meta but never group them into people.
            if ((withScans || mlDone > 0) && this.state === 'ready' && ! this.dupScanning && ! this.peopleScanning) {
                await this.scanFaces();
            }
            if (withScans && this.state === 'ready' && ! this.dupScanning && ! this.peopleScanning) {
                await this.scanDuplicates();
            }
        } finally {
            this._pipelineRunning = false;
        }
    },
    // "Run all" from the Activity panel: if photos still need the ML face pass,
    // force it (deepFaceRescan analyzes + clusters), else run the normal pipeline;
    // finish with a duplicate pass either way.
    async runAllJobs() {
        if (this._pipelineRunning || this.deepScanning || this.state !== 'ready') return;
        if (this.unanalyzedCount() > 0) {
            await this.deepFaceRescan();
            if (! this.dupScanning) await this.scanDuplicates();
        } else {
            await this.runPipeline();
        }
    },
    _procErrorText(e) {
        const m = (e && e.message) || '';
        if (m === 'network') return labels.uploadErrNetwork || 'Network error';
        if (m === 'timeout') return labels.uploadErrTimeout || 'Timed out';
        if (m.startsWith('server:')) return m.slice(7);
        if (m.startsWith('http:')) return `${labels.procErrFailed || 'Processing failed'} (${m.slice(5)})`;
        return m || labels.procErrFailed || 'Processing failed';
    },

    /**
     * Pair Apple Live Photos that were uploaded as two separate files (a still +
     * its .MOV). Both carry the same `content_id` (Apple asset id, sealed in the
     * meta blob); the still adopts the MOV's original as its motion clip and the
     * redundant video entry is dropped, its derived blobs freed. Idempotent —
     * only touches unpaired stills that have a matching video. Returns the count.
     */
    async pairLivePhotos() {
        if (this._pairing || this.state !== 'ready') return 0;
        const photos = this.index.photos.filter((p) => ! p.trashed);
        const stills = photos.filter((p) => p.media_type !== 'video' && ! p.motionRef);
        const videos = photos.filter((p) => p.media_type === 'video');
        if (! stills.length || ! videos.length) return 0;
        this._pairing = true;
        try {
            await this._ensureMeta(photos);
            const cid = (p) => metaCache[p.id]?.content_id || null;
            const videoByCid = {};
            for (const v of videos) { const c = cid(v); if (c && ! videoByCid[c]) videoByCid[c] = v; }

            const removed = new Set();
            const freed = [];
            for (const s of stills) {
                const c = cid(s);
                const v = c ? videoByCid[c] : null;
                if (! v || removed.has(v)) continue;
                // The MOV becomes the still's motion clip (keep its original blob).
                s.motionRef = v.originalRef; s.motionKey = v.originalKey;
                s.duration = s.duration || metaCache[v.id]?.duration || null;
                removed.add(v);
                for (const ref of [v.thumbRef, v.mediumRef, v.motionRef, v.metaRef, ...(v.faceCropRefs || [])]) if (ref) freed.push(ref);
                delete metaCache[v.id];
            }
            if (! removed.size) return 0;

            const removedIds = new Set([...removed].map((v) => v.id));
            this.index.photos = this.index.photos.filter((p) => ! removed.has(p));
            // Drop any face members / empty clusters that pointed at a removed video.
            if (Array.isArray(this.index.people)) {
                this.index.people = this.index.people
                    .map((pp) => ({ ...pp, faces: (pp.faces || []).filter((f) => ! removedIds.has(f.photoId)) }))
                    .filter((pp) => (pp.faces || []).length >= 2);
            }
            for (const v of removed) { if (this.thumbs?.[v.id]) { URL.revokeObjectURL(this.thumbs[v.id]); delete this.thumbs[v.id]; } }

            this._save();
            await window.LLGalleryStore.flush();
            if (freed.length) this._freeBlobs(freed);
            return removed.size;
        } finally {
            this._pairing = false;
        }
    },

    async _processOne(p) {
        // 1. Decrypt the original.
        const plain = await this._decryptBlob(p.originalRef, p.originalKey);
        const file = new File([plain], p.name || 'photo', { type: p.mime || 'application/octet-stream' });
        // Backfill the exact-duplicate signature for photos uploaded before it existed.
        if (! p.sig) p.sig = await this._fileSig(file);
        // 2. Transient transform on the server (plaintext in, derived out, discarded).
        const fd = new FormData();
        fd.append('_token', config.token);
        fd.append('file', file, file.name);
        // Fast upload: skip the CLIP embedding + face detection so the photo
        // appears immediately; the deferred ML backlog fills them in later.
        fd.append('ml', '0');
        const res = await fetch(config.processUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        if (! res.ok) {
            let detail = '';
            try { detail = (await res.json()).message || ''; } catch (e) { /* not JSON (e.g. 502/504) */ }
            throw new Error(detail ? 'server:' + detail : 'http:' + res.status);
        }
        const d = await res.json();
        // 3. Encrypt + store the derived blobs.
        const meta = {
            exif: d.exif, place: d.place, embedding: d.embedding, phash: d.phash,
            embModel: Array.isArray(d.embedding) ? config.clipModel : null,
            faces: [], width: d.width, height: d.height, duration: d.duration, content_id: d.content_id,
        };
        p.embModel = meta.embModel; // mirror on the index for cheap re-embed checks
        if (d.thumb) { const r = await this._encStore(this._b64bytes(d.thumb), 'thumb.enc'); p.thumbRef = r.id; p.thumbKey = r.key; p.thumbPending = false; }
        if (d.medium) { const r = await this._encStore(this._b64bytes(d.medium), 'medium.enc'); p.mediumRef = r.id; p.mediumKey = r.key; }
        if (d.motion) { const r = await this._encStore(this._b64bytes(d.motion), 'motion.enc'); p.motionRef = r.id; p.motionKey = r.key; }
        for (const f of (d.faces || [])) {
            const face = { score: f.score, box: f.box, embedding: f.embedding };
            if (f.crop) { const r = await this._encStore(this._b64bytes(f.crop), 'crop.enc'); face.cropRef = r.id; face.cropKey = r.key; }
            meta.faces.push(face);
        }
        // 4. Seal the metadata blob.
        const metaBytes = new TextEncoder().encode(JSON.stringify(meta));
        const mr = await this._encStore(metaBytes, 'meta.enc');
        p.metaRef = mr.id; p.metaKey = mr.key;
        // 5. Promote display fields onto the index entry (sealed, so client-only):
        // date/dims + GPS (for the map) + camera (for fast metadata search).
        p.taken_at = d.exif?.taken_at || p.created;
        // Persist the resolved capture date onto the sealed manifest entry so the
        // dashboard's "on this day" widget can read it without re-decrypting meta.
        const entry = this.index.photos.find((x) => x.id === p.id);
        if (entry && entry.taken_at !== p.taken_at) { entry.taken_at = p.taken_at; this._save(); }
        p.width = d.width; p.height = d.height; p.duration = d.duration;
        // lat/lng are stored as fixed 6-dp decimal STRINGS (or null) — hot records
        // carry no floats (§4.1/§5.2), so a float never corrupts the shard's
        // canonical-JSON hash. Consumers parse back to Number where a number is needed.
        p.lat = dec6(d.exif?.lat); p.lng = dec6(d.exif?.lon);
        p.geoChecked = true; // coords are now known (or known-absent) — map skip
        p.camera = d.exif?.camera ?? null;
        // Faces were skipped in this fast pass — leave hasFaces UNKNOWN (null), not
        // 0, so the photo counts as un-analyzed until the ML pass actually runs.
        p.hasFaces = null;
        p.faceCropRefs = [];
        // The CLIP embedding + faces were skipped (fast upload) — mark the photo
        // for the deferred ML pass and cache its fresh meta for the merge.
        p.mlPending = true;
        metaCache[p.id] = meta;
        // Prime the decrypted thumbnail so the grid updates live (reactive cache).
        this.thumbFor(p);
    },

    /**
     * A11 (§8.1/§8.2/§8.3): derive a photo's thumb + medium renditions and its
     * dimensions ON-DEVICE, so a browser-decodable original never leaves the
     * browser for /process. Canvas + WebP mirror the server's THUMB_WIDTH/
     * MEDIUM_WIDTH + quality so a browser-derived rendition is interchangeable
     * with a server one. EXIF (capture date / GPS / camera) is recovered from the
     * JPEG header on-device (no lib for other formats → left absent). CLIP + faces
     * stay server-side (mlPending). Non-decodable formats (HEIC/HEIF) get
     * thumbPending=true and defer to the /process backlog. Never throws — any
     * failure falls back to the pending path so the photo is never lost.
     */
    async _deriveOnDevice(p, file) {
        if (pickDerivationPath(p.mime, p.name) !== 'canvas') {
            // Can't decode here (HEIC/HEIF) — defer thumb/medium/dims + EXIF + ML
            // to the server /process backlog (it fills them all in one pass).
            p.thumbPending = true;
            return;
        }
        try {
            const bmp = await this._decodeBitmap(file);
            if (! bmp) { p.thumbPending = true; return; }
            const sw = bmp.width, sh = bmp.height;
            const thumb = await this._canvasWebp(bmp, THUMB_WIDTH, THUMB_QUALITY);
            const medium = await this._canvasWebp(bmp, MEDIUM_WIDTH, MEDIUM_QUALITY);
            if (typeof bmp.close === 'function') bmp.close();
            if (! thumb || ! medium) { p.thumbPending = true; return; }
            const tr = await this._encStore(thumb, 'thumb.enc');
            const mr = await this._encStore(medium, 'medium.enc');
            p.thumbRef = tr.id; p.thumbKey = tr.key;
            p.mediumRef = mr.id; p.mediumKey = mr.key;
            p.width = sw; p.height = sh;

            // On-device EXIF (JPEG only) for the hot display fields. Fail-safe: any
            // absent/unparseable value stays null and we simply defer to `created`.
            let exif = { taken_at: null, lat: null, lon: null, camera: null };
            if (/^image\/jpe?g$/i.test(p.mime) || /\.jpe?g$/i.test(p.name)) {
                try { exif = readJpegExif(await file.arrayBuffer()); } catch (e) { /* keep nulls */ }
            }
            p.taken_at = exif.taken_at || p.created;
            p.lat = dec6(exif.lat); p.lng = dec6(exif.lon);
            // Coords are known (or known-absent) from the header → the map's
            // meta-decrypt backfill can skip this photo.
            p.geoChecked = true;
            p.camera = exif.camera ?? null;

            // Seal a meta blob so the viewer info panel + dedup + ML merge have a
            // home (mirrors _processOne's shape). ML fills embedding/faces later.
            const meta = {
                exif: { taken_at: exif.taken_at, lat: exif.lat, lon: exif.lon, camera: exif.camera },
                place: {}, embedding: null, phash: null, embModel: null,
                faces: [], width: sw, height: sh, duration: null, content_id: null,
            };
            const metaBlob = await this._encStore(new TextEncoder().encode(JSON.stringify(meta)), 'meta.enc');
            p.metaRef = metaBlob.id; p.metaKey = metaBlob.key;
            metaCache[p.id] = meta;

            // Renditions are present; only the vision pass is outstanding.
            p.thumbPending = false;
            p.hasFaces = null;    // unknown until the ML pass runs
            p.faceCropRefs = [];
            p.mlPending = true;   // CLIP + faces still run server-side (_analyzeOne)
            this.thumbFor(p);     // prime the live grid thumbnail
        } catch (e) {
            // Anything unexpected → hand the photo to the server backlog untouched.
            p.thumbPending = true;
        }
    },
    // Decode an image File to a bitmap. Prefer createImageBitmap (off-main-thread,
    // handles EXIF orientation via imageOrientation); fall back to an <img> + a
    // decoded object URL for browsers/formats createImageBitmap rejects.
    async _decodeBitmap(file) {
        try {
            if (typeof createImageBitmap === 'function') {
                return await createImageBitmap(file, { imageOrientation: 'from-image' });
            }
        } catch (e) { /* fall through to <img> */ }
        try {
            const url = URL.createObjectURL(file);
            try {
                const img = new Image();
                img.decoding = 'async';
                await new Promise((res, rej) => { img.onload = res; img.onerror = rej; img.src = url; });
                if (typeof img.decode === 'function') { try { await img.decode(); } catch (e) { /* onload already fired */ } }
                return img;
            } finally { URL.revokeObjectURL(url); }
        } catch (e) { return null; }
    },
    // Draw a decoded bitmap scaled down to `maxWidth` and encode as WebP. Uses
    // OffscreenCanvas where available (no DOM), else a detached <canvas>. Returns
    // the encoded bytes, or null if WebP encoding isn't supported / fails.
    async _canvasWebp(bmp, maxWidth, quality) {
        const sw = bmp.width || bmp.naturalWidth;
        const sh = bmp.height || bmp.naturalHeight;
        const { w, h } = scaleDownSize(sw, sh, maxWidth);
        if (! w || ! h) return null;
        let blob = null;
        if (typeof OffscreenCanvas === 'function') {
            const cv = new OffscreenCanvas(w, h);
            cv.getContext('2d').drawImage(bmp, 0, 0, w, h);
            blob = await cv.convertToBlob({ type: 'image/webp', quality });
        } else {
            const cv = document.createElement('canvas');
            cv.width = w; cv.height = h;
            cv.getContext('2d').drawImage(bmp, 0, 0, w, h);
            blob = await new Promise((r) => cv.toBlob(r, 'image/webp', quality));
        }
        // A browser that silently ignored the WebP request (some old engines) hands
        // back a PNG; that's still a valid, all-clients-decodable rendition, so keep
        // it — the manifest stores only a ref and the bytes are self-describing.
        if (! blob) return null;
        return new Uint8Array(await blob.arrayBuffer());
    },

    /**
     * Deferred vision pass for one photo: decrypt its medium rendition, get the
     * CLIP embedding + faces from the server, and merge them into the sealed
     * metadata. Runs in the background after the fast upload already made the
     * photo visible. The old meta blob is replaced (reconcile frees the orphan).
     */
    async _analyzeOne(p) {
        const ref = p.mediumRef || p.originalRef;
        const key = p.mediumKey || p.originalKey;
        if (! ref) { p.mlPending = false; return; }
        const bytes = await this._decryptBlob(ref, key);
        const file = new File([bytes], 'medium.jpg', { type: 'image/jpeg' });
        const fd = new FormData();
        fd.append('_token', config.token);
        fd.append('file', file, file.name);
        const res = await fetch(config.analyzeUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        if (! res.ok) {
            let detail = '';
            try { detail = (await res.json()).message || ''; } catch (e) { /* not JSON */ }
            throw new Error(detail ? 'server:' + detail : 'http:' + res.status);
        }
        const d = await res.json();
        // Merge into the current meta (decrypt if not cached).
        let meta = metaCache[p.id];
        if (! meta) {
            try { meta = JSON.parse(new TextDecoder().decode(await this._decryptBlob(p.metaRef, p.metaKey))); }
            catch (e) { meta = { faces: [] }; }
        }
        meta.embedding = d.embedding;
        // Tag the CLIP model so a later model swap can ignore stale-space vectors
        // (they'd give meaningless cosine scores) until the photo is re-analysed.
        meta.embModel = Array.isArray(d.embedding) ? config.clipModel : null;
        meta.faces = [];
        for (const f of (d.faces || [])) {
            const face = { score: f.score, box: f.box, embedding: f.embedding };
            if (f.crop) { const r = await this._encStore(this._b64bytes(f.crop), 'crop.enc'); face.cropRef = r.id; face.cropKey = r.key; }
            meta.faces.push(face);
        }
        // Re-seal the meta blob (replaces the old one; reconcile frees the orphan).
        const mr = await this._encStore(new TextEncoder().encode(JSON.stringify(meta)), 'meta.enc');
        p.metaRef = mr.id; p.metaKey = mr.key;
        metaCache[p.id] = meta;
        p.embModel = meta.embModel; // mirror on the index for cheap re-embed checks
        if (Array.isArray(meta.embedding) && meta.embModel === config.clipModel) searchEmb[p.id] = this._norm(meta.embedding);
        p.hasFaces = meta.faces.length;
        p.faceCropRefs = meta.faces.map((f) => f.cropRef).filter(Boolean);
        p.mlPending = false;
    },

    /**
     * Background pass that runs the deferred ML (embedding + faces) for every
     * photo still marked mlPending. Throttled + batched like the upload backlog so
     * a large import doesn't hammer the ML sidecar; resumes across sessions.
     */
    mlProgress: { done: 0, total: 0 },
    _mlRunning: false, // reactive so the activity panel can show ML backlog state
    reindexProgress: null, // {done,total} while a CLIP re-embed runs, else null
    async runMlBacklog() {
        if (this._mlRunning || this.state !== 'ready') return 0;
        const pending = () => this.index.photos.filter((p) => p.mlPending && ! p.trashed && ! p.failed && (p.mediumRef || p.originalRef));
        const total = pending().length;
        if (! total) return 0;
        this._mlRunning = true;
        this.mlProgress = { done: 0, total };
        let sinceFlush = 0;
        let done = 0;
        try {
            for (;;) {
                const p = pending()[0];
                if (! p) break;
                try {
                    await this._analyzeOne(p);
                } catch (e) {
                    // Leave mlPending set so it retries next run; don't fail the photo.
                    p._mlTries = (p._mlTries || 0) + 1;
                    // Give up after a few tries, but flag it so a deep rescan can retry.
                    if (p._mlTries >= 3) { p.mlPending = false; p.mlFailed = true; }
                }
                this._save();
                this.mlProgress = { done: Math.min(++done, total), total };
                if (++sinceFlush >= 8) { sinceFlush = 0; await window.LLGalleryStore.flush(); }
                if (done % 8 === 0) await new Promise((r) => setTimeout(r, 700));
            }
        } finally {
            try { await window.LLGalleryStore.flush(); } catch (e) { /* debounce backstop */ }
            this._mlRunning = false;
        }
        return done;
    },
    // Photos never successfully run through the ML face pass yet (no face count
    // recorded) or that gave up earlier — these carry faces the clustering can't
    // see until they're analyzed.
    unanalyzedCount() {
        return this._cache('unan', () => this.index.photos.filter((p) => ! p.trashed && (p.mediumRef || p.originalRef) && (p.hasFaces == null || p.mlFailed)).length);
    },
    // Force the deferred ML (embedding + faces) on every not-yet-analyzed photo,
    // then re-cluster. This is what actually surfaces people across a large
    // library where the background pass never finished.
    deepScanning: false,
    // What the People "Rescan" button should do: if photos still need the ML
    // face pass (detection), run that first — clustering alone can only regroup
    // faces that were already detected, so on an un-analyzed library it finds
    // nothing new. Otherwise just re-cluster.
    async smartRescan() {
        // People scanning always covers the whole library (the scope selector is
        // for the duplicate scan) — a scoped re-cluster of a few photos surprised
        // users by finishing instantly with no visible change.
        const prev = this.scanLimit;
        this.scanLimit = 0;
        try {
            if (this.unanalyzedCount() > 0) return await this.deepFaceRescan();
            return await this.scanFaces();
        } finally { this.scanLimit = prev; }
    },
    // Diagnostics for the People panel: how many faces were actually detected and
    // in how many photos — makes it obvious whether detection (vs clustering) is
    // the problem when few or no people show up.
    facesDetected() { return this._cache('facesDet', () => this.index.photos.reduce((s, p) => s + (Number(p.hasFaces) || 0), 0)); },
    photosWithFaces() { return this._cache('photosFaces', () => this.index.photos.filter((p) => (Number(p.hasFaces) || 0) > 0).length); },
    async deepFaceRescan() {
        if (this.peopleScanning || this._mlRunning || this.deepScanning || this.state !== 'ready') return;
        this.deepScanning = true;
        try {
            let any = false;
            for (const p of this.index.photos) {
                if (p.trashed || ! (p.mediumRef || p.originalRef)) continue;
                if (p.hasFaces == null || p.mlFailed) { p.mlPending = true; p._mlTries = 0; delete p.mlFailed; any = true; }
            }
            if (any) this._save();
            await this.runMlBacklog();
            await this.scanFaces();
        } finally {
            this.deepScanning = false;
        }
    },
    // Re-embed ONLY the CLIP search vector for photos on a stale/absent model
    // (e.g. after a CLIP model swap). Deliberately does NOT touch faces or
    // re-cluster people — a model change only affects text/image search, and
    // the buffalo_l face model is unchanged, so the existing people stay intact.
    async _reembedOne(p) {
        const ref = p.mediumRef || p.originalRef, key = p.mediumKey || p.originalKey;
        if (! ref) return;
        const bytes = await this._decryptBlob(ref, key);
        const fd = new FormData();
        fd.append('_token', config.token);
        fd.append('file', new File([bytes], 'medium.jpg', { type: 'image/jpeg' }), 'medium.jpg');
        const res = await fetch(config.analyzeUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        if (! res.ok) return;
        const d = await res.json();
        if (! Array.isArray(d.embedding)) return;
        let meta = metaCache[p.id];
        if (! meta) { try { meta = JSON.parse(new TextDecoder().decode(await this._decryptBlob(p.metaRef, p.metaKey))); } catch (e) { return; } }
        meta.embedding = d.embedding;
        meta.embModel = config.clipModel; // faces untouched
        const mr = await this._encStore(new TextEncoder().encode(JSON.stringify(meta)), 'meta.enc');
        p.metaRef = mr.id; p.metaKey = mr.key;
        metaCache[p.id] = meta;
        p.embModel = config.clipModel;
        searchEmb[p.id] = this._norm(meta.embedding);
    },
    async reindexAll() {
        if (this.deepScanning || this._mlRunning || this.state !== 'ready') return;
        const todo = this.index.photos.filter((p) => ! p.trashed && (p.mediumRef || p.originalRef) && p.embModel !== config.clipModel);
        if (! todo.length) { window.llToast?.(labels.reindexNone || 'Search index is already up to date.'); return; }
        if (! await this.$store.confirm.ask((labels.reindexConfirm || 'Re-index :n photos for search?').replace(':n', todo.length))) return;
        this._mlRunning = true;
        this.reindexProgress = { done: 0, total: todo.length };
        try {
            let sinceFlush = 0;
            for (const p of todo) {
                try { await this._reembedOne(p); } catch (e) { /* skip, retry on a later run */ }
                this.reindexProgress = { done: this.reindexProgress.done + 1, total: todo.length };
                if (++sinceFlush >= 8) { sinceFlush = 0; this._save(); await window.LLGalleryStore.flush(); }
            }
            this._save();
            await window.LLGalleryStore.flush();
            window.llToast?.(labels.reindexDone || 'Search re-indexing complete.');
        } finally {
            this._mlRunning = false; this.reindexProgress = null;
        }
    },

    // Encrypt raw bytes with a fresh per-blob key and upload; returns { id, key }.
    async _encStore(bytes, name) {
        const enc = window.Vault.encryptContent(bytes, { name, mime: 'application/octet-stream' });
        const cipher = new File([await this._padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
        const id = await this._uploadBlob(cipher, null);
        return { id, key: enc.encFileKey };
    },
    // Length-hiding padding for stored content blobs. The secretstream decryptor
    // stops at its FINAL frame, so random bytes appended after the ciphertext are
    // never parsed — decryption is unaffected while the stored/on-ledger size is
    // rounded up to a Padmé bucket (leaks O(log log n) bits, ≤~12% overhead)
    // instead of revealing the exact plaintext length. Random (not zero) padding
    // so an observer of the raw blob can't spot the boundary.
    _padBlob(blob) {
        return padBlob(blob);
    },
    _decryptBlob(ref, key) {
        return fetchDecrypt(config.rawBase, ref, key);
    },
    _b64bytes(b64) {
        const bin = atob(b64); const out = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
        return out;
    },
    // Cheap exact-file signature: byte size + SHA-256 over the first and last
    // 1 MiB. Bounded memory (never buffers a whole video), and collisions for two
    // genuinely different files are astronomically unlikely.
    _fileSig(file) { return fileSigFromBlob(file); },

    /* ---- Thumbnails (decrypted, cached) ---- */
    async thumbFor(p) {
        if (! p.thumbRef) return '';
        if (this.thumbs[p.id]) return this.thumbs[p.id];
        // Dedupe: x-intersect can fire this several times for the same tile before
        // it resolves — share the one in-flight job instead of decrypting twice.
        if (this._thumbPending[p.id]) return this._thumbPending[p.id];
        const job = thumbLane(async () => {
            const bytes = await fetchDecryptWorker(config.rawBase, p.thumbRef, p.thumbKey);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this.thumbs[p.id] = url;
            return url;
        }).catch(() => '').finally(() => { delete this._thumbPending[p.id]; });
        this._thumbPending[p.id] = job;
        return job;
    },
    _revokeThumbs() {
        for (const k in this.thumbs) URL.revokeObjectURL(this.thumbs[k]);
        for (const k in this.faceThumbs) URL.revokeObjectURL(this.faceThumbs[k]);
        for (const k in this.motionUrls) URL.revokeObjectURL(this.motionUrls[k]);
        this.thumbs = {}; this.faceThumbs = {}; this.motionUrls = {}; this.dupGroups = null;
        for (const k in metaCache) delete metaCache[k];
        for (const k in searchEmb) delete searchEmb[k];
    },

    /* ---- Live Photo hover-to-play (grid) ---- */
    motionUrls: {}, // photoId -> decrypted motion object URL (cached per session)

    // Hover over a Live Photo tile → decrypt + overlay-play its motion clip. Only
    // on true hover devices (pointer:fine) so a touch tap still just opens it.
    async hoverMotion(p, tile) {
        if (! p.motionRef || ! window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
        const video = tile.querySelector('video[data-motion]');
        if (! video) return;
        try {
            if (! this.motionUrls[p.id]) {
                const bytes = await this._decryptBlob(p.motionRef, p.motionKey);
                this.motionUrls[p.id] = URL.createObjectURL(new Blob([bytes], { type: 'video/mp4' }));
            }
            if (! tile.matches(':hover')) return; // pointer left while decrypting
            video.src = this.motionUrls[p.id];
            video.currentTime = 0;
            video.style.transform = (p.rotation || p.flipH || p.flipV)
                ? `rotate(${p.rotation || 0}deg) scaleX(${p.flipH ? -1 : 1}) scaleY(${p.flipV ? -1 : 1})` : '';
            video.style.display = 'block';
            await video.play().catch(() => {});
        } catch (e) { /* ignore — the still stays */ }
    },
    unhoverMotion(tile) {
        const video = tile.querySelector('video[data-motion]');
        if (! video) return;
        video.pause();
        video.style.display = 'none';
    },

    /* ---- Viewer ---- */
    async openViewer(p) {
        this.viewer = { open: true, kind: 'loading', src: '', photo: p, meta: null, hasMotion: ! ! p.motionRef, motionOn: false, motionSrc: '', fit: 1 };
        // Decrypt the sealed metadata blob in parallel for the info panel.
        if (p.metaRef) this._loadViewerMeta(p);
        else if (p.lat != null) this._renderMiniMap(parseFloat(p.lat), parseFloat(p.lng));
        try {
            if (p.media_type === 'video') {
                // Videos play the original clip; the sealed `medium` blob is only a
                // poster frame and must not be shown as a still image.
                const bytes = await this._decryptBlob(p.originalRef, p.originalKey);
                if (this.viewer.photo?.id !== p.id) return; // switched/closed meanwhile
                this.viewer.src = URL.createObjectURL(new Blob([bytes], { type: p.mime || 'video/mp4' }));
                this.viewer.kind = 'video';
            } else {
                const ref = p.mediumRef || p.originalRef;
                const key = p.mediumRef ? p.mediumKey : p.originalKey;
                const bytes = await this._decryptBlob(ref, key);
                if (this.viewer.photo?.id !== p.id) return;
                this.viewer.src = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
                this.viewer.kind = 'image';
            }
        } catch (e) { this.error = labels.loadFailed || 'load failed'; this.closeViewer(); }
    },
    // Live Photo playback: decrypt the embedded motion clip on demand and overlay
    // it on the still. Cached for the open viewer session.
    async playMotion() {
        const p = this.viewer.photo;
        if (! p || ! p.motionRef) return;
        try {
            if (! this.viewer.motionSrc) {
                const bytes = await this._decryptBlob(p.motionRef, p.motionKey);
                if (this.viewer.photo?.id !== p.id) return;
                this.viewer.motionSrc = URL.createObjectURL(new Blob([bytes], { type: 'video/mp4' }));
            }
            this.viewer.motionOn = true;
        } catch (e) { /* stay on the still */ }
    },
    stopMotion() { this.viewer.motionOn = false; },
    async _loadViewerMeta(p) {
        try {
            const b = await this._decryptBlob(p.metaRef, p.metaKey);
            const m = JSON.parse(new TextDecoder().decode(b));
            if (this.viewer.photo?.id !== p.id) return;
            this.viewer.meta = m;
            // Self-heal: a client that uploaded this photo (e.g. mobile) may have
            // written EXIF (capture date + GPS + camera) only into the cold meta
            // blob, leaving the hot record's display fields empty. The timeline then
            // groups by upload date (`created`) instead of the capture date, and the
            // photo carries no coords so it never pins on the Explore map — while the
            // detail panel (which reads the meta blob) shows both correctly. Backfill
            // the hot fields from the meta so every consumer that reads the manifest
            // (timeline, on-this-day, dashboard, Explore) agrees. Re-seal once, only
            // on a real change. All fields stay in the sealed manifest (ZK).
            let healed = false;
            const entry = this.index.photos.find((x) => x.id === p.id);
            if (m.exif?.taken_at && p.taken_at !== m.exif.taken_at) {
                p.taken_at = m.exif.taken_at;
                if (entry) entry.taken_at = m.exif.taken_at;
                healed = true;
            }
            // Coords: meta carries raw numbers; the hot record stores dec-6 strings.
            if (p.lat == null && m.exif?.lat != null && m.exif?.lon != null) {
                const dlat = dec6(m.exif.lat), dlng = dec6(m.exif.lon);
                if (dlat != null && dlng != null) {
                    p.lat = dlat; p.lng = dlng; p.geoChecked = true;
                    if (entry) { entry.lat = dlat; entry.lng = dlng; entry.geoChecked = true; }
                    healed = true;
                }
            }
            if (p.camera == null && m.exif?.camera) {
                p.camera = m.exif.camera;
                if (entry) entry.camera = m.exif.camera;
                healed = true;
            }
            if (healed) this._save(); // bumps _mut → invalidates memos, regroups + re-pins
            // meta.exif carries raw numbers; p.lat/p.lng are dec-strings — coerce
            // to Number for Leaflet either way.
            const lat = m.exif?.lat ?? (p.lat != null ? parseFloat(p.lat) : null);
            const lng = m.exif?.lon ?? (p.lng != null ? parseFloat(p.lng) : null);
            if (lat != null) this._renderMiniMap(lat, lng);
        } catch (e) { /* info panel just stays sparse */ }
    },
    closeViewer() {
        if (this.viewer.src) URL.revokeObjectURL(this.viewer.src);
        if (this.viewer.motionSrc) URL.revokeObjectURL(this.viewer.motionSrc);
        if (this._miniMap) { this._miniMap.remove(); this._miniMap = null; }
        this.viewer = { open: false, kind: 'none', src: '', photo: null, meta: null, hasMotion: false, motionOn: false, motionSrc: '', fit: 1 };
    },

    /* ---- Non-destructive photo edits (rotate / flip / date / place) ----
     * All edits live on the sealed manifest entry (and are mirrored into the
     * open viewer's decrypted meta for display). Rotation/flip are applied as a
     * CSS transform everywhere the photo is shown — the original bytes are never
     * re-encoded, so edits are instant and fully reversible. */
    photoTransform(p) {
        if (! p) return '';
        const r = p.rotation || 0;
        const sx = p.flipH ? -1 : 1;
        const sy = p.flipV ? -1 : 1;
        if (! r && sx === 1 && sy === 1) return '';
        return `transform: rotate(${r}deg) scaleX(${sx}) scaleY(${sy});`;
    },
    // Viewer transform: like photoTransform but folds in a fit scale so a 90/270°
    // rotation still fills the stage instead of overflowing/shrinking.
    viewerTransform() {
        const p = this.viewer.photo;
        if (! p) return '';
        const r = p.rotation || 0;
        const s = this.viewer.fit || 1;
        const sx = (p.flipH ? -1 : 1) * s;
        const sy = (p.flipV ? -1 : 1) * s;
        if (! r && sx === 1 && sy === 1) return '';
        return `transform: rotate(${r}deg) scaleX(${sx}) scaleY(${sy}); transform-origin: center;`;
    },
    // Scale that makes a 90/270°-rotated image fit the stage (0/180° = no scale).
    _fitViewer() {
        const img = this.$refs.vimg;
        const stage = this.$refs.vstage;
        const r = this.viewer.photo?.rotation || 0;
        if (! img || ! stage || r % 180 === 0) { this.viewer.fit = 1; return; }
        const dw = img.clientWidth;
        const dh = img.clientHeight;
        const cw = stage.clientWidth - 32; // p-4 padding both sides
        const ch = stage.clientHeight - 32;
        if (! dw || ! dh || cw <= 0 || ch <= 0) { this.viewer.fit = 1; return; }
        this.viewer.fit = Math.min(cw / dh, ch / dw);
    },
    rotatePhoto(p, dir) {
        if (! p) return;
        p.rotation = ((((p.rotation || 0) + dir * 90) % 360) + 360) % 360;
        this._save();
        if (p === this.viewer.photo) this.$nextTick(() => this._fitViewer());
    },
    flipPhoto(p, axis) {
        if (! p) return;
        if (axis === 'h') p.flipH = ! p.flipH; else p.flipV = ! p.flipV;
        this._save();
    },
    // datetime-local <-> ISO (local wall-clock, no timezone maths).
    toLocalInput(iso) {
        if (! iso) return '';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    },
    setTakenAt(p, value) {
        if (! p || ! value) return;
        const d = new Date(value);
        if (isNaN(d.getTime())) return;
        p.taken_at = d.toISOString();
        if (this.viewer.meta?.exif) this.viewer.meta.exif.taken_at = p.taken_at;
        this._save();
    },

    /* ---- Location picker (Leaflet: click the map to set the spot) ---- */
    loc: { open: false, target: null, lat: null, lng: null },
    _locMap: null,
    _locMarker: null,
    geoQuery: '',
    geoResults: [],
    geoBusy: false,
    geoSearched: false,
    // Single photo (viewer) location.
    openLocPicker(p) {
        if (! p) return;
        // p.lat/p.lng are dec-strings on the record; the working `loc` + Leaflet
        // need Numbers, so parse them here.
        const plat = p.lat != null ? parseFloat(p.lat) : null;
        const plng = p.lng != null ? parseFloat(p.lng) : null;
        this.loc = { open: true, bulk: false, target: p, lat: plat, lng: plng };
        this._mountLocMap(plat ?? this.viewer.meta?.exif?.lat ?? 48.2082, plng ?? this.viewer.meta?.exif?.lon ?? 16.3738, plat != null);
    },
    // Set one location on every selected photo.
    openBulkLocPicker() {
        if (! this.selectedCount) return;
        this.loc = { open: true, bulk: true, target: null, lat: null, lng: null };
        this._mountLocMap(48.2082, 16.3738, false);
    },
    async _mountLocMap(startLat, startLng, hasMarker) {
        const L = await loadLeaflet();
        this.$nextTick(() => {
            const el = this.$refs.locmap;
            if (! el || ! this.loc.open) return;
            if (this._locMap) { this._locMap.remove(); this._locMap = null; }
            this._locMarker = null;
            this._locMap = L.map(el).setView([startLat, startLng], hasMarker ? 13 : 4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this._locMap);
            if (hasMarker) this._locMarker = L.marker([startLat, startLng]).addTo(this._locMap);
            this._locMap.on('click', (e) => {
                this.loc.lat = e.latlng.lat; this.loc.lng = e.latlng.lng;
                if (this._locMarker) this._locMarker.setLatLng(e.latlng);
                else this._locMarker = L.marker(e.latlng).addTo(this._locMap);
            });
            setTimeout(() => { if (this._locMap) this._locMap.invalidateSize(); }, 120);
        });
    },
    saveLoc() {
        if (this.loc.lat != null) {
            if (this.loc.bulk) {
                const dlat = dec6(this.loc.lat); const dlng = dec6(this.loc.lng);
                this._eachSelected((p) => { p.lat = dlat; p.lng = dlng; });
                this.selected = [];
                this._save();
            } else if (this.loc.target) {
                const p = this.loc.target;
                // Hot record stores dec-strings; the cold meta blob keeps raw
                // numbers (never hashed for dirty-detection, §5.2).
                p.lat = dec6(this.loc.lat); p.lng = dec6(this.loc.lng);
                if (this.viewer.meta?.exif) { this.viewer.meta.exif.lat = this.loc.lat; this.viewer.meta.exif.lon = this.loc.lng; }
                this._renderMiniMap(this.loc.lat, this.loc.lng);
                this._save();
            }
        }
        this.closeLocPicker();
    },
    clearLoc() {
        if (this.loc.bulk) {
            this._eachSelected((p) => { p.lat = null; p.lng = null; });
            this.selected = [];
        } else if (this.loc.target) {
            const p = this.loc.target;
            p.lat = null; p.lng = null;
            if (this.viewer.meta?.exif) { this.viewer.meta.exif.lat = null; this.viewer.meta.exif.lon = null; }
            if (this._miniMap) { this._miniMap.remove(); this._miniMap = null; }
        }
        this._save();
        this.closeLocPicker();
    },
    closeLocPicker() {
        if (this._locMap) { this._locMap.remove(); this._locMap = null; }
        this._locMarker = null;
        this.loc = { open: false, bulk: false, target: null, lat: null, lng: null };
        this.geoQuery = ''; this.geoResults = []; this.geoBusy = false; this.geoSearched = false;
    },
    // Forward-geocode an address query (server-proxied) to candidate places.
    async geoSearch() {
        const q = this.geoQuery.trim();
        if (! q) { this.geoResults = []; this.geoSearched = false; return; }
        this.geoBusy = true;
        try {
            const data = await getJson(config.geocodeUrl + '?q=' + encodeURIComponent(q));
            this.geoResults = data.results || [];
        } catch (e) { this.geoResults = []; } finally { this.geoBusy = false; this.geoSearched = true; }
    },
    // Drop the map marker on a chosen search result.
    pickGeoResult(r) {
        if (r == null || r.lat == null || r.lng == null) return;
        this.loc.lat = r.lat; this.loc.lng = r.lng;
        this.geoResults = []; this.geoSearched = false;
        if (this._locMap) {
            this._locMap.setView([r.lat, r.lng], 14);
            if (this._locMarker) this._locMarker.setLatLng([r.lat, r.lng]);
            else this._mountLocMarker(r.lat, r.lng);
        }
    },
    async _mountLocMarker(lat, lng) {
        const L = await loadLeaflet();
        if (this._locMap && ! this._locMarker) this._locMarker = L.marker([lat, lng]).addTo(this._locMap);
    },

    /* ---- Multi-select ---- */
    selected: [],
    _lastSel: null, // last tile clicked, for shift-range selection
    isSelected(id) { return this.selected.includes(id); },
    toggleSelect(id) { const i = this.selected.indexOf(id); if (i >= 0) this.selected.splice(i, 1); else this.selected.push(id); },
    // Click a tile's checkbox: shift extends a range from the last one, otherwise toggles.
    clickSelect(id, ev) {
        if (ev && ev.shiftKey && this._lastSel && this._lastSel !== id) this.selectRange(this._lastSel, id);
        else this.toggleSelect(id);
        this._lastSel = id;
    },
    selectRange(fromId, toId) {
        const ids = this.displayGroups.flatMap((g) => g.photos.map((p) => p.id));
        const a = ids.indexOf(fromId), b = ids.indexOf(toId);
        if (a < 0 || b < 0) { this.toggleSelect(toId); return; }
        const [lo, hi] = a < b ? [a, b] : [b, a];
        for (let i = lo; i <= hi; i++) if (! this.selected.includes(ids[i])) this.selected.push(ids[i]);
    },
    // Per-day-group select-all checkbox.
    groupSelected(group) { return group.photos.length > 0 && group.photos.every((p) => this.selected.includes(p.id)); },
    toggleGroup(group) {
        const ids = group.photos.map((p) => p.id);
        if (this.groupSelected(group)) this.selected = this.selected.filter((id) => ! ids.includes(id));
        else for (const id of ids) if (! this.selected.includes(id)) this.selected.push(id);
    },
    clearSelection() { this.selected = []; this._lastSel = null; },
    get selectedCount() { return this.selected.length; },
    selectAllVisible() {
        const map = { trash: this.trashedPhotos, favorites: this.favoritePhotos, archive: this.archivedPhotos };
        const ids = (map[this.view] || this.timelinePhotos).map((p) => p.id);
        this.selected = this.selected.length === ids.length ? [] : ids;
    },
    _eachSelected(fn) { for (const id of [...this.selected]) { const p = this.index.photos.find((x) => x.id === id); if (p) fn(p); } },
    // Draft date/time for the selection, edited in its own modal (like the
    // location picker) so a half-typed value never commits.
    bulkDate: '',
    dateModal: false,
    openBulkDate() { if (! this.selectedCount) return; this.bulkDate = ''; this.dateModal = true; },
    closeBulkDate() { this.dateModal = false; this.bulkDate = ''; },
    bulkApplyDate() {
        if (! this.bulkDate) return;
        const d = new Date(this.bulkDate);
        if (isNaN(d.getTime())) return;
        const iso = d.toISOString();
        this._eachSelected((p) => { p.taken_at = iso; });
        this.bulkDate = '';
        this.dateModal = false;
        this.selected = [];
        this._save();
    },
    bulkTrash() { const t = new Date().toISOString(); this._eachSelected((p) => { if (! p.trashed) p.trashed = t; }); this.selected = []; this._save(); },
    bulkRestore() { this._eachSelected((p) => { p.trashed = null; }); this.selected = []; this._save(); },
    // Toggle: if every selected photo is already a favorite, clear them all;
    // otherwise favorite the whole selection.
    bulkFavorite() {
        const allFav = this.selected.length > 0 && this.selected.every((id) => { const p = this.index.photos.find((x) => x.id === id); return p && p.favorite; });
        this._eachSelected((p) => { p.favorite = ! allFav; }); this.selected = []; this._save();
    },
    bulkArchive() { const t = new Date().toISOString(); this._eachSelected((p) => { if (! p.archived) p.archived = t; }); this.selected = []; this._save(); },
    bulkUnarchive() { this._eachSelected((p) => { p.archived = null; }); this.selected = []; this._save(); },
    async bulkPurge() {
        if (! await this.$store.confirm.ask(labels.emptyTrashConfirm || labels.purgeConfirm || '')) return;
        this._eachSelected((p) => this._purgeOne(p)); this.selected = []; await this._persist();
    },

    /* ---- Search (metadata + CLIP content, all client-side) ---- */
    query: '',
    searchResults: null, // null = not searching; array = results
    searching: false,
    _searchTimer: null,
    get isSearching() { return this.searchResults !== null; },
    runSearch() {
        clearTimeout(this._searchTimer);
        if (! this.query.trim()) { this.searchResults = null; return; }
        this._searchTimer = setTimeout(() => this._doSearch(), 350);
    },
    clearSearch() { this.query = ''; this.searchResults = null; },
    async _doSearch() {
        const q = this.query.trim();
        if (! q) { this.searchResults = null; return; }
        this.searching = true;
        const lc = q.toLowerCase();
        // Instant metadata matches from the (already decrypted) index.
        const metaIds = this.libraryPhotos.filter((p) => (p.name || '').toLowerCase().includes(lc) || (p.camera || '').toLowerCase().includes(lc) || (p.caption || '').toLowerCase().includes(lc)).map((p) => p.id);
        // CLIP content matches: embed the text, cosine vs cached image vectors.
        let contentIds = [];
        try {
            await this._ensureEmbeddings();
            const { embedding: qv } = await postForm(config.embedTextUrl, { q });
            if (Array.isArray(qv)) {
                const qn = this._norm(qv); // normalised → cosine is a plain dot product
                const scored = Object.entries(searchEmb).map(([id, emb]) => [id, this._dot(qn, emb)]);
                scored.sort((a, b) => b[1] - a[1]);
                contentIds = scored.filter(([, s]) => s > 0.2).slice(0, 80).map(([id]) => id);
            }
        } catch (e) { /* fall back to metadata only */ }
        const seen = new Set();
        const order = [...contentIds, ...metaIds].filter((id) => (seen.has(id) ? false : seen.add(id)));
        const byId = new Map(this.libraryPhotos.map((p) => [p.id, p]));
        this.searchResults = order.map((id) => byId.get(id)).filter(Boolean);
        this.searching = false;
    },
    // Cheap vector helpers. Normalising once turns every later cosine into a
    // plain dot product (no per-pair sqrt), and Float32Array keeps the O(n²)
    // duplicate pass out of double-precision boxing.
    _norm(v) {
        let s = 0; for (let i = 0; i < v.length; i++) s += v[i] * v[i];
        const inv = s > 0 ? 1 / Math.sqrt(s) : 0;
        const out = new Float32Array(v.length);
        for (let i = 0; i < v.length; i++) out[i] = v[i] * inv;
        return out;
    },
    _dot(a, b) {
        let d = 0; const n = Math.min(a.length, b.length);
        for (let i = 0; i < n; i++) d += a[i] * b[i];
        return d;
    },
    async _ensureEmbeddings() {
        await this._ensureMeta();
        for (const p of this.libraryPhotos) {
            const m = metaCache[p.id];
            if (m && Array.isArray(m.embedding) && m.embModel === config.clipModel && ! searchEmb[p.id]) searchEmb[p.id] = this._norm(m.embedding);
        }
    },
    // Shared decrypted-metadata cache (embedding/phash/faces/place). Every
    // cross-photo feature — search, duplicates, faces — reads from here so each
    // sealed meta blob is fetched and decrypted at most once per session. Kept in
    // the factory closure (metaCache), never on reactive state.
    async _ensureMeta(photos, onProgress) {
        const list = photos || this.libraryPhotos;
        const todo = list.filter((p) => p.metaRef && ! metaCache[p.id]);
        let done = 0;
        for (const p of todo) {
            try {
                const b = await this._decryptBlob(p.metaRef, p.metaKey);
                metaCache[p.id] = JSON.parse(new TextDecoder().decode(b));
            } catch (e) { metaCache[p.id] = null; }
            if (onProgress) onProgress(++done, todo.length);
        }
    },
    cosine(a, b) {
        let dot = 0, na = 0, nb = 0;
        const n = Math.min(a.length, b.length);
        for (let i = 0; i < n; i++) { dot += a[i] * b[i]; na += a[i] * a[i]; nb += b[i] * b[i]; }
        return (na && nb) ? dot / (Math.sqrt(na) * Math.sqrt(nb)) : 0;
    },
    // Library shown as day groups, or a single flat group of search hits.
    get displayGroups() {
        if (this.searchResults !== null) {
            return this.searchResults.length ? [{ day: 'search', label: '', photos: this.searchResults }] : [];
        }
        return this.groupedPhotos;
    },

    /* ---- Viewer mini-map (single photo location) ---- */
    _miniMap: null,
    // A small, static map with one marker for the viewer info panel.
    async _renderMiniMap(lat, lng) {
        if (lat == null || lng == null) return;
        const L = await loadLeaflet();
        this.$nextTick(() => {
            const el = this.$refs.minimap;
            if (! el || ! this.viewer.open) return;
            if (this._miniMap) { this._miniMap.remove(); this._miniMap = null; }
            this._miniMap = L.map(el, { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, keyboard: false, touchZoom: false }).setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this._miniMap);
            L.marker([lat, lng]).addTo(this._miniMap);
            setTimeout(() => { if (this._miniMap) this._miniMap.invalidateSize(); }, 120);
        });
    },

    /* ---- Albums (plain client-side grouping, sealed in the index) ---- */
    activeAlbum: null,
    albumPicker: false, // "add selected to album" modal (from the bulk bar)
    get albums() {
        return (this.index.albums || []).slice().sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    },
    get currentAlbum() { return (this.index.albums || []).find((a) => a.id === this.activeAlbum) || null; },
    albumPhotos(al) {
        if (! al) return [];
        const set = new Set(al.photoIds || []);
        return this.libraryPhotos.filter((p) => set.has(p.id));
    },
    albumCount(al) { return this.albumPhotos(al).length; },
    albumCover(al) {
        const ps = this.albumPhotos(al);
        return ps.find((p) => p.id === al.cover) || ps[0] || null;
    },
    openAlbum(al) { this.activeAlbum = al.id; this.view = 'album'; },
    async createAlbum() {
        const raw = await this.$store.confirm.prompt('', { placeholder: labels.albumName || '', ok: labels.create || '' });
        const name = (raw || '').trim();
        if (! name) return;
        const al = { id: window.LLGalleryStore.newId(), name, photoIds: [...this.selected], cover: this.selected[0] || null, created: new Date().toISOString() };
        (this.index.albums = this.index.albums || []).push(al);
        this.selected = [];
        this._save();
    },
    async renameAlbum(al) {
        const raw = await this.$store.confirm.prompt('', { value: al.name, placeholder: labels.albumName || '', ok: labels.save || '' });
        const name = (raw || '').trim();
        if (name) { al.name = name; this._save(); }
    },
    async deleteAlbum(al) {
        if (! await this.$store.confirm.ask(labels.deleteAlbumConfirm || '')) return;
        if (al.share) { try { await this._revokeShareRequest(al.share.token); } catch (e) { /* best effort */ } }
        const i = (this.index.albums || []).findIndex((a) => a.id === al.id);
        if (i >= 0) this.index.albums.splice(i, 1);
        if (this.activeAlbum === al.id) { this.activeAlbum = null; this.view = 'albums'; }
        this._save();
    },
    addSelectedToAlbum(al) {
        const set = new Set(al.photoIds || []);
        for (const id of this.selected) set.add(id);
        al.photoIds = [...set];
        if (! al.cover) al.cover = al.photoIds[0] || null;
        this.selected = [];
        this._save();
    },
    removeFromAlbum(al, p) {
        al.photoIds = (al.photoIds || []).filter((id) => id !== p.id);
        if (al.cover === p.id) al.cover = al.photoIds[0] || null;
        this._save();
    },

    /* ---- Public album share links (zero-knowledge) ----
       The client seals a share manifest (photo list + each blob's per-file key
       re-wrapped under a fresh share key) and posts only ciphertext. The share
       key lives in the returned link's #fragment and in our own sealed index
       (al.share.sk) so the owner can re-copy it; it never reaches the server. --- */
    share: { open: false, album: null, busy: false, password: '', expiresAt: '', allowDownload: false, link: '', error: '' },
    openShare(al) {
        const s = al.share || {};
        this.share = {
            open: true, album: al, busy: false, error: '',
            password: '', expiresAt: s.expiresAt || '', allowDownload: ! ! s.allowDownload,
            link: s.token ? this._shareLink(s.token, s.sk) : '',
        };
    },
    closeShare() { this.share.open = false; this.share.album = null; this.share.password = ''; },
    _shareLink(token, sk) { return `${config.shareBase}/${token}#s:${sk}`; },

    // Build the sealed share manifest for an album under a given share key.
    async _buildShareManifest(al, sk, allowDownload) {
        const refs = [];
        const photos = this.albumPhotos(al);
        const entries = [];
        for (const p of photos) {
            const e = { id: p.id, t: p.media_type || 'image', at: p.taken_at || p.created || null, w: p.width || null, h: p.height || null, cap: p.caption || '' };
            const add = async (refK, keyK, outR, outK) => {
                if (! p[refK]) return;
                const fk = window.Vault.unwrapContentKey(p[keyK]);
                e[outR] = p[refK]; e[outK] = await window.ShareCrypto.wrap(fk, sk); refs.push(p[refK]);
            };
            await add('thumbRef', 'thumbKey', 'tR', 'tK');
            await add('mediumRef', 'mediumKey', 'mR', 'mK');
            await add('motionRef', 'motionKey', 'moR', 'moK');
            if (allowDownload) await add('originalRef', 'originalKey', 'oR', 'oK');
            entries.push(e);
        }
        const manifest = { name: al.name || '', allowDownload: ! ! allowDownload, photos: entries };
        const sealed = await window.ShareCrypto.wrap(new TextEncoder().encode(JSON.stringify(manifest)), sk);
        return { sealed, refs: [...new Set(refs)] };
    },

    _shareBody(sealed, refs) {
        const body = { sealed_manifest: sealed, blob_refs: refs, allow_download: this.share.allowDownload };
        if (this.share.expiresAt) body.expires_at = new Date(this.share.expiresAt).toISOString();
        if (this.share.password.trim()) body.password = this.share.password.trim();
        return body;
    },

    async createShare() {
        const al = this.share.album;
        if (! al || this.share.busy) return;
        this.share.busy = true; this.share.error = '';
        try {
            const sk = await window.ShareCrypto.newKey();
            const { sealed, refs } = await this._buildShareManifest(al, sk, this.share.allowDownload);
            const { token } = await postForm(config.sharesUrl, this._shareBody(sealed, refs));
            al.share = { token, sk, allowDownload: this.share.allowDownload, hasPassword: ! ! this.share.password.trim(), expiresAt: this.share.expiresAt || null, created: new Date().toISOString() };
            this.share.password = '';
            this.share.link = this._shareLink(token, sk);
            this._save();
        } catch (e) { this.share.error = labels.shareError || 'Error'; } finally { this.share.busy = false; }
    },

    // Re-push the manifest + settings for an existing link (same token/key), e.g.
    // after adding photos or changing the password/expiry/download options.
    async updateShare() {
        const al = this.share.album;
        if (! al || ! al.share || this.share.busy) return;
        this.share.busy = true; this.share.error = '';
        try {
            const sk = al.share.sk;
            const { sealed, refs } = await this._buildShareManifest(al, sk, this.share.allowDownload);
            const body = this._shareBody(sealed, refs);
            if (! this.share.password.trim() && ! al.share.hasPassword) body.clear_password = true;
            await postForm(`${config.sharesUrl}/${al.share.token}`, body, 'PUT');
            al.share.allowDownload = this.share.allowDownload;
            al.share.expiresAt = this.share.expiresAt || null;
            if (this.share.password.trim()) al.share.hasPassword = true;
            else if (body.clear_password) al.share.hasPassword = false;
            this.share.password = '';
            this._save();
        } catch (e) { this.share.error = labels.shareError || 'Error'; } finally { this.share.busy = false; }
    },

    _revokeShareRequest(token) {
        return postForm(`${config.sharesUrl}/${token}`, null, 'DELETE');
    },
    async revokeShare() {
        const al = this.share.album;
        if (! al || ! al.share) return;
        this.share.busy = true;
        try { await this._revokeShareRequest(al.share.token); } catch (e) { /* best effort */ }
        delete al.share; this.share.link = ''; this.share.busy = false;
        this._save();
    },
    async copyShareLink() {
        if (! this.share.link) return;
        try { await navigator.clipboard.writeText(this.share.link); window.llToast(labels.shareCopied || ''); } catch (e) { /* clipboard blocked */ }
    },

    /* ---- Scan scope (shared by faces + duplicates): 0 = whole library, N = the
       N most-recently-added photos, so a re-scan needn't re-crunch everything. --- */
    scanLimit: 0,
    _scanTargets() {
        if (! this.scanLimit) return this.libraryPhotos;
        return [...this.index.photos.filter((p) => ! p.trashed)]
            .sort((a, b) => new Date(b.created || 0) - new Date(a.created || 0))
            .slice(0, this.scanLimit);
    },

    /* ---- Duplicates (pHash Hamming + CLIP cosine, all client-side) ---- */
    dupGroups: null,
    dupScanning: false,
    dupProgress: { done: 0, total: 0 },
    async scanDuplicates() {
        this.dupScanning = true;
        this.dupGroups = null;
        try {
            const targets = this._scanTargets();
            await this._ensureMeta(targets, (d, t) => { this.dupProgress = { done: d, total: t }; });
            const items = targets.filter((p) => metaCache[p.id]);
            const N = items.length;
            // Precompute the numeric inputs once: a normalised Float32 CLIP vector
            // plus the 64-bit pHash split into two 32-bit halves (so the worker can
            // Hamming-compare with a fast integer popcount instead of BigInt).
            const emb = new Array(N), phHi = new Array(N), phLo = new Array(N), phNull = new Array(N), vid = new Array(N);
            for (let i = 0; i < N; i++) {
                const m = metaCache[items[i].id];
                emb[i] = Array.isArray(m.embedding) ? this._norm(m.embedding) : null;
                vid[i] = items[i].media_type === 'video';
                let b = null;
                // Fold to an unsigned 64-bit value so the split never sees a
                // negative (a signed int64 phash would).
                if (m.phash != null && Number.isFinite(m.phash)) { try { b = BigInt.asUintN(64, BigInt(Math.trunc(m.phash))); } catch (e) { b = null; } }
                if (b == null) { phNull[i] = true; phHi[i] = 0; phLo[i] = 0; }
                else { phNull[i] = false; phHi[i] = Number(b >> 32n) >>> 0; phLo[i] = Number(b & 0xffffffffn) >>> 0; }
            }
            // Run the O(n²) pairwise comparison off the main thread so a large
            // library no longer stutters the UI; fall back to an inline scan if the
            // worker can't be created (unsupported / blocked).
            const idxGroups = await this._computeDupGroups({ emb, phHi, phLo, phNull, vid, N });
            this.dupGroups = idxGroups
                .map((g) => g.map((i) => items[i]).sort((a, b) => (b.size || 0) - (a.size || 0)));
        } finally {
            this.dupScanning = false;
        }
    },
    // Resolve the duplicate groups (as arrays of item indices) via a Web Worker,
    // with a main-thread fallback that yields to the event loop.
    _computeDupGroups(data) {
        return new Promise((resolve) => {
            let worker;
            try {
                worker = new Worker(new URL('../scan.worker.js', import.meta.url), { type: 'module' });
            } catch (e) {
                resolve(this._dupGroupsInline(data));
                return;
            }
            worker.onmessage = (e) => {
                if (e.data.progress != null) { this.dupProgress = { done: e.data.progress, total: e.data.total }; return; }
                worker.terminate();
                if (e.data.error) { this._dupGroupsInline(data).then(resolve); return; }
                resolve(e.data.groups || []);
            };
            worker.onerror = () => { worker.terminate(); this._dupGroupsInline(data).then(resolve); };
            worker.postMessage(data);
        });
    },
    async _dupGroupsInline({ emb, phHi, phLo, phNull, vid, N }) {
        const pc = (x) => { x = x - ((x >>> 1) & 0x55555555); x = (x & 0x33333333) + ((x >>> 2) & 0x33333333); x = (x + (x >>> 4)) & 0x0f0f0f0f; return (x * 0x01010101) >>> 24; };
        const parent = new Array(N); for (let i = 0; i < N; i++) parent[i] = i;
        const find = (i) => { while (parent[i] !== i) { parent[i] = parent[parent[i]]; i = parent[i]; } return i; };
        const union = (i, j) => { const a = find(i), b = find(j); if (a !== b) parent[a] = b; };
        for (let i = 0; i < N; i++) {
            for (let j = i + 1; j < N; j++) {
                const hd = (! phNull[i] && ! phNull[j]) ? pc((phHi[i] ^ phHi[j]) >>> 0) + pc((phLo[i] ^ phLo[j]) >>> 0) : 64;
                const dup = (vid[i] || vid[j]) ? hd <= 4 : ((emb[i] && emb[j] && this._dot(emb[i], emb[j]) >= 0.97) || hd <= 3);
                if (dup) union(i, j);
            }
            if ((i & 15) === 0) { this.dupProgress = { done: i, total: N }; await new Promise((r) => setTimeout(r)); }
        }
        const groups = new Map();
        for (let i = 0; i < N; i++) { const r = find(i); if (! groups.has(r)) groups.set(r, []); groups.get(r).push(i); }
        return [...groups.values()].filter((g) => g.length > 1);
    },
    get dupTotal() { return this.dupGroups ? this.dupGroups.reduce((n, g) => n + g.length - 1, 0) : 0; },
    // Per-set trash marks: the user picks any subset to delete (multi-select),
    // keeping the rest — not just one survivor.
    dupMarked: [],
    isDupMarked(id) { return this.dupMarked.includes(id); },
    toggleDupMark(id) { const i = this.dupMarked.indexOf(id); if (i >= 0) this.dupMarked.splice(i, 1); else this.dupMarked.push(id); },
    dupMarkedCount(group) { return group.filter((p) => this.dupMarked.includes(p.id)).length; },
    // Quick action: mark every copy except the best (largest) for deletion.
    markRest(group) {
        for (let i = 1; i < group.length; i++) if (! this.isDupMarked(group[i].id)) this.dupMarked.push(group[i].id);
    },
    // Trash the marked copies in this set, keep the rest.
    trashMarked(group) {
        const t = new Date().toISOString();
        const marks = new Set(this.dupMarked);
        for (const p of group) if (marks.has(p.id) && ! p.trashed) p.trashed = t;
        const gone = new Set(group.filter((p) => marks.has(p.id)).map((p) => p.id));
        this.dupMarked = this.dupMarked.filter((id) => ! gone.has(id));
        const remaining = group.filter((p) => ! p.trashed);
        this.dupGroups = (this.dupGroups || []).map((g) => (g === group ? remaining : g)).filter((g) => g.length > 1);
        this._persist();
    },

    /* ---- People (client-side face clustering over sealed embeddings) ---- */
    faceThumbs: {},
    activePerson: null,
    peopleScanning: false,
    peopleProgress: { done: 0, total: 0 },
    // Hide clusters that have no live photos left (all trashed/purged) so a wiped
    // library doesn't leave ghost people behind.
    get people() {
        return this._cache('people', () => {
            // Precompute each person's photo count once (not inside the comparator,
            // which would rebuild it O(n log n) times).
            const rows = (this.index.people || [])
                .filter((pp) => ! pp.hidden)
                .map((pp) => ({ pp, n: this.personPhotos(pp).length }))
                .filter((r) => r.n > 0);
            // Named people first (ordered by the contacts sort pref), then the
            // unnamed rest by size.
            rows.sort((a, b) => {
                const an = (a.pp.name || '').trim(), bn = (b.pp.name || '').trim();
                if (an && ! bn) return -1;
                if (! an && bn) return 1;
                if (an && bn) return this._personSortKey(a.pp).localeCompare(this._personSortKey(b.pp));
                return b.n - a.n;
            });
            return rows.map((r) => r.pp);
        });
    },
    get currentPerson() { return (this.index.people || []).find((pp) => pp.id === this.activePerson) || null; },
    // Contacts (from /store) keyed by id, so linked People render exactly like the
    // contacts module — same "Last, First" label and same sort pref.
    _peopleContacts: {},
    async _loadPeopleContacts() {
        if (! (this.index.people || []).some((pp) => pp.contactId)) return;
        try {
            if (await bootStore(this.$store, 'contacts')) {
                const map = {};
                for (const c of (window.LLModuleStore.contacts.data?.contacts || [])) if (! c.trashed) map[c.id] = c;
                this._peopleContacts = map;
                this._mut++; // invalidate the people memo so the new labels/order apply
            }
        } catch (e) { /* fall back to the stored snapshot parts */ }
    },

    /* ---- Birthdays: surface a person whose linked contact has a birthday today.
       The contact's BDAY (ISO YYYY-MM-DD) lives in the decrypted contacts store,
       loaded by _loadPeopleContacts; matching is by month+day, age by year. --- */
    _bdayToday(iso) {
        if (! iso || iso.length < 10) return false;
        const now = new Date();
        const [, m, d] = iso.split('-').map(Number);
        return m === now.getMonth() + 1 && d === now.getDate();
    },
    _ageOn(iso, when = new Date()) {
        if (! iso || iso.length < 10) return null;
        const [y, m, d] = iso.split('-').map(Number);
        if (! y || y < 1000) return null; // BDAY without a year (--MM-DD) → no age
        let a = when.getFullYear() - y;
        if (when.getMonth() + 1 < m || (when.getMonth() + 1 === m && when.getDate() < d)) a--;
        return a >= 0 ? a : null;
    },
    personBday(pp) { const c = pp?.contactId ? this._peopleContacts[pp.contactId] : null; return c?.bday || ''; },
    personAge(pp) { return this._ageOn(this.personBday(pp)); },
    // Linked people whose contact birthday is today (and who actually have photos).
    get birthdayPeople() {
        return this._cache('bdayppl', () => (this.index.people || [])
            .filter((pp) => ! pp.hidden && pp.contactId)
            .map((pp) => { const bday = this.personBday(pp); return this._bdayToday(bday) ? { pp, bday, age: this._ageOn(bday), photos: this.personPhotos(pp) } : null; })
            .filter((x) => x && x.photos.length));
    },

    // Display label for a person: mirror the linked contact's "Last, First" label
    // (live record if loaded, else the snapshot parts captured at link time).
    personLabel(pp) {
        if (pp?.contactId) {
            const c = this._peopleContacts[pp.contactId] || { first: pp.contactFirst, last: pp.contactLast, fn: pp.contactName };
            const label = contactDisplayName(c);
            if (label) return label;
        }
        return (pp?.name || '').trim();
    },
    // Sort key following the contacts module's persisted sort mode.
    _personSortKey(pp) {
        const mode = contactsSortPref();
        if (pp?.contactId) {
            const c = this._peopleContacts[pp.contactId] || { first: pp.contactFirst, last: pp.contactLast, fn: pp.contactName };
            const { first, last } = contactNameParts(c);
            if (mode === 'first') return (first || last || '').toLowerCase();
            if (mode === 'last') return (last || first || '').toLowerCase();
            return (contactDisplayName(c) || '').toLowerCase();
        }
        return (pp?.name || '').toLowerCase();
    },
    openPerson(pp) { this.activePerson = pp.id; this.view = 'person'; },
    openPersonById(id) {
        const pp = (this.index.people || []).find((x) => x.id === id);
        if (! pp) return;
        this.closeViewer();
        this.openPerson(pp);
    },
    // Detected faces on the currently open photo, each tagged with the person it
    // was clustered into (if any). Drives the "Faces" section in the info panel.
    viewerFaces() {
        const p = this.viewer.photo, m = this.viewer.meta;
        if (! p || ! m || ! Array.isArray(m.faces)) return [];
        const out = [];
        m.faces.forEach((f, idx) => {
            if (! f.cropRef) return;
            const person = (this.index.people || []).find((pp) => (pp.faces || []).some((x) => x.photoId === p.id && x.idx === idx));
            out.push({ cropRef: f.cropRef, cropKey: f.cropKey, name: person?.name || '', personId: person?.id || null });
        });
        return out;
    },
    /* ---- Manual face tagging: draw a box → detect → assign to a person ---- */
    faceTag: { active: false, drawing: false, box: null, busy: false },
    _manualFace: null,
    // Drawing needs a linear screen↔pixel map, so only at identity orientation.
    canTagFace() {
        const p = this.viewer.photo;
        return !! p && this.viewer.kind === 'image' && ! p.rotation && ! p.flipH && ! p.flipV;
    },
    toggleFaceTag() {
        if (! this.faceTag.active && ! this.canTagFace()) { window.llToast?.(labels.faceTagReset || 'Reset rotation/flip first'); return; }
        const activating = ! this.faceTag.active;
        this.faceTag = { active: activating, drawing: false, box: null, busy: false };
        if (activating) { window.llToast?.(labels.faceTagHint || 'Tap a face, or drag a box around it.'); }
    },
    faceDragStart(e) {
        if (! this.faceTag.active || this.faceTag.busy) return;
        // Capture on the overlay (currentTarget) so every pointermove/up routes
        // here even if the drawn box slides under the cursor mid-drag.
        e.currentTarget.setPointerCapture?.(e.pointerId);
        const r = this.$refs.vimg.getBoundingClientRect();
        this._fdOrigin = { x: e.clientX - r.left, y: e.clientY - r.top };
        this.faceTag.drawing = true;
        this.faceTag.box = { x: this._fdOrigin.x, y: this._fdOrigin.y, w: 0, h: 0 };
    },
    faceDragMove(e) {
        if (! this.faceTag.drawing) return;
        const r = this.$refs.vimg.getBoundingClientRect();
        const x = Math.max(0, Math.min(r.width, e.clientX - r.left));
        const y = Math.max(0, Math.min(r.height, e.clientY - r.top));
        this.faceTag.box = { x: Math.min(x, this._fdOrigin.x), y: Math.min(y, this._fdOrigin.y), w: Math.abs(x - this._fdOrigin.x), h: Math.abs(y - this._fdOrigin.y) };
    },
    async faceDragEnd() {
        if (! this.faceTag.drawing) return;
        this.faceTag.drawing = false;
        let b = this.faceTag.box;
        const img = this.$refs.vimg;
        // A tap / tiny drag → synthesize a sensible square around the point so
        // tagging works like "tap a face" (Google/Apple), not only draw-a-box.
        if (img && (! b || b.w < 16 || b.h < 16)) {
            const side = Math.max(64, Math.round(Math.min(img.clientWidth, img.clientHeight) * 0.18));
            const cx = this._fdOrigin ? this._fdOrigin.x : (b ? b.x : 0);
            const cy = this._fdOrigin ? this._fdOrigin.y : (b ? b.y : 0);
            b = {
                x: Math.max(0, Math.min(img.clientWidth - side, cx - side / 2)),
                y: Math.max(0, Math.min(img.clientHeight - side, cy - side / 2)),
                w: side, h: side,
            };
        }
        if (! b || b.w < 16 || b.h < 16) { this.faceTag.box = null; return; }
        await this._analyzeManualFace(b);
    },
    // Crop the drawn region (padded) from the displayed image, run detection on
    // it, take the best face, seal it into the photo meta, open the assign picker.
    async _analyzeManualFace(boxPx) {
        const img = this.$refs.vimg, p = this.viewer.photo;
        if (! img || ! p) return;
        this.faceTag.busy = true;
        try {
            const sx = img.naturalWidth / img.clientWidth, sy = img.naturalHeight / img.clientHeight;
            const padX = boxPx.w * 0.45, padY = boxPx.h * 0.45;
            let nx = Math.max(0, (boxPx.x - padX) * sx), ny = Math.max(0, (boxPx.y - padY) * sy);
            let nw = Math.min(img.naturalWidth - nx, (boxPx.w + 2 * padX) * sx), nh = Math.min(img.naturalHeight - ny, (boxPx.h + 2 * padY) * sy);
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(nw); canvas.height = Math.round(nh);
            canvas.getContext('2d').drawImage(img, nx, ny, nw, nh, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.92));
            const fd = new FormData();
            fd.append('_token', config.token);
            fd.append('file', new File([blob], 'face.jpg', { type: 'image/jpeg' }));
            const res = await fetch(config.analyzeUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            if (! res.ok) throw new Error('http');
            const d = await res.json();
            const det = (d.faces || []).filter((f) => Array.isArray(f.embedding)).sort((a, b) => (b.score || 0) - (a.score || 0))[0];
            if (! det) { window.llToast?.(labels.faceTagNone || 'No face found — draw a larger area'); this.faceTag.box = null; return; }
            let meta = metaCache[p.id];
            if (! meta) { try { meta = JSON.parse(new TextDecoder().decode(await this._decryptBlob(p.metaRef, p.metaKey))); } catch (e) { meta = { faces: [] }; } }
            if (! Array.isArray(meta.faces)) meta.faces = [];
            const cropBytes = det.crop ? this._b64bytes(det.crop) : new Uint8Array(await blob.arrayBuffer());
            const cr = await this._encStore(cropBytes, 'crop.enc');
            const face = { score: det.score, box: det.box, embedding: det.embedding, cropRef: cr.id, cropKey: cr.key, manual: true };
            const idx = meta.faces.push(face) - 1;
            const mr = await this._encStore(new TextEncoder().encode(JSON.stringify(meta)), 'meta.enc');
            p.metaRef = mr.id; p.metaKey = mr.key;
            metaCache[p.id] = meta;
            p.hasFaces = meta.faces.length;
            p.faceCropRefs = meta.faces.map((f) => f.cropRef).filter(Boolean);
            this.viewer.meta = meta;
            this._save();
            this._manualFace = { photoId: p.id, idx, cropRef: cr.id, cropKey: cr.key, embedding: det.embedding };
            this.faceTag.box = null; this.faceTag.active = false;
            this.assignQuery = ''; this.assignPicker = true;
        } catch (e) {
            window.llToast?.(labels.faceTagFailed || 'Could not analyze the face');
            this.faceTag.box = null;
        } finally {
            this.faceTag.busy = false;
        }
    },
    /* ---- Assign a manually tagged face to a person (existing or new) ---- */
    assignPicker: false,
    assignQuery: '',
    closeAssign() { this.assignPicker = false; this._manualFace = null; },
    assignCandidates() {
        const q = this.assignQuery.trim().toLowerCase();
        let list = (this.index.people || []).filter((pp) => ! pp.hidden);
        if (q) list = list.filter((pp) => (pp.name || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    },
    assignToPerson(pp) {
        const mf = this._manualFace;
        if (! mf || ! pp) return;
        if (! (pp.faces || (pp.faces = [])).some((f) => f.photoId === mf.photoId && f.idx === mf.idx)) {
            this._centroidAdd(pp, mf.embedding);
            pp.faces.push({ photoId: mf.photoId, idx: mf.idx, cropRef: mf.cropRef, cropKey: mf.cropKey, manual: true });
        }
        pp.pinned = true; // manual training — anchor this person in every future scan
        this._save();
        this.closeAssign();
    },
    assignToNew() {
        const mf = this._manualFace;
        if (! mf) return;
        const pp = { id: window.LLGalleryStore.newId(), name: this.assignQuery.trim(), hidden: false, pinned: true, centroid: mf.embedding.slice(), faces: [{ photoId: mf.photoId, idx: mf.idx, cropRef: mf.cropRef, cropKey: mf.cropKey, manual: true }] };
        (this.index.people = this.index.people || []).push(pp);
        this._save();
        this.closeAssign();
    },
    personPhotos(pp) {
        if (! pp) return [];
        const byId = this._photoIndex(); // memoised map, not rebuilt per call
        const ids = [...new Set((pp.faces || []).map((f) => f.photoId))];
        // Newest capture first — not the order faces happened to be detected.
        return ids.map((id) => byId.get(id)).filter(Boolean)
            .sort((a, b) => new Date(b.taken_at || b.created || 0) - new Date(a.taken_at || a.created || 0));
    },
    personCount(pp) { return this.personPhotos(pp).length; },
    async faceThumb(f) {
        if (! f || ! f.cropRef) return '';
        if (this.faceThumbs[f.cropRef]) return this.faceThumbs[f.cropRef];
        try {
            const bytes = await this._decryptBlob(f.cropRef, f.cropKey);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this.faceThumbs[f.cropRef] = url;
            return url;
        } catch (e) { return ''; }
    },
    personCover(pp) { return (pp.faces || [])[0] || null; },
    // Choose which photo's face represents the person in the people grid: move
    // that face to the front (personCover returns faces[0]).
    setPersonCover(photo) {
        const pp = this.currentPerson;
        if (! pp || ! photo) return;
        const i = (pp.faces || []).findIndex((f) => f.photoId === photo.id);
        if (i > 0) { const [f] = pp.faces.splice(i, 1); pp.faces.unshift(f); this._save(); }
    },
    async scanFaces() {
        this.peopleScanning = true;
        try {
            const targets = this._scanTargets();
            const incremental = this.scanLimit > 0;
            await this._ensureMeta(targets, (d, t) => { this.peopleProgress = { done: d, total: t }; });
            // Collect every detected face carrying an embedding + a crop blob.
            const faces = [];
            for (const p of targets) {
                const m = metaCache[p.id];
                if (! m || ! Array.isArray(m.faces)) continue;
                m.faces.forEach((f, idx) => {
                    if (Array.isArray(f.embedding) && f.cropRef) {
                        faces.push({ emb: Array.from(f.embedding), meta: { photoId: p.id, idx, cropRef: f.cropRef, cropKey: f.cropKey } });
                    }
                });
            }
            // Unwrap Alpine's reactive proxies into plain arrays/objects — the Web
            // Worker's structured clone can't serialise a Proxy (DataCloneError).
            const seedShape = (pp) => ({ id: pp.id, name: pp.name || '', hidden: ! ! pp.hidden, pinned: ! ! pp.pinned, centroid: Array.from(pp.centroid || []), members: (pp.faces || []).map((f) => ({ photoId: f.photoId, idx: f.idx, cropRef: f.cropRef, cropKey: f.cropKey, manual: ! ! f.manual })) });
            // A scoped (incremental) scan seeds from the existing people so new
            // faces merge into them; a full scan starts empty and carries names
            // over by matching the previous scan's centroids. Manually trained
            // (pinned) people ALWAYS seed — even a full scan — so a hand-drawn face
            // anchors its person and pulls matching faces in from other photos.
            const withCentroid = (this.index.people || []).filter((pp) => Array.isArray(pp.centroid) && pp.centroid.length);
            const base = incremental ? withCentroid : withCentroid.filter((pp) => pp.pinned);
            const seedIds = new Set();
            const seeds = [];
            for (const pp of base) { if (! seedIds.has(pp.id)) { seedIds.add(pp.id); seeds.push(seedShape(pp)); } }
            const prev = incremental ? [] : (this.index.people || []).filter((pp) => pp.centroid).map((pp) => ({ name: pp.name || '', hidden: ! ! pp.hidden, centroid: Array.from(pp.centroid || []) }));

            // Cluster off the main thread (falls back to an inline pass). New
            // clusters come back with id=null; assign store ids here.
            let built = await this._computeFaceClusters({ faces, seeds, prev, incremental });
            built = built.map((b) => (b.id ? b : { ...b, id: window.LLGalleryStore.newId() }));

            // Preserve people the scan couldn't rebuild: incremental keeps any that
            // weren't seeded; every scan keeps pinned (manually trained) people so a
            // hand-tagged person is never dropped.
            const builtIds = new Set(built.map((b) => b.id));
            for (const pp of (this.index.people || [])) {
                if (builtIds.has(pp.id)) continue;
                if (pp.pinned || (incremental && (pp.faces || []).length >= 2)) built.push(pp);
            }
            // Carry the pinned flag onto rebuilt clusters (worker returns plain data).
            const pinnedIds = new Set((this.index.people || []).filter((pp) => pp.pinned).map((pp) => pp.id));
            for (const b of built) if (pinnedIds.has(b.id)) b.pinned = true;
            this.index.people = built;
            this._save();
        } finally {
            this.peopleScanning = false;
        }
    },
    // Resolve face clusters via the Web Worker, with a main-thread fallback.
    _computeFaceClusters(data) {
        return new Promise((resolve) => {
            let worker;
            try {
                worker = new Worker(new URL('../scan.worker.js', import.meta.url), { type: 'module' });
            } catch (e) {
                resolve(this._faceClustersInline(data));
                return;
            }
            worker.onmessage = (e) => {
                if (e.data.progress != null) { this.peopleProgress = { done: e.data.progress, total: e.data.total }; return; }
                worker.terminate();
                if (e.data.error) { this._faceClustersInline(data).then(resolve); return; }
                resolve(e.data.built || []);
            };
            worker.onerror = () => { worker.terminate(); this._faceClustersInline(data).then(resolve); };
            // postMessage can throw synchronously (e.g. DataCloneError on a value
            // structured-clone can't serialise) — fall back to the inline pass.
            try { worker.postMessage({ type: 'faces', ...data }); }
            catch (e) { worker.terminate(); this._faceClustersInline(data).then(resolve); }
        });
    },
    async _faceClustersInline({ faces, seeds, prev, incremental }) {
        const clusters = [];
        const placed = new Set();
        for (const s of seeds) {
            clusters.push({ id: s.id, name: s.name || '', hidden: ! ! s.hidden, pinned: ! ! s.pinned, centroid: s.centroid.slice(), count: s.members.length, members: s.members });
            for (const m of s.members) placed.add(m.photoId + ':' + m.idx);
        }
        let fi = 0;
        for (const face of faces) {
            if ((++fi & 127) === 0) { this.peopleProgress = { done: fi, total: faces.length }; await new Promise((r) => setTimeout(r)); }
            const key = face.meta.photoId + ':' + face.meta.idx;
            if (placed.has(key)) continue;
            placed.add(key);
            let best = null, bestSim = 0.5;
            for (const c of clusters) { const s = this.cosine(face.emb, c.centroid); if (s > bestSim) { bestSim = s; best = c; } }
            if (best) {
                const n = best.count || best.members.length;
                for (let i = 0; i < best.centroid.length; i++) best.centroid[i] = (best.centroid[i] * n + face.emb[i]) / (n + 1);
                best.count = n + 1;
                best.members.push(face.meta);
            } else {
                clusters.push({ id: null, name: '', hidden: false, centroid: face.emb.slice(), count: 1, members: [face.meta] });
            }
        }
        return clusters.filter((c) => c.members.length >= 2 || c.pinned)
            .sort((a, b) => b.members.length - a.members.length)
            .map((c) => {
                let name = c.name || '', hidden = ! ! c.hidden;
                if (! incremental && ! c.pinned) {
                    let bestSim = 0.6, match = null;
                    for (const pp of prev) { if (! pp.centroid) continue; const s = this.cosine(c.centroid, pp.centroid); if (s > bestSim) { bestSim = s; match = pp; } }
                    if (match) { name = match.name || ''; hidden = ! ! match.hidden; }
                }
                return { id: c.id, name, hidden, pinned: ! ! c.pinned, centroid: c.centroid, faces: c.members };
            });
    },
    async renamePerson(pp) {
        const raw = await this.$store.confirm.prompt('', { value: pp.name || '', placeholder: labels.personName || '', ok: labels.save || '' });
        if (raw === null) return;
        pp.name = raw.trim(); this._save();
    },
    hidePerson(pp) {
        pp.hidden = true;
        if (this.activePerson === pp.id) { this.activePerson = null; this.view = 'people'; }
        this._save();
    },

    /* ---- Merge two people into one ---- */
    mergePicker: false,
    mergeQuery: '',
    openMergePicker() { if (this.currentPerson) { this.mergeQuery = ''; this.mergePicker = true; } },
    closeMergePicker() { this.mergePicker = false; },
    // Every other visible person that could be merged into the current one, filtered
    // by the search box and ordered named-first (alphabetical), then the unnamed.
    mergeCandidates() {
        const q = this.mergeQuery.trim().toLowerCase();
        let list = (this.index.people || []).filter((pp) => pp.id !== this.activePerson && ! pp.hidden && this.personPhotos(pp).length > 0);
        if (q) list = list.filter((pp) => (pp.name || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => {
            const an = (a.name || '').trim(), bn = (b.name || '').trim();
            if (an && ! bn) return -1;
            if (! an && bn) return 1;
            if (an && bn) return an.localeCompare(bn);
            return this.personCount(b) - this.personCount(a);
        });
    },
    // Merge `other` INTO the current person: combine faces (dedup), average the
    // centroids by face count so future scans still match, keep a name, and drop
    // the merged-away person. Client-side over the sealed index — one save.
    // Merge person `other` into `target` (combine faces + weighted centroid, keep
    // the target's name/contact, drop `other`). Shared by the manual merge picker
    // and the same-name auto-merge.
    _mergePair(target, other) {
        if (! target || ! other || target.id === other.id) return;
        const aFaces = target.faces || (target.faces = []);
        const bFaces = other.faces || [];
        const na = aFaces.length, nb = bFaces.length;
        // Weighted-mean centroid (guard shape/absence so a legacy person still merges).
        if (Array.isArray(target.centroid) && Array.isArray(other.centroid) && target.centroid.length === other.centroid.length && (na + nb) > 0) {
            target.centroid = target.centroid.map((v, i) => (v * na + other.centroid[i] * nb) / (na + nb));
        } else if (! Array.isArray(target.centroid) && Array.isArray(other.centroid)) {
            target.centroid = other.centroid.slice();
        }
        const seen = new Set(aFaces.map((f) => f.photoId + ':' + f.idx));
        for (const f of bFaces) { const k = f.photoId + ':' + f.idx; if (! seen.has(k)) { seen.add(k); aFaces.push(f); } }
        // Keep the target's name; adopt the other's only if the target is unnamed.
        if (! (target.name || '').trim() && (other.name || '').trim()) target.name = other.name;
        if (other.pinned) target.pinned = true;
        if (other.contactId && ! target.contactId) {
            target.contactId = other.contactId; target.contactName = other.contactName;
            target.contactFirst = other.contactFirst; target.contactLast = other.contactLast;
            target.contactAvatarRef = other.contactAvatarRef; target.contactAvatarKey = other.contactAvatarKey;
        }
        this.index.people = (this.index.people || []).filter((pp) => pp.id !== other.id);
    },
    mergeInto(other) {
        const target = this.currentPerson;
        if (! target || ! other || target.id === other.id) { this.mergePicker = false; return; }
        this._mergePair(target, other);
        this.mergePicker = false;
        this._save();
    },
    // Count of person clusters that share a name with another (the duplicates).
    duplicatePeopleCount() {
        return this._cache('dupPeople', () => {
            const byName = {};
            for (const pp of (this.index.people || [])) {
                const k = (pp.name || '').trim().toLowerCase();
                if (k) byName[k] = (byName[k] || 0) + 1;
            }
            return Object.values(byName).reduce((s, n) => s + (n > 1 ? n - 1 : 0), 0);
        });
    },
    // Consolidate clusters that carry the SAME name — same name means the user (or
    // a linked contact) already declared them one person, so the clustering simply
    // fragmented them. Merges each group into its largest cluster.
    async mergeDuplicates() {
        const byName = {};
        for (const pp of (this.index.people || [])) {
            const k = (pp.name || '').trim().toLowerCase();
            if (! k) continue;
            (byName[k] = byName[k] || []).push(pp);
        }
        const groups = Object.values(byName).filter((g) => g.length > 1);
        const count = groups.reduce((s, g) => s + g.length - 1, 0);
        if (! count) { window.llToast?.(labels.mergeDupNone || 'No same-named clusters to merge.'); return; }
        if (! await this.$store.confirm.ask((labels.mergeDupConfirm || 'Merge :n duplicate clusters?').replace(':n', count))) return;
        for (const g of groups) {
            // Prefer a contact-linked cluster as the survivor so its curated cover
            // and avatar are kept; fall back to the largest (best centroid).
            g.sort((a, b) => ((a.contactId ? 0 : 1) - (b.contactId ? 0 : 1)) || ((b.faces?.length || 0) - (a.faces?.length || 0)));
            const target = g[0];
            for (let i = 1; i < g.length; i++) this._mergePair(target, g[i]);
        }
        this._save();
        window.llToast?.((labels.mergeDupDone || 'Merged :n clusters.').replace(':n', count));
    },

    /* ---- Correct a face: remove from a person, or reassign to another ---- */
    reassignFor: null, // the photo being reassigned (opens the target picker)
    openReassign(photo) { this.reassignFor = photo; },
    closeReassign() { this.reassignFor = null; },

    // The stored face embedding for a person-face descriptor, from the photo meta.
    _faceEmb(f) { return metaCache[f.photoId]?.faces?.[f.idx]?.embedding || null; },
    // Update a person's running-mean centroid when a face leaves / joins. Call
    // BEFORE mutating pp.faces so the count reflects the pre-change size.
    _centroidRemove(pp, emb) {
        const n = (pp.faces || []).length;
        if (! Array.isArray(emb) || ! Array.isArray(pp.centroid) || n <= 1) return;
        for (let i = 0; i < pp.centroid.length && i < emb.length; i++) pp.centroid[i] = (pp.centroid[i] * n - emb[i]) / (n - 1);
    },
    _centroidAdd(pp, emb) {
        if (! Array.isArray(emb)) return;
        if (! Array.isArray(pp.centroid)) { pp.centroid = emb.slice(); return; }
        const n = (pp.faces || []).length;
        for (let i = 0; i < pp.centroid.length && i < emb.length; i++) pp.centroid[i] = (pp.centroid[i] * n + emb[i]) / (n + 1);
    },
    // Drop a person that fell below the 2-face cluster floor, leaving its view.
    _dropIfEmpty(pp) {
        if ((pp.faces || []).length >= 2) return false;
        this.index.people = (this.index.people || []).filter((x) => x.id !== pp.id);
        if (this.activePerson === pp.id) { this.activePerson = null; this.view = 'people'; }
        return true;
    },

    // "Not this person": remove the current person's face(s) in this photo.
    async removeFaceFromPerson(photo) {
        const pp = this.currentPerson;
        if (! pp || ! photo) return;
        const removed = (pp.faces || []).filter((f) => f.photoId === photo.id);
        if (! removed.length) return;
        await this._ensureMeta([photo]);
        for (const f of removed) { this._centroidRemove(pp, this._faceEmb(f)); pp.faces = pp.faces.filter((x) => x !== f); }
        this._dropIfEmpty(pp);
        this._save();
    },

    // Reassign this photo's face(s) from the current person to `target`.
    async moveFaceToPerson(target) {
        const photo = this.reassignFor;
        const pp = this.currentPerson;
        if (! pp || ! target || ! photo || target.id === pp.id) { this.reassignFor = null; return; }
        const moving = (pp.faces || []).filter((f) => f.photoId === photo.id);
        if (! moving.length) { this.reassignFor = null; return; }
        await this._ensureMeta([photo]);
        const tf = target.faces || (target.faces = []);
        const seen = new Set(tf.map((f) => f.photoId + ':' + f.idx));
        for (const f of moving) {
            const emb = this._faceEmb(f);
            this._centroidRemove(pp, emb);
            pp.faces = pp.faces.filter((x) => x !== f);
            this._centroidAdd(target, emb);
            const k = f.photoId + ':' + f.idx;
            if (! seen.has(k)) { seen.add(k); tf.push(f); }
        }
        this._dropIfEmpty(pp);
        this.reassignFor = null;
        this._save();
    },

    /* ---- Link a person to a Contact (cross-manifest: /gallery/store + /store) ---- */
    linkPicker: false,
    linkLoading: false,
    linkQuery: '',
    _linkContacts: [],
    // Normalise a display name to natural "First Last" order. Some contacts
    // (vCard-imported) carry an FN like "Last, First"; the contacts module also
    // snapshots links as "Last, First". Flip that single-comma form so People
    // always shows one consistent order.
    _normName(n) {
        n = (n || '').trim();
        const i = n.indexOf(',');
        if (i > 0 && n.indexOf(',', i + 1) === -1) {
            const last = n.slice(0, i).trim(), first = n.slice(i + 1).trim();
            if (last && first) return first + ' ' + last;
        }
        return n;
    },
    _contactName(c) {
        return this._normName((c.fn || [c.first, c.last].filter(Boolean).join(' ') || c.org || (c.emails ?? [])[0]?.value || '').trim());
    },
    // Open the contact picker — lazily boots the /store manifest (contacts live
    // there, a different sealed manifest than the gallery), suggests by name.
    async openLinkPicker() {
        if (! this.currentPerson) return;
        this.linkLoading = true;
        this.linkQuery = '';
        try {
            if (! await bootStore(this.$store, 'contacts')) return;
            this._linkContacts = (window.LLModuleStore.contacts.data.contacts || []).filter((c) => ! c.trashed);
            this.linkPicker = true;
        } finally { this.linkLoading = false; }
    },
    closeLinkPicker() { this.linkPicker = false; },
    // Search-filtered; named-match first (auto-suggest), then the rest by name.
    linkSuggestions() {
        const q = this.linkQuery.trim().toLowerCase();
        const name = (this.currentPerson?.name || '').trim().toLowerCase();
        let list = this._linkContacts;
        if (q) list = list.filter((c) => this._contactName(c).toLowerCase().includes(q) || (c.org || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => {
            const am = name && this._contactName(a).toLowerCase().includes(name) ? 0 : 1;
            const bm = name && this._contactName(b).toLowerCase().includes(name) ? 0 : 1;
            return (am - bm) || this._contactName(a).localeCompare(this._contactName(b));
        });
    },
    // Link the current person to a contact: write the person snapshot (name +
    // avatar ref/key, so the gallery renders it without loading /store) and set
    // contact.personId; the person adopts the contact's name; optionally seed the
    // contact photo from the cover face.
    async linkTo(contact) {
        const p = this.currentPerson;
        if (! p || ! contact) { this.linkPicker = false; return; }
        const cname = this._contactName(contact);
        if (cname) p.name = cname; // the person takes over the contact's name
        p.contactId = contact.id;
        p.contactName = cname;
        const parts = contactNameParts(contact);
        p.contactFirst = parts.first; p.contactLast = parts.last; // snapshot for "Last, First" display
        this._peopleContacts[contact.id] = contact; // resolve the label without a reload
        p.contactAvatarRef = contact.avatarRef || null;
        p.contactAvatarKey = contact.avatarKey || null;
        contact.personId = p.id;
        contact.personName = p.name || ''; // snapshot so the contact page shows it
        contact.updated = new Date().toISOString();
        this._save();
        window.LLModuleStore.contacts.touch(); // persist the contacts store side too
        this.linkPicker = false;
        // Second step: let the user pick which of the person's photos becomes the
        // contact avatar (and crop it). Skippable if the person has no photos.
        this._avatarContact = contact;
        this._choosePhotos = this.personPhotos(p);
        if (this._choosePhotos.length) this.avatarChoose = true;
    },
    // ---- Pick + crop a photo for the linked contact's avatar ----
    avatarChoose: false,
    _avatarContact: null,
    _choosePhotos: [],
    closeAvatarChoose() { this.avatarChoose = false; this._avatarContact = null; this._choosePhotos = []; },
    async chooseAvatarPhoto(photo) {
        const contact = this._avatarContact;
        if (! contact || ! photo) return;
        this.avatarChoose = false;
        try {
            const ref = photo.mediumRef || photo.originalRef || photo.thumbRef;
            const key = photo.mediumKey || photo.originalKey || photo.thumbKey;
            const bytes = await this._decryptBlob(ref, key);
            const out = await window.llCrop(new Blob([bytes], { type: 'image/jpeg' }));
            if (out) await this._bytesToContactAvatar(contact, out);
        } catch (e) { /* best effort */ } finally { this.closeAvatarChoose(); }
    },
    // Encrypt avatar bytes, upload to the contacts blob store, update both sides.
    async _bytesToContactAvatar(contact, bytes) {
        try {
            const enc = window.Vault.encryptContent(bytes, { name: 'avatar.jpg', mime: 'image/jpeg' });
            const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
            const fd = new FormData();
            fd.append('_token', config.token);
            fd.append('file', cipher, cipher.name);
            const res = await fetch('/contacts/upload', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            if (! res.ok) return;
            const ref = (await res.json()).id;
            const old = contact.avatarRef;
            contact.avatarRef = ref; contact.avatarKey = enc.encFileKey;
            contact.updated = new Date().toISOString();
            // Seed the decrypt cache from the bytes we already hold so the gallery
            // <img> (keyed by ref) updates immediately instead of blanking until a
            // reload re-runs x-init. Revoke the stale object URL to avoid a leak.
            try {
                if (old && this._contactAvatars[old]) { URL.revokeObjectURL(this._contactAvatars[old]); delete this._contactAvatars[old]; }
                this._contactAvatars[ref] = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            } catch (e) { /* non-fatal; falls back to fetchDecrypt */ }
            // Refresh the person snapshot so the gallery renders the new avatar.
            const p = (this.index.people || []).find((pp) => pp.id === contact.personId);
            if (p) { p.contactAvatarRef = ref; p.contactAvatarKey = enc.encFileKey; this._save(); }
            window.LLModuleStore.contacts.touch();
            if (old) { try { await fetch(`/contacts/blob/${old}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': config.token, 'X-Requested-With': 'XMLHttpRequest' } }); } catch (e) { /* orphan sweep handles it */ } }
        } catch (e) { /* best effort */ }
    },
    async unlinkContact() {
        const p = this.currentPerson;
        if (! p?.contactId) return;
        const cid = p.contactId;
        p.contactId = null; p.contactName = null; p.contactFirst = null; p.contactLast = null; p.contactAvatarRef = null; p.contactAvatarKey = null;
        this._save();
        try {
            if (await bootStore(this.$store, 'contacts')) {
                const c = (window.LLModuleStore.contacts.data?.contacts || []).find((x) => x.id === cid);
                if (c && c.personId === p.id) { c.personId = null; c.personName = null; window.LLModuleStore.contacts.touch(); }
            }
        } catch (e) { /* best effort */ }
    },
    // Decrypt + cache the linked contact's avatar (from the person snapshot).
    _contactAvatars: {},
    async contactAvatarFor(p) {
        if (! p?.contactAvatarRef) return '';
        if (this._contactAvatars[p.contactAvatarRef]) return this._contactAvatars[p.contactAvatarRef];
        try {
            const bytes = await fetchDecrypt('/contacts/raw', p.contactAvatarRef, p.contactAvatarKey);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this._contactAvatars[p.contactAvatarRef] = url;
            return url;
        } catch (e) { return ''; }
    },

    /* ---- Trash (soft-delete → recoverable) ---- */
    trash(p) { p.trashed = new Date().toISOString(); this._save(); },
    restore(p) { p.trashed = null; this._save(); },
    toggleFavorite(p) { if (! p) return; p.favorite = ! p.favorite; this._save(); },
    archivePhoto(p) { if (! p) return; p.archived = new Date().toISOString(); this._save(); },
    unarchive(p) { if (! p) return; p.archived = null; this._save(); },
    // Free-text caption/description, editable in the viewer and folded into search.
    setCaption(p, text) { if (! p) return; const v = (text || '').trim(); if ((p.caption || '') === v) return; p.caption = v; this._save(); },
    async purge(p) {
        if (! await this.$store.confirm.ask(labels.purgeConfirm || labels.deleteConfirm || '')) return;
        this._purgeOne(p);
        await this._persist();
    },
    async emptyTrash() {
        if (! this.trashCount()) return;
        if (! await this.$store.confirm.ask(labels.emptyTrashConfirm || '')) return;
        for (const p of this.index.photos.filter((x) => x.trashed)) this._purgeOne(p);
        await this._persist();
    },
    // Persist the index immediately. Destructive ops can't wait for the debounce —
    // a reload right after emptying the trash would otherwise bring photos back.
    async _persist() {
        if (this.state !== 'ready') return;
        window.LLGalleryStore.touch(); // arm the debounce as a retry backstop
        try { await window.LLGalleryStore.flush(); } catch (e) { /* backstop fires later */ }
    },
    _purgeOne(p) {
        const refs = [p.originalRef, p.thumbRef, p.mediumRef, p.motionRef, p.metaRef, ...(p.faceCropRefs || [])];
        const i = this.index.photos.findIndex((x) => x.id === p.id);
        if (i >= 0) this.index.photos.splice(i, 1);
        // Drop dangling references from albums and face clusters.
        for (const al of (this.index.albums || [])) {
            al.photoIds = (al.photoIds || []).filter((id) => id !== p.id);
            if (al.cover === p.id) al.cover = al.photoIds[0] || null;
        }
        for (const pp of (this.index.people || [])) pp.faces = (pp.faces || []).filter((f) => f.photoId !== p.id);
        // A cluster that has lost all (or its last remaining) faces is gone for good.
        this.index.people = (this.index.people || []).filter((pp) => (pp.faces || []).length >= 2);
        delete metaCache[p.id]; delete searchEmb[p.id];
        if (this.thumbs[p.id]) { URL.revokeObjectURL(this.thumbs[p.id]); delete this.thumbs[p.id]; }
        this._freeBlobs(refs);
    },
    _freeBlobs(refs) {
        const uniq = [...new Set(refs.filter(Boolean))];
        // Refresh once all the (paced, 429-retried) deletes have actually landed
        // so usage reflects the reclaimed bytes, not the mid-flight state.
        Promise.all(uniq.map((ref) => queueBlobDelete(`${config.blobBase}/${ref}`, config.token)))
            .then(() => this.refreshUsage());
        this.refreshUsage();
    },

    /* ---- Usage + reconcile ---- */
    async refreshUsage() {
        try { const r = await fetch(config.usageUrl, { cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); if (r.ok) this.usage = await r.json(); } catch (e) { /* keep */ }
    },
    reconcileBlobs() {
        const blobs = [];
        for (const p of this.index.photos) {
            for (const ref of [p.originalRef, p.thumbRef, p.mediumRef, p.motionRef, p.metaRef]) if (ref) blobs.push(ref);
            for (const ref of (p.faceCropRefs || [])) if (ref) blobs.push(ref);
        }
        // The shard blobs hold the photo records themselves — keep them too, or the
        // sweep would treat the whole library index as orphaned.
        for (const ref of window.LLGalleryStore.shardRefs()) blobs.push(ref);
        postForm(config.reconcileUrl, { blobs: [...new Set(blobs)] })
            .then((u) => { if (u) this.usage = u; }).catch(() => {});
    },

    fmtBytes: formatBytes,
    fmtDate: formatDate,
    placeText(place) {
        if (! place) return '';
        if (typeof place === 'string') return place;
        return place.display || place.name || [place.city, place.state, place.country].filter(Boolean).join(', ') || '';
    },
    dismissUploads() { this.uploads = []; },
    get uploading() { return this.uploads.some((u) => u.state === 'uploading'); },
};
};
