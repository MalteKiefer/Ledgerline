import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { Vault, ShareCrypto } from './vault';
import { csrfToken, jsonHeaders } from './shared/api';
import { newId as _newId } from './shared/sealed-store';
import { fetchDecryptWorker, queueBlobDelete } from './shared/blob-io';
import { padBlob } from './shared/padme';
import contacts from './components/contacts';
import passwords from './components/passwords';
import vaultFiles from './components/files';
import vaultGallery from './components/gallery';
import publicShare from './components/public-share';
import fileShare from './components/file-share';
import invoices from './components/invoices';
import todos from './components/todos';
import notes from './components/notes';
import bookmarks from './components/bookmarks';
import toastHub from './components/toast-hub';
import cropModal from './components/crop-modal';
import backupRuns from './components/backup-runs';
import devicePairing from './components/device-pairing';
import paperlessSettings from './components/paperless-settings';
import notificationBell from './components/notification-bell';

// After a redeploy, Vite regenerates every chunk hash and the old chunks are
// gone. A still-open tab holding the previous bundle then 404s when it lazily
// imports a chunk (map/leaflet, markdown, libsodium…). Reload once to pick up
// the fresh assets. A short cooldown prevents a reload loop if the failure is
// genuinely persistent.
window.addEventListener('vite:preloadError', () => {
    const last = Number(sessionStorage.getItem('ll-chunk-reload') || 0);
    if (Date.now() - last > 10000) {
        sessionStorage.setItem('ll-chunk-reload', String(Date.now()));
        window.location.reload();
    }
});


// Zero-knowledge encryption vault (client-side crypto for the Files module).
// Exposed globally so the vault UI + files component can lock/unlock/encrypt.
// The reactive Alpine.store('vault') boots it (restores the cached key) on init.
window.Vault = Vault;
window.ShareCrypto = ShareCrypto;

/**
 * The opaque zero-knowledge store client. The whole workspace (notes, bookmarks,
 * todos, and their structure/flags) lives in ONE sealed manifest; the server only
 * stores/returns ciphertext + a version. This singleton loads + decrypts it once,
 * holds it in memory, and saves (debounced, sealed, optimistic version) on change.
 * File content bytes stay as separate opaque blobs (a later phase folds files in).
 */
window.LLStore = {
    data: null,        // decrypted manifest, or null until loaded
    version: 0,
    ready: false,
    loaded: false,
    _timer: null,
    _saving: false,
    _again: false,
    _onError: null,

    _blank() {
        return { v: 1, notes: [], bookmarks: [], bookmarkFolders: [], todos: [], todoLists: [], files: [], fileFolders: [], contacts: [], invoices: [], invoiceSeq: 0, secrets: [], secretFolders: [] };
    },

    // A random client-side id for a new item (server never assigns ids now).
    newId() { return _newId(); },

    // Load + decrypt the manifest once (call after the vault is unlocked).
    async load() {
        const res = await fetch('/store', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (! res.ok) throw new Error('store load failed');
        const d = await res.json();
        this.version = d.version ?? 0;
        this.data = d.ciphertext ? window.Vault.openManifest(d.ciphertext) : this._blank();
        // Forward-compat: ensure every collection exists.
        for (const k of Object.keys(this._blank())) if (! (k in this.data)) this.data[k] = this._blank()[k];
        this.loaded = true;
        this.ready = true;
        return this.data;
    },

    reset() { this.data = null; this.version = 0; this.ready = false; this.loaded = false; clearTimeout(this._timer); },

    // Schedule a debounced save; every mutation calls this.
    touch() {
        clearTimeout(this._timer);
        this._timer = setTimeout(() => this.flush(), 800);
    },

    // Seal + PUT the manifest with optimistic concurrency. On a version conflict
    // (another tab/device wrote in between) we reload the server version and
    // re-apply our in-memory copy (last-write-wins for this single-user app).
    async flush() {
        if (! this.loaded) return;
        if (this._saving) { this._again = true; return; }
        this._saving = true;
        try {
            const body = JSON.stringify({ ciphertext: window.Vault.sealManifest(this.data), version: this.version });
            const res = await fetch('/store', { method: 'PUT', headers: jsonHeaders(), body });
            if (res.status === 409) {
                const cur = await fetch('/store', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
                this.version = cur.version ?? this.version;
                this._again = true; // re-PUT our copy over the fresh version
            } else if (res.status === 429) {
                // Rate limited — back off, then re-arm the save (via _again) rather
                // than dropping it, so a destructive edit is never silently lost.
                const ra = parseInt(res.headers.get('Retry-After') || '', 10);
                await new Promise((r) => setTimeout(r, Number.isFinite(ra) && ra > 0 ? ra * 1000 : 1500));
                this._again = true;
            } else if (res.ok) {
                this.version = (await res.json()).version ?? this.version + 1;
            } else {
                throw new Error('store save failed');
            }
        } catch (e) {
            if (this._onError) this._onError();
        } finally {
            this._saving = false;
            if (this._again) { this._again = false; this.touch(); }
        }
    },
};

// Wait for the vault, then load the opaque manifest once (shared across the
// notes/bookmarks/todos components). Returns true when the manifest is ready,
// false while the vault is still locked.

// Separate sealed store for the gallery index (photos/albums/people), kept apart
// from the shared workspace manifest so gallery churn never re-seals notes/todos.
// Same contract as LLStore but against /gallery/store.
// Photos per sealed shard. The whole library used to live in ONE sealed
// manifest, so every edit re-sealed + re-uploaded all of it (multi-MB at scale).
// Now photo records are split into content-addressed shard blobs; the small root
// manifest just lists them, and a save re-seals only the shards that changed.
const GALLERY_SHARD_SIZE = 1000;

async function sha256Hex(str) {
    const dig = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(str));
    return [...new Uint8Array(dig)].map((x) => x.toString(16).padStart(2, '0')).join('');
}


window.LLGalleryStore = {
    data: null,
    version: 0,
    ready: false,
    loaded: false,
    _timer: null,
    _chain: null,
    _onError: null,
    _shards: [], // [{ ref, key, hash, count }] descriptors from the last load/save

    _blank() {
        return { v: 2, photos: [], albums: [], people: [] };
    },

    // Refs of the current photo shards, so the gallery's blob reconcile keeps
    // them (they hold the photo records, not referenced by any photo entry).
    shardRefs() {
        return this._shards.map((s) => s.ref).filter(Boolean);
    },

    // Seal raw bytes into a padded, content-addressed gallery blob → { ref, key }.
    async _sealBlob(bytes) {
        const enc = window.Vault.encryptContent(bytes, { name: 'shard.enc', mime: 'application/octet-stream' });
        const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
        const fd = new FormData();
        fd.append('_token', csrfToken());
        fd.append('file', cipher, cipher.name);
        const res = await fetch('/gallery/upload', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        if (! res.ok) throw new Error('shard upload failed');
        return { ref: (await res.json()).id, key: enc.encFileKey };
    },

    newId() { return _newId(); },

    async load() {
        const res = await fetch('/gallery/store', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (! res.ok) throw new Error('gallery store load failed');
        const d = await res.json();
        this.version = d.version ?? 0;
        this._shards = [];
        const root = d.ciphertext ? window.Vault.openManifest(d.ciphertext) : this._blank();

        if (root.v === 2 && Array.isArray(root.shards)) {
            // Sharded format: root lists the photo shards; load + decrypt them in
            // parallel (cheap on repeat visits — shard blobs cache immutably). A
            // failed shard THROWS (fails the whole load) rather than silently
            // dropping its photos — a partial in-memory set could then be saved and
            // would free the "missing" shard, losing data for good.
            const parts = await Promise.all(root.shards.map((s) => fetchDecryptWorker('/gallery/raw', s.ref, s.key)
                .then((b) => JSON.parse(new TextDecoder().decode(b)))));
            const photos = [];
            for (const arr of parts) if (Array.isArray(arr)) photos.push(...arr);
            this.data = { v: 2, photos, albums: root.albums || [], people: root.people || [] };
            this._shards = root.shards.map((s) => ({ ...s }));
        } else {
            // Legacy monolith (v1) or blank: load the inline photos as-is. Nothing
            // is lost — the next save writes them out as shards and shrinks the
            // root manifest. No migration step, no re-upload.
            this.data = { v: 2, photos: root.photos || [], albums: root.albums || [], people: root.people || [] };
        }

        this.loaded = true;
        this.ready = true;
        return this.data;
    },

    reset() { this.data = null; this.version = 0; this.ready = false; this.loaded = false; this._shards = []; clearTimeout(this._timer); },

    touch() {
        clearTimeout(this._timer);
        this._timer = setTimeout(() => this.flush(), 800);
    },

    // Serialised, awaitable, COALESCING save. Callers can `await flush()` and be
    // sure the CURRENT data was persisted. While a save is in flight, extra
    // flush() calls collapse into a single queued save (each _doFlush always
    // seals the latest in-memory data), so a burst of edits — a bulk/motion
    // delete cascading many shard re-seals plus the background ML/pairing passes
    // all flushing — no longer queues dozens of racing PUTs that fight over the
    // version counter and exhaust the 409 retry budget.
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

    // Split photos into shards, (re-)seal only the ones whose contents changed,
    // free shards that vanished, and return the small root manifest. Index-based
    // shards stay stable for the common cases (append new / edit in place); only
    // a mid-array purge cascades, which is rare.
    async _buildRoot() {
        const photos = this.data.photos || [];
        const descriptors = [];
        for (let i = 0; i < photos.length; i += GALLERY_SHARD_SIZE) {
            const chunk = photos.slice(i, i + GALLERY_SHARD_SIZE);
            const json = JSON.stringify(chunk);
            const hash = await sha256Hex(json);
            const prev = this._shards[descriptors.length];
            if (prev && prev.hash === hash && prev.ref) {
                descriptors.push(prev); // unchanged → reuse the existing shard blob
            } else {
                const sealed = await this._sealBlob(new TextEncoder().encode(json));
                descriptors.push({ ref: sealed.ref, key: sealed.key, hash, count: chunk.length });
            }
        }
        // Free shard blobs no longer referenced (shrunk library or replaced shards).
        const live = new Set(descriptors.map((d) => d.ref));
        for (const old of this._shards) if (old.ref && ! live.has(old.ref)) queueBlobDelete('/gallery/blob/' + old.ref, csrfToken());
        this._shards = descriptors;

        return { v: 2, shards: descriptors.map(({ ref, key, hash, count }) => ({ ref, key, hash, count })), albums: this.data.albums || [], people: this.data.people || [] };
    },

    async _doFlush(retry = 0) {
        if (! this.loaded || ! this.data) return;
        try {
            const root = await this._buildRoot();
            const body = JSON.stringify({ ciphertext: window.Vault.sealManifest(root), version: this.version });
            const res = await fetch('/gallery/store', { method: 'PUT', headers: jsonHeaders(), body });
            if (res.status === 409) {
                // Another writer (e.g. the background ML pass, or a second tab)
                // advanced the version. Adopt it and re-seal our data (this tab holds
                // the authoritative in-memory copy). Back off a touch so a burst of
                // concurrent flushes doesn't livelock.
                const cur = await fetch('/gallery/store', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
                this.version = cur.version ?? this.version;
                if (retry < 8) { await new Promise((r) => setTimeout(r, Math.min(120 * 2 ** retry, 2000))); return this._doFlush(retry + 1); }
                throw new Error('gallery store save conflict');
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
                throw new Error('gallery store save failed');
            }
        } catch (e) {
            if (this._onError) this._onError();
            throw e;
        }
    },
};

// Wait for the vault, then load the sealed gallery index once.
// App-wide confirm modal store (replaces native window.confirm everywhere).
// Usage in Alpine components: `if (! await this.$store.confirm.ask(msg)) return;`
document.addEventListener('alpine:init', () => {
    Alpine.store('confirm', {
        open: false, message: '', _resolve: null,
        isPrompt: false, input: '', placeholder: '', okLabel: '',
        ask(message) {
            this.message = message || ''; this.isPrompt = false; this.okLabel = '';
            this.open = true;
            return new Promise((resolve) => { this._resolve = resolve; });
        },
        // In-app replacement for window.prompt: resolves to the entered string, or
        // null if cancelled. `opts` = { value, placeholder, ok }.
        prompt(message, opts = {}) {
            this.message = message || ''; this.isPrompt = true;
            this.input = opts.value || ''; this.placeholder = opts.placeholder || ''; this.okLabel = opts.ok || '';
            this.open = true;
            return new Promise((resolve) => { this._resolve = resolve; });
        },
        yes() {
            const val = this.isPrompt ? this.input : true;
            this.open = false; const r = this._resolve; this._resolve = null; if (r) r(val);
        },
        no() {
            this.open = false; const r = this._resolve; this._resolve = null;
            if (r) r(this.isPrompt ? null : false);
        },
    });

    // Global navigation/off-canvas state. Drives the mobile hamburger nav drawer
    // and the per-module sidebar slide-over. Opening one closes the other.
    Alpine.store('nav', {
        navOpen: false,
        sidebarOpen: false,
        toggleNav() { this.navOpen = ! this.navOpen; if (this.navOpen) this.sidebarOpen = false; },
        toggleSidebar() { this.sidebarOpen = ! this.sidebarOpen; if (this.sidebarOpen) this.navOpen = false; },
        closeAll() { this.navOpen = false; this.sidebarOpen = false; },
    });

    // Reactive wrapper around the zero-knowledge Vault crypto module. Tracks
    // whether the user's vault is configured (server) and unlocked (this login),
    // so the Files UI can gate on it. All crypto stays in window.Vault; nothing
    // secret is held here.
    Alpine.store('vault', {
        configured: false,
        ready: false, // true once the cached key has been restored (or not) at load
        _unlockedAt: 0, // reactive nonce bumped on lock/unlock so getters re-run
        get unlocked() { this._unlockedAt; return window.Vault.unlocked(); },
        async init() {
            // Restore the cached key (it survives navigation between modules)
            // BEFORE anything reads `unlocked`, and bump the reactive nonce so the
            // getter reflects the restored state — otherwise leaving + returning to
            // Files would wrongly show the vault as locked.
            try { await window.Vault.boot(); } catch (e) { /* stays locked */ }
            this._unlockedAt++;
            this.ready = true;
            try { this.configured = (await window.Vault.status()).configured; } catch (e) { /* leave false */ }
            // Idle watchdog: auto-lock once the cached key's idle window passes,
            // and extend that window on real user activity. Runs in-page (the
            // previous check only ran at page load).
            const bump = () => { if (window.Vault.unlocked()) window.Vault.touch(); };
            let last = 0;
            const onActivity = () => { const t = Date.now(); if (t - last > 15000) { last = t; bump(); } };
            ['pointerdown', 'keydown', 'scroll'].forEach((ev) => window.addEventListener(ev, onActivity, { passive: true }));
            setInterval(() => {
                if (window.Vault.unlocked() && window.Vault.expiresAt() > 0 && Date.now() > window.Vault.expiresAt()) {
                    this.lock();
                }
            }, 15000);
            // NB: do NOT lock on `pagehide` — it fires on every same-tab navigation
            // and reload, which would drop the cached key on each page change and
            // force re-entry of the passphrase everywhere. The key is held in
            // sessionStorage (already cleared by the browser when the tab closes),
            // bound to the current login (vault-owner), and auto-locked by the idle
            // watchdog above — so it correctly survives navigation but not a real
            // tab close, logout or idle timeout.
        },
        async setup(passphrase, remember = true) {
            const code = await window.Vault.setup(passphrase, remember);
            this.configured = true; this._unlockedAt++;
            return code;
        },
        async unlock(passphrase, remember = true) { await window.Vault.unlock(passphrase, remember); this._unlockedAt++; },
        async recover(code, remember = true) { await window.Vault.recover(code, remember); this._unlockedAt++; },
        async changePassphrase(a, b) { const code = await window.Vault.changePassphrase(a, b); this._unlockedAt++; return code; },
        async setPassphrase(b) { const code = await window.Vault.setPassphrase(b); this._unlockedAt++; return code; },
        lock() { window.Vault.lock(); this._unlockedAt++; },
    });
    Alpine.store('vault').init();
});

// CSP-safe replacement for inline `onsubmit="return confirm(...)"`: any form
// carrying data-confirm asks (via the in-app modal, not window.confirm) before
// submitting. Lets the CSP drop 'unsafe-inline' for scripts.
document.addEventListener('submit', (e) => {
    const form = e.target;
    const message = form?.getAttribute?.('data-confirm');
    if (! message || form.dataset.confirmed) return;
    e.preventDefault();
    Alpine.store('confirm').ask(message).then((ok) => {
        if (ok) { form.dataset.confirmed = '1'; form.submit(); }
    });
}, true);

// Heavy, feature-specific libraries are code-split and loaded on first use so
// they stay out of the initial bundle (pages that never open an editor / export
// a PDF / bulk-download / view a map never download them). Each loader is
// memoised.

/**
 * Live backup run list: loads recent runs as JSON, refreshes after "back up
 * now" (no page reload) and polls while any run is still running. Each finished
 * run can be expanded to its log or downloaded.
 */
Alpine.data('backupRuns', backupRuns);

/**
 * Fire a transient toast. `url` (optional) renders a link inside the toast.
 */
function toast(message, url = null) {
    window.dispatchEvent(new CustomEvent('ll-toast', { detail: { message, url } }));
}
window.llToast = toast;

// Component registrations (definitions live in ./components/*).
Alpine.data('toastHub', toastHub);
Alpine.data('cropModal', cropModal);
Alpine.data('devicePairing', devicePairing);

Alpine.data('paperlessSettings', paperlessSettings);
Alpine.data('notificationBell', notificationBell);

/**
 * File explorer: multiselect, a shared "move to folder" modal (for a single row
 * or the whole selection), inline rename, and a bulk-delete modal.
 *
 * @param {number[]} allIds  Ids of the files currently listed.
 */
/* ---- Zero-knowledge gallery (client-driven) ----
 *
 * The whole library lives in a sealed index (LLGalleryStore); photo bytes +
 * renditions + a per-photo metadata blob are opaque blobs. Nothing is server-
 * readable. On unlock the client processes any un-processed uploads through the
 * transient /gallery/process endpoint (plaintext in, derived out, discarded),
 * re-seals the derived data, and renders the grid from decrypted thumbnails.
 */
/**
 * Public album share viewer (/s/{token}). Runs WITHOUT a vault: the share key
 * comes from the URL fragment and unwraps each blob's per-file key from the
 * sealed manifest. No key or plaintext ever goes to the server.
 */
Alpine.data('publicShare', publicShare);

/**
 * Public file/folder share viewer (/s/{token}). Like publicShare but lists files
 * (a single file or a folder subtree) with preview + download; the share key from
 * the fragment unwraps each blob's per-file key. No key/plaintext hits the server.
 */
Alpine.data('fileShare', fileShare);

Alpine.data('vaultGallery', vaultGallery);

/* ---- Zero-knowledge file browser (manifest model) ----
 *
 * The whole directory structure lives in one encrypted manifest; the server
 * stores only that ciphertext and anonymous, padded content blobs. Everything
 * below — listing, search, sort, rename, move, delete — runs on the decrypted
 * manifest in memory and is written back as a whole (optimistic-locked).
 */

/**
 * Shared Paperless transfer state. One store drives a single modal reused by
 * both the mail attachment list and the file browser: it holds the cached
 * quick-pick terms, the document being sent, and the metadata form.
 */
Alpine.store('paperless', {
    configured: false,
    loaded: false,
    tags: [], documentTypes: [], correspondents: [],
    labels: {},

    open: false,
    submitting: false,
    preparing: false, // fetching/decrypting the document while the modal is open
    error: '',
    file: null, filename: '',
    // Autocomplete query text per picker (also the name used when the typed
    // value has no match and a new term is created on the fly).
    corrQuery: '', typeQuery: '', tagQuery: '',
    // Set when opened from the file browser: offer to delete the stored file
    // after a successful upload (like the Markdown-to-note migration).
    allowDelete: false,
    deleteAfter: true,
    context: null,
    form: { title: '', correspondent: '', documentType: '', tags: [], created: '' },

    // ---- Autocomplete filtering + selection ----
    matches(list, query, exclude = []) {
        const q = (query || '').trim().toLowerCase();
        return list
            .filter((x) => ! exclude.includes(x.id))
            .filter((x) => q === '' || x.name.toLowerCase().includes(q))
            .slice(0, 50);
    },
    get filteredCorrespondents() { return this.matches(this.correspondents, this.corrQuery); },
    get filteredDocumentTypes() { return this.matches(this.documentTypes, this.typeQuery); },
    get filteredTags() { return this.matches(this.tags, this.tagQuery, this.form.tags); },

    // Offer "Create «x»" only when the typed name has no exact match.
    canCreate(list, query) {
        const q = (query || '').trim();
        return q !== '' && ! list.some((x) => x.name.toLowerCase() === q.toLowerCase());
    },

    tagName(id) { return (this.tags.find((t) => t.id === id) || {}).name || ''; },

    selectCorrespondent(c) { this.form.correspondent = c.id; this.corrQuery = c.name; },
    clearCorrespondent() { this.form.correspondent = ''; this.corrQuery = ''; },
    selectDocumentType(t) { this.form.documentType = t.id; this.typeQuery = t.name; },
    clearDocumentType() { this.form.documentType = ''; this.typeQuery = ''; },
    addTag(t) { if (! this.form.tags.includes(t.id)) this.form.tags.push(t.id); this.tagQuery = ''; },
    removeTag(id) { this.form.tags = this.form.tags.filter((x) => x !== id); },

    async init() {
        await this.load();
    },

    async load() {
        try {
            const res = await fetch('/paperless/terms', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            const b = await res.json();
            this.configured = !! b.configured;
            this.tags = b.tags ?? [];
            this.documentTypes = b.document_types ?? [];
            this.correspondents = b.correspondents ?? [];
            this.loaded = true;
        } catch (e) { /* stay unconfigured */ }
    },

    _reset(filename, defaults = {}, opts = {}) {
        this.filename = filename || 'document.pdf';
        this.error = '';
        this.corrQuery = this.typeQuery = this.tagQuery = '';
        this.allowDelete = !! opts.allowDelete;
        this.deleteAfter = this.allowDelete; // default to deleting, like the note migration
        this.context = opts.context ?? null;
        this.form = {
            title: defaults.title ?? this.filename.replace(/\.[^.]+$/, ''),
            correspondent: '', documentType: '', tags: [],
            created: defaults.created ?? new Date().toISOString().slice(0, 10),
        };
        this.open = true;
        if (! this.loaded) this.load();
    },

    // Open the modal immediately with the document already in hand.
    openFor(blob, filename, defaults = {}, opts = {}) {
        this._reset(filename, defaults, opts);
        this.file = blob;
        this.preparing = false;
    },

    // Open the modal right away while the document is still being fetched /
    // decrypted (IMAP round-trip or client-side decryption can take seconds);
    // setFile() fills it in when ready, so the UI never blocks.
    begin(filename, defaults = {}, opts = {}) {
        this._reset(filename, defaults, opts);
        this.file = null;
        this.preparing = true;
    },
    setFile(blob) { this.file = blob; this.preparing = false; },
    fail(msg) { this.error = msg || this.labels.failed; this.preparing = false; },

    close() { this.open = false; this.file = null; this.preparing = false; },

    async createTerm(kind, name) {
        name = (name || '').trim();
        if (! name) return;
        try {
            const res = await fetch('/paperless/terms', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ kind, name }),
            });
            const b = await res.json();
            if (! b.ok) { this.error = b.detail || this.labels.failed; return; }
            const item = { id: b.id, name: b.name };
            // create() is idempotent server-side, so avoid duplicating a term
            // that already sits in the cached list.
            const upsert = (list) => { if (! list.some((x) => x.id === b.id)) list.push(item); };
            if (kind === 'tag') { upsert(this.tags); this.addTag(item); }
            if (kind === 'document_type') { upsert(this.documentTypes); this.selectDocumentType(item); }
            if (kind === 'correspondent') { upsert(this.correspondents); this.selectCorrespondent(item); }
        } catch (e) { this.error = this.labels.failed; }
    },

    async submit() {
        if (! this.file || this.submitting || this.preparing) return;
        this.submitting = true; this.error = '';
        try {
            const fd = new FormData();
            fd.append('file', this.file, this.filename);
            if (this.form.title) fd.append('title', this.form.title);
            if (this.form.created) fd.append('created', this.form.created);
            if (this.form.correspondent) fd.append('correspondent', this.form.correspondent);
            if (this.form.documentType) fd.append('document_type', this.form.documentType);
            (this.form.tags || []).forEach((t) => fd.append('tags[]', t));
            const res = await fetch('/paperless/documents', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: fd,
            });
            const b = await res.json();
            if (b.ok) {
                this.open = false; this.file = null;
                window.dispatchEvent(new CustomEvent('paperless-sent', {
                    detail: { deleteAfter: this.allowDelete && this.deleteAfter, context: this.context },
                }));
            } else {
                this.error = b.detail || this.labels.failed;
            }
        } catch (e) { this.error = this.labels.failed; }
        this.submitting = false;
    },
});


Alpine.data('vaultFiles', vaultFiles);


/* ---- Zero-knowledge notes (manifest model) ----
 *
 * Whole notes — titles, markdown content, tags, timestamps — live inside one
 * encrypted manifest; the server stores only that ciphertext. Rendering uses
 * GitHub-flavored markdown, sanitised before it touches the DOM.
 */


Alpine.plugin(intersect);

window.Alpine = Alpine;

/**
 * To-do lists + tasks. Zero-knowledge: everything lives in the opaque manifest
 * (one sealed blob shared with notes/bookmarks), so there is no fetch/seal per
 * row — fields (incl. list names + due dates) are plaintext inside the sealed
 * manifest and every mutation edits the in-memory arrays in place then schedules
 * a debounced sealed save. Due dates are sealed too, so there are no server-side
 * reminders — any reminder would only ever be client-side.
 */
/**
 * Shared lifecycle for the zero-knowledge manifest modules (notes, bookmarks,
 * to-dos). They all point local arrays at window.LLStore.data.* once the vault
 * is unlocked, mutate them in place, and schedule a debounced sealed save; on
 * lock they clear those arrays and reset the store. Each component spreads
 * this and supplies its module-specific bits.
 *
 * cfg.map: { <LLStore.data key>: '<component property>' } — the collections to
 *          wire (e.g. { todos: 'tasks', todoLists: 'lists' }).
 * cfg.onLock(self): optional extra reset (e.g. notes clears currentId).
 */

Alpine.data('invoices', invoices);

Alpine.data('todos', todos);

/**
 * Notes: zero-knowledge markdown. Each note's {title, content, tags} is sealed
 * with the per-user vault key; the server only stores/returns ciphertext. The
 * browser decrypts, renders the markdown itself (DOMPurify-sanitised) and re-seals
 * on save. No server render, search or share.
 */
Alpine.data('notes', notes);


/**
 * Contacts. Zero-knowledge: every record lives in the opaque /store manifest
 * (shared with notes/todos) — plaintext inside the sealed blob, so CRUD just
 * edits the in-memory array and schedules a debounced sealed save. The only
 * per-record blob is the optional avatar (kept OUT of the manifest so it stays
 * small): encrypted + uploaded to the contacts blob store, referenced by
 * avatarRef/avatarKey. vCard mapping + gallery-person linking build on this.
 */
Alpine.data('contacts', contacts);


/**
 * Bookmarks + folders. Zero-knowledge: everything lives in the opaque manifest
 * (one sealed blob shared with notes/todos), so there is no fetch/seal per row —
 * fields are plaintext inside the sealed manifest and every mutation edits the
 * in-memory arrays in place then schedules a debounced sealed save.
 */
Alpine.data('bookmarks', bookmarks);

/**
 * Mail signatures management page: list + rich-text editor for reusable HTML
 * signatures (unlimited, one default).
 */

/**
 * Mail identities management page: all identities grouped by account, each
 * editable with an optional linked signature. At least one identity per account.
 */

/**
 * Dedicated mail account settings page (add/edit). Clean sectioned form with an
 * IMAP + SMTP connection test; identities and signatures are managed on their
 * own pages (linked from here). Replaces the cramped account modal.
 */

/**
 * Password manager ("passwords"). Zero-knowledge like the other opaque-store
 * modules: every secret lives as a record in the sealed manifest (LLStore.data
 * .secrets), unlocked with the vault key. Six item types (login, password,
 * card, wifi, license, server); per-item version history on every field change;
 * client-side TOTP, password generator, Wi-Fi QR, and copy-with-auto-clear.
 */
Alpine.data('passwords', passwords);

Alpine.start();

// PWA: register the service worker (network-first navigations with an offline
// fallback; hashed build assets cached). Registration failures are non-fatal.
// Skip on public visitor pages (upload/download/share links) — they must not be
// handled by the app's PWA shell.
if ('serviceWorker' in navigator && ! /^\/(u|f|p)\//.test(location.pathname)) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

// Theme "system": follow OS scheme changes live (the head bootstrap only ran
// at load). Explicit light/dark settings ignore the OS.
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (document.documentElement.dataset.theme === 'system') {
        document.documentElement.classList.toggle('dark', e.matches);
    }
});
