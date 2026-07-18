import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { Vault, ShareCrypto } from './vault';
import { parseCsv as pwParseCsv, detectCsv as pwDetectCsv, cardBrand as pwCardBrand, totpSecret as pwTotpSecret, totp as pwTotp, pwScore as pwStrength } from './passwords-util';
import { PW_WORDS } from './shared/wordlists';
import { escapeHtml, saveBlobAs } from './shared/dom';
import { normVec as _normVec, dotVec as _dotVec } from './shared/vector-math';
import { EXT_CATEGORY, extOf, fileCategory, CATEGORY_ICON, formatBytes } from './shared/file-categories';
import { csrfToken, jsonHeaders, apiRequest } from './shared/api';
import { contactNameParts, contactDisplayName, contactsSortPref } from './shared/contact-utils';
import { ocrWorker, ocrImage } from './shared/ocr';
import { loadLeaflet, loadCodeMirror, cmModule } from './shared/lazy-loaders';
import { fetchBlobBuffer, fetchDecrypt, fetchDecryptWorker, thumbLane, queueBlobDelete } from './shared/blob-io';
import { loadMarkdown } from './shared/markdown';
import { bootStore, bootGalleryStore, zkModule } from './shared/zk-module';
import { padmeSize, padBlob } from './shared/padme';
import contacts from './components/contacts';
import passwords from './components/passwords';
import vaultFiles from './components/files';
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
    newId() {
        const b = new Uint8Array(16);
        crypto.getRandomValues(b);
        return [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    },

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

    newId() {
        const b = new Uint8Array(16);
        crypto.getRandomValues(b);
        return [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    },

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
Alpine.data('publicShare', (config = {}, labels = {}) => ({
    state: 'boot', // boot | password | ready | error | expired | notfound
    error: '',
    sk: null,
    manifest: null, // { name, allowDownload, photos: [] }
    password: '',
    unlocking: false,
    thumbs: {},
    viewer: { open: false, src: '', kind: 'none', photo: null },

    async init() {
        // Share key from the fragment (#s:<b64>) — never sent to the server.
        const m = (location.hash || '').match(/s:([A-Za-z0-9_\-+/=]+)/);
        this.sk = m ? decodeURIComponent(m[1]) : null;
        try {
            const res = await fetch(config.metaUrl, { headers: { Accept: 'application/json' } });
            if (res.status === 404) { this.state = 'notfound'; return; }
            if (res.status === 410) { this.state = 'expired'; return; }
            const meta = await res.json();
            if (! meta.found) { this.state = 'notfound'; return; }
            if (meta.expired) { this.state = 'expired'; return; }
            if (! this.sk) { this.state = 'error'; this.error = labels.noKey || ''; return; }
            if (meta.needsPassword && ! meta.unlocked) { this.state = 'password'; return; }
            await this.loadManifest();
        } catch (e) { this.state = 'error'; }
    },

    async unlock() {
        if (this.unlocking) return;
        this.unlocking = true; this.error = '';
        try {
            const res = await fetch(config.unlockUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ password: this.password }) });
            if (! res.ok) { this.error = labels.wrongPassword || ''; return; }
            this.password = '';
            await this.loadManifest();
        } catch (e) { this.error = labels.wrongPassword || ''; } finally { this.unlocking = false; }
    },

    async loadManifest() {
        this.state = 'boot';
        try {
            const res = await fetch(config.manifestUrl, { headers: { Accept: 'application/json' } });
            if (! res.ok) throw new Error('manifest');
            const { sealed } = await res.json();
            const bytes = await window.ShareCrypto.unwrap(sealed, this.sk);
            this.manifest = JSON.parse(new TextDecoder().decode(bytes));
            this.state = 'ready';
        } catch (e) { this.state = 'error'; this.error = labels.badKey || ''; }
    },

    get photos() { return this.manifest?.photos || []; },

    async _blob(ref, keyJson) {
        const buf = await (await fetch(`${config.blobBase}/${ref}`)).arrayBuffer();
        const fk = await window.ShareCrypto.unwrap(keyJson, this.sk);
        return window.ShareCrypto.decrypt(buf, fk);
    },

    async thumbFor(p) {
        if (! p.tR || this.thumbs[p.id]) return this.thumbs[p.id];
        try {
            const bytes = await this._blob(p.tR, p.tK);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this.thumbs[p.id] = url;
            return url;
        } catch (e) { return ''; }
    },

    async openViewer(p) {
        let ref = p.mR || p.tR, key = p.mK || p.tK, kind = 'image', mime = 'image/jpeg';
        if (p.t === 'video' && this.manifest?.allowDownload && p.oR) { ref = p.oR; key = p.oK; kind = 'video'; mime = 'video/mp4'; }
        this.viewer = { open: true, src: '', kind, photo: p };
        try {
            const bytes = await this._blob(ref, key);
            this.viewer.src = URL.createObjectURL(new Blob([bytes], { type: mime }));
        } catch (e) { this.closeViewer(); }
    },
    closeViewer() { if (this.viewer.src) URL.revokeObjectURL(this.viewer.src); this.viewer = { open: false, src: '', kind: 'none', photo: null }; },

    canDownload(p) { return ! ! (this.manifest?.allowDownload && p && p.oR); },
    async download(p) {
        if (! this.canDownload(p)) return;
        try {
            const bytes = await this._blob(p.oR, p.oK);
            const url = URL.createObjectURL(new Blob([bytes]));
            const a = document.createElement('a'); a.href = url; a.download = p.id || 'photo'; a.click();
            setTimeout(() => URL.revokeObjectURL(url), 5000);
        } catch (e) { /* ignore */ }
    },
    fmtDate(v) { if (! v) return ''; try { return new Date(v).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }); } catch (e) { return ''; } },
}));

/**
 * Public file/folder share viewer (/s/{token}). Like publicShare but lists files
 * (a single file or a folder subtree) with preview + download; the share key from
 * the fragment unwraps each blob's per-file key. No key/plaintext hits the server.
 */
Alpine.data('fileShare', (config = {}, labels = {}) => ({
    state: 'boot', // boot | password | ready | error | expired | notfound
    error: '',
    sk: null,
    manifest: null, // { kind, name, files: [] }
    password: '',
    unlocking: false,
    thumbs: {},
    viewer: { open: false, kind: 'none', src: '', file: null },

    async init() {
        const m = (location.hash || '').match(/s:([A-Za-z0-9_\-+/=]+)/);
        this.sk = m ? decodeURIComponent(m[1]) : null;
        try {
            const res = await fetch(config.metaUrl, { headers: { Accept: 'application/json' } });
            if (res.status === 404) { this.state = 'notfound'; return; }
            if (res.status === 410) { this.state = 'expired'; return; }
            const meta = await res.json();
            if (! meta.found) { this.state = 'notfound'; return; }
            if (meta.expired) { this.state = 'expired'; return; }
            if (! this.sk) { this.state = 'error'; this.error = labels.noKey || ''; return; }
            if (meta.needsPassword && ! meta.unlocked) { this.state = 'password'; return; }
            await this.loadManifest();
        } catch (e) { this.state = 'error'; }
    },
    async unlock() {
        if (this.unlocking) return;
        this.unlocking = true; this.error = '';
        try {
            const res = await fetch(config.unlockUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ password: this.password }) });
            if (! res.ok) { this.error = labels.wrongPassword || ''; return; }
            this.password = '';
            await this.loadManifest();
        } catch (e) { this.error = labels.wrongPassword || ''; } finally { this.unlocking = false; }
    },
    async loadManifest() {
        this.state = 'boot';
        try {
            const res = await fetch(config.manifestUrl, { headers: { Accept: 'application/json' } });
            if (! res.ok) throw new Error('manifest');
            const { sealed } = await res.json();
            const bytes = await window.ShareCrypto.unwrap(sealed, this.sk);
            this.manifest = JSON.parse(new TextDecoder().decode(bytes));
            this.state = 'ready';
        } catch (e) { this.state = 'error'; this.error = labels.badKey || ''; }
    },
    cwd: '', // current relative folder path within a shared folder ('' = its root)
    get allFiles() { return this.manifest?.files || []; },
    get isFolder() { return this.manifest?.kind === 'folder'; },
    // Files that sit directly in the current folder.
    get filesHere() { return this.allFiles.filter((f) => (f.path || '') === this.cwd); },
    // Immediate subfolder names under the current folder (derived from the paths).
    get subfolders() {
        const prefix = this.cwd === '' ? '' : this.cwd + '/';
        const set = new Set();
        for (const f of this.allFiles) {
            const p = f.path || '';
            if (this.cwd !== '' && p !== this.cwd && ! p.startsWith(prefix)) continue;
            const rest = this.cwd === '' ? p : p.slice(prefix.length);
            if (rest) set.add(rest.split('/')[0]);
        }
        return [...set].sort((a, b) => a.localeCompare(b));
    },
    get crumbs() {
        if (! this.cwd) return [];
        const segs = this.cwd.split('/');
        return segs.map((name, i) => ({ name, path: segs.slice(0, i + 1).join('/') }));
    },
    enterFolder(name) { this.cwd = this.cwd === '' ? name : this.cwd + '/' + name; },
    goTo(path) { this.cwd = path || ''; },
    // How many files live anywhere under a subfolder of the current folder.
    folderFileCount(name) {
        const base = (this.cwd === '' ? '' : this.cwd + '/') + name;
        return this.allFiles.filter((f) => { const p = f.path || ''; return p === base || p.startsWith(base + '/'); }).length;
    },
    isImage(f) { return /^image\//.test(f.mime || '') && ! /svg/.test(f.mime || ''); },
    isPdf(f) { return (f.mime || '') === 'application/pdf'; },
    async _blob(f) {
        const buf = await (await fetch(`${config.blobBase}/${f.ref}`)).arrayBuffer();
        const fk = await window.ShareCrypto.unwrap(f.key, this.sk);
        return window.ShareCrypto.decrypt(buf, fk);
    },
    async thumbFor(f) {
        if (! this.isImage(f) || this.thumbs[f.ref]) return this.thumbs[f.ref];
        try { const b = await this._blob(f); const url = URL.createObjectURL(new Blob([b], { type: f.mime })); this.thumbs[f.ref] = url; return url; } catch (e) { return ''; }
    },
    async open(f) {
        if (this.isImage(f) || this.isPdf(f)) {
            this.viewer = { open: true, kind: this.isPdf(f) ? 'pdf' : 'image', src: '', file: f };
            try { const b = await this._blob(f); this.viewer.src = URL.createObjectURL(new Blob([b], { type: f.mime })); } catch (e) { this.closeViewer(); }
        } else { this.download(f); }
    },
    closeViewer() { if (this.viewer.src) URL.revokeObjectURL(this.viewer.src); this.viewer = { open: false, kind: 'none', src: '', file: null }; },
    async download(f) {
        try {
            const b = await this._blob(f);
            const url = URL.createObjectURL(new Blob([b], { type: f.mime || 'application/octet-stream' }));
            const a = document.createElement('a'); a.href = url; a.download = f.name || 'file'; a.click();
            setTimeout(() => URL.revokeObjectURL(url), 5000);
        } catch (e) { /* ignore */ }
    },
    fmtSize(n) { n = n || 0; const u = ['B', 'KB', 'MB', 'GB']; let i = 0; while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; } return (i ? n.toFixed(1) : n) + ' ' + u[i]; },
}));

Alpine.data('vaultGallery', (config = {}, labels = {}) => {
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
        // Leaving the library clears the selection + any active search; entering
        // the map (re)builds it from the geotagged photos.
        this.$watch('view', (v) => {
            this.selected = [];
            if (v !== 'library') this.clearSearch();
            if (v === 'map') this.renderMap();
            else this._destroyMap();
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
        const pid = new URLSearchParams(location.search).get('person');
        if (pid && (this.index.people || []).some((pp) => pp.id === pid)) { this.activePerson = pid; this.view = 'person'; }
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
        if (d.thumb) { const r = await this._encStore(this._b64bytes(d.thumb), 'thumb.enc'); p.thumbRef = r.id; p.thumbKey = r.key; }
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
        p.width = d.width; p.height = d.height; p.duration = d.duration;
        p.lat = d.exif?.lat ?? null; p.lng = d.exif?.lon ?? null;
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
    async _fileSig(file) {
        try {
            const cap = 1024 * 1024;
            const head = new Uint8Array(await file.slice(0, Math.min(cap, file.size)).arrayBuffer());
            const tail = file.size > cap ? new Uint8Array(await file.slice(file.size - cap).arrayBuffer()) : new Uint8Array(0);
            const buf = new Uint8Array(head.length + tail.length);
            buf.set(head, 0); buf.set(tail, head.length);
            const dig = await crypto.subtle.digest('SHA-256', buf);
            const hex = [...new Uint8Array(dig)].map((x) => x.toString(16).padStart(2, '0')).join('');
            return `${file.size}:${hex}`;
        } catch (e) { return ''; }
    },

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
        else if (p.lat != null) this._renderMiniMap(p.lat, p.lng);
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
            const lat = m.exif?.lat ?? p.lat;
            const lng = m.exif?.lon ?? p.lng;
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
        this.loc = { open: true, bulk: false, target: p, lat: p.lat ?? null, lng: p.lng ?? null };
        this._mountLocMap(p.lat ?? this.viewer.meta?.exif?.lat ?? 48.2082, p.lng ?? this.viewer.meta?.exif?.lon ?? 16.3738, p.lat != null);
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
                this._eachSelected((p) => { p.lat = this.loc.lat; p.lng = this.loc.lng; });
                this.selected = [];
                this._save();
            } else if (this.loc.target) {
                const p = this.loc.target;
                p.lat = this.loc.lat; p.lng = this.loc.lng;
                if (this.viewer.meta?.exif) { this.viewer.meta.exif.lat = p.lat; this.viewer.meta.exif.lon = p.lng; }
                this._renderMiniMap(p.lat, p.lng);
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
            const res = await fetch(config.geocodeUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
            this.geoResults = res.ok ? ((await res.json()).results || []) : [];
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
            const res = await fetch(config.embedTextUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ q }) });
            const qv = res.ok ? (await res.json()).embedding : null;
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

    /* ---- Map ---- */
    _map: null,
    _miniMap: null,
    _geoLoaded: false,
    get mapPhotos() { return this._cache('mapPhotos', () => this.libraryPhotos.filter((p) => p.lat != null && p.lng != null)); },
    // Photos processed before GPS was promoted onto the index carry their
    // coordinates only inside the meta blob; backfill lat/lng from there once.
    geoProgress: { done: 0, total: 0 },
    async _ensureGeo() {
        if (this._geoLoaded) return;
        // Only photos we've never inspected: those with promoted coords are done,
        // and `geoChecked` marks the geo-less ones so we never decrypt them again
        // (across sessions), which is what made the first map open crawl.
        const targets = this.libraryPhotos.filter((p) => p.lat == null && ! p.geoChecked && p.metaRef);
        if (! targets.length) { this._geoLoaded = true; return; }
        this.geoProgress = { done: 0, total: targets.length };
        let changed = false, i = 0, done = 0;
        // Decrypt the meta blobs in parallel on the worker pool (off the main
        // thread; the ciphertext fetch hits the immutable blob cache).
        const worker = async () => {
            while (i < targets.length) {
                const p = targets[i++];
                try {
                    const b = await fetchDecryptWorker('/gallery/raw', p.metaRef, p.metaKey);
                    const m = JSON.parse(new TextDecoder().decode(b));
                    if (m.exif && m.exif.lat != null) {
                        p.lat = m.exif.lat; p.lng = m.exif.lon;
                        if (! p.camera && m.exif.camera) p.camera = m.exif.camera;
                    }
                } catch (e) { /* skip */ }
                p.geoChecked = true; changed = true;
                this.geoProgress = { done: ++done, total: targets.length };
            }
        };
        await Promise.all(Array.from({ length: Math.min(8, targets.length) }, worker));
        this._geoLoaded = true;
        if (changed) this._save();
    },
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
    async renderMap() {
        await this._ensureGeo();
        const L = await loadLeaflet();
        this.$nextTick(() => {
            const el = this.$refs.map;
            if (! el) return;
            if (this._map) { this._map.remove(); this._map = null; }
            // Zoom animation off: with markercluster + fitBounds/double-click, an
            // in-flight animateZoom can fire on a torn-down pane and throw
            // "_latLngToNewLayerPoint of null". Instant zoom sidesteps it entirely.
            this._map = L.map(el, { zoomAnimation: false, markerZoomAnimation: false }).setView([20, 0], 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(this._map);
            const cluster = L.markerClusterGroup ? L.markerClusterGroup({ animate: false }) : L.layerGroup();
            for (const p of this.mapPhotos) {
                const m = L.marker([p.lat, p.lng]);
                m.on('click', () => this.openViewer(p));
                cluster.addLayer(m);
            }
            this._map.addLayer(cluster);
            if (this.mapPhotos.length) this._map.fitBounds(L.latLngBounds(this.mapPhotos.map((p) => [p.lat, p.lng])), { padding: [40, 40], maxZoom: 14, animate: false });
            setTimeout(() => { if (this._map) this._map.invalidateSize(); }, 120);
        });
    },
    // Tear the map down when leaving the map view so stray animation callbacks
    // can't fire on a hidden/half-removed container.
    _destroyMap() { if (this._map) { this._map.remove(); this._map = null; } },

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
            const res = await fetch(config.sharesUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(this._shareBody(sealed, refs)) });
            if (! res.ok) throw new Error('share failed');
            const { token } = await res.json();
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
            const res = await fetch(`${config.sharesUrl}/${al.share.token}`, { method: 'PUT', headers: jsonHeaders(), body: JSON.stringify(body) });
            if (! res.ok) throw new Error('share update failed');
            al.share.allowDownload = this.share.allowDownload;
            al.share.expiresAt = this.share.expiresAt || null;
            if (this.share.password.trim()) al.share.hasPassword = true;
            else if (body.clear_password) al.share.hasPassword = false;
            this.share.password = '';
            this._save();
        } catch (e) { this.share.error = labels.shareError || 'Error'; } finally { this.share.busy = false; }
    },

    _revokeShareRequest(token) {
        return fetch(`${config.sharesUrl}/${token}`, { method: 'DELETE', headers: jsonHeaders() });
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
                worker = new Worker(new URL('./scan.worker.js', import.meta.url), { type: 'module' });
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
            if (await bootStore(this.$store)) {
                const map = {};
                for (const c of (window.LLStore.data?.contacts || [])) if (! c.trashed) map[c.id] = c;
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
                worker = new Worker(new URL('./scan.worker.js', import.meta.url), { type: 'module' });
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
            if (! await bootStore(this.$store)) return;
            this._linkContacts = (window.LLStore.data.contacts || []).filter((c) => ! c.trashed);
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
        window.LLStore.touch(); // persist the /store side too
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
            window.LLStore.touch();
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
            if (await bootStore(this.$store)) {
                const c = (window.LLStore.data?.contacts || []).find((x) => x.id === cid);
                if (c && c.personId === p.id) { c.personId = null; c.personName = null; window.LLStore.touch(); }
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
        fetch(config.reconcileUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ blobs: [...new Set(blobs)] }) })
            .then((r) => r.ok && r.json()).then((u) => { if (u) this.usage = u; }).catch(() => {});
    },

    fmtBytes: formatBytes,
    fmtDate(iso) {
        if (! iso) return '';
        const d = new Date(iso);
        return isNaN(d.getTime()) ? '' : d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
    placeText(place) {
        if (! place) return '';
        if (typeof place === 'string') return place;
        return place.display || place.name || [place.city, place.state, place.country].filter(Boolean).join(', ') || '';
    },
    dismissUploads() { this.uploads = []; },
    get uploading() { return this.uploads.some((u) => u.state === 'uploading'); },
};
});

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

Alpine.data('invoices', (config = {}, labels = {}) => ({
    ...zkModule({ map: { invoices: 'invoices' }, onLock: (self) => { self.view = 'list'; self.current = null; } }),

    company: config.company || {},
    _labelsByLang: config.labelsByLang || {},
    invoices: [],
    view: 'list',        // 'list' | 'edit'
    current: null,       // the invoice being edited
    filterStatus: '',    // '' | draft | sent | paid
    _printing: null,     // invoice rendered into the hidden print sheet

    async init() {
        await this._initZk();
    },

    // ---- Derived ----
    get activeInvoices() { return (this.invoices || []).filter((i) => ! i.trashed); },
    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.activeInvoices;
        if (this.filterStatus) list = list.filter((i) => i.status === this.filterStatus);
        if (q) list = list.filter((i) => (i.number || '').toLowerCase().includes(q) || (i.customer?.name || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => (b.issueDate || '').localeCompare(a.issueDate || '') || (b.number || '').localeCompare(a.number || ''));
    },
    get totals() { return this.computeTotals(this.current); },

    _today() { return new Date().toISOString().slice(0, 10); },
    _addDays(iso, days) { const d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate() + (days || 0)); return d.toISOString().slice(0, 10); },
    _defaultVat() { const v = parseFloat(this.company.default_vat_rate); return Number.isFinite(v) ? v : 19; },

    // ---- CRUD ----
    newInvoice() {
        const issue = this._today();
        const inv = {
            id: window.LLStore.newId(),
            number: null,
            status: 'draft',
            issueDate: issue,
            dueDate: this._addDays(issue, parseInt(this.company.payment_terms_days, 10) || 14),
            currency: this.company.currency || 'EUR',
            lang: (document.documentElement.lang || 'de').slice(0, 2) === 'en' ? 'en' : 'de',
            customer: { name: '', attn: '', address: '', email: '', vatId: '', contactId: null },
            lines: [{ desc: '', qty: 1, unit: '', unitPrice: 0, vatRate: this._defaultVat() }],
            note: '',
            footer: this.company.footer_text || '',
            trashed: false,
            updated: new Date().toISOString(),
        };
        this.invoices.unshift(inv);
        this._save();
        this.open(inv);
    },
    open(inv) {
        // Backfill fields added after this invoice was created.
        inv.lang ??= 'de';
        inv.currency ??= (this.company.currency || 'EUR');
        inv.customer ??= { name: '', attn: '', address: '', email: '', vatId: '', contactId: null };
        inv.customer.attn ??= '';
        this.current = inv;
        this.view = 'edit';
    },
    backToList() { this.view = 'list'; this.current = null; },
    saveSoon() { if (this.current) this.current.updated = new Date().toISOString(); this._save(); },

    addLine() { this.current.lines.push({ desc: '', qty: 1, unit: '', unitPrice: 0, vatRate: this._defaultVat() }); this.saveSoon(); },
    removeLine(i) { this.current.lines.splice(i, 1); if (! this.current.lines.length) this.addLine(); else this.saveSoon(); },

    // ---- Clockify CSV import → prefill line items ----
    // RFC 4180 parse (quoted fields, "" escapes, CRLF); returns rows of fields.
    _parseCsv(text) {
        const rows = []; let row = [], field = '', inQ = false;
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            if (inQ) {
                if (ch === '"') { if (text[i + 1] === '"') { field += '"'; i++; } else inQ = false; }
                else field += ch;
            } else if (ch === '"') { inQ = true; }
            else if (ch === ',') { row.push(field); field = ''; }
            else if (ch === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
            else field += ch;
        }
        if (field.length || row.length) { row.push(field); rows.push(row); }
        return rows.filter((r) => r.some((c) => (c || '').trim() !== ''));
    },
    async importClockify(fileList) {
        const file = fileList && fileList[0];
        if (! file || ! this.current) return;
        try {
            const rows = this._parseCsv(await file.text());
            if (rows.length < 2) return;
            const head = rows[0].map((h) => h.trim().toLowerCase());
            const iDesc = head.indexOf('description');
            const iDur = head.indexOf('duration (decimal)');
            const iDate = head.indexOf('start date');
            if (iDesc < 0 || iDur < 0) { window.llToast?.(labels.csvBadFormat || 'CSV columns not found.'); return; }
            const unit = this.current.lang === 'en' ? 'h' : 'Std';
            const lines = [];
            for (let r = 1; r < rows.length; r++) {
                const desc = (rows[r][iDesc] || '').trim();
                const qty = parseFloat((rows[r][iDur] || '').replace(',', '.')) || 0;
                const date = iDate >= 0 ? (rows[r][iDate] || '').trim() : '';
                if (! desc && ! qty) continue;
                lines.push({ desc: date ? (date + '; ' + desc) : desc, qty, unit, unitPrice: 0, vatRate: this._defaultVat() });
            }
            if (! lines.length) { window.llToast?.(labels.csvBadFormat || 'No rows found.'); return; }
            const cur = this.current.lines;
            const onlyEmpty = cur.length === 1 && ! (cur[0].desc || '').trim() && ! cur[0].unitPrice;
            this.current.lines = onlyEmpty ? lines : [...cur, ...lines];
            this.saveSoon();
            window.llToast?.((labels.csvImported || ':n lines imported.').replace(':n', lines.length));
        } catch (e) { window.llToast?.(labels.csvBadFormat || 'Could not read CSV.'); }
    },

    trash(inv) { inv.trashed = new Date().toISOString(); this._save(); if (this.current === inv) this.backToList(); },
    restore(inv) { inv.trashed = false; this._save(); },
    async remove(inv) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm || 'Delete this invoice permanently?')) return;
        const i = this.invoices.indexOf(inv);
        if (i >= 0) this.invoices.splice(i, 1);
        this._save();
        if (this.current === inv) this.backToList();
    },

    // ---- Totals (net, VAT grouped by rate, gross) ----
    lineNet(l) { return (parseFloat(l.qty) || 0) * (parseFloat(l.unitPrice) || 0); },
    computeTotals(inv) {
        const t = { net: 0, vatByRate: {}, vat: 0, gross: 0 };
        if (! inv) return t;
        for (const l of inv.lines || []) {
            const net = this.lineNet(l);
            const rate = parseFloat(l.vatRate) || 0;
            t.net += net;
            const v = net * rate / 100;
            t.vatByRate[rate] = (t.vatByRate[rate] || 0) + v;
            t.vat += v;
        }
        t.gross = t.net + t.vat;
        return t;
    },
    fmtMoney(n, currency, lang) {
        const cur = currency || this.current?.currency || this.company.currency || 'EUR';
        const loc = (lang || this.current?.lang || 'de') === 'en' ? 'en' : 'de';
        try { return new Intl.NumberFormat(loc, { style: 'currency', currency: cur }).format(n || 0); }
        catch (e) { return (n || 0).toFixed(2) + ' ' + cur; }
    },
    // Print-sheet label in the invoice's own language (falls back to German).
    pl(key) {
        const lang = this._printing?.lang || 'de';
        const set = this._labelsByLang[lang] || this._labelsByLang.de || {};
        return set[key] || key;
    },
    // Currencies offered per invoice.
    currencyOptions: ['EUR', 'USD', 'CHF'],
    // Chosen print template (modern | elegant | schlicht).
    get tpl() { const t = this.company.template || 'editorial'; return t === 'schlicht' ? 'elegant' : t; },
    vatRatesOf(inv) { return Object.keys(this.computeTotals(inv).vatByRate).map(Number).sort((a, b) => a - b); },
    // Locale-formatted quantity (German uses a decimal comma).
    fmtQty(n, lang) {
        const loc = (lang || this.current?.lang || 'de') === 'en' ? 'en' : 'de';
        try { return new Intl.NumberFormat(loc, { maximumFractionDigits: 2 }).format(parseFloat(n) || 0); }
        catch (e) { return String(n ?? ''); }
    },

    // ---- Customer picker (reads zero-knowledge contacts) ----
    customerPicker: false,
    custQuery: '',
    _custContacts: [],
    async openCustomerPicker() {
        this.customerPicker = true;
        this.custQuery = '';
        try { if (await bootStore(this.$store)) this._custContacts = (window.LLStore.data.contacts || []).filter((c) => ! c.trashed); }
        catch (e) { /* leave empty */ }
    },
    closeCustomerPicker() { this.customerPicker = false; },
    _custName(c) { return contactDisplayName(c) || ''; },
    custSuggestions() {
        const q = this.custQuery.trim().toLowerCase();
        let list = this._custContacts;
        if (q) list = list.filter((c) => this._custName(c).toLowerCase().includes(q) || (c.org || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => this._custName(a).localeCompare(this._custName(b)));
    },
    _custAddress(c) {
        const a = (c.addresses || [])[0];
        if (! a) return '';
        return [a.street, [a.zip, a.city].filter(Boolean).join(' '), a.region, a.country].filter(Boolean).join('\n');
    },
    pickCustomer(c) {
        // Bill a company to its org name with the person as the contact (Attn);
        // a private contact bills to the person directly.
        const parts = contactNameParts(c);
        const person = [parts.first, parts.last].filter(Boolean).join(' ') || this._custName(c);
        const org = (c.org || '').trim();
        this.current.customer = {
            name: org || person,
            attn: org ? person : '',
            address: this._custAddress(c),
            email: (c.emails || [])[0]?.value || '',
            vatId: c.vatId || '',
            contactId: c.id,
        };
        this.customerPicker = false;
        this.saveSoon();
    },
    clearCustomer() { this.current.customer = { name: '', attn: '', address: '', email: '', vatId: '', contactId: null }; this.saveSoon(); },

    // ---- Finalize / status ----
    // Render a number template. YYYY/YY/MM/DD from the issue date, and a run of
    // N's becomes the zero-padded sequence (NNNN → 0042). Longer tokens first.
    _formatNumber(fmt, seq, issueDate) {
        const d = issueDate ? new Date(issueDate + 'T00:00:00') : new Date();
        const y = d.getFullYear();
        return (fmt || 'YYYY-NNNN')
            .replace(/YYYY/g, String(y))
            .replace(/YY/g, String(y).slice(-2))
            .replace(/MM/g, String(d.getMonth() + 1).padStart(2, '0'))
            .replace(/DD/g, String(d.getDate()).padStart(2, '0'))
            .replace(/N+/g, (m) => String(seq).padStart(m.length, '0'));
    },
    _nextNumber(issueDate) {
        // The manifest counter is authoritative, but the company "next number"
        // raises the floor — so an owner who already issued invoices elsewhere
        // this year can resume at, say, 42.
        const floor = parseInt(this.company.next_number, 10) || 1;
        const seq = Math.max((window.LLStore.data.invoiceSeq || 0) + 1, floor);
        window.LLStore.data.invoiceSeq = seq;
        return this._formatNumber(this.company.number_format, seq, issueDate);
    },
    finalize(inv) {
        const i = inv || this.current;
        if (! i) return;
        if (! i.number) i.number = this._nextNumber(i.issueDate);
        if (i.status === 'draft') i.status = 'sent';
        i.totals = this.computeTotals(i); // freeze
        this.saveSoon();
    },
    markPaid(inv) { inv.status = 'paid'; this.saveSoon(); },
    markSent(inv) { if (! inv.number) inv.number = this._nextNumber(inv.issueDate); inv.status = 'sent'; this.saveSoon(); },
    statusLabel(s) { return ({ draft: labels.statusDraft, sent: labels.statusSent, paid: labels.statusPaid })[s] || s; },

    // ---- Print / PDF (client-side, zero-knowledge) ----
    printInvoice(inv) {
        this._printing = inv || this.current;
        this.$nextTick(() => { window.print(); });
    },
}));

Alpine.data('todos', (labels = {}) => ({
    ...zkModule({ map: { todos: 'tasks', todoLists: 'lists' } }),
    lists: [],
    tasks: [],
    view: 'all', // all | marked | trash | a list id
    newListName: '',
    editorOpen: false,
    editing: null,

    async init() { await this._initZk(); },

    listName(id) { return (this.lists.find((l) => l.id === id) || {}).name || ''; },

    addList() {
        const name = this.newListName.trim();
        if (! name) return;
        this.lists.push({ id: window.LLStore.newId(), name });
        this.lists.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
        this.newListName = '';
        this._save();
    },
    renameList(l) {
        const name = (prompt(labels.renameList, l.name) || '').trim();
        if (! name || name === l.name) return;
        l.name = name;
        this.lists.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
        this._save();
    },
    async deleteList(l) {
        if (! await this.$store.confirm.ask(labels.deleteListConfirm)) return;
        for (const t of this.tasks) if (t.listId === l.id) t.listId = null;
        const i = this.lists.findIndex((x) => x.id === l.id);
        if (i >= 0) this.lists.splice(i, 1);
        if (this.view === l.id) this.view = 'all';
        this._save();
    },

    get allTags() { return this._tagsOf(this.tasks); },
    get trashCount() { return this._trashCount(this.tasks); },

    get filteredTasks() {
        const q = this.query.trim().toLowerCase();
        let list = this.tasks.filter((t) => this.view === 'trash' ? t.trashed : ! t.trashed);
        if (this.view === 'marked') list = list.filter((t) => t.marked);
        else if (this.view !== 'all' && this.view !== 'trash') list = list.filter((t) => t.listId === this.view);
        if (this.activeTag !== '') list = list.filter((t) => (t.tags ?? []).includes(this.activeTag));
        if (q !== '') {
            list = list.filter((t) => (t.title ?? '').toLowerCase().includes(q)
                || (t.description ?? '').toLowerCase().includes(q)
                || (t.tags ?? []).some((g) => g.toLowerCase().includes(q)));
        }
        const prio = { high: 0, normal: 1, low: 2 };
        return [...list].sort((a, b) =>
            (Number(a.done) - Number(b.done))
            || (Number(b.marked) - Number(a.marked))
            || ((prio[a.priority] ?? 1) - (prio[b.priority] ?? 1))
            || ((a.due ?? '￿').localeCompare(b.due ?? '￿')));
    },

    newTask() {
        const listId = (this.view !== 'all' && this.view !== 'marked' && this.view !== 'trash') ? this.view : null;
        this.editing = { id: null, listId, title: '', description: '', url: '', priority: 'normal', marked: false, tags: [], due: '', done: false };
        this.tagsValue = '';
        this.editorOpen = true;
    },
    editTask(t) {
        this.editing = { ...t, tags: [...(t.tags ?? [])] };
        this.tagsValue = (this.editing.tags || []).join(', ');
        this.editorOpen = true;
    },
    closeEditor() { this.editorOpen = false; this.editing = null; },

    saveTask() {
        const e = this.editing;
        if (! e || ! (e.title || '').trim()) return;
        e.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        // Only http(s) for the url — a javascript:/data: URL would execute on click.
        let url = (e.url || '').trim();
        if (url && ! /^https?:\/\//i.test(url)) url = '';
        if (e.id) {
            const t = this.tasks.find((x) => x.id === e.id);
            if (t) {
                t.listId = e.listId ?? null; t.title = e.title.trim(); t.description = e.description || '';
                t.url = url; t.tags = e.tags; t.priority = e.priority; t.marked = !! e.marked; t.due = e.due || '';
            }
        } else {
            this.tasks.unshift({
                id: window.LLStore.newId(), title: e.title.trim(), description: e.description || '', url,
                tags: e.tags, priority: e.priority, marked: !! e.marked, due: e.due || '',
                done: false, listId: e.listId ?? null, trashed: false,
            });
        }
        this._save();
        this.closeEditor();
    },

    toggleDone(t) { t.done = ! t.done; this._save(); },
    toggleMark(t) { t.marked = ! t.marked; this._save(); },
    trashTask(t) { t.trashed = true; this._save(); },
    restoreTask(t) { t.trashed = false; this._save(); },
    async deleteForever(t) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm)) return;
        const i = this.tasks.findIndex((x) => x.id === t.id);
        if (i >= 0) this.tasks.splice(i, 1);
        this._save();
    },
    emptyTrash() { return this._emptyTrashArr(this.tasks, labels.emptyTrashConfirm); },

    priorityClass(p) { return p === 'high' ? 'bg-red-500' : (p === 'low' ? 'bg-gray-300' : 'bg-amber-400'); },
    dueLabel(t) { if (! t.due) return ''; try { return new Date(t.due).toLocaleString(); } catch (e) { return t.due; } },
    isOverdue(t) { return t.due && ! t.done && new Date(t.due).getTime() < Date.now(); },
}));

/**
 * Notes: zero-knowledge markdown. Each note's {title, content, tags} is sealed
 * with the per-user vault key; the server only stores/returns ciphertext. The
 * browser decrypts, renders the markdown itself (DOMPurify-sanitised) and re-seals
 * on save. No server render, search or share.
 */
Alpine.data('notes', (labels = {}) => ({
    ...zkModule({ map: { notes: 'notes' }, onLock: (self) => { self.currentId = null; } }),
    notes: [],
    currentId: null,
    view: 'active', // active | trash
    previewHtml: '',
    previewTimer: null,

    async init() { await this._initZk(); },

    get allTags() { return this._tagsOf(this.notes); },
    get trashCount() { return this._trashCount(this.notes); },
    get current() { return this.notes.find((n) => n.id === this.currentId) ?? null; },

    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.notes.filter((n) => this.view === 'trash' ? n.trashed : ! n.trashed);
        if (this.activeTag !== '') list = list.filter((n) => (n.tags ?? []).includes(this.activeTag));
        if (q !== '') {
            list = list.filter((n) => (n.title ?? '').toLowerCase().includes(q)
                || (n.content ?? '').toLowerCase().includes(q)
                || (n.tags ?? []).some((t) => t.toLowerCase().includes(q)));
        }
        return [...list].sort((a, b) => (Number(b.pinned) - Number(a.pinned)) || (b.updated ?? '').localeCompare(a.updated ?? ''));
    },

    excerpt(n) { return (n.content ?? '').replace(/[#*_`>\[\]()-]/g, '').replace(/\s+/g, ' ').trim().slice(0, 80); },

    async open(n) {
        this.currentId = n.id;
        this.tagsValue = (n.tags ?? []).join(', ');
        this.refreshPreview();
    },

    newNote() {
        const note = { id: window.LLStore.newId(), title: '', content: '', tags: [], pinned: false, trashed: false, updated: new Date().toISOString() };
        this.notes.unshift(note);
        this._save();
        this.open(note);
    },

    schedulePreview() {
        clearTimeout(this.previewTimer);
        this.previewTimer = setTimeout(() => this.refreshPreview(), 250);
    },
    // Render the current note's markdown IN THE BROWSER (server never sees it).
    // The markdown stack is lazy-loaded on first preview (kept out of the
    // initial bundle); guard against a stale render if the note changed while
    // it loaded.
    async refreshPreview() {
        if (! this.current) { this.previewHtml = ''; return; }
        const id = this.currentId;
        const md = await loadMarkdown();
        if (this.currentId === id) this.previewHtml = md.render(this.current.content || '');
    },

    save() {
        const n = this.current;
        if (! n) return;
        n.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        n.updated = new Date().toISOString();
        this._save();
    },

    togglePin(n) { n.pinned = ! n.pinned; n.updated = new Date().toISOString(); this._save(); },
    trash(n) { n.trashed = new Date().toISOString(); if (this.currentId === n.id) this.currentId = null; this._save(); },
    restore(n) { n.trashed = false; this._save(); },
    async remove(n) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm)) return;
        const i = this.notes.findIndex((x) => x.id === n.id);
        if (i >= 0) this.notes.splice(i, 1);
        if (this.currentId === n.id) this.currentId = null;
        this._save();
    },
    emptyTrash() { return this._emptyTrashArr(this.notes, labels.emptyTrashConfirm); },
}));


/**
 * Contacts. Zero-knowledge: every record lives in the opaque /store manifest
 * (shared with notes/todos) — plaintext inside the sealed blob, so CRUD just
 * edits the in-memory array and schedules a debounced sealed save. The only
 * per-record blob is the optional avatar (kept OUT of the manifest so it stays
 * small): encrypted + uploaded to the contacts blob store, referenced by
 * avatarRef/avatarKey. vCard mapping + gallery-person linking build on this.
 */
Alpine.data('contacts', contacts);

// Monochrome icon paths a bookmark folder can be given (rendered inline so the
// name can be data-driven, unlike the server-side <x-icon>).
const FOLDER_ICONS = {
    folder: 'M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z',
    star: 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z',
    heart: 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
    bookmark: 'M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z',
    briefcase: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 6.15a2.18 2.18 0 01-.75.633m0 0a48.415 48.415 0 01-15.75 0m15.75 0v2.475M6.75 6.144a48.11 48.11 0 013.413-.387m0 0V4.933c0-.99.803-1.816 1.794-1.85 1.31-.045 2.617-.045 3.926 0 .99.034 1.794.86 1.794 1.85v.808m-9.021 0h9.021',
    home: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    tag: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z',
    globe: 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-2.998 0-5.74-.784-7.843-2.247m0 0A9.003 9.003 0 013 12c0-1.605.42-3.113 1.157-4.418',
    code: 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5',
    document: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    photo: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
    film: 'M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0118 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C4.254 8.25 3.75 8.754 3.75 9.375v1.5c0 .621.504 1.125 1.125 1.125m0-3.75h13.5m-13.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m14.625-3.75c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M18 15.375v-1.5c0-.621-.504-1.125-1.125-1.125M18 15.375c0 .621-.504 1.125-1.125 1.125M18 15.375h-1.5m1.5 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-1.5-3.75h-13.5c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125h13.5',
    music: 'M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z',
    cloud: 'M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z',
    cog: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    envelope: 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
    chat: 'M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 01-.923 1.785A5.969 5.969 0 006 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337z',
    cart: 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
    card: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
    academic: 'M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
    book: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
    beaker: 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5',
    fire: 'M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z',
    gift: 'M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
    key: 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z',
    bulb: 'M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18',
    map: 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z',
    mic: 'M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z',
    brush: 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42',
    phone: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z',
    puzzle: 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401-.29-.221-.634-.349-1.003-.349-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959-.221-.29-.349-.634-.349-1.003 0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.401.604-.401.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z',
    rocket: 'M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.63 8.65m5.96 5.72a14.926 14.926 0 01-5.841 2.58m-.119-8.3a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z',
    server: 'M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z',
    shield: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
    sparkles: 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z',
    trophy: 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0',
    truck: 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
    users: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    wrench: 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z',
    banknote: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m0 0v.375c0 .621.504 1.125 1.125 1.125H5.25m-3-6h7.5M12 12h.008v.008H12V12zm0 0h.375a1.125 1.125 0 011.125 1.125V15m-3-3h-.008v.008H10.5V12zm0 3h.008v.008H10.5V15zm7.5-1.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 0v.375c0 .621.504 1.125 1.125 1.125H21.75M21.75 15V6.75',
    camera: 'M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z',
    chart: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
    clipboard: 'M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184',
    cube: 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
    flag: 'M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5',
    headphones: 'M2.25 12v3.75c0 .621.504 1.125 1.125 1.125H6a.75.75 0 00.75-.75v-5.25a.75.75 0 00-.75-.75H3.375c-.621 0-1.125.504-1.125 1.125V12zm0 0A9.75 9.75 0 0112 2.25a9.75 9.75 0 019.75 9.75m0 0v3.75c0 .621-.504 1.125-1.125 1.125H18a.75.75 0 01-.75-.75v-5.25a.75.75 0 01.75-.75h2.625c.621 0 1.125.504 1.125 1.125V12z',
    lock: 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z',
    newspaper: 'M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z',
    plane: 'M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5',
    printer: 'M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z',
    scissors: 'M7.848 8.25l1.536.887M7.848 8.25a3 3 0 11-5.196-3 3 3 0 015.196 3zm1.536.887a2.165 2.165 0 011.083 1.839c.005.351.054.695.14 1.024M9.384 9.137l6.115 3.53m0 0a3 3 0 105.196 3 3 3 0 00-5.196-3zm0 0l-6.115 3.53M9.384 9.137c-.086.328-.135.672-.14 1.023a2.165 2.165 0 01-1.083 1.84m0 0l6.115 3.53',
    sun: 'M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z',
    moon: 'M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z',
    wifi: 'M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z',
};

/**
 * Bookmarks + folders. Zero-knowledge: everything lives in the opaque manifest
 * (one sealed blob shared with notes/todos), so there is no fetch/seal per row —
 * fields are plaintext inside the sealed manifest and every mutation edits the
 * in-memory arrays in place then schedules a debounced sealed save.
 */
Alpine.data('bookmarks', (labels = {}) => ({
    ...zkModule({ map: { bookmarks: 'bookmarks', bookmarkFolders: 'folders' } }),
    folders: [],
    bookmarks: [],
    view: 'all', // all | favorites | readlater | trash | a folder id
    editorOpen: false,
    // Kept a non-null blank so the teleported editor's x-model bindings never
    // read from null before a bookmark is opened.
    editing: { id: null, folderId: null, title: '', url: '', description: '', tags: [], favorite: false, readLater: false },
    dragItem: null, // { type: 'bookmark' | 'folder', id }

    async init() { await this._initZk(); },

    host(url) { try { return new URL(url).host; } catch (e) { return ''; } },

    // ---- Folders (create / subfolder / rename + colour + icon) ----
    folderEditor: { open: false, id: null, parentId: null, name: '', color: '', icon: '' },
    moreIconsOpen: false,
    get allFolderIcons() { return Object.keys(FOLDER_ICONS); },
    openFolderCreate(parentId = null) {
        this.folderEditor = { open: true, id: null, parentId, name: '', color: '', icon: '' };
    },
    openFolderEdit(f) {
        this.folderEditor = { open: true, id: f.id, parentId: f.parentId ?? null, name: f.name || '', color: f.color || '', icon: f.icon || '' };
    },
    saveFolder() {
        const e = this.folderEditor;
        const name = (e.name || '').trim();
        if (! name) return;
        if (e.id) {
            const f = this.folders.find((x) => x.id === e.id);
            if (f) { f.name = name; f.color = e.color || ''; f.icon = e.icon || ''; }
        } else {
            this.folders.push({ id: window.LLStore.newId(), name, parentId: e.parentId ?? null, color: e.color || '', icon: e.icon || '' });
        }
        this._save();
        this.folderEditor.open = false;
    },
    addSubfolder(parent) { this.openFolderCreate(parent.id); },

    async deleteFolder(f) {
        if (! await this.$store.confirm.ask(labels.deleteFolderConfirm)) return;
        // Reparent this folder's subfolders to roots and drop the folder from its
        // bookmarks, so nothing is orphaned inside the manifest.
        for (const child of this.folders) if (child.parentId === f.id) child.parentId = null;
        for (const b of this.bookmarks) if (b.folderId === f.id) b.folderId = null;
        const i = this.folders.findIndex((x) => x.id === f.id);
        if (i >= 0) this.folders.splice(i, 1);
        if (this.view === f.id) this.view = 'all';
        this._save();
    },

    // Name of the folder a bookmark lives in (for the list badge).
    folderById(id) { return this.folders.find((f) => f.id === id) || null; },
    folderIconPath(name) { return FOLDER_ICONS[name] || FOLDER_ICONS.folder; },

    // Folders as a depth-annotated, pre-order flat list for indented rendering.
    get folderTree() {
        const byParent = {};
        for (const f of this.folders) {
            const p = f.parentId ?? null;
            (byParent[p] ??= []).push(f);
        }
        for (const k in byParent) byParent[k].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
        const out = [];
        const walk = (parent, depth) => {
            for (const f of (byParent[parent] ?? [])) { out.push({ ...f, depth }); walk(f.id, depth + 1); }
        };
        walk(null, 0);
        return out;
    },

    // ---- Drag & drop into folders ----
    onFolderDrop(folderId) {
        const d = this.dragItem;
        this.dragItem = null;
        if (! d) return;
        if (d.type === 'bookmark') this.moveBookmarkToFolder(d.id, folderId);
        else if (d.type === 'folder' && d.id !== folderId) this.moveFolderTo(d.id, folderId);
    },

    moveBookmarkToFolder(id, folderId) {
        const b = this.bookmarks.find((x) => x.id === id);
        if (b) { b.folderId = folderId; this._save(); }
    },

    moveFolderTo(id, parentId) {
        const f = this.folders.find((x) => x.id === id);
        if (f) { f.parentId = parentId; this._save(); }
    },

    get allTags() { return this._tagsOf(this.bookmarks); },
    get trashCount() { return this._trashCount(this.bookmarks); },
    get readLaterCount() { return this.bookmarks.filter((b) => ! b.trashed && b.readLater && ! b.read).length; },

    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.bookmarks.filter((b) => this.view === 'trash' ? b.trashed : ! b.trashed);
        if (this.view === 'favorites') list = list.filter((b) => b.favorite);
        else if (this.view === 'readlater') list = list.filter((b) => b.readLater && ! b.read);
        else if (this.view !== 'all' && this.view !== 'trash') list = list.filter((b) => b.folderId === this.view);
        if (this.activeTag !== '') list = list.filter((b) => (b.tags ?? []).includes(this.activeTag));
        if (q !== '') {
            list = list.filter((b) => (b.title ?? '').toLowerCase().includes(q)
                || (b.url ?? '').toLowerCase().includes(q)
                || (b.description ?? '').toLowerCase().includes(q)
                || (b.tags ?? []).some((t) => t.toLowerCase().includes(q)));
        }
        return list;
    },

    newBookmark() {
        const folderId = (this.view !== 'all' && this.view !== 'favorites' && this.view !== 'readlater' && this.view !== 'trash') ? this.view : null;
        this.editing = { id: null, folderId, title: '', url: '', description: '', tags: [], favorite: false, readLater: this.view === 'readlater' };
        this.tagsValue = '';
        this.editorOpen = true;
    },
    editBookmark(b) {
        this.editing = { ...b, tags: [...(b.tags ?? [])] };
        this.tagsValue = (this.editing.tags || []).join(', ');
        this.editorOpen = true;
    },
    closeEditor() { this.editorOpen = false; this.editing = { id: null, folderId: null, title: '', url: '', description: '', tags: [], favorite: false, readLater: false }; },

    saveBookmark() {
        const e = this.editing;
        const url = (e?.url || '').trim();
        if (! e || ! url) { this.error = labels.urlRequired; return; }
        // Only http(s): a javascript:/data: URL would execute on click via :href.
        if (! /^https?:\/\//i.test(url)) { this.error = labels.urlRequired; return; }
        this.error = '';
        // Fall back to the host as the title so a bookmark is never untitled.
        const title = (e.title || '').trim() || this.host(url) || url;
        const description = e.description || '';
        const tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        const folderId = e.folderId ?? null;
        const favorite = !! e.favorite;
        const readLater = !! e.readLater;
        if (e.id) {
            const b = this.bookmarks.find((x) => x.id === e.id);
            if (b) { b.url = url; b.title = title; b.description = description; b.tags = tags; b.folderId = folderId; b.favorite = favorite; b.readLater = readLater; }
        } else {
            this.bookmarks.unshift({ id: window.LLStore.newId(), url, title, description, tags, folderId, favorite, readLater, read: false, trashed: false });
        }
        this._save();
        this.closeEditor();
    },

    toggleReadLater(b) { b.readLater = ! b.readLater; if (! b.readLater) b.read = false; this._save(); },
    markRead(b) { b.read = true; this._save(); },
    toggleFavorite(b) { b.favorite = ! b.favorite; this._save(); },
    trash(b) { b.trashed = true; this._save(); },
    restore(b) { b.trashed = false; this._save(); },
    async remove(b) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm)) return;
        const i = this.bookmarks.findIndex((x) => x.id === b.id);
        if (i >= 0) this.bookmarks.splice(i, 1);
        this._save();
    },
    emptyTrash() { return this._emptyTrashArr(this.bookmarks, labels.emptyTrashConfirm); },
}));

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
