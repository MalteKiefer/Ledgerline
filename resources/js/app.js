import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { Vault } from './vault';
import { EditorView, basicSetup } from 'codemirror';
import { EditorState, Compartment } from '@codemirror/state';
import { LanguageDescription } from '@codemirror/language';
import { languages } from '@codemirror/language-data';
import JSZip from 'jszip';
import { marked } from 'marked';
import { markedHighlight } from 'marked-highlight';
import hljs from 'highlight.js/lib/common';
import DOMPurify from 'dompurify';
import html2pdf from 'html2pdf.js';
import 'github-markdown-css/github-markdown-light.css';
import 'highlight.js/styles/github.css';
import L from 'leaflet';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet's default marker resolves its images by a relative URL that 404s
// under a bundler; point it at the bundled assets so pins render.
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

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

            // Merge zero-knowledge notes, searched client-side over the
            // decrypted manifest (only while the vault is unlocked).
            const notes = await searchNotesClient(term);
            if (notes.length) {
                this.groups.push({
                    group: document.documentElement.lang === 'de' ? 'Notizen' : 'Notes',
                    results: notes,
                });
            }

            const bookmarks = await searchBookmarksClient(term);
            if (bookmarks.length) {
                this.groups.push({
                    group: document.documentElement.lang === 'de' ? 'Lesezeichen' : 'Bookmarks',
                    results: bookmarks,
                });
            }

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

    mountMap() {
        if (this.map) {
            this.map.remove();
            this.map = null;
            this.marker = null;
        }
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
            this.marker = L.marker([lat, lng]).addTo(this.map);
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

/**
 * Zero-knowledge encryption vault store. Wraps the crypto module so views can
 * check state and drive setup / unlock / recover / lock.
 */
Alpine.store('vault', {
    configured: false,
    unlocked: false,
    busy: false,
    ready: null,

    // Idempotent: repeated calls return the same in-flight/settled promise, so
    // components can `await boot()` to be sure the cached key was restored.
    boot() {
        if (! this.ready) {
            this.ready = (async () => {
                await Vault.boot();
                this.unlocked = Vault.unlocked();
                try {
                    this.configured = (await Vault.status()).configured;
                } catch (e) { /* offline: leave defaults */ }
            })();
        }
        return this.ready;
    },

    async setup(passphrase) {
        const code = await Vault.setup(passphrase);
        this.configured = true;
        this.unlocked = true;
        this.announceUnlocked();
        return code;
    },

    async unlock(passphrase) {
        await Vault.unlock(passphrase);
        this.unlocked = true;
        this.announceUnlocked();
    },

    async recover(code) {
        await Vault.recover(code);
        this.unlocked = true;
        this.announceUnlocked();
    },

    async changePassphrase(currentPass, newPass) {
        await Vault.changePassphrase(currentPass, newPass);
        this.unlocked = true;
        this.announceUnlocked();
    },

    lock() {
        Vault.lock();
        this.unlocked = false;
    },

    // Let the encrypted-name/type/preview components on the page re-decrypt
    // themselves once the key is available, without a manual page reload.
    announceUnlocked() {
        window.dispatchEvent(new CustomEvent('vault-unlocked'));
    },
});

/* ---- Zero-knowledge file browser (manifest model) ----
 *
 * The whole directory structure lives in one encrypted manifest; the server
 * stores only that ciphertext and anonymous, padded content blobs. Everything
 * below — listing, search, sort, rename, move, delete — runs on the decrypted
 * manifest in memory and is written back as a whole (optimistic-locked).
 */

// Map a mime to a FileType-like category key for the type column.
function classifyMime(mime) {
    mime = (mime || '').toLowerCase();
    if (mime.startsWith('image/')) return 'IMAGE';
    if (mime === 'application/pdf') return 'PDF';
    if ([
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv', 'application/csv',
    ].includes(mime)) return 'SPREADSHEET';
    if ([
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'application/rtf', 'text/plain', 'text/markdown',
    ].includes(mime)) return 'DOCUMENT';
    if ([
        'application/zip', 'application/x-tar', 'application/gzip',
        'application/x-7z-compressed', 'application/x-rar-compressed', 'application/vnd.rar',
    ].includes(mime)) return 'ARCHIVE';
    return 'OTHER';
}

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
    languageOptions: languages.map((l) => l.name).sort((a, b) => a.localeCompare(b)),

    async init() {
        await this.$store.vault.boot();
        window.addEventListener('vault-unlocked', () => this.load());
        this.initDropzone();
        if (! this.$store.vault.configured) {
            this.state = 'unconfigured';
            return;
        }
        if (! this.$store.vault.unlocked) {
            this.state = 'locked';
            return;
        }
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
            const { data, version } = await Vault.loadManifest('files');
            this.manifest = data;
            this.version = version;
            this.state = 'ready';
        } catch (e) {
            this.state = 'error';
        }
    },

    // Persist the manifest; on a concurrent save, reload and surface it.
    async persist() {
        try {
            this.version = await Vault.saveManifest('files', this.manifest, this.version);
            this.error = '';
        } catch (e) {
            if (e.stale) {
                await this.load();
                this.error = labels.stale;
            } else {
                this.error = labels.saveFailed;
            }
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
        // a flat, vault-wide result set.
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

    typeLabel(file) {
        return labels.types?.[classifyMime(file?.mime)] ?? file?.mime ?? '';
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

    // Append a note to the (independent) notes manifest, reloading once if
    // another tab saved in between (optimistic version).
    async migrateAddNote(note) {
        for (let attempt = 0; attempt < 2; attempt++) {
            const { data, version } = await Vault.loadManifest('notes');
            const manifest = data.notes ? data : { v: 1, notes: [] };
            manifest.notes.push(note);
            try {
                await Vault.saveManifest('notes', manifest, version);
                window.dispatchEvent(new CustomEvent('notes-changed'));
                return true;
            } catch (e) {
                if (! e.stale) throw e;
                // stale: loop reloads the current manifest and retries once
            }
        }
        return false;
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
            const now = new Date().toISOString();
            const ok = await this.migrateAddNote({
                id: crypto.randomUUID(),
                title: (row.name || '').replace(/\.(md|markdown)$/i, ''),
                content: text,
                tags: [],
                pinned: false,
                created: now,
                updated: now,
                trashed: null,
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

    // Download the selection (files + folders, recursively) as one zip built in
    // the browser, folder structure preserved. Large sets build in memory, so
    // confirm above 2 GiB.
    async bulkDownload() {
        const refs = this.selectionRefs;
        if (! refs.length) return;

        const jobs = [];
        const byId = new Map(this.manifest.folders.map((x) => [x.id, x]));
        const folderPath = (id) => {
            const parts = [];
            let cur = id;
            while (cur != null && byId.has(cur)) { parts.unshift(byId.get(cur).name); cur = byId.get(cur).parent; }
            return parts;
        };
        for (const ref of refs) {
            if (ref.kind === 'file') {
                const f = this.manifest.files.find((x) => x.id === ref.id);
                if (f) jobs.push({ file: f, path: f.name });
            } else {
                const tree = this.subtree(ref.id);
                const base = folderPath(byId.get(ref.id)?.parent ?? null);
                for (const f of this.manifest.files) {
                    if (f.folder != null && tree.has(f.folder)) {
                        const rel = folderPath(f.folder).slice(base.length);
                        jobs.push({ file: f, path: [...rel, f.name].join('/') });
                    }
                }
            }
        }
        if (! jobs.length) return;

        const total = jobs.reduce((n, j) => n + (j.file.size || 0), 0);
        if (total > 2 * 1024 * 1024 * 1024 && ! window.confirm(labels.largeZip)) return;

        const zip = new JSZip();
        const used = new Set();
        this.dl = { active: true, done: 0, total: jobs.length };
        for (const job of jobs) {
            try {
                let candidate = job.path;
                let i = 1;
                while (used.has(candidate)) {
                    const dot = job.path.lastIndexOf('.');
                    candidate = dot > 0 ? `${job.path.slice(0, dot)} (${++i})${job.path.slice(dot)}` : `${job.path} (${++i})`;
                }
                used.add(candidate);
                zip.file(candidate, await this.fetchPlain(job.file));
            } catch (e) { /* skip an unfetchable file */ }
            this.dl.done++;
        }

        const blob = await zip.generateAsync({ type: 'blob' });
        saveBlobAs(new Uint8Array(await blob.arrayBuffer()), 'files.zip', 'application/zip');
        this.dl.active = false;
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
                const bytes = new Uint8Array(await item.file.arrayBuffer());
                const { blob, key } = Vault.encryptBlob(bytes);

                const data = new FormData();
                data.append('_token', config.token);
                data.append('blob', blob, 'blob');
                const res = await fetch(config.blobBase, {
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
                    key,
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
        const res = await fetch(`${config.blobBase}/${row.blob}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (! res.ok) throw new Error('fetch failed');
        const cipher = new Uint8Array(await res.arrayBuffer());
        return Vault.decryptBlob(cipher, row.key, row.size);
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

    /* ---- Preview & editor (all in the browser, nothing readable leaves it) ---- */

    async openFile(row) {
        this.dl = { active: true, done: 0, total: 1 };
        try {
            const plain = await this.fetchPlain(row);
            this.dl.active = false;
            const mime = row.mime || 'application/octet-stream';

            if (mime.startsWith('image/')) {
                this.viewer = { open: true, kind: 'image', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            if (mime === 'application/pdf') {
                this.viewer = { open: true, kind: 'pdf', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
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

    mountEditor(text, filename) {
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
        const desc = languages.find((l) => l.name === this.editorLang);
        desc ? this.applyEditorLanguage(desc) : this.editorView.dispatch({ effects: this.langComp.reconfigure([]) });
    },

    applyEditorLanguage(desc) {
        this.editorLang = desc.name;
        desc.load().then((support) => this.editorView.dispatch({ effects: this.langComp.reconfigure(support) }));
    },

    // Save the edited text: encrypt into a NEW blob, point the manifest at it,
    // then discard the old blob — an atomic swap from the manifest's viewpoint.
    async saveText() {
        const row = this.viewer.row;
        if (! this.editorView || ! row) return;
        this.viewer.saving = true;
        this.viewer.saved = false;
        try {
            const bytes = new TextEncoder().encode(this.editorView.state.doc.toString());
            const { blob, key } = Vault.encryptBlob(bytes);

            const data = new FormData();
            data.append('_token', config.token);
            data.append('blob', blob, 'blob');
            const res = await fetch(config.blobBase, {
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
                entry.key = key;
                entry.size = bytes.length;
            }
            await this.persist();

            row.blob = id;
            row.key = key;
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

marked.use({ gfm: true, breaks: true });
// GitHub-style syntax highlighting for fenced code blocks; the hljs output is
// plain spans and survives the DOMPurify pass.
marked.use(markedHighlight({
    langPrefix: 'hljs language-',
    highlight(code, lang) {
        const language = hljs.getLanguage(lang) ? lang : 'plaintext';
        return hljs.highlight(code, { language }).value;
    },
}));

function renderMarkdown(text) {
    // marked renders GFM task checkboxes disabled; strip that so they stay
    // clickable in the preview (the click handler writes back to the source).
    return DOMPurify.sanitize(marked.parse(text ?? '')).replace(/<input disabled(="")?\s/g, '<input ');
}

/* Client-side notes search for the global palette. The manifest is cached in
 * memory and invalidated whenever the notes page saves. Locked vault → no
 * results, nothing leaks. */
let notesSearchCache = null;
window.addEventListener('notes-changed', () => { notesSearchCache = null; });
window.addEventListener('vault-unlocked', () => { notesSearchCache = null; });

async function searchNotesClient(term) {
    if (! Alpine.store('vault').unlocked) return [];
    try {
        if (! notesSearchCache) {
            notesSearchCache = (await Vault.loadManifest('notes')).data;
        }
    } catch (e) {
        return [];
    }
    const q = term.toLowerCase();
    const out = [];
    for (const n of notesSearchCache.notes ?? []) {
        if (n.trashed) continue;
        const title = n.title ?? '';
        const content = n.content ?? '';
        const inTitle = title.toLowerCase().includes(q);
        const at = content.toLowerCase().indexOf(q);
        const inTags = (n.tags ?? []).some((t) => t.toLowerCase().includes(q));
        if (! inTitle && at === -1 && ! inTags) continue;
        const snippet = at !== -1
            ? content.slice(Math.max(0, at - 30), at + q.length + 30).replace(/\s+/g, ' ').trim()
            : (n.tags ?? []).join(', ');
        out.push({
            title: title || '…',
            subtitle: snippet,
            url: `/notes?open=${n.id}`,
        });
        if (out.length >= 5) break;
    }
    return out;
}

/* Client-side bookmark search for the global palette. Same zero-knowledge
 * pattern as notes: the decrypted manifest is memoised and invalidated on save
 * or unlock. */
let bookmarksSearchCache = null;
window.addEventListener('bookmarks-changed', () => { bookmarksSearchCache = null; });
window.addEventListener('vault-unlocked', () => { bookmarksSearchCache = null; });

async function searchBookmarksClient(term) {
    if (! Alpine.store('vault').unlocked) return [];
    try {
        if (! bookmarksSearchCache) {
            bookmarksSearchCache = (await Vault.loadManifest('bookmarks')).data;
        }
    } catch (e) {
        return [];
    }
    const q = term.toLowerCase();
    const out = [];
    for (const b of bookmarksSearchCache.bookmarks ?? []) {
        if (b.trashed) continue;
        const title = b.title ?? '';
        const url = b.url ?? '';
        const hit = title.toLowerCase().includes(q)
            || url.toLowerCase().includes(q)
            || (b.description ?? '').toLowerCase().includes(q)
            || (b.tags ?? []).some((t) => t.toLowerCase().includes(q));
        if (! hit) continue;
        out.push({ title: title || url || '…', subtitle: url, url: `/bookmarks?open=${b.id}` });
        if (out.length >= 5) break;
    }
    return out;
}

Alpine.data('vaultBookmarks', (labels = {}) => ({
    state: 'boot', // boot | locked | unconfigured | ready | error
    manifest: { v: 1, folders: [], bookmarks: [] },
    version: 0,
    cwd: null,
    query: '',
    view: 'active', // active | trash
    activeTag: '',
    error: '',
    dialogOpen: false,
    editingId: null, // null = creating a new bookmark
    form: { url: '', title: '', description: '', tags: '', folder: '', favorite: false, favicon: null },
    fetching: false,
    tagsOpen: false,
    tagsRef: null,
    tagsValue: '',
    moveOpen: false,
    moveRef: null,
    moveTarget: '',
    dragItem: null, // {kind, id} being dragged into a folder

    async init() {
        await this.$store.vault.boot();
        window.addEventListener('vault-unlocked', () => this.load());
        if (! this.$store.vault.configured) { this.state = 'unconfigured'; return; }
        if (! this.$store.vault.unlocked) { this.state = 'locked'; return; }
        await this.load();
    },

    async load() {
        try {
            const { data, version } = await Vault.loadManifest('bookmarks');
            this.manifest = data.bookmarks ? data : { v: 1, folders: [], bookmarks: [] };
            this.version = version;
            this.state = 'ready';
            const open = new URLSearchParams(window.location.search).get('open');
            const b = open && this.manifest.bookmarks.find((x) => x.id === open);
            if (b) this.openEdit(b);
        } catch (e) {
            this.state = 'error';
        }
    },

    async persist() {
        try {
            this.version = await Vault.saveManifest('bookmarks', this.manifest, this.version);
            this.error = '';
            window.dispatchEvent(new CustomEvent('bookmarks-changed'));
        } catch (e) {
            if (e.stale) { await this.load(); this.error = labels.stale; } else { this.error = labels.saveFailed; }
            throw e;
        }
    },

    /* ---- Derived ---- */

    get breadcrumb() {
        const chain = [];
        const byId = new Map(this.manifest.folders.map((f) => [f.id, f]));
        let cur = this.cwd;
        while (cur != null && byId.has(cur)) { chain.unshift(byId.get(cur)); cur = byId.get(cur).parent; }
        return chain;
    },

    get currentFolderName() {
        return this.breadcrumb.length ? this.breadcrumb[this.breadcrumb.length - 1].name : null;
    },

    get rows() {
        const q = this.query.trim().toLowerCase();
        const tag = this.activeTag;
        const flat = q !== '' || tag !== '';
        let bms = this.manifest.bookmarks.filter((b) => (this.view === 'trash' ? b.trashed : ! b.trashed));
        if (! flat) bms = bms.filter((b) => (b.folder ?? null) === this.cwd);
        if (q !== '') {
            bms = bms.filter((b) => (b.title ?? '').toLowerCase().includes(q)
                || (b.url ?? '').toLowerCase().includes(q)
                || (b.description ?? '').toLowerCase().includes(q)
                || (b.tags ?? []).some((t) => t.toLowerCase().includes(q)));
        }
        if (tag !== '') bms = bms.filter((b) => (b.tags ?? []).includes(tag));
        bms = [...bms].sort((a, b) => (Number(b.favorite) - Number(a.favorite)) || (b.updated ?? '').localeCompare(a.updated ?? ''));

        let folders = [];
        if (! flat && this.view === 'active') {
            folders = this.manifest.folders
                .filter((f) => (f.parent ?? null) === this.cwd)
                .map((f) => ({ ...f, kind: 'folder' }))
                .sort((a, b) => a.name.localeCompare(b.name));
        }
        return [...folders, ...bms.map((b) => ({ ...b, kind: 'bookmark' }))];
    },

    get allTags() {
        const set = new Set();
        for (const b of this.manifest.bookmarks) for (const t of b.tags ?? []) set.add(t);
        return [...set].sort((a, b) => a.localeCompare(b));
    },

    get trashCount() {
        return this.manifest.bookmarks.filter((b) => b.trashed).length;
    },

    // All folders as flat, path-labelled options for the move dialog.
    get moveOptions() {
        const byId = new Map(this.manifest.folders.map((f) => [f.id, f]));
        const path = (f) => {
            const parts = [];
            let cur = f;
            while (cur) { parts.unshift(cur.name); cur = cur.parent != null ? byId.get(cur.parent) : null; }
            return parts.join(' / ');
        };
        return this.manifest.folders.map((f) => ({ id: f.id, label: path(f) })).sort((a, b) => a.label.localeCompare(b.label));
    },

    hostname(url) {
        try { return new URL(url).hostname.replace(/^www\./, ''); } catch (e) { return url; }
    },

    /* ---- URL helpers ---- */

    normalizeUrl(u) {
        u = (u || '').trim();
        if (! u) return '';
        if (! /^https?:\/\//i.test(u)) u = `https://${u}`;
        return u;
    },

    isHttpUrl(u) {
        try { const p = new URL(u); return p.protocol === 'http:' || p.protocol === 'https:'; } catch (e) { return false; }
    },

    openBookmark(b) {
        if (this.isHttpUrl(b.url)) window.open(b.url, '_blank', 'noopener,noreferrer');
    },

    /* ---- Add / edit ---- */

    openAdd() {
        this.editingId = null;
        this.form = { url: '', title: '', description: '', tags: '', folder: this.cwd ?? '', favorite: false, favicon: null };
        this.error = '';
        this.dialogOpen = true;
    },

    openEdit(b) {
        const src = this.manifest.bookmarks.find((x) => x.id === b.id) ?? b;
        this.editingId = src.id;
        this.form = {
            url: src.url ?? '', title: src.title ?? '', description: src.description ?? '',
            tags: (src.tags ?? []).join(', '), folder: src.folder ?? '', favorite: !! src.favorite, favicon: src.favicon ?? null,
        };
        this.error = '';
        this.dialogOpen = true;
    },

    async saveBookmark() {
        const url = this.normalizeUrl(this.form.url);
        if (! this.isHttpUrl(url)) { this.error = labels.invalidUrl; return; }
        const tags = [...new Set((this.form.tags || '').split(',').map((t) => t.trim()).filter(Boolean))];
        const now = new Date().toISOString();
        if (this.editingId) {
            const b = this.manifest.bookmarks.find((x) => x.id === this.editingId);
            if (b) {
                Object.assign(b, {
                    url, title: this.form.title || this.hostname(url), description: this.form.description ?? '',
                    tags, folder: this.form.folder || null, favorite: !! this.form.favorite,
                    favicon: this.form.favicon ?? b.favicon ?? null, updated: now,
                });
            }
        } else {
            this.manifest.bookmarks.push({
                id: crypto.randomUUID(), url, title: this.form.title || this.hostname(url),
                description: this.form.description ?? '', tags, favorite: !! this.form.favorite,
                folder: this.form.folder || null, favicon: this.form.favicon ?? null,
                created: now, updated: now, trashed: null,
            });
        }
        this.dialogOpen = false;
        await this.persist().catch(() => {});
    },

    // Best-effort: the browser fetches the page title and favicon directly from
    // the target site (never via our server). Cross-origin reads are usually
    // blocked by CORS, so the title falls back to the hostname and the favicon
    // to none — then the user just types it.
    async fetchMeta() {
        const url = this.normalizeUrl(this.form.url);
        if (! this.isHttpUrl(url)) { this.error = labels.invalidUrl; return; }
        this.form.url = url;
        this.error = '';
        this.fetching = true;
        try {
            try {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 4000);
                const res = await fetch(url, { signal: ctrl.signal, redirect: 'follow' });
                const html = await res.text();
                clearTimeout(timer);
                const title = new DOMParser().parseFromString(html, 'text/html').querySelector('title')?.textContent?.trim();
                if (title) this.form.title = title;
            } catch (e) { /* CORS / opaque / timeout: fall back below */ }
            if (! this.form.title) this.form.title = this.hostname(url);
            this.form.favicon = await this.fetchFavicon(url);
        } finally {
            this.fetching = false;
        }
    },

    async fetchFavicon(url) {
        try {
            const origin = new URL(url).origin;
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), 4000);
            const res = await fetch(`${origin}/favicon.ico`, { signal: ctrl.signal });
            clearTimeout(timer);
            if (! res.ok) return null;
            const blob = await res.blob();
            if (! blob.type.startsWith('image/') || blob.size > 100 * 1024) return null;
            return await new Promise((resolve) => {
                const fr = new FileReader();
                fr.onload = () => resolve(fr.result);
                fr.onerror = () => resolve(null);
                fr.readAsDataURL(blob);
            });
        } catch (e) {
            return null;
        }
    },

    /* ---- Actions ---- */

    async toggleFavorite(b) {
        const t = this.manifest.bookmarks.find((x) => x.id === b.id);
        if (! t) return;
        t.favorite = ! t.favorite;
        await this.persist().catch(() => {});
    },

    openTags(b) {
        this.tagsRef = b.id;
        this.tagsValue = (b.tags ?? []).join(', ');
        this.tagsOpen = true;
    },

    async applyTags() {
        const id = this.tagsRef;
        this.tagsOpen = false;
        this.tagsRef = null;
        const b = id && this.manifest.bookmarks.find((x) => x.id === id);
        if (b) {
            b.tags = [...new Set(this.tagsValue.split(',').map((t) => t.trim()).filter(Boolean))];
            await this.persist().catch(() => {});
        }
    },

    async mkdir(name) {
        name = (name || '').trim();
        if (! name) return;
        this.manifest.folders.push({ id: crypto.randomUUID(), name, parent: this.cwd });
        await this.persist().catch(() => {});
    },

    /* ---- Drag & drop into folders ---- */

    get parentFolderId() {
        const f = this.manifest.folders.find((x) => x.id === this.cwd);
        return f ? (f.parent ?? null) : null;
    },

    folderSubtree(id) {
        const set = new Set([id]);
        let grew = true;
        while (grew) {
            grew = false;
            for (const f of this.manifest.folders) {
                if (f.parent != null && set.has(f.parent) && ! set.has(f.id)) { set.add(f.id); grew = true; }
            }
        }
        return set;
    },

    onDragStart(event, row) {
        this.dragItem = { kind: row.kind, id: row.id };
        event.dataTransfer.effectAllowed = 'move';
        try { event.dataTransfer.setData('text/plain', row.id); } catch (e) { /* ignore */ }
    },

    onDragEnd() {
        this.dragItem = null;
    },

    async dropInto(targetFolderId) {
        const item = this.dragItem;
        this.dragItem = null;
        if (! item) return;
        if (item.kind === 'folder') {
            if (item.id === targetFolderId) return;
            if (targetFolderId !== null && this.folderSubtree(item.id).has(targetFolderId)) return; // no cycle
            const f = this.manifest.folders.find((x) => x.id === item.id);
            if (f && (f.parent ?? null) !== targetFolderId) { f.parent = targetFolderId; await this.persist().catch(() => {}); }
        } else {
            const b = this.manifest.bookmarks.find((x) => x.id === item.id);
            if (b && (b.folder ?? null) !== targetFolderId) { b.folder = targetFolderId; await this.persist().catch(() => {}); }
        }
    },

    // Reparent a folder's children up one level, then drop the folder. Bookmarks
    // are never lost, so no confirmation is needed.
    async deleteFolder(f) {
        const pid = f.parent ?? null;
        this.manifest.bookmarks.forEach((b) => { if ((b.folder ?? null) === f.id) b.folder = pid; });
        this.manifest.folders.forEach((x) => { if ((x.parent ?? null) === f.id) x.parent = pid; });
        this.manifest.folders = this.manifest.folders.filter((x) => x.id !== f.id);
        if (this.cwd === f.id) this.cwd = pid;
        await this.persist().catch(() => {});
    },

    openMove(b) {
        this.moveRef = b.id;
        this.moveTarget = this.manifest.bookmarks.find((x) => x.id === b.id)?.folder ?? '';
        this.moveOpen = true;
    },

    async applyMove() {
        const id = this.moveRef;
        this.moveOpen = false;
        this.moveRef = null;
        const b = id && this.manifest.bookmarks.find((x) => x.id === id);
        if (b) { b.folder = this.moveTarget || null; await this.persist().catch(() => {}); }
    },

    async toTrash(b) {
        const t = this.manifest.bookmarks.find((x) => x.id === b.id);
        if (! t) return;
        t.trashed = new Date().toISOString();
        await this.persist().catch(() => {});
    },

    async restore(b) {
        const t = this.manifest.bookmarks.find((x) => x.id === b.id);
        if (! t) return;
        t.trashed = null;
        await this.persist().catch(() => {});
    },

    async destroyForever(b) {
        this.manifest.bookmarks = this.manifest.bookmarks.filter((x) => x.id !== b.id);
        await this.persist().catch(() => {});
    },

    async emptyTrash() {
        this.manifest.bookmarks = this.manifest.bookmarks.filter((b) => ! b.trashed);
        await this.persist().catch(() => {});
    },
}));

Alpine.data('vaultMail', (labels = {}) => ({
    state: 'boot', // boot | locked | unconfigured | ready | error
    manifest: { v: 1, accounts: [] },
    version: 0,
    error: '',
    busyId: null, // account id currently refreshing
    errors: {}, // per-account error message
    dialogOpen: false,
    editingId: null,
    form: { name: '', host: '', port: 993, encryption: 'ssl', username: '', password: '', validateCert: true },
    deleteOpen: false,
    deleteId: null,
    cacheVersion: 0, // bumped on background sync to re-read cached stats
    reader: {
        open: false, account: null, folderPath: 'INBOX', page: 1, total: 0, perPage: 50,
        messages: [], current: null, loading: false, loadingMore: false, error: '', imagesAllowed: false, busy: false,
        folders: [], foldersLoading: false, sortDir: 'desc', selected: [], deleteChoiceOpen: false, headersOpen: false, emptyChoiceOpen: false,
        transferOpen: false, transferAccount: '', transferFolder: 'INBOX', transferFolderList: [],
    },

    async init() {
        await this.$store.vault.boot();
        window.addEventListener('vault-unlocked', () => this.load());
        // Re-read cached stats when the background sync refreshes them.
        window.addEventListener('mail-synced', () => { this.cacheVersion++; });
        if (! this.$store.vault.configured) { this.state = 'unconfigured'; return; }
        if (! this.$store.vault.unlocked) { this.state = 'locked'; return; }
        await this.load();
    },

    // Prefer freshly background-synced stats from the cache, falling back to the
    // last stats stored in the manifest. Reading cacheVersion makes the template
    // re-evaluate after a sync.
    accountStats(a) {
        void this.cacheVersion;
        return Vault.cacheGet(`stats:${a.id}`) ?? a.stats;
    },

    async load() {
        try {
            const { data, version } = await Vault.loadManifest('mail');
            this.manifest = data.accounts ? data : { v: 1, accounts: [] };
            this.version = version;
            this.state = 'ready';
        } catch (e) {
            this.state = 'error';
        }
    },

    async persist() {
        try {
            this.version = await Vault.saveManifest('mail', this.manifest, this.version);
            this.error = '';
        } catch (e) {
            if (e.stale) { await this.load(); this.error = labels.stale; } else { this.error = labels.saveFailed; }
            throw e;
        }
    },

    openAdd() {
        this.editingId = null;
        this.form = { name: '', host: '', port: 993, encryption: 'ssl', username: '', password: '', validateCert: true };
        this.error = '';
        this.dialogOpen = true;
    },

    openEdit(a) {
        this.editingId = a.id;
        this.form = {
            name: a.name ?? '', host: a.host ?? '', port: a.port ?? 993, encryption: a.encryption ?? 'ssl',
            username: a.username ?? '', password: a.password ?? '', validateCert: a.validateCert !== false,
        };
        this.error = '';
        this.dialogOpen = true;
    },

    async saveAccount() {
        const f = this.form;
        if (! f.host.trim() || ! f.username.trim() || ! f.password) { this.error = labels.saveFailed; return; }
        const now = new Date().toISOString();
        const fields = {
            name: f.name.trim() || f.host.trim(), host: f.host.trim(), port: Number(f.port) || 993,
            encryption: f.encryption, username: f.username.trim(), password: f.password, validateCert: !! f.validateCert,
        };
        if (this.editingId) {
            const a = this.manifest.accounts.find((x) => x.id === this.editingId);
            if (a) Object.assign(a, fields, { updated: now });
        } else {
            this.manifest.accounts.push({ id: crypto.randomUUID(), ...fields, stats: null, created: now, updated: now });
        }
        this.dialogOpen = false;
        await this.persist().catch(() => {});
    },

    confirmDelete(a) { this.deleteId = a.id; this.deleteOpen = true; },

    async applyDelete() {
        this.deleteOpen = false;
        this.manifest.accounts = this.manifest.accounts.filter((x) => x.id !== this.deleteId);
        this.deleteId = null;
        await this.persist().catch(() => {});
    },

    // Decrypted credentials are posted to the stateless stats endpoint only for
    // this fetch; the server never stores them. The result is cached back into
    // the encrypted manifest so it shows instantly next time.
    async refresh(a) {
        if (this.busyId) return;
        this.busyId = a.id;
        this.errors = { ...this.errors, [a.id]: '' };
        try {
            const res = await fetch('/mail/stats', {
                method: 'POST',
                headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    host: a.host, port: a.port, encryption: a.encryption,
                    username: a.username, password: a.password, validate_cert: a.validateCert,
                }),
            });
            if (! res.ok) {
                const body = await res.json().catch(() => ({}));
                this.errors = { ...this.errors, [a.id]: body.message || labels.connectFailed };
                return;
            }
            const stats = await res.json();
            const t = this.manifest.accounts.find((x) => x.id === a.id);
            if (t) {
                t.stats = { ...stats, fetchedAt: new Date().toISOString() };
                await this.persist().catch(() => {});
            }
        } catch (e) {
            this.errors = { ...this.errors, [a.id]: labels.connectFailed };
        } finally {
            this.busyId = null;
        }
    },

    async refreshAll() {
        for (const a of this.manifest.accounts) await this.refresh(a);
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
        return [...(list ?? [])].sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base', numeric: true }));
    },

    /* ---- Reader ---- */

    credsBody(a) {
        return {
            host: a.host, port: a.port, encryption: a.encryption,
            username: a.username, password: a.password, validate_cert: a.validateCert,
        };
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
        this.reader.open = true;
        this.reader.account = a;
        this.reader.current = null;
        this.reader.transferOpen = false;
        this.reader.error = '';
        this.reader.folders = [];
        this.reader.messages = [];
        this.reader.selected = [];
        this.reader.folderPath = 'INBOX';
        this.hydrateFolder();       // messages: cache instantly + background/foreground
        this.loadFoldersAsync(a);   // folder sidebar in parallel, not awaited
    },

    loadFoldersAsync(a) {
        this.reader.foldersLoading = true;
        this.loadFolders(this.credsBody(a)).then((folders) => {
            this.reader.folders = this.sortedFolders(folders);
            // Adopt the server's real INBOX path only if still on the default.
            const inbox = this.reader.folders.find((f) => /^inbox$/i.test(f.name) || /^inbox$/i.test(f.path));
            if (inbox && this.reader.folderPath === 'INBOX' && inbox.path !== 'INBOX') {
                this.reader.folderPath = inbox.path;
                this.hydrateFolder();
            }
        }).finally(() => { this.reader.foldersLoading = false; });
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
        const cached = Vault.cacheGet(`msgs:${this.reader.account.id}:${this.reader.folderPath}`);
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
        return /trash|deleted|papierkorb/i.test(f?.name || this.reader.folderPath || '');
    },

    // Re-fetch this account's stats into the cache so the overview + folder
    // counts update everywhere without waiting for the next background sync.
    async syncAccountStats(a) {
        try {
            const res = await mailPostRaw('/mail/stats', this.credsBody(a));
            if (res.ok) { Vault.cachePut(`stats:${a.id}`, await res.json()); this.cacheVersion++; }
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
    async bulkAction(action, target = null) {
        if (! this.reader.selected.length || this.reader.busy) return;
        this.reader.busy = true;
        this.reader.error = '';
        const done = [];
        try {
            for (const uid of [...this.reader.selected]) {
                const res = await this.mailPost('/mail/message/action', { uid, action, target });
                if (! res.ok) { const b = await res.json().catch(() => ({})); this.reader.error = b.detail || labels.connectFailed; break; }
                done.push(uid);
            }
        } finally {
            if (done.length) this.applyActionLocal(action, done, target);
            this.reader.selected = [];
            this.reader.busy = false;
        }
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
        if (reset) {
            this.reader.page = 1;
            if (! background) { this.reader.messages = []; this.reader.total = 0; }
        }
        this.reader.loading = reset && ! background;
        this.reader.loadingMore = ! reset;
        this.reader.error = '';
        try {
            const res = await this.mailPost('/mail/messages', { page: this.reader.page });
            if (this.reader.folderPath !== folder) return; // folder changed mid-flight → drop
            if (! res.ok) {
                const body = await res.json().catch(() => ({}));
                if (! background) this.reader.error = body.detail || labels.connectFailed;
                return;
            }
            const data = await res.json();
            if (this.reader.folderPath !== folder) return; // changed while parsing
            const rows = data.messages ?? [];
            this.reader.messages = reset ? rows : [...this.reader.messages, ...rows];
            this.reader.total = data.total ?? 0;
            this.cacheList(folder);
        } catch (e) {
            if (! background && this.reader.folderPath === folder) this.reader.error = labels.connectFailed;
        } finally {
            this.reader.loading = false;
            this.reader.loadingMore = false;
        }
    },

    cacheList(folder = null) {
        if (! this.reader.account) return;
        Vault.cachePut(`msgs:${this.reader.account.id}:${folder ?? this.reader.folderPath}`, {
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
        return `msg:${this.reader.account.id}:${folder}:${uid}`;
    },

    async openMsg(uid) {
        // Bind to the folder the click happened in — UIDs are per-folder, so a
        // folder switch mid-fetch must not apply the wrong message.
        const folder = this.reader.folderPath;
        this.reader.imagesAllowed = false;
        // Served from the encrypted cache if this message was already read this
        // session — instant, no re-fetch. The cache is dropped on lock/logout.
        const cached = Vault.cacheGet(this.msgCacheKey(folder, uid));
        if (cached) { this.reader.current = cached; return; }

        this.reader.busy = true;
        try {
            const res = await this.mailPost('/mail/message', { uid, mark_seen: true });
            if (this.reader.folderPath !== folder) return; // folder changed → drop
            if (! res.ok) { const b = await res.json().catch(() => ({})); this.reader.error = b.detail || labels.connectFailed; return; }
            this.reader.current = await res.json();
            Vault.cachePut(this.msgCacheKey(folder, uid), this.reader.current);
            // Opening marks \Seen server-side — reflect it in the list and the
            // folder's unread count without a reload.
            const row = this.reader.messages.find((m) => m.uid === uid);
            if (row && ! row.seen) { row.seen = true; this.bumpFolder(folder, 0, -1); this.cacheList(folder); }
        } catch (e) {
            this.reader.error = labels.connectFailed;
        } finally {
            this.reader.busy = false;
        }
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

    async msgAction(action, target = null) {
        if (! this.reader.current) return;
        const uid = this.reader.current.uid;
        this.reader.busy = true;
        this.reader.error = '';
        try {
            const res = await this.mailPost('/mail/message/action', { uid, action, target });
            if (! res.ok) { const b = await res.json().catch(() => ({})); this.reader.error = b.detail || labels.connectFailed; return; }
            if (action === 'seen' || action === 'unseen') {
                this.reader.current.seen = action === 'seen';
                this.markSeenLocal([uid], action === 'seen');
            } else {
                this.applyActionLocal(action, [uid], target);
                this.reader.current = null;
            }
        } catch (e) {
            this.reader.error = labels.connectFailed;
        } finally {
            this.reader.busy = false;
        }
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
        this.reader.error = '';
        const done = [];
        try {
            for (const uid of uids) {
                const res = await fetch('/mail/message/transfer', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json', 'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        ...this.credsBody(this.reader.account), folder: this.reader.folderPath, uid,
                        target: this.credsBody(a), target_folder: this.reader.transferFolder,
                    }),
                });
                if (! res.ok) { const b = await res.json().catch(() => ({})); this.reader.error = b.detail || labels.connectFailed; break; }
                done.push(uid);
            }
        } catch (e) {
            this.reader.error = labels.connectFailed;
        } finally {
            // Transferred messages leave this account's folder (target is another
            // account, not in this sidebar) — remove them locally, no reload.
            if (done.length) this.removeMessages(done);
            this.reader.transferOpen = false;
            this.reader.current = null;
            this.reader.selected = [];
            this.reader.busy = false;
        }
    },

    async downloadAttachment(att) {
        try {
            const res = await this.mailPost('/mail/message/attachment', { uid: this.reader.current.uid, attachment: att.id });
            if (! res.ok) return;
            saveBlobAs(new Uint8Array(await res.arrayBuffer()), att.name, att.mime);
        } catch (e) { /* ignore */ }
    },

    printMsg() {
        const c = this.reader.current;
        if (! c) return;
        const body = c.html
            ? this.sanitizeEmail(c.html, true)
            : `<pre style="white-space:pre-wrap;word-break:break-word">${escapeHtml(c.text || '')}</pre>`;
        const win = window.open('', '_blank');
        if (! win) return;
        win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(c.subject || '')}</title></head>`
            + `<body style="font-family:system-ui,sans-serif;padding:16px">${body}`
            + '<script>window.onload=function(){window.focus();window.print();};<\/script></body></html>');
        win.document.close();
    },

    fmtAddress(a) {
        if (! a) return '';
        return a.name ? `${a.name} <${a.email}>` : a.email;
    },
}));

Alpine.data('vaultNotes', (labels = {}) => ({
    state: 'boot', // boot | locked | unconfigured | ready | error
    manifest: { v: 1, notes: [] },
    version: 0,
    currentId: null,
    query: '',
    mobilePane: 'list', // list | editor (small screens)
    mode: 'edit', // edit | split | preview
    fullscreen: false,
    previewHtml: '',
    saveState: 'idle', // idle | dirty | saving | saved
    error: '',
    editorView: null,
    saveTimer: null,
    view: 'active', // active | trash
    activeTag: '',
    tagsRef: null,
    tagsOpen: false,
    tagsValue: '',
    shareDialog: false,
    shareExpiry: '86400',
    sharePassword: '',
    shareMaxViews: '',
    shareAllowDownload: false,
    shareLink: '',
    shareBusy: false,
    shareError: '',
    shareCopied: false,

    async init() {
        await this.$store.vault.boot();
        window.addEventListener('vault-unlocked', () => this.load());
        // Flush pending edits when leaving the page.
        window.addEventListener('beforeunload', () => { if (this.saveState === 'dirty') this.saveNow(); });
        if (! this.$store.vault.configured) {
            this.state = 'unconfigured';
            return;
        }
        if (! this.$store.vault.unlocked) {
            this.state = 'locked';
            return;
        }
        await this.load();
    },

    async load() {
        try {
            const { data, version } = await Vault.loadManifest('notes');
            this.manifest = data.notes ? data : { v: 1, notes: [] };
            this.version = version;
            this.state = 'ready';
            const open = new URLSearchParams(window.location.search).get('open');
            if (open && this.manifest.notes.some((n) => n.id === open)) {
                this.open(open);
            }
        } catch (e) {
            this.state = 'error';
        }
    },

    async persist() {
        try {
            this.version = await Vault.saveManifest('notes', this.manifest, this.version);
            this.error = '';
            window.dispatchEvent(new CustomEvent('notes-changed'));
        } catch (e) {
            if (e.stale) {
                await this.load();
                this.error = labels.stale;
            } else {
                this.error = labels.saveFailed;
            }
            throw e;
        }
    },

    get trashCount() {
        return this.manifest.notes.filter((n) => n.trashed).length;
    },

    get allTags() {
        const set = new Set();
        for (const n of this.manifest.notes) {
            for (const t of n.tags ?? []) set.add(t);
        }
        return [...set].sort((a, b) => a.localeCompare(b));
    },

    /* ---- Derived ---- */

    get notes() {
        const q = this.query.trim().toLowerCase();
        let list = this.manifest.notes.filter((n) => this.view === 'trash' ? n.trashed : ! n.trashed);
        if (this.activeTag !== '') {
            list = list.filter((n) => (n.tags ?? []).includes(this.activeTag));
        }
        if (q !== '') {
            // Full-text: title, tags and markdown content, all in memory.
            list = list.filter((n) =>
                (n.title ?? '').toLowerCase().includes(q)
                || (n.tags ?? []).some((t) => t.toLowerCase().includes(q))
                || (n.content ?? '').toLowerCase().includes(q));
        }
        return [...list].sort((a, b) =>
            (Number(b.pinned) - Number(a.pinned)) || (b.updated ?? '').localeCompare(a.updated ?? ''));
    },

    get current() {
        return this.manifest.notes.find((n) => n.id === this.currentId) ?? null;
    },

    excerpt(note) {
        const line = (note.content ?? '').split('\n').map((l) => l.trim())
            .find((l) => l !== '' && ! l.startsWith('#')) ?? '';
        return line.replace(/[*_`>\[\]()#-]/g, '').slice(0, 120);
    },

    fmtDate(iso) {
        return iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '';
    },

    /* ---- CRUD ---- */

    async create() {
        const note = {
            id: crypto.randomUUID(),
            title: '',
            content: '',
            tags: [],
            pinned: false,
            created: new Date().toISOString(),
            updated: new Date().toISOString(),
            trashed: null,
        };
        this.manifest.notes.push(note);
        await this.persist().catch(() => {});
        this.open(note.id);
    },

    open(id) {
        if (this.currentId === id) return;
        if (this.saveState === 'dirty') this.saveNow();
        this.currentId = id;
        this.mode = 'edit';
        this.saveState = 'idle';
        this.mobilePane = 'editor';
        this.$nextTick(() => this.mountEditor(this.current?.content ?? ''));
    },

    closeNote() {
        if (this.saveState === 'dirty') this.saveNow();
        this.destroyEditor();
        this.currentId = null;
        this.mobilePane = 'list';
    },

    mountEditor(content) {
        this.destroyEditor();
        const markDirty = () => this.markDirty();
        const langComp = new Compartment();
        this.editorView = new EditorView({
            parent: this.$refs.noteEditor,
            state: EditorState.create({
                doc: content,
                extensions: [
                    basicSetup,
                    langComp.of([]),
                    EditorView.lineWrapping,
                    EditorView.updateListener.of((update) => { if (update.docChanged) markDirty(); }),
                    EditorView.theme({ '&': { height: '100%' }, '.cm-scroller': { overflow: 'auto' } }),
                ],
            }),
        });
        const md = LanguageDescription.matchFilename(languages, 'note.md');
        if (md) {
            md.load().then((support) => {
                if (this.editorView) {
                    this.editorView.dispatch({ effects: langComp.reconfigure(support) });
                }
            });
        }
    },

    destroyEditor() {
        if (this.editorView) {
            this.editorView.destroy();
            this.editorView = null;
        }
    },

    markDirty() {
        this.saveState = 'dirty';
        // Split view shows a live preview beside the editor.
        if (this.mode === 'split' && this.editorView) {
            this.previewHtml = renderMarkdown(this.editorView.state.doc.toString());
        }
        clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => this.saveNow(), 3000);
    },

    async saveNow() {
        const note = this.current;
        if (! note) return;
        clearTimeout(this.saveTimer);
        if (this.editorView) {
            note.content = this.editorView.state.doc.toString();
        }
        note.updated = new Date().toISOString();
        this.saveState = 'saving';
        try {
            await this.persist();
            this.saveState = 'saved';
        } catch (e) {
            this.saveState = 'dirty';
        }
    },

    // Switch the editor view. The CodeMirror instance stays mounted for edit
    // and split; preview just hides it. Split needs the wider layout, so on a
    // narrow screen it falls back to preview.
    setMode(m) {
        if (m === 'split' && window.innerWidth < 768) m = 'preview';
        if (this.editorView && this.current) {
            this.current.content = this.editorView.state.doc.toString();
        }
        this.mode = m;
        if (m !== 'edit') {
            this.previewHtml = renderMarkdown(this.current?.content ?? '');
        }
    },

    toggleFullscreen() {
        this.fullscreen = ! this.fullscreen;
    },

    async toTrash(note) {
        const target = this.manifest.notes.find((n) => n.id === note.id);
        if (! target) return;
        target.trashed = new Date().toISOString();
        if (this.currentId === note.id) this.closeNote();
        await this.persist().catch(() => {});
    },

    async restore(note) {
        const target = this.manifest.notes.find((n) => n.id === note.id);
        if (! target) return;
        target.trashed = null;
        await this.persist().catch(() => {});
    },

    async destroyForever(note) {
        this.manifest.notes = this.manifest.notes.filter((n) => n.id !== note.id);
        if (this.currentId === note.id) this.closeNote();
        await this.persist().catch(() => {});
    },

    async emptyTrash() {
        this.manifest.notes = this.manifest.notes.filter((n) => ! n.trashed);
        await this.persist().catch(() => {});
    },

    async togglePin(note) {
        const target = this.manifest.notes.find((n) => n.id === note.id);
        if (! target) return;
        target.pinned = ! target.pinned;
        await this.persist().catch(() => {});
    },

    openTags(note) {
        this.tagsRef = note.id;
        this.tagsValue = (note.tags ?? []).join(', ');
        this.tagsOpen = true;
    },

    /* ---- Sharing ---- */

    get activeShares() {
        return this.current?.shares ?? [];
    },

    openShare() {
        this.shareLink = '';
        this.sharePassword = '';
        this.shareMaxViews = '';
        this.shareExpiry = '86400';
        this.shareAllowDownload = false;
        this.shareError = '';
        this.shareCopied = false;
        this.shareDialog = true;
    },

    // Freeze the current note into an encrypted snapshot and register a
    // time-limited public link. The share key never reaches the server: it
    // goes into the link fragment, or (with a password) is wrapped client-side.
    async createShare() {
        const note = this.current;
        if (! note || this.shareBusy) return;
        this.shareBusy = true;
        this.shareError = '';
        this.shareCopied = false;
        try {
            const content = this.exportContent();
            const snapshot = { title: note.title ?? '', content, created: note.created };
            const enc = Vault.shareEncrypt(snapshot);

            const body = {
                cipher: enc.cipher,
                nonce: enc.nonce,
                expires_in: Number(this.shareExpiry),
                has_password: this.sharePassword !== '',
                allow_download: this.shareAllowDownload,
            };
            const maxViews = Number(this.shareMaxViews);
            if (maxViews > 0) body.max_views = maxViews;
            if (this.sharePassword !== '') {
                Object.assign(body, Vault.sharePasswordWrap(enc.key, this.sharePassword));
            }

            const res = await fetch('/shares', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(body),
            });
            if (! res.ok) throw new Error('share failed');
            const data = await res.json();

            // No password → the key travels in the fragment; with a password the
            // recipient derives it, so the link carries no key.
            this.shareLink = this.sharePassword !== ''
                ? data.url
                : `${data.url}#k=${encodeURIComponent(enc.key)}`;

            note.shares = note.shares ?? [];
            note.shares.push({
                id: data.id,
                expires: data.expires_at,
                hasPassword: this.sharePassword !== '',
                allowDownload: this.shareAllowDownload,
                maxViews: maxViews > 0 ? maxViews : null,
                created: new Date().toISOString(),
            });
            await this.persist().catch(() => {});
            this.sharePassword = '';
            this.shareMaxViews = '';
        } catch (e) {
            this.shareError = labels.shareFailed ?? 'Could not create the link.';
        } finally {
            this.shareBusy = false;
        }
    },

    async revokeShare(share) {
        try {
            await fetch(`/shares/${share.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });
        } catch (e) { /* remove locally regardless */ }
        const note = this.current;
        if (note) {
            note.shares = (note.shares ?? []).filter((s) => s.id !== share.id);
            await this.persist().catch(() => {});
        }
    },

    async copyShareLink() {
        try {
            await navigator.clipboard.writeText(this.shareLink);
            this.shareCopied = true;
            setTimeout(() => { this.shareCopied = false; }, 2000);
        } catch (e) { /* clipboard blocked; the field is selectable */ }
    },

    fmtDateTime(iso) {
        return iso ? new Date(iso).toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
        }) : '';
    },

    /* ---- Export ---- */

    // Best-effort filename from the note title; falls back to "note".
    exportName(ext) {
        const base = (this.current?.title ?? '').trim().replace(/[\/\\:*?"<>|]+/g, '-').slice(0, 80);
        return `${base || 'note'}.${ext}`;
    },

    // Sync the CodeMirror buffer into the note before reading its content, so
    // an unsaved edit still exports.
    exportContent() {
        if (this.mode !== 'preview' && this.editorView && this.current) {
            this.current.content = this.editorView.state.doc.toString();
        }
        return this.current?.content ?? '';
    },

    exportMarkdown() {
        if (! this.current) return;
        saveBlobAs(this.exportContent(), this.exportName('md'), 'text/markdown;charset=utf-8');
    },

    // Zero-knowledge PDF: render the markdown into an offscreen element styled
    // with the app's github-markdown-css and let html2pdf build a finished PDF
    // that downloads directly — no print dialog, nothing leaves the browser.
    exportPdf() {
        if (! this.current) return;
        const el = document.createElement('div');
        el.className = 'markdown-body';
        el.style.cssText = 'max-width:46rem;margin:0 auto;padding:24px;background:#fff;';
        el.innerHTML = renderMarkdown(this.exportContent());
        document.body.appendChild(el);
        html2pdf().set({
            filename: this.exportName('pdf'),
            margin: [10, 10, 10, 10],
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
        }).from(el).save().finally(() => el.remove());
    },

    async applyTags() {
        const id = this.tagsRef;
        this.tagsOpen = false;
        this.tagsRef = null;
        if (! id) return;
        const note = this.manifest.notes.find((n) => n.id === id);
        if (note) {
            note.tags = [...new Set(this.tagsValue.split(',').map((t) => t.trim()).filter(Boolean))];
            await this.persist().catch(() => {});
        }
    },

    // Toggle the nth GFM task checkbox from the rendered preview back into the
    // markdown source, save, and re-render — checkboxes stay interactive.
    async togglePreviewTask(event) {
        const box = event.target;
        if (box.tagName !== 'INPUT' || box.type !== 'checkbox' || ! this.current) return;
        const boxes = [...this.$refs.notePreview.querySelectorAll('input[type=checkbox]')];
        const index = boxes.indexOf(box);
        if (index === -1) return;

        let i = -1;
        this.current.content = this.current.content.replace(/(-\s\[)([ xX])(\])/g, (m, a, mark, c) => {
            i++;
            if (i !== index) return m;
            return a + (mark === ' ' ? 'x' : ' ') + c;
        });
        this.previewHtml = renderMarkdown(this.current.content);
        this.current.updated = new Date().toISOString();
        await this.persist().catch(() => {});
    },
}));

// Public viewer for a shared note. Runs on a page with no unlocked vault: it
// fetches the ciphertext, then decrypts it with the key from the URL fragment
// or from a password the recipient types. Nothing is sent back to the server.
Alpine.data('sharedNote', (config = {}, labels = {}) => ({
    id: config.id,
    state: 'loading', // loading | password | ready | error
    errorMsg: '',
    password: '',
    payload: null,
    title: '',
    html: '',
    content: '',

    get canDownload() {
        return this.state === 'ready' && !! this.payload?.allow_download;
    },

    async init() {
        await Vault.ensureReady();
        let res;
        try {
            res = await fetch(`/s/${this.id}/data`, { headers: { Accept: 'application/json' } });
        } catch (e) {
            return this.fail(labels.error);
        }
        if (res.status === 410 || res.status === 404) return this.fail(labels.gone);
        if (! res.ok) return this.fail(labels.error);
        this.payload = await res.json();

        if (this.payload.has_password) {
            this.state = 'password';
            return;
        }
        this.reveal();
    },

    // No password: the share key is in the fragment (#k=…), never sent to the
    // server. Decrypt directly.
    reveal() {
        const hash = window.location.hash;
        const key = hash.startsWith('#k=') ? decodeURIComponent(hash.slice(3)) : '';
        if (! key) return this.fail(labels.no_key);
        try {
            this.render(Vault.shareDecrypt(this.payload.cipher, this.payload.nonce, key));
        } catch (e) {
            this.fail(labels.error);
        }
    },

    submitPassword() {
        this.errorMsg = '';
        try {
            const key = Vault.sharePasswordUnwrap(this.payload, this.password);
            this.render(Vault.shareDecrypt(this.payload.cipher, this.payload.nonce, key));
        } catch (e) {
            this.errorMsg = labels.wrong_password;
        }
    },

    render(snapshot) {
        this.title = snapshot.title || labels.untitled;
        this.content = snapshot.content || '';
        this.html = renderMarkdown(this.content);
        document.title = `${this.title} — Ledgerline`;
        this.state = 'ready';
    },

    // Filename from the shared note's title; falls back to "note".
    downloadName(ext) {
        const base = (this.title ?? '').trim().replace(/[\/\\:*?"<>|]+/g, '-').slice(0, 80);
        return `${base || 'note'}.${ext}`;
    },

    downloadMarkdown() {
        if (! this.canDownload) return;
        saveBlobAs(this.content, this.downloadName('md'), 'text/markdown;charset=utf-8');
    },

    downloadPdf() {
        if (! this.canDownload) return;
        const el = document.createElement('div');
        el.className = 'markdown-body';
        el.style.cssText = 'max-width:46rem;margin:0 auto;padding:24px;background:#fff;';
        el.innerHTML = this.html;
        document.body.appendChild(el);
        html2pdf().set({
            filename: this.downloadName('pdf'),
            margin: [10, 10, 10, 10],
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
        }).from(el).save().finally(() => el.remove());
    },

    fail(message) {
        this.errorMsg = message;
        this.state = 'error';
    },
}));

Alpine.plugin(intersect);

window.Alpine = Alpine;

// Boot the vault store on every page: it restores a cached (unlocked) vault key
// from sessionStorage so encrypted files decrypt on the detail/edit pages too,
// not only on the files browser where the unlock panel lives.
document.addEventListener('alpine:init', () => Alpine.store('vault').boot());

// Background mail sync: while the vault is unlocked, refresh each account's
// stats and INBOX headers into the encrypted client cache so the overview and
// reader show instantly. Runs only client-side (the server never stores the
// IMAP credentials) at the configured interval and on tab focus.
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
            window.addEventListener('vault-unlocked', () => setTimeout(() => this.tick(), 1500));
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
            if (this.running || ! Vault.unlocked()) return;
            this.running = true;
            try {
                const { data } = await Vault.loadManifest('mail');
                for (const a of data.accounts ?? []) {
                    const creds = {
                        host: a.host, port: a.port, encryption: a.encryption,
                        username: a.username, password: a.password, validate_cert: a.validateCert,
                    };
                    try {
                        const s = await mailPostRaw('/mail/stats', creds);
                        if (s.ok) Vault.cachePut(`stats:${a.id}`, await s.json());
                    } catch (e) { /* skip */ }
                    try {
                        const m = await mailPostRaw('/mail/messages', { ...creds, folder: 'INBOX', page: 1 });
                        if (m.ok) { const d = await m.json(); Vault.cachePut(`msgs:${a.id}:INBOX`, { messages: d.messages ?? [], total: d.total ?? 0, ts: Date.now() }); }
                    } catch (e) { /* skip */ }
                }
                this.lastSync = Date.now();
                window.dispatchEvent(new CustomEvent('mail-synced'));
            } catch (e) {
                /* vault locked or manifest unavailable */
            } finally {
                this.running = false;
            }
        },
    });
});

Alpine.start();
