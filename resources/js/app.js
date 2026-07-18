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
import vaultGallery from './components/gallery';
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
