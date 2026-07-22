// Explore map module (ZK). Tracks, photo↔track couplings and the coupling
// tolerances live in the sealed `explore` module store (window.LLModuleStore.
// explore); the server only ever sees ciphertext. Gallery photos are read from
// the already-decrypted gallery index (window.LLGalleryStore.data.photos) —
// their sealed EXIF lat/lng place a pin, and photos without GPS are placed by
// matching their capture time against an imported track (matchPhotoToTracks +
// interpolatePosition). MapLibre renders same-origin tiles relayed via /maps.
//
// Everything heavy is lazy: MapLibre (mapLibre()), uPlot (loadUplot()) and the
// KMZ unzip (fflate, dynamic import) are only pulled when the view needs them,
// so none of them touch the startup bundle.

import { bootStore, bootGalleryStore } from '../shared/zk-module';
import { parseTrack, parseTrackBinary } from '../shared/track-parse';
import { matchPhotoToTracks } from '../shared/photo-track-match';
import { mapLibre } from '../shared/lazy-loaders';
import { loadUplot } from '../shared/uplot-loader';
import { fetchDecryptWorker, thumbLane } from '../shared/blob-io';
import { padBlob } from '../shared/padme';

// Distinct, deterministic polyline colours cycled per track (iOS-ish accents).
const TRACK_COLORS = ['#7066f5', '#3b9fd6', '#59ad6b', '#e2915a', '#d9a441', '#3fae9f', '#9e70fa', '#ef4444'];

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | ready | error
    view: 'media', // media | tracks
    error: '',
    busy: false,
    importing: false,

    // Store-bound collections (aliased to the sealed store's own objects so
    // mutations persist through touch()). `_mut` is bumped on every save so
    // store-derived getters recompute (the store data is NOT an Alpine proxy).
    tracks: [],
    couplings: {},
    settings: { couplingTimeToleranceS: 3600, couplingDistanceToleranceM: 100 },
    _mut: 0,

    photos: [],          // gallery photos (best-effort; [] when gallery empty/locked)
    thumbs: {},          // photoId -> decrypted object URL (for media pins/popups)
    _thumbPending: {},

    selectedTrackId: null,
    settingsOpen: false,
    assignFor: null,     // mediaId currently choosing a manual track, or null

    // Persisted map camera so it survives the media↔tracks view toggle.
    _cam: { center: null, zoom: null },
    _map: null,
    _mapReady: false,
    _markers: [],        // MapLibre Marker instances (media pins)
    _trackLayerIds: [],  // GeoJSON source/layer ids currently on the map
    _hoverMarker: null,  // elevation-profile hover position marker
    _chart: null,        // uPlot elevation instance
    _chartAbort: null,

    async init() {
        await this._boot();
        this.$watch('$store.vault.unlocked', async (on) => {
            if (on && this.state !== 'ready') await this._boot();
            if (! on) this._onLock();
        });
        // Re-render map contents when the view flips or the data mutates.
        this.$watch('view', () => this._renderView());
        this.$watch('_mut', () => this._renderView());
        this.$watch('selectedTrackId', () => { this._renderView(); this.$nextTick(() => this.renderElevation()); });
    },

    async _boot() {
        this.state = 'boot';
        try {
            if (! await bootStore(this.$store, 'explore')) { this.state = 'locked'; return; }
        } catch (e) { this.state = 'error'; return; }

        const data = window.LLModuleStore.explore.data;
        this.tracks = data.tracks;
        this.couplings = data.couplings;
        this.settings = data.settings;

        // Gallery photos are best-effort — Explore still works with none.
        try {
            if (await bootGalleryStore(this.$store)) {
                this.photos = (window.LLGalleryStore.data.photos || []).filter((p) => ! p.trashed);
            }
        } catch (e) { this.photos = []; }

        this.state = 'ready';
        this.$nextTick(() => this._initMap());
    },

    _onLock() {
        this.state = 'locked';
        this.tracks = [];
        this.couplings = {};
        this.photos = [];
        this._revokeThumbs();
        this._destroyChart();
        this._destroyMap();
        window.LLModuleStore.explore.reset();
    },

    // Persist the sealed store (debounced) after a mutation.
    _save() { this._mut++; window.LLModuleStore.explore.touch(); },

    /* ---------------------------------------------------------------- Map */

    async _initMap() {
        const el = this.$refs.map;
        if (! el || this._map) return;
        let maplibregl;
        try { maplibregl = await mapLibre(); } catch (e) { this.error = labels.loadFailed || 'map load failed'; return; }
        this.__ml = maplibregl; // cache so sync draw helpers can reach the module
        if (! el.isConnected) return;
        try {
            this._map = new maplibregl.Map({
                container: el,
                style: config.styleUrl,
                center: this._cam.center || [0, 20],
                zoom: this._cam.zoom ?? 1.5,
                attributionControl: true,
            });
            this._map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
            this._map.on('load', () => { this._mapReady = true; this._renderView(); this.fitToData(); });
            // Keep the camera across the view toggle.
            this._map.on('moveend', () => {
                if (! this._map) return;
                const c = this._map.getCenter();
                this._cam = { center: [c.lng, c.lat], zoom: this._map.getZoom() };
            });
        } catch (e) {
            this.error = labels.mapUnavailable || 'map unavailable';
        }
    },

    _destroyMap() {
        this._clearTrackLayers();
        this._clearMarkers();
        if (this._map) { try { this._map.remove(); } catch (e) { /* ignore */ } this._map = null; }
        this._mapReady = false;
    },

    _clearMarkers() {
        for (const m of this._markers) { try { m.remove(); } catch (e) { /* ignore */ } }
        this._markers = [];
    },

    _clearTrackLayers() {
        if (! this._map) { this._trackLayerIds = []; return; }
        for (const id of this._trackLayerIds) {
            try { if (this._map.getLayer(id)) this._map.removeLayer(id); } catch (e) { /* ignore */ }
            try { if (this._map.getSource(id)) this._map.removeSource(id); } catch (e) { /* ignore */ }
        }
        this._trackLayerIds = [];
    },

    // Draw whatever the active view needs onto the map.
    _renderView() {
        void this._mut;
        if (! this._map || ! this._mapReady) return;
        this._clearTrackLayers();
        this._clearMarkers();
        if (this.view === 'tracks') this._drawTracks();
        else this._drawMediaPins();
    },

    _drawTracks() {
        this.tracks.forEach((track, i) => {
            const coords = (track.points || []).map((p) => [p.lng, p.lat]);
            if (coords.length < 2) return;
            const id = 'track-' + track.id;
            const color = TRACK_COLORS[i % TRACK_COLORS.length];
            const selected = this.selectedTrackId === track.id;
            try {
                this._map.addSource(id, { type: 'geojson', data: { type: 'Feature', geometry: { type: 'LineString', coordinates: coords }, properties: {} } });
                this._map.addLayer({
                    id,
                    type: 'line',
                    source: id,
                    layout: { 'line-join': 'round', 'line-cap': 'round' },
                    paint: { 'line-color': color, 'line-width': selected ? 5 : 3, 'line-opacity': selected || ! this.selectedTrackId ? 0.9 : 0.4 },
                });
                this._trackLayerIds.push(id);
                this._map.on('click', id, () => { this.selectedTrackId = track.id; });
            } catch (e) { /* ignore a track that fails to add */ }
        });
    },

    async _drawMediaPins() {
        const maplibregl = this._maplibre;
        if (! maplibregl) return;
        for (const p of this.placedMedia) {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'explore-pin';
            el.style.cssText = 'width:34px;height:34px;border-radius:9px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);background:#7066f5 center/cover no-repeat;cursor:pointer;padding:0;';
            const marker = new maplibregl.Marker({ element: el }).setLngLat([p._lng, p._lat]).addTo(this._map);
            this._markers.push(marker);
            // Lazy thumbnail into the pin background (bounded worker lane).
            this._thumbFor(p).then((url) => { if (url) el.style.backgroundImage = `url("${url}")`; });
        }
    },

    // Recenter/zoom to fit everything currently placed.
    fitToData() {
        if (! this._map || ! this._mapReady) return;
        const pts = [];
        if (this.view === 'tracks') {
            for (const t of this.tracks) for (const p of (t.points || [])) pts.push([p.lng, p.lat]);
        } else {
            for (const m of this.placedMedia) pts.push([m._lng, m._lat]);
        }
        if (pts.length === 0) return;
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        for (const [x, y] of pts) { if (x < minX) minX = x; if (x > maxX) maxX = x; if (y < minY) minY = y; if (y > maxY) maxY = y; }
        try { this._map.fitBounds([[minX, minY], [maxX, maxY]], { padding: 48, maxZoom: 15, duration: 400 }); } catch (e) { /* ignore */ }
    },

    // Cache the resolved maplibre module so draw helpers can reach it sync.
    get _maplibre() { return this.__ml || null; },

    /* -------------------------------------------------------- Media placement */

    // Photos with a resolved map position: EXIF GPS first, else the coupling's
    // interpolated lat/lng. Returns copies carrying _lat/_lng/_source.
    get placedMedia() {
        void this._mut;
        const out = [];
        for (const p of this.photos) {
            const lat = p.lat != null ? parseFloat(p.lat) : null;
            const lng = p.lng != null ? parseFloat(p.lng) : null;
            if (lat != null && lng != null && Number.isFinite(lat) && Number.isFinite(lng)) {
                out.push({ ...p, _lat: lat, _lng: lng, _source: 'exif' });
                continue;
            }
            const c = this.couplings[p.id];
            if (c && c.lat != null && c.lng != null) {
                const cl = parseFloat(c.lat), cg = parseFloat(c.lng);
                if (Number.isFinite(cl) && Number.isFinite(cg)) out.push({ ...p, _lat: cl, _lng: cg, _source: c.source || 'interpolated' });
            }
        }
        return out;
    },
    placedCount() { return this.placedMedia.length; },

    /* --------------------------------------------------------------- Import */

    // File input change handler: read + parse each file, push the track, seal
    // the raw bytes (best-effort), then re-match photos.
    async onImport(event) {
        const files = [...(event.target.files || [])];
        event.target.value = ''; // allow re-picking the same file
        if (! files.length) return;
        this.importing = true;
        this.error = '';
        try {
            for (const file of files) {
                try { await this._importOne(file); }
                catch (e) { this.error = (labels.importFailed || 'Import failed') + ': ' + ((e && e.message) || e); }
            }
            await this.matchPhotos();
        } finally {
            this.importing = false;
        }
    },

    async _importOne(file) {
        const name = file.name || 'track';
        const ext = (name.split('.').pop() || '').toLowerCase();
        let track;
        let rawForBlob = null; // ArrayBuffer or string to seal

        if (ext === 'fit') {
            const buf = await file.arrayBuffer();
            track = parseTrackBinary(buf, name);
            rawForBlob = buf;
        } else if (ext === 'kmz') {
            const buf = await file.arrayBuffer();
            const kml = await this._extractKmlFromKmz(new Uint8Array(buf));
            track = parseTrack(kml, name.replace(/\.kmz$/i, '.kml'));
            rawForBlob = buf;
        } else {
            // gpx / kml / tcx — text, sniffed if extension unknown.
            const text = await file.text();
            track = parseTrack(text, name);
            rawForBlob = text;
        }

        const entry = { id: window.LLModuleStore.explore.newId(), ...track, importedAt: new Date().toISOString(), rawBlobId: null, rawBlobKey: null };

        // Best-effort seal of the original file so it can be re-exported later;
        // never blocks the import (a failed upload just leaves rawBlobId null).
        try {
            const sealed = await this._sealRaw(rawForBlob, name);
            entry.rawBlobId = sealed.id;
            entry.rawBlobKey = sealed.key;
        } catch (e) { /* optional */ }

        this.tracks.push(entry);
        this._save();
        this.selectedTrackId = entry.id;
        this.view = 'tracks';
    },

    // Unzip a KMZ (a zip) with fflate and return the inner KML text. Picks the
    // first *.kml entry (KMZ convention is a single doc.kml at the root).
    async _extractKmlFromKmz(bytes) {
        const { unzipSync, strFromU8 } = await import('fflate');
        const files = unzipSync(bytes);
        const kmlName = Object.keys(files).find((n) => /\.kml$/i.test(n));
        if (! kmlName) throw new Error(labels.kmzNoKml || 'No KML in KMZ');
        return strFromU8(files[kmlName]);
    },

    // Encrypt the raw track bytes with a fresh per-blob key (Padmé-padded) and
    // upload; returns { id, key }. The plaintext never leaves the browser
    // un-sealed — the id + key live sealed inside the manifest for later export.
    async _sealRaw(raw, name) {
        const bytes = raw instanceof ArrayBuffer ? new Uint8Array(raw)
            : (raw instanceof Uint8Array ? raw : new TextEncoder().encode(String(raw)));
        const enc = window.Vault.encryptContent(bytes, { name, mime: 'application/octet-stream' });
        const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
        const data = new FormData();
        data.append('_token', config.token);
        data.append('file', cipher, cipher.name);
        const res = await fetch(config.uploadUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
        if (! res.ok) throw new Error('upload failed');
        const d = await res.json();
        return { id: d.id, key: enc.encFileKey };
    },

    /* ------------------------------------------------------- Photo coupling */

    // Match every gallery photo against the imported tracks using the current
    // tolerances, writing results into data.couplings. A photo already given a
    // manual coupling is left untouched.
    async matchPhotos() {
        if (! this.tracks.length || ! this.photos.length) return;
        this.busy = true;
        try {
            const matchTracks = this.tracks.map((t) => ({ id: t.id, points: t.points || [] }));
            const opts = { timeToleranceS: this.settings.couplingTimeToleranceS, distanceToleranceM: this.settings.couplingDistanceToleranceM };
            let n = 0;
            for (const p of this.photos) {
                if (this.couplings[p.id] && this.couplings[p.id].source === 'manual') continue;
                const photoLat = p.lat != null ? parseFloat(p.lat) : null;
                const photoLng = p.lng != null ? parseFloat(p.lng) : null;
                const photoTime = p.taken_at ? Date.parse(p.taken_at) : (p.created ? Date.parse(p.created) : null);
                const m = matchPhotoToTracks(
                    { photoLat, photoLng, photoTime: Number.isFinite(photoTime) ? photoTime : null },
                    matchTracks, opts,
                );
                if (m.source === 'none') { delete this.couplings[p.id]; continue; }
                this.couplings[p.id] = { trackId: m.trackId, source: m.source, lat: m.lat ?? null, lng: m.lng ?? null };
                n++;
            }
            this._save();
            window.llToast?.((labels.matched || ':n matched').replace(':n', n));
        } finally {
            this.busy = false;
        }
    },

    // Manually pin a photo to a specific track (interpolated position by time),
    // marking the coupling as 'manual' so a re-match won't overwrite it.
    assignToTrack(mediaId, trackId) {
        const p = this.photos.find((x) => x.id === mediaId);
        const track = this.tracks.find((t) => t.id === trackId);
        if (! p || ! track) return;
        const photoTime = p.taken_at ? Date.parse(p.taken_at) : (p.created ? Date.parse(p.created) : null);
        const matchTracks = [{ id: track.id, points: track.points || [] }];
        const m = matchPhotoToTracks(
            { photoLat: null, photoLng: null, photoTime: Number.isFinite(photoTime) ? photoTime : null },
            matchTracks,
            { timeToleranceS: Number.MAX_SAFE_INTEGER / 2000, distanceToleranceM: 0 },
        );
        const lat = p.lat != null ? parseFloat(p.lat) : (m.lat ?? null);
        const lng = p.lng != null ? parseFloat(p.lng) : (m.lng ?? null);
        this.couplings[mediaId] = { trackId, source: 'manual', lat, lng };
        this.assignFor = null;
        this._save();
    },

    clearCoupling(mediaId) {
        delete this.couplings[mediaId];
        this._save();
    },

    couplingLabel(mediaId) {
        const c = this.couplings[mediaId];
        const src = c ? c.source : 'none';
        return {
            exif: labels.sourceExif, interpolated: labels.sourceInterpolated,
            manual: labels.sourceManual, none: labels.sourceNone,
        }[src] || labels.sourceNone || 'Unplaced';
    },

    /* -------------------------------------------------------- Settings */

    saveSettings() {
        this.settings.couplingTimeToleranceS = Math.max(0, parseInt(this.settings.couplingTimeToleranceS, 10) || 0);
        this.settings.couplingDistanceToleranceM = Math.max(0, parseInt(this.settings.couplingDistanceToleranceM, 10) || 0);
        this._save();
        this.settingsOpen = false;
        this.matchPhotos();
    },

    /* -------------------------------------------------------- Track actions */

    selectTrack(id) { this.selectedTrackId = this.selectedTrackId === id ? null : id; },

    get selectedTrack() { void this._mut; return this.tracks.find((t) => t.id === this.selectedTrackId) || null; },

    async deleteTrack(track) {
        if (! await this.$store.confirm.ask(labels.deleteTrackConfirm || 'Delete this track?')) return;
        const idx = this.tracks.findIndex((t) => t.id === track.id);
        if (idx < 0) return;
        // Drop couplings that pointed at this track.
        for (const [mid, c] of Object.entries(this.couplings)) if (c.trackId === track.id) delete this.couplings[mid];
        this.tracks.splice(idx, 1);
        if (this.selectedTrackId === track.id) this.selectedTrackId = null;
        this._save();
    },

    trackColor(track) {
        const i = this.tracks.findIndex((t) => t.id === track.id);
        return TRACK_COLORS[(i < 0 ? 0 : i) % TRACK_COLORS.length];
    },

    /* --------------------------------------------------- Formatting helpers */

    fmtDistance(m) {
        if (! (m > 0)) return '0 ' + (labels.unitKm || 'km');
        return (m / 1000).toFixed(m < 10000 ? 2 : 1) + ' ' + (labels.unitKm || 'km');
    },
    fmtDuration(s) {
        s = Math.round(s || 0);
        const h = Math.floor(s / 3600), min = Math.floor((s % 3600) / 60);
        return h > 0 ? `${h}h ${min}m` : `${min}m`;
    },
    fmtSpeed(mps) { return ((mps || 0) * 3.6).toFixed(1) + ' ' + (labels.unitKmh || 'km/h'); },
    fmtEle(m) { return m == null ? '—' : Math.round(m) + ' ' + (labels.unitM || 'm'); },

    /* ------------------------------------------------------ Elevation chart */

    _destroyChart() {
        if (this._chartAbort) { this._chartAbort.abort(); this._chartAbort = null; }
        if (this._chart) { try { this._chart.destroy(); } catch (e) { /* ignore */ } this._chart = null; }
    },

    // Render an elevation-over-distance profile for the selected track; hovering
    // the chart drops a marker at the corresponding position on the map.
    async renderElevation() {
        this._destroyChart();
        const track = this.selectedTrack;
        const el = this.$refs.elevation;
        if (! el || ! track) return;
        const profile = (track.stats && track.stats.elevationProfile) || [];
        const usable = profile.filter((pt) => pt.eleM != null);
        if (usable.length < 2) return;

        const xs = profile.map((pt) => pt.distM / 1000); // km
        const ys = profile.map((pt) => (pt.eleM == null ? null : pt.eleM));

        const UPlot = await loadUplot();
        if (! el.isConnected) return;
        const isDark = document.documentElement.classList.contains('dark');
        const grid = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const axis = isDark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.35)';

        const opts = {
            width: el.clientWidth || 600,
            height: 160,
            cursor: { drag: { x: false, y: false } },
            scales: { x: { time: false }, y: {} },
            axes: [
                { stroke: axis, grid: { stroke: grid, width: 1 }, ticks: { stroke: grid, width: 1 }, values: (_u, vals) => vals.map((v) => v.toFixed(1)) },
                { stroke: axis, grid: { stroke: grid, width: 1 }, ticks: { stroke: grid, width: 1 } },
            ],
            series: [{}, { label: labels.elevation || 'Elevation', stroke: '#7066f5', fill: 'rgba(112,102,245,0.12)', width: 2, spanGaps: true }],
            hooks: {
                setCursor: [(u) => {
                    const idx = u.cursor.idx;
                    if (idx == null) { this._hideHover(); return; }
                    const pt = track.points && track.points[idx];
                    if (pt) this._showHover(pt.lat, pt.lng);
                }],
            },
        };
        this._chart = new UPlot(opts, [xs, ys], el);

        this._chartAbort = new AbortController();
        el.addEventListener('mouseleave', () => this._hideHover(), { signal: this._chartAbort.signal });
    },

    async _showHover(lat, lng) {
        if (! this._map || ! this._mapReady) return;
        const maplibregl = this._maplibre;
        if (! maplibregl) return;
        if (! this._hoverMarker) {
            const el = document.createElement('div');
            el.style.cssText = 'width:14px;height:14px;border-radius:50%;background:#7066f5;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.5);';
            this._hoverMarker = new maplibregl.Marker({ element: el });
        }
        this._hoverMarker.setLngLat([lng, lat]).addTo(this._map);
    },
    _hideHover() { if (this._hoverMarker) { try { this._hoverMarker.remove(); } catch (e) { /* ignore */ } } },

    /* --------------------------------------------------------- Thumbnails */

    async _thumbFor(p) {
        if (! p.thumbRef) return '';
        if (this.thumbs[p.id]) return this.thumbs[p.id];
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
        this.thumbs = {};
        this._thumbPending = {};
    },
});
