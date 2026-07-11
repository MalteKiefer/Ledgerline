import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { Vault } from './vault';

// The markdown stack (marked + DOMPurify + highlight.js + its CSS) is only ever
// needed to preview a note, so it is code-split out of the initial bundle and
// loaded on first use. Returns a memoised { render(md) } that highlights fenced
// code (client-side — notes are zero-knowledge) and DOMPurify-sanitises output.
let _markdown = null;
async function loadMarkdown() {
    if (_markdown) return _markdown;
    const [{ marked }, DOMPurify, { markedHighlight }, hljs] = await Promise.all([
        import('marked'),
        import('dompurify'),
        import('marked-highlight'),
        import('highlight.js/lib/common'),
    ]);
    await Promise.all([
        import('github-markdown-css/github-markdown-light.css'),
        import('highlight.js/styles/github.css'),
    ]);
    const hl = hljs.default;
    marked.use(markedHighlight({
        langPrefix: 'hljs language-',
        highlight(code, lang) {
            const language = lang && hl.getLanguage(lang) ? lang : 'plaintext';
            return hl.highlight(code, { language }).value;
        },
    }));
    _markdown = { render: (md) => (md ? DOMPurify.default.sanitize(marked.parse(md, { gfm: true, breaks: true })) : '') };
    return _markdown;
}

// Zero-knowledge encryption vault (client-side crypto for the Files module).
// Exposed globally so the vault UI + files component can lock/unlock/encrypt.
// The reactive Alpine.store('vault') boots it (restores the cached key) on init.
window.Vault = Vault;

// Padmé length-hiding for stored ciphertext blobs. Rounds a blob up to a Padmé
// bucket (leaks O(log log n) bits, ≤~12% overhead) so the stored/on-ledger size
// can't fingerprint the exact plaintext length. Shared by the gallery and files
// blob paths (both persist size into their *_blobs ledger + the DB dump). The
// random pad sits AFTER the self-delimiting secretstream frames, so it is never
// parsed and decryption is unaffected — no download-side stripping needed.
function padmeSize(n) {
    if (n < 2) return n;
    const e = Math.floor(Math.log2(n));
    const s = Math.floor(Math.log2(e)) + 1;
    const bits = e - s;
    if (bits <= 0) return n;
    const mask = (1 << bits) - 1;
    return (n + mask) & ~mask;
}
async function padBlob(blob) {
    let pad = padmeSize(blob.size) - blob.size;
    if (pad <= 0) return blob;
    const parts = [blob];
    while (pad > 0) {
        const chunk = new Uint8Array(Math.min(pad, 65536));
        crypto.getRandomValues(chunk);
        parts.push(chunk);
        pad -= chunk.length;
    }
    return new Blob(parts, { type: 'application/octet-stream' });
}

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
        return { v: 1, notes: [], bookmarks: [], bookmarkFolders: [], todos: [], todoLists: [], files: [], fileFolders: [] };
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
async function bootStore(store) {
    while (! store.vault.ready) { await new Promise((r) => setTimeout(r, 20)); }
    if (! store.vault.unlocked) return false;
    if (! window.LLStore.loaded) await window.LLStore.load();
    return true;
}

// Separate sealed store for the gallery index (photos/albums/people), kept apart
// from the shared workspace manifest so gallery churn never re-seals notes/todos.
// Same contract as LLStore but against /gallery/store.
window.LLGalleryStore = {
    data: null,
    version: 0,
    ready: false,
    loaded: false,
    _timer: null,
    _chain: null,
    _onError: null,

    _blank() {
        return { v: 1, photos: [], albums: [], people: [] };
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
        this.data = d.ciphertext ? window.Vault.openManifest(d.ciphertext) : this._blank();
        for (const k of Object.keys(this._blank())) if (! (k in this.data)) this.data[k] = this._blank()[k];
        this.loaded = true;
        this.ready = true;
        return this.data;
    },

    reset() { this.data = null; this.version = 0; this.ready = false; this.loaded = false; clearTimeout(this._timer); },

    touch() {
        clearTimeout(this._timer);
        this._timer = setTimeout(() => this.flush(), 800);
    },

    // Serialised, awaitable save. Callers can `await flush()` and be sure the
    // CURRENT data was persisted — a save in flight no longer turns the call into
    // a no-op (which lost destructive edits like emptying the trash on reload).
    flush() {
        if (! this.loaded) return Promise.resolve();
        this._chain = (this._chain || Promise.resolve()).then(() => this._doFlush()).catch(() => {});
        return this._chain;
    },

    async _doFlush(retry = 0) {
        if (! this.loaded || ! this.data) return;
        try {
            const body = JSON.stringify({ ciphertext: window.Vault.sealManifest(this.data), version: this.version });
            const res = await fetch('/gallery/store', { method: 'PUT', headers: jsonHeaders(), body });
            if (res.status === 409) {
                // Someone else advanced the version; adopt it and re-seal our data.
                const cur = await fetch('/gallery/store', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
                this.version = cur.version ?? this.version;
                if (retry < 3) return this._doFlush(retry + 1);
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
async function bootGalleryStore(store) {
    while (! store.vault.ready) { await new Promise((r) => setTimeout(r, 20)); }
    if (! store.vault.unlocked) return false;
    if (! window.LLGalleryStore.loaded) await window.LLGalleryStore.load();
    return true;
}

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
        async setup(passphrase) {
            const code = await window.Vault.setup(passphrase);
            this.configured = true; this._unlockedAt++;
            return code;
        },
        async unlock(passphrase) { await window.Vault.unlock(passphrase); this._unlockedAt++; },
        async recover(code) { await window.Vault.recover(code); this._unlockedAt++; },
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
let leafletModule = null;
async function loadLeaflet() {
    if (! leafletModule) {
        const L = (await import('leaflet')).default;
        await import('leaflet.markercluster'); // augments L with markerClusterGroup
        await Promise.all([
            import('leaflet/dist/leaflet.css'),
            import('leaflet.markercluster/dist/MarkerCluster.css'),
            import('leaflet.markercluster/dist/MarkerCluster.Default.css'),
        ]);
        const [icon, icon2x, shadow] = await Promise.all([
            import('leaflet/dist/images/marker-icon.png'),
            import('leaflet/dist/images/marker-icon-2x.png'),
            import('leaflet/dist/images/marker-shadow.png'),
        ]);
        // Leaflet's default marker resolves its images by a relative URL that
        // 404s under a bundler; point it at the bundled assets so pins render.
        L.Icon.Default.mergeOptions({
            iconUrl: icon.default,
            iconRetinaUrl: icon2x.default,
            shadowUrl: shadow.default,
        });
        leafletModule = L;
    }
    return leafletModule;
}

let cmModule = null;
async function loadCodeMirror() {
    if (! cmModule) {
        const [core, state, language, data] = await Promise.all([
            import('codemirror'),
            import('@codemirror/state'),
            import('@codemirror/language'),
            import('@codemirror/language-data'),
        ]);
        cmModule = {
            EditorView: core.EditorView,
            basicSetup: core.basicSetup,
            EditorState: state.EditorState,
            Compartment: state.Compartment,
            LanguageDescription: language.LanguageDescription,
            languages: data.languages,
        };
    }
    return cmModule;
}

/**
 * Live backup run list: loads recent runs as JSON, refreshes after "back up
 * now" (no page reload) and polls while any run is still running. Each finished
 * run can be expanded to its log or downloaded.
 */
Alpine.data('backupRuns', (labels = {}) => ({
    runs: [],
    expanded: {},
    pollUntil: 0, // keep polling until this timestamp (covers queue lag + run time)
    _timer: null,
    decrypt: { open: false, id: null },

    openDecrypt(id) {
        this.decrypt = { open: true, id };
    },
    get decryptAction() {
        return (labels.decryptBase || '').replace('__id__', this.decrypt.id);
    },

    init() {
        this.load();
        // A job was triggered (here or elsewhere): poll for a window so the new
        // run appears and updates even if the queue is slow to pick it up.
        window.addEventListener('backup-ran', () => {
            this.pollUntil = Date.now() + 180000; // 3 min
            this.load();
        });
        // Poll while something is running, or within a post-trigger window.
        this._timer = setInterval(() => {
            if (! document.hidden && (this.anyRunning() || Date.now() < this.pollUntil)) {
                this.load();
            }
        }, 5000);
    },

    anyRunning() {
        return this.runs.some((r) => r.status === 'running');
    },

    async load() {
        try {
            const res = await fetch(labels.runsUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            this.runs = (await res.json()).runs ?? [];
        } catch (e) { /* keep current on error */ }
    },

    toggle(id) {
        this.expanded[id] = ! this.expanded[id];
    },

    downloadUrl(id) {
        return labels.downloadBase.replace('__id__', id);
    },

    async cancel(id) {
        // Flip the flag optimistically so the button turns into "cancelling…"
        // right away; the manager stops at its next checkpoint.
        const run = this.runs.find((r) => r.id === id);
        if (run) { run.cancellable = false; run.cancelling = true; }
        try {
            await fetch(labels.cancelBase.replace('__id__', id), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            });
        } catch (e) { /* poll will reconcile */ }
        this.pollUntil = Date.now() + 60000;
        this.load();
    },
}));

/**
 * Fire a transient toast. `url` (optional) renders a link inside the toast.
 */
function toast(message, url = null) {
    window.dispatchEvent(new CustomEvent('ll-toast', { detail: { message, url } }));
}
window.llToast = toast;

/**
 * Toast hub rendered once in the layout; listens for `ll-toast` events.
 */
Alpine.data('toastHub', (labels = {}) => ({
    items: [],
    init() {
        window.addEventListener('ll-toast', (e) => this.push(e.detail));
    },
    push({ message, url }) {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, url, linkLabel: labels.link || '' });
        setTimeout(() => this.dismiss(id), 6000);
    },
    dismiss(id) {
        this.items = this.items.filter((i) => i.id !== id);
    },
}));

/**
 * Downloads center: lists the user's async exports, polls while any are still
 * building, supports multiselect delete, and streams finished zip parts.
 */
Alpine.data('downloadsPage', (labels = {}) => ({
    exports: [],
    selected: [],
    loading: true,
    _timer: null,

    init() {
        this.load();
        this._timer = setInterval(() => {
            if (! document.hidden && this.anyBuilding()) this.load();
        }, 4000);
    },

    anyBuilding() {
        return this.exports.some((e) => e.status === 'queued' || e.status === 'processing');
    },

    async load() {
        try {
            const res = await fetch(labels.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            this.exports = (await res.json()).exports ?? [];
            // Drop selections that no longer exist.
            const ids = new Set(this.exports.map((e) => e.id));
            this.selected = this.selected.filter((id) => ids.has(id));
        } catch (e) { /* keep current on error */ } finally {
            this.loading = false;
        }
    },

    statusLabel(status) { return (labels.status || {})[status] || status; },
    sourceLabel(e) {
        const src = (labels.source || {})[e.source] || e.source;
        const variant = e.variant ? (labels.variant || {})[e.variant] : '';
        return variant ? `${src} · ${variant}` : src;
    },

    metaLine(e) {
        const parts = [];
        if (e.total_size) parts.push(this.humanSize(e.total_size));
        if (e.part_count > 1) parts.push(`${e.part_count}×`);
        if (e.expires_at) {
            const when = new Date(e.expires_at).toLocaleDateString();
            parts.push((labels.expires || '__W__').replace('__W__', when));
        }
        return parts.join(' · ');
    },

    humanSize(bytes) {
        if (! bytes) return '0 B';
        const u = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${(bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0)} ${u[i]}`;
    },

    async destroy(id) {
        await this._destroy([id]);
    },

    async destroySelected() {
        if (! this.selected.length) return;
        if (! await this.$store.confirm.ask(labels.confirmDelete)) return;
        await this._destroy([...this.selected]);
        this.selected = [];
    },

    async _destroy(ids) {
        // Optimistic removal; reconcile on next load.
        this.exports = this.exports.filter((e) => ! ids.includes(e.id));
        try {
            await fetch(labels.destroyUrl, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ ids }),
            });
        } catch (e) { /* next load reconciles */ }
        this.load();
    },
}));

/**
 * QR device pairing (profile page). Starts a pairing, shows the QR, polls its
 * state, and lets the owner approve/reject the device that scanned it. The app
 * exchanges the code for a bearer once approved — no token ever touches this UI.
 */
Alpine.data('devicePairing', () => ({
    active: false, qr: '', id: null, status: '', deviceName: '', expiresAt: 0, remaining: 0, devices: [], _timer: null, _tick: null,
    init() { this.loadDevices(); },
    async loadDevices() {
        try {
            const r = await fetch('/devices', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this.devices = (await r.json()).devices || [];
        } catch (e) { /* keep current list */ }
    },
    async revokeDevice(id) {
        try {
            const r = await fetch(`/devices/${id}`, { method: 'DELETE', headers: jsonHeaders() });
            if (r.ok) this.loadDevices();
        } catch (e) { /* ignore */ }
    },
    async start() {
        this._stopTimers();
        try {
            const r = await fetch('/device-pairings', { method: 'POST', headers: jsonHeaders() });
            if (! r.ok) return;
            const d = await r.json();
            this.id = d.id; this.qr = d.qr; this.status = 'pending_scan'; this.active = true;
            this.expiresAt = Date.parse(d.expires_at) || 0;
            this._countdown();
            this._poll();
        } catch (e) { /* ignore */ }
    },
    // "Generate a new code" — start a fresh pairing (invalidates the old one).
    regenerate() { return this.start(); },
    _poll() {
        clearTimeout(this._timer);
        this._timer = setTimeout(async () => {
            // Keep polling through 'approved' until the app actually collects its
            // token (status becomes 'consumed'), so the device list refreshes live.
            if (! this.active || ['consumed', 'rejected', 'expired'].includes(this.status)) return;
            try {
                const r = await fetch(`/device-pairings/${this.id}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const d = await r.json();
                    this.status = d.status; this.deviceName = d.device_name || '';
                    if (this.status === 'consumed') this.loadDevices();
                }
            } catch (e) { /* keep polling */ }
            this._poll();
        }, 2000);
    },
    _countdown() {
        clearInterval(this._tick);
        const step = () => {
            this.remaining = Math.max(0, Math.round((this.expiresAt - Date.now()) / 1000));
            if (this.remaining <= 0 && ['pending_scan', 'pending_approval'].includes(this.status)) {
                this.status = 'expired';
                clearInterval(this._tick);
            }
        };
        step();
        this._tick = setInterval(step, 1000);
    },
    get remainingText() {
        const s = Math.max(0, this.remaining);
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    },
    approve() { return this._act('approve'); },
    reject() { return this._act('reject'); },
    async _act(what) {
        try {
            const r = await fetch(`/device-pairings/${this.id}/${what}`, { method: 'POST', headers: jsonHeaders() });
            if (r.ok) { const d = await r.json(); this.status = d.status; clearInterval(this._tick); }
        } catch (e) { /* ignore */ }
    },
    _stopTimers() { clearTimeout(this._timer); clearInterval(this._tick); },
    reset() { this._stopTimers(); this.active = false; this.qr = ''; this.id = null; this.status = ''; this.deviceName = ''; this.remaining = 0; },
}));

/**
 * Paperless settings page: connection test and on-demand cache refresh, both
 * over AJAX so the page needn't reload.
 */
Alpine.data('paperlessSettings', (config) => ({
    config,
    busy: null,
    testResult: '', testOk: false,
    syncError: '',
    counts: config.counts,

    async test() {
        this.busy = 'test'; this.testResult = ''; this.syncError = '';
        try {
            const res = await fetch(config.testUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ paperless_url: this.$refs.url.value, paperless_token: this.$refs.token.value }),
            });
            const b = await res.json();
            this.testOk = !! b.ok; this.testResult = b.detail || '';
        } catch (e) { this.testOk = false; this.testResult = config.failed; }
        this.busy = null;
    },

    async sync() {
        this.busy = 'sync'; this.syncError = ''; this.testResult = '';
        try {
            const res = await fetch(config.syncUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            });
            const b = await res.json();
            if (b.ok) { this.counts = b.counts; } else { this.syncError = b.detail || config.failed; }
        } catch (e) { this.syncError = config.failed; }
        this.busy = null;
    },
}));

/**
 * Bell menu: local in-app notifications with an unread badge, plus browser /
 * desktop notifications (Web Notifications API) while the app is open. Polls the
 * server and mirrors newly-arrived items to a desktop notification.
 */
Alpine.data('notificationBell', (labels = {}) => ({
    open: false,
    items: [],
    unread: 0,
    maxSeenId: 0,
    etag: null,
    primed: false, // skip desktop popups for the first (historical) load
    desktop: (typeof Notification !== 'undefined') ? Notification.permission : 'unsupported',

    init() {
        this.load();
        // Poll while the tab is visible and only from the "leader" tab, so many
        // open tabs don't each hammer the endpoint. A conditional request (ETag)
        // makes the unchanged case a cheap 304.
        this._timer = setInterval(() => { if (! document.hidden && this.isLeader()) this.load(); }, 30000);
        document.addEventListener('visibilitychange', () => { if (! document.hidden) this.load(); });
    },

    // One tab per browser polls: claim leadership via a short-lived localStorage
    // lease refreshed on each poll; any tab may still load on focus/open.
    isLeader() {
        try {
            const now = Date.now();
            const raw = localStorage.getItem('lln:poll-leader');
            const lease = raw ? JSON.parse(raw) : null;
            if (! this._tabId) this._tabId = String(now) + Math.round(now % 100000);
            if (! lease || lease.id === this._tabId || now - lease.at > 70000) {
                localStorage.setItem('lln:poll-leader', JSON.stringify({ id: this._tabId, at: now }));
                return true;
            }
            return false;
        } catch (e) {
            return true; // no localStorage → just poll
        }
    },

    async load() {
        try {
            const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            if (this.etag) headers['If-None-Match'] = this.etag;
            const res = await fetch('/notifications', { headers });
            if (res.status === 304) return; // nothing changed
            if (! res.ok) return;
            this.etag = res.headers.get('ETag') || this.etag;
            const data = await res.json();
            this.unread = data.unread ?? 0;
            const items = data.items ?? [];

            // Fire a desktop notification for items newer than the last seen id
            // (but not on the very first load, which would replay history).
            if (this.primed) {
                const fresh = items.filter((n) => n.id > this.maxSeenId && ! n.read);
                if (this.desktop === 'granted') {
                    fresh.slice().sort((a, b) => a.id - b.id).forEach((n) => this.popDesktop(n));
                }
                // A backup notification means a run just finished elsewhere — tell
                // the backup run list to refresh (it may not be actively polling).
                if (fresh.some((n) => n.category === 'backup')) {
                    window.dispatchEvent(new CustomEvent('backup-ran'));
                }
            }
            if (items.length) this.maxSeenId = Math.max(this.maxSeenId, ...items.map((n) => n.id));
            this.items = items;
            this.primed = true;
        } catch (e) { /* offline: keep current */ }
    },

    popDesktop(n) {
        try {
            new Notification(n.title, { body: n.body || '', tag: 'lln-' + n.id });
        } catch (e) { /* ignore */ }
    },

    async enableDesktop() {
        if (typeof Notification === 'undefined') return;
        try {
            this.desktop = await Notification.requestPermission();
        } catch (e) { /* ignore */ }
    },

    toggle() {
        this.open = ! this.open;
        if (this.open) this.load();
    },

    async markRead(n) {
        if (n.read) return;
        n.read = true;
        this.unread = Math.max(0, this.unread - 1);
        try {
            await fetch(`/notifications/${n.id}/read`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            });
        } catch (e) { /* optimistic */ }
    },

    async markAllRead() {
        this.items.forEach((n) => { n.read = true; });
        this.unread = 0;
        try {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            });
        } catch (e) { /* optimistic */ }
    },

    fmt(iso) {
        if (! iso) return '';
        const d = new Date(iso);
        const diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60) return labels.now || 'now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        return d.toLocaleDateString();
    },

    // Where clicking a notification navigates, by its category.
    hrefFor(n) {
        return ({ backup: '/settings/backup' })[n.category] ?? null;
    },

    // Mark read, then open the related section (if any).
    activate(n) {
        const href = this.hrefFor(n);
        this.markRead(n);
        if (href) window.location.href = href;
    },
}));

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
    error: '',
    busy: 0,
    uploads: [], // { name, state, progress }
    uploadBatches: 0,
    progress: { active: false, done: 0, total: 0 }, // backlog processing
    thumbs: {}, // photoId -> objectURL (decrypted, in-memory cache)
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
        });
    },

    async load() {
        this.state = 'boot';
        try {
            if (! await bootGalleryStore(this.$store)) { this.state = 'locked'; return; }
        } catch (e) { this.state = 'error'; return; }
        this.index = window.LLGalleryStore.data;
        this.state = 'ready';
        this.refreshUsage();
        // Process pending uploads (its finally pairs Live Photos); also run a pair
        // pass for the backfill case where nothing is pending but split Live Photos
        // already exist. The guard makes a double call a no-op.
        this.runBacklog().finally(() => this.pairLivePhotos());
    },

    _save() { if (this.state === 'ready') window.LLGalleryStore.touch(); },

    initDropzone() {
        let depth = 0;
        this.dragging = false;
        window.addEventListener('dragenter', (e) => { if (e.dataTransfer?.types?.includes('Files')) { depth++; this.dragging = true; } });
        window.addEventListener('dragleave', () => { depth = Math.max(0, depth - 1); if (! depth) this.dragging = false; });
        window.addEventListener('drop', () => { depth = 0; this.dragging = false; });
    },
    dragging: false,
    drop(event) {
        this.dragging = false;
        if (this.state !== 'ready') return;
        this.upload(event.dataTransfer.files);
    },

    /* ---- Derived views ---- */
    get libraryPhotos() {
        return this.index.photos
            .filter((p) => ! p.trashed)
            .sort((a, b) => new Date(b.taken_at || b.created || 0) - new Date(a.taken_at || a.created || 0));
    },
    get pendingCount() { return this.index.photos.filter((p) => ! p.trashed && ! p.thumbRef && ! p.failed).length; },
    photoCount() { return this.libraryPhotos.length; },
    trashCount() { return this.index.photos.filter((p) => p.trashed).length; },
    get trashedPhotos() {
        return this.index.photos.filter((p) => p.trashed)
            .sort((a, b) => new Date(b.trashed || 0) - new Date(a.trashed || 0));
    },
    // Library photos grouped by capture day (newest first) for the timeline.
    get groupedPhotos() {
        const groups = new Map();
        for (const p of this.libraryPhotos) {
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
        const files = [...fileList].filter((f) => /^image\/|^video\//.test(f.type) || /\.(heic|heif|avif|mov|mp4|m4v)$/i.test(f.name));
        if (! files.length) return;
        if (this.uploadBatches === 0) this.uploads = [];
        this.uploadBatches++;
        return this._track((async () => {
            // Show the whole batch immediately, then encrypt+upload a few in
            // parallel so the tray doesn't sit at 0% while one large HEIC encrypts.
            const start = this.uploads.length;
            for (const f of files) this.uploads.push({ name: f.name, state: 'pending', progress: 0 });
            let next = 0;
            // Exact-duplicate guard: skip re-uploading a byte-identical file. The
            // signature (size + hash of head/tail) is computed client-side before
            // encryption, so an identical file is never uploaded in the first place.
            const seen = new Set(this.index.photos.filter((p) => ! p.trashed && p.sig).map((p) => p.sig));
            const worker = async () => {
                while (next < files.length) {
                    const idx = next++;
                    const file = files[idx];
                    const entry = this.uploads[start + idx];
                    try {
                        const sig = await this._fileSig(file);
                        if (sig && seen.has(sig)) { entry.state = 'duplicate'; continue; }
                        if (sig) seen.add(sig);
                        entry.state = 'uploading';
                        const enc = await window.Vault.encryptFile(file);
                        const cipher = new File([await this._padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
                        const id = await this._uploadBlob(cipher, entry);
                        this.index.photos.unshift({
                            id: window.LLGalleryStore.newId(),
                            originalRef: id, originalKey: enc.encFileKey,
                            name: file.name, mime: file.type || 'application/octet-stream', size: file.size,
                            media_type: (file.type.startsWith('video/') || /\.(mov|mp4|m4v)$/i.test(file.name)) ? 'video' : 'image',
                            sig,
                            created: new Date().toISOString(),
                        });
                        entry.state = 'done'; entry.progress = 100;
                        this._save();
                    } catch (e) { entry.state = 'error'; entry.error = this._uploadErrorText(e); }
                }
            };
            await Promise.all(Array.from({ length: Math.min(2, files.length) }, worker));
            this.uploadBatches--;
            this.refreshUsage();
            this.runBacklog();
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
                    p.failed = true; // isolate the bad record; never blocks the rest
                }
                this.progress.done++;
                this._save();
                // Persist in batches rather than re-sealing + PUTting the whole
                // manifest after every single photo (which hammered the store-save
                // rate limit on a large import). A crash only re-processes the few
                // since the last flush — pending() already makes that idempotent.
                if (++sinceFlush >= 8) { sinceFlush = 0; await window.LLGalleryStore.flush(); }
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
        const res = await fetch(config.processUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        if (! res.ok) throw new Error('process failed');
        const d = await res.json();
        // 3. Encrypt + store the derived blobs.
        const meta = {
            exif: d.exif, place: d.place, embedding: d.embedding, phash: d.phash,
            faces: [], width: d.width, height: d.height, duration: d.duration, content_id: d.content_id,
        };
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
        p.camera = d.exif?.camera ?? null;
        p.hasFaces = meta.faces.length;
        // Face-crop blob ids on the entry too, so reconcile keeps them (they live
        // inside the meta blob otherwise, invisible to the live-set builder).
        p.faceCropRefs = meta.faces.map((f) => f.cropRef).filter(Boolean);
        // Prime the decrypted thumbnail so the grid updates live (reactive cache).
        this.thumbFor(p);
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
        try {
            const bytes = await this._decryptBlob(p.thumbRef, p.thumbKey);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this.thumbs[p.id] = url;
            return url;
        } catch (e) { return ''; }
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
    async openLocPicker(p) {
        if (! p) return;
        const startLat = p.lat ?? this.viewer.meta?.exif?.lat ?? 48.2082;
        const startLng = p.lng ?? this.viewer.meta?.exif?.lon ?? 16.3738;
        this.loc = { open: true, target: p, lat: p.lat ?? null, lng: p.lng ?? null };
        const L = await loadLeaflet();
        this.$nextTick(() => {
            const el = this.$refs.locmap;
            if (! el || ! this.loc.open) return;
            if (this._locMap) { this._locMap.remove(); this._locMap = null; }
            this._locMarker = null;
            this._locMap = L.map(el).setView([startLat, startLng], p.lat != null ? 13 : 4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this._locMap);
            if (p.lat != null) this._locMarker = L.marker([p.lat, p.lng]).addTo(this._locMap);
            this._locMap.on('click', (e) => {
                this.loc.lat = e.latlng.lat; this.loc.lng = e.latlng.lng;
                if (this._locMarker) this._locMarker.setLatLng(e.latlng);
                else this._locMarker = L.marker(e.latlng).addTo(this._locMap);
            });
            setTimeout(() => { if (this._locMap) this._locMap.invalidateSize(); }, 120);
        });
    },
    saveLoc() {
        const p = this.loc.target;
        if (p && this.loc.lat != null) {
            p.lat = this.loc.lat; p.lng = this.loc.lng;
            if (this.viewer.meta?.exif) { this.viewer.meta.exif.lat = p.lat; this.viewer.meta.exif.lon = p.lng; }
            this._renderMiniMap(p.lat, p.lng);
            this._save();
        }
        this.closeLocPicker();
    },
    clearLoc() {
        const p = this.loc.target;
        if (p) {
            p.lat = null; p.lng = null;
            if (this.viewer.meta?.exif) { this.viewer.meta.exif.lat = null; this.viewer.meta.exif.lon = null; }
            if (this._miniMap) { this._miniMap.remove(); this._miniMap = null; }
            this._save();
        }
        this.closeLocPicker();
    },
    closeLocPicker() {
        if (this._locMap) { this._locMap.remove(); this._locMap = null; }
        this._locMarker = null;
        this.loc = { open: false, target: null, lat: null, lng: null };
    },

    /* ---- Multi-select ---- */
    selected: [],
    isSelected(id) { return this.selected.includes(id); },
    toggleSelect(id) { const i = this.selected.indexOf(id); if (i >= 0) this.selected.splice(i, 1); else this.selected.push(id); },
    clearSelection() { this.selected = []; },
    get selectedCount() { return this.selected.length; },
    selectAllVisible() {
        const ids = (this.view === 'trash' ? this.trashedPhotos : this.libraryPhotos).map((p) => p.id);
        this.selected = this.selected.length === ids.length ? [] : ids;
    },
    _eachSelected(fn) { for (const id of [...this.selected]) { const p = this.index.photos.find((x) => x.id === id); if (p) fn(p); } },
    bulkTrash() { const t = new Date().toISOString(); this._eachSelected((p) => { if (! p.trashed) p.trashed = t; }); this.selected = []; this._save(); },
    bulkRestore() { this._eachSelected((p) => { p.trashed = null; }); this.selected = []; this._save(); },
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
        const metaIds = this.libraryPhotos.filter((p) => (p.name || '').toLowerCase().includes(lc) || (p.camera || '').toLowerCase().includes(lc)).map((p) => p.id);
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
            if (m && Array.isArray(m.embedding) && ! searchEmb[p.id]) searchEmb[p.id] = this._norm(m.embedding);
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
    get mapPhotos() { return this.libraryPhotos.filter((p) => p.lat != null && p.lng != null); },
    // Photos processed before GPS was promoted onto the index carry their
    // coordinates only inside the meta blob; backfill lat/lng from there once.
    async _ensureGeo() {
        if (this._geoLoaded) return;
        let changed = false;
        for (const p of this.libraryPhotos) {
            if (p.lat != null || ! p.metaRef) continue;
            try {
                const b = await this._decryptBlob(p.metaRef, p.metaKey);
                const m = JSON.parse(new TextDecoder().decode(b));
                if (m.exif && m.exif.lat != null) {
                    p.lat = m.exif.lat; p.lng = m.exif.lon;
                    if (! p.camera && m.exif.camera) p.camera = m.exif.camera;
                    changed = true;
                }
            } catch (e) { /* skip */ }
        }
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
            this._map = L.map(el).setView([20, 0], 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(this._map);
            const cluster = L.markerClusterGroup ? L.markerClusterGroup() : L.layerGroup();
            for (const p of this.mapPhotos) {
                const m = L.marker([p.lat, p.lng]);
                m.on('click', () => this.openViewer(p));
                cluster.addLayer(m);
            }
            this._map.addLayer(cluster);
            if (this.mapPhotos.length) this._map.fitBounds(L.latLngBounds(this.mapPhotos.map((p) => [p.lat, p.lng])), { padding: [40, 40], maxZoom: 14 });
        });
    },

    /* ---- Albums (plain client-side grouping, sealed in the index) ---- */
    activeAlbum: null,
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
    get people() { return (this.index.people || []).filter((pp) => ! pp.hidden && this.personPhotos(pp).length > 0); },
    get currentPerson() { return (this.index.people || []).find((pp) => pp.id === this.activePerson) || null; },
    openPerson(pp) { this.activePerson = pp.id; this.view = 'person'; },
    personPhotos(pp) {
        if (! pp) return [];
        const ids = [...new Set((pp.faces || []).map((f) => f.photoId))];
        const byId = new Map(this.libraryPhotos.map((p) => [p.id, p]));
        return ids.map((id) => byId.get(id)).filter(Boolean);
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
                        faces.push({ emb: f.embedding, meta: { photoId: p.id, idx, cropRef: f.cropRef, cropKey: f.cropKey } });
                    }
                });
            }
            // A scoped (incremental) scan seeds from the existing people so new
            // faces merge into them; a full scan starts empty and carries names
            // over by matching the previous scan's centroids.
            const seeds = incremental
                ? (this.index.people || [])
                    .filter((pp) => Array.isArray(pp.centroid) && pp.centroid.length)
                    .map((pp) => ({ id: pp.id, name: pp.name || '', hidden: ! ! pp.hidden, centroid: pp.centroid, members: (pp.faces || []).map((f) => ({ photoId: f.photoId, idx: f.idx, cropRef: f.cropRef, cropKey: f.cropKey })) }))
                : [];
            const prev = incremental ? [] : (this.index.people || []).filter((pp) => pp.centroid).map((pp) => ({ name: pp.name || '', hidden: ! ! pp.hidden, centroid: pp.centroid }));

            // Cluster off the main thread (falls back to an inline pass). New
            // clusters come back with id=null; assign store ids here.
            let built = await this._computeFaceClusters({ faces, seeds, prev, incremental });
            built = built.map((b) => (b.id ? b : { ...b, id: window.LLGalleryStore.newId() }));

            // Incremental: preserve any existing person that couldn't be seeded
            // (e.g. a legacy entry without a stored centroid) so it isn't dropped.
            if (incremental) {
                const builtIds = new Set(built.map((b) => b.id));
                for (const pp of (this.index.people || [])) {
                    if (! builtIds.has(pp.id) && (pp.faces || []).length >= 2) built.push(pp);
                }
            }
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
            worker.postMessage({ type: 'faces', ...data });
        });
    },
    async _faceClustersInline({ faces, seeds, prev, incremental }) {
        const clusters = [];
        const placed = new Set();
        for (const s of seeds) {
            clusters.push({ id: s.id, name: s.name || '', hidden: ! ! s.hidden, centroid: s.centroid.slice(), count: s.members.length, members: s.members });
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
        return clusters.filter((c) => c.members.length >= 2)
            .sort((a, b) => b.members.length - a.members.length)
            .map((c) => {
                let name = c.name || '', hidden = ! ! c.hidden;
                if (! incremental) {
                    let bestSim = 0.6, match = null;
                    for (const pp of prev) { if (! pp.centroid) continue; const s = this.cosine(c.centroid, pp.centroid); if (s > bestSim) { bestSim = s; match = pp; } }
                    if (match) { name = match.name || ''; hidden = ! ! match.hidden; }
                }
                return { id: c.id, name, hidden, centroid: c.centroid, faces: c.members };
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

    /* ---- Trash (soft-delete → recoverable) ---- */
    trash(p) { p.trashed = new Date().toISOString(); this._save(); },
    restore(p) { p.trashed = null; this._save(); },
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

// Filename-extension → category, covering the most common ~100 file types.
// Extension is more reliable than the browser-supplied MIME (often empty or
// application/octet-stream), so it is checked first.
const EXT_CATEGORY = {
    // Images
    jpg: 'IMAGE', jpeg: 'IMAGE', png: 'IMAGE', gif: 'IMAGE', webp: 'IMAGE', bmp: 'IMAGE',
    tif: 'IMAGE', tiff: 'IMAGE', ico: 'IMAGE', heic: 'IMAGE', heif: 'IMAGE', avif: 'IMAGE', jfif: 'IMAGE',
    svg: 'VECTOR', ai: 'VECTOR', eps: 'VECTOR', psd: 'IMAGE', xcf: 'IMAGE', raw: 'IMAGE', cr2: 'IMAGE', nef: 'IMAGE', dng: 'IMAGE',
    // Video
    mp4: 'VIDEO', m4v: 'VIDEO', mov: 'VIDEO', webm: 'VIDEO', mkv: 'VIDEO', avi: 'VIDEO', wmv: 'VIDEO',
    flv: 'VIDEO', mpeg: 'VIDEO', mpg: 'VIDEO', '3gp': 'VIDEO', ogv: 'VIDEO', ts: 'VIDEO',
    // Audio
    mp3: 'AUDIO', wav: 'AUDIO', flac: 'AUDIO', aac: 'AUDIO', ogg: 'AUDIO', oga: 'AUDIO', m4a: 'AUDIO',
    wma: 'AUDIO', opus: 'AUDIO', aiff: 'AUDIO', mid: 'AUDIO', midi: 'AUDIO',
    // Documents
    pdf: 'PDF',
    doc: 'DOCUMENT', docx: 'DOCUMENT', odt: 'DOCUMENT', rtf: 'DOCUMENT', pages: 'DOCUMENT', epub: 'EBOOK', mobi: 'EBOOK', azw3: 'EBOOK',
    // Spreadsheets
    xls: 'SPREADSHEET', xlsx: 'SPREADSHEET', ods: 'SPREADSHEET', csv: 'SPREADSHEET', tsv: 'SPREADSHEET', numbers: 'SPREADSHEET',
    // Presentations
    ppt: 'PRESENTATION', pptx: 'PRESENTATION', odp: 'PRESENTATION', key: 'PRESENTATION',
    // Archives
    zip: 'ARCHIVE', tar: 'ARCHIVE', gz: 'ARCHIVE', tgz: 'ARCHIVE', bz2: 'ARCHIVE', xz: 'ARCHIVE',
    '7z': 'ARCHIVE', rar: 'ARCHIVE', zst: 'ARCHIVE', lz: 'ARCHIVE', cab: 'ARCHIVE', iso: 'DISK', dmg: 'DISK',
    // Code
    js: 'CODE', mjs: 'CODE', ts: 'CODE', jsx: 'CODE', tsx: 'CODE', vue: 'CODE', php: 'CODE', py: 'CODE',
    rb: 'CODE', go: 'CODE', rs: 'CODE', java: 'CODE', kt: 'CODE', c: 'CODE', h: 'CODE', cpp: 'CODE', cc: 'CODE',
    cs: 'CODE', swift: 'CODE', sh: 'CODE', bash: 'CODE', zsh: 'CODE', ps1: 'CODE', sql: 'CODE', html: 'CODE',
    htm: 'CODE', css: 'CODE', scss: 'CODE', less: 'CODE', json: 'CODE', xml: 'CODE', yaml: 'CODE', yml: 'CODE',
    toml: 'CODE', ini: 'CODE', env: 'CODE', lua: 'CODE', pl: 'CODE', r: 'CODE', dart: 'CODE',
    // Plain text
    txt: 'TEXT', md: 'TEXT', markdown: 'TEXT', log: 'TEXT', text: 'TEXT', rst: 'TEXT',
    // Fonts
    ttf: 'FONT', otf: 'FONT', woff: 'FONT', woff2: 'FONT', eot: 'FONT',
};

function extOf(name) {
    const i = (name || '').lastIndexOf('.');
    return i > 0 ? name.slice(i + 1).toLowerCase() : '';
}

// Category from a filename + MIME. Extension wins; MIME is the fallback.
// Client-side counterpart of PHP App\Enums\FileType::fromMime() — keep the two
// category sets in sync (this one is richer: it also uses the extension).
function fileCategory(name, mime) {
    const byExt = EXT_CATEGORY[extOf(name)];
    if (byExt) return byExt;
    mime = (mime || '').toLowerCase();
    if (mime.startsWith('image/')) return mime.includes('svg') ? 'VECTOR' : 'IMAGE';
    if (mime.startsWith('video/')) return 'VIDEO';
    if (mime.startsWith('audio/')) return 'AUDIO';
    if (mime.startsWith('text/')) return 'TEXT';
    if (mime === 'application/pdf') return 'PDF';
    if (/(epub|mobipocket)/.test(mime)) return 'EBOOK';
    if (/(iso9660|diskimage|apple-disk)/.test(mime)) return 'DISK';
    if (/(zip|tar|gzip|compressed|7z|rar|zstd)/.test(mime)) return 'ARCHIVE';
    if (/(word|opendocument.text|rtf)/.test(mime)) return 'DOCUMENT';
    if (/(excel|spreadsheet|csv)/.test(mime)) return 'SPREADSHEET';
    if (/(powerpoint|presentation)/.test(mime)) return 'PRESENTATION';
    if (/(json|xml|javascript|x-sh|x-php|x-python)/.test(mime)) return 'CODE';
    if (mime.startsWith('font/')) return 'FONT';
    return 'OTHER';
}

// Small monochrome heroicon-style glyph per category, for the file list.
const CATEGORY_ICON = {
    IMAGE: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
    VECTOR: 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42',
    VIDEO: 'M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z',
    AUDIO: 'M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163V4.883a.75.75 0 00-.943-.724L9.75 6.75m0 0v9.375a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66A2.25 2.25 0 009 12.375V4.5',
    PDF: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    DOCUMENT: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    SPREADSHEET: 'M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m0 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m0 0h7.5m0-9v9m0-9c0-.621.504-1.125 1.125-1.125h7.5c.621 0 1.125.504 1.125 1.125m0 0v1.5c0 .621-.504 1.125-1.125 1.125m0 0h-7.5',
    PRESENTATION: 'M3.75 3v11.25A2.25 2.25 0 006 16.5h12a2.25 2.25 0 002.25-2.25V3m-16.5 0h16.5m-16.5 0h-1.5m18 0h1.5m-16.5 16.5l3-3.75m9 3.75l-3-3.75m-6 0h6m-6 0l-.75.938M15 16.5l.75.938',
    ARCHIVE: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
    DISK: 'M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008V8.25zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008V8.25z',
    CODE: 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5',
    TEXT: 'M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h4.5M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z',
    FONT: 'M3 8.25V6a1.5 1.5 0 011.5-1.5h15A1.5 1.5 0 0121 6v2.25M3.75 6h16.5M9 20.25h6M12 4.5v15.75',
    EBOOK: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
    OTHER: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
};

function formatBytes(n) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = Number(n) || 0;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
    const num = i === 0 ? String(Math.round(value)) : String(Math.round(value * 100) / 100);
    return `${num} ${units[i]}`;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// Shared JSON request headers + fetch wrapper for the reload-free module clients
// (notes / todos / bookmarks / files / mail). One definition so a change to the
// CSRF/accept handling or error behaviour applies everywhere.
function jsonHeaders() {
    return { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() };
}
async function apiRequest(method, url, body) {
    const res = await fetch(url, { method, headers: jsonHeaders(), body: body ? JSON.stringify(body) : undefined });
    if (! res.ok) throw new Error('request failed');
    return res.json().catch(() => ({}));
}

// Fetch an opaque content blob and decrypt it in the browser. Shared by the
// gallery and files views — identical protocol (GET {rawBase}/{ref}, then
// Vault.decryptFile with the blob's own key); the caller supplies its module's
// raw-stream base.
async function fetchDecrypt(rawBase, ref, key) {
    const res = await fetch(`${rawBase}/${ref}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (! res.ok) throw new Error('fetch failed');
    return window.Vault.decryptFile(await res.arrayBuffer(), key);
}

// Bounded, rate-limit-aware content-blob deleter shared by the gallery + files
// trash paths. Emptying a large trash frees hundreds of blobs; firing every
// DELETE at once tripped the per-route throttle (429) and the errors were
// swallowed, so the bytes were never reclaimed and usage never dropped. Funnel
// deletes through a few lanes and back off + retry on 429 (honouring
// Retry-After) so every owned blob is eventually freed. DELETE is idempotent,
// so a retried/duplicated call is harmless.
const _blobDelQueue = [];
let _blobDelActive = 0;
const BLOB_DEL_LANES = 4;
const BLOB_DEL_MAX_TRIES = 10;
function queueBlobDelete(url, token) {
    return new Promise((resolve) => {
        _blobDelQueue.push({ url, token, tries: 0, resolve });
        _pumpBlobDeletes();
    });
}
function _pumpBlobDeletes() {
    while (_blobDelActive < BLOB_DEL_LANES && _blobDelQueue.length) {
        _blobDelActive++;
        _runBlobDelete(_blobDelQueue.shift());
    }
}
async function _runBlobDelete(job) {
    try {
        const res = await fetch(job.url, { method: 'DELETE', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': job.token } });
        if (res.status === 429 && job.tries < BLOB_DEL_MAX_TRIES) {
            const ra = parseInt(res.headers.get('Retry-After') || '', 10);
            const wait = Number.isFinite(ra) && ra > 0 ? ra * 1000 : Math.min(500 * 2 ** job.tries, 15000);
            job.tries++;
            _blobDelActive--;
            setTimeout(() => { _blobDelQueue.unshift(job); _pumpBlobDeletes(); }, wait);
            return;
        }
    } catch (e) { /* network error — best effort */ }
    _blobDelActive--;
    job.resolve();
    _pumpBlobDeletes();
}

function escapeHtml(text) {
    return String(text ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function saveBlobAs(bytes, name, mime) {
    const url = URL.createObjectURL(new Blob([bytes], { type: mime || 'application/octet-stream' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = name || 'download';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10000);
}

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

Alpine.data('vaultFiles', (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | unconfigured | ready | error
    // Points at the shared opaque store's file arrays once loaded; the tree is
    // plaintext inside the sealed blob, so mutations edit these in place.
    manifest: { v: 1, folders: [], files: [] },
    version: 0,
    cwd: null,
    query: '',
    sortDir: 'asc',
    sortKey: 'name', // name | size | date
    layout: (typeof localStorage !== 'undefined' && localStorage.getItem('ll-files-layout')) || 'list', // list | grid
    renaming: null,   // item id currently renamed inline
    renameValue: '',
    moveRefs: [],     // [{kind, id}] for the move modal
    moveTarget: '',
    moveOpen: false,
    deleteRefs: [],   // [{kind, id, name}]
    deleteOpen: false,
    selected: [],     // ['kind:id', …]
    tagsRef: null,    // {kind, id} being tagged
    tagsOpen: false,
    tagsValue: '',
    activeTag: '',
    view: 'files', // files | favorites | recent | trash
    newFolderName: '',
    infoOpen: false,
    infoRow: null,
    infoNote: '',
    migrateOpen: false,
    migrateRow: null,
    migrateDelete: true,
    migrateBusy: false,
    dragItem: null, // {kind, id} being dragged into a folder
    uploads: [], // per-file upload tray: [{ name, state, progress, error }]
    uploadBatches: 0, // concurrent uploadItems() runs still in flight
    dl: { active: false, done: 0, total: 0 },
    busy: 0, // in-flight file operations (sync/save/trash/delete/move/…); drives the spinner badge
    error: '',

    // Track an async file operation so the UI can show a "working" spinner badge
    // for every mutation (the user gets feedback even for a slow permanent delete).
    _track(p) {
        this.busy++;
        return Promise.resolve(p).finally(() => { this.busy = Math.max(0, this.busy - 1); });
    },
    dragging: false,
    viewer: { open: false, kind: 'none', src: '', row: null, saving: false, saved: false },
    editorView: null,
    editorLang: '',
    langComp: null,
    languageOptions: [], // populated when the editor (CodeMirror) is loaded on first open

    async init() {
        window.addEventListener('paperless-sent', (e) => this.onPaperlessSent(e.detail));
        this.initDropzone();
        // Switching view clears any selection, so a stale pick doesn't keep the
        // bulk bar / select-all checkbox active.
        this.$watch('view', () => { this.selected = []; });
        // Zero-knowledge gate: the tree lives in the shared opaque store, which can
        // only be opened with an unlocked vault. load() waits for the vault + store.
        await this.load();
        this.$watch('$store.vault.unlocked', (on) => {
            if (on && this.state !== 'ready') this.load();
            if (! on) { this.state = 'locked'; window.LLStore.reset(); }
        });
    },

    initDropzone() {
        let depth = 0;
        window.addEventListener('dragenter', (e) => {
            if (e.dataTransfer?.types?.includes('Files')) { depth++; this.dragging = true; }
        });
        window.addEventListener('dragleave', () => { depth = Math.max(0, depth - 1); if (! depth) this.dragging = false; });
        window.addEventListener('drop', () => { depth = 0; this.dragging = false; });
    },

    async drop(event) {
        this.dragging = false;
        if (this.state !== 'ready') return;
        const items = event.dataTransfer.items;
        let files = [];

        // Prefer the entries API so dropped folders (and subfolders) are walked.
        if (items && items.length && items[0].webkitGetAsEntry) {
            const entries = [...items].map((i) => i.webkitGetAsEntry()).filter(Boolean);
            for (const entry of entries) {
                await this.walkEntry(entry, '', files);
            }
        } else {
            files = [...event.dataTransfer.files].map((f) => ({ file: f, path: f.name }));
        }
        await this.uploadItems(files);
    },

    async walkEntry(entry, prefix, out) {
        if (entry.isFile) {
            const f = await new Promise((res) => entry.file(res, () => res(null)));
            if (f) {
                out.push({ file: f, path: prefix + f.name });
            } else {
                // Surface an unreadable dropped file instead of silently dropping it.
                this.uploads.push({ name: prefix + entry.name, state: 'error', progress: 0, error: labels.saveFailed || 'read failed' });
            }
            return;
        }
        // Read ALL child entries first (a tight readEntries loop, no per-file
        // await in between): the DirectoryReader gets invalidated on large
        // folders if you pause to read files between batches, which truncated
        // big drops (~stopped after a few dozen files). Then recurse.
        const reader = entry.createReader();
        const children = [];
        for (;;) {
            const batch = await new Promise((res) => reader.readEntries(res, () => res([])));
            if (! batch.length) break;
            children.push(...batch);
        }
        for (const child of children) {
            await this.walkEntry(child, prefix + entry.name + '/', out);
        }
    },

    // Open the shared opaque store (waits for the vault) and point the in-memory
    // manifest at its file arrays. The tree is already plaintext inside the sealed
    // blob — no per-row decrypt — so the UI works on it directly and every mutation
    // edits these arrays in place, then a debounced sealed save persists the whole
    // workspace. Mutations must splice in place, never reassign the arrays, so the
    // reference into window.LLStore.data stays intact (see _spliceWhere).
    async load() {
        this.state = 'boot';
        try {
            if (! await bootStore(this.$store)) { this.state = 'locked'; return; }
        } catch (e) { this.state = 'error'; return; }
        this.manifest.folders = window.LLStore.data.fileFolders;
        this.manifest.files = window.LLStore.data.files;
        this.state = 'ready';
        this.refreshUsage();
        // Tell the server which blobs the manifest still references so it can
        // reclaim the quota held by any it no longer does (grace-gated).
        this.reconcileBlobs();
    },

    // Current storage usage (the server can only report opaque blob bytes vs quota).
    refreshUsage() {
        return this._track((async () => {
            try {
                const res = await fetch(config.usageUrl, { cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) this.usage = await res.json();
            } catch (e) { /* keep the last value */ }
        })());
    },

    // Remove matching elements from an array IN PLACE, so the shared reference
    // into window.LLStore.data (which the sealed save reads) is never detached.
    _spliceWhere(arr, pred) {
        for (let i = arr.length - 1; i >= 0; i--) if (pred(arr[i])) arr.splice(i, 1);
    },

    // Best-effort reclaim of content blobs the manifest no longer references
    // (permanent delete / version-cap overflow / edit swap). The server verifies
    // ownership; a still-referenced blob is never passed here.
    _freeBlobs(blobs) {
        const uniq = [...new Set((blobs || []).filter(Boolean))];
        if (! uniq.length) return;
        // Paced + 429-retried so emptying a large trash reclaims every blob's
        // bytes instead of tripping the rate limit and silently dropping them.
        Promise.all(uniq.map((blob) => queueBlobDelete(`${config.blobBase}/${blob}`, config.token)))
            .then(() => this.refreshUsage());
        this.refreshUsage();
    },

    // Send the manifest's full live blob set so the server frees the rest of the
    // user's quota ledger. Debounced; runs on load (self-heals quota each visit).
    _reconcileTimer: null,
    reconcileBlobs() {
        clearTimeout(this._reconcileTimer);
        this._reconcileTimer = setTimeout(() => this._reconcileNow(), 1500);
    },
    async _reconcileNow() {
        if (this.state !== 'ready') return;
        const blobs = [];
        for (const f of this.manifest.files) {
            if (f.blob) blobs.push(f.blob);
            for (const v of f.versions ?? []) if (v.blob) blobs.push(v.blob);
        }
        try {
            const res = await fetch(config.reconcileUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ blobs: [...new Set(blobs)] }),
            });
            if (res.ok) this.usage = await res.json();
        } catch (e) { /* best effort */ }
    },

    // All descendant folder ids of the given folders (inclusive of the roots).
    _folderClosure(folderIds) {
        const kill = new Set(folderIds);
        for (let grew = true; grew;) {
            grew = false;
            for (const f of this.manifest.folders) {
                if (! kill.has(f.id) && f.parent && kill.has(f.parent)) { kill.add(f.id); grew = true; }
            }
        }
        return kill;
    },

    // Keep only the newest N versions of a file; reclaim the overflow blobs.
    _trimVersions(entry) {
        const keep = config.maxVersions || 10;
        if (! entry.versions || entry.versions.length <= keep) return;
        const evicted = entry.versions.slice(keep);
        entry.versions = entry.versions.slice(0, keep);
        this._freeBlobs(evicted.map((v) => v.blob));
    },

    usage: { used: 0, quota: 0 },
    versions: { open: false, row: null, list: [], loading: false },

    // Version history lives in the file's manifest row (versions[]) — no server
    // round-trip. Each entry keeps its own wrapped key so its blob decrypts.
    openVersions(row) {
        const f = this.manifest.files.find((x) => x.id === row.id) || row;
        this.versions = {
            open: true, row, loading: false,
            list: (f.versions ?? []).map((v) => ({ ...v, created_at: v.created })),
        };
    },
    // Download a snapshot: fetch its ciphertext blob and decrypt with the
    // version's own wrapped key.
    async downloadVersion(v) {
        try {
            const res = await fetch(`${config.rawBase}/${v.blob}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) throw new Error('fetch failed');
            saveBlobAs(window.Vault.decryptFile(await res.arrayBuffer(), v.encFileKey), v.name, v.mime);
        } catch (e) { this.error = labels.downloadFailed; }
    },
    // Restore a version: snapshot the current blob as a new version, then point
    // the row at the restored blob + its wrapped key (kept decryptable), cap, save.
    async restoreVersion(v) {
        if (! await this.$store.confirm.ask(labels.restoreConfirm || '')) return;
        const row = this.manifest.files.find((f) => f.id === this.versions.row.id);
        if (! row) return;
        row.versions = row.versions ?? [];
        row.versions.unshift({ id: crypto.randomUUID(), blob: row.blob, encFileKey: row.encFileKey, size: row.size, mime: row.mime, name: row.name, created: new Date().toISOString() });
        row.blob = v.blob;
        row.size = v.size;
        if (v.mime) row.mime = v.mime;
        row.encFileKey = v.encFileKey;
        this._spliceWhere(row.versions, (x) => x.id === v.id);
        this._trimVersions(row);
        this.persist();
        this.versions.open = false;
    },

    // Persist the whole workspace: schedule a debounced, sealed save of the shared
    // opaque store (the file arrays are live references into it). LLStore coalesces
    // rapid edits and handles optimistic-concurrency + retry itself.
    persist() {
        if (this.state === 'ready') window.LLStore.touch();
        return Promise.resolve();
    },
    // Kept as thin aliases so existing call sites don't need to change: LLStore
    // already debounces, and there is no stale whole-tree PUT to cancel anymore
    // (every save seals the current shared state).
    _schedulePersist() { this.persist(); },
    _cancelPendingPersist() {},

    /* ---- Derived views ---- */

    get breadcrumb() {
        const chain = [];
        let cur = this.cwd;
        const byId = new Map(this.manifest.folders.map((f) => [f.id, f]));
        while (cur != null && byId.has(cur)) {
            chain.unshift(byId.get(cur));
            cur = byId.get(cur).parent;
        }
        return chain;
    },

    get currentFolderName() {
        return this.breadcrumb.length ? this.breadcrumb[this.breadcrumb.length - 1].name : null;
    },

    get trashView() { return this.view === 'trash'; },

    get trashCount() {
        return this.manifest.files.filter((f) => f.trashed).length;
    },

    get favCount() {
        return this.manifest.files.filter((f) => f.favorite && ! f.trashed).length;
    },

    get rows() {
        const q = this.query.trim().toLowerCase();
        const tag = this.activeTag;
        const factor = this.sortDir === 'desc' ? -1 : 1;
        const byName = (a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base', numeric: true });
        const base = this.sortKey === 'size' ? ((a, b) => (a.size || 0) - (b.size || 0))
            : this.sortKey === 'date' ? ((a, b) => new Date(a.created || 0) - new Date(b.created || 0))
                : byName;
        const cmp = (a, b) => factor * (base(a, b) || byName(a, b));
        const search = (list) => q === '' ? list : list.filter((x) => x.name.toLowerCase().includes(q));

        // Flat views (trash / favorites / recent): a tree-wide file list, not
        // folder-scoped.
        if (this.view === 'trash') {
            return search(this.manifest.files.filter((f) => f.trashed)).map((f) => ({ ...f, kind: 'file' })).sort(cmp);
        }
        if (this.view === 'favorites') {
            return search(this.manifest.files.filter((f) => f.favorite && ! f.trashed)).map((f) => ({ ...f, kind: 'file' })).sort(cmp);
        }
        if (this.view === 'recent') {
            return search(this.manifest.files.filter((f) => ! f.trashed))
                .map((f) => ({ ...f, kind: 'file' }))
                .sort((a, b) => new Date(b.created || 0) - new Date(a.created || 0)).slice(0, 100);
        }

        // A text search or an active tag filter switches from folder browsing to
        // a flat, tree-wide result set.
        const inScope = (list) => {
            let scoped = (q === '' && tag === '')
                ? list.filter((x) => (x.parent ?? x.folder ?? null) === this.cwd)
                : list;
            if (q !== '') scoped = scoped.filter((x) => x.name.toLowerCase().includes(q));
            if (tag !== '') scoped = scoped.filter((x) => (x.tags ?? []).includes(tag));
            return scoped;
        };

        const folders = inScope(this.manifest.folders.map((f) => ({ ...f, kind: 'folder' })));
        // Hide trashed files (e.g. deleted over WebDAV): data() returns them with
        // a `trashed` timestamp so sync keeps their state, but they must not show.
        const files = inScope(this.manifest.files.filter((f) => ! f.trashed).map((f) => ({ ...f, kind: 'file' })));

        return [...folders.sort(cmp), ...files.sort(cmp)];
    },

    // Every tag used anywhere in the manifest, for suggestions.
    get allTags() {
        const set = new Set();
        for (const x of [...this.manifest.folders, ...this.manifest.files]) {
            for (const t of x.tags ?? []) set.add(t);
        }
        return [...set].sort((a, b) => a.localeCompare(b));
    },

    // Rich category (uses the filename extension + MIME) for a row.
    fileCat(row) {
        return fileCategory(row?.name, row?.mime);
    },

    typeLabel(file) {
        return labels.types?.[this.fileCat(file)] ?? file?.mime ?? '';
    },

    // Small type icon path for a file row.
    fileIconPath(row) {
        return CATEGORY_ICON[this.fileCat(row)] ?? CATEGORY_ICON.OTHER;
    },

    fmtSize: formatBytes,

    fmtDate(iso) {
        return iso ? new Date(iso).toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
        }) : '';
    },

    /* ---- Information ---- */

    openInfo(row) {
        this.infoRow = row;
        this.infoNote = row.note || '';
        this.infoOpen = true;
    },

    // Save the note on the current info file. The note is plaintext inside the
    // sealed manifest — just edit the row and schedule a save.
    saveNote() {
        const row = this.infoRow;
        if (! row || row.kind !== 'file') return;
        const note = this.infoNote.trim();
        if ((row.note || '') === note) return;
        const f = this.manifest.files.find((x) => x.id === row.id);
        if (f) f.note = note;
        row.note = note;
        this.persist();
    },

    // Direct children of a folder (files + subfolders), counted client-side —
    // the count lives only in the decrypted manifest, never on the server.
    folderItemCount(row) {
        if (! row) return 0;
        const files = this.manifest.files.filter((f) => (f.folder ?? null) === row.id).length;
        const folders = this.manifest.folders.filter((f) => (f.parent ?? null) === row.id).length;
        return files + folders;
    },

    /* ---- Migrate a Markdown file into a note ---- */

    isMarkdown(row) {
        if (! row || row.kind !== 'file') return false;
        return /\.(md|markdown)$/i.test(row.name || '') || (row.mime || '').includes('markdown');
    },

    openMigrate(row) {
        this.migrateRow = row;
        this.migrateDelete = true;
        this.migrateOpen = true;
    },

    // Create a note from a title + content. Notes live in the same opaque store,
    // so this just adds a row to the shared notes manifest (no plaintext ever
    // leaves the vault) and schedules a sealed save.
    async migrateAddNote(note) {
        try {
            if (! await bootStore(this.$store)) return false;
            window.LLStore.data.notes.unshift({
                id: window.LLStore.newId(),
                title: note.title || '', content: note.content || '',
                tags: [], pinned: false, trashed: false, updated: new Date().toISOString(),
            });
            window.LLStore.touch();
            return true;
        } catch (e) {
            return false;
        }
    },

    // Decrypt a Markdown file in the browser, create a note from it (title =
    // filename without extension), then optionally delete the source file.
    async applyMigrate() {
        const row = this.migrateRow;
        const del = this.migrateDelete;
        if (! row || this.migrateBusy) return;
        // Note: both files AND notes are zero-knowledge, so the content stays
        // encrypted end to end — no vault-exit warning needed here.
        this.migrateBusy = true;
        this.error = '';
        try {
            const plain = await this.fetchPlain(row);
            const text = new TextDecoder('utf-8').decode(plain);
            const ok = await this.migrateAddNote({
                title: (row.name || '').replace(/\.(md|markdown)$/i, ''),
                content: text,
            });
            if (! ok) {
                this.error = labels.migrateFailed;
                return;
            }

            if (del) {
                const src = this.manifest.files.find((x) => x.id === row.id);
                const blobs = src ? [src.blob, ...(src.versions ?? []).map((v) => v.blob)] : [];
                this._spliceWhere(this.manifest.files, (x) => x.id === row.id);
                this.persist();
                this._freeBlobs(blobs);
            }
            this.migrateOpen = false;
        } catch (e) {
            this.error = labels.migrateFailed;
        } finally {
            this.migrateBusy = false;
        }
    },

    // Human-readable path of the folder an item lives in ("All files / A / B").
    infoFolderPath(row) {
        const root = labels.rootFolder ?? '';
        if (! row) return root;
        const parentId = row.kind === 'folder' ? (row.parent ?? null) : (row.folder ?? null);
        if (parentId == null) return root;
        const byId = new Map(this.manifest.folders.map((f) => [f.id, f]));
        const chain = [];
        let cur = parentId;
        while (cur != null && byId.has(cur)) {
            chain.unshift(byId.get(cur).name);
            cur = byId.get(cur).parent;
        }
        return [root, ...chain].join(' / ');
    },

    /* ---- Structure operations ---- */

    async mkdir(name) {
        name = (name || '').trim();
        if (! name) return;
        this.manifest.folders.push({ id: crypto.randomUUID(), name, parent: this.cwd });
        await this.persist().catch(() => this.load());
    },

    startRename(row) {
        this.renaming = row.id;
        this.renameValue = row.name;
        this.$nextTick(() => this.$refs['rename']?.focus());
    },

    async applyRename(row) {
        const name = this.renameValue.trim();
        this.renaming = null;
        if (! name || name === row.name) return;
        const list = row.kind === 'folder' ? this.manifest.folders : this.manifest.files;
        const item = list.find((x) => x.id === row.id);
        if (item) {
            item.name = name;
            await this.persist().catch(() => this.load());
        }
    },

    /* ---- Selection ---- */

    rowKey: (row) => `${row.kind}:${row.id}`,

    toggleAll(event) {
        this.selected = event.target.checked ? this.rows.map(this.rowKey) : [];
    },

    get selectionRefs() {
        return this.selected.map((key) => {
            const [kind, id] = key.split(':');
            const list = kind === 'folder' ? this.manifest.folders : this.manifest.files;
            const item = list.find((x) => x.id === id);
            return item ? { kind, id, name: item.name } : null;
        }).filter(Boolean);
    },

    // Expand a folder id to its whole subtree of folder ids.
    subtree(id) {
        const set = new Set([id]);
        let grew = true;
        while (grew) {
            grew = false;
            for (const f of this.manifest.folders) {
                if (f.parent != null && set.has(f.parent) && ! set.has(f.id)) {
                    set.add(f.id);
                    grew = true;
                }
            }
        }
        return set;
    },

    openMove(row) {
        this.moveRefs = row ? [{ kind: row.kind, id: row.id }] : this.selectionRefs;
        this.moveTarget = '';
        this.moveOpen = this.moveRefs.length > 0;
    },

    // Folders eligible as a move target (never a selected folder's own subtree).
    get moveOptions() {
        const excluded = new Set();
        for (const ref of this.moveRefs) {
            if (ref.kind === 'folder') {
                for (const id of this.subtree(ref.id)) excluded.add(id);
            }
        }
        const byId = new Map(this.manifest.folders.map((x) => [x.id, x]));
        const path = (f) => {
            const parts = [f.name];
            let cur = f.parent;
            while (cur != null && byId.has(cur)) { parts.unshift(byId.get(cur).name); cur = byId.get(cur).parent; }
            return parts.join(' / ');
        };
        return this.manifest.folders
            .filter((f) => ! excluded.has(f.id))
            .map((f) => ({ id: f.id, label: path(f) }))
            .sort((a, b) => a.label.localeCompare(b.label));
    },

    async applyMove() {
        const refs = this.moveRefs;
        this.moveOpen = false;
        this.moveRefs = [];
        if (! refs.length) return;
        const target = this.moveTarget === '' ? null : this.moveTarget;
        for (const ref of refs) {
            if (ref.kind === 'folder') {
                if (target !== null && this.subtree(ref.id).has(target)) continue; // never into own subtree
                const f = this.manifest.folders.find((x) => x.id === ref.id);
                if (f) f.parent = target;
            } else {
                const f = this.manifest.files.find((x) => x.id === ref.id);
                if (f) f.folder = target;
            }
        }
        this.selected = [];
        // If the move fails to save, re-sync from the server so the client never
        // shows items in a folder they were not actually moved to (a later delete
        // of the old folder would then look like data loss).
        await this.persist().catch(() => this.load());
    },

    /* ---- Drag & drop into folders ---- */

    // Parent folder of the current directory (null = root), for the ".." row.
    get parentFolderId() {
        const f = this.manifest.folders.find((x) => x.id === this.cwd);
        return f ? (f.parent ?? null) : null;
    },

    onDragStart(event, row) {
        this.dragItem = { kind: row.kind, id: row.id };
        event.dataTransfer.effectAllowed = 'move';
        try { event.dataTransfer.setData('text/plain', row.id); } catch (e) { /* ignore */ }
    },

    onDragEnd() {
        this.dragItem = null;
    },

    // Move the dragged item into a folder (null = root / parent via "..").
    async dropInto(targetFolderId) {
        const item = this.dragItem;
        this.dragItem = null;
        if (! item) return;
        if (item.kind === 'folder') {
            if (item.id === targetFolderId) return;
            if (targetFolderId !== null && this.subtree(item.id).has(targetFolderId)) return; // no cycle
            const f = this.manifest.folders.find((x) => x.id === item.id);
            if (f && (f.parent ?? null) !== targetFolderId) { f.parent = targetFolderId; await this.persist().catch(() => this.load()); }
        } else {
            const f = this.manifest.files.find((x) => x.id === item.id);
            if (f && (f.folder ?? null) !== targetFolderId) { f.folder = targetFolderId; await this.persist().catch(() => this.load()); }
        }
    },

    openTags(row) {
        this.tagsRef = { kind: row.kind, id: row.id };
        this.tagsValue = (row.tags ?? []).join(', ');
        this.tagsOpen = true;
    },

    async applyTags() {
        const ref = this.tagsRef;
        this.tagsOpen = false;
        this.tagsRef = null;
        if (! ref) return;
        const tags = [...new Set(this.tagsValue.split(',').map((t) => t.trim()).filter(Boolean))];
        const list = ref.kind === 'folder' ? this.manifest.folders : this.manifest.files;
        const item = list.find((x) => x.id === ref.id);
        if (item) {
            item.tags = tags;
            await this.persist().catch(() => this.load());
        }
    },

    confirmDelete(row) {
        this.deleteRefs = row ? [{ kind: row.kind, id: row.id, name: row.name }] : this.selectionRefs;
        this.deleteOpen = this.deleteRefs.length > 0;
    },

    // Delete via the modal: to the trash (soft) or permanently. A folder brings
    // its whole subtree. Everything is a manifest edit — trash is just a flag;
    // permanent removal also reclaims the content blobs (file + versions).
    applyDelete(permanent = false) {
        const refs = this.deleteRefs;
        this.deleteOpen = false;
        this.deleteRefs = [];
        if (! refs.length) return;
        const fileIds = new Set(refs.filter((r) => r.kind === 'file').map((r) => r.id));
        const folderIds = refs.filter((r) => r.kind === 'folder').map((r) => r.id);
        const killFolders = this._folderClosure(folderIds);
        // Directly-selected files + every file inside a deleted folder subtree.
        const targets = this.manifest.files.filter((f) => fileIds.has(f.id) || killFolders.has(f.folder));

        if (permanent) {
            const blobs = [];
            for (const f of targets) { blobs.push(f.blob); for (const v of f.versions ?? []) blobs.push(v.blob); }
            const kill = new Set(targets.map((f) => f.id));
            this._spliceWhere(this.manifest.files, (f) => kill.has(f.id));
            this._spliceWhere(this.manifest.folders, (f) => killFolders.has(f.id));
            this.persist();
            this._freeBlobs(blobs);
        } else {
            const stamp = new Date().toISOString();
            for (const f of targets) {
                if (! f.trashed) f.trashed = stamp;
                // The folder is gone from the tree, so detach to root — a restore
                // then lands the file at the top level instead of nowhere.
                if (killFolders.has(f.folder)) f.folder = null;
            }
            this._spliceWhere(this.manifest.folders, (f) => killFolders.has(f.id));
            this.persist();
        }
        this.selected = [];
    },

    // Move an item straight to the trash (used by drag-and-drop onto the trash).
    trashItem(ref) {
        if (ref.kind === 'folder') {
            const killFolders = this._folderClosure([ref.id]);
            const stamp = new Date().toISOString();
            for (const f of this.manifest.files) {
                if (killFolders.has(f.folder)) { if (! f.trashed) f.trashed = stamp; f.folder = null; }
            }
            this._spliceWhere(this.manifest.folders, (f) => killFolders.has(f.id));
        } else {
            const f = this.manifest.files.find((x) => x.id === ref.id);
            if (f && ! f.trashed) f.trashed = new Date().toISOString();
        }
        this.selected = [];
        this.persist();
    },

    // Restore a trashed file back into the browser (clear its flag).
    restore(row) {
        const f = this.manifest.files.find((x) => x.id === row.id);
        if (! f) return;
        f.trashed = null;
        this.persist();
    },

    // Permanently delete one trashed file + reclaim its blobs.
    async purge(row) {
        if (! await this.$store.confirm.ask(labels.purgeConfirm || '')) return;
        const f = this.manifest.files.find((x) => x.id === row.id);
        if (! f) return;
        const blobs = [f.blob, ...(f.versions ?? []).map((v) => v.blob)];
        this._spliceWhere(this.manifest.files, (x) => x.id === row.id);
        this.persist();
        this._freeBlobs(blobs);
    },

    // Permanently delete every trashed file + reclaim their blobs.
    async emptyTrash() {
        if (! this.trashCount) return;
        if (! await this.$store.confirm.ask(labels.emptyTrashConfirm || '')) return;
        const trashed = this.manifest.files.filter((f) => f.trashed);
        const blobs = [];
        for (const f of trashed) { blobs.push(f.blob); for (const v of f.versions ?? []) blobs.push(v.blob); }
        this._spliceWhere(this.manifest.files, (f) => f.trashed);
        this.persist();
        this._freeBlobs(blobs);
    },

    // Toggle a file's favourite (a plain manifest flag).
    toggleFavorite(row) {
        const f = this.manifest.files.find((x) => x.id === row.id);
        if (! f) return;
        f.favorite = ! f.favorite;
        if (row) row.favorite = f.favorite;
        this.persist();
    },

    /* ---- Content operations ---- */

    upload(fileList) {
        return this.uploadItems([...fileList].map((f) => ({ file: f, path: f.name })));
    },

    // Upload files (optionally with relative paths from a dropped folder),
    // recreating the folder chain in the manifest under the current folder.
    // Existing sibling folders are reused by name so re-drops don't duplicate.
    // OS/editor junk that should never be uploaded — macOS, iOS, Windows, Linux
    // and Android metadata, thumbnail, lock and temp files.
    isJunkUpload(name) {
        const path = name || '';
        const n = path.split('/').pop();
        // Anything inside a junk/system directory of a dropped folder.
        if (/(^|\/)(__MACOSX|\.Spotlight-V100|\.Trashes|\.fseventsd|\.TemporaryItems|\.DocumentRevisions-V100|\$RECYCLE\.BIN|System Volume Information|\.thumbnails|LOST\.DIR|\.git)(\/|$)/i.test(path)) return true;
        return (
            // macOS / iOS
            /^\.DS_Store$/i.test(n) || /^\._/.test(n) || /^\.localized$/i.test(n)
            || /^\.AppleDouble$/i.test(n) || /^\.AppleDB$/i.test(n) || /^\.AppleDesktop$/i.test(n)
            || /^\.apdisk$/i.test(n) || /^Icon\r?$/.test(n)
            // Windows
            || /^Thumbs\.db$/i.test(n) || /^ehthumbs\.db$/i.test(n) || /^ehthumbs_vista\.db$/i.test(n)
            || /^desktop\.ini$/i.test(n) || /^\$RECYCLE\.BIN$/i.test(n) || /^~\$/.test(n) || /\.stackdump$/i.test(n)
            // Linux
            || /^\.directory$/i.test(n) || /^\.Trash-/i.test(n) || /^\.nfs[0-9a-f]+$/i.test(n)
            || /^\.fuse_hidden/i.test(n) || /^\.~lock\./i.test(n)
            // Android
            || /^\.nomedia$/i.test(n) || /^\.pending-/i.test(n) || /^\.trashed-/i.test(n)
            // Generic editor/browser temp + partial downloads
            || /\.(tmp|temp|swp|swo|swn|crdownload|part|partial|bak|old)$/i.test(n)
            || /~$/.test(n) || /^\.#/.test(n)
        );
    },

    async uploadItems(items) {
        // Drop OS/editor junk (e.g. .DS_Store, ._*, Thumbs.db) silently.
        items = (items || []).filter((it) => ! this.isJunkUpload(it.file?.name || it.path));
        if (! items.length) return;
        // A fresh batch when nothing is in flight clears the finished tray.
        if (this.uploadBatches === 0) this.uploads = [];
        this.uploadBatches++;

        const dirCache = new Map(); // relative dir path -> folder id
        dirCache.set('', this.cwd);
        const folderFor = (path) => {
            const parts = path.split('/');
            parts.pop(); // drop the filename
            let acc = '';
            let parent = this.cwd;
            for (const seg of parts) {
                acc = acc ? `${acc}/${seg}` : seg;
                if (dirCache.has(acc)) {
                    parent = dirCache.get(acc);
                    continue;
                }
                const existing = this.manifest.folders.find((f) => (f.parent ?? null) === parent && f.name === seg);
                const id = existing ? existing.id : crypto.randomUUID();
                if (! existing) {
                    this.manifest.folders.push({ id, name: seg, parent });
                }
                dirCache.set(acc, id);
                parent = id;
            }
            return parent;
        };

        // Show the whole batch immediately, then upload a few at a time. Concurrent
        // in-flight XHRs keep transferring even when the tab is backgrounded/frozen
        // (the browser freezes JS, not requests already in flight), whereas a
        // sequential loop would stall between files until the tab is focused again.
        const start = this.uploads.length;
        for (const item of items) this.uploads.push({ name: item.file.name, state: 'pending', progress: 0, error: '' });

        let next = 0;
        const worker = async () => {
            while (next < items.length) {
                const idx = next++;
                const item = items[idx];
                const entry = this.uploads[start + idx]; // reactive element
                try {
                    // Zero-knowledge: encrypt in the browser, upload only ciphertext.
                    // Large files stream — encrypted + uploaded part-by-part so neither
                    // the whole file nor the whole ciphertext is ever held in memory
                    // (constant-memory upload of any size). Small files take the simple
                    // whole-in-memory path.
                    let id, encFileKey;
                    if (item.file.size > 64 * 1024 * 1024) {
                        ({ id, encFileKey } = await this._uploadStreamEncrypted(item.file, entry));
                    } else {
                        const enc = await window.Vault.encryptFile(item.file);
                        // Neutral filename — the real name is sealed inside encMeta and
                        // never sent to the server. Padmé-pad the ciphertext so the
                        // stored blob size (recorded in file_blobs.size, and thus in the
                        // DB dump) is length-hidden and can't fingerprint the plaintext —
                        // the same treatment the gallery blob path already applies. The
                        // trailing pad sits after the self-delimiting secretstream frames,
                        // so decryption ignores it (no download-side stripping needed).
                        const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
                        id = await this._uploadOne(cipher, entry);
                        encFileKey = enc.encFileKey;
                    }
                    entry.state = 'done';
                    entry.progress = 100;
                    this.manifest.files.push({
                        id: crypto.randomUUID(),
                        blob: id,
                        encFileKey,
                        name: item.file.name,
                        mime: item.file.type || 'application/octet-stream',
                        size: item.file.size,
                        folder: folderFor(item.path),
                        created: new Date().toISOString(),
                        versions: [],
                    });
                    // Persist incrementally (debounced) so an interrupted bulk
                    // upload doesn't strand every uploaded blob without a row —
                    // the manifest is saved every ~2s instead of only at the end.
                    this._schedulePersist();
                } catch (e) {
                    entry.state = 'error';
                    entry.error = e && e.quota ? (labels.quotaExceeded || labels.uploadFailed)
                        : (e && e.unreadable ? (labels.uploadUnreadable || labels.uploadFailed) : labels.uploadFailed);
                }
            }
        };
        // Fewer parallel lanes when the batch has large files: each in-flight
        // large upload holds a rolling part buffer, so serialising them keeps
        // peak memory bounded.
        const hasLarge = items.some((i) => i.file.size > 64 * 1024 * 1024);
        const lanes = Math.min(hasLarge ? 2 : 4, items.length);
        await Promise.all(Array.from({ length: lanes }, worker));

        this.uploadBatches--;
        this.persist();
        this.refreshUsage();
        // Auto-dismiss the tray a few seconds after a clean finish (keep it open
        // when something errored so the user sees which file failed).
        if (this.uploadBatches === 0 && ! this.uploads.some((u) => u.state === 'error')) {
            setTimeout(() => { if (! this.uploading) this.uploads = []; }, 4000);
        }
    },

    // XHR upload of a single file, reporting byte progress into the tray entry.
    _uploadOne(file, entry) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', config.token);
            data.append('file', file, file.name);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', config.uploadUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            entry.state = 'uploading';
            xhr.timeout = 300000; // never let one request wedge the whole batch
            xhr.upload.onprogress = (ev) => {
                if (ev.lengthComputable) entry.progress = Math.round((ev.loaded / ev.total) * 100);
            };
            xhr.onload = () => {
                if (xhr.status === 413) { const err = new Error('quota'); err.quota = true; reject(err); return; }
                if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('upload failed')); return; }
                try { resolve(JSON.parse(xhr.responseText).id); } catch (e) { reject(e); }
            };
            xhr.onerror = () => { const e = new Error('network'); e.unreadable = true; reject(e); };
            xhr.ontimeout = () => reject(new Error('timeout'));
            xhr.onabort = () => reject(new Error('abort'));
            try { xhr.send(data); } catch (e) { const err = new Error('read'); err.unreadable = true; reject(err); }
        });
    },

    // (Removed the old plaintext chunked upload — it streamed raw bytes + the
    // real filename. Large files go through _uploadStreamEncrypted, which uploads
    // only ciphertext with a neutral name.)

    // Constant-memory encrypted upload: encrypt the file 4 MiB at a time and
    // stream the ciphertext straight into S3 multipart parts, so neither the
    // whole file nor the whole ciphertext is ever buffered. Returns the stored
    // blob id + the wrapped per-file key. Handles any size.
    async _uploadStreamEncrypted(file, entry) {
        entry.state = 'uploading';
        const enc = window.Vault.newContentEncryptor();
        const cipherSize = window.Vault.ciphertextSize(file.size);
        const init = await fetch(config.chunkInitUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
            body: JSON.stringify({ name: 'blob.enc', size: cipherSize }),
        });
        if (init.status === 413) { const e = new Error('quota'); e.quota = true; throw e; }
        if (! init.ok) throw new Error('init failed');
        const { token, id, partSize } = await init.json();

        // Rolling ciphertext buffer (array of Uint8Array frames); flushed as an
        // S3 part whenever it reaches partSize. Peak memory ≈ partSize + 4 MiB.
        const buf = [];
        let bufLen = 0;
        let partNum = 0;
        let sent = 0;
        const parts = [];
        const pull = (n) => {
            const out = new Uint8Array(n);
            let filled = 0;
            while (filled < n) {
                const head = buf[0];
                const need = n - filled;
                if (head.length <= need) { out.set(head, filled); filled += head.length; buf.shift(); }
                else { out.set(head.subarray(0, need), filled); buf[0] = head.subarray(need); filled += need; }
            }
            bufLen -= n;
            return out;
        };
        const flush = async (bytes) => {
            partNum++;
            const etag = await this._uploadPart(token, partNum, new Blob([bytes]), entry, sent, cipherSize);
            parts.push({ part: partNum, etag });
            sent += bytes.length;
        };
        const feed = async (frame) => {
            buf.push(frame); bufLen += frame.length;
            while (bufLen >= partSize) { await flush(pull(partSize)); }
        };

        try {
            await feed(enc.header);
            const CH = enc.chunkSize;
            const total = file.size;
            for (let off = 0; off < total || off === 0;) {
                const end = Math.min(off + CH, total);
                const last = end >= total;
                const slice = new Uint8Array(await file.slice(off, end).arrayBuffer());
                await feed(enc.encryptChunk(slice, last));
                off = end;
                if (last) { break; }
            }
            if (bufLen > 0) { await flush(pull(bufLen)); } // final part (any size)

            const comp = await fetch(config.chunkCompleteUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ token, parts }),
            });
            if (! comp.ok) throw new Error('complete failed');
            entry.progress = 100;
            return { id: (await comp.json()).id, encFileKey: enc.sealKey() };
        } catch (e) {
            fetch(config.chunkAbortUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ token }),
            }).catch(() => {});
            throw e;
        }
    },

    // Upload one part via XHR, reporting overall byte progress into the tray.
    _uploadPart(token, part, blob, entry, offsetStart, totalSize) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', config.token);
            data.append('token', token);
            data.append('part', part);
            data.append('chunk', blob, 'chunk');
            const xhr = new XMLHttpRequest();
            xhr.open('POST', config.chunkPartUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 600000;
            xhr.upload.onprogress = (ev) => {
                if (ev.lengthComputable) entry.progress = Math.round(((offsetStart + ev.loaded) / totalSize) * 100);
            };
            xhr.onload = () => {
                if (xhr.status === 413) { const err = new Error('quota'); err.quota = true; reject(err); return; }
                if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('part failed')); return; }
                try { resolve(JSON.parse(xhr.responseText).etag); } catch (e) { reject(e); }
            };
            xhr.onerror = () => { const e = new Error('network'); e.unreadable = true; reject(e); };
            xhr.ontimeout = () => reject(new Error('timeout'));
            try { xhr.send(data); } catch (e) { const err = new Error('read'); err.unreadable = true; reject(err); }
        });
    },

    get uploading() {
        return this.uploads.some((u) => u.state === 'pending' || u.state === 'uploading');
    },
    get uploadsDone() {
        return this.uploads.filter((u) => u.state === 'done' || u.state === 'error').length;
    },
    dismissUploads() {
        this.uploads = [];
    },

    // Fetch a file's ciphertext and decrypt it in the browser back to plaintext
    // bytes. Central to download + preview, so decrypting here makes every
    // consumer zero-knowledge with no per-caller change.
    fetchPlain(row) {
        return fetchDecrypt(config.rawBase, row.blob, row.encFileKey);
    },

    async download(row) {
        // Large files: stream-decrypt straight to disk (constant memory) when the
        // browser can write incrementally; otherwise fall back to whole-in-memory.
        if (window.showSaveFilePicker && (row.size || 0) > 64 * 1024 * 1024) {
            try { await this._downloadStreaming(row); return; }
            catch (e) { if (e && e.name === 'AbortError') return; /* else fall back */ }
        }
        this.dl = { active: true, done: 0, total: 1 };
        try {
            saveBlobAs(await this.fetchPlain(row), row.name, row.mime);
        } catch (e) {
            this.error = labels.downloadFailed;
        }
        this.dl.active = false;
    },

    // Constant-memory download: decrypt the framed ciphertext incrementally and
    // write each plaintext chunk to a user-chosen file, so a multi-GB download
    // never buffers in RAM. Uses the File System Access API.
    async _downloadStreaming(row) {
        const handle = await window.showSaveFilePicker({ suggestedName: row.name || 'download' });
        const writable = await handle.createWritable();
        this.dl = { active: true, done: 0, total: row.size || 1 };
        try {
            const dec = window.Vault.beginDecrypt(row.encFileKey);
            const res = await fetch(`${config.rawBase}/${row.blob}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) throw new Error('fetch failed');
            const reader = res.body.getReader();

            let buf = new Uint8Array(0);
            let started = false;
            let msgLen = -1; // -1 = expecting the 4-byte length prefix
            let done = false;
            const readU32 = (b) => b[0] | (b[1] << 8) | (b[2] << 16) | (b[3] << 24);

            for (;;) {
                const { value, done: streamDone } = await reader.read();
                if (value && value.length) {
                    const merged = new Uint8Array(buf.length + value.length);
                    merged.set(buf); merged.set(value, buf.length); buf = merged;
                }
                // Parse as many complete frames as the buffer currently holds.
                for (;;) {
                    if (! started) {
                        if (buf.length < dec.headerLen) { break; }
                        dec.start(buf.subarray(0, dec.headerLen));
                        buf = buf.subarray(dec.headerLen); started = true;
                        continue;
                    }
                    if (msgLen < 0) {
                        if (buf.length < 4) { break; }
                        msgLen = readU32(buf); buf = buf.subarray(4);
                        continue;
                    }
                    if (buf.length < msgLen) { break; }
                    const { message, final } = dec.pull(buf.subarray(0, msgLen));
                    buf = buf.subarray(msgLen); msgLen = -1;
                    if (message.length) { await writable.write(message); this.dl.done += message.length; }
                    if (final) { done = true; break; }
                }
                if (done) { break; }
                if (streamDone) { break; }
            }
            await writable.close();
        } catch (e) {
            try { await writable.abort(); } catch (_) { /* ignore */ }
            this.error = labels.downloadFailed;
        }
        this.dl.active = false;
    },

    isPdf(row) {
        return row?.kind === 'file' && (row.mime === 'application/pdf' || /\.pdf$/i.test(row.name || ''));
    },

    isZip(row) {
        return row?.kind === 'file' && (row.mime === 'application/zip'
            || /\.(zip|tar|tgz|tbz2)$/i.test(row.name || '') || /\.tar\.(gz|bz2)$/i.test(row.name || ''));
    },

    isImage(row) {
        return row?.kind === 'file' && (row.mime || '').startsWith('image/');
    },

    setLayout(l) {
        this.layout = l;
        try { localStorage.setItem('ll-files-layout', l); } catch (e) { /* ignore */ }
    },

    // Sort from a column header: same column flips direction, a new one starts
    // ascending.
    sortBy(key) {
        if (this.sortKey === key) { this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'; } else { this.sortKey = key; this.sortDir = 'asc'; }
    },
    sortArrow(key) {
        return this.sortKey === key ? (this.sortDir === 'asc' ? '↑' : '↓') : '';
    },

    // Open the Paperless modal immediately, then decrypt the PDF in the
    // background so the dialog never blocks. allowDelete lets the modal offer
    // to remove the stored file after upload.
    async openPaperless(row) {
        // Sending to Paperless takes the file OUT of the zero-knowledge vault: it
        // is decrypted in the browser and uploaded as plaintext to Paperless.
        // Warn before it leaves the encrypted store.
        if (! await this.$store.confirm.ask(labels.paperlessDecryptWarn || '')) return;
        const store = Alpine.store('paperless');
        store.begin(row.name, {}, {
            allowDelete: true,
            context: { source: 'files', rowId: row.id, blob: row.blob },
        });
        try {
            const plain = await this.fetchPlain(row);
            store.setFile(new Blob([plain], { type: 'application/pdf' }));
        } catch (e) {
            store.fail(labels.downloadFailed);
        }
    },

    // After a file-browser upload the user may choose to delete the original;
    // remove it from the manifest and drop its blob (best effort).
    onPaperlessSent(detail) {
        const ctx = detail?.context;
        if (! detail?.deleteAfter || ctx?.source !== 'files') return;
        const row = this.manifest.files.find((x) => x.id === ctx.rowId);
        if (! row) return;
        // If the deleted file is the one open in the viewer, close it.
        if (this.viewer.open && this.viewer.row?.id === row.id) this.closeViewer();
        const blobs = [row.blob, ...(row.versions ?? []).map((v) => v.blob)];
        this._spliceWhere(this.manifest.files, (x) => x.id === ctx.rowId);
        this.persist();
        this._freeBlobs(blobs);
    },

    /* ---- Preview & editor (all in the browser, nothing readable leaves it) ---- */

    async openFile(row) {
        this.dl = { active: true, done: 0, total: 1 };
        try {
            const plain = await this.fetchPlain(row);
            this.dl.active = false;
            const mime = row.mime || 'application/octet-stream';

            // SVG is the one "image" type that can carry markup/external refs;
            // never render it inline — let it fall through to download.
            if (mime.startsWith('image/') && ! mime.includes('svg')) {
                this.viewer = { open: true, kind: 'image', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            if (mime === 'application/pdf') {
                this.viewer = { open: true, kind: 'pdf', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            if (mime.startsWith('video/')) {
                this.viewer = { open: true, kind: 'video', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            if (mime.startsWith('audio/')) {
                this.viewer = { open: true, kind: 'audio', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            // Editable text: valid UTF-8 and reasonably small.
            if (row.size <= 2 * 1024 * 1024) {
                try {
                    const text = new TextDecoder('utf-8', { fatal: true }).decode(plain);
                    this.viewer = { open: true, kind: 'text', src: '', row, saving: false, saved: false };
                    this.$nextTick(() => this.mountEditor(text, row.name));
                    return;
                } catch (e) { /* binary: fall through */ }
            }
            this.viewer = { open: true, kind: 'none', src: '', row, saving: false, saved: false };
        } catch (e) {
            this.dl.active = false;
            this.error = labels.downloadFailed;
        }
    },

    // Images in the current view, in display order — the slideshow set.
    get viewerImages() {
        return this.rows.filter((r) => r.kind === 'file' && (r.mime || '').startsWith('image/'));
    },

    // Position of the open image within that set (-1 if not an image view).
    get viewerIndex() {
        if (this.viewer.kind !== 'image' || ! this.viewer.row) return -1;
        const key = this.rowKey(this.viewer.row);
        return this.viewerImages.findIndex((r) => this.rowKey(r) === key);
    },

    // More than one image ⇒ offer prev/next navigation.
    get viewerHasGallery() {
        return this.viewer.kind === 'image' && this.viewerImages.length > 1;
    },

    // Step to another image, wrapping around so every image is reachable.
    viewerStep(dir) {
        const imgs = this.viewerImages;
        if (imgs.length < 2) return;
        const i = this.viewerIndex;
        if (i < 0) return;
        const next = imgs[(i + dir + imgs.length) % imgs.length];
        if (this.viewer.src) URL.revokeObjectURL(this.viewer.src);
        this.openFile(next);
    },

    async mountEditor(text, filename) {
        const { EditorView, EditorState, Compartment, LanguageDescription, languages, basicSetup } = await loadCodeMirror();
        if (! this.languageOptions.length) {
            this.languageOptions = languages.map((l) => l.name).sort((a, b) => a.localeCompare(b));
        }
        this.langComp = new Compartment();
        this.editorView = new EditorView({
            parent: this.$refs.viewerEditor,
            state: EditorState.create({
                doc: text,
                extensions: [
                    basicSetup,
                    this.langComp.of([]),
                    EditorView.theme({ '&': { height: '60vh' }, '.cm-scroller': { overflow: 'auto' } }),
                ],
            }),
        });
        const detected = filename ? LanguageDescription.matchFilename(languages, filename) : null;
        if (detected) {
            this.applyEditorLanguage(detected);
        }
    },

    onEditorLanguageChange() {
        const desc = cmModule?.languages.find((l) => l.name === this.editorLang);
        desc ? this.applyEditorLanguage(desc) : this.editorView.dispatch({ effects: this.langComp.reconfigure([]) });
    },

    applyEditorLanguage(desc) {
        this.editorLang = desc.name;
        desc.load().then((support) => this.editorView.dispatch({ effects: this.langComp.reconfigure(support) }));
    },

    // Save the edited text: upload a NEW file blob, point the row at it, then
    // discard the old blob — an atomic swap from the manifest's viewpoint.
    async saveText() {
        const row = this.viewer.row;
        if (! this.editorView || ! row) return;
        this.viewer.saving = true;
        this.viewer.saved = false;
        try {
            const text = this.editorView.state.doc.toString();
            const bytes = new TextEncoder().encode(text);

            // Zero-knowledge: encrypt the edited bytes with a FRESH per-file key
            // (exactly like upload) and upload only the ciphertext. Uploading raw
            // bytes here would leak plaintext to the server AND leave the manifest
            // row's old wrapped key stale, making the file undecryptable.
            const enc = window.Vault.encryptContent(bytes, { name: row.name, mime: row.mime || 'text/plain' });
            const data = new FormData();
            data.append('_token', config.token);
            data.append('file', new File([enc.blob], 'blob.enc', { type: 'application/octet-stream' }));
            const res = await fetch(config.uploadUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            if (! res.ok) throw new Error('upload failed');
            const { id } = await res.json();

            const entry = this.manifest.files.find((f) => f.id === row.id);
            if (entry) {
                // Snapshot the outgoing blob as a version (kept + decryptable via
                // its own wrapped key) before pointing the row at the new blob,
                // then cap the history — the overflow blobs are reclaimed.
                const oldBlob = entry.blob;
                if (oldBlob && oldBlob !== id) {
                    entry.versions = entry.versions ?? [];
                    entry.versions.unshift({ id: crypto.randomUUID(), blob: oldBlob, encFileKey: entry.encFileKey, size: entry.size, mime: entry.mime, name: entry.name, created: new Date().toISOString() });
                    this._trimVersions(entry);
                }
                entry.blob = id;
                entry.size = bytes.length;
                entry.encFileKey = enc.encFileKey; // the new blob's wrapped key
            }
            this.persist();

            row.blob = id;
            row.size = bytes.length;
            row.encFileKey = enc.encFileKey;
            this.viewer.saved = true;
            this.refreshUsage();
        } catch (e) {
            this.error = labels.saveFailed;
        }
        this.viewer.saving = false;
    },

    closeViewer() {
        if (this.viewer.src) {
            URL.revokeObjectURL(this.viewer.src);
        }
        if (this.editorView) {
            this.editorView.destroy();
            this.editorView = null;
        }
        this.viewer = { open: false, kind: 'none', src: '', row: null, saving: false, saved: false };
    },
}));


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
function zkModule(cfg) {
    return {
        state: 'boot',
        query: '',
        activeTag: '',
        error: '',
        tagsValue: '',

        // Persist the manifest (debounced, sealed) after a mutation.
        _save() { window.LLStore.touch(); },

        // Point the mapped component properties at the (already-decrypted) store
        // arrays; false while the vault is still locked.
        async _bootAssign() {
            if (! await bootStore(this.$store)) { this.state = 'locked'; return false; }
            for (const [key, prop] of Object.entries(cfg.map)) this[prop] = window.LLStore.data[key];
            this.state = 'ready';
            return true;
        },

        async _initZk() {
            await this._bootAssign();
            this.$watch('$store.vault.unlocked', async (on) => {
                if (on && this.state !== 'ready') await this._bootAssign();
                if (! on) {
                    this.state = 'locked';
                    for (const prop of Object.values(cfg.map)) this[prop] = [];
                    if (cfg.onLock) cfg.onLock(this);
                    window.LLStore.reset();
                }
            });
        },

        // Sorted union of every tag on the rows of a collection (for suggestions).
        _tagsOf(list) {
            const set = new Set();
            for (const x of list) for (const t of x.tags ?? []) set.add(t);
            return [...set].sort((a, b) => a.localeCompare(b));
        },
        _trashCount(list) { return list.filter((x) => x.trashed).length; },

        // Permanently drop every trashed row of a collection (in place).
        async _emptyTrashArr(list, confirmMsg) {
            if (! await this.$store.confirm.ask(confirmMsg)) return;
            for (let i = list.length - 1; i >= 0; i--) if (list[i].trashed) list.splice(i, 1);
            this._save();
        },
    };
}

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
