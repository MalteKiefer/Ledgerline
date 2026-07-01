import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import L from 'leaflet';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

/**
 * Type-ahead combobox for selecting a contact's function from the fixed
 * ContactFunction enum. The visible input filters the labels; the submitted
 * value is always one of the enum's backing values (or empty, which the
 * server-side validation rejects). No third-party widget library is used.
 *
 * @param {{value: string, label: string}[]} options
 * @param {string} initial  The pre-selected enum value, if any.
 */
Alpine.data('contactFunctionCombobox', (options, initial = '') => ({
    options,
    open: false,
    selected: initial,
    query: (options.find((option) => option.value === initial) || {}).label || '',

    /** Options matching the current query (by label or value). */
    get filtered() {
        const query = this.query.toLowerCase().trim();

        if (query === '') {
            return this.options;
        }

        return this.options.filter(
            (option) =>
                option.label.toLowerCase().includes(query) ||
                option.value.toLowerCase().includes(query),
        );
    },

    /** Pick an option from the list. */
    choose(option) {
        this.selected = option.value;
        this.query = option.label;
        this.open = false;
    },

    /** Keep the submitted value in sync when the user types an exact label. */
    syncFromQuery() {
        this.open = true;

        const match = this.options.find(
            (option) => option.label.toLowerCase() === this.query.toLowerCase().trim(),
        );

        this.selected = match ? match.value : '';
    },
}));

/**
 * Repeater for a contact's labelled email addresses and phone numbers. Each
 * channel has a free label (suggested via a datalist) and a value. Rows can be
 * added and removed freely; empty rows are stripped server-side.
 *
 * @param {{label: string, value: string}[]} initialEmails
 * @param {{label: string, value: string}[]} initialPhones
 */
Alpine.data('contactChannels', (initialEmails = [], initialPhones = []) => ({
    emails: initialEmails.length
        ? initialEmails.map((row) => ({ ...row }))
        : [{ label: 'Work', value: '' }],
    phones: initialPhones.length
        ? initialPhones.map((row) => ({ ...row }))
        : [{ label: 'Work', value: '' }],

    addEmail() {
        this.emails.push({ label: '', value: '' });
    },

    removeEmail(index) {
        this.emails.splice(index, 1);
    },

    addPhone() {
        this.phones.push({ label: '', value: '' });
    },

    removePhone(index) {
        this.phones.splice(index, 1);
    },
}));

/**
 * Type-ahead combobox for selecting a country from the full ISO 3166 list.
 * Options carry a flag emoji; the submitted value is always an alpha-2 code
 * (or empty). The visible list is capped for rendering performance.
 *
 * @param {{value: string, label: string, flag: string}[]} options
 * @param {string} initial  The pre-selected country code, if any.
 */
Alpine.data('countryCombobox', (options, initial = '') => ({
    options,
    open: false,
    selected: initial,
    query: (options.find((option) => option.value === initial) || {}).label || '',

    get selectedFlag() {
        const option = this.options.find((item) => item.value === this.selected);

        return option ? option.flag : '';
    },

    get filtered() {
        const query = this.query.toLowerCase().trim();

        if (query === '') {
            return this.options.slice(0, 80);
        }

        return this.options
            .filter(
                (option) =>
                    option.label.toLowerCase().includes(query) ||
                    option.value.toLowerCase() === query,
            )
            .slice(0, 80);
    },

    choose(option) {
        this.selected = option.value;
        this.query = option.label;
        this.open = false;
    },

    syncFromQuery() {
        this.open = true;

        const match = this.options.find(
            (option) => option.label.toLowerCase() === this.query.toLowerCase().trim(),
        );

        this.selected = match ? match.value : '';
    },
}));

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
 * Generic value/label type-ahead combobox (e.g. for picking a customer).
 *
 * @param {{value: string|number, label: string}[]} options
 * @param {string} initial
 */
Alpine.data('selectCombobox', (options, initial = '') => ({
    options,
    open: false,
    selected: initial,
    query: (options.find((option) => String(option.value) === String(initial)) || {}).label || '',

    get filtered() {
        const query = this.query.toLowerCase().trim();

        if (query === '') {
            return this.options.slice(0, 50);
        }

        return this.options
            .filter((option) => option.label.toLowerCase().includes(query))
            .slice(0, 50);
    },

    choose(option) {
        this.selected = option.value;
        this.query = option.label;
        this.open = false;
    },

    syncFromQuery() {
        this.open = true;

        const match = this.options.find(
            (option) => option.label.toLowerCase() === this.query.toLowerCase().trim(),
        );

        this.selected = match ? match.value : '';
    },
}));

/**
 * Tag input: free-text chips with suggestions. Submits one hidden tags[] field
 * per chip. Enter or comma adds the current text; duplicates are ignored.
 *
 * @param {string[]} initial
 */
Alpine.data('tagInput', (initial = []) => ({
    tags: Array.isArray(initial) ? [...initial] : [],
    query: '',

    add() {
        const value = this.query.trim().replace(/,$/, '').trim();

        if (value !== '' && !this.tags.some((tag) => tag.toLowerCase() === value.toLowerCase())) {
            this.tags.push(value);
        }

        this.query = '';
    },

    remove(index) {
        this.tags.splice(index, 1);
    },

    onKey(event) {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            this.add();
        }
    },
}));

/**
 * Drag-and-drop file field. Wraps a hidden native file input, reflects the
 * chosen file name, and highlights while dragging over.
 */
Alpine.data('dropzone', () => ({
    fileName: '',
    over: false,

    onDrop(event) {
        this.over = false;
        const files = event.dataTransfer?.files;

        if (files && files.length) {
            this.$refs.input.files = files;
            this.fileName = files[0].name;
        }
    },

    onChange() {
        this.fileName = this.$refs.input.files.length ? this.$refs.input.files[0].name : '';
    },
}));

/**
 * Repeatable invoice line editor. Each row submits as lines[i][field]; the
 * server recomputes all totals, so the live net here is only a preview.
 *
 * @param {{description: string, quantity: number, unit: string, unit_price: string, tax_rate: number}[]} initial
 */
Alpine.data('invoiceLines', (initial = []) => ({
    lines: initial.length
        ? initial
        : [{ description: '', quantity: 1, unit: '', unit_price: '', tax_rate: 19 }],

    add() {
        this.lines.push({ description: '', quantity: 1, unit: '', unit_price: '', tax_rate: 19 });
    },

    remove(index) {
        this.lines.splice(index, 1);
        if (this.lines.length === 0) {
            this.add();
        }
    },

    /** Live net preview (quantity * unit price), for display only. */
    get net() {
        return this.lines.reduce(
            (sum, line) => sum + (parseFloat(line.quantity || 0) * parseFloat(line.unit_price || 0)),
            0,
        );
    },
}));

/**
 * File explorer: multiselect, a shared "move to folder" modal (for a single row
 * or the whole selection), inline rename, and a bulk-delete modal.
 *
 * @param {number[]} allIds  Ids of the files currently listed.
 */
Alpine.data('filesExplorer', (allIds = [], config = {}) => ({
    allIds,
    selected: [],
    moveOpen: false,
    moveIds: [],
    target: '',
    deleteOpen: false,
    renaming: null,

    // Drag-and-drop upload (whole-window dropzone, folders included).
    dragging: false,
    uploading: false,
    progress: { done: 0, total: 0 },

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

        if (files.length) {
            await this.uploadFiles(files);
        }
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

    async uploadFiles(files) {
        this.uploading = true;
        this.progress = { done: 0, total: files.length };

        // Send in batches to stay within the per-request file limit.
        const chunkSize = 25;
        for (let i = 0; i < files.length; i += chunkSize) {
            const chunk = files.slice(i, i + chunkSize);
            const data = new FormData();
            data.append('_token', config.token);
            if (config.folderId) data.append('folder_id', config.folderId);
            if (config.customerId) data.append('customer_id', config.customerId);
            if (config.projectId) data.append('project_id', config.projectId);
            chunk.forEach((item) => {
                data.append('files[]', item.file);
                data.append('paths[]', item.path);
            });
            try {
                await fetch(config.uploadUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
            } catch (e) { /* continue with remaining batches */ }
            this.progress.done = Math.min(files.length, i + chunk.length);
        }

        window.location.reload();
    },

    toggleAll(event) {
        this.selected = event.target.checked ? [...this.allIds] : [];
    },

    /** Open the move modal for a single file or, with no argument, the selection. */
    openMove(id = null) {
        this.moveIds = id === null ? [...this.selected] : [id];
        this.target = '';
        this.moveOpen = true;
    },

    openBulkDelete() {
        if (this.selected.length) {
            this.deleteOpen = true;
        }
    },

    startRename(id) {
        this.renaming = id;
        this.$nextTick(() => this.$refs['rename-' + id]?.focus());
    },
}));

/**
 * Gallery uploader: accepts dropped/selected images and uploads them one at a
 * time via XMLHttpRequest, exposing a per-file progress list (Immich-style).
 * Reloads the page when the batch finishes so the timeline shows the new photos.
 *
 * @param {string} url       The gallery store endpoint.
 * @param {string} token     CSRF token.
 * @param {string} feedUrl   The infinite-scroll feed endpoint.
 * @param {boolean} hasMore  Whether a second page exists.
 */
Alpine.data('gallery', (url, token, feedUrl = '', hasMore = false, mapZoom = 13, monthsUrl = '') => ({
    dragging: false,
    queue: [],
    selected: [],
    active: 0,
    maxConcurrent: 3,
    summary: null,
    lastSelected: null,

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

        // Live-update the mini-map as the coordinates are edited.
        this.$watch('current.lat', () => { if (this.viewerOpen) this.renderMiniMap(); });
        this.$watch('current.lng', () => { if (this.viewerOpen) this.renderMiniMap(); });

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

    renderMiniMap() {
        this.destroyMiniMap();

        const el = this.$refs.miniMap;
        const lat = parseFloat(this.current.lat);
        const lng = parseFloat(this.current.lng);
        if (! el || ! Number.isFinite(lat) || ! Number.isFinite(lng)) {
            return;
        }

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
        this.selected = event.target.checked ? [...this.allIds()] : [];
    },

    allIds() {
        return [...document.querySelectorAll('[data-photo-id]')].map((el) => Number(el.dataset.photoId));
    },

    /* ---- Selection (click, shift-range, select-all) ---- */

    selectableIds() {
        return [...document.querySelectorAll('[data-select]')].map((cb) => Number(cb.value));
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
                this.selected = [...set];
                this.lastSelected = id;
                return;
            }
        }

        this.selected = this.selected.includes(id)
            ? this.selected.filter((x) => x !== id)
            : [...this.selected, id];
        this.lastSelected = id;
    },

    selectAllVisible() {
        this.selected = this.selectableIds();
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

    init() {
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

Alpine.plugin(intersect);

window.Alpine = Alpine;

Alpine.start();
