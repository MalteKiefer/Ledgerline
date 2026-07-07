import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import DOMPurify from 'dompurify';
import 'github-markdown-css/github-markdown-light.css';
import 'highlight.js/styles/github.css';

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

// Shared JSON request used by the module Alpine components (contacts, calendar,
// albums, …). Each component still calls this.\_json(url, method, body); the body
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
 * (calendars, address books). The host must provide `_json` and cfg.shares*.
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
 * Contacts page: book/group filter + search, contact list, and a create/edit
 * modal that writes through the vCard-backed API.
 */
Alpine.data('contactsPage', (cfg = {}) => ({
    ...shareMixin(cfg),
    cfg,
    books: [], groups: [], contacts: [], loading: true,
    book: '', group: '', q: '', favorites: false, selected: [],
    sort: 'first_name', displayFormat: 'first_last', _settingsReady: false,
    importing: false, importResult: '',
    nameModal: { open: false, title: '', value: '', onsubmit: null },
    confirmModal: { open: false, message: '', onConfirm: null },

    openConfirm(message, onConfirm) { this.confirmModal = { open: true, message, onConfirm }; },
    async doConfirm() { const cb = this.confirmModal.onConfirm; this.confirmModal.open = false; if (cb) await cb(); },

    init() {
        this.load();
        this.$watch('q', () => this.load());
        this.$watch('book', () => this.load());
        this.$watch('group', () => this.load());
        this.$watch('favorites', () => this.load());
    },

    // --- reusable name modal (replaces window.prompt for books/groups) ---
    openNameModal(title, value, onsubmit) {
        this.nameModal = { open: true, title, value: value || '', onsubmit };
        this.$nextTick(() => this.$refs.nameInput?.focus());
    },
    async submitNameModal() {
        const v = (this.nameModal.value || '').trim();
        const cb = this.nameModal.onsubmit;
        this.nameModal.open = false;
        if (v && cb) await cb(v);
    },

    async load() {
        const u = new URL(cfg.dataUrl, location.origin);
        if (this.book) u.searchParams.set('book', this.book);
        if (this.group) u.searchParams.set('group', this.group);
        if (this.q) u.searchParams.set('q', this.q);
        if (this.favorites) u.searchParams.set('favorites', '1');
        try {
            const r = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) {
                const d = await r.json();
                this.books = d.books; this.groups = d.groups; this.contacts = d.contacts;
                if (d.settings) { this.sort = d.settings.sort; this.displayFormat = d.settings.display_format; this._settingsReady = true; }
                // Drop selections that fell out of the current filter/list.
                const ids = new Set(this.contacts.map((c) => c.id));
                this.selected = this.selected.filter((id) => ids.has(id));
            }
        } catch (e) { /* keep */ } finally { this.loading = false; }
    },

    // --- multiselect + bulk delete ---
    toggleAll() {
        this.selected = this.selected.length === this.contacts.length ? [] : this.contacts.map((c) => c.id);
    },
    bulkDelete() {
        if (! this.selected.length) return;
        const ids = [...this.selected];
        this.openConfirm(cfg.deleteSelectedConfirm.replace(':count', ids.length), async () => {
            await this._json(cfg.bulkDestroyUrl, 'DELETE', { ids });
            this.selected = [];
            this.load();
        });
    },

    /** Format a contact's name per the chosen display format, with sensible fallbacks. */
    displayName(c) {
        const first = (c.first_name || '').trim();
        const last = (c.last_name || '').trim();
        if (this.displayFormat === 'last_first' && (first || last)) {
            return last ? (first ? `${last}, ${first}` : last) : first;
        }
        if (first || last) return `${first} ${last}`.trim();
        return c.fn || '—';
    },

    /** Up-to-two-letter initials for the avatar placeholder when a contact has no photo. */
    initials(c) {
        const first = (c.first_name || '').trim();
        const last = (c.last_name || '').trim();
        const letters = ((first[0] || '') + (last[0] || '')).toUpperCase();
        if (letters) return letters;
        const fn = (c.fn || '').trim();
        return fn ? fn[0].toUpperCase() : '';
    },

    async saveSettings() {
        if (! this._settingsReady) return;
        await this._json(cfg.settingsUrl, 'POST', { sort: this.sort, display_format: this.displayFormat });
        this.load();
    },

    /** Rows open the read-only detail page; "new" goes straight to the editor. */
    openEditor(id) {
        window.location.href = id ? cfg.contactBase + '/' + id + '/view' : cfg.createUrl;
    },

    async toggleFavorite(c) {
        c.favorite = ! c.favorite; // optimistic; corrected below on failure
        try {
            const r = await this._json(cfg.contactBase + '/' + c.id + '/favorite', 'PATCH', { favorite: c.favorite });
            if (r && typeof r.favorite === 'boolean') c.favorite = r.favorite;
        } catch (e) { c.favorite = ! c.favorite; }
        if (this.favorites) this.load(); // list may lose the row when filtering favorites
    },

    async importFile(ev) {
        const f = ev.target.files[0]; if (! f) return;
        const fd = new FormData(); fd.append('file', f); fd.append('book_id', this.book || this.books[0]?.id);
        this.importing = true; this.importResult = '';
        try {
            const r = await fetch(cfg.importUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token }, body: fd });
            if (r.ok) {
                const d = await r.json();
                this.importResult = (cfg.importResultLabel || '')
                    .replace(':created', d.created ?? 0).replace(':updated', d.updated ?? 0).replace(':skipped', d.skipped ?? 0);
                setTimeout(() => { this.importResult = ''; }, 8000);
            }
        } catch (e) { /* ignore */ } finally {
            this.importing = false; this.load(); ev.target.value = '';
        }
    },

    addBook() {
        this.openNameModal(cfg.newBook, '', async (name) => { await this._json(cfg.booksUrl, 'POST', { name }); this.load(); });
    },
    addGroup() {
        this.openNameModal(cfg.newGroup, '', async (name) => { await this._json(cfg.groupsUrl, 'POST', { name }); this.load(); });
    },

    renameBook(b) {
        this.openNameModal(cfg.renameBook, b.name, async (name) => {
            if (name === b.name) return;
            await this._json(cfg.bookBase + '/' + b.id, 'PUT', { name }); this.load();
        });
    },
    deleteBook(b) {
        this.openConfirm(cfg.confirmDeleteBook, async () => {
            if (this.book === b.id) this.book = '';
            await this._json(cfg.bookBase + '/' + b.id, 'DELETE'); this.load();
        });
    },
    deleteGroup(g) {
        this.openConfirm(cfg.confirmDeleteGroup, async () => {
            if (this.group === g.id) this.group = '';
            await this._json(cfg.groupBase + '/' + g.id, 'DELETE'); this.load();
        });
    },

    async _json(url, method, body) {
        return apiJson(url, method, body, cfg.token);
    },
}));

/**
 * Dedicated contact editor page (/contacts/new, /contacts/{id}/edit). Loads the
 * user's books + groups, then the contact (when editing), and saves back via the
 * JSON API before returning to the list. Also hosts the avatar picker/crop and
 * the per-address map preview.
 */
Alpine.data('contactEditorPage', (cfg = {}) => ({
    cfg,
    books: [], groups: [],
    form: { emails: [], phones: [], anniversaries: [], addresses: [], related: [], custom_fields: [], group_ids: [] },
    groupQuery: '', groupOpen: false,
    relatedIndex: null, relatedSuggestions: [],
    mapModal: { open: false, loading: false, error: false, display: '', osmUrl: '' }, _map: null,

    async init() {
        try {
            const r = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) { const d = await r.json(); this.books = d.books; this.groups = d.groups; }
        } catch (e) { /* fields still usable */ }
        if (cfg.contactId) { await this.loadContact(cfg.contactId); } else { this.form = this.blank(); }
    },

    blank() {
        const owned = this.books.filter((b) => b.owned);
        return { id: null, book_id: owned[0]?.id, fn: '', first_name: '', last_name: '', org: '', title: '', nickname: '', bday: '', anniversaries: [], note: '', emails: [{ value: '', type: 'home' }], phones: [{ value: '', type: 'cell' }], urls: [], group_ids: [], avatar: null, addresses: [], related: [], custom_fields: [], favorite: false };
    },

    async loadContact(id) {
        const r = await fetch(cfg.contactBase + '/' + id, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (! r.ok) { window.location.href = cfg.indexUrl; return; }
        const d = await r.json();
        this.form = {
            id: d.id, book_id: d.book, fn: d.fn || '', first_name: d.first_name || '', last_name: d.last_name || '',
            org: d.org || '', title: d.title || '', nickname: d.nickname || '', bday: d.bday || '', anniversaries: d.anniversaries || [], note: d.note || '',
            // Imported TYPE params can be compounds like "HOME,pref" or
            // "CELL,VOICE" — normalise to the label selects' options.
            emails: d.emails?.length ? d.emails.map((e) => ({ value: e.value || '', type: this.typeToken(e.type, 'home') })) : [{ value: '', type: 'home' }],
            phones: d.phones?.length ? d.phones.map((p) => ({ value: p.value || '', type: this.typeToken(p.type, 'cell', true) })) : [{ value: '', type: 'cell' }],
            urls: (d.urls || []).map((u) => ({ value: u.value || '', type: this.typeToken(u.type, 'home') })),
            group_ids: d.group_ids || [],
            avatar: d.photo || null, // parse() returns the PHOTO data: URI directly
            addresses: (d.addresses || []).map((a) => ({ type: this.typeToken(a.type, 'home'), street: a.street || '', ext: a.ext || '', zip: a.zip || '', city: a.city || '', region: a.region || '', country: a.country || '' })),
            related: (d.related || []).map((r) => ({ type: r.type || 'other', name: r.name || r.value || '', uid: r.uid || null })),
            custom_fields: (d.custom_fields || []).map((f) => ({ label: f.label || '', value: f.value || '' })),
            favorite: !! d.favorite,
        };
    },

    typeToken(raw, def = 'other', allowCell = false) {
        const t = (raw || '').toLowerCase();
        if (allowCell && (t.includes('cell') || t.includes('mobile'))) return 'cell';
        if (t.includes('work')) return 'work';
        if (t.includes('home')) return 'home';
        return t ? 'other' : def;
    },

    // --- group combobox (multi-select with autocomplete) ---
    filteredGroups() {
        const q = this.groupQuery.toLowerCase().trim();
        const chosen = this.form.group_ids || [];
        return this.groups.filter((g) => ! chosen.includes(g.id) && (q === '' || g.name.toLowerCase().includes(q)));
    },
    groupName(id) { return this.groups.find((g) => g.id === id)?.name || ''; },
    addGroupChip(id) {
        if (! this.form.group_ids) this.form.group_ids = [];
        if (! this.form.group_ids.includes(id)) this.form.group_ids.push(id);
        this.groupQuery = ''; this.groupOpen = false;
    },
    removeGroupChip(id) {
        const arr = this.form.group_ids || [];
        const i = arr.indexOf(id);
        if (i >= 0) arr.splice(i, 1);
    },

    payload() {
        return {
            book_id: this.form.book_id, fn: this.form.fn, first_name: this.form.first_name, last_name: this.form.last_name,
            org: this.form.org, title: this.form.title, nickname: this.form.nickname, bday: this.form.bday, anniversaries: this.form.anniversaries.filter((a) => a.date), note: this.form.note,
            emails: this.form.emails.filter((e) => e.value), phones: this.form.phones.filter((p) => p.value),
            urls: this.form.urls.filter((u) => u.value), group_ids: this.form.group_ids,
            addresses: this.form.addresses.filter((a) => (a.street + a.ext + a.zip + a.city + a.region + a.country).trim() !== ''),
            // A linked relation travels by the target card's UID; free text by name.
            related: this.form.related
                .filter((r) => r.uid || (r.name || '').trim() !== '')
                .map((r) => ({ type: r.type, uid: r.uid, value: r.uid ? null : r.name.trim() })),
            custom_fields: this.form.custom_fields.filter((f) => (f.value || '').trim() !== ''),
            favorite: !! this.form.favorite,
        };
    },

    async save() {
        // Saving keeps the editor open: an update reloads the (normalised)
        // card in place, a new contact moves to its edit URL.
        if (this.saving) return;
        this.saving = true;
        try {
            const id = this.form.id;
            const res = await this._json(id ? cfg.contactBase + '/' + id : cfg.storeUrl, id ? 'PUT' : 'POST', this.payload());
            const d = await res.json().catch(() => ({}));
            if (! id && d.id) { window.location.href = cfg.contactBase + '/' + d.id + '/edit'; return; }
            if (id && res.ok) { await this.loadContact(id); window.llToast?.(cfg.savedToast); }
        } finally { this.saving = false; }
    },

    async destroy() {
        if (! this.form.id) return;
        if (! await this.$store.confirm.ask(cfg.confirmDelete)) return;
        await this._json(cfg.contactBase + '/' + this.form.id, 'DELETE');
        window.location.href = cfg.indexUrl;
    },

    // --- related-contact autocomplete (links by the target card's UID) ---
    async relatedSearch(i) {
        this.relatedIndex = i;
        const r = this.form.related[i];
        r.uid = null; // typing breaks an existing link; picking below relinks
        const q = (r.name || '').trim();
        if (q.length < 2) { this.relatedSuggestions = []; return; }
        try {
            const u = new URL(cfg.suggestUrl, location.origin);
            u.searchParams.set('q', q);
            const res = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                this.relatedSuggestions = ((await res.json()).contacts ?? [])
                    .filter((s) => s.uid && s.id !== this.form.id);
            }
        } catch (e) { this.relatedSuggestions = []; }
    },
    pickRelated(i, s) {
        this.form.related[i].name = s.name;
        this.form.related[i].uid = s.uid;
        this.relatedSuggestions = [];
        this.relatedIndex = null;
    },

    // --- address map preview (server-side geocode, Leaflet lazy-loaded) ---
    async showMap(i) {
        if (! this.form.id) return;
        this.mapModal = { open: true, loading: true, error: false, display: '', osmUrl: '' };
        this.destroyMap();
        try {
            const u = new URL(cfg.contactBase + '/' + this.form.id + '/geo', location.origin);
            u.searchParams.set('address', i);
            const r = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! r.ok) { this.mapModal.loading = false; this.mapModal.error = true; return; }
            const d = await r.json();
            this.mapModal.loading = false;
            this.mapModal.display = d.display;
            this.mapModal.osmUrl = `https://www.openstreetmap.org/?mlat=${d.lat}&mlon=${d.lon}#map=17/${d.lat}/${d.lon}`;
            const L = await loadLeaflet();
            await this.$nextTick();
            const el = this.$refs.contactMap;
            if (! el || ! this.mapModal.open) return;
            this._map = L.map(el).setView([d.lat, d.lon], 16);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this._map);
            L.marker([d.lat, d.lon]).addTo(this._map);
            setTimeout(() => this._map?.invalidateSize(), 50);
        } catch (e) {
            this.mapModal.loading = false;
            this.mapModal.error = true;
        }
    },
    closeMap() { this.mapModal.open = false; this.destroyMap(); },
    destroyMap() { if (this._map) { this._map.remove(); this._map = null; } },

    // --- avatar picker (device / gallery / people / files) + crop ---
    avatarModal: { open: false, tab: 'upload', loading: false },
    galleryPhotos: [], peopleList: [], personPhotos: [], personSelected: null, filePhotos: [],
    cropSrc: null, _cropper: null, saving: false,

    openAvatarModal() {
        if (! this.form.id) return; // avatar needs a saved contact to attach to
        this.avatarModal = { open: true, tab: 'upload', loading: false };
        this.cropSrc = null; this.personSelected = null; this.personPhotos = [];
        this.destroyCropper();
    },
    closeAvatarModal() { this.avatarModal.open = false; this.cropSrc = null; this.destroyCropper(); },

    async avatarTab(tab) {
        this.avatarModal.tab = tab;
        this.cropSrc = null; this.destroyCropper();
        this.personSelected = null; this.personPhotos = [];
        if (tab === 'gallery' && ! this.galleryPhotos.length) await this.loadPicker('galleryPickerUrl', 'photos', 'galleryPhotos');
        if (tab === 'people' && ! this.peopleList.length) await this.loadPeople();
        if (tab === 'files' && ! this.filePhotos.length) await this.loadFilePhotos();
    },
    async loadPicker(cfgKey, field, target) {
        this.avatarModal.loading = true;
        try {
            const r = await fetch(cfg[cfgKey], { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this[target] = (await r.json())[field] ?? [];
        } catch (e) { /* keep */ } finally { this.avatarModal.loading = false; }
    },
    async loadPeople() {
        this.avatarModal.loading = true;
        try {
            const r = await fetch(cfg.peopleUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this.peopleList = ((await r.json()).people ?? []).filter((p) => p.cover && p.name);
        } catch (e) { /* keep */ } finally { this.avatarModal.loading = false; }
    },
    // Pick a person -> filter to all photos they appear in, then choose one.
    async pickPerson(person) {
        this.avatarModal.loading = true;
        this.personSelected = person;
        try {
            const r = await fetch(cfg.peopleShowBase + '/' + person.id + '/data', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this.personPhotos = (await r.json()).photos ?? [];
        } catch (e) { /* keep */ } finally { this.avatarModal.loading = false; }
    },
    backToPeople() { this.personSelected = null; this.personPhotos = []; },
    async loadFilePhotos() {
        this.avatarModal.loading = true;
        try {
            const r = await fetch(cfg.filesDataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) {
                const d = await r.json();
                this.filePhotos = (d.files ?? []).filter((f) => ! f.trashed && (f.mime || '').startsWith('image/'))
                    .map((f) => ({ name: f.name, url: cfg.filesRawBase + '/' + f.blob }));
            }
        } catch (e) { /* keep */ } finally { this.avatarModal.loading = false; }
    },

    pickDeviceImage(ev) {
        const f = ev.target.files[0]; if (! f) return;
        this.startCrop(URL.createObjectURL(f));
        ev.target.value = '';
    },
    async startCrop(src) {
        this.cropSrc = src;
        await this.$nextTick();
        this.destroyCropper();
        const { default: Cropper } = await import('cropperjs');
        await import('cropperjs/dist/cropper.css');
        this._cropper = new Cropper(this.$refs.cropImg, { aspectRatio: 1, viewMode: 1, autoCropArea: 1, background: false });
    },
    destroyCropper() { if (this._cropper) { this._cropper.destroy(); this._cropper = null; } },

    async confirmCrop() {
        if (! this._cropper || ! this.form.id) return;
        this.saving = true;
        const canvas = this._cropper.getCroppedCanvas({ width: 512, height: 512, imageSmoothingQuality: 'high' });
        const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', 0.85));
        const fd = new FormData(); fd.append('photo', blob, 'avatar.jpg');
        try {
            const r = await fetch(cfg.contactBase + '/' + this.form.id + '/avatar', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token }, body: fd });
            if (r.ok) { const d = await r.json(); this.form.avatar = d.avatar + '?t=' + Date.now(); this.closeAvatarModal(); }
        } catch (e) { /* ignore */ } finally { this.saving = false; }
    },

    async _json(url, method, body) {
        return apiJson(url, method, body, cfg.token);
    },
}));

/**
 * Read-only contact detail page (/contacts/{id}/view). Shows every card field
 * Google-style; editing happens on the separate edit page. Hosts the same
 * per-address map preview as the editor.
 */
Alpine.data('contactViewPage', (cfg = {}) => ({
    cfg,
    c: { emails: [], phones: [], urls: [], addresses: [], related: [], custom_fields: [], anniversaries: [], group_ids: [] },
    groups: [],
    geo: {}, _minis: [],
    mapModal: { open: false, loading: false, error: false, display: '', osmUrl: '' }, _map: null,

    async init() {
        const r = await fetch(cfg.contactBase + '/' + cfg.contactId, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (! r.ok) { window.location.href = cfg.indexUrl; return; }
        this.c = await r.json();
        try {
            const g = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (g.ok) this.groups = (await g.json()).groups ?? [];
        } catch (e) { /* groups stay empty */ }
        this.loadGeos();
    },

    // --- external map provider chooser (click on the address text) ---
    mapChooser: { open: false, index: null },
    openMapChooser(i) { this.mapChooser = { open: true, index: i }; },
    providerUrl(p) {
        const i = this.mapChooser.index;
        const a = (this.c.addresses || [])[i] || {};
        const g = this.geo[i];
        const q = encodeURIComponent(this.addressLines(a).replace(/\n/g, ', '));
        // Prefer the geocoded coordinates when we have them; fall back to a
        // free-text search with the formatted address.
        switch (p) {
            case 'apple': return g ? `https://maps.apple.com/?ll=${g.lat},${g.lon}&q=${q}` : `https://maps.apple.com/?q=${q}`;
            case 'google': return g ? `https://www.google.com/maps/search/?api=1&query=${g.lat}%2C${g.lon}` : `https://www.google.com/maps/search/?api=1&query=${q}`;
            case 'here': return g ? `https://wego.here.com/?map=${g.lat},${g.lon},16` : `https://wego.here.com/search/${q}`;
            default: return g
                ? `https://www.openstreetmap.org/?mlat=${g.lat}&mlon=${g.lon}#map=17/${g.lat}/${g.lon}`
                : `https://www.openstreetmap.org/search?query=${q}`;
        }
    },
    openProvider(p) {
        window.open(this.providerUrl(p), '_blank', 'noopener');
        this.mapChooser.open = false;
    },

    // Geocode every address (cached server-side) and render a small static
    // map thumbnail beside it; clicking the thumbnail opens the big modal.
    async loadGeos() {
        for (let i = 0; i < (this.c.addresses || []).length; i++) {
            try {
                const u = new URL(cfg.contactBase + '/' + cfg.contactId + '/geo', location.origin);
                u.searchParams.set('address', i);
                const r = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) this.geo[i] = await r.json();
            } catch (e) { /* no thumbnail for this address */ }
        }
        if (Object.keys(this.geo).length) await this.renderMinis();
    },
    async renderMinis() {
        const L = await loadLeaflet();
        await this.$nextTick();
        this.$root.querySelectorAll('[data-mini-map]').forEach((el) => {
            const g = this.geo[el.dataset.miniMap];
            if (! g || el._miniDone) return;
            el._miniDone = true;
            const m = L.map(el, {
                zoomControl: false, attributionControl: false, dragging: false,
                scrollWheelZoom: false, doubleClickZoom: false, boxZoom: false,
                keyboard: false, touchZoom: false,
            }).setView([g.lat, g.lon], 15);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(m);
            L.marker([g.lat, g.lon]).addTo(m);
            setTimeout(() => m.invalidateSize(), 50);
            this._minis.push(m);
        });
    },

    displayName() {
        const name = `${this.c.first_name || ''} ${this.c.last_name || ''}`.trim();
        return name || this.c.fn || '—';
    },
    initials() {
        const letters = (((this.c.first_name || '')[0] || '') + ((this.c.last_name || '')[0] || '')).toUpperCase();
        return letters || ((this.c.fn || '').trim()[0] || '').toUpperCase();
    },
    label(raw) {
        const t = (raw || '').toLowerCase();
        const token = (t.includes('cell') || t.includes('mobile')) ? 'cell'
            : t.includes('work') ? 'work' : t.includes('home') ? 'home' : (t ? 'other' : '');
        return token ? (cfg.labels[token] || '') : '';
    },
    relatedLabel(t) { return cfg.relatedTypes[(t || '').toLowerCase()] || (t || ''); },
    addressLines(a) {
        return [
            [a.street, a.ext].filter(Boolean).join(', '),
            [a.zip, a.city].filter(Boolean).join(' '),
            a.region,
            a.country,
        ].filter(Boolean).join('\n');
    },
    prettyDate(d) {
        const parsed = new Date(d);
        return isNaN(parsed) ? (d || '') : parsed.toLocaleDateString();
    },
    groupNames() {
        return (this.c.group_ids || []).map((id) => this.groups.find((g) => g.id === id)?.name).filter(Boolean);
    },

    async showMap(i) {
        this.mapModal = { open: true, loading: true, error: false, display: '', osmUrl: '' };
        this.destroyMap();
        try {
            const u = new URL(cfg.contactBase + '/' + cfg.contactId + '/geo', location.origin);
            u.searchParams.set('address', i);
            const r = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! r.ok) { this.mapModal.loading = false; this.mapModal.error = true; return; }
            const d = await r.json();
            this.mapModal.loading = false;
            this.mapModal.display = d.display;
            this.mapModal.osmUrl = `https://www.openstreetmap.org/?mlat=${d.lat}&mlon=${d.lon}#map=17/${d.lat}/${d.lon}`;
            const L = await loadLeaflet();
            await this.$nextTick();
            const el = this.$refs.contactMap;
            if (! el || ! this.mapModal.open) return;
            this._map = L.map(el).setView([d.lat, d.lon], 16);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this._map);
            L.marker([d.lat, d.lon]).addTo(this._map);
            setTimeout(() => this._map?.invalidateSize(), 50);
        } catch (e) {
            this.mapModal.loading = false;
            this.mapModal.error = true;
        }
    },
    closeMap() { this.mapModal.open = false; this.destroyMap(); },
    destroyMap() { if (this._map) { this._map.remove(); this._map = null; } },
}));

/**
 * Contact duplicate review: lists likely-duplicate groups, lets the user pick the
 * primary card per group, then merges (union of fields) or dismisses the group.
 */
Alpine.data('contactDuplicatesPage', (cfg = {}) => ({
    cfg,
    groups: [], primary: {}, loading: true,
    confirmModal: { open: false, onConfirm: null },

    init() { this.load(); },

    openConfirm(onConfirm) { this.confirmModal = { open: true, onConfirm }; },
    async doConfirm() { const cb = this.confirmModal.onConfirm; this.confirmModal.open = false; if (cb) await cb(); },

    async load() {
        try {
            const r = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! r.ok) return;
            this.groups = (await r.json()).groups ?? [];
            for (const g of this.groups) {
                if (this.primary[g.signature] == null && g.contacts.length) this.primary[g.signature] = g.contacts[0].id;
            }
        } catch (e) { /* keep current */ } finally { this.loading = false; }
    },

    merge(g) {
        const primaryId = this.primary[g.signature];
        if (! primaryId) return;
        this.openConfirm(async () => {
            this.groups = this.groups.filter((x) => x.signature !== g.signature); // optimistic
            try {
                await this._post(cfg.mergeUrl, { primary_id: primaryId, ids: g.contacts.map((c) => c.id) });
            } catch (e) { /* next load reconciles */ }
            this.load();
        });
    },

    async dismiss(g) {
        this.groups = this.groups.filter((x) => x.signature !== g.signature);
        try {
            await this._post(cfg.dismissUrl, { ids: g.contacts.map((c) => c.id) });
        } catch (e) { /* ignore */ }
    },

    _post(url, body) {
        return fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token }, body: JSON.stringify(body) });
    },
}));

/**
 * Calendar: month/week/day/agenda over the CalDAV-backed event store. Recurrence
 * is expanded server-side within the requested window; the client only lays out
 * the returned instances by day.
 */
Alpine.data('calendarPage', (cfg = {}) => ({
    ...shareMixin(cfg),
    cfg,
    calendars: [], events: [], loading: true,
    view: 'month', cursor: new Date(),
    hidden: new Set(),
    editor: false, form: {},
    locale: document.documentElement.lang || 'en',
    weekStart: 'monday', weekNumbers: false, defaultMinutes: 60,
    tzSetting: '', browserTz: 'UTC', tzMismatch: null,

    init() {
        this.weekStart = (document.querySelector('meta[name="calendar-week-start"]')?.content) || 'monday';
        this.weekNumbers = (document.querySelector('meta[name="calendar-week-numbers"]')?.content) === '1';
        this.defaultMinutes = parseInt(document.querySelector('meta[name="calendar-default-minutes"]')?.content, 10) || 60;
        this.tzSetting = (document.querySelector('meta[name="calendar-timezone"]')?.content) || '';
        try { this.browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'; } catch (e) { this.browserTz = 'UTC'; }
        // A pinned timezone that no longer matches this device → offer to switch.
        if (this.tzSetting && this.tzSetting !== this.browserTz) this.tzMismatch = this.browserTz;
        this.cursor.setHours(0, 0, 0, 0);
        this.load();
    },

    // The zone the calendar renders in: the pinned setting, else this browser.
    effectiveTz() { return this.tzSetting || this.browserTz; },

    async acceptTimezone() {
        const tz = this.tzMismatch; if (! tz) return;
        const r = await this._json(cfg.timezoneUrl, 'POST', { timezone: tz });
        if (r.ok) { this.tzSetting = tz; this.tzMismatch = null; this.load(); }
    },
    dismissTimezone() { this.tzMismatch = null; },

    // ---- range for the current view (week start per settings) ---------------
    startOfWeek(d) {
        const x = new Date(d);
        const wd = this.weekStart === 'sunday' ? x.getDay() : (x.getDay() + 6) % 7;
        x.setDate(x.getDate() - wd); x.setHours(0, 0, 0, 0); return x;
    },
    addDays(d, n) { const x = new Date(d); x.setDate(x.getDate() + n); return x; },
    ymd(d) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; },

    /** ISO-8601 week number (Thursday-of-week rule). */
    isoWeek(d) {
        const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
        const day = (t.getUTCDay() + 6) % 7;
        t.setUTCDate(t.getUTCDate() - day + 3);
        const firstThursday = new Date(Date.UTC(t.getUTCFullYear(), 0, 4));
        const fday = (firstThursday.getUTCDay() + 6) % 7;
        firstThursday.setUTCDate(firstThursday.getUTCDate() - fday + 3);
        return 1 + Math.round((t - firstThursday) / (7 * 24 * 3600 * 1000));
    },
    /** The 6 week-rows of the month grid, each with its week number. */
    monthWeeks() {
        const [start] = this.range();
        return Array.from({ length: 6 }, (_, w) => {
            const days = Array.from({ length: 7 }, (_, i) => this.addDays(start, w * 7 + i));
            return { week: this.isoWeek(days[0]), days };
        });
    },

    range() {
        const c = this.cursor;
        if (this.view === 'day') return [new Date(c), this.addDays(c, 1)];
        if (this.view === 'week') { const s = this.startOfWeek(c); return [s, this.addDays(s, 7)]; }
        // month + agenda: the 6-week grid around the visited month.
        const first = new Date(c.getFullYear(), c.getMonth(), 1);
        const gridStart = this.startOfWeek(first);
        return [gridStart, this.addDays(gridStart, 42)];
    },

    async load() {
        const [from, to] = this.range();
        const u = new URL(cfg.dataUrl, location.origin);
        u.searchParams.set('from', this.ymd(from));
        u.searchParams.set('to', this.ymd(to));
        u.searchParams.set('tz', this.effectiveTz());
        this.loading = true;
        try {
            const r = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) { const d = await r.json(); this.calendars = d.calendars; this.events = d.events; }
        } catch (e) { /* keep */ } finally { this.loading = false; }
    },

    // ---- navigation ---------------------------------------------------------
    step(n) {
        const c = this.cursor;
        if (this.view === 'day') this.cursor = this.addDays(c, n);
        else if (this.view === 'week') this.cursor = this.addDays(c, 7 * n);
        else this.cursor = new Date(c.getFullYear(), c.getMonth() + n, 1);
        this.load();
    },
    today() { this.cursor = new Date(); this.cursor.setHours(0, 0, 0, 0); this.load(); },
    setView(v) { this.view = v; this.load(); },

    // ---- layout helpers -----------------------------------------------------
    parse(s) { return s ? new Date(s.replace(' ', 'T')) : null; },
    visibleEvents() { return this.events.filter((e) => ! this.hidden.has(e.calendar_id)); },
    /** Events touching day d — multi-day spans appear on every covered day. */
    eventsOn(d) {
        const dayStart = new Date(d); dayStart.setHours(0, 0, 0, 0);
        const dayEnd = new Date(dayStart.getTime() + 86400000);
        return this.visibleEvents()
            .filter((e) => {
                const s = this.parse(e.start);
                if (! s) return false;
                // All-day DTEND is exclusive; timed events without an end are points.
                const en = e.end ? this.parse(e.end) : s;
                return s < dayEnd && (en > dayStart || (+en === +s && s >= dayStart));
            })
            .sort((a, b) => (a.all_day === b.all_day ? a.start.localeCompare(b.start) : (a.all_day ? -1 : 1)));
    },
    /** First visible day of the event (time label only shown there). */
    startsOn(e, d) { return this.ymd(this.parse(e.start)) === this.ymd(d); },
    agenda() {
        return this.visibleEvents().slice().sort((a, b) => a.start.localeCompare(b.start));
    },

    monthGrid() {
        const [start] = this.range();
        return Array.from({ length: 42 }, (_, i) => this.addDays(start, i));
    },
    weekDays() { const s = this.startOfWeek(this.cursor); return Array.from({ length: 7 }, (_, i) => this.addDays(s, i)); },

    isToday(d) { return this.ymd(d) === this.ymd(new Date()); },
    inMonth(d) { return d.getMonth() === this.cursor.getMonth(); },

    // ---- hour grid (week/day, Google-style) --------------------------------
    hourPx: 48,
    hours() { return Array.from({ length: 24 }, (_, h) => h); },
    fmtHour(h) { return String(h).padStart(2, '0') + ':00'; },
    timedEventsOn(d) { return this.eventsOn(d).filter((e) => ! e.all_day); },
    allDayOn(d) { return this.eventsOn(d).filter((e) => e.all_day); },
    /** Position within day d's hour grid, clipped to that day for multi-day spans. */
    eventStyle(e, d) {
        const s = this.parse(e.start);
        if (! s) return 'display:none';
        let end = e.end ? this.parse(e.end) : new Date(s.getTime() + this.defaultMinutes * 60000);
        if (! (end > s)) end = new Date(s.getTime() + 30 * 60000);
        const dayStart = new Date(d); dayStart.setHours(0, 0, 0, 0);
        const dayEnd = new Date(dayStart.getTime() + 86400000);
        const segS = s < dayStart ? dayStart : s;
        const segE = end > dayEnd ? dayEnd : end;
        const startMin = (segS - dayStart) / 60000;
        const top = (startMin / 60) * this.hourPx;
        const height = Math.max(((segE - segS) / 60000 / 60) * this.hourPx, 16);
        return `top:${top}px;height:${height}px`;
    },
    openNewAt(d, ev) {
        if (this._suppressClick) return; // tail end of a drag, not a click
        if (! this.calendars.some((c) => ! c.read_only)) return;
        const rect = ev.currentTarget.getBoundingClientRect();
        let min = Math.round(((ev.clientY - rect.top) / this.hourPx * 60) / 15) * 15;
        min = Math.max(0, Math.min(23 * 60 + 45, min));
        const start = new Date(d); start.setHours(0, min, 0, 0);
        const end = new Date(start.getTime() + this.defaultMinutes * 60000);
        const fmt = (x) => `${this.ymd(x)}T${String(x.getHours()).padStart(2, '0')}:${String(x.getMinutes()).padStart(2, '0')}`;
        const writable = this.calendars.find((c) => ! c.read_only);
        this.form = { id: null, calendar_id: writable?.id, summary: '', start: fmt(start), end: fmt(end), all_day: false, timezone: this.effectiveTz(), location: '', description: '', rrule: '', reminders: [], read_only: false };
        this.rec = this.parseRrule('');
        this.instanceCtx = null;
        this.editor = true;
    },

    // ---- drag & drop (move/resize; mouse + pen — touch keeps scrolling) ----
    drag: null, _suppressClick: false,

    draggable(e) {
        // Recurring instances are draggable too — the drop writes a
        // RECURRENCE-ID override for just that occurrence.
        const cal = this.calendars.find((c) => c.id === e.calendar_id);
        return !! cal && ! cal.read_only;
    },
    /** 'YYYY-MM-DD HH:MM:00' local wall-clock (matches the data feed format). */
    fmtLocal(d) {
        return `${this.ymd(d)} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:00`;
    },
    /** Open the editor unless the click is the tail end of a drag. */
    evClick(e) { if (! this._suppressClick) this.openEditor(e.id ?? e, e.id ? e : null); },

    /** Week/day hour grids: drag body = move (week: across days too), drag the
        bottom edge = resize. Snaps to 15 minutes. */
    dragStart(ev, e) {
        if (ev.pointerType === 'touch' || ev.button !== 0 || ! this.draggable(e)) return;
        const el = ev.currentTarget;
        const rect = el.getBoundingClientRect();
        const start = this.parse(e.start);
        this.drag = {
            e, mode: ev.clientY > rect.bottom - 8 ? 'resize' : 'move',
            startX: ev.clientX, startY: ev.clientY,
            colWidth: el.parentElement.getBoundingClientRect().width,
            week: this.view === 'week',
            origStart: start,
            origEnd: e.end ? this.parse(e.end) : new Date(start.getTime() + this.defaultMinutes * 60000),
            moved: false,
        };
        const move = (m) => this.dragMove(m);
        const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); this.dragEnd(); };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    },
    dragMove(ev) {
        const d = this.drag; if (! d) return;
        const dy = ev.clientY - d.startY;
        const dx = ev.clientX - d.startX;
        if (! d.moved && Math.abs(dy) < 4 && Math.abs(dx) < 4) return;
        d.moved = true;
        const stepMin = Math.round((dy / this.hourPx * 60) / 15) * 15;
        const dayShift = d.week ? Math.round(dx / d.colWidth) : 0;
        if (d.mode === 'move') {
            const dur = d.origEnd - d.origStart;
            const ns = new Date(d.origStart.getTime() + stepMin * 60000 + dayShift * 86400000);
            d.e.start = this.fmtLocal(ns);
            d.e.end = this.fmtLocal(new Date(ns.getTime() + dur));
        } else {
            let ne = new Date(d.origEnd.getTime() + stepMin * 60000);
            if (ne - d.origStart < 15 * 60000) ne = new Date(d.origStart.getTime() + 15 * 60000);
            d.e.end = this.fmtLocal(ne);
        }
    },
    async dragEnd() {
        const d = this.drag; this.drag = null;
        if (! d || ! d.moved) return; // plain click -> evClick opens the editor
        this._suppressClick = true;
        setTimeout(() => { this._suppressClick = false; }, 50);
        try {
            await this._json(cfg.eventBase + '/' + d.e.id + '/move', 'PATCH', {
                start: d.e.start, end: d.e.end,
                recurrence_id: d.e.recurring ? d.e.recurrence_id : null,
            });
        } finally { this.load(); } // reload reconciles (or reverts on failure)
    },

    /** Month grid: drag a chip onto another day cell; time and duration are
        kept, only the date shifts. */
    monthDragStart(ev, e) {
        if (ev.pointerType === 'touch' || ev.button !== 0 || ! this.draggable(e)) return;
        const d = { e, startX: ev.clientX, startY: ev.clientY, moved: false };
        const move = (m) => {
            if (! d.moved && (Math.abs(m.clientX - d.startX) > 4 || Math.abs(m.clientY - d.startY) > 4)) {
                d.moved = true;
                document.body.style.cursor = 'grabbing';
            }
        };
        const up = async (u) => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            document.body.style.cursor = '';
            if (! d.moved) return;
            this._suppressClick = true;
            setTimeout(() => { this._suppressClick = false; }, 50);
            const cell = document.elementFromPoint(u.clientX, u.clientY)?.closest('[data-date]');
            if (! cell) return;
            const from = this.ymd(this.parse(d.e.start));
            const to = cell.dataset.date;
            if (from === to) return;
            const shift = this.parse(to) - this.parse(from);
            const ns = new Date(this.parse(d.e.start).getTime() + shift);
            const ne = d.e.end ? new Date(this.parse(d.e.end).getTime() + shift) : null;
            const fmt = (x) => (d.e.all_day ? this.ymd(x) : this.fmtLocal(x));
            try {
                await this._json(cfg.eventBase + '/' + d.e.id + '/move', 'PATCH', {
                    start: fmt(ns), end: ne ? fmt(ne) : null,
                    recurrence_id: d.e.recurring ? d.e.recurrence_id : null,
                });
            } finally { this.load(); }
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    },

    // ---- formatting (locale-aware) -----------------------------------------
    fmtTime(s) { const d = this.parse(s); return d ? d.toLocaleTimeString(this.locale, { hour: '2-digit', minute: '2-digit' }) : ''; },
    fmtDay(d) { return d.toLocaleDateString(this.locale, { weekday: 'short', day: 'numeric' }); },
    fmtWeekday(d) { return d.toLocaleDateString(this.locale, { weekday: 'short' }); },
    fmtFullDate(d) { return d.toLocaleDateString(this.locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); },
    get title() {
        if (this.view === 'day') return this.fmtFullDate(this.cursor);
        if (this.view === 'week') { const s = this.startOfWeek(this.cursor); const e = this.addDays(s, 6); return s.toLocaleDateString(this.locale, { day: 'numeric', month: 'short' }) + ' – ' + e.toLocaleDateString(this.locale, { day: 'numeric', month: 'short', year: 'numeric' }); }
        return this.cursor.toLocaleDateString(this.locale, { month: 'long', year: 'numeric' });
    },

    toggleCalendar(id) { this.hidden.has(id) ? this.hidden.delete(id) : this.hidden.add(id); },
    calColor(id) { return this.calendars.find((c) => c.id === id)?.color || '#3366cc'; },

    // ---- event editor -------------------------------------------------------
    blank(d) {
        const writable = this.calendars.find((c) => ! c.read_only);
        const day = d || this.cursor;
        const startAt = new Date(day); startAt.setHours(9, 0, 0, 0);
        const endAt = new Date(startAt.getTime() + this.defaultMinutes * 60000);
        const fmt = (x) => `${this.ymd(x)}T${String(x.getHours()).padStart(2, '0')}:${String(x.getMinutes()).padStart(2, '0')}`;
        return { id: null, calendar_id: writable?.id, summary: '', start: fmt(startAt), end: fmt(endAt), all_day: false, timezone: this.effectiveTz(), location: '', description: '', rrule: '', reminders: [], read_only: false };
    },

    openNew(d) {
        if (this._suppressClick) return; // tail end of a drag, not a click
        if (! this.calendars.some((c) => ! c.read_only)) return;
        this.form = this.blank(d);
        this.rec = this.parseRrule('');
        this.instanceCtx = null;
        this.editor = true;
    },

    /** @param instance the clicked feed instance (recurring context) or null */
    async openEditor(id, instance = null) {
        const r = await fetch(cfg.eventBase + '/' + id, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (! r.ok) return;
        const d = await r.json();
        const recurring = !! d.rrule;
        // For a series the time fields show the CLICKED instance; "whole
        // series" saves apply the delta to the master start.
        const fmtIn = (v) => (v ? (d.all_day ? v.slice(0, 10) : v.slice(0, 16).replace(' ', 'T')) : '');
        this.instanceCtx = recurring && instance ? {
            recurrence_id: instance.recurrence_id,
            start: instance.start, end: instance.end,
            masterStart: d.start || '', masterEnd: d.end || '',
        } : null;
        this.form = {
            id: d.id, calendar_id: d.calendar_id, summary: d.summary || '',
            start: this.instanceCtx ? fmtIn(instance.start) : (d.start || ''),
            end: this.instanceCtx ? fmtIn(instance.end) : (d.end || ''),
            all_day: !! d.all_day,
            timezone: d.timezone || this.effectiveTz(),
            location: d.location || '', description: d.description || '', rrule: d.rrule || '',
            reminders: (d.reminders || []).map(String),
            // Birthday/anniversary/holiday calendars are read-only — open such an
            // event for viewing only (no save/delete, calendar shown as text).
            read_only: !! this.calendars.find((c) => c.id === d.calendar_id)?.read_only,
        };
        this.rec = this.parseRrule(this.form.rrule);
        this.editor = true;
    },

    calName(id) { return this.calendars.find((c) => c.id === id)?.name || ''; },
    // Own, editable calendars get management buttons; generated + shared ones
    // (read-only or not owned) go under "Other calendars".
    ownCalendars() { return this.calendars.filter((c) => c.owned && ! c.read_only); },
    otherCalendars() { return this.calendars.filter((c) => ! (c.owned && ! c.read_only)); },

    payload() {
        return {
            calendar_id: this.form.calendar_id, summary: this.form.summary,
            start: this.form.all_day ? this.form.start.slice(0, 10) : this.form.start,
            end: this.form.all_day ? (this.form.end || this.form.start).slice(0, 10) : this.form.end,
            all_day: this.form.all_day, location: this.form.location, description: this.form.description,
            timezone: this.form.all_day ? null : (this.form.timezone || null),
            rrule: this.buildRrule(this.rec) || null,
            reminders: this.form.reminders.filter((m) => m !== '').map(Number),
        };
    },

    // ---- recurrence builder (structured RRULE) ------------------------------
    rec: { freq: '', interval: 1, byday: [], monthlyMode: 'bymonthday', ends: 'never', until: '', count: 5, custom: null },
    instanceCtx: null,
    scopeModal: { open: false, deleting: false },

    /** Parse an RRULE into builder state; unsupported parts become "custom". */
    parseRrule(rrule) {
        const base = { freq: '', interval: 1, byday: [], monthlyMode: 'bymonthday', ends: 'never', until: '', count: 5, custom: null };
        if (! rrule) return base;
        const parts = {};
        for (const kv of rrule.toUpperCase().split(';')) {
            const [k, v] = kv.split('=');
            if (k) parts[k] = v || '';
        }
        const known = ['FREQ', 'INTERVAL', 'BYDAY', 'UNTIL', 'COUNT'];
        if (! parts.FREQ || Object.keys(parts).some((k) => ! known.includes(k))
            || ! ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'].includes(parts.FREQ)
            || (parts.BYDAY && parts.FREQ === 'MONTHLY' && ! /^(-?[1-4])(MO|TU|WE|TH|FR|SA|SU)$/.test(parts.BYDAY))) {
            return { ...base, custom: rrule }; // keep verbatim, edit disabled
        }
        const out = { ...base, freq: parts.FREQ, interval: Math.max(1, parseInt(parts.INTERVAL || '1', 10) || 1) };
        if (parts.BYDAY && parts.FREQ === 'WEEKLY') out.byday = parts.BYDAY.split(',').filter(Boolean);
        if (parts.BYDAY && parts.FREQ === 'MONTHLY') { out.monthlyMode = 'byday'; out.byday = [parts.BYDAY]; }
        if (parts.UNTIL) { out.ends = 'until'; out.until = parts.UNTIL.slice(0, 8).replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3'); }
        else if (parts.COUNT) { out.ends = 'count'; out.count = Math.max(1, parseInt(parts.COUNT, 10) || 1); }
        return out;
    },
    buildRrule(rec) {
        if (rec.custom) return rec.custom;
        if (! rec.freq) return '';
        const parts = ['FREQ=' + rec.freq];
        if (rec.interval > 1) parts.push('INTERVAL=' + rec.interval);
        if (rec.freq === 'WEEKLY' && rec.byday.length) parts.push('BYDAY=' + rec.byday.join(','));
        if (rec.freq === 'MONTHLY' && rec.monthlyMode === 'byday') parts.push('BYDAY=' + this.monthlyByday());
        if (rec.ends === 'until' && rec.until) parts.push('UNTIL=' + rec.until.replaceAll('-', '') + 'T235959Z');
        if (rec.ends === 'count') parts.push('COUNT=' + Math.max(1, rec.count));
        return parts.join(';');
    },
    /** "2TU"-style token for the start date (its weekday + which one of the month). */
    monthlyByday() {
        const d = this.parse(this.form.start) || new Date();
        const codes = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
        return Math.ceil(d.getDate() / 7) + codes[d.getDay()];
    },
    weekdayOptions() {
        const codes = this.weekStart === 'sunday'
            ? ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'] : ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
        const ref = this.startOfWeek(new Date());
        return codes.map((code, i) => ({ code, label: this.addDays(ref, i).toLocaleDateString(this.locale, { weekday: 'short' }) }));
    },
    toggleByday(code) {
        const i = this.rec.byday.indexOf(code);
        i >= 0 ? this.rec.byday.splice(i, 1) : this.rec.byday.push(code);
    },

    async save() {
        if (! this.form.summary || ! this.form.calendar_id) return;
        // Editing an instance of a series → ask for the scope first.
        if (this.form.id && this.instanceCtx) { this.scopeModal = { open: true, deleting: false }; return; }
        await this.saveSeries();
    },
    async saveSeries() {
        const id = this.form.id;
        const p = this.payload();
        if (id && this.instanceCtx) {
            // "Whole series": apply the instance-field delta to the master start
            // so shifting one occurrence's time shifts the pattern, like Google.
            const dStart = this.parse(this.form.start) - this.parse(this.instanceCtx.start.slice(0, 16).replace(' ', 'T'));
            const dur = this.form.end ? this.parse(this.form.end) - this.parse(this.form.start) : null;
            const ms = this.parse(this.instanceCtx.masterStart);
            const ns = new Date(ms.getTime() + dStart);
            const fmt = (x) => this.form.all_day ? this.ymd(x) : `${this.ymd(x)}T${String(x.getHours()).padStart(2, '0')}:${String(x.getMinutes()).padStart(2, '0')}`;
            p.start = fmt(ns);
            p.end = dur !== null ? fmt(new Date(ns.getTime() + dur)) : null;
        }
        await this._json(id ? cfg.eventBase + '/' + id : cfg.eventsUrl, id ? 'PUT' : 'POST', p);
        this.editor = false; this.scopeModal.open = false; this.load();
    },
    async saveInstance() {
        const p = this.payload();
        await this._json(cfg.eventBase + '/' + this.form.id + '/instance', 'PUT', {
            recurrence_id: this.instanceCtx.recurrence_id,
            summary: p.summary, start: p.start, end: p.end,
            location: p.location, description: p.description,
        });
        this.editor = false; this.scopeModal.open = false; this.load();
    },

    destroy() {
        if (! this.form.id) return;
        if (this.instanceCtx) { this.scopeModal = { open: true, deleting: true }; return; }
        const id = this.form.id;
        this.openConfirm(cfg.confirmDelete, async () => {
            await this._json(cfg.eventBase + '/' + id, 'DELETE');
            this.editor = false; this.load();
        });
    },
    async destroySeries() {
        await this._json(cfg.eventBase + '/' + this.form.id, 'DELETE');
        this.editor = false; this.scopeModal.open = false; this.load();
    },
    async destroyInstance() {
        await this._json(cfg.eventBase + '/' + this.form.id + '/instance', 'DELETE', {
            recurrence_id: this.instanceCtx.recurrence_id,
        });
        this.editor = false; this.scopeModal.open = false; this.load();
    },

    async importFile(ev) {
        const f = ev.target.files[0]; if (! f) return;
        const target = this.calendars.find((c) => ! c.read_only);
        if (! target) { ev.target.value = ''; return; }
        const fd = new FormData(); fd.append('file', f); fd.append('calendar_id', target.id);
        await fetch(cfg.importUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token }, body: fd });
        this.load(); ev.target.value = '';
    },

    // ---- modals (replace window.prompt/confirm) ----------------------------
    calModal: { open: false, id: null, name: '', color: '#3366cc', readOnly: false },
    linkModal: { open: false, mode: 'import', url: '', name: '', error: '' },
    confirmModal: { open: false, message: '', onConfirm: null },
    palette: ['#3366cc', '#e11d48', '#059669', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#4b5563'],

    openConfirm(message, onConfirm) { this.confirmModal = { open: true, message, onConfirm }; },
    async doConfirm() { const cb = this.confirmModal.onConfirm; this.confirmModal.open = false; if (cb) await cb(); },

    addCalendar() { this.calModal = { open: true, id: null, name: '', color: this.randomColor(), readOnly: false }; },
    editCalendar(c) { this.calModal = { open: true, id: c.id, name: c.name, color: c.color || '#3366cc', readOnly: !! c.read_only }; },
    async saveCalModal() {
        const m = this.calModal;
        if (! m.id && ! m.name.trim()) return;
        this.calModal.open = false;
        if (m.id) {
            await this._json(cfg.calBase + '/' + m.id, 'PUT', { name: m.name, color: m.color });
        } else {
            await this._json(cfg.calUrl, 'POST', { name: m.name, color: m.color });
        }
        this.load();
    },
    deleteCalendar(c) {
        this.openConfirm(cfg.confirmDeleteCalendar, async () => { await this._json(cfg.calBase + '/' + c.id, 'DELETE'); this.load(); });
    },

    importFromUrl() { this.linkModal = { open: true, mode: 'import', url: '', name: '', error: '' }; },
    subscribe() { this.linkModal = { open: true, mode: 'subscribe', url: '', name: '', error: '' }; },
    async saveLinkModal() {
        const m = this.linkModal;
        if (! m.url.trim()) return;
        let r;
        if (m.mode === 'import') {
            const target = this.calendars.find((c) => ! c.read_only); if (! target) return;
            r = await this._json(cfg.importFromUrl, 'POST', { url: m.url, calendar_id: target.id });
        } else {
            if (! m.name.trim()) { this.linkModal.error = cfg.subscribeNamePrompt; return; }
            r = await this._json(cfg.subscribeUrl, 'POST', { url: m.url, name: m.name, color: this.randomColor() });
        }
        if (! r.ok) { this.linkModal.error = cfg.feedFailed; return; }
        this.linkModal.open = false; this.load();
    },
    randomColor() { const p = this.palette; return p[Math.floor(Math.random() * p.length)]; },

    async _json(url, method, body) {
        return apiJson(url, method, body, cfg.token);
    },
}));

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
    nameOpen: false, nameSuggest: [],
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

    // Contact-name autocomplete for the person's name.
    async suggestNames() {
        const q = (this.person.name || '').trim();
        this.nameOpen = true;
        try {
            const r = await fetch(cfg.suggestUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this.nameSuggest = (await r.json()).contacts ?? [];
        } catch (e) { /* keep */ }
    },
    pickContactName(c) { this.person.name = c.name; this.nameOpen = false; this.save(); },

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
        this.groups = this.groups.filter((x) => x.group !== g.group); // optimistic
        try {
            await fetch(cfg.resolveBase.replace('__G__', g.group), {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token },
                body: JSON.stringify({ keep_id: keepId }),
            });
        } catch (e) { /* next load reconciles */ }
    },

    async dismiss(g) {
        this.groups = this.groups.filter((x) => x.group !== g.group);
        try {
            await fetch(cfg.dismissBase.replace('__G__', g.group), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token },
            });
        } catch (e) { /* ignore */ }
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
    hoverMotion(el, enter) {
        // Hover-to-play only makes sense with a real pointer; on touch devices
        // mouseenter fires on tap and would clash with opening the viewer.
        if (! window.matchMedia('(pointer: fine)').matches) return;
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
    manifest: { v: 1, folders: [], files: [] },
    version: 0,
    cwd: null,
    query: '',
    sortDir: 'asc',
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
    infoOpen: false,
    infoRow: null,
    migrateOpen: false,
    migrateRow: null,
    migrateDelete: true,
    migrateBusy: false,
    dragItem: null, // {kind, id} being dragged into a folder
    up: { active: false, done: 0, total: 0 },
    uploadBatches: 0, // concurrent uploadItems() runs still in flight
    dl: { active: false, done: 0, total: 0 },
    error: '',
    dragging: false,
    viewer: { open: false, kind: 'none', src: '', row: null, saving: false, saved: false },
    editorView: null,
    editorLang: '',
    langComp: null,
    languageOptions: [], // populated when the editor (CodeMirror) is loaded on first open

    async init() {
        window.addEventListener('paperless-sent', (e) => this.onPaperlessSent(e.detail));
        this.initDropzone();
        await this.load();
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

    walkEntry(entry, prefix, out) {
        return new Promise((resolve) => {
            if (entry.isFile) {
                entry.file((f) => { out.push({ file: f, path: prefix + f.name }); resolve(); }, () => resolve());
                return;
            }
            const reader = entry.createReader();
            const readBatch = () => reader.readEntries(async (batch) => {
                if (! batch.length) { resolve(); return; }
                for (const child of batch) {
                    await this.walkEntry(child, prefix + entry.name + '/', out);
                }
                readBatch(); // readEntries yields in chunks; keep going until empty
            }, () => resolve());
            readBatch();
        });
    },

    async load() {
        try {
            const res = await fetch(config.dataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) { this.state = 'error'; return; }
            const data = await res.json();
            this.manifest = data.files ? data : { v: 1, folders: [], files: [] };
            this.usage = data.usage || { used: 0, quota: 0 };
            this.state = 'ready';
        } catch (e) {
            this.state = 'error';
        }
    },

    usage: { used: 0, quota: 0 },
    versions: { open: false, row: null, list: [], loading: false },

    async openVersions(row) {
        this.versions = { open: true, row, list: [], loading: true };
        try {
            const res = await fetch(config.versionsBase + '/' + row.id + '/versions', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) this.versions.list = (await res.json()).versions || [];
        } catch (e) { /* keep empty */ }
        this.versions.loading = false;
    },
    versionDownloadUrl(versionId) {
        return config.versionsBase + '/' + this.versions.row.id + '/versions/' + versionId + '/download';
    },
    // Restore a version by pointing the file's manifest row back at the version's
    // blob and re-syncing. The client stays authoritative; the sync then snapshots
    // the pre-restore blob as a new version automatically.
    async restoreVersion(v) {
        if (! await this.$store.confirm.ask(labels.restoreConfirm || '')) return;
        const row = this.manifest.files.find((f) => f.id === this.versions.row.id);
        if (! row) return;
        row.blob = v.blob;
        row.size = v.size;
        row.mime = v.mime;
        await this.persist();
        this.versions.open = false;
        await this.load();
    },

    // Persist the whole tree; the server syncs it to clean rows.
    async persist() {
        try {
            const res = await fetch(config.dataUrl, {
                method: 'PUT',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ folders: this.manifest.folders, files: this.manifest.files }),
            });
            if (! res.ok) throw new Error('save failed');
            this.error = '';
        } catch (e) {
            this.error = labels.saveFailed;
            throw e;
        }
    },

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

    get rows() {
        const q = this.query.trim().toLowerCase();
        const tag = this.activeTag;
        const factor = this.sortDir === 'desc' ? -1 : 1;
        const cmp = (a, b) => factor * a.name.localeCompare(b.name, undefined, { sensitivity: 'base', numeric: true });

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
        this.infoOpen = true;
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

    // Create a note from a title + content via the (plain) notes API.
    async migrateAddNote(note) {
        try {
            const res = await fetch('/notes', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify(note),
            });
            return res.ok;
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
                const blobId = row.blob;
                this.manifest.files = this.manifest.files.filter((x) => x.id !== row.id);
                try {
                    await this.persist();
                    fetch(`${config.blobBase}/${blobId}`, {
                        method: 'DELETE',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                    }).catch(() => {});
                } catch (e) { /* note already created; keep the file on save failure */ }
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
        await this.persist().catch(() => {});
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
            await this.persist().catch(() => {});
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
        await this.persist().catch(() => {});
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
            if (f && (f.parent ?? null) !== targetFolderId) { f.parent = targetFolderId; await this.persist().catch(() => {}); }
        } else {
            const f = this.manifest.files.find((x) => x.id === item.id);
            if (f && (f.folder ?? null) !== targetFolderId) { f.folder = targetFolderId; await this.persist().catch(() => {}); }
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
            await this.persist().catch(() => {});
        }
    },

    confirmDelete(row) {
        this.deleteRefs = row ? [{ kind: row.kind, id: row.id, name: row.name }] : this.selectionRefs;
        this.deleteOpen = this.deleteRefs.length > 0;
    },

    async applyDelete() {
        const refs = this.deleteRefs;
        this.deleteOpen = false;
        this.deleteRefs = [];
        if (! refs.length) return;

        const blobIds = [];
        const doomedFolders = new Set();
        for (const ref of refs) {
            if (ref.kind === 'file') {
                const f = this.manifest.files.find((x) => x.id === ref.id);
                if (f) blobIds.push(f.blob);
                this.manifest.files = this.manifest.files.filter((x) => x.id !== ref.id);
            } else {
                for (const id of this.subtree(ref.id)) doomedFolders.add(id);
            }
        }
        if (doomedFolders.size) {
            for (const f of this.manifest.files) {
                if (f.folder != null && doomedFolders.has(f.folder)) blobIds.push(f.blob);
            }
            this.manifest.files = this.manifest.files.filter((f) => ! (f.folder != null && doomedFolders.has(f.folder)));
            this.manifest.folders = this.manifest.folders.filter((f) => ! doomedFolders.has(f.id));
        }

        this.selected = [];
        try {
            await this.persist();
        } catch (e) {
            return; // manifest not saved: keep the blobs
        }
        // Blobs are orphaned once the manifest no longer references them.
        for (const id of blobIds) {
            fetch(`${config.blobBase}/${id}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
            }).catch(() => {});
        }
    },

    // Queue an async export of the selection (files + folders). A worker builds
    // the zip(s) server-side (folder structure preserved) and it appears under
    // Downloads — no in-browser zipping or memory pressure.
    async bulkDownload(format = 'zip') {
        const refs = this.selectionRefs;
        if (! refs.length) return;

        const file_ids = refs.filter((r) => r.kind === 'file').map((r) => r.id);
        const folder_ids = refs.filter((r) => r.kind !== 'file').map((r) => r.id);
        if (! file_ids.length && ! folder_ids.length) return;

        try {
            const res = await fetch(labels.exportUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ file_ids, folder_ids, format }),
            });
            if (res.ok) { window.llToast(labels.exportQueued, labels.downloadsUrl); }
            else { const body = await res.json().catch(() => ({})); if (body.message) window.llToast(body.message); }
        } catch (e) { /* user can retry */ }
        this.selected = [];
    },

    /* ---- Content operations ---- */

    upload(fileList) {
        return this.uploadItems([...fileList].map((f) => ({ file: f, path: f.name })));
    },

    // Upload files (optionally with relative paths from a dropped folder),
    // recreating the folder chain in the manifest under the current folder.
    // Existing sibling folders are reused by name so re-drops don't duplicate.
    async uploadItems(items) {
        if (! items.length) return;
        // Accumulate across concurrent drops: a new batch dropped while another
        // is still uploading adds to the running total instead of resetting it.
        // The counter starts fresh only when nothing is in flight.
        if (this.uploadBatches === 0) {
            this.up = { active: true, done: 0, total: 0 };
        }
        this.up.total += items.length;
        this.up.active = true;
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

        for (const item of items) {
            try {
                const data = new FormData();
                data.append('_token', config.token);
                data.append('file', item.file, item.file.name);
                const res = await fetch(config.uploadUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                });
                if (res.status === 413) { this.error = labels.quotaExceeded || labels.uploadFailed; break; }
                if (! res.ok) throw new Error('upload failed');
                const { id } = await res.json();

                this.manifest.files.push({
                    id: crypto.randomUUID(),
                    blob: id,
                    name: item.file.name,
                    mime: item.file.type || 'application/octet-stream',
                    size: item.file.size,
                    folder: folderFor(item.path),
                    created: new Date().toISOString(),
                });
            } catch (e) {
                this.error = labels.uploadFailed;
            }
            this.up.done++;
        }

        // Only hide the indicator once every concurrent batch has finished.
        this.uploadBatches--;
        if (this.uploadBatches === 0) this.up.active = false;
        await this.persist().catch(() => {});
    },

    async fetchPlain(row) {
        const res = await fetch(`${config.rawBase}/${row.blob}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (! res.ok) throw new Error('fetch failed');
        return new Uint8Array(await res.arrayBuffer());
    },

    async download(row) {
        this.dl = { active: true, done: 0, total: 1 };
        try {
            saveBlobAs(await this.fetchPlain(row), row.name, row.mime);
        } catch (e) {
            this.error = labels.downloadFailed;
        }
        this.dl.active = false;
    },

    isPdf(row) {
        return row?.kind === 'file' && (row.mime === 'application/pdf' || /\.pdf$/i.test(row.name || ''));
    },

    // Open the Paperless modal immediately, then decrypt the PDF in the
    // background so the dialog never blocks. allowDelete lets the modal offer
    // to remove the stored file after upload.
    async openPaperless(row) {
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
    async onPaperlessSent(detail) {
        const ctx = detail?.context;
        if (! detail?.deleteAfter || ctx?.source !== 'files') return;
        const row = this.manifest.files.find((x) => x.id === ctx.rowId);
        if (! row) return;
        // If the deleted file is the one open in the viewer, close it.
        if (this.viewer.open && this.viewer.row?.id === row.id) this.closeViewer();
        this.manifest.files = this.manifest.files.filter((x) => x.id !== row.id);
        try {
            await this.persist();
            fetch(`${config.blobBase}/${ctx.blob}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
            }).catch(() => {});
        } catch (e) { /* keep the file on save failure */ }
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

            const data = new FormData();
            data.append('_token', config.token);
            data.append('file', new Blob([bytes], { type: row.mime || 'text/plain' }), row.name || 'file.txt');
            const res = await fetch(config.uploadUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            if (! res.ok) throw new Error('upload failed');
            const { id } = await res.json();

            const entry = this.manifest.files.find((f) => f.id === row.id);
            const oldBlob = entry?.blob;
            if (entry) {
                entry.blob = id;
                entry.size = bytes.length;
            }
            await this.persist();

            row.blob = id;
            row.size = bytes.length;
            if (oldBlob && oldBlob !== id) {
                fetch(`${config.blobBase}/${oldBlob}`, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                }).catch(() => {});
            }
            this.viewer.saved = true;
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
 * To-do lists + tasks, driven entirely client-side over the JSON API (no page
 * reloads). Reminders are handled server-side on save.
 */
Alpine.data('todos', (labels = {}) => ({
    state: 'boot', // boot | ready | error
    lists: [],
    tasks: [],
    view: 'all', // all | marked | trash | a list id
    query: '',
    activeTag: '',
    error: '',
    newListName: '',
    editorOpen: false,
    editing: null,
    tagsValue: '',

    async init() { await this.load(); },

    async load() {
        try {
            const res = await fetch('/todos/data', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) { this.state = 'error'; return; }
            const d = await res.json();
            this.lists = d.lists ?? [];
            this.tasks = d.tasks ?? [];
            this.state = 'ready';
        } catch (e) { this.state = 'error'; }
    },

    _headers() {
        return jsonHeaders();
    },
    async _api(method, url, body) {
        return apiRequest(method, url, body);
    },
    _payload(t) {
        return {
            todo_list_id: t.listId ?? null, title: t.title, description: t.description, url: t.url,
            priority: t.priority, marked: !! t.marked, tags: t.tags ?? [],
            due: t.due || null, reminder_channels: t.reminderChannels ?? [], done: !! t.done,
        };
    },
    _replace(task) {
        const i = this.tasks.findIndex((x) => x.id === task.id);
        if (i >= 0) this.tasks[i] = task; else this.tasks.unshift(task);
    },

    listName(id) { return (this.lists.find((l) => l.id === id) || {}).name || ''; },

    async addList() {
        const name = this.newListName.trim();
        if (! name) return;
        try {
            const l = await this._api('POST', '/todos/lists', { name });
            this.lists.push({ id: l.id, name: l.name });
            this.lists.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            this.newListName = '';
        } catch (e) { this.error = labels.saveFailed; }
    },
    async renameList(l) {
        const name = (prompt(labels.renameList, l.name) || '').trim();
        if (! name || name === l.name) return;
        try { await this._api('PUT', `/todos/lists/${l.id}`, { name }); l.name = name; this.lists.sort((a, b) => (a.name || '').localeCompare(b.name || '')); }
        catch (e) { this.error = labels.saveFailed; }
    },
    async deleteList(l) {
        if (! confirm(labels.deleteListConfirm)) return;
        try {
            await this._api('DELETE', `/todos/lists/${l.id}`);
            this.lists = this.lists.filter((x) => x.id !== l.id);
            this.tasks.forEach((t) => { if (t.listId === l.id) t.listId = null; });
            if (this.view === l.id) this.view = 'all';
        } catch (e) { this.error = labels.saveFailed; }
    },

    get allTags() {
        const set = new Set();
        for (const t of this.tasks) for (const g of t.tags ?? []) set.add(g);
        return [...set].sort((a, b) => a.localeCompare(b));
    },
    get trashCount() { return this.tasks.filter((t) => t.trashed).length; },

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
        this.editing = { id: null, listId, title: '', description: '', url: '', priority: 'normal', marked: false, tags: [], due: '', done: false, reminderChannels: [...(labels.defaultReminderChannels ?? [])] };
        this.tagsValue = '';
        this.editorOpen = true;
    },
    editTask(t) {
        this.editing = { ...t, tags: [...(t.tags ?? [])], reminderChannels: [...(t.reminderChannels ?? [])] };
        this.tagsValue = (this.editing.tags || []).join(', ');
        this.editorOpen = true;
    },
    closeEditor() { this.editorOpen = false; this.editing = null; },

    async saveTask() {
        const e = this.editing;
        if (! e || ! (e.title || '').trim()) return;
        e.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        try {
            const task = e.id
                ? await this._api('PUT', `/todos/tasks/${e.id}`, this._payload(e))
                : await this._api('POST', '/todos/tasks', this._payload(e));
            this._replace(task);
            this.closeEditor();
        } catch (err) { this.error = labels.saveFailed; }
    },

    async _patch(t, changes) {
        try { this._replace(await this._api('PATCH', `/todos/tasks/${t.id}`, changes)); }
        catch (e) { this.error = labels.saveFailed; }
    },
    async toggleDone(t) { await this._patch(t, { done: ! t.done }); },
    async toggleMark(t) { await this._patch(t, { marked: ! t.marked }); },
    async trashTask(t) { await this._patch(t, { trashed: true }); },
    async restoreTask(t) { await this._patch(t, { trashed: false }); },
    async deleteForever(t) {
        if (! confirm(labels.deleteConfirm)) return;
        try { await this._api('DELETE', `/todos/tasks/${t.id}`); this.tasks = this.tasks.filter((x) => x.id !== t.id); }
        catch (e) { this.error = labels.saveFailed; }
    },
    async emptyTrash() {
        if (! confirm(labels.emptyTrashConfirm)) return;
        try { await this._api('DELETE', '/todos/trash'); this.tasks = this.tasks.filter((t) => ! t.trashed); }
        catch (e) { this.error = labels.saveFailed; }
    },

    priorityClass(p) { return p === 'high' ? 'bg-red-500' : (p === 'low' ? 'bg-gray-300' : 'bg-amber-400'); },
    dueLabel(t) { if (! t.due) return ''; try { return new Date(t.due).toLocaleString(); } catch (e) { return t.due; } },
    isOverdue(t) { return t.due && ! t.done && new Date(t.due).getTime() < Date.now(); },
}));

/**
 * Notes: client-side over a JSON API (no reloads). Markdown is rendered on the
 * server (security-sensitive sanitising) via /notes/preview; share creation is
 * server-side too. Everything else happens in the browser.
 */
Alpine.data('notes', (labels = {}) => ({
    state: 'boot',
    notes: [],
    currentId: null,
    query: '',
    activeTag: '',
    view: 'active', // active | trash
    error: '',
    tagsValue: '',
    previewHtml: '',
    previewTimer: null,
    shareOpen: false,
    shareUrl: '',
    shareBusy: false,

    async init() { await this.load(); },

    async load() {
        try {
            const res = await fetch('/notes/data', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) { this.state = 'error'; return; }
            this.notes = (await res.json()).notes ?? [];
            this.state = 'ready';
        } catch (e) { this.state = 'error'; }
    },

    _headers() {
        return jsonHeaders();
    },
    async _api(method, url, body) {
        return apiRequest(method, url, body);
    },

    get allTags() {
        const set = new Set();
        for (const n of this.notes) for (const t of n.tags ?? []) set.add(t);
        return [...set].sort((a, b) => a.localeCompare(b));
    },
    get trashCount() { return this.notes.filter((n) => n.trashed).length; },
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
        this.shareOpen = false; this.shareUrl = '';
        await this.refreshPreview();
    },

    async newNote() {
        try {
            const note = await this._api('POST', '/notes', { title: '', content: '', tags: [] });
            this.notes.unshift(note);
            await this.open(note);
        } catch (e) { this.error = labels.saveFailed; }
    },

    schedulePreview() {
        clearTimeout(this.previewTimer);
        this.previewTimer = setTimeout(() => this.refreshPreview(), 400);
    },
    async refreshPreview() {
        if (! this.current) { this.previewHtml = ''; return; }
        try {
            const r = await this._api('POST', '/notes/preview', { content: this.current.content || '' });
            this.previewHtml = r.html || '';
        } catch (e) { /* keep last preview */ }
    },

    async save() {
        const n = this.current;
        if (! n) return;
        n.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        try {
            const saved = await this._api('PUT', `/notes/${n.id}`, { title: n.title, content: n.content, tags: n.tags, pinned: n.pinned });
            Object.assign(n, saved);
        } catch (e) { this.error = labels.saveFailed; }
    },

    async togglePin(n) {
        try { Object.assign(n, await this._api('PATCH', `/notes/${n.id}`, { pinned: ! n.pinned })); }
        catch (e) { this.error = labels.saveFailed; }
    },
    async trash(n) {
        try { Object.assign(n, await this._api('PATCH', `/notes/${n.id}`, { trashed: true })); if (this.currentId === n.id) this.currentId = null; }
        catch (e) { this.error = labels.saveFailed; }
    },
    async restore(n) {
        try { Object.assign(n, await this._api('PATCH', `/notes/${n.id}`, { trashed: false })); }
        catch (e) { this.error = labels.saveFailed; }
    },
    async remove(n) {
        if (! confirm(labels.deleteConfirm)) return;
        try { await this._api('DELETE', `/notes/${n.id}`); this.notes = this.notes.filter((x) => x.id !== n.id); if (this.currentId === n.id) this.currentId = null; }
        catch (e) { this.error = labels.saveFailed; }
    },
    async emptyTrash() {
        if (! confirm(labels.emptyTrashConfirm)) return;
        try { await this._api('DELETE', '/notes/trash/all'); this.notes = this.notes.filter((n) => ! n.trashed); }
        catch (e) { this.error = labels.saveFailed; }
    },

    async createShare(form) {
        if (! this.current) return;
        this.shareBusy = true; this.shareUrl = '';
        try {
            const r = await this._api('POST', `/notes/${this.current.id}/share`, form);
            this.shareUrl = r.url || '';
        } catch (e) { this.error = labels.shareFailed; }
        this.shareBusy = false;
    },
}));

/**
 * Bookmarks + folders, driven client-side over a JSON API (no reloads).
 */
Alpine.data('bookmarks', (labels = {}) => ({
    state: 'boot',
    folders: [],
    bookmarks: [],
    view: 'all', // all | favorites | trash | a folder id
    query: '',
    activeTag: '',
    error: '',
    newFolderName: '',
    editorOpen: false,
    // Kept a non-null blank so the teleported editor's x-model bindings never
    // read from null before a bookmark is opened.
    editing: { id: null, folderId: null, title: '', url: '', description: '', tags: [], favorite: false, readLater: false },
    tagsValue: '',
    importing: false, importResult: '',
    fetchingMeta: false,

    async init() { await this.load(); },

    async load() {
        try {
            const res = await fetch('/bookmarks/data', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) { this.state = 'error'; return; }
            const d = await res.json();
            this.folders = d.folders ?? [];
            this.bookmarks = d.bookmarks ?? [];
            this.state = 'ready';
        } catch (e) { this.state = 'error'; }
    },

    _headers() {
        return jsonHeaders();
    },
    async _api(method, url, body) {
        return apiRequest(method, url, body);
    },
    _replace(b) {
        const i = this.bookmarks.findIndex((x) => x.id === b.id);
        if (i >= 0) this.bookmarks[i] = b; else this.bookmarks.unshift(b);
    },
    host(url) { try { return new URL(url).host; } catch (e) { return ''; } },

    async addFolder() {
        const name = this.newFolderName.trim();
        if (! name) return;
        try {
            const f = await this._api('POST', '/bookmarks/folders', { name });
            this.folders.push({ id: f.id, name: f.name });
            this.folders.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            this.newFolderName = '';
        } catch (e) { this.error = labels.saveFailed; }
    },
    async deleteFolder(f) {
        if (! confirm(labels.deleteFolderConfirm)) return;
        try {
            await this._api('DELETE', `/bookmarks/folders/${f.id}`);
            this.folders = this.folders.filter((x) => x.id !== f.id);
            this.bookmarks.forEach((b) => { if (b.folderId === f.id) b.folderId = null; });
            if (this.view === f.id) this.view = 'all';
        } catch (e) { this.error = labels.saveFailed; }
    },

    get allTags() {
        const set = new Set();
        for (const b of this.bookmarks) for (const t of b.tags ?? []) set.add(t);
        return [...set].sort((a, b) => a.localeCompare(b));
    },
    get trashCount() { return this.bookmarks.filter((b) => b.trashed).length; },
    get readLaterCount() { return this.bookmarks.filter((b) => ! b.trashed && b.readLater && ! b.read).length; },
    get deadCount() { return this.bookmarks.filter((b) => ! b.trashed && b.dead).length; },

    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.bookmarks.filter((b) => this.view === 'trash' ? b.trashed : ! b.trashed);
        if (this.view === 'favorites') list = list.filter((b) => b.favorite);
        else if (this.view === 'readlater') list = list.filter((b) => b.readLater && ! b.read);
        else if (this.view === 'dead') list = list.filter((b) => b.dead);
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
        const folderId = (this.view !== 'all' && this.view !== 'favorites' && this.view !== 'trash') ? this.view : null;
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

    async saveBookmark() {
        const e = this.editing;
        if (! e || ! (e.url || '').trim()) { this.error = labels.urlRequired; return; }
        // Fall back to the host as the title so a bookmark is never untitled.
        if (! (e.title || '').trim()) e.title = this.host(e.url) || e.url;
        this.error = '';
        e.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        const body = { bookmark_folder_id: e.folderId ?? null, title: e.title, url: e.url, description: e.description, tags: e.tags, favorite: !! e.favorite };
        try {
            let b = e.id ? await this._api('PUT', `/bookmarks/${e.id}`, body) : await this._api('POST', '/bookmarks', body);
            // read_later is a PATCH concern (it also resets read_at) — sync it
            // when the checkbox differs from the stored state.
            if (!! e.readLater !== !! b.readLater) b = await this._api('PATCH', `/bookmarks/${b.id}`, { read_later: !! e.readLater });
            this._replace(b);
            this.closeEditor();
        } catch (err) { this.error = labels.saveFailed; }
    },

    // ---- metadata prefill / import / read-later ----------------------------
    async fetchMeta() {
        const url = (this.editing?.url || '').trim();
        if (! url || this.fetchingMeta) return;
        this.fetchingMeta = true;
        try {
            const d = await this._api('POST', '/bookmarks/fetch-meta', { url });
            if (d.title && ! (this.editing.title || '').trim()) this.editing.title = d.title;
            if (d.description && ! (this.editing.description || '').trim()) this.editing.description = d.description;
        } catch (e) { /* leave fields as typed */ } finally { this.fetchingMeta = false; }
    },
    async importFile(ev) {
        const f = ev.target.files[0]; if (! f) return;
        const fd = new FormData(); fd.append('file', f);
        this.importing = true; this.importResult = '';
        try {
            const res = await fetch('/bookmarks/import', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: fd,
            });
            if (res.ok) {
                const d = await res.json();
                this.importResult = (labels.importResult || ':created / :skipped').replace(':created', d.created).replace(':skipped', d.skipped);
                setTimeout(() => { this.importResult = ''; }, 8000);
                await this.load();
            } else { this.error = labels.saveFailed; }
        } catch (e) { this.error = labels.saveFailed; } finally { this.importing = false; ev.target.value = ''; }
    },
    async toggleReadLater(b) { await this._patch(b, { read_later: ! b.readLater }); },
    async markRead(b) { await this._patch(b, { read: true }); },

    async _patch(b, changes) {
        try { this._replace(await this._api('PATCH', `/bookmarks/${b.id}`, changes)); }
        catch (e) { this.error = labels.saveFailed; }
    },
    async toggleFavorite(b) { await this._patch(b, { favorite: ! b.favorite }); },
    async trash(b) { await this._patch(b, { trashed: true }); },
    async restore(b) { await this._patch(b, { trashed: false }); },
    async remove(b) {
        if (! confirm(labels.deleteConfirm)) return;
        try { await this._api('DELETE', `/bookmarks/${b.id}`); this.bookmarks = this.bookmarks.filter((x) => x.id !== b.id); }
        catch (e) { this.error = labels.saveFailed; }
    },
    async emptyTrash() {
        if (! confirm(labels.emptyTrashConfirm)) return;
        try { await this._api('DELETE', '/bookmarks/trash/all'); this.bookmarks = this.bookmarks.filter((b) => ! b.trashed); }
        catch (e) { this.error = labels.saveFailed; }
    },
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
if ('serviceWorker' in navigator) {
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
