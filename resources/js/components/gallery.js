// Gallery module — plaintext-relational (final pivot). No vault gate, no sealed
// store, no per-blob crypto, no decrypt/scan workers: photos and albums are
// owner-scoped rows fetched + mutated over per-row REST endpoints (shared with
// the mobile API). The bytes are PLAINTEXT now, so thumbnails/images/video/motion
// load directly from their render URLs (/gallery/photos/{id}/thumb|medium|raw|
// motion) in <img>/<video> — no decryption, no object URLs, no worker pool.
//
// CLIP semantic search + face/people detection are served by a server-side ML
// backend again (pgvector); this client CONSUMES those plaintext endpoints:
//   GET /gallery/search?q=            → semantic photo results
//   GET /gallery/photos/{id}/similar  → visually similar photos
//   GET /gallery/people[/{id}]        → people + their faces/photos
//   PUT/DELETE /gallery/people/{id}, POST /gallery/people/merge
//   POST /gallery/faces/{id}/assign|hide, GET /gallery/faces/{id}/crop
//   POST /gallery/photos/{id}/reprocess
// All of it degrades silently when ML/pgvector is off (empty people/search).
// Duplicate scanning + on-device rendition derivation (the server derives thumb/
// medium/EXIF/pHash now) and Live-Photo pairing (the single-file upload endpoint
// can't merge a separately-uploaded .MOV) stay deferred. What remains: the timeline grid, the viewer
// (image/video/Live motion), favorites, description edit, non-destructive
// date/location edits, trash (soft-delete + restore + purge + empty), albums
// (create/rename/delete/cover/add-remove), the viewer map/EXIF panel, whole +
// chunked upload with progress, the storage usage bar, and the public share
// dialog (/gallery/rel-shares → /gallery-share/{token} link).
import { getJson, apiRequest, postForm, jsonHeaders, csrfToken } from '../shared/api';
import { formatBytes } from '../shared/file-categories';
import { loadLeaflet } from '../shared/lazy-loaders';
import { formatDate } from '../shared/dom';

// Above this size an upload streams as chunks instead of one request.
const CHUNK_THRESHOLD = 8 * 1024 * 1024;

// Server row → client shape. camelCase for the fields the template reads; the
// disk paths are never used as URLs (the render endpoints are keyed by id) —
// only their presence is kept as hasThumb/hasMedium/hasMotion flags.
const normPhoto = (p) => ({
    id: p.id,
    kind: p.kind ?? 'image',
    mime: p.mime ?? 'application/octet-stream',
    size: p.size ?? 0,
    width: p.width ?? null,
    height: p.height ?? null,
    takenAt: p.taken_at ?? null,
    lat: p.lat ?? null,
    lng: p.lng ?? null,
    camera: p.camera ?? null,
    place: (p.exif && p.exif.place) || null,
    favorite: !! p.favorite,
    description: p.description ?? '',
    hasThumb: !! p.thumb_path,
    hasMedium: !! p.medium_path,
    hasMotion: !! p.motion_path,
    version: p.version ?? 0,
    created: p.created_at ?? null,
    trashed: p.deleted_at ?? null,
});

const normAlbum = (a) => ({
    id: a.id,
    name: a.name ?? '',
    cover: a.cover_photo_id ?? null,
    photoIds: Array.isArray(a.photos) ? a.photos.map((x) => x.id) : (Array.isArray(a.photo_ids) ? a.photo_ids : []),
    version: a.version ?? 0,
    share: null, // owner-side share link created this session (no list endpoint)
});

const normShare = (s) => ({
    id: s.id,
    token: s.token,
    allowDownload: !! s.allow_download,
    needsPassword: !! s.needs_password,
    expiresAt: s.expires_at ?? null,
    version: s.version ?? 0,
});

// People + faces (server ML). snake_case → camelCase; the face crop is served
// by id (/gallery/faces/{id}/crop), never from crop_path directly.
const normPerson = (p) => ({
    id: p.id,
    name: p.name ?? '',
    faceCount: p.face_count ?? 0,
    coverFaceId: p.cover_face_id ?? null,
    version: p.version ?? 0,
});
const normFace = (f) => ({
    id: f.id,
    photoId: f.gallery_photo_id ?? null,
    personId: f.gallery_person_id ?? null,
    score: f.score ?? null,
    box: Array.isArray(f.box) ? f.box : null,
    hidden: !! f.hidden,
});

export default (config = {}, labels = {}, initial = {}) => ({
    photos: (initial.photos || []).map(normPhoto),
    albums: (initial.albums || []).map(normAlbum),
    usage: initial.usage || { used: 0, quota: 0 },

    view: 'library', // library | memories | favorites | albums | album | trash
    error: '',
    busy: 0,
    dragging: false,

    // Incremental render window: only the newest N library photos are in the DOM;
    // a scroll sentinel raises this so the grid never builds thousands of tiles.
    renderLimit: 300,
    _renderStep: 300,

    uploads: [], // { name, state, progress, error }
    uploadBatches: 0,

    viewer: { open: false, photo: null, fit: 1, motionOn: false, pool: null },
    _miniMap: null,

    // trash (loaded on demand)
    trash: [],
    trashLoaded: false,

    // search — instant local metadata filter (while typing) + server-side CLIP
    // semantic search on submit (Enter). semanticActive flags the smart results.
    query: '',
    searchResults: null, // null = not searching; array = results
    _searchTimer: null,
    semanticActive: false,
    semanticBusy: false,

    // people + faces (server ML, loaded on demand for the People view)
    people: [],
    peopleLoaded: false,
    activePerson: null,          // person id while in the person-detail view
    personDetail: null,          // { person, faces:[], photos:[] }
    mergePicker: { open: false, into: null },
    facePicker: { open: false, face: null },

    // "find similar" strip shown inside the viewer
    similar: { loading: false, photos: [] },
    similarOpen: false,

    // multi-select
    selected: [],
    _lastSel: null,

    // albums
    activeAlbum: null,
    albumPicker: false,

    // bulk date / location modals
    bulkDate: '',
    dateModal: false,
    loc: { open: false, bulk: false, target: null, lat: null, lng: null },
    _locMap: null,
    _locMarker: null,
    geoQuery: '',
    geoResults: [],
    geoBusy: false,
    geoSearched: false,

    // share dialog
    share: { open: false, album: null, busy: false, password: '', expiresAt: '', allowDownload: false, link: '', error: '' },

    _token() { return config.token || csrfToken(); },
    _track(p) { this.busy++; return Promise.resolve(p).finally(() => { this.busy = Math.max(0, this.busy - 1); }); },

    async init() {
        this.initDropzone();
        this.$watch('view', (v) => {
            this.selected = [];
            if (v !== 'library') this.clearSearch();
            if (v === 'trash') this._loadTrash();
            if (v === 'people') this._loadPeople();
        });
        await this._track(this.load());
        // Deep link to a single photo (?photo=<id>) → open it directly.
        const photoId = new URLSearchParams(location.search).get('photo');
        if (photoId) {
            const target = this.photos.find((pp) => String(pp.id) === String(photoId));
            if (target) this.openViewer(target);
        }
    },

    initDropzone() {
        let depth = 0;
        window.addEventListener('dragenter', (e) => { if (e.dataTransfer?.types?.includes('Files')) { depth++; this.dragging = true; } });
        window.addEventListener('dragleave', () => { depth = Math.max(0, depth - 1); if (! depth) this.dragging = false; });
        window.addEventListener('drop', () => { depth = 0; this.dragging = false; });
    },

    // Load the active photos + albums + usage snapshot.
    async load() {
        try {
            const d = await getJson(config.dataUrl);
            this.photos = (d.photos || []).map(normPhoto);
            this.albums = (d.albums || []).map(normAlbum);
            if (d.usage) this.usage = d.usage;
            this.renderLimit = this._renderStep;
        } catch (e) { /* keep the inlined initial data */ }
    },

    async _loadTrash() {
        if (this.trashLoaded) return;
        try { const d = await getJson(config.trashUrl); this.trash = (d.photos || []).map(normPhoto); this.trashLoaded = true; }
        catch (e) { /* keep */ }
    },

    async refreshUsage() {
        try { const d = await getJson(config.dataUrl); if (d.usage) this.usage = d.usage; } catch (e) { /* keep */ }
    },

    /* ---- Render URLs (plaintext bytes, used directly) ---- */
    thumbUrl(p) { return config.photoBase + '/' + p.id + '/thumb'; },
    mediumUrl(p) { return config.photoBase + '/' + p.id + '/medium'; },
    rawUrl(p) { return config.photoBase + '/' + p.id + '/raw'; },
    motionUrl(p) { return config.photoBase + '/' + p.id + '/motion'; },
    viewerSrc(p) { return p && p.hasMedium ? this.mediumUrl(p) : this.rawUrl(p); },

    /* ---- Derived views ---- */
    get libraryPhotos() {
        return this.photos
            .filter((p) => ! p.trashed)
            .sort((a, b) => new Date(b.takenAt || b.created || 0) - new Date(a.takenAt || a.created || 0));
    },
    get favoritePhotos() { return this.libraryPhotos.filter((p) => p.favorite); },
    favoriteCount() { return this.favoritePhotos.length; },
    photoCount() { return this.libraryPhotos.length; },
    trashCount() { return this.trashLoaded ? this.trash.length : this.photos.filter((p) => p.trashed).length; },
    get trashedPhotos() {
        return this.trash.slice().sort((a, b) => new Date(b.trashed || 0) - new Date(a.trashed || 0));
    },

    // "On this day": library photos whose capture month+day match today, grouped
    // by year (past years only). Purely client-side over the loaded index.
    get memories() {
        const now = new Date();
        const md = (d) => d.getMonth() + '-' + d.getDate();
        const today = md(now), thisYear = now.getFullYear();
        const byYear = new Map();
        for (const p of this.libraryPhotos) {
            const t = p.takenAt || p.created;
            if (! t) continue;
            const d = new Date(t);
            if (isNaN(d.getTime()) || md(d) !== today || d.getFullYear() >= thisYear) continue;
            const y = d.getFullYear();
            if (! byYear.has(y)) byYear.set(y, []);
            byYear.get(y).push(p);
        }
        return [...byYear.entries()].sort((a, b) => b[0] - a[0]).map(([year, photos]) => ({ year, yearsAgo: thisYear - year, photos }));
    },
    memoryCount() { return this.memories.reduce((n, g) => n + g.photos.length, 0); },

    get hasMore() { return this.searchResults === null && this.renderLimit < this.libraryPhotos.length; },
    loadMore() { if (this.hasMore) this.renderLimit += this._renderStep; },

    // Library photos grouped by capture day (newest first), only the current
    // render window so the DOM never holds the whole library.
    get groupedPhotos() {
        const groups = new Map();
        for (const p of this.libraryPhotos.slice(0, this.renderLimit)) {
            const d = new Date(p.takenAt || p.created || 0);
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
    // Library shown as day groups, or a single flat group of search hits.
    get displayGroups() {
        if (this.searchResults !== null) {
            return this.searchResults.length ? [{ day: 'search', label: '', photos: this.searchResults }] : [];
        }
        return this.groupedPhotos;
    },
    get isSearching() { return this.searchResults !== null; },

    /* ---- Search — instant local metadata filter + semantic (CLIP) on submit ---- */
    // Typing → debounced local metadata filter (keeps the box responsive and
    // works with ML off). Any keystroke invalidates a prior smart-results set.
    runSearch() {
        clearTimeout(this._searchTimer);
        this.semanticActive = false;
        if (! this.query.trim()) { this.searchResults = null; return; }
        this._searchTimer = setTimeout(() => this._doSearch(), 250);
    },
    clearSearch() { this.query = ''; this.searchResults = null; this.semanticActive = false; this.semanticBusy = false; },
    _doSearch() {
        const q = this.query.trim().toLowerCase();
        if (! q) { this.searchResults = null; return; }
        this.searchResults = this.libraryPhotos.filter((p) =>
            (p.description || '').toLowerCase().includes(q)
            || (p.camera || '').toLowerCase().includes(q)
            || (this.placeText(p.place) || '').toLowerCase().includes(q)
            || (p.takenAt ? this.fmtDate(p.takenAt).toLowerCase().includes(q) : false));
    },
    // Enter/submit → natural-language CLIP search. Graceful fallback: empty
    // result (ML/pgvector off) or an error keeps the local metadata filter.
    async submitSearch() {
        const q = this.query.trim();
        clearTimeout(this._searchTimer);
        if (! q) { this.clearSearch(); return; }
        if (! config.searchUrl) { this._doSearch(); return; }
        this._doSearch(); // seed with local hits so the grid is never blank while we wait
        this.semanticBusy = true;
        try {
            const d = await getJson(config.searchUrl + '?q=' + encodeURIComponent(q));
            const hits = (d.photos || []).map(normPhoto);
            if (hits.length) { this.searchResults = hits; this.semanticActive = true; }
            else { this.semanticActive = false; } // nothing semantic → local filter stands
        } catch (e) {
            this.semanticActive = false; // ML unavailable → local filter stands
        } finally { this.semanticBusy = false; }
    },

    /* ---- Upload (plaintext; the server derives renditions/EXIF/pHash) ---- */
    upload(fileList) {
        const files = [...fileList].filter((f) => /^image\/|^video\//.test(f.type) || /\.(heic|heif|avif|mov|mp4|m4v)$/i.test(f.name));
        if (! files.length) return;
        if (this.uploadBatches === 0) this.uploads = [];
        this.uploadBatches++;
        return this._track((async () => {
            const start = this.uploads.length;
            for (const f of files) this.uploads.push({ name: f.name, state: 'pending', progress: 0, error: '' });
            let next = 0;
            const worker = async () => {
                while (next < files.length) {
                    const idx = next++;
                    const file = files[idx];
                    const entry = this.uploads[start + idx];
                    try {
                        const photo = file.size > CHUNK_THRESHOLD
                            ? await this._uploadChunked(file, entry)
                            : await this._uploadWhole(file, entry);
                        entry.state = 'done'; entry.progress = 100;
                        if (photo) this.photos.unshift(normPhoto(photo));
                    } catch (e) { entry.state = 'error'; entry.error = this._uploadErrorText(e); }
                }
            };
            const hasLarge = files.some((f) => f.size > CHUNK_THRESHOLD);
            const lanes = Math.min(hasLarge ? 2 : 4, files.length);
            await Promise.all(Array.from({ length: lanes }, worker));
            this.uploadBatches--;
            this.refreshUsage();
            if (this.uploadBatches === 0 && ! this.uploads.some((u) => u.state === 'error')) {
                setTimeout(() => { if (! this.uploading) this.uploads = []; }, 4000);
            }
        })());
    },
    get uploading() { return this.uploads.some((u) => u.state === 'pending' || u.state === 'uploading'); },
    uploadDone() { return this.uploads.filter((u) => u.state === 'done' || u.state === 'error').length; },
    dismissUploads() { this.uploads = []; },

    _uploadWhole(file, entry) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', this._token());
            data.append('file', file, file.name);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', config.uploadUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 300000;
            entry.state = 'uploading';
            xhr.upload.onprogress = (ev) => { if (ev.lengthComputable) entry.progress = Math.round((ev.loaded / ev.total) * 100); };
            xhr.onload = () => {
                if (xhr.status === 413) { reject(new Error('quota')); return; }
                if (xhr.status < 200 || xhr.status >= 300) {
                    let detail = '';
                    try { detail = JSON.parse(xhr.responseText).message || ''; } catch (e) { /* not JSON */ }
                    reject(new Error(detail ? 'server:' + detail : 'http:' + xhr.status));
                    return;
                }
                try { resolve(JSON.parse(xhr.responseText).photo); } catch (e) { reject(new Error('bad_response')); }
            };
            xhr.onerror = () => reject(new Error('network'));
            xhr.ontimeout = () => reject(new Error('timeout'));
            try { xhr.send(data); } catch (e) { reject(new Error('network')); }
        });
    },

    async _uploadChunked(file, entry) {
        entry.state = 'uploading';
        const init = await fetch(config.chunkBase + '/init', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ name: file.name, size: file.size, mime: file.type || 'application/octet-stream' }) });
        if (init.status === 413) throw new Error('quota');
        if (! init.ok) throw new Error('http:' + init.status);
        const { id, partSize } = await init.json();
        const ps = partSize || CHUNK_THRESHOLD;
        try {
            let index = 0, sent = 0;
            const total = file.size;
            for (let off = 0; off < total || (total === 0 && index === 0); off += ps) {
                const end = Math.min(off + ps, total);
                await this._uploadChunkPart(id, index, file.slice(off, end), entry, sent, total);
                sent += (end - off); index++;
                if (total === 0) break;
            }
            const comp = await fetch(config.chunkBase + '/complete', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ id }) });
            if (comp.status === 413) throw new Error('quota');
            if (! comp.ok) throw new Error('http:' + comp.status);
            entry.progress = 100;
            return (await comp.json()).photo;
        } catch (e) {
            fetch(config.chunkBase + '/abort', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ id }) }).catch(() => {});
            throw e;
        }
    },
    _uploadChunkPart(id, index, blob, entry, offsetStart, totalSize) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', this._token());
            data.append('id', id);
            data.append('index', index);
            data.append('file', blob, 'chunk');
            const xhr = new XMLHttpRequest();
            xhr.open('POST', config.chunkBase + '/part');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 600000;
            xhr.upload.onprogress = (ev) => { if (ev.lengthComputable && totalSize) entry.progress = Math.round(((offsetStart + ev.loaded) / totalSize) * 100); };
            xhr.onload = () => {
                if (xhr.status === 413) { reject(new Error('quota')); return; }
                if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('part failed')); return; }
                resolve();
            };
            xhr.onerror = () => reject(new Error('network'));
            xhr.ontimeout = () => reject(new Error('timeout'));
            try { xhr.send(data); } catch (e) { reject(new Error('network')); }
        });
    },
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

    // Whole-window / picker drop: walk any dropped folders too.
    async drop(event) {
        this.dragging = false;
        const items = event.dataTransfer.items;
        const entries = items && items.length && items[0].webkitGetAsEntry
            ? [...items].map((it) => it.webkitGetAsEntry?.()).filter(Boolean) : [];
        if (entries.length) {
            const files = await this._filesFromEntries(entries);
            if (files.length) return this.upload(files);
        }
        this.upload(event.dataTransfer.files);
    },
    async _filesFromEntries(entries) {
        const out = [];
        const walk = async (entry) => {
            if (! entry) return;
            if (entry.isFile) { await new Promise((res) => entry.file((f) => { out.push(f); res(); }, () => res())); }
            else if (entry.isDirectory) {
                const reader = entry.createReader();
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

    /* ---- Per-photo mutations (optimistic version + 409 refetch) ---- */
    _findPhoto(id) { return this.photos.find((x) => x.id === id) || this.trash.find((x) => x.id === id); },
    _applyPhoto(row) {
        if (! row) return;
        const n = normPhoto(row);
        const p = this._findPhoto(row.id);
        if (p) Object.assign(p, n);
        if (this.viewer.photo && this.viewer.photo.id === row.id) Object.assign(this.viewer.photo, n);
    },

    // PUT a field patch with optimistic concurrency; on 409 re-stamp + retry.
    async _savePhoto(p, patch) {
        for (let i = 0; i < 4; i++) {
            let res;
            try { res = await fetch(config.photoBase + '/' + p.id, { method: 'PUT', headers: jsonHeaders(), body: JSON.stringify({ ...patch, version: p.version }) }); }
            catch (e) { break; }
            if (res.ok) { const d = await res.json().catch(() => ({})); if (d.photo) this._applyPhoto(d.photo); return true; }
            if (res.status === 409) { const d = await res.json().catch(() => ({})); if (typeof d.version === 'number') { p.version = d.version; continue; } }
            break;
        }
        window.llToast?.(labels.loadFailed);
        return false;
    },

    async toggleFavorite(p) {
        if (! p) return;
        const next = ! p.favorite;
        p.favorite = next;
        try { const d = await postForm(config.photoBase + '/' + p.id + '/toggle', { field: 'favorite', value: next }); if (d.photo) this._applyPhoto(d.photo); }
        catch (e) { p.favorite = ! next; window.llToast?.(labels.loadFailed); }
    },
    setCaption(p, text) {
        if (! p) return;
        const v = (text || '').trim();
        if ((p.description || '') === v) return;
        p.description = v;
        this._savePhoto(p, { description: v });
    },
    setTakenAt(p, value) {
        if (! p || ! value) return;
        const d = new Date(value);
        if (isNaN(d.getTime())) return;
        p.takenAt = d.toISOString();
        this._savePhoto(p, { taken_at: p.takenAt });
    },

    /* ---- Trash lifecycle ---- */
    async trashPhoto(p) {
        if (! p) return;
        try {
            await apiRequest('DELETE', config.photoBase + '/' + p.id);
            p.trashed = new Date().toISOString();
            const i = this.photos.findIndex((x) => x.id === p.id);
            if (i >= 0) this.photos.splice(i, 1);
            if (this.trashLoaded) this.trash.unshift(p);
            if (this.viewer.photo && this.viewer.photo.id === p.id) this.closeViewer();
            this.refreshUsage();
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    async restore(p) {
        if (! p) return;
        try {
            const d = await postForm(config.photoBase + '/' + p.id + '/restore', {});
            const i = this.trash.findIndex((x) => x.id === p.id);
            if (i >= 0) this.trash.splice(i, 1);
            if (d.photo) this.photos.unshift(normPhoto(d.photo));
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    async purge(p) {
        if (! p) return;
        if (! await this.$store.confirm.ask(labels.purgeConfirm || labels.deleteConfirm || '')) return;
        await this._track(this._forcePurge(p));
        this.refreshUsage();
    },
    async _forcePurge(p) {
        try {
            await apiRequest('DELETE', config.photoBase + '/' + p.id + '/force');
            const i = this.trash.findIndex((x) => x.id === p.id);
            if (i >= 0) this.trash.splice(i, 1);
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    async emptyTrash() {
        if (! this.trashLoaded) await this._loadTrash();
        if (! this.trash.length) return;
        if (! await this.$store.confirm.ask(labels.emptyTrashConfirm || '')) return;
        try { await this._track(postForm(config.emptyTrashUrl, {})); this.trash = []; this.refreshUsage(); }
        catch (e) { window.llToast?.(labels.loadFailed); }
    },

    /* ---- Viewer ---- */
    // `pool` overrides the prev/next set (person photos, similar strip); default
    // is the current library/search display groups.
    openViewer(p, pool = null) {
        this.similar = { loading: false, photos: [] };
        this.similarOpen = false;
        this.viewer = { open: true, photo: p, fit: 1, motionOn: false, pool };
        if (p.lat != null && p.lng != null) this._renderMiniMap(parseFloat(p.lat), parseFloat(p.lng));
    },
    closeViewer() {
        if (this._miniMap) { this._miniMap.remove(); this._miniMap = null; }
        this.similar = { loading: false, photos: [] };
        this.similarOpen = false;
        this.viewer = { open: false, photo: null, fit: 1, motionOn: false, pool: null };
    },
    // Images in the current view (or the explicit pool), for prev/next stepping.
    get viewerImages() {
        const pool = this.viewer.pool || this.displayGroups.flatMap((g) => g.photos);
        return pool.filter((p) => p.kind !== 'video');
    },
    get viewerIndex() { return this.viewer.photo ? this.viewerImages.findIndex((r) => r.id === this.viewer.photo.id) : -1; },
    get viewerHasGallery() { return !! this.viewer.photo && this.viewerImages.length > 1; },
    viewerStep(dir) {
        const imgs = this.viewerImages;
        if (imgs.length < 2) return;
        const i = this.viewerIndex;
        if (i < 0) return;
        this.openViewer(imgs[(i + dir + imgs.length) % imgs.length], this.viewer.pool);
    },

    /* ---- Find similar (server ML), shown as a strip inside the viewer ---- */
    async findSimilar() {
        const photo = this.viewer.photo;
        if (! photo) return;
        this.similarOpen = true;
        this.similar = { loading: true, photos: [] };
        try {
            const d = await getJson(config.photoBase + '/' + photo.id + '/similar');
            this.similar.photos = (d.photos || []).map(normPhoto).filter((x) => x.id !== photo.id);
        } catch (e) { this.similar.photos = []; } // ML off → empty strip
        finally { this.similar.loading = false; }
    },
    openSimilar(p) { this.openViewer(p, this.similar.photos.slice()); },

    // Re-run ML detection on a single photo (backfill); refresh the row.
    async reprocess(p) {
        const photo = p || this.viewer.photo;
        if (! photo) return;
        try {
            const d = await postForm(config.photoBase + '/' + photo.id + '/reprocess', {});
            if (d && d.photo) this._applyPhoto(d.photo);
            this.peopleLoaded = false; // people may have changed
            if (this.view === 'people') this._loadPeople();
            window.llToast?.(labels.reprocessing || '');
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    playMotion() { if (this.viewer.photo?.hasMotion) this.viewer.motionOn = true; },
    stopMotion() { this.viewer.motionOn = false; },

    /* ---- Viewer mini-map (single photo location) ---- */
    async _renderMiniMap(lat, lng) {
        if (lat == null || lng == null || isNaN(lat) || isNaN(lng)) return;
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

    /* ---- Location picker (Leaflet: click the map to set the spot) ---- */
    openLocPicker(p) {
        if (! p) return;
        const plat = p.lat != null ? parseFloat(p.lat) : null;
        const plng = p.lng != null ? parseFloat(p.lng) : null;
        this.loc = { open: true, bulk: false, target: p, lat: plat, lng: plng };
        this._mountLocMap(plat ?? 48.2082, plng ?? 16.3738, plat != null);
    },
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
                const lat = this.loc.lat, lng = this.loc.lng;
                this._eachSelected((p) => { p.lat = lat; p.lng = lng; this._savePhoto(p, { lat, lng }); });
                this.selected = [];
            } else if (this.loc.target) {
                const p = this.loc.target;
                p.lat = this.loc.lat; p.lng = this.loc.lng;
                this._renderMiniMap(this.loc.lat, this.loc.lng);
                this._savePhoto(p, { lat: this.loc.lat, lng: this.loc.lng });
            }
        }
        this.closeLocPicker();
    },
    clearLoc() {
        if (this.loc.bulk) { this._eachSelected((p) => { p.lat = null; p.lng = null; this._savePhoto(p, { lat: null, lng: null }); }); this.selected = []; }
        else if (this.loc.target) {
            const p = this.loc.target;
            p.lat = null; p.lng = null;
            if (this._miniMap) { this._miniMap.remove(); this._miniMap = null; }
            this._savePhoto(p, { lat: null, lng: null });
        }
        this.closeLocPicker();
    },
    closeLocPicker() {
        if (this._locMap) { this._locMap.remove(); this._locMap = null; }
        this._locMarker = null;
        this.loc = { open: false, bulk: false, target: null, lat: null, lng: null };
        this.geoQuery = ''; this.geoResults = []; this.geoBusy = false; this.geoSearched = false;
    },
    async geoSearch() {
        const q = this.geoQuery.trim();
        if (! q) { this.geoResults = []; this.geoSearched = false; return; }
        this.geoBusy = true;
        try { const data = await getJson(config.geocodeUrl + '?q=' + encodeURIComponent(q)); this.geoResults = data.results || []; }
        catch (e) { this.geoResults = []; } finally { this.geoBusy = false; this.geoSearched = true; }
    },
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
    isSelected(id) { return this.selected.includes(id); },
    toggleSelect(id) { const i = this.selected.indexOf(id); if (i >= 0) this.selected.splice(i, 1); else this.selected.push(id); },
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
    groupSelected(group) { return group.photos.length > 0 && group.photos.every((p) => this.selected.includes(p.id)); },
    toggleGroup(group) {
        const ids = group.photos.map((p) => p.id);
        if (this.groupSelected(group)) this.selected = this.selected.filter((id) => ! ids.includes(id));
        else for (const id of ids) if (! this.selected.includes(id)) this.selected.push(id);
    },
    clearSelection() { this.selected = []; this._lastSel = null; },
    get selectedCount() { return this.selected.length; },
    selectAllVisible() {
        const map = { trash: this.trashedPhotos, favorites: this.favoritePhotos };
        const ids = (map[this.view] || this.libraryPhotos).map((p) => p.id);
        this.selected = this.selected.length === ids.length ? [] : ids;
    },
    _eachSelected(fn) { for (const id of [...this.selected]) { const p = this._findPhoto(id); if (p) fn(p); } },

    bulkFavorite() {
        const allFav = this.selected.length > 0 && this.selected.every((id) => { const p = this._findPhoto(id); return p && p.favorite; });
        this._eachSelected((p) => { if (!! p.favorite !== ! allFav) this.toggleFavorite(p); });
        this.selected = [];
    },
    openBulkDate() { if (! this.selectedCount) return; this.bulkDate = ''; this.dateModal = true; },
    closeBulkDate() { this.dateModal = false; this.bulkDate = ''; },
    bulkApplyDate() {
        if (! this.bulkDate) return;
        const d = new Date(this.bulkDate);
        if (isNaN(d.getTime())) return;
        const iso = d.toISOString();
        this._eachSelected((p) => { p.takenAt = iso; this._savePhoto(p, { taken_at: iso }); });
        this.bulkDate = ''; this.dateModal = false; this.selected = [];
    },
    bulkTrash() { this._eachSelected((p) => this.trashPhoto(p)); this.selected = []; },
    bulkRestore() { this._eachSelected((p) => this.restore(p)); this.selected = []; },
    async bulkPurge() {
        if (! await this.$store.confirm.ask(labels.emptyTrashConfirm || labels.purgeConfirm || '')) return;
        const targets = [...this.selected].map((id) => this._findPhoto(id)).filter(Boolean);
        this.selected = [];
        await this._track(Promise.all(targets.map((p) => this._forcePurge(p))));
        this.refreshUsage();
    },

    /* ---- Albums ---- */
    get albumsSorted() { return this.albums.slice().sort((a, b) => (a.name || '').localeCompare(b.name || '')); },
    get currentAlbum() { return this.albums.find((a) => a.id === this.activeAlbum) || null; },
    albumPhotos(al) {
        if (! al) return [];
        const set = new Set(al.photoIds || []);
        return this.libraryPhotos.filter((p) => set.has(p.id));
    },
    albumCount(al) { return al ? (al.photoIds || []).length : 0; },
    albumCover(al) {
        if (! al) return null;
        const ps = this.albumPhotos(al);
        return ps.find((p) => p.id === al.cover) || ps[0] || null;
    },
    openAlbum(al) { this.activeAlbum = al.id; this.view = 'album'; },
    async createAlbum() {
        const raw = await this.$store.confirm.prompt('', { placeholder: labels.albumName || '', ok: labels.create || '' });
        const name = (raw || '').trim();
        if (! name) return;
        try {
            const d = await postForm(config.albumsBase, { name });
            const al = normAlbum(d.album);
            if (this.selected.length) { await this._addPhotosToAlbum(al, [...this.selected]); this.selected = []; }
            this.albums.unshift(al);
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    async renameAlbum(al) {
        const raw = await this.$store.confirm.prompt('', { value: al.name, placeholder: labels.albumName || '', ok: labels.save || '' });
        const name = (raw || '').trim();
        if (! name) return;
        try { const d = await apiRequest('PUT', config.albumsBase + '/' + al.id, { name, version: al.version }); if (d.album) Object.assign(al, normAlbum({ ...d.album, photos: (al.photoIds || []).map((id) => ({ id })) })); }
        catch (e) { window.llToast?.(labels.loadFailed); }
    },
    async deleteAlbum(al) {
        if (! await this.$store.confirm.ask(labels.deleteAlbumConfirm || '')) return;
        try {
            await apiRequest('DELETE', config.albumsBase + '/' + al.id);
            const i = this.albums.findIndex((a) => a.id === al.id);
            if (i >= 0) this.albums.splice(i, 1);
            if (this.activeAlbum === al.id) { this.activeAlbum = null; this.view = 'albums'; }
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    async _addPhotosToAlbum(al, ids) {
        const d = await postForm(config.albumsBase + '/' + al.id + '/photos', { photo_ids: ids });
        if (d.album && Array.isArray(d.album.photos)) al.photoIds = d.album.photos.map((p) => p.id);
        else al.photoIds = [...new Set([...(al.photoIds || []), ...ids])];
        if (! al.cover) al.cover = al.photoIds[0] || null;
    },
    async addSelectedToAlbum(al) {
        const ids = [...this.selected];
        this.selected = [];
        try { await this._addPhotosToAlbum(al, ids); } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    async removeFromAlbum(al, p) {
        try {
            await apiRequest('DELETE', config.albumsBase + '/' + al.id + '/photos/' + p.id);
            al.photoIds = (al.photoIds || []).filter((id) => id !== p.id);
            if (al.cover === p.id) { al.cover = al.photoIds[0] || null; }
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    async setAlbumCover(al, p) {
        if (! al || ! p) return;
        try { const d = await postForm(config.albumsBase + '/' + al.id + '/cover', { photo_id: p.id }); if (d.album) al.cover = d.album.cover_photo_id ?? p.id; }
        catch (e) { window.llToast?.(labels.loadFailed); }
    },

    /* ---- Public share link (plaintext; owner side) ---- */
    openShare(al) {
        const s = al.share;
        this.share = {
            open: true, album: al, busy: false, error: '',
            password: '', expiresAt: s?.expiresAt ? this._toLocalInput(s.expiresAt) : '', allowDownload: !! s?.allowDownload,
            link: s ? this._shareLink(s.token) : '',
        };
    },
    closeShare() { this.share.open = false; this.share.album = null; this.share.password = ''; },
    _shareLink(token) { return `${config.shareBase}/${token}`; },
    async copyShareLink() {
        if (! this.share.link) return;
        try { await navigator.clipboard.writeText(this.share.link); window.llToast?.(labels.shareCopied || ''); } catch (e) { /* clipboard blocked */ }
    },
    async createShare() {
        const al = this.share.album;
        if (! al || this.share.busy) return;
        this.share.busy = true; this.share.error = '';
        try {
            const body = { kind: 'album', gallery_album_id: al.id, allow_download: this.share.allowDownload };
            if (this.share.expiresAt) body.expires_at = new Date(this.share.expiresAt).toISOString();
            if (this.share.password.trim()) body.password = this.share.password.trim();
            const { share } = await postForm(config.sharesUrl, body);
            al.share = normShare(share);
            this.share.password = '';
            this.share.link = this._shareLink(al.share.token);
        } catch (e) { this.share.error = labels.shareError || 'Error'; } finally { this.share.busy = false; }
    },
    async updateShare() {
        const al = this.share.album;
        if (! al || ! al.share || this.share.busy) return;
        this.share.busy = true; this.share.error = '';
        try {
            const body = { allow_download: this.share.allowDownload, version: al.share.version };
            if (this.share.expiresAt) body.expires_at = new Date(this.share.expiresAt).toISOString();
            else body.expires_at = null;
            if (this.share.password.trim()) body.password = this.share.password.trim();
            else if (! al.share.needsPassword) body.remove_password = true;
            const { share } = await postForm(`${config.sharesUrl}/${al.share.id}`, body, 'PUT');
            al.share = normShare(share);
            this.share.password = '';
        } catch (e) { this.share.error = labels.shareError || 'Error'; } finally { this.share.busy = false; }
    },
    async revokeShare() {
        const al = this.share.album;
        if (! al || ! al.share) return;
        this.share.busy = true;
        try { await apiRequest('DELETE', `${config.sharesUrl}/${al.share.id}`); } catch (e) { /* best effort */ }
        al.share = null; this.share.link = ''; this.share.busy = false;
    },

    /* ---- People (server ML) ---- */
    async _loadPeople() {
        if (this.peopleLoaded) return;
        try {
            const d = await getJson(config.peopleBase);
            this.people = (d.people || []).map(normPerson);
            this.peopleLoaded = true;
        } catch (e) { this.people = []; this.peopleLoaded = true; } // ML off → empty
    },
    async _reloadPeople() { this.peopleLoaded = false; await this._loadPeople(); },
    peopleCount() { return this.people.length; },
    // Named people first, then by face count (biggest clusters up top).
    get peopleSorted() {
        return this.people.slice().sort((a, b) => {
            const an = a.name ? 0 : 1, bn = b.name ? 0 : 1;
            if (an !== bn) return an - bn;
            return (b.faceCount || 0) - (a.faceCount || 0);
        });
    },
    faceCropUrl(faceId) { return faceId ? config.facesBase + '/' + faceId + '/crop' : ''; },
    personCover(person) { return person ? this.faceCropUrl(person.coverFaceId) : ''; },
    get currentPerson() {
        return this.personDetail ? this.personDetail.person : (this.people.find((p) => p.id === this.activePerson) || null);
    },
    get personPhotos() { return this.personDetail ? this.personDetail.photos : []; },
    get personFaces() { return this.personDetail ? this.personDetail.faces.filter((f) => ! f.hidden) : []; },

    async openPerson(person) {
        if (! person) return;
        this.activePerson = person.id;
        this.view = 'person';
        this.personDetail = { person: normPerson(person), faces: [], photos: [] };
        try {
            const d = await getJson(config.peopleBase + '/' + person.id);
            this.personDetail = {
                person: normPerson(d.person || person),
                faces: (d.faces || []).map(normFace),
                photos: (d.photos || []).map(normPhoto),
            };
        } catch (e) { /* keep the stub; ML/detail unavailable */ }
    },
    backToPeople() { this.view = 'people'; this.activePerson = null; this.personDetail = null; },

    // Inline rename (optimistic version; on repeated 409 reload the list).
    async renamePerson(person) {
        if (! person) return;
        const raw = await this.$store.confirm.prompt('', { value: person.name, placeholder: labels.personName || '', ok: labels.save || '' });
        if (raw === null || raw === undefined) return;
        const name = raw.trim();
        for (let i = 0; i < 4; i++) {
            let res;
            try { res = await fetch(config.peopleBase + '/' + person.id, { method: 'PUT', headers: jsonHeaders(), body: JSON.stringify({ name, version: person.version }) }); }
            catch (e) { break; }
            if (res.ok) {
                const d = await res.json().catch(() => ({}));
                if (d.person) { Object.assign(person, normPerson(d.person)); if (this.personDetail && this.personDetail.person.id === person.id) Object.assign(this.personDetail.person, normPerson(d.person)); }
                return;
            }
            if (res.status === 409) { const d = await res.json().catch(() => ({})); if (typeof d.version === 'number') { person.version = d.version; continue; } await this._reloadPeople(); return; }
            break;
        }
        window.llToast?.(labels.loadFailed);
    },
    async deletePerson(person) {
        if (! person) return;
        if (! await this.$store.confirm.ask(labels.deletePersonConfirm || '')) return;
        try {
            await apiRequest('DELETE', config.peopleBase + '/' + person.id);
            this.people = this.people.filter((p) => p.id !== person.id);
            if (this.activePerson === person.id) this.backToPeople();
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },

    /* ---- Merge two people ---- */
    openMerge(person) { this.mergePicker = { open: true, into: person || this.currentPerson }; },
    closeMerge() { this.mergePicker = { open: false, into: null }; },
    get mergeCandidates() { const into = this.mergePicker.into; return into ? this.peopleSorted.filter((p) => p.id !== into.id) : []; },
    async mergeInto(source) {
        const into = this.mergePicker.into;
        if (! into || ! source) return;
        this.closeMerge();
        try {
            await postForm(config.peopleBase + '/merge', { source_id: source.id, target_id: into.id });
            await this._reloadPeople();
            if (this.activePerson === into.id) { const cur = this.people.find((p) => p.id === into.id); if (cur) this.openPerson(cur); else this.backToPeople(); }
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },

    /* ---- Face management within a person ---- */
    async assignFace(face, personId) {
        if (! face) return;
        try {
            await postForm(config.facesBase + '/' + face.id + '/assign', { gallery_person_id: personId ?? null });
            if (this.personDetail) this.personDetail.faces = this.personDetail.faces.filter((f) => f.id !== face.id);
            this._reloadPeople();
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    detachFace(face) { return this.assignFace(face, null); }, // "not this person"
    async hideFace(face) {
        if (! face) return;
        try {
            await postForm(config.facesBase + '/' + face.id + '/hide', {});
            if (this.personDetail) this.personDetail.faces = this.personDetail.faces.filter((f) => f.id !== face.id);
            this._reloadPeople();
        } catch (e) { window.llToast?.(labels.loadFailed); }
    },
    openFaceReassign(face) { this.facePicker = { open: true, face }; },
    closeFaceReassign() { this.facePicker = { open: false, face: null }; },
    get facePickerCandidates() { return this.peopleSorted.filter((p) => p.id !== this.activePerson); },
    async reassignFace(person) {
        const face = this.facePicker.face;
        this.closeFaceReassign();
        if (face && person) await this.assignFace(face, person.id);
    },

    /* ---- Formatting helpers ---- */
    fmtBytes: formatBytes,
    fmtDate: formatDate,
    _toLocalInput(iso) {
        if (! iso) return '';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    },
    toLocalInput(iso) { return this._toLocalInput(iso); },
    placeText(place) {
        if (! place) return '';
        if (typeof place === 'string') return place;
        return place.display || place.name || [place.city, place.state, place.country].filter(Boolean).join(', ') || '';
    },
});
