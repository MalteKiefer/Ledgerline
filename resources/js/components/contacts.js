import Alpine from 'alpinejs';

// Sharing UI is Phase 3 — no-op mixin so Phase-1 views that spread it stay inert.
const shareMixin = () => ({});

// Leaflet was removed from deps; embedded maps degrade to the "open in
// OpenStreetMap" link (mapModal.osmUrl is still computed). This chainable no-op
// stands in for Leaflet's `L` so the (currently dead) embed code cannot throw.
// Real embedding returns once the leaflet dep is re-added.
const loadLeaflet = async () => {
    const noop = new Proxy(function () {}, { get: () => () => noop, apply: () => noop });
    return noop;
};

// JSON fetch helper (method + optional body), CSRF-guarded. Returns the parsed
// body merged with { ok, status } so callers can read res.ok and res.<field>.
async function apiJson(url, method, body, token) {
    const r = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    let data = {};
    try { data = await r.json(); } catch (e) { /* empty body */ }
    return { ok: r.ok, status: r.status, ...data };
}

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

    async pickDeviceImage(ev) {
        const f = ev.target.files[0]; ev.target.value = '';
        if (! f || ! this.form.id || typeof window.llCrop !== 'function') return;
        const bytes = await window.llCrop(f);
        if (bytes) await this._uploadAvatar(new Blob([bytes], { type: 'image/jpeg' }));
    },
    async _uploadAvatar(blob) {
        this.saving = true;
        const fd = new FormData(); fd.append('photo', blob, 'avatar.jpg');
        try {
            const r = await fetch(cfg.contactBase + '/' + this.form.id + '/avatar', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.token }, body: fd });
            if (r.ok) { const d = await r.json(); this.form.avatar = d.avatar + '?t=' + Date.now(); this.closeAvatarModal(); }
        } catch (e) { /* ignore */ } finally { this.saving = false; }
    },
    async startCrop() { /* crop handled by window.llCrop */ },
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
