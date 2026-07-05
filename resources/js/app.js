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

async function loadJSZip() {
    return (await import('jszip')).default;
}

async function loadHtml2Pdf() {
    return (await import('html2pdf.js')).default;
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
        shareModal: { open: false, type: '', id: null, name: '', shares: [], email: '', permission: 'read', error: '', feedback: '' },
        shareMailConfigured: !! cfg.mailConfigured,
        async openShare(type, id, name) {
            this.shareModal = { open: true, type, id, name, shares: [], email: '', permission: 'read', error: '', feedback: '' };
            await this.loadShares();
        },
        async copyShareLink() {
            try { await navigator.clipboard.writeText(cfg.shareLink); this.shareFlash(cfg.linkCopied || 'Copied'); } catch (e) { /* ignore */ }
        },
        async emailShare(id) {
            const r = await this._json(cfg.sharesBase + '/' + id + '/email', 'POST');
            if (r.ok) { this.shareFlash(cfg.mailSent || 'Sent'); } else { this.shareModal.error = cfg.mailUnavailable || 'Error'; }
        },
        shareFlash(msg) { this.shareModal.feedback = msg; clearTimeout(this._shareFlashT); this._shareFlashT = setTimeout(() => { this.shareModal.feedback = ''; }, 2500); },
        async loadShares() {
            try {
                const r = await fetch(cfg.sharesDataUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const d = await r.json();
                    this.shareModal.shares = (d.shared_by_me || []).filter((s) => s.type === this.shareModal.type && String(s.resource_id) === String(this.shareModal.id));
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
    book: '', group: '', q: '',
    sort: 'first_name', displayFormat: 'first_last', _settingsReady: false,
    importing: false, importResult: '',
    editor: false, form: {},
    nameModal: { open: false, title: '', value: '', onsubmit: null },
    confirmModal: { open: false, message: '', onConfirm: null },
    groupQuery: '', groupOpen: false,

    openConfirm(message, onConfirm) { this.confirmModal = { open: true, message, onConfirm }; },
    async doConfirm() { const cb = this.confirmModal.onConfirm; this.confirmModal.open = false; if (cb) await cb(); },

    init() {
        this.load();
        this.$watch('q', () => this.load());
        this.$watch('book', () => this.load());
        this.$watch('group', () => this.load());
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
        try {
            const r = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) {
                const d = await r.json();
                this.books = d.books; this.groups = d.groups; this.contacts = d.contacts;
                if (d.settings) { this.sort = d.settings.sort; this.displayFormat = d.settings.display_format; this._settingsReady = true; }
            }
        } catch (e) { /* keep */ } finally { this.loading = false; }
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

    async saveSettings() {
        if (! this._settingsReady) return;
        await this._json(cfg.settingsUrl, 'POST', { sort: this.sort, display_format: this.displayFormat });
        this.load();
    },

    blank() {
        const ownedBooks = this.books.filter((b) => b.owned);
        const preferred = ownedBooks.find((b) => b.id === this.book) ? this.book : ownedBooks[0]?.id;
        return { id: null, book_id: preferred, fn: '', first_name: '', last_name: '', org: '', title: '', nickname: '', bday: '', anniversaries: [], note: '', emails: [{ value: '', type: 'home' }], phones: [{ value: '', type: 'cell' }], urls: [], group_ids: [], avatar: null };
    },

    async openEditor(id) {
        if (! id) { this.form = this.blank(); this.editor = true; return; }
        const r = await fetch(cfg.contactBase + '/' + id, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (! r.ok) return;
        const d = await r.json();
        this.form = {
            id: d.id, book_id: d.book, fn: d.fn || '', first_name: d.first_name || '', last_name: d.last_name || '',
            org: d.org || '', title: d.title || '', nickname: d.nickname || '', bday: d.bday || '', anniversaries: d.anniversaries || [], note: d.note || '',
            emails: d.emails?.length ? d.emails : [{ value: '', type: 'home' }],
            phones: d.phones?.length ? d.phones : [{ value: '', type: 'cell' }],
            urls: d.urls || [], group_ids: d.group_ids || [],
            avatar: this.contacts.find((c) => c.id === id)?.avatar || null,
        };
        this.editor = true;
    },

    payload() {
        return {
            book_id: this.form.book_id, fn: this.form.fn, first_name: this.form.first_name, last_name: this.form.last_name,
            org: this.form.org, title: this.form.title, nickname: this.form.nickname, bday: this.form.bday, anniversaries: this.form.anniversaries.filter((a) => a.date), note: this.form.note,
            emails: this.form.emails.filter((e) => e.value), phones: this.form.phones.filter((p) => p.value),
            urls: this.form.urls, group_ids: this.form.group_ids,
        };
    },

    async save() {
        const id = this.form.id;
        await this._json(id ? cfg.contactBase + '/' + id : cfg.storeUrl, id ? 'PUT' : 'POST', this.payload());
        this.editor = false; this.load();
    },

    destroy() {
        if (! this.form.id) return;
        const id = this.form.id;
        this.openConfirm(cfg.confirmDelete, async () => {
            await this._json(cfg.contactBase + '/' + id, 'DELETE');
            this.editor = false; this.load();
        });
    },

    // --- avatar picker (device / gallery / people / files) + crop ---
    avatarModal: { open: false, tab: 'upload', loading: false },
    galleryPhotos: [], peoplePhotos: [], filePhotos: [],
    cropSrc: null, _cropper: null, saving: false,

    openAvatarModal() {
        if (! this.form.id) return; // avatar needs a saved contact to attach to
        this.avatarModal = { open: true, tab: 'upload', loading: false };
        this.cropSrc = null;
        this.destroyCropper();
    },
    closeAvatarModal() { this.avatarModal.open = false; this.cropSrc = null; this.destroyCropper(); },

    async avatarTab(tab) {
        this.avatarModal.tab = tab;
        this.cropSrc = null; this.destroyCropper();
        if (tab === 'gallery' && ! this.galleryPhotos.length) await this.loadPicker('galleryPickerUrl', 'photos', 'galleryPhotos');
        if (tab === 'people' && ! this.peoplePhotos.length) await this.loadPeople();
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
            if (r.ok) this.peoplePhotos = ((await r.json()).people ?? []).filter((p) => p.cover).map((p) => ({ name: p.name, url: p.cover }));
        } catch (e) { /* keep */ } finally { this.avatarModal.loading = false; }
    },
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
            if (r.ok) { const d = await r.json(); this.form.avatar = d.avatar + '?t=' + Date.now(); this.load(); this.closeAvatarModal(); }
        } catch (e) { /* ignore */ } finally { this.saving = false; }
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
        return fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token }, body: body ? JSON.stringify(body) : undefined });
    },
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
    eventsOn(d) {
        const key = this.ymd(d);
        return this.visibleEvents()
            .filter((e) => this.ymd(this.parse(e.start)) === key)
            .sort((a, b) => (a.all_day === b.all_day ? a.start.localeCompare(b.start) : (a.all_day ? -1 : 1)));
    },
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
    eventStyle(e) {
        const s = this.parse(e.start);
        if (! s) return 'display:none';
        const end = e.end ? this.parse(e.end) : null;
        const startMin = s.getHours() * 60 + s.getMinutes();
        let dur = end ? (end - s) / 60000 : this.defaultMinutes;
        if (! (dur > 0)) dur = 30;
        const top = (startMin / 60) * this.hourPx;
        const height = Math.max((dur / 60) * this.hourPx, 16);
        return `top:${top}px;height:${height}px`;
    },
    openNewAt(d, ev) {
        if (! this.calendars.some((c) => ! c.read_only)) return;
        const rect = ev.currentTarget.getBoundingClientRect();
        let min = Math.round(((ev.clientY - rect.top) / this.hourPx * 60) / 15) * 15;
        min = Math.max(0, Math.min(23 * 60 + 45, min));
        const start = new Date(d); start.setHours(0, min, 0, 0);
        const end = new Date(start.getTime() + this.defaultMinutes * 60000);
        const fmt = (x) => `${this.ymd(x)}T${String(x.getHours()).padStart(2, '0')}:${String(x.getMinutes()).padStart(2, '0')}`;
        const writable = this.calendars.find((c) => ! c.read_only);
        this.form = { id: null, calendar_id: writable?.id, summary: '', start: fmt(start), end: fmt(end), all_day: false, timezone: this.effectiveTz(), location: '', description: '', rrule: '', reminder_minutes: '', read_only: false };
        this.editor = true;
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
        return { id: null, calendar_id: writable?.id, summary: '', start: fmt(startAt), end: fmt(endAt), all_day: false, timezone: this.effectiveTz(), location: '', description: '', rrule: '', reminder_minutes: '', read_only: false };
    },

    openNew(d) {
        if (! this.calendars.some((c) => ! c.read_only)) return;
        this.form = this.blank(d); this.editor = true;
    },

    async openEditor(id) {
        const r = await fetch(cfg.eventBase + '/' + id, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (! r.ok) return;
        const d = await r.json();
        this.form = {
            id: d.id, calendar_id: d.calendar_id, summary: d.summary || '',
            start: d.start || '', end: d.end || '', all_day: !! d.all_day,
            timezone: d.timezone || this.effectiveTz(),
            location: d.location || '', description: d.description || '', rrule: d.rrule || '',
            reminder_minutes: d.reminder_minutes != null ? String(d.reminder_minutes) : '',
            // Birthday/anniversary/holiday calendars are read-only — open such an
            // event for viewing only (no save/delete, calendar shown as text).
            read_only: !! this.calendars.find((c) => c.id === d.calendar_id)?.read_only,
        };
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
            rrule: this.form.rrule || null,
            reminder_minutes: this.form.reminder_minutes === '' ? null : Number(this.form.reminder_minutes),
        };
    },

    async save() {
        if (! this.form.summary || ! this.form.calendar_id) return;
        const id = this.form.id;
        await this._json(id ? cfg.eventBase + '/' + id : cfg.eventsUrl, id ? 'PUT' : 'POST', this.payload());
        this.editor = false; this.load();
    },

    destroy() {
        if (! this.form.id) return;
        const id = this.form.id;
        this.openConfirm(cfg.confirmDelete, async () => {
            await this._json(cfg.eventBase + '/' + id, 'DELETE');
            this.editor = false; this.load();
        });
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
        return fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token }, body: body ? JSON.stringify(body) : undefined });
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

    initGallery() {
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
            if (res.ok) window.llToast(queuedMsg, '/downloads');
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

/* Plain localStorage cache for the mail reader (stats/folders/message lists).
 * Mail is no longer encrypted, so this is a plain cache. Best-effort — any
 * failure (quota, private mode) is swallowed and treated as a cache miss. */
const mailCache = {
    put(key, value) { try { localStorage.setItem('mailcache:' + key, JSON.stringify({ v: value, t: Date.now() })); } catch (e) { /* ignore */ } },
    get(key, maxAgeMs = null) {
        try {
            const raw = localStorage.getItem('mailcache:' + key);
            if (! raw) return null;
            const r = JSON.parse(raw);
            if (maxAgeMs && (Date.now() - r.t) > maxAgeMs) return null;
            return r.v;
        } catch (e) { return null; }
    },
    remove(key) { try { localStorage.removeItem('mailcache:' + key); } catch (e) { /* ignore */ } },
};

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
            this.state = 'ready';
        } catch (e) {
            this.state = 'error';
        }
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
        const files = inScope(this.manifest.files.map((f) => ({ ...f, kind: 'file' })));

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
    async bulkDownload() {
        const refs = this.selectionRefs;
        if (! refs.length) return;

        const file_ids = refs.filter((r) => r.kind === 'file').map((r) => r.id);
        const folder_ids = refs.filter((r) => r.kind !== 'file').map((r) => r.id);
        if (! file_ids.length && ! folder_ids.length) return;

        try {
            const res = await fetch(labels.exportUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ file_ids, folder_ids }),
            });
            if (res.ok) window.llToast(labels.exportQueued, labels.downloadsUrl);
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


Alpine.data('vaultMail', (labels = {}) => ({
    state: 'boot', // boot | locked | unconfigured | ready | error
    standalone: labels.standalone === true, // /mail reader page (auto-open + switcher)
    accountMenuOpen: false,
    manifest: { v: 1, accounts: [] },
    version: 0,
    error: '',
    busyId: null, // account id currently refreshing
    refreshingAll: false, // "refresh all" in progress
    errors: {}, // per-account error message
    dialogOpen: false,
    editingId: null,
    form: { name: '', host: '', port: 993, encryption: 'ssl', username: '', password: '', validateCert: true },
    deleteOpen: false,
    deleteId: null,
    cacheVersion: 0, // bumped on background sync to re-read cached stats
    reader: {
        open: false, account: null, folderPath: 'INBOX', page: 1, total: 0, perPage: 50, uidValidity: 0,
        messages: [], current: null, loading: false, loadingMore: false, error: '', imagesAllowed: false, busy: false, working: 0, gen: 0,
        folders: [], foldersLoading: false, sortDir: 'desc', selected: [], deleteChoiceOpen: false, headersOpen: false, emptyChoiceOpen: false,
        transferOpen: false, transferAccount: '', transferFolder: 'INBOX', transferFolderList: [], transferError: '',
        saveAtt: { open: false, att: null, folder: '', busy: false, error: '', done: false }, filesFolders: [],
        attView: { open: false, name: '', kind: '', url: '', loading: false, error: '' },
    },

    async init() {
        // Re-read cached stats when the background sync refreshes them.
        window.addEventListener('mail-synced', () => { this.cacheVersion++; });
        await this.load();
    },

    // Prefer freshly-synced stats from the (plain) cache, falling back to the
    // stats last fetched onto the in-memory account. Reading cacheVersion makes
    // the template re-evaluate after a sync.
    accountStats(a) {
        void this.cacheVersion;
        return mailCache.get(`stats:${a.id}`) ?? a.stats;
    },

    // "New mail" count for the switcher badge: unread in the INBOX only. The
    // stats.unseen field sums every folder (Junk, Trash, Sent…), which badly
    // overstates new mail — so read the INBOX folder's own unseen instead.
    accountUnread(id) {
        if (! id) return 0;
        const a = this.manifest.accounts.find((x) => x.id === id);
        const s = a ? this.accountStats(a) : null;
        if (! s) return 0;
        const inbox = (s.folders ?? []).find((f) => /^inbox$/i.test(f.path) || /^inbox$/i.test(f.name));
        return inbox ? (inbox.unseen ?? 0) : (s.unseen ?? 0);
    },

    async load() {
        try {
            const res = await fetch('/mail/accounts', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) { this.state = 'error'; return; }
            this.manifest = { v: 1, accounts: (await res.json()).accounts ?? [] };
            this.state = 'ready';
            // On the standalone reader page (/mail), open an account straight
            // away: the last one used in this browser, else the first.
            if (labels.standalone && ! this.reader.open && this.manifest.accounts.length) {
                const lastId = (() => { try { return Number(localStorage.getItem('mail:last-account')) || null; } catch (e) { return null; } })();
                const acc = this.manifest.accounts.find((a) => a.id === lastId) ?? this.sortedAccounts[0];
                if (acc) this.openReader(acc);
            }
        } catch (e) {
            this.state = 'error';
        }
    },

    _headers() {
        return jsonHeaders();
    },

    // Switch the reader to another account (from the sidebar account menu).
    switchAccount(id) {
        const acc = this.manifest.accounts.find((a) => a.id === id);
        if (acc && acc.id !== this.reader.account?.id) this.openReader(acc);
    },

    openAdd() {
        this.editingId = null;
        this.form = { name: '', host: '', port: 993, encryption: 'ssl', username: '', password: '', validateCert: true };
        this.error = '';
        this.dialogOpen = true;
    },

    openEdit(a) {
        this.editingId = a.id;
        // Password left blank keeps the stored one (never sent to the browser).
        this.form = {
            name: a.name ?? '', host: a.host ?? '', port: a.port ?? 993, encryption: a.encryption ?? 'ssl',
            username: a.username ?? '', password: '', validateCert: a.validateCert !== false,
        };
        this.error = '';
        this.dialogOpen = true;
    },

    async saveAccount() {
        const f = this.form;
        if (! f.host.trim() || ! f.username.trim() || (! this.editingId && ! f.password)) { this.error = labels.saveFailed; return; }
        const body = {
            name: f.name.trim() || f.host.trim(), host: f.host.trim(), port: Number(f.port) || 993,
            encryption: f.encryption, username: f.username.trim(), password: f.password, validate_cert: !! f.validateCert,
        };
        try {
            if (this.editingId) {
                const saved = await (await fetch(`/mail/accounts/${this.editingId}`, { method: 'PUT', headers: this._headers(), body: JSON.stringify(body) })).json();
                const a = this.manifest.accounts.find((x) => x.id === this.editingId);
                if (a) Object.assign(a, saved);
            } else {
                const res = await fetch('/mail/accounts', { method: 'POST', headers: this._headers(), body: JSON.stringify(body) });
                if (! res.ok) { this.error = labels.saveFailed; return; }
                this.manifest.accounts.push(await res.json());
            }
            this.dialogOpen = false;
        } catch (e) { this.error = labels.saveFailed; }
    },

    confirmDelete(a) { this.deleteId = a.id; this.deleteOpen = true; },

    async applyDelete() {
        const id = this.deleteId;
        this.deleteOpen = false;
        this.deleteId = null;
        try {
            await fetch(`/mail/accounts/${id}`, { method: 'DELETE', headers: this._headers() });
            this.manifest.accounts = this.manifest.accounts.filter((x) => x.id !== id);
            mailCache.remove(`stats:${id}`);
        } catch (e) { this.error = labels.saveFailed; }
    },

    // The server loads the (encrypted) credentials by id and connects; the
    // result is cached locally so it shows instantly next time.
    async refresh(a) {
        if (this.busyId) return;
        this.busyId = a.id;
        this.errors = { ...this.errors, [a.id]: '' };
        try {
            const res = await fetch('/mail/stats', { method: 'POST', headers: this._headers(), body: JSON.stringify({ account_id: a.id }) });
            if (! res.ok) {
                const body = await res.json().catch(() => ({}));
                this.errors = { ...this.errors, [a.id]: body.message || labels.connectFailed };
                return;
            }
            const stats = { ...(await res.json()), fetchedAt: new Date().toISOString() };
            const t = this.manifest.accounts.find((x) => x.id === a.id);
            if (t) t.stats = stats;
            mailCache.put(`stats:${a.id}`, stats);
            this.cacheVersion++;
        } catch (e) {
            this.errors = { ...this.errors, [a.id]: labels.connectFailed };
        } finally {
            this.busyId = null;
        }
    },

    async refreshAll() {
        if (this.refreshingAll) return;
        this.refreshingAll = true;
        try {
            for (const a of this.manifest.accounts) await this.refresh(a);
        } finally {
            this.refreshingAll = false;
        }
    },

    /* ---- Local archive (server-deleted mail kept locally) ---- */
    archive: { open: false, loading: false, messages: [], viewing: null, viewLoading: false, error: '' },

    async openArchive(a) {
        this.archive = { open: true, loading: true, messages: [], viewing: null, viewLoading: false, error: '' };
        this.archiveAccountId = a.id;
        try {
            const res = await fetch(`/mail/archive/${a.id}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            this.archive.messages = res.ok ? (await res.json()).messages : [];
        } catch (e) { this.archive.error = labels.connectFailed; }
        this.archive.loading = false;
    },
    closeArchive() { this.archive.open = false; this.archive.viewing = null; },

    async viewArchived(m) {
        this.archive.viewLoading = true; this.archive.viewing = { id: m.id, subject: m.subject, from: m.from };
        try {
            const res = await fetch(`/mail/archive/message/${m.id}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) this.archive.viewing = { id: m.id, ...(await res.json()) };
        } catch (e) { /* keep header */ }
        this.archive.viewLoading = false;
    },
    archivedAttachmentUrl(i) { return this.archive.viewing ? `/mail/archive/message/${this.archive.viewing.id}/attachment/${i}` : '#'; },

    async restoreArchived(m) {
        try {
            const res = await fetch(`/mail/archive/message/${m.id}/restore`, { method: 'POST', headers: this._headers() });
            if (! res.ok) { this.archive.error = labels.connectFailed; return; }
            this.archive.messages = this.archive.messages.filter((x) => x.id !== m.id);
            if (this.archive.viewing?.id === m.id) this.archive.viewing = null;
        } catch (e) { this.archive.error = labels.connectFailed; }
    },

    async deleteArchived(m) {
        if (! confirm(labels.archiveDeleteConfirm)) return;
        try {
            await fetch(`/mail/archive/message/${m.id}`, { method: 'DELETE', headers: this._headers() });
            this.archive.messages = this.archive.messages.filter((x) => x.id !== m.id);
            if (this.archive.viewing?.id === m.id) this.archive.viewing = null;
        } catch (e) { this.archive.error = labels.connectFailed; }
    },

    /* ---- Mail search (over the whole local archive) ---- */
    search: { open: false, loading: false, ran: false, q: '', dateFrom: '', dateTo: '', hasAttachment: false, results: [], error: '' },

    openSearch(a) {
        this.search = { open: true, loading: false, ran: false, q: '', dateFrom: '', dateTo: '', hasAttachment: false, results: [], error: '' };
        this.searchAccountId = a.id;
    },
    closeSearch() { this.search.open = false; },

    async runSearch() {
        this.search.loading = true; this.search.ran = true; this.search.error = '';
        const p = new URLSearchParams();
        if (this.search.q) p.set('q', this.search.q);
        if (this.search.dateFrom) p.set('date_from', this.search.dateFrom);
        if (this.search.dateTo) p.set('date_to', this.search.dateTo);
        if (this.search.hasAttachment) p.set('has_attachment', '1');
        try {
            const res = await fetch(`/mail/archive/${this.searchAccountId}/search?${p.toString()}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            this.search.results = res.ok ? (await res.json()).messages : [];
        } catch (e) { this.search.error = labels.connectFailed; }
        this.search.loading = false;
    },

    // Open a search hit in the normal reader (not a modal): switch to its
    // folder and open the message — archived, so it renders instantly.
    viewSearchResult(m) {
        if (! m.folderPath) return;
        this.closeSearch();
        this.openFolder(m.folderPath);
        this.openMsg(m.uid);
    },

    fmtBytes(n) { return formatBytes(n); },

    fmtDateTime(iso) {
        return iso ? new Date(iso).toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
        }) : '';
    },

    quotaPct(s) {
        return s && s.quotaLimit ? Math.min(100, Math.round((s.quotaUsed / s.quotaLimit) * 100)) : 0;
    },

    // Accounts and each account's IMAP folders are shown alphabetically.
    get sortedAccounts() {
        return [...this.manifest.accounts].sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' }));
    },

    sortedFolders(list) {
        // Dedupe by path: some servers (iCloud) intermittently return a folder
        // twice — once as a \Noselect container (selectable:false → rendered as
        // an uppercase section header) and once selectable — which showed each
        // folder both as a header and a row. Merge duplicates, keeping it
        // selectable and the larger counts.
        const byPath = new Map();
        for (const f of (list ?? [])) {
            // Normalise the key: a trailing hierarchy delimiter (iCloud returns
            // e.g. "Archive" and a "Archive/" container) must count as the same
            // folder, else each shows as both an uppercase header and a row.
            const key = (f.path || '').replace(/[/.]+$/, '') || f.path;
            const prev = byPath.get(key);
            byPath.set(key, prev ? {
                ...prev, ...f,
                path: prev.selectable !== false ? prev.path : f.path,
                selectable: prev.selectable !== false || f.selectable !== false,
                total: Math.max(prev.total || 0, f.total || 0),
                unseen: Math.max(prev.unseen || 0, f.unseen || 0),
                role: prev.role ?? f.role ?? null,
            } : f);
        }

        return [...byPath.values()].sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base', numeric: true }));
    },

    /* ---- Reader ---- */

    credsBody(a) {
        // The server resolves the (encrypted) credentials from the account id;
        // the password never travels to the browser.
        return { account_id: a.id };
    },

    // Folders available for navigation / move targets — fetched live from the
    // account (not the cached stats), so the list is always complete.
    readerFolders() {
        return this.sortedFolders(this.reader.folders);
    },

    otherAccounts() {
        return this.sortedAccounts.filter((a) => a.id !== this.reader.account?.id);
    },

    async loadFolders(creds) {
        try {
            const res = await fetch('/mail/folders', {
                method: 'POST',
                headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(creds),
            });
            if (! res.ok) return [];
            return (await res.json()).folders ?? [];
        } catch (e) {
            return [];
        }
    },

    // Open instantly and load folders + messages in parallel (never
    // folders-first-then-messages). Both show cached data at once, or a
    // spinner, and fill in when their fetch resolves.
    openReader(a) {
        try { localStorage.setItem('mail:last-account', a.id); } catch (e) { /* ignore */ }
        // Bump the generation so any in-flight loads from a previous account are
        // dropped when they resolve (switching before loading finished must not
        // apply the old account's folders/messages to the new one).
        this.reader.gen = (this.reader.gen || 0) + 1;
        this.reader.open = true;
        this.reader.account = a;
        this.reader.current = null;
        this.reader.transferOpen = false;
        this.reader.error = '';
        this.reader.folders = [];
        this.reader.messages = [];
        this.reader.total = 0;
        this.reader.selected = [];
        this.reader.folderPath = 'INBOX';
        this.hydrateFolder();       // messages: cache instantly + background/foreground
        this.loadFoldersAsync(a);   // folder sidebar in parallel, not awaited
    },

    loadFoldersAsync(a) {
        const gen = this.reader.gen;
        // Show the cached folder tree instantly; only spin when there is none.
        const cached = mailCache.get(`folders:${a.id}`, 86400000);
        if (cached && cached.length) {
            this.reader.folders = this.sortedFolders(cached);
            this.adoptInboxPath();
        } else {
            this.reader.foldersLoading = true;
        }
        this.loadFolders(this.credsBody(a)).then((folders) => {
            if (this.reader.gen !== gen) return; // switched account mid-flight → drop
            if (! folders.length) return;        // keep the cached tree on a failed refresh
            this.reader.folders = this.sortedFolders(folders);
            mailCache.put(`folders:${a.id}`, folders);
            this.adoptInboxPath();
        }).finally(() => { if (this.reader.gen === gen) this.reader.foldersLoading = false; });
    },

    // Adopt the server's real INBOX path if we are still on the default guess.
    adoptInboxPath() {
        const inbox = this.reader.folders.find((f) => /^inbox$/i.test(f.name) || /^inbox$/i.test(f.path));
        if (inbox && this.reader.folderPath === 'INBOX' && inbox.path !== 'INBOX') {
            this.reader.folderPath = inbox.path;
            this.hydrateFolder();
        }
    },

    // Manual "check for new mail": reload the current folder and refresh counts.
    refreshCurrentFolder() {
        this.loadMessages(true);
        this.loadFoldersAsync(this.reader.account);
    },

    // Show the cached message list instantly (if any), then refresh in the
    // background; otherwise load normally with a spinner.
    hydrateFolder() {
        this.reader.page = 1;
        // Show a cached list instantly only if it is at most a day old (a fresh
        // load runs right after anyway); older entries fall back to a spinner.
        const cached = mailCache.get(`msgs:${this.reader.account.id}:${this.reader.folderPath}`, 86400000);
        if (cached && (cached.messages ?? []).length) {
            this.reader.messages = cached.messages;
            this.reader.total = cached.total ?? cached.messages.length;
            this.reader.loading = false;
            this.loadMessages(true, true);
        } else {
            this.loadMessages(true);
        }
    },

    closeReader() {
        this.reader.open = false;
        this.reader.account = null;
        this.reader.messages = [];
        this.reader.current = null;
    },

    /* ---- Folder tree / management ---- */

    // Nesting depth of a folder from its path + delimiter, for indentation.
    folderDepth(f) {
        const parts = (f.path || '').split(f.delimiter || '/').filter(Boolean);
        return Math.max(0, parts.length - 1);
    },

    isTrashFolder() {
        const f = this.reader.folders.find((x) => x.path === this.reader.folderPath);
        return f?.role === 'trash' || /trash|deleted|papierkorb/i.test(f?.name || this.reader.folderPath || '');
    },

    // Folders in tree order: children directly under their parent, siblings by
    // role priority (Inbox, then the standard folders) then name. Fixes the
    // alphabetical flat sort that interleaved [Gmail]/* with custom folders.
    orderedFolders() {
        const list = this.reader.folders;
        const byPath = new Map(list.map((f) => [f.path, f]));
        const parentPath = (f) => {
            const d = f.delimiter || '/';
            const i = f.path.lastIndexOf(d);
            return i > 0 ? f.path.slice(0, i) : null;
        };
        const children = new Map();
        for (const f of list) {
            const p = parentPath(f);
            const key = (p && byPath.has(p)) ? p : '__root__';
            (children.get(key) ?? children.set(key, []).get(key)).push(f);
        }
        const prio = { inbox: 0, all: 1, drafts: 2, sent: 3, archive: 4, junk: 5, trash: 6, important: 7, flagged: 8 };
        const cmp = (a, b) => {
            const pa = a.role ? (prio[a.role] ?? 20) : 50;
            const pb = b.role ? (prio[b.role] ?? 20) : 50;
            return pa - pb || (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base', numeric: true });
        };
        const out = [];
        const walk = (key) => {
            for (const f of (children.get(key) ?? []).slice().sort(cmp)) { out.push(f); walk(f.path); }
        };
        walk('__root__');
        return out;
    },

    // Selectable folders (no containers) in tree order, for move targets.
    moveFolders() {
        return this.orderedFolders().filter((f) => f.selectable);
    },

    // Localised display name for standard folders; custom folders keep their
    // server name.
    folderLabel(f) {
        return (f.role && labels.folderNames && labels.folderNames[f.role]) ? labels.folderNames[f.role] : f.name;
    },

    // Translated name of the folder currently open in the reader (falls back to
    // the raw path before the folder list has loaded).
    currentFolderLabel() {
        const f = this.reader.folders.find((x) => x.path === this.reader.folderPath);
        return f ? this.folderLabel(f) : this.reader.folderPath;
    },

    // Heroicon path for a standard folder's role, or '' for custom folders.
    folderIconPath(f) {
        const icons = {
            inbox: 'M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z',
            sent: 'M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5',
            drafts: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10',
            trash: 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
            junk: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
            archive: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
            important: 'M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5',
        };
        icons.all = icons.archive;
        icons.flagged = icons.important;
        return f.role ? (icons[f.role] ?? '') : '';
    },

    // Re-fetch this account's stats into the cache so the overview + folder
    // counts update everywhere without waiting for the next background sync.
    async syncAccountStats(a) {
        try {
            const res = await mailPostRaw('/mail/stats', this.credsBody(a));
            if (res.ok) { mailCache.put(`stats:${a.id}`, await res.json()); this.cacheVersion++; }
        } catch (e) { /* ignore */ }
    },

    async createFolder(name) {
        name = (name || '').trim();
        if (! name || this.reader.busy) return;
        this.reader.busy = true;
        this.reader.error = '';
        try {
            const res = await this.mailPost('/mail/folder/create', { folder: name });
            if (! res.ok) { const b = await res.json().catch(() => ({})); this.reader.error = b.detail || labels.connectFailed; return; }
            this.reader.folders = this.sortedFolders(await this.loadFolders(this.credsBody(this.reader.account)));
            await this.syncAccountStats(this.reader.account);
        } finally {
            this.reader.busy = false;
        }
    },

    async emptyCurrentFolder() {
        if (this.reader.busy) return;
        this.reader.busy = true;
        this.reader.error = '';
        try {
            const res = await this.mailPost('/mail/folder/empty', { folder: this.reader.folderPath });
            if (! res.ok) { const b = await res.json().catch(() => ({})); this.reader.error = b.detail || labels.connectFailed; return; }
            this.reader.messages = [];
            this.reader.total = 0;
            this.reader.selected = [];
            this.reader.current = null;
            this.cacheList();
            this.reader.folders = this.sortedFolders(await this.loadFolders(this.credsBody(this.reader.account)));
            await this.syncAccountStats(this.reader.account);
        } finally {
            this.reader.busy = false;
        }
    },

    openFolder(path) {
        this.reader.folderPath = path;
        this.reader.current = null;
        this.reader.selected = [];
        this.hydrateFolder();
    },

    /* ---- Multi-select ---- */

    toggleSelectAll() {
        const uids = this.reader.messages.map((m) => m.uid);
        this.reader.selected = uids.every((u) => this.reader.selected.includes(u)) ? [] : uids;
    },

    get allSelected() {
        return this.reader.messages.length > 0 && this.reader.messages.every((m) => this.reader.selected.includes(m.uid));
    },

    /* ---- Optimistic local updates (avoid re-fetching the whole list) ---- */

    // Adjust a folder's total/unseen counts in the sidebar.
    bumpFolder(path, dTotal, dUnseen) {
        const f = this.reader.folders.find((x) => x.path === path);
        if (! f) return;
        f.total = Math.max(0, (f.total || 0) + dTotal);
        f.unseen = Math.max(0, (f.unseen || 0) + dUnseen);
    },

    // Remove messages from the current view + total + folder counts.
    removeMessages(uids) {
        const removed = this.reader.messages.filter((m) => uids.includes(m.uid));
        const unseen = removed.filter((m) => ! m.seen).length;
        this.reader.messages = this.reader.messages.filter((m) => ! uids.includes(m.uid));
        this.reader.total = Math.max(0, this.reader.total - removed.length);
        this.bumpFolder(this.reader.folderPath, -removed.length, -unseen);
        this.cacheList();
        return { count: removed.length, unseen };
    },

    // Flip \Seen locally and keep the folder's unread count in sync.
    markSeenLocal(uids, seen) {
        uids.forEach((uid) => {
            const m = this.reader.messages.find((x) => x.uid === uid);
            if (m && !! m.seen !== seen) {
                m.seen = seen;
                this.bumpFolder(this.reader.folderPath, 0, seen ? -1 : 1);
            }
        });
        this.cacheList();
    },

    // Apply an action's effect to the local list/counts (no reload).
    applyActionLocal(action, uids, target = null) {
        if (action === 'seen' || action === 'unseen') {
            this.markSeenLocal(uids, action === 'seen');
            return;
        }
        // trash / delete / move: leaves the current folder.
        const { count, unseen } = this.removeMessages(uids);
        if (action === 'move' && target) this.bumpFolder(target, count, unseen);
    },

    // Run an action over every selected message, updating locally as each
    // succeeds — no full folder reload.
    // Run a mail action in the background: apply the effect optimistically now
    // (so the UI updates instantly and stays usable for more operations), and
    // fire the request without blocking. A small "working" indicator shows while
    // any request is in flight; on failure the folder is resynced.
    runBg(promise) {
        this.reader.working++;
        Promise.resolve(promise).then(async (res) => {
            if (res && ! res.ok) {
                const b = await res.json().catch(() => ({}));
                this.reader.error = b.detail || labels.connectFailed;
                this.loadMessages(true, true);
            }
        }).catch(() => {
            this.reader.error = labels.connectFailed;
            this.loadMessages(true, true);
        }).finally(() => {
            this.reader.working = Math.max(0, this.reader.working - 1);
        });
    },

    // Route a toolbar action to the right handler: the open message in
    // single-view, otherwise the current multi-selection. Lets one toolbar
    // serve both modes.
    act(action, target = null) {
        return this.reader.current ? this.msgAction(action, target) : this.bulkAction(action, target);
    },

    bulkAction(action, target = null) {
        if (! this.reader.selected.length) return;
        const uids = [...this.reader.selected];
        this.reader.selected = [];
        this.reader.error = '';
        this.applyActionLocal(action, uids, target); // optimistic, instant
        this.runBg(this.mailPost('/mail/message/action', { uids, action, target }));
    },

    async mailPost(url, extra) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json', 'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ ...this.credsBody(this.reader.account), folder: this.reader.folderPath, ...extra }),
        });
        return res;
    },

    // Load a page of messages. reset=true starts a fresh folder view; otherwise
    // the next page is appended (infinite scroll, gallery-style).
    // background=true refreshes silently over an already-shown cached list
    // (no blanking, no spinner). Successful loads are written to the cache.
    async loadMessages(reset = true, background = false) {
        // Bind this load to the folder it was started for. A fast folder switch
        // must not let a late response apply to (or cache under) another folder
        // — that mixed UIDs across folders and caused "no headers found".
        const folder = this.reader.folderPath;
        const gen = this.reader.gen; // bound to the current account
        if (reset) {
            this.reader.page = 1;
            if (! background) { this.reader.messages = []; this.reader.total = 0; }
        }
        this.reader.loading = reset && ! background;
        this.reader.loadingMore = ! reset;
        this.reader.error = '';
        try {
            const res = await this.mailPost('/mail/messages', { page: this.reader.page });
            if (this.reader.gen !== gen || this.reader.folderPath !== folder) return; // account/folder changed → drop
            if (! res.ok) {
                const body = await res.json().catch(() => ({}));
                if (! background) this.reader.error = body.detail || labels.connectFailed;
                return;
            }
            const data = await res.json();
            if (this.reader.gen !== gen || this.reader.folderPath !== folder) return; // changed while parsing
            const rows = data.messages ?? [];
            this.reader.messages = reset ? rows : [...this.reader.messages, ...rows];
            this.reader.total = data.total ?? 0;
            // UIDs are only valid within one UIDVALIDITY. Track it so cached
            // message bodies keyed by UID can't be reused after the mailbox is
            // recreated (they'd otherwise point at a different message).
            if (data.uidValidity != null) this.reader.uidValidity = data.uidValidity;
            this.cacheList(folder);
        } catch (e) {
            if (! background && this.reader.gen === gen && this.reader.folderPath === folder) this.reader.error = labels.connectFailed;
        } finally {
            // Only clear the spinners if this load still owns the view.
            if (this.reader.gen === gen && this.reader.folderPath === folder) {
                this.reader.loading = false;
                this.reader.loadingMore = false;
            }
        }
    },

    cacheList(folder = null) {
        if (! this.reader.account) return;
        mailCache.put(`msgs:${this.reader.account.id}:${folder ?? this.reader.folderPath}`, {
            messages: this.reader.messages, total: this.reader.total, ts: Date.now(),
        });
    },

    get hasMoreMessages() {
        return this.reader.messages.length < this.reader.total;
    },

    async loadMore() {
        if (this.reader.loading || this.reader.loadingMore || ! this.hasMoreMessages) return;
        this.reader.page += 1;
        await this.loadMessages(false);
    },

    // Loaded messages sorted by date (newest first by default). Fetch order is
    // newest-first by UID; this keeps the displayed order correct by date and
    // lets the user flip it via the Date column.
    sortedMessages() {
        const dir = this.reader.sortDir === 'asc' ? 1 : -1;
        return [...this.reader.messages].sort((a, b) => {
            const ta = a.date ? Date.parse(a.date) || 0 : 0;
            const tb = b.date ? Date.parse(b.date) || 0 : 0;
            return (ta - tb) * dir;
        });
    },

    toggleSort() {
        this.reader.sortDir = this.reader.sortDir === 'desc' ? 'asc' : 'desc';
    },

    msgCacheKey(folder, uid) {
        // Include UIDVALIDITY: a recreated mailbox reuses UID numbers for
        // different messages, so a stale cached body must not be served.
        // v2: bumped when the rendered body changed (inline cid: images are now
        // embedded), so pre-fix cached bodies with raw cid: are not reused.
        return `msg:v2:${this.reader.account.id}:${folder}:${this.reader.uidValidity}:${uid}`;
    },

    // Reflect the list's archived state on the open message, so the reader
    // header shows the archive marker even when served from the session cache.
    applyArchivedFlag(uid) {
        if (! this.reader.current) return;
        const row = this.reader.messages.find((m) => m.uid === uid);
        if (row?.archived) this.reader.current.archived = true;
    },

    async openMsg(uid) {
        // Bind to the folder + account the click happened in — UIDs are per
        // folder, so a folder or account switch mid-fetch must not apply the
        // wrong message.
        const folder = this.reader.folderPath;
        const gen = this.reader.gen;
        this.reader.imagesAllowed = false;
        // Served from the encrypted cache if this message was already read this
        // session — instant, no re-fetch. The cache is dropped on lock/logout.
        const cached = mailCache.get(this.msgCacheKey(folder, uid));
        if (cached) { this.reader.current = cached; this.applyArchivedFlag(uid); return; }

        const accId = this.reader.account?.id;
        this.reader.busy = true;
        try {
            // 1) Prefer the local archive (S3) — instant, no IMAP round-trip.
            const arch = await this.mailPost(`/mail/message/cached/${accId}`, { uid });
            if (this.reader.gen !== gen || this.reader.folderPath !== folder) return;
            if (arch.ok) {
                const b = await arch.json().catch(() => ({}));
                if (b.found) {
                    this.reader.current = b.message; // has archived:true, archiveId, html/text/attachments
                    mailCache.put(this.msgCacheKey(folder, uid), this.reader.current);
                    this.markSeenHere(folder, uid); // local + server (background)
                    return;
                }
            }

            // 2) Not archived → live fetch (marks \Seen server-side).
            const res = await this.mailPost('/mail/message', { uid, mark_seen: true });
            if (this.reader.gen !== gen || this.reader.folderPath !== folder) return;
            if (! res.ok) { const b = await res.json().catch(() => ({})); this.reader.error = b.detail || labels.connectFailed; return; }
            this.reader.current = await res.json();
            if (this.reader.current.uidValidity != null) this.reader.uidValidity = this.reader.current.uidValidity;
            mailCache.put(this.msgCacheKey(folder, uid), this.reader.current);
            const row = this.reader.messages.find((m) => m.uid === uid);
            if (row && ! row.seen) { row.seen = true; this.bumpFolder(folder, 0, -1); this.cacheList(folder); }

            // 3) Capture it into the archive at the same time (fire-and-forget).
            if (! this.reader.current.archived) {
                this.runBg(this.mailPost(`/mail/message/archive/${accId}`, { uid, uidvalidity: this.reader.uidValidity ?? 0 })
                    .then((r) => r.ok ? r.json() : null)
                    .then((b) => { if (b?.archived) { this.reader.current && (this.reader.current.archived = true); const rr = this.reader.messages.find((m) => m.uid === uid); if (rr) rr.archived = true; } }));
            }
        } catch (e) {
            this.reader.error = labels.connectFailed;
        } finally {
            this.reader.busy = false;
        }
    },

    // Mark the open message read: locally (list + folder count) and on the
    // server in the background. Used when a message is rendered from the archive
    // (which does not itself touch the server).
    markSeenHere(folder, uid) {
        const row = this.reader.messages.find((m) => m.uid === uid);
        if (row && ! row.seen) { row.seen = true; this.bumpFolder(folder, 0, -1); this.cacheList(folder); }
        if (this.reader.current) this.reader.current.seen = true;
        this.runBg(this.mailPost('/mail/message/action', { uids: [uid], action: 'seen' }));
    },

    // Build the sandboxed iframe document. A strict CSP blocks remote content
    // (images/CSS) until the user opts in, regardless of inline styles.
    sanitizeEmail(html, allowImages) {
        const clean = DOMPurify.sanitize(html ?? '', {
            FORBID_TAGS: ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'base', 'form'],
            FORBID_ATTR: ['onerror', 'onload', 'onclick'],
        });
        const doc = new DOMParser().parseFromString(clean, 'text/html');
        doc.querySelectorAll('a').forEach((a) => { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener noreferrer'); });
        if (! allowImages) {
            doc.querySelectorAll('img').forEach((img) => {
                const src = img.getAttribute('src') || '';
                if (/^https?:/i.test(src)) { img.setAttribute('data-blocked', src); img.removeAttribute('src'); }
            });
        }
        return doc.body.innerHTML;
    },

    messageSrcdoc() {
        const c = this.reader.current;
        if (! c) return '';
        // Plain text: render in a normal sans-serif with comfortable line height
        // (like other mail clients) instead of monospace <pre>, keeping the
        // original line breaks.
        const body = c.html
            ? this.sanitizeEmail(c.html, this.reader.imagesAllowed)
            : `<div style="white-space:pre-wrap">${escapeHtml(c.text || '')}</div>`;
        const csp = this.reader.imagesAllowed
            ? "default-src 'none'; img-src data: https:; style-src 'unsafe-inline'; font-src data:"
            : "default-src 'none'; img-src data:; style-src 'unsafe-inline'; font-src data:";
        return `<!doctype html><html><head><meta charset="utf-8">`
            + `<meta http-equiv="Content-Security-Policy" content="${csp}">`
            + `</head><body style="font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:14px;line-height:1.5;margin:0;padding:16px;color:#111;word-break:break-word">${body}</body></html>`;
    },

    get messageHasBlockedImages() {
        const c = this.reader.current;
        return !! (c && c.html && /<img[^>]+src=["']https?:/i.test(c.html));
    },

    // Archived message body, rendered through the SAME safe pipeline as the live
    // reader (DOMPurify + sandboxed iframe + strict CSP) — never raw x-html, as
    // email HTML is attacker-controlled. Remote images are blocked.
    archiveSrcdoc(v = this.archive.viewing) {
        if (! v) return '';
        const body = v.html
            ? this.sanitizeEmail(v.html, false)
            : `<div style="white-space:pre-wrap">${escapeHtml(v.text || '')}</div>`;
        const csp = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; font-src data:";
        return `<!doctype html><html><head><meta charset="utf-8">`
            + `<meta http-equiv="Content-Security-Policy" content="${csp}">`
            + `</head><body style="font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:14px;line-height:1.5;margin:0;padding:16px;color:#111;word-break:break-word">${body}</body></html>`;
    },

    msgAction(action, target = null) {
        if (! this.reader.current) return;
        const uid = this.reader.current.uid;
        this.reader.error = '';
        if (action === 'seen' || action === 'unseen') {
            this.reader.current.seen = action === 'seen';
            this.markSeenLocal([uid], action === 'seen');
        } else {
            // Delete / move: apply optimistically and close the open message
            // right away → back to the list, ready for the next operation while
            // this one finishes in the background.
            this.applyActionLocal(action, [uid], target);
            this.reader.current = null;
        }
        this.runBg(this.mailPost('/mail/message/action', { uids: [uid], action, target }));
    },

    // Folders of the selected transfer-target account, fetched live; INBOX
    // fallback while loading or on failure.
    transferFolders() {
        const folders = this.sortedFolders(this.reader.transferFolderList);
        return folders.length ? folders : [{ name: 'INBOX', path: 'INBOX', total: 0, unseen: 0 }];
    },

    async openTransfer() {
        const first = this.otherAccounts()[0];
        this.reader.transferAccount = first?.id ?? '';
        this.reader.transferFolderList = [];
        this.reader.transferOpen = true;
        await this.onTransferAccountChange();
    },

    // Load the target account's folders and default to its INBOX.
    async onTransferAccountChange() {
        const a = this.manifest.accounts.find((x) => x.id === this.reader.transferAccount);
        this.reader.transferFolderList = a ? this.sortedFolders(await this.loadFolders(this.credsBody(a))) : [];
        const folders = this.transferFolders();
        const inbox = folders.find((f) => /^inbox$/i.test(f.name) || /^inbox$/i.test(f.path)) ?? folders[0];
        this.reader.transferFolder = inbox?.path ?? 'INBOX';
    },

    async confirmTransfer() {
        const a = this.manifest.accounts.find((x) => x.id === this.reader.transferAccount);
        if (! a || ! this.reader.transferFolder) return;
        const uids = this.reader.selected.length ? [...this.reader.selected] : (this.reader.current ? [this.reader.current.uid] : []);
        if (! uids.length) return;
        this.reader.busy = true;
        this.reader.transferError = '';
        let ok = false;
        try {
            // One request for the whole selection — the server copies each to
            // the target account and trashes the source over shared connections.
            const res = await fetch('/mail/message/transfer', {
                method: 'POST',
                headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    account_id: this.reader.account.id, folder: this.reader.folderPath, uids,
                    target_account_id: a.id, target_folder: this.reader.transferFolder,
                }),
            });
            if (res.ok) { ok = true; } else { const b = await res.json().catch(() => ({})); this.reader.transferError = b.detail || labels.connectFailed; }
        } catch (e) {
            this.reader.transferError = labels.connectFailed;
        } finally {
            this.reader.busy = false;
        }

        if (ok) {
            // Transferred messages leave this account's folder (target is another
            // account, not in this sidebar) — remove them locally, no reload.
            this.removeMessages(uids);
            this.reader.transferOpen = false;
            this.reader.current = null;
            this.reader.selected = [];
        }
        // On failure keep the modal open so the error/log stays visible; the
        // list is left as-is (nothing was removed).
    },

    // Fetch attachment bytes from wherever the open message came from: the
    // local archive (S3) if it was rendered from there, else live IMAP.
    fetchAttachment(att) {
        const id = this.reader.current?.archiveId;
        if (id) {
            return fetch(`/mail/archive/message/${id}/attachment/${att.id}`, { headers: { Accept: '*/*', 'X-Requested-With': 'XMLHttpRequest' } });
        }

        return this.mailPost('/mail/message/attachment', { uid: this.reader.current.uid, attachment: att.id });
    },

    async downloadAttachment(att) {
        try {
            const res = await this.fetchAttachment(att);
            if (! res.ok) return;
            saveBlobAs(new Uint8Array(await res.arrayBuffer()), att.name, att.mime);
        } catch (e) { /* ignore */ }
    },

    isPdfAttachment(att) {
        return (att?.mime || '').toLowerCase() === 'application/pdf' || /\.pdf$/i.test(att?.name || '');
    },

    // Open the Paperless modal immediately, then fetch the attachment bytes in
    // the background (the IMAP round-trip can take a few seconds). The title is
    // prefilled from the subject and the date from the message.
    async attachmentToPaperless(att) {
        const store = Alpine.store('paperless');
        const created = this.reader.current?.date ? String(this.reader.current.date).slice(0, 10) : null;
        store.begin(att.name || 'document.pdf', {
            title: this.reader.current?.subject || (att.name || '').replace(/\.[^.]+$/, ''),
            created,
        });
        try {
            const res = await this.fetchAttachment(att);
            if (! res.ok) { store.fail(labels.connectFailed); return; }
            store.setFile(new Blob([await res.arrayBuffer()], { type: 'application/pdf' }));
        } catch (e) { store.fail(labels.connectFailed); }
    },

    // ---- Inline preview for browser-displayable attachments (images / PDF) ----

    // True for attachments a browser can render inline. SVG is deliberately
    // excluded: it can carry scripts and would render in the app origin.
    attachmentPreviewable(att) {
        const mime = (att?.mime || '').toLowerCase();
        return /^image\/(png|jpe?g|gif|webp|bmp|avif|x-icon)$/.test(mime) || mime === 'application/pdf';
    },

    // Fetch the bytes, wrap them in a blob URL and show them in a modal. The
    // bytes come from the stateless endpoint; nothing is stored.
    async openAttachment(att) {
        if (! this.attachmentPreviewable(att)) { this.downloadAttachment(att); return; }
        this.closeAttachment(); // revoke any previous blob URL
        const kind = (att.mime || '').toLowerCase() === 'application/pdf' ? 'pdf' : 'image';
        this.reader.attView = { open: true, name: att.name || '', kind, url: '', loading: true, error: '' };
        try {
            const res = await this.fetchAttachment(att);
            if (! res.ok) { this.reader.attView.error = labels.connectFailed; this.reader.attView.loading = false; return; }
            const blob = new Blob([await res.arrayBuffer()], { type: att.mime || 'application/octet-stream' });
            this.reader.attView.url = URL.createObjectURL(blob);
            this.reader.attView.loading = false;
        } catch (e) {
            this.reader.attView.error = labels.connectFailed;
            this.reader.attView.loading = false;
        }
    },

    closeAttachment() {
        if (this.reader.attView.url) URL.revokeObjectURL(this.reader.attView.url);
        this.reader.attView = { open: false, name: '', kind: '', url: '', loading: false, error: '' };
    },

    // ---- Save an attachment into Files (plain upload via the files API) ----

    async openSaveAttachment(att) {
        this.reader.saveAtt = { open: true, att, folder: '', busy: false, error: '', done: false };
        // Load the Files folders for the destination picker.
        try {
            const d = await (await fetch('/files/data', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })).json();
            this.reader.filesFolders = this.buildFolderOptions(d.folders ?? []);
        } catch (e) {
            this.reader.filesFolders = [];
        }
    },

    // Flatten the folder tree into "A / B / C" labelled options for a <select>.
    buildFolderOptions(folders) {
        const byId = new Map(folders.map((f) => [f.id, f]));
        const pathOf = (f) => {
            const parts = [];
            let cur = f;
            let guard = 0;
            while (cur && guard++ < 64) { parts.unshift(cur.name); cur = cur.parent ? byId.get(cur.parent) : null; }
            return parts.join(' / ');
        };
        return folders
            .map((f) => ({ id: f.id, label: pathOf(f) }))
            .sort((a, b) => a.label.localeCompare(b.label, undefined, { sensitivity: 'base' }));
    },

    async saveAttachmentToFiles() {
        const s = this.reader.saveAtt;
        if (! s || ! s.att || s.busy || ! this.reader.current) return;
        s.busy = true;
        s.error = '';
        try {
            // Fetch the attachment bytes, then upload them into Files (plain).
            const res = await this.fetchAttachment(s.att);
            if (! res.ok) { s.error = labels.saveFailed; return; }
            const bytes = new Uint8Array(await res.arrayBuffer());

            const fd = new FormData();
            fd.append('_token', csrfToken());
            fd.append('file', new Blob([bytes], { type: s.att.mime || 'application/octet-stream' }), s.att.name || 'attachment');
            if (s.folder) fd.append('folder_id', s.folder);
            const up = await fetch('/files/import', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            if (! up.ok) { s.error = labels.saveFailed; return; }

            s.done = true;
            window.dispatchEvent(new CustomEvent('files-changed'));
            setTimeout(() => { if (this.reader.saveAtt) this.reader.saveAtt.open = false; }, 1000);
        } catch (e) {
            s.error = labels.saveFailed;
        } finally {
            s.busy = false;
        }
    },

    printMsg() {
        const c = this.reader.current;
        if (! c) return;
        const body = c.html
            ? this.sanitizeEmail(c.html, true)
            : `<pre style="white-space:pre-wrap;word-break:break-word">${escapeHtml(c.text || '')}</pre>`;
        // Print via a sandboxed, script-less iframe with a strict CSP — never a
        // same-origin window with an injected <script>. Email HTML must never run
        // in the app origin (a DOMPurify bypass could otherwise read session data
        // from sessionStorage). No allow-scripts → nothing in the body executes.
        const csp = "default-src 'none'; img-src data: https:; style-src 'unsafe-inline'; font-src data:";
        const srcdoc = '<!doctype html><html><head><meta charset="utf-8">'
            + `<meta http-equiv="Content-Security-Policy" content="${csp}">`
            + `<title>${escapeHtml(c.subject || '')}</title></head>`
            + `<body style="font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;padding:16px;color:#111">${body}</body></html>`;
        const frame = document.createElement('iframe');
        frame.setAttribute('sandbox', 'allow-same-origin allow-modals');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
        frame.srcdoc = srcdoc;
        frame.onload = () => {
            try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (e) { /* ignore */ }
            setTimeout(() => frame.remove(), 1000);
        };
        document.body.appendChild(frame);
    },

    fmtAddress(a) {
        if (! a) return '';
        return a.name ? `${a.name} <${a.email}>` : a.email;
    },
}));

Alpine.plugin(intersect);

window.Alpine = Alpine;

// Background mail sync: refresh each account's
// stats and INBOX headers into the client cache so the overview and reader show
// instantly. Runs only client-side (the server never stores the IMAP
// credentials) at the configured interval and on tab focus.
function mailPostRaw(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json', 'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
}

document.addEventListener('alpine:init', () => {
    Alpine.store('mailSync', {
        timer: null,
        running: false,
        lastSync: 0,

        init() {
            setTimeout(() => this.maybeTick(), 1500);
            document.addEventListener('visibilitychange', () => { if (! document.hidden) this.maybeTick(); });
            this.timer = setInterval(() => this.maybeTick(), this.intervalMs());
        },

        intervalMs() {
            const m = Number(document.querySelector('meta[name="mail-sync-minutes"]')?.getAttribute('content')) || 5;
            return Math.max(5, m) * 60000;
        },

        maybeTick() {
            if (document.hidden) return;
            if (Date.now() - this.lastSync < this.intervalMs() - 1000) return;
            this.tick();
        },

        async tick() {
            if (this.running) return;
            this.running = true;
            try {
                const accts = await (await fetch('/mail/accounts', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })).json();
                for (const a of accts.accounts ?? []) {
                    const creds = { account_id: a.id };
                    try {
                        const s = await mailPostRaw('/mail/stats', creds);
                        if (s.ok) mailCache.put(`stats:${a.id}`, await s.json());
                    } catch (e) { /* skip */ }
                    try {
                        const m = await mailPostRaw('/mail/messages', { ...creds, folder: 'INBOX', page: 1 });
                        if (m.ok) { const d = await m.json(); mailCache.put(`msgs:${a.id}:INBOX`, { messages: d.messages ?? [], total: d.total ?? 0, ts: Date.now() }); }
                    } catch (e) { /* skip */ }
                    try {
                        // Cache the folder tree too, so opening the mailbox shows the
                        // sidebar instantly instead of spinning on an IMAP round-trip.
                        const f = await mailPostRaw('/mail/folders', creds);
                        if (f.ok) { const d = await f.json(); if ((d.folders ?? []).length) mailCache.put(`folders:${a.id}`, d.folders); }
                    } catch (e) { /* skip */ }
                }
                this.lastSync = Date.now();
                window.dispatchEvent(new CustomEvent('mail-synced'));
            } catch (e) {
                /* accounts unavailable */
            } finally {
                this.running = false;
            }
        },
    });
});

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
    editing: null,
    tagsValue: '',

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

    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.bookmarks.filter((b) => this.view === 'trash' ? b.trashed : ! b.trashed);
        if (this.view === 'favorites') list = list.filter((b) => b.favorite);
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
        this.editing = { id: null, folderId, title: '', url: '', description: '', tags: [], favorite: false };
        this.tagsValue = '';
        this.editorOpen = true;
    },
    editBookmark(b) {
        this.editing = { ...b, tags: [...(b.tags ?? [])] };
        this.tagsValue = (this.editing.tags || []).join(', ');
        this.editorOpen = true;
    },
    closeEditor() { this.editorOpen = false; this.editing = null; },

    async saveBookmark() {
        const e = this.editing;
        if (! e || ! (e.title || '').trim() || ! (e.url || '').trim()) return;
        e.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        const body = { bookmark_folder_id: e.folderId ?? null, title: e.title, url: e.url, description: e.description, tags: e.tags, favorite: !! e.favorite };
        try {
            const b = e.id ? await this._api('PUT', `/bookmarks/${e.id}`, body) : await this._api('POST', '/bookmarks', body);
            this._replace(b);
            this.closeEditor();
        } catch (err) { this.error = labels.saveFailed; }
    },

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

Alpine.start();
