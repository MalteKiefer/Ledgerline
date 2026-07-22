// Explore map module (ZK). Tracks, photo↔track couplings and the coupling
// tolerances live in the sealed `explore` module store (window.LLModuleStore.
// explore); the server only ever sees ciphertext. Gallery photos are read from
// the already-decrypted gallery index (window.LLGalleryStore.data.photos) —
// their sealed EXIF lat/lng place a pin, and photos without GPS are placed by
// matching their capture time against an imported track (matchPhotoToTracks +
// interpolatePosition). Leaflet renders raster OpenStreetMap tiles as <img>
// (allowed by the CSP img-src) — same tile layer the gallery location-picker
// and viewer mini-map use; no tile relay / tileserver is involved.
//
// Everything heavy is lazy: Leaflet (loadLeaflet()), uPlot (loadUplot()) and the
// KMZ unzip (fflate, dynamic import) are only pulled when the view needs them,
// so none of them touch the startup bundle.

import { bootStore, bootGalleryStore } from '../shared/zk-module';
import { parseTrack, parseTrackBinary } from '../shared/track-parse';
import { matchPhotoToTracks } from '../shared/photo-track-match';
import { loadLeaflet } from '../shared/lazy-loaders';
import { loadUplot } from '../shared/uplot-loader';
import { fetchDecryptWorker, thumbLane } from '../shared/blob-io';
import { padBlob } from '../shared/padme';
import { buildPlannedTrack, hasElevation, downsampleProfile } from '../shared/explore-detail';

// Distinct, deterministic polyline colours cycled per track (iOS-ish accents).
const TRACK_COLORS = ['#7066f5', '#3b9fd6', '#59ad6b', '#e2915a', '#d9a441', '#3fae9f', '#9e70fa', '#ef4444'];

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | ready | error
    view: 'media', // media | tracks | detail
    error: '',
    busy: false,
    importing: false,

    // Client-side search over the decrypted data (never leaves the browser).
    trackQuery: '',
    mediaQuery: '',

    // Inline rename editor: the track id being renamed, plus the working name.
    renamingId: null,
    renameValue: '',

    // Tour-planning (hand-drawn route) mode.
    planning: false,
    planPoints: [],       // ordered [lat, lng] waypoints while drawing
    _planLayer: null,     // Leaflet polyline of the growing route
    _planMarkers: [],     // small vertex markers
    _planClick: null,     // bound map click handler (removed on exit)

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

    // Persisted map camera ([lat, lng] + zoom) so it survives the media↔tracks
    // view toggle. The Leaflet map itself is never torn down on toggle.
    _cam: { center: null, zoom: null },
    _map: null,
    _mapReady: false,
    _markers: [],        // Leaflet media-pin marker layers
    _trackLayers: [],    // Leaflet polyline layers currently on the map
    _hoverMarker: null,  // elevation-profile hover position marker (circleMarker)
    _L: null,            // resolved Leaflet module (sync access for draw helpers)
    _chart: null,        // uPlot elevation instance
    _chartAbort: null,

    async init() {
        await this._boot();
        this.$watch('$store.vault.unlocked', async (on) => {
            if (on && this.state !== 'ready') await this._boot();
            if (! on) this._onLock();
        });
        // Re-render map contents when the view flips or the data mutates.
        this.$watch('view', () => {
            if (this.view !== 'tracks' && this.planning) this._exitPlan();
            this._renderView();
            if (this.view === 'detail') { this.$nextTick(() => { this.fitToData(); this.renderElevation(); }); }
        });
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
        this.trackQuery = '';
        this.mediaQuery = '';
        this.renamingId = null;
        this._exitPlan();
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
        let L;
        try { L = await loadLeaflet(); } catch (e) { this.error = labels.loadFailed || 'map load failed'; return; }
        this._L = L; // cache so sync draw helpers can reach the module
        if (! el.isConnected) return;
        try {
            this._map = L.map(el).setView(this._cam.center || [20, 0], this._cam.zoom ?? 2);
            // Raster OSM tiles as <img> — same layer as the gallery location
            // picker; allowed by the CSP img-src, no tile relay.
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(this._map);
            // Keep the camera across the view toggle.
            this._map.on('moveend', () => {
                if (! this._map) return;
                const c = this._map.getCenter();
                this._cam = { center: [c.lat, c.lng], zoom: this._map.getZoom() };
            });
            this._mapReady = true;
            this._renderView();
            this.fitToData();
            setTimeout(() => { if (this._map) this._map.invalidateSize(); }, 120);
        } catch (e) {
            this.error = labels.mapUnavailable || 'map unavailable';
        }
    },

    _destroyMap() {
        this._clearTrackLayers();
        this._clearMarkers();
        this._hideHover();
        this._hoverMarker = null;
        if (this._map) { try { this._map.remove(); } catch (e) { /* ignore */ } this._map = null; }
        this._mapReady = false;
    },

    _clearMarkers() {
        for (const m of this._markers) { try { m.remove(); } catch (e) { /* ignore */ } }
        this._markers = [];
    },

    _clearTrackLayers() {
        for (const layer of this._trackLayers) { try { layer.remove(); } catch (e) { /* ignore */ } }
        this._trackLayers = [];
    },

    // Draw whatever the active view needs onto the map.
    _renderView() {
        void this._mut;
        if (! this._map || ! this._mapReady) return;
        this._clearTrackLayers();
        this._clearMarkers();
        if (this.view === 'tracks' || this.view === 'detail') this._drawTracks();
        else this._drawMediaPins();
    },

    _drawTracks() {
        const L = this._L;
        if (! L) return;
        // In the detail view only the selected track is drawn.
        const detail = this.view === 'detail';
        this.tracks.forEach((track, i) => {
            if (detail && track.id !== this.selectedTrackId) return;
            const latlngs = (track.points || []).map((p) => [p.lat, p.lng]);
            if (latlngs.length < 2) return;
            const color = TRACK_COLORS[i % TRACK_COLORS.length];
            const selected = this.selectedTrackId === track.id;
            try {
                const line = L.polyline(latlngs, {
                    color,
                    weight: selected ? 5 : 3,
                    opacity: selected || ! this.selectedTrackId ? 0.9 : 0.4,
                    lineJoin: 'round',
                    lineCap: 'round',
                }).addTo(this._map);
                line.on('click', () => { this.selectedTrackId = track.id; });
                this._trackLayers.push(line);
            } catch (e) { /* ignore a track that fails to add */ }
        });
    },

    _drawMediaPins() {
        const L = this._L;
        if (! L) return;
        for (const p of this.placedMedia) {
            const icon = L.divIcon({
                className: 'explore-pin',
                html: '<span style="display:block;width:34px;height:34px;border-radius:9px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);background:#7066f5 center/cover no-repeat;"></span>',
                iconSize: [34, 34],
                iconAnchor: [17, 17],
            });
            const marker = L.marker([p._lat, p._lng], { icon }).addTo(this._map);
            this._markers.push(marker);
            // Lazy thumbnail into the pin background (bounded worker lane).
            this._thumbFor(p).then((url) => {
                if (! url) return;
                const span = marker.getElement()?.querySelector('span');
                if (span) span.style.backgroundImage = `url("${url}")`;
            });
        }
    },

    // Recenter/zoom to fit everything currently placed.
    fitToData() {
        const L = this._L;
        if (! this._map || ! this._mapReady || ! L) return;
        const pts = [];
        if (this.view === 'detail') {
            const t = this.selectedTrack;
            if (t) for (const p of (t.points || [])) pts.push([p.lat, p.lng]);
        } else if (this.view === 'tracks') {
            for (const t of this.tracks) for (const p of (t.points || [])) pts.push([p.lat, p.lng]);
        } else {
            for (const m of this.placedMedia) pts.push([m._lat, m._lng]);
        }
        if (pts.length === 0) return;
        try { this._map.fitBounds(L.latLngBounds(pts), { padding: [48, 48], maxZoom: 15 }); } catch (e) { /* ignore */ }
    },

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

    // Placed media filtered by the client-side filename search (case-insensitive).
    get filteredMedia() {
        void this._mut;
        const q = this.mediaQuery.trim().toLowerCase();
        const all = this.placedMedia;
        if (! q) return all;
        return all.filter((m) => String(m.name || m.id || '').toLowerCase().includes(q));
    },

    // Tracks filtered by the client-side name search (case-insensitive substring).
    get filteredTracks() {
        void this._mut;
        const q = this.trackQuery.trim().toLowerCase();
        if (! q) return this.tracks;
        return this.tracks.filter((t) => String(t.name || '').toLowerCase().includes(q));
    },

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

    // Open the full-detail panel for a track (dedicated view + fitted map).
    openDetail(id) {
        this.selectedTrackId = id;
        this.renamingId = null;
        this.view = 'detail';
    },

    // Return to the tracks list from the detail view.
    backToList() {
        this.view = 'tracks';
        this.renamingId = null;
    },

    async deleteTrack(track) {
        if (! await this.$store.confirm.ask(labels.deleteTrackConfirm || 'Delete this track?')) return;
        const idx = this.tracks.findIndex((t) => t.id === track.id);
        if (idx < 0) return;
        // Drop couplings that pointed at this track.
        for (const [mid, c] of Object.entries(this.couplings)) if (c.trackId === track.id) delete this.couplings[mid];
        // Best-effort cleanup of the sealed raw-track blob (orphan sweep is the
        // backstop; a failed delete just leaves an orphan the sweep collects).
        if (track.rawBlobId) {
            try {
                await fetch(`${config.deleteUrl || '/explore/blob'}/${encodeURIComponent(track.rawBlobId)}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': config.token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
            } catch (e) { /* orphan sweep handles it */ }
        }
        this.tracks.splice(idx, 1);
        if (this.selectedTrackId === track.id) { this.selectedTrackId = null; if (this.view === 'detail') this.view = 'tracks'; }
        this._save();
    },

    /* --------------------------------------------------------- Rename / note */

    startRename(track) {
        this.renamingId = track.id;
        this.renameValue = track.name || '';
        this.$nextTick(() => { try { this.$refs.renameInput?.focus(); this.$refs.renameInput?.select(); } catch (e) { /* ignore */ } });
    },
    cancelRename() { this.renamingId = null; this.renameValue = ''; },
    saveRename(track) {
        const name = String(this.renameValue || '').trim();
        if (name) { track.name = name; this._save(); }
        this.renamingId = null;
        this.renameValue = '';
    },

    // Free-text note on a track (debounced-persisted through the store touch()).
    saveNote(track, value) {
        const note = String(value ?? '').trim();
        if (note) track.note = note; else delete track.note;
        this._save();
    },

    /* --------------------------------------------------------- Tour planning */

    togglePlan() { this.planning ? this._exitPlan() : this._enterPlan(); },

    _enterPlan() {
        if (! this._map || ! this._mapReady || ! this._L) return;
        this.view = 'tracks';
        this.selectedTrackId = null;
        this.planning = true;
        this.planPoints = [];
        this._planClick = (e) => this._addWaypoint(e.latlng.lat, e.latlng.lng);
        this._map.on('click', this._planClick);
    },

    _exitPlan() {
        this.planning = false;
        this.planPoints = [];
        if (this._map && this._planClick) { try { this._map.off('click', this._planClick); } catch (e) { /* ignore */ } }
        this._planClick = null;
        if (this._planLayer) { try { this._planLayer.remove(); } catch (e) { /* ignore */ } this._planLayer = null; }
        for (const m of this._planMarkers) { try { m.remove(); } catch (e) { /* ignore */ } }
        this._planMarkers = [];
    },

    cancelPlan() { this._exitPlan(); },

    _addWaypoint(lat, lng) {
        this.planPoints.push([lat, lng]);
        this._drawPlan();
    },

    undoWaypoint() {
        this.planPoints.pop();
        this._drawPlan();
    },

    _drawPlan() {
        const L = this._L;
        if (! this._map || ! L) return;
        if (this._planLayer) { try { this._planLayer.remove(); } catch (e) { /* ignore */ } this._planLayer = null; }
        for (const m of this._planMarkers) { try { m.remove(); } catch (e) { /* ignore */ } }
        this._planMarkers = [];
        if (this.planPoints.length >= 2) {
            try {
                this._planLayer = L.polyline(this.planPoints, { color: '#7066f5', weight: 4, dashArray: '6 6', opacity: 0.9 }).addTo(this._map);
            } catch (e) { /* ignore */ }
        }
        for (const [lat, lng] of this.planPoints) {
            try {
                this._planMarkers.push(L.circleMarker([lat, lng], { radius: 4, color: '#fff', weight: 2, fillColor: '#7066f5', fillOpacity: 1 }).addTo(this._map));
            } catch (e) { /* ignore */ }
        }
    },

    async savePlan() {
        if (this.planPoints.length < 2) return;
        const name = await this.$store.confirm.prompt('', { placeholder: labels.routeName || '', ok: labels.save || '' });
        if (name === null) return; // cancelled
        const track = buildPlannedTrack(
            this.planPoints,
            String(name || '').trim() || labels.plannedRoute || 'Route',
            window.LLModuleStore.explore.newId(),
            new Date().toISOString(),
        );
        if (! track) return;
        this.tracks.push(track);
        this._exitPlan();
        this._save();
        this.selectedTrackId = track.id;
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
    fmtDateTime(iso) {
        if (! iso) return '—';
        const d = new Date(iso);
        return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString();
    },

    /* ------------------------------------------------------ Elevation chart */

    _destroyChart() {
        if (this._chartAbort) { this._chartAbort.abort(); this._chartAbort = null; }
        if (this._chart) { try { this._chart.destroy(); } catch (e) { /* ignore */ } this._chart = null; }
    },

    // True when the selected track has a usable elevation profile.
    get hasElevationProfile() {
        void this._mut;
        const t = this.selectedTrack;
        return !! t && hasElevation((t.stats && t.stats.elevationProfile) || []);
    },

    // The selected track's coupled photos, chronologically ordered (by capture
    // time, falling back to filename). Used by the detail-view thumbnail strip.
    get coupledPhotos() {
        void this._mut;
        const t = this.selectedTrack;
        if (! t) return [];
        const out = [];
        for (const p of this.photos) {
            const c = this.couplings[p.id];
            if (c && c.trackId === t.id) out.push(p);
        }
        const ts = (p) => {
            const v = p.taken_at ? Date.parse(p.taken_at) : (p.created ? Date.parse(p.created) : NaN);
            return Number.isFinite(v) ? v : Infinity;
        };
        out.sort((a, b) => (ts(a) - ts(b)) || String(a.name || '').localeCompare(String(b.name || '')));
        return out;
    },

    // Render an elevation-over-distance profile for the selected track; hovering
    // the chart moves a circle marker to the matching point on the map. The
    // x-axis is distance in km, the y-axis elevation in m; the profile is
    // downsampled for a clean line, and the sample→track-point index map keeps
    // the hover marker accurate. Planned/GPS-only tracks (no elevation) skip the
    // chart — the blade shows a "no elevation" state instead.
    async renderElevation() {
        this._destroyChart();
        const track = this.selectedTrack;
        const el = this.$refs.elevation;
        if (! el || ! track) return;
        const profile = (track.stats && track.stats.elevationProfile) || [];
        if (! hasElevation(profile)) return;

        const { xs, ys, idx } = downsampleProfile(profile, 400);
        if (xs.length < 2) return;

        const UPlot = await loadUplot();
        if (! el.isConnected) return;
        const isDark = document.documentElement.classList.contains('dark');
        const grid = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const axis = isDark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.35)';
        const km = labels.unitKm || 'km';
        const m = labels.unitM || 'm';

        const opts = {
            width: el.clientWidth || 600,
            height: 220,
            cursor: { drag: { x: false, y: false }, points: { size: 7 } },
            scales: { x: { time: false }, y: {} },
            axes: [
                {
                    stroke: axis, grid: { stroke: grid, width: 1 }, ticks: { stroke: grid, width: 1 },
                    values: (_u, vals) => vals.map((v) => v.toFixed(1) + ' ' + km),
                },
                {
                    stroke: axis, grid: { stroke: grid, width: 1 }, ticks: { stroke: grid, width: 1 }, size: 52,
                    values: (_u, vals) => vals.map((v) => Math.round(v) + ' ' + m),
                },
            ],
            series: [
                { label: km },
                { label: labels.elevation || 'Elevation', stroke: '#7066f5', fill: 'rgba(112,102,245,0.15)', width: 2, spanGaps: true, value: (_u, v) => (v == null ? '—' : Math.round(v) + ' ' + m) },
            ],
            hooks: {
                setCursor: [(u) => {
                    const ci = u.cursor.idx;
                    if (ci == null) { this._hideHover(); return; }
                    const pointIdx = idx[ci];
                    const pt = track.points && track.points[pointIdx];
                    if (pt) this._showHover(pt.lat, pt.lng);
                }],
            },
        };
        this._chart = new UPlot(opts, [xs, ys], el);

        this._chartAbort = new AbortController();
        el.addEventListener('mouseleave', () => this._hideHover(), { signal: this._chartAbort.signal });
    },

    _showHover(lat, lng) {
        const L = this._L;
        if (! this._map || ! this._mapReady || ! L) return;
        if (! this._hoverMarker) {
            this._hoverMarker = L.circleMarker([lat, lng], {
                radius: 6, color: '#fff', weight: 2, fillColor: '#7066f5', fillOpacity: 1,
            });
        } else {
            this._hoverMarker.setLatLng([lat, lng]);
        }
        this._hoverMarker.addTo(this._map);
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
