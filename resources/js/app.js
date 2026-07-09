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

// App-wide confirm modal store (replaces native window.confirm everywhere).
// Usage in Alpine components: `if (! await this.$store.confirm.ask(msg)) return;`
document.addEventListener('alpine:init', () => {
    Alpine.store('confirm', {
        open: false, message: '', _resolve: null,
        ask(message) {
            this.message = message || '';
            this.open = true;
            return new Promise((resolve) => { this._resolve = resolve; });
        },
        yes() { this.open = false; const r = this._resolve; this._resolve = null; if (r) r(true); },
        no() { this.open = false; const r = this._resolve; this._resolve = null; if (r) r(false); },
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
 * Spotlight-style global search palette. Opens a centred modal, searches live
 * as the user types (debounced), and supports keyboard navigation. Results come
 * from the JSON suggest endpoint; the URLs are app routes.
 */
Alpine.data('spotlight', () => ({
    open: false,
    query: '',
    groups: [],
    flat: [],
    activeIndex: -1,
    loading: false,
    controller: null,

    openPalette() {
        this.open = true;
        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
    },

    close() {
        this.open = false;
        this.query = '';
        this.groups = [];
        this.flat = [];
        this.activeIndex = -1;
    },

    async runSearch() {
        const term = this.query.trim();

        if (term === '') {
            this.groups = [];
            this.flat = [];
            this.activeIndex = -1;

            return;
        }

        this.loading = true;

        try {
            if (this.controller) {
                this.controller.abort();
            }

            this.controller = new AbortController();

            const response = await fetch(
                '/search/suggest?q=' + encodeURIComponent(term),
                { headers: { Accept: 'application/json' }, signal: this.controller.signal },
            );

            if (!response.ok) {
                this.groups = [];
                this.flat = [];

                return;
            }

            const data = await response.json();
            this.groups = data.groups || [];
            // Notes, to-dos and bookmarks are plain rows now — all served by
            // the server suggest endpoint.
            this.flat = this.groups.flatMap((group) => group.results);
            this.activeIndex = this.flat.length ? 0 : -1;
        } catch (error) {
            // Aborted or network error: leave the previous results in place.
        } finally {
            this.loading = false;
        }
    },

    move(delta) {
        if (!this.flat.length) {
            return;
        }

        this.activeIndex = (this.activeIndex + delta + this.flat.length) % this.flat.length;
    },

    isActive(item) {
        return this.activeIndex >= 0 && this.flat[this.activeIndex] && this.flat[this.activeIndex].url === item.url;
    },

    go() {
        const item = this.flat[this.activeIndex];

        if (item) {
            window.location.href = item.url;
        } else if (this.query.trim() !== '') {
            this.seeAll();
        }
    },

    seeAll() {
        window.location.href = '/search?q=' + encodeURIComponent(this.query.trim());
    },
}));

/**
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

// Shared JSON request used by the module Alpine components (albums, people,
// …). Each component still calls this._json(url, method, body); the body
// delegates here so the fetch shape lives in one place.
function apiJson(url, method, body, token) {
    return fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
        body: body ? JSON.stringify(body) : undefined,
    });
}

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
 * Cross-user sharing modal behaviour, mixed into pages that share resources
 * (albums). The host must provide `_json` and cfg.shares*.
 */
function shareMixin(cfg) {
    return {
        shareModal: { open: false, type: '', id: null, name: '', shares: [], email: '', permission: 'read', error: '', feedback: '', publicUrl: '', publicId: null, publicEmail: '', publicExpiry: '', publicPassword: '', publicExpiresAt: null, publicHasPassword: false },
        shareMailConfigured: !! cfg.mailConfigured,
        async openShare(type, id, name) {
            this.shareModal = { open: true, type, id, name, shares: [], email: '', permission: 'read', error: '', feedback: '', publicUrl: '', publicId: null, publicEmail: '', publicExpiry: '', publicPassword: '', publicExpiresAt: null, publicHasPassword: false };
            await this.loadShares();
        },
        async copyShareLink() {
            try { await navigator.clipboard.writeText(cfg.shareLink); this.shareFlash(cfg.linkCopied || 'Copied'); } catch (e) { /* ignore */ }
        },
        async emailShare(id) {
            const r = await this._json(cfg.sharesBase + '/' + id + '/email', 'POST');
            if (r.ok) { this.shareFlash(cfg.mailSent || 'Sent'); } else { this.shareModal.error = cfg.mailUnavailable || 'Error'; }
        },
        async createPublic() {
            const payload = {
                type: this.shareModal.type,
                id: this.shareModal.id,
                expires_in: this.shareModal.publicExpiry ? Number(this.shareModal.publicExpiry) : null,
            };
            if (this.shareModal.type === 'albums' && this.shareModal.publicPassword) payload.password = this.shareModal.publicPassword;
            const r = await this._json(cfg.publicStoreUrl, 'POST', payload);
            if (r.ok) { this.shareModal.publicPassword = ''; await this.loadShares(); }
        },
        async rotatePublic(msg) {
            if (! this.shareModal.publicId) return;
            const r = await this._json(cfg.publicBase + '/' + this.shareModal.publicId + '/rotate', 'POST');
            if (r.ok && r.url) { this.shareModal.publicUrl = r.url; this.shareFlash(msg || 'Regenerated'); }
        },
        async revokePublic() {
            if (! this.shareModal.publicId) return;
            await this._json(cfg.publicBase + '/' + this.shareModal.publicId, 'DELETE');
            await this.loadShares();
        },
        async copyPublicLink() {
            try { await navigator.clipboard.writeText(this.shareModal.publicUrl); this.shareFlash(cfg.linkCopied || 'Copied'); } catch (e) { /* ignore */ }
        },
        async emailPublic() {
            if (! this.shareModal.publicId || ! this.shareModal.publicEmail) return;
            const r = await this._json(cfg.publicBase + '/' + this.shareModal.publicId + '/email', 'POST', { email: this.shareModal.publicEmail });
            if (r.ok) { this.shareModal.publicEmail = ''; this.shareFlash(cfg.mailSent || 'Sent'); } else { this.shareModal.error = cfg.mailUnavailable || 'Error'; }
        },
        shareFlash(msg) { this.shareModal.feedback = msg; clearTimeout(this._shareFlashT); this._shareFlashT = setTimeout(() => { this.shareModal.feedback = ''; }, 2500); },
        async loadShares() {
            try {
                const r = await fetch(cfg.sharesDataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const d = await r.json();
                    this.shareModal.shares = (d.shared_by_me || []).filter((s) => s.type === this.shareModal.type && String(s.resource_id) === String(this.shareModal.id));
                    const pub = (d.public || []).find((s) => s.type === this.shareModal.type && String(s.resource_id) === String(this.shareModal.id));
                    this.shareModal.publicUrl = pub ? pub.url : '';
                    this.shareModal.publicId = pub ? pub.id : null;
                    this.shareModal.publicExpiresAt = pub ? (pub.expires_at || null) : null;
                    this.shareModal.publicHasPassword = pub ? !! pub.has_password : false;
                }
            } catch (e) { /* keep */ }
        },
        async addShare() {
            this.shareModal.error = '';
            const r = await this._json(cfg.sharesUrl, 'POST', { type: this.shareModal.type, id: this.shareModal.id, email: this.shareModal.email, permission: this.shareModal.permission });
            if (r.ok) { this.shareModal.email = ''; await this.loadShares(); } else { this.shareModal.error = cfg.shareError || 'Error'; }
        },
        async revokeShare(id) { await this._json(cfg.sharesBase + '/' + id, 'DELETE'); await this.loadShares(); },
    };
}

/**
 * People grid: lists clustered people (cover face + name + count).
 */
Alpine.data('peoplePage', (cfg = {}) => ({
    people: [],
    loading: true,
    cfg,
    async init() {
        try {
            const res = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) this.people = (await res.json()).people ?? [];
        } catch (e) { /* keep */ } finally { this.loading = false; }
    },
}));

/**
 * Person page: a person's photos + rename / hide.
 */
Alpine.data('personPage', (cfg = {}) => ({
    person: { name: '', count: 0, hidden: false },
    photos: [], faces: [], others: [],
    nameOpen: false,
    mergeQuery: '', mergeOpen: false,
    saved: false, _savedT: null,
    async init() { await this.load(); },

    flashSaved() {
        this.saved = true;
        clearTimeout(this._savedT);
        this._savedT = setTimeout(() => { this.saved = false; }, 2500);
    },
    async load() {
        try {
            const res = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            const data = await res.json();
            this.person = data.person;
            this.photos = data.photos ?? [];
            this.faces = data.faces ?? [];
            this.others = data.others ?? [];
        } catch (e) { /* keep */ }
    },
    async _patch(body) {
        try {
            await fetch(cfg.updateUrl, {
                method: 'PATCH',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token },
                body: JSON.stringify(body),
            });
        } catch (e) { /* ignore */ }
    },
    async _post(url, body) {
        try {
            await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token }, body: body ? JSON.stringify(body) : undefined });
        } catch (e) { /* ignore */ }
    },
    async save() { await this._patch({ name: this.person.name }); this.nameOpen = false; this.flashSaved(); },
    toggleHidden() { this.person.hidden = ! this.person.hidden; this._patch({ hidden: this.person.hidden }); },

    // Merge autocomplete over the already-named people.
    filteredOthers() {
        const q = this.mergeQuery.toLowerCase().trim();
        return this.others.filter((o) => q === '' || (o.name || '').toLowerCase().includes(q));
    },
    async pickMerge(o) {
        this.mergeOpen = false; this.mergeQuery = '';
        await this.merge(o.id);
    },
    async merge(sourceId) {
        if (! sourceId || ! await this.$store.confirm.ask(cfg.mergeConfirm)) return;
        await this._post(cfg.mergeUrl, { source_id: sourceId });
        this.load();
    },
    async reassignFace(faceId) {
        if (! await this.$store.confirm.ask(cfg.reassignConfirm)) return;
        await this._post(cfg.faceBase + '/' + faceId + '/reassign', { new: true });
        this.load();
    },
}));

/**
 * Duplicates review page: lists content-based duplicate groups; pick one photo
 * to keep and trash the rest, or dismiss a group as not-a-duplicate.
 */
Alpine.data('duplicatesPage', (cfg = {}) => ({
    groups: [],
    keep: {},
    loading: true,

    init() { this.load(); },

    async load() {
        try {
            const res = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            this.groups = (await res.json()).groups ?? [];
            // Default: keep the first member of each group.
            for (const g of this.groups) {
                if (this.keep[g.group] == null && g.photos.length) this.keep[g.group] = g.photos[0].id;
            }
        } catch (e) { /* keep current */ } finally {
            this.loading = false;
        }
    },

    async resolve(g) {
        const keepId = this.keep[g.group];
        if (! keepId) return;
        if (! await this.$store.confirm.ask(cfg.confirm)) return;
        // Only drop the group from the UI after the server confirms — otherwise a
        // failed request would hide a duplicate that was never actually resolved.
        try {
            const res = await fetch(cfg.resolveBase.replace('__G__', g.group), {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token },
                body: JSON.stringify({ keep_id: keepId }),
            });
            if (! res.ok) throw new Error('resolve failed');
            this.groups = this.groups.filter((x) => x.group !== g.group);
        } catch (e) {
            window.llToast?.(cfg.saveFailed || '');
            this.load?.();
        }
    },

    async dismiss(g) {
        try {
            const res = await fetch(cfg.dismissBase.replace('__G__', g.group), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token },
            });
            if (! res.ok) throw new Error('dismiss failed');
            this.groups = this.groups.filter((x) => x.group !== g.group);
        } catch (e) {
            window.llToast?.(cfg.saveFailed || '');
            this.load?.();
        }
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
Alpine.data('gallery', (url, token, feedUrl = '', hasMore = false, mapZoom = 13, monthsUrl = '', reverseUrl = '') => ({
    dragging: false,
    queue: [],
    selected: [],
    active: 0,
    maxConcurrent: 3,
    summary: null,
    lastSelected: null,
    maxSelection: 1000,
    capNotice: false,

    // Infinite scroll.
    page: 1,
    hasMore,
    loading: false,

    // Timeline scrubber.
    months: [],

    // Zoom: photos per row (persisted per user).
    cols: 6,

    // Viewer.
    viewerOpen: false,
    current: {},
    list: [],
    index: 0,
    editing: false,
    motionPlaying: false,
    showDetails: false,
    miniMap: null,
    placeTimer: null,
    mapZoom,

    async saveCols() {
        try {
            await fetch('/gallery/columns', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ columns: this.cols }),
            });
        } catch (e) { /* ignore */ }
    },

    // Play a Live Photo's motion clip on hover (Apple-style), stop on leave.
    hoverMotion(el, enter, ev) {
        // Hover-to-play only makes sense with a real mouse; on touch devices a
        // tap fires pointerenter and would clash with opening the viewer. Gate
        // on the event's pointerType (reliable) rather than a media query
        // (which is false on some hybrid/hi-dpi setups, killing hover there).
        if (ev && ev.pointerType && ev.pointerType !== 'mouse') return;
        const src = el.getAttribute('data-motion');
        if (! src) return;
        if (enter) {
            if (el._motionVid) return;
            const v = document.createElement('video');
            v.src = src; v.muted = true; v.loop = true; v.playsInline = true; v.autoplay = true;
            v.className = 'absolute inset-0 h-full w-full object-cover';
            el.appendChild(v);
            el._motionVid = v;
            v.play().catch(() => {});
        } else if (el._motionVid) {
            el._motionVid.pause(); el._motionVid.remove(); el._motionVid = null;
        }
    },

    // Add selected photos to an album (existing or new).
    albumBox: { open: false, list: [], newName: '' },
    async openAlbumBox() {
        if (! this.selected.length) return;
        this.albumBox = { open: true, list: [], newName: '' };
        try {
            const r = await fetch('/gallery/albums/data', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this.albumBox.list = ((await r.json()).albums ?? []).filter((a) => a.owned);
        } catch (e) { /* keep */ }
    },
    _albumPost(url, body) {
        return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }, body: JSON.stringify(body) });
    },
    async addToAlbum(id) {
        await this._albumPost('/gallery/albums/' + id + '/photos', { photo_ids: this.selected });
        this.albumBox.open = false; this.selected = [];
    },
    async createAlbumAndAdd() {
        const name = (this.albumBox.newName || '').trim();
        if (! name) return;
        const r = await this._albumPost('/gallery/albums', { name });
        const id = (await r.json()).id;
        await this.addToAlbum(id);
    },

    initGallery() {
        this.cols = Number(document.querySelector('meta[name="gallery-columns"]')?.content) || 6;

        // Show the drop overlay only while files are dragged over the window.
        let depth = 0;
        window.addEventListener('dragenter', (e) => {
            if (e.dataTransfer?.types?.includes('Files')) { depth++; this.dragging = true; }
        });
        window.addEventListener('dragleave', () => { depth = Math.max(0, depth - 1); if (! depth) this.dragging = false; });
        window.addEventListener('drop', () => { depth = 0; this.dragging = false; });

        // Tear the mini-map down when the viewer closes so it re-initialises
        // cleanly for the next photo, and stop any playing video.
        this.$watch('viewerOpen', (open) => {
            if (! open) {
                this.destroyMiniMap();
                document.querySelectorAll('video').forEach((v) => v.pause());
            }
        });

        // Live-update the mini-map and, while editing, the reverse-geocoded place
        // as the coordinates change.
        this.$watch('current.lat', () => { if (this.viewerOpen) { this.renderMiniMap(); this.queuePlaceRefresh(); } });
        this.$watch('current.lng', () => { if (this.viewerOpen) { this.renderMiniMap(); this.queuePlaceRefresh(); } });

        // Apply a location chosen in the map picker to the photo being edited.
        window.addEventListener('location-picked', (e) => {
            if (e.detail?.context === 'single') {
                this.current.lat = e.detail.lat;
                this.current.lng = e.detail.lng;
            }
        });

        this.loadMonths();
    },

    pick(event) {
        this.enqueue(event.target.files);
        event.target.value = '';
    },

    drop(event) {
        this.dragging = false;
        this.enqueue(event.dataTransfer.files);
    },

    /* ---- Infinite scroll ---- */

    async loadMore() {
        if (this.loading || ! this.hasMore) {
            return;
        }
        this.loading = true;
        const next = this.page + 1;

        try {
            const sep = feedUrl.includes('?') ? '&' : '?';
            const r = await fetch(`${feedUrl}${sep}page=${next}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await r.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const target = this.$refs.timeline;
            doc.querySelectorAll('section[data-day]').forEach((section) => {
                const day = section.dataset.day;
                const existing = target.querySelector(`section[data-day="${day}"] [data-day-grid]`);
                if (existing) {
                    section.querySelectorAll('[data-day-grid] > *').forEach((tile) => existing.appendChild(tile));
                } else {
                    target.appendChild(section);
                }
            });
            this.page = next;
            this.hasMore = html.includes('data-has-more="1"');
        } catch (e) { /* leave hasMore as-is so a later scroll retries */ } finally {
            this.loading = false;
        }

        // Fast scrolling can blow past the sentinel; keep loading while it is
        // still within reach of the viewport.
        this.$nextTick(() => this.fillViewport());
    },

    fillViewport() {
        const el = this.$refs.sentinel;
        if (! el || ! this.hasMore || this.loading) {
            return;
        }
        if (el.getBoundingClientRect().top < window.innerHeight + 800) {
            this.loadMore();
        }
    },

    /* ---- Timeline scrubber ---- */

    loadMonths() {
        if (! monthsUrl) {
            return;
        }
        fetch(monthsUrl, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then(({ months }) => { this.months = months; })
            .catch(() => {});
    },

    // Scroll to a month, loading further pages until its section is present.
    async scrollToMonth(ym) {
        for (let guard = 0; guard < 80; guard++) {
            const el = this.$refs.timeline.querySelector(`section[data-month="${ym}"]`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            if (! this.hasMore) {
                return;
            }
            await this.loadMore();
        }
    },

    /* ---- Viewer ---- */

    openViewer(el) {
        this.list = [...document.querySelectorAll('[data-photo]')];
        this.index = this.list.indexOf(el);
        this.setCurrent();
        this.viewerOpen = true;
    },

    setCurrent() {
        const d = this.list[this.index]?.dataset;
        this.current = d ? { ...d } : {};
        this.editing = false;
        this.motionPlaying = false;
        this.showDetails = false;
        this.$nextTick(() => this.renderMiniMap());
    },

    // Toggle the favourite without a reload (reusing the form's csrf token) so
    // the lightbox stays open. The source card's dataset is kept in sync.
    async favoriteCurrent(event) {
        try {
            const res = await fetch(event.target.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(event.target),
            });
            if (! res.ok) return;
            const b = await res.json();
            this.current.favorite = b.favorite ? '1' : '';
            const el = this.list[this.index];
            if (el) el.dataset.favorite = this.current.favorite;
        } catch (e) { /* leave state unchanged on failure */ }
    },

    // Bulk soft-delete without a reload: remove the deleted cards from the grid
    // and clear the selection. Returns success so the modal can close.
    async bulkDelete(event) {
        const res = await fetch(event.target.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(event.target),
        });
        if (! res.ok) return false;
        const b = await res.json();
        (b.ids ?? []).forEach((id) => document.querySelector(`[data-photo-id="${id}"]`)?.remove());
        // Drop any day section left with no photos, so its date header does not
        // linger after all of that day's images are deleted.
        document.querySelectorAll('section[data-day]').forEach((s) => {
            if (! s.querySelector('[data-photo-id]')) s.remove();
        });
        if (this.viewerOpen && (b.ids ?? []).includes(Number(this.current.id))) this.viewerOpen = false;
        this.applySelection([]);
        return true;
    },

    // Queue an async export of the current selection; a worker builds the zip and
    // it appears under Downloads. Shows a toast instead of a synchronous download.
    async queueExport(variant, queuedMsg) {
        if (! this.selected.length) return;
        try {
            const res = await fetch('/gallery/export', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ photo_ids: this.selected, variant }),
            });
            if (res.ok) { window.llToast(queuedMsg, '/downloads'); return; }
            // Surface the in-flight cap (429) or other server message.
            const body = await res.json().catch(() => ({}));
            if (body.message) window.llToast(body.message);
        } catch (e) { /* ignore; user can retry */ }
    },

    // Bulk set location without a reload; returns success so the modal can close.
    async bulkLocation(event) {
        const res = await fetch(event.target.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(event.target),
        });
        return res.ok;
    },

    // Save the name/date/location edits without a reload (the form carries its
    // own _token + _method=PUT); refresh the shown metadata from the response.
    async saveMeta(event) {
        try {
            const res = await fetch(event.target.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(event.target),
            });
            if (! res.ok) return;
            const b = await res.json();
            Object.assign(this.current, {
                name: b.name, date: b.date, dateiso: b.dateiso, time: b.time,
                camera: b.camera, place: b.place, placeLines: b.placeLines,
                lat: b.lat ?? '', lng: b.lng ?? '',
            });
            const el = this.list[this.index];
            if (el) { el.dataset.name = b.name ?? ''; el.dataset.date = b.date ?? ''; el.dataset.dateiso = b.dateiso ?? ''; }
            this.editing = false;
            this.$nextTick(() => this.renderMiniMap());
        } catch (e) { /* leave state unchanged on failure */ }
    },

    async renderMiniMap() {
        // Two watchers (lat + lng) fire on every photo change; without a token
        // both async runs would call L.map() on the same container and the
        // second throws "Map container is already initialized". Only the latest
        // call past the await is allowed to create the map.
        const token = (this._miniToken = (this._miniToken || 0) + 1);
        this.destroyMiniMap();

        const el = this.$refs.miniMap;
        const lat = parseFloat(this.current.lat);
        const lng = parseFloat(this.current.lng);
        if (! el || ! Number.isFinite(lat) || ! Number.isFinite(lng)) {
            return;
        }

        const L = await loadLeaflet();
        if (token !== this._miniToken) return; // superseded by a newer render
        this.destroyMiniMap();
        this.miniMap = L.map(el, {
            attributionControl: false,
            zoomControl: false,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
        }).setView([lat, lng], this.mapZoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.miniMap);

        const icon = L.divIcon({
            className: '',
            html: '<div class="h-3 w-3 rounded-full bg-red-500 ring-2 ring-white shadow"></div>',
            iconSize: [12, 12],
            iconAnchor: [6, 6],
        });
        L.marker([lat, lng], { icon }).addTo(this.miniMap);

        this.$nextTick(() => this.miniMap && this.miniMap.invalidateSize());
    },

    destroyMiniMap() {
        if (this.miniMap) {
            this.miniMap.remove();
            this.miniMap = null;
        }
    },

    // While editing coordinates, reverse-geocode the new spot (debounced) and
    // update the shown place so the user sees the new location immediately.
    queuePlaceRefresh() {
        if (! this.editing || ! reverseUrl) {
            return;
        }
        const lat = parseFloat(this.current.lat);
        const lng = parseFloat(this.current.lng);
        if (! Number.isFinite(lat) || ! Number.isFinite(lng)) {
            this.current.placeLines = '';
            this.current.place = '';
            return;
        }
        clearTimeout(this.placeTimer);
        this.placeTimer = setTimeout(async () => {
            try {
                const r = await fetch(`${reverseUrl}?lat=${lat}&lon=${lng}`, { headers: { Accept: 'application/json' } });
                if (! r.ok) return;
                const data = await r.json();
                this.current.place = data.place || '';
                this.current.placeLines = (data.lines || []).join('|');
            } catch (e) { /* leave the previous place shown */ }
        }, 600);
    },

    next() {
        if (this.index < this.list.length - 1) { this.index++; this.setCurrent(); }
    },

    prev() {
        if (this.index > 0) { this.index--; this.setCurrent(); }
    },

    enqueue(fileList) {
        this.summary = null;
        // Only image types the browser can actually render get a live preview;
        // HEIC and friends fall back to an icon instead of a broken image.
        const previewable = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
        for (const file of fileList) {
            const isVideo = file.type.startsWith('video/');
            if (! isVideo && ! file.type.startsWith('image/')) {
                continue;
            }
            this.queue.push({
                name: file.name,
                file,
                isVideo,
                preview: previewable.includes(file.type) ? URL.createObjectURL(file) : null,
                progress: 0,
                state: 'pending', // pending | uploading | done | duplicate | skipped | error
                reason: '',
            });
        }
        this.pump();
    },

    // Keep up to maxConcurrent uploads in flight; report a summary when the
    // whole batch settles.
    pump() {
        while (this.active < this.maxConcurrent) {
            const item = this.queue.find((entry) => entry.state === 'pending');
            if (! item) {
                break;
            }
            this.upload(item);
        }

        if (this.active === 0 && ! this.queue.some((entry) => entry.state === 'pending')) {
            this.onComplete();
        }
    },

    upload(item) {
        item.state = 'uploading';
        this.active++;

        const data = new FormData();
        data.append('photo', item.file);
        data.append('_token', token);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                item.progress = Math.round((e.loaded / e.total) * 100);
            }
        };
        const settle = () => { this.active--; this.pump(); };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                item.progress = 100;
                let body = {};
                try { body = JSON.parse(xhr.responseText); } catch (e) { /* plain response */ }
                item.state = body.skipped ? 'skipped' : (body.duplicate ? 'duplicate' : 'done');
                item.reason = body.reason || '';
            } else {
                item.state = 'error';
            }
            settle();
        };
        xhr.onerror = () => { item.state = 'error'; settle(); };
        xhr.send(data);
    },

    onComplete() {
        const created = this.queue.filter((e) => e.state === 'done').length;
        const duplicates = this.queue.filter((e) => e.state === 'duplicate').map((e) => e.name);
        const skipped = this.queue.filter((e) => e.state === 'skipped').map((e) => e.name);
        const errored = this.queue.filter((e) => e.state === 'error').map((e) => e.name);

        // Any new photos → refresh to show them. Only keep the tray open when
        // nothing was added (everything was a duplicate, skipped or failed) so
        // that list stays visible to review.
        if (created > 0) {
            window.location.reload();
            return;
        }

        this.summary = { created, duplicates, skipped, errored };
    },

    dismissUploads() {
        this.queue.forEach((e) => e.preview && URL.revokeObjectURL(e.preview));
        this.queue = [];
        this.summary = null;
        if (this.$refs.timeline) {
            window.location.reload();
        }
    },

    toggleAll(event) {
        this.applySelection(event.target.checked ? this.allIds() : []);
    },

    allIds() {
        return [...document.querySelectorAll('[data-photo-id]')].map((el) => Number(el.dataset.photoId));
    },

    /* ---- Selection (click, shift-range, select-all, per-day) ---- */

    selectableIds() {
        return [...document.querySelectorAll('[data-select]')].map((cb) => Number(cb.value));
    },

    // Photo ids under a given day section.
    dayIds(day) {
        return [...document.querySelectorAll(`section[data-day="${day}"] [data-select]`)].map((cb) => Number(cb.value));
    },

    dayFullySelected(day) {
        const ids = this.dayIds(day);
        return ids.length > 0 && ids.every((id) => this.selected.includes(id));
    },

    // Select or clear all photos of one day at once.
    toggleDay(day) {
        const ids = this.dayIds(day);
        if (this.dayFullySelected(day)) {
            const remove = new Set(ids);
            this.selected = this.selected.filter((id) => ! remove.has(id));
        } else {
            this.applySelection([...this.selected, ...ids]);
        }
    },

    // Cap every multi-selection at maxSelection; warn and keep the first N.
    applySelection(ids) {
        const unique = [...new Set(ids)];
        if (unique.length > this.maxSelection) {
            this.selected = unique.slice(0, this.maxSelection);
            this.notifyCap();
        } else {
            this.selected = unique;
        }
    },

    notifyCap() {
        this.capNotice = true;
        clearTimeout(this._capTimer);
        this._capTimer = setTimeout(() => { this.capNotice = false; }, 5000);
    },

    toggleSelect(event, id) {
        // Shift-click extends the selection from the last clicked tile to here.
        if (event.shiftKey && this.lastSelected !== null) {
            const ids = this.selectableIds();
            const from = ids.indexOf(this.lastSelected);
            const to = ids.indexOf(id);
            if (from !== -1 && to !== -1) {
                const [a, b] = from < to ? [from, to] : [to, from];
                const set = new Set(this.selected);
                for (let i = a; i <= b; i++) {
                    set.add(ids[i]);
                }
                this.applySelection([...set]);
                this.lastSelected = id;
                return;
            }
        }

        if (this.selected.includes(id)) {
            this.selected = this.selected.filter((x) => x !== id);
        } else if (this.selected.length >= this.maxSelection) {
            this.notifyCap(); // at the cap: refuse to add more
        } else {
            this.selected = [...this.selected, id];
        }
        this.lastSelected = id;
    },

    selectAllVisible() {
        this.applySelection(this.selectableIds());
    },

    onKeydown(event) {
        if ((event.metaKey || event.ctrlKey) && (event.key === 'a' || event.key === 'A')) {
            const tag = document.activeElement?.tagName;
            if (this.viewerOpen || tag === 'INPUT' || tag === 'TEXTAREA') {
                return;
            }
            event.preventDefault();
            this.selectAllVisible();
        }
    },
}));

/**
 * Photo map: loads geotagged photos and plots them on an OpenStreetMap map with
 * marker clustering (counts, scroll/pinch zoom). Each marker is the photo's
 * thumbnail; clicking opens it. Clusters expand on click / zoom.
 *
 * @param {string} pointsUrl  Endpoint returning { points: [...] }.
 */
Alpine.data('photoMap', (pointsUrl, mapZoom = 13) => ({
    lightbox: null,

    async init() {
        const L = await loadLeaflet();
        const map = L.map(this.$refs.map, { scrollWheelZoom: true }).setView([20, 0], 2);
        this.mapZoom = mapZoom;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        const clusters = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });

        fetch(pointsUrl, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then(({ points }) => {
                if (! points.length) {
                    return;
                }
                for (const p of points) {
                    const icon = L.divIcon({
                        className: '',
                        html: `<img src="${p.thumb}" class="h-12 w-12 rounded-md object-cover shadow ring-2 ring-white">`,
                        iconSize: [48, 48],
                        iconAnchor: [24, 24],
                    });
                    const marker = L.marker([p.lat, p.lng], { icon });
                    marker.on('click', () => { this.lightbox = { src: p.medium, download: p.original }; });
                    clusters.addLayer(marker);
                }
                map.addLayer(clusters);
                map.fitBounds(clusters.getBounds().pad(0.2), { maxZoom: this.mapZoom });
            });
    },
}));


/**
 * Editor for an encrypted file: fetches the ciphertext, decrypts it to text in
 * the browser, edits it in CodeMirror, and on save re-encrypts and PUTs the new
 * ciphertext. The server never sees the plaintext. Binary content is refused.
 */
Alpine.data('locationPicker', (searchUrl) => ({
    open: false,
    context: null,
    lat: null,
    lng: null,
    query: '',
    results: [],
    searching: false,
    map: null,
    marker: null,
    searchTimer: null,

    initPicker() {
        window.addEventListener('open-location-picker', (e) => {
            this.context = e.detail?.context ?? null;
            const lat = parseFloat(e.detail?.lat);
            const lng = parseFloat(e.detail?.lng);
            this.lat = Number.isFinite(lat) ? lat : null;
            this.lng = Number.isFinite(lng) ? lng : null;
            this.query = '';
            this.results = [];
            this.open = true;
            this.$nextTick(() => this.mountMap());
        });
    },

    async mountMap() {
        if (this.map) {
            this.map.remove();
            this.map = null;
            this.marker = null;
        }
        const L = await loadLeaflet();
        const hasPin = this.lat != null && this.lng != null;
        this.map = L.map(this.$refs.pickerMap).setView(hasPin ? [this.lat, this.lng] : [20, 0], hasPin ? 13 : 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);
        if (hasPin) {
            this.setMarker(this.lat, this.lng, false);
        }
        this.map.on('click', (e) => this.setMarker(e.latlng.lat, e.latlng.lng, false));
        this.$nextTick(() => this.map && this.map.invalidateSize());
    },

    setMarker(lat, lng, recenter = true) {
        this.lat = lat;
        this.lng = lng;
        if (this.marker) {
            this.marker.setLatLng([lat, lng]);
        } else {
            // Leaflet is already loaded here (the map exists).
            this.marker = leafletModule.marker([lat, lng]).addTo(this.map);
        }
        if (recenter) {
            this.map.setView([lat, lng], Math.max(this.map.getZoom(), 13));
        }
    },

    queueSearch() {
        clearTimeout(this.searchTimer);
        if (! this.query.trim()) {
            this.results = [];
            return;
        }
        this.searchTimer = setTimeout(() => this.runSearch(), 500);
    },

    async runSearch() {
        this.searching = true;
        try {
            const r = await fetch(`${searchUrl}?q=${encodeURIComponent(this.query.trim())}`, { headers: { Accept: 'application/json' } });
            this.results = (await r.json()).results || [];
        } catch (e) {
            this.results = [];
        } finally {
            this.searching = false;
        }
    },

    choose(res) {
        this.results = [];
        this.query = res.display;
        this.setMarker(res.lat, res.lon, true);
    },

    apply() {
        if (this.lat == null || this.lng == null) {
            return;
        }
        window.dispatchEvent(new CustomEvent('location-picked', {
            detail: { context: this.context, lat: this.lat, lng: this.lng },
        }));
        this.close();
    },

    close() {
        this.open = false;
        this.results = [];
        if (this.map) {
            this.map.remove();
            this.map = null;
            this.marker = null;
        }
    },
}));

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
        for (const blob of uniq) {
            fetch(`${config.blobBase}/${blob}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token } }).catch(() => {});
        }
        if (uniq.length) this.refreshUsage();
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
                        // never sent to the server.
                        const cipher = new File([enc.blob], 'blob.enc', { type: 'application/octet-stream' });
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
    async fetchPlain(row) {
        const res = await fetch(`${config.rawBase}/${row.blob}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (! res.ok) throw new Error('fetch failed');
        return window.Vault.decryptFile(await res.arrayBuffer(), row.encFileKey);
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


/**
 * Albums list: create/rename/delete albums, open the share dialog per album.
 */
Alpine.data('albumsPage', (cfg = {}) => ({
    ...shareMixin(cfg),
    // Exposed so template expressions (:href) can read it — the closure `cfg`
    // itself is not visible inside Blade x-bind/x-text, only component state is.
    cfg,
    albums: [], loading: true,
    nameModal: { open: false, value: '' },

    init() { this.load(); },
    async load() {
        try {
            const r = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this.albums = (await r.json()).albums ?? [];
        } catch (e) { /* keep */ } finally { this.loading = false; }
    },
    openNew() { this.nameModal = { open: true, value: '' }; this.$nextTick(() => this.$refs.albumName?.focus()); },
    async createAlbum() {
        const name = (this.nameModal.value || '').trim();
        if (! name) return;
        this.nameModal.open = false;
        await this._json(cfg.storeUrl, 'POST', { name });
        this.load();
    },
    _json(url, method, body) {
        return apiJson(url, method, body, cfg.token);
    },
}));

/**
 * Album detail: photos grid, add (from the gallery picker) / remove, rename,
 * delete, and share (internal + public) via shareMixin.
 */
Alpine.data('albumPage', (cfg = {}) => ({
    ...shareMixin(cfg),
    album: { name: '', owned: false, can_edit: false }, photos: [], loading: true,
    picker: { open: false, list: [], chosen: [] },
    renameModal: { open: false, value: '' },

    init() { this.load(); },
    async load() {
        try {
            const r = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) { const d = await r.json(); this.album = d.album; this.photos = d.photos ?? []; }
        } catch (e) { /* keep */ } finally { this.loading = false; }
    },
    async openPicker() {
        this.picker = { open: true, list: [], chosen: [] };
        try {
            const r = await fetch(cfg.pickerUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this.picker.list = (await r.json()).photos ?? [];
        } catch (e) { /* keep */ }
    },
    togglePick(id) {
        const i = this.picker.chosen.indexOf(id);
        if (i >= 0) this.picker.chosen.splice(i, 1); else this.picker.chosen.push(id);
    },
    async addChosen() {
        if (! this.picker.chosen.length) { this.picker.open = false; return; }
        await this._json(cfg.photosUrl, 'POST', { photo_ids: this.picker.chosen });
        this.picker.open = false; this.load();
    },
    async removePhoto(id) {
        // Confirm a single-click removal so a mis-click doesn't silently detach a
        // photo from the album (the photo itself is untouched).
        if (! await this.$store.confirm.ask(cfg.removeConfirm || cfg.deleteConfirm || '')) return;
        await this._json(cfg.photosUrl, 'DELETE', { photo_ids: [id] });
        this.load();
    },
    openRename() { this.renameModal = { open: true, value: this.album.name }; },
    async saveRename() {
        const name = (this.renameModal.value || '').trim();
        if (! name) return;
        this.renameModal.open = false;
        await this._json(cfg.albumUrl, 'PUT', { name });
        this.album.name = name;
    },
    async destroyAlbum() {
        if (! await this.$store.confirm.ask(cfg.deleteConfirm)) return;
        await this._json(cfg.albumUrl, 'DELETE');
        window.location = cfg.albumsUrl;
    },
    _json(url, method, body) {
        return apiJson(url, method, body, cfg.token);
    },
}));

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
 * lock they clear those arrays and reset the store. Mirrors the shareMixin(cfg)
 * pattern: each component spreads this and supplies its module-specific bits.
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
