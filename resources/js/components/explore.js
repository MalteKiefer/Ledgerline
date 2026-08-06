// Explore map module (ZK). GPS tracks live in the sealed `explore` module store
// (window.LLModuleStore.explore); the server only ever sees ciphertext. Leaflet
// renders raster OpenStreetMap tiles as <img> (allowed by the CSP img-src) — the
// same tile layer the location picker uses; no tile relay / tileserver involved.
//
// Everything heavy is lazy: Leaflet (loadLeaflet()), uPlot (loadUplot()) and the
// KMZ unzip (fflate, dynamic import) are only pulled when the view needs them,
// so none of them touch the startup bundle.

import { bootStore } from '../shared/zk-module';
import { parseTrack, parseTrackBinary, smoothedAscentDescent } from '../shared/track-parse';
import { loadLeaflet } from '../shared/lazy-loaders';
import { loadUplot } from '../shared/uplot-loader';
import { padBlob } from '../shared/padme';
import { buildPlannedTrack, hasElevation, downsampleProfile, normalizeRouteElevation, aggregateSurfaces } from '../shared/explore-detail';
import { haversineM } from '../shared/track-parse';
import { classifySearch } from '../shared/geo-search';
import { escapeHtml, saveBlobAs, formatDate } from '../shared/dom';
import {
    distanceUnit, distanceValue, distanceLabel, elevationUnit, elevationValue, elevationLabel, convertDistance,
} from '../shared/prefs';
import { buildGpx, gpxFilename } from '../shared/track-export';
import { estimateCalories } from '../shared/explore-calories';
import { routeGroup } from '../shared/track-similarity';

// Distinct, deterministic polyline colours cycled per track (iOS-ish accents).
const TRACK_COLORS = ['#7066f5', '#3b9fd6', '#59ad6b', '#e2915a', '#d9a441', '#3fae9f', '#9e70fa', '#ef4444'];

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | ready | error
    view: 'tracks', // tracks | detail
    error: '',
    importing: false,

    // Client-side search over the decrypted data (never leaves the browser).
    trackQuery: '',

    // Map search box: place / POI / coordinates / Google-Maps link. Coordinates
    // and long Google links resolve locally (no egress); a place query hits the
    // geocoder, a short google link hits /maps/resolve.
    searchQuery: '',
    searching: false,
    searchResults: [], // geocoder hits [{display,lat,lng}]
    searchMsg: '',
    _searchMarker: null, // Leaflet marker for the last search hit

    // Inline rename editor: the track id being renamed, plus the working name.
    renamingId: null,
    renameValue: '',

    // Tour-planning (hand-drawn route) mode.
    planning: false,
    planPoints: [],       // ordered [lat, lng] waypoints the user clicked (kept raw)
    autoRoute: false,     // opt-in: snap waypoints to real paths via /maps/route (OFF = straight lines)
    routing: false,       // an auto-route request is in flight
    _routedGeometry: null, // snapped [lat,lng] geometry from the router (null = use straight waypoints)
    // Rich metadata from the last successful auto-route (/maps/route): distance,
    // duration, elevation profile + ascent/descent, surface breakdown. Null while
    // straight-line or before the first route lands; cleared on waypoint edit /
    // auto-route off / plan exit. `_mut` is bumped so the toolbar getters recompute.
    _routeMeta: null,     // { distanceM, durationS, elevation:[{distM,eleM}]|null, ascentM, descentM, surfaces:[{surface,distM}]|null }
    _routeSeq: 0,         // monotonic token so a stale route response can't overwrite a newer one
    _routeTimer: null,    // debounce handle for auto-route on waypoint change
    _planLayer: null,     // Leaflet polyline of the growing route
    _planMarkers: [],     // small vertex markers
    _planClick: null,     // bound map click handler (removed on exit)
    _planChart: null,     // uPlot instance for the live planning elevation profile
    _planChartAbort: null,
    _planElToken: 0,      // monotonic token guarding concurrent plan-chart renders

    // Store-bound collections (aliased to the sealed store's own objects so
    // mutations persist through touch()). `_mut` is bumped on every save so
    // store-derived getters recompute (the store data is NOT an Alpine proxy).
    // `couplings`/`settings` are kept bound to preserve the sealed store shape
    // (they held photo↔track couplings, now unused) but are never read/written.
    tracks: [],
    couplings: {},
    settings: {},
    _mut: 0,

    healthProfile: null, // { heightCm, sex, ... } from the sealed health store (best-effort)
    latestWeightKg: null, // most recent weight entry (kg), for the calorie estimate

    selectedTrackId: null,

    // Persisted map camera ([lat, lng] + zoom) so it survives the view toggle.
    // The Leaflet map itself is never torn down on toggle.
    _cam: { center: null, zoom: null },
    _map: null,
    _mapReady: false,
    _trackLayers: [],    // Leaflet polyline layers currently on the map
    heatOn: false,       // tracks view: aggregate route-frequency heatmap of ALL tours
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
        this._healAscent();

        // Health profile is best-effort too — drives the optional calorie estimate,
        // only when height + sex + latest weight are all on file. Read-only.
        try {
            if (await bootStore(this.$store, 'health')) {
                const h = window.LLModuleStore.health.data || {};
                this.healthProfile = h.healthProfile || null;
                // Weight is a health MEASUREMENT (metric 'weight'), not a profile
                // field — take the most recent one. Coerce v (it may be stored as a
                // string) so a valid weight is never dropped.
                const weights = (h.healthEntries || [])
                    .filter((e) => e.metric === 'weight' && Number.isFinite(Number(e.v)))
                    .sort((a, b) => new Date(b.ts) - new Date(a.ts));
                this.latestWeightKg = weights.length ? Number(weights[0].v) : null;
            }
        } catch (e) { this.healthProfile = null; this.latestWeightKg = null; }

        this.state = 'ready';
        this.$nextTick(() => this._initMap());
    },

    _onLock() {
        this.state = 'locked';
        this.tracks = [];
        this.couplings = {};
        this.trackQuery = '';
        this.renamingId = null;
        this._exitPlan();
        this._destroyChart();
        this._destroyMap();
        window.LLModuleStore.explore.reset();
    },

    // Persist the sealed store (debounced) after a mutation.
    _save() { this._mut++; window.LLModuleStore.explore.touch(); },

    // Re-derive ascent/descent for existing tracks with the GPS-noise smoothing
    // (older tracks were stored with raw per-point deltas that inflated total
    // climb ~5-10x — wrong on the display AND in the calorie estimate). One
    // debounced save if anything changed; leaves duration/distance untouched.
    _healAscent() {
        let dirty = false;
        for (const t of (this.tracks || [])) {
            const pts = t.points || [];
            if (! t.stats || pts.length < 2) continue;
            const { ascentM, descentM } = smoothedAscentDescent(pts);
            const round2 = (n) => Math.round(n * 100) / 100;
            if (Math.abs(round2(ascentM) - (Number(t.stats.ascentM) || 0)) > 1) {
                t.stats.ascentM = round2(ascentM);
                t.stats.descentM = round2(descentM);
                dirty = true;
            }
        }
        if (dirty) this._save();
    },

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
        this._hideHover();
        this._hoverMarker = null;
        if (this._map) { try { this._map.remove(); } catch (e) { /* ignore */ } this._map = null; }
        this._mapReady = false;
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
        // While planning a route only the plan polyline is shown — never redraw
        // the existing tracks over it (the plan layer is managed by _drawPlan).
        if (this.planning) return;
        this._drawTracks();
    },

    // Toggle the aggregate route-frequency heatmap over all tours (tracks view only).
    toggleHeat() {
        this.heatOn = ! this.heatOn;
        if (this.heatOn) { this.selectedTrackId = null; this._renderView(); this._fitAllTracks(); }
        else this._renderView();
    },
    _fitAllTracks() {
        const L = this._L;
        if (! L || ! this._map) return;
        const pts = [];
        for (const t of (this.tracks || [])) for (const p of (t.points || [])) pts.push([p.lat, p.lng]);
        if (pts.length) { try { this._map.fitBounds(L.latLngBounds(pts), { padding: [48, 48], maxZoom: 15 }); } catch (e) { /* ignore */ } }
    },

    _drawTracks() {
        const L = this._L;
        if (! L) return;
        // Heatmap: draw EVERY track as a low-opacity warm polyline. SVG alpha blends on
        // overlap, so frequently-walked segments render darker (a route-frequency heatmap).
        // No per-track colour / click / arrows — it's an aggregate view.
        if (this.heatOn && this.view === 'tracks') {
            for (const track of this.tracks) {
                const latlngs = (track.points || []).map((p) => [p.lat, p.lng]);
                if (latlngs.length < 2) continue;
                try {
                    const line = L.polyline(latlngs, { color: '#ef4444', weight: 5, opacity: 0.13, lineJoin: 'round', lineCap: 'round', interactive: false }).addTo(this._map);
                    this._trackLayers.push(line);
                } catch (e) { /* ignore */ }
            }
            return;
        }
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
                // Direction-of-travel arrows along the selected track, at regular
                // spacing (points in track order → the tour was walked start→end).
                if (selected) this._directionArrows(track, color);
            } catch (e) { /* ignore a track that fails to add */ }
        });
    },

    // Compass bearing (deg, 0 = north, clockwise) from a → b.
    _bearing(lat1, lng1, lat2, lng2) {
        const toRad = (d) => (d * Math.PI) / 180;
        const φ1 = toRad(lat1), φ2 = toRad(lat2), Δλ = toRad(lng2 - lng1);
        const y = Math.sin(Δλ) * Math.cos(φ2);
        const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ);
        return ((Math.atan2(y, x) * 180) / Math.PI + 360) % 360;
    },

    // Place small triangular arrows along a track pointing in the travel direction,
    // spaced ~evenly by distance (4..40 arrows depending on length). Non-interactive;
    // stored in _trackLayers so they clear with the polyline.
    _directionArrows(track, color) {
        const L = this._L;
        const pts = (track.points || []).filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));
        if (! L || pts.length < 2) return;
        const cum = [0];
        let total = 0;
        for (let i = 1; i < pts.length; i++) { total += haversineM(pts[i - 1].lat, pts[i - 1].lng, pts[i].lat, pts[i].lng); cum.push(total); }
        if (total < 80) return;
        const count = Math.min(40, Math.max(4, Math.round(total / 400)));
        const step = total / (count + 1);
        let seg = 1;
        for (let k = 1; k <= count; k++) {
            const target = step * k;
            while (seg < pts.length && cum[seg] < target) seg++;
            if (seg >= pts.length) break;
            const a = pts[seg - 1], b = pts[seg];
            const span = cum[seg] - cum[seg - 1];
            const t = span > 0 ? (target - cum[seg - 1]) / span : 0;
            const lat = a.lat + (b.lat - a.lat) * t;
            const lng = a.lng + (b.lng - a.lng) * t;
            const deg = this._bearing(a.lat, a.lng, b.lat, b.lng);
            try {
                const icon = L.divIcon({
                    className: 'explore-arrow',
                    html: `<div style="transform:rotate(${deg}deg);color:${color};font-size:13px;line-height:0;text-shadow:0 0 2px rgba(255,255,255,.9),0 0 2px rgba(255,255,255,.9);">▲</div>`,
                    iconSize: [14, 14],
                    iconAnchor: [7, 7],
                });
                const m = L.marker([lat, lng], { icon, interactive: false, keyboard: false }).addTo(this._map);
                this._trackLayers.push(m);
            } catch (e) { /* ignore */ }
        }
    },

    // Recenter/zoom to fit the tracks currently shown (all of them, or just the
    // selected one in the detail view).
    fitToData() {
        const L = this._L;
        if (! this._map || ! this._mapReady || ! L) return;
        const pts = [];
        if (this.view === 'detail') {
            const t = this.selectedTrack;
            if (t) for (const p of (t.points || [])) pts.push([p.lat, p.lng]);
        } else {
            for (const t of this.tracks) for (const p of (t.points || [])) pts.push([p.lat, p.lng]);
        }
        if (pts.length === 0) return;
        try { this._map.fitBounds(L.latLngBounds(pts), { padding: [48, 48], maxZoom: 15 }); } catch (e) { /* ignore */ }
    },

    /* ------------------------------------------------------------- Map search */

    // Run the search box: coordinates + long Google-Maps links resolve locally;
    // a short google link goes to /maps/resolve; anything else is a place/POI
    // query to the geocoder (results shown in a dropdown).
    async runSearch() {
        const action = classifySearch(this.searchQuery);
        if (! action) return;
        this.searchResults = [];
        this.searchMsg = '';

        if (action.kind === 'coords') { this._goToPlace(action.lat, action.lng); return; }

        this.searching = true;
        try {
            if (action.kind === 'resolve') {
                const url = `${config.resolveUrl}?url=${encodeURIComponent(action.url)}`;
                const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const d = res.ok ? await res.json() : {};
                if (Number.isFinite(d.lat) && Number.isFinite(d.lng)) this._goToPlace(d.lat, d.lng);
                else this.searchMsg = labels.searchNotFound || 'Nothing found.';
                return;
            }
            // Free-text place/POI query → geocoder.
            const url = `${config.geocodeUrl}?q=${encodeURIComponent(action.q)}`;
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const d = res.ok ? await res.json() : {};
            const results = Array.isArray(d.results) ? d.results : [];
            if (! results.length) { this.searchMsg = labels.searchNotFound || 'Nothing found.'; return; }
            if (results.length === 1) { this.pickSearchResult(results[0]); return; }
            this.searchResults = results;
        } catch (e) {
            this.searchMsg = labels.searchFailed || 'Search failed.';
        } finally {
            this.searching = false;
        }
    },

    pickSearchResult(r) {
        this.searchResults = [];
        this._goToPlace(r.lat, r.lng, r.display);
    },

    // Fly to a coordinate and drop a temporary search marker + popup.
    _goToPlace(lat, lng, label) {
        const L = this._L;
        if (! this._map || ! this._mapReady || ! L) return;
        if (this._searchMarker) { try { this._searchMarker.remove(); } catch (e) { /* ignore */ } this._searchMarker = null; }
        const coordText = `${(+lat).toFixed(6)}, ${(+lng).toFixed(6)}`;
        const title = label || coordText;
        try {
            this._searchMarker = L.marker([lat, lng]).addTo(this._map)
                .bindPopup(`<strong>${escapeHtml(title)}</strong><br><span style="color:#6b7280">${escapeHtml(coordText)}</span>`)
                .openPopup();
            this._map.flyTo([lat, lng], Math.max(this._map.getZoom() || 0, 14), { duration: 0.6 });
        } catch (e) { /* ignore */ }
        this.searchMsg = (labels.searchResult || 'Found: :place').replace(':place', title);
    },

    /* -------------------------------------------------------- Track filtering */

    // Tracks filtered by the client-side name search (case-insensitive substring).
    get filteredTracks() {
        void this._mut;
        const q = this.trackQuery.trim().toLowerCase();
        if (! q) return this.tracks;
        return this.tracks.filter((t) => String(t.name || '').toLowerCase().includes(q));
    },

    /* --------------------------------------------------------------- Import */

    // File input change handler: read + parse each file, push the track, seal
    // the raw bytes (best-effort).
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
        // Resolve the index by id AFTER the awaits (confirm + blob delete). A concurrent
        // 409 rebase may have reordered this.tracks in the meantime, so an index computed
        // earlier could splice out the WRONG track.
        const idx = this.tracks.findIndex((t) => t.id === track.id);
        if (idx < 0) { this._save(); return; }
        this.tracks.splice(idx, 1);
        if (this.selectedTrackId === track.id) { this.selectedTrackId = null; if (this.view === 'detail') this.view = 'tracks'; }
        this._save();
    },

    /* --------------------------------------------------------- Rename / note */

    // Download a track (recorded, imported or planned) as a GPX file. Fully
    // client-side from the already-decrypted points — nothing leaves the ZK
    // boundary; the server never sees the track in the clear.
    downloadGpx(track) {
        if (! track || ! (track.points || []).length) return;
        const gpx = buildGpx(track);
        if (! gpx) return;
        saveBlobAs(new TextEncoder().encode(gpx), gpxFilename(track.name), 'application/gpx+xml');
    },

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
        this.planning = true;
        this.selectedTrackId = null;
        this.planPoints = [];
        this._routedGeometry = null;
        this._routeMeta = null;
        this.routing = false;
        this._routeSeq++;            // invalidate any in-flight route from a prior session
        if (this._routeTimer) { clearTimeout(this._routeTimer); this._routeTimer = null; }
        this._destroyPlanChart();
        // Clear whatever route was on the map so only the new plan shows.
        this._clearTrackLayers();
        this._hideHover();
        this._planClick = (e) => this._addWaypoint(e.latlng.lat, e.latlng.lng);
        this._map.on('click', this._planClick);
    },

    _exitPlan() {
        this.planning = false;
        this.planPoints = [];
        this._routedGeometry = null;
        this._routeMeta = null;
        this.routing = false;
        this._routeSeq++;            // any late route response is now stale
        if (this._routeTimer) { clearTimeout(this._routeTimer); this._routeTimer = null; }
        this._destroyPlanChart();
        if (this._map && this._planClick) { try { this._map.off('click', this._planClick); } catch (e) { /* ignore */ } }
        this._planClick = null;
        if (this._planLayer) { try { this._planLayer.remove(); } catch (e) { /* ignore */ } this._planLayer = null; }
        for (const m of this._planMarkers) { try { m.remove(); } catch (e) { /* ignore */ } }
        this._planMarkers = [];
        this._renderView(); // restore the normal track/pin layers
    },

    cancelPlan() { this._exitPlan(); },

    // Toggle the opt-in auto-routing. Turning it ON re-routes the current
    // waypoints; turning it OFF drops the snapped geometry back to straight
    // lines. The raw clicked waypoints are always preserved either way.
    // Reacts to the checkbox @change — x-model has ALREADY flipped `autoRoute`,
    // so this only runs the side-effect (do NOT toggle again or it cancels out).
    toggleAutoRoute() {
        if (! this.autoRoute) {
            this._routedGeometry = null;
            this._routeMeta = null;
            this.routing = false;
            this._routeSeq++;
            if (this._routeTimer) { clearTimeout(this._routeTimer); this._routeTimer = null; }
            this._destroyPlanChart();
            this._drawPlan();
            return;
        }
        this._scheduleRoute();
    },

    _addWaypoint(lat, lng) {
        this.planPoints.push([lat, lng]);
        // A new waypoint invalidates any previously snapped geometry + its rich
        // metadata until the re-route lands, so the preview/stats never show a
        // route for stale points.
        this._routedGeometry = null;
        this._routeMeta = null;
        this._destroyPlanChart();
        this._drawPlan();
        this._scheduleRoute();
    },

    undoWaypoint() {
        this.planPoints.pop();
        this._routedGeometry = null;
        this._routeMeta = null;
        this._destroyPlanChart();
        this._drawPlan();
        this._scheduleRoute();
    },

    // Debounce an auto-route request (only when auto-route is on and there are
    // at least two waypoints). Straight-line mode never calls the server.
    _scheduleRoute() {
        if (this._routeTimer) { clearTimeout(this._routeTimer); this._routeTimer = null; }
        if (! this.autoRoute || this.planPoints.length < 2) return;
        // Honour a rate-limit cooldown: while set, keep the straight-line preview
        // and don't hammer the proxy/upstream (a 429 set this).
        if (this._routeCooldownUntil && Date.now() < this._routeCooldownUntil) return;
        this._routeTimer = setTimeout(() => { this._routeTimer = null; this._requestRoute(); }, 700);
    },

    // Ask the server-side proxy (/maps/route) to snap the current waypoints onto
    // real paths. On success the snapped geometry becomes the preview + saved
    // track; on failure/null we fall back to the straight-line waypoints and
    // surface a subtle notice — the user's waypoints are never lost.
    async _requestRoute() {
        if (! this.autoRoute || this.planPoints.length < 2) return;
        // Server caps a route at 100 waypoints — beyond that keep straight lines
        // (don't fire a request that would 422) and tell the user.
        if (this.planPoints.length > 100) {
            this._routedGeometry = null;
            this._routeMeta = null;
            this._destroyPlanChart();
            window.llToast?.(labels.routeTooMany || labels.routeFallback || '');
            this._drawPlan();
            return;
        }
        const seq = ++this._routeSeq;
        const points = this.planPoints
            .map(([lat, lng]) => `${lat.toFixed(6)},${lng.toFixed(6)}`)
            .join(';');
        this.routing = true;
        let geometry = null;
        let meta = null;
        let rateLimited = false;
        try {
            const res = await fetch(`${config.routeUrl}?points=${encodeURIComponent(points)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) {
                const d = await res.json();
                if (Array.isArray(d.geometry) && d.geometry.length >= 2) {
                    geometry = d.geometry;
                    // Capture the rich route metadata (distance/duration always present;
                    // elevation/surfaces only when a GraphHopper backend is configured).
                    const elevation = normalizeRouteElevation(d.elevation);
                    const surfaces = aggregateSurfaces(d.surfaces);
                    meta = {
                        distanceM: Number.isFinite(Number(d.distanceM)) ? Number(d.distanceM) : null,
                        durationS: Number.isFinite(Number(d.durationS)) ? Number(d.durationS) : null,
                        elevation: elevation.length ? elevation : null,
                        ascentM: Number.isFinite(Number(d.ascentM)) ? Number(d.ascentM) : null,
                        descentM: Number.isFinite(Number(d.descentM)) ? Number(d.descentM) : null,
                        surfaces: surfaces.length ? surfaces : null,
                    };
                }
            } else if (res.status === 429) {
                // Too many routing calls (our throttle or the upstream). Back off for
                // Retry-After (default 30s) and keep straight lines until then.
                rateLimited = true;
                const ra = parseInt(res.headers.get('Retry-After') || '', 10);
                this._routeCooldownUntil = Date.now() + (Number.isFinite(ra) && ra > 0 ? ra * 1000 : 30000);
            }
        } catch (e) { /* fall back to straight lines below */ }
        // A newer waypoint edit (or plan exit / auto-route off) superseded us.
        if (seq !== this._routeSeq || ! this.planning || ! this.autoRoute) return;
        this.routing = false;
        this._routedGeometry = geometry;
        this._routeMeta = meta;
        if (! geometry) window.llToast?.((rateLimited ? (labels.routeRateLimited || labels.routeFallback) : labels.routeFallback) || '');
        this._drawPlan();
        // Draw/update or tear down the live elevation profile for the new route.
        this.$nextTick(() => this.renderPlanElevation());
    },

    // The polyline actually shown/saved: the snapped geometry when auto-route
    // produced one, otherwise the raw clicked waypoints (straight lines).
    _planLine() {
        return (this.autoRoute && this._routedGeometry && this._routedGeometry.length >= 2)
            ? this._routedGeometry
            : this.planPoints;
    },

    /* ------------------------------------------------- Live planning stats */

    // Total distance of the plan (metres). Prefers the router's reported distance
    // when an auto-route is in effect; otherwise a haversine sum over the line
    // actually drawn (snapped geometry or straight-line waypoints).
    get planDistanceM() {
        if (this._routeMeta && Number.isFinite(this._routeMeta.distanceM)) return this._routeMeta.distanceM;
        const line = this._planLine();
        if (! Array.isArray(line) || line.length < 2) return 0;
        let sum = 0;
        for (let i = 1; i < line.length; i++) {
            sum += haversineM(line[i - 1][0], line[i - 1][1], line[i][0], line[i][1]);
        }
        return sum;
    },

    // Estimated duration (seconds) from the router, or null in straight-line mode
    // (we can't estimate travel time without a routing profile).
    get planDurationS() {
        return (this._routeMeta && Number.isFinite(this._routeMeta.durationS)) ? this._routeMeta.durationS : null;
    },

    // Elevation profile of the routed plan ([{distM,eleM}]) or [] when unavailable.
    get planElevationProfile() {
        return (this._routeMeta && Array.isArray(this._routeMeta.elevation)) ? this._routeMeta.elevation : [];
    },
    get planHasElevation() { return hasElevation(this.planElevationProfile); },

    // Ascent/descent from the routed plan (metres) or null.
    get planAscentM() { return (this._routeMeta && Number.isFinite(this._routeMeta.ascentM)) ? this._routeMeta.ascentM : null; },
    get planDescentM() { return (this._routeMeta && Number.isFinite(this._routeMeta.descentM)) ? this._routeMeta.descentM : null; },

    // Compact surface breakdown ([{surface, distM}] sorted desc) or [] when the
    // router didn't return per-segment surfaces (no GraphHopper backend).
    get planSurfaces() {
        return (this._routeMeta && Array.isArray(this._routeMeta.surfaces)) ? this._routeMeta.surfaces : [];
    },

    // Human label for a routing surface token (falls back to the raw token).
    surfaceLabel(surface) {
        const key = String(surface || 'unknown');
        return (labels.surfaces && labels.surfaces[key]) || key;
    },

    _drawPlan() {
        const L = this._L;
        if (! this._map || ! L) return;
        if (this._planLayer) { try { this._planLayer.remove(); } catch (e) { /* ignore */ } this._planLayer = null; }
        for (const m of this._planMarkers) { try { m.remove(); } catch (e) { /* ignore */ } }
        this._planMarkers = [];
        const line = this._planLine();
        // Snapped routes render solid; straight-line previews stay dashed.
        const snapped = this.autoRoute && this._routedGeometry && this._routedGeometry.length >= 2;
        if (line.length >= 2) {
            try {
                this._planLayer = L.polyline(line, {
                    color: '#7066f5', weight: 4, opacity: 0.9,
                    ...(snapped ? {} : { dashArray: '6 6' }),
                }).addTo(this._map);
            } catch (e) { /* ignore */ }
        }
        // Vertex markers always mark the user's clicked waypoints.
        for (const [lat, lng] of this.planPoints) {
            try {
                this._planMarkers.push(L.circleMarker([lat, lng], { radius: 4, color: '#fff', weight: 2, fillColor: '#7066f5', fillOpacity: 1 }).addTo(this._map));
            } catch (e) { /* ignore */ }
        }
    },

    async savePlan() {
        if (this.planPoints.length < 2) return;
        // If auto-route is on but the debounce hasn't fired yet, snap now so the
        // saved track follows real paths (best-effort; falls back on failure).
        if (this.autoRoute && ! this.routing && ! this._routedGeometry) {
            if (this._routeTimer) { clearTimeout(this._routeTimer); this._routeTimer = null; }
            await this._requestRoute();
        }
        const name = await this.$store.confirm.prompt('', { placeholder: labels.routeName || '', ok: labels.save || '' });
        if (name === null) return; // cancelled
        // Save the snapped geometry when we have one; otherwise the raw waypoints.
        // sourceFormat stays 'planned' (no elevation/time) → distance-only stats
        // over whichever line we saved.
        const track = buildPlannedTrack(
            this._planLine(),
            String(name || '').trim() || labels.plannedRoute || 'Route',
            window.LLModuleStore.explore.newId(),
            new Date().toISOString(),
        );
        if (! track) return;
        // Enrich with the router's real elevation profile + surface breakdown when
        // present (GraphHopper backend). Straight-line / no-elevation plans stay as
        // buildPlannedTrack left them (distance-only stats, no elevation, no surfaces).
        if (this._routeMeta) {
            const profile = Array.isArray(this._routeMeta.elevation) ? this._routeMeta.elevation : [];
            if (hasElevation(profile)) {
                track.stats.elevationProfile = profile;
                let minEle = null;
                let maxEle = null;
                for (const pt of profile) {
                    if (pt.eleM == null || ! Number.isFinite(pt.eleM)) continue;
                    if (minEle === null || pt.eleM < minEle) minEle = pt.eleM;
                    if (maxEle === null || pt.eleM > maxEle) maxEle = pt.eleM;
                }
                track.stats.minEleM = minEle;
                track.stats.maxEleM = maxEle;
                if (Number.isFinite(this._routeMeta.ascentM)) track.stats.ascentM = this._routeMeta.ascentM;
                if (Number.isFinite(this._routeMeta.descentM)) track.stats.descentM = this._routeMeta.descentM;
            }
            if (Number.isFinite(this._routeMeta.durationS)) track.stats.durationTotalS = this._routeMeta.durationS;
            if (Array.isArray(this._routeMeta.surfaces) && this._routeMeta.surfaces.length) {
                track.surfaces = this._routeMeta.surfaces;
            }
        }
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

    // Distance/elevation honour the user's global unit preference (km/mi, m/ft);
    // the canonical value stays in metres — these convert for display only.
    fmtDistance(m) {
        if (! (m > 0)) return '0 ' + distanceUnit();
        return distanceLabel(m, m < 10000 ? 2 : 1);
    },
    fmtDuration(s) {
        s = Math.round(s || 0);
        const h = Math.floor(s / 3600), min = Math.floor((s % 3600) / 60);
        return h > 0 ? `${h}h ${min}m` : `${min}m`;
    },
    // Speed in the distance unit's system: km/h for metric, mph for miles.
    fmtSpeed(mps) {
        const perHourM = (mps || 0) * 3600;
        const d = convertDistance(perHourM, 1);
        return d.value + ' ' + d.unit + '/h';
    },
    fmtEle(m) { return m == null ? '—' : elevationLabel(m); },
    fmtDateTime(iso) { return iso ? (formatDate(iso, { dateStyle: 'medium', timeStyle: 'short' }) || '—') : '—'; },

    /* ------------------------------------------------------ Elevation chart */

    _destroyChart() {
        if (this._chartAbort) { this._chartAbort.abort(); this._chartAbort = null; }
        if (this._chart) { try { this._chart.destroy(); } catch (e) { /* ignore */ } this._chart = null; }
    },

    _destroyPlanChart() {
        this._planElToken++; // supersede any in-flight render awaiting loadUplot()
        if (this._planChartAbort) { this._planChartAbort.abort(); this._planChartAbort = null; }
        if (this._planChart) { try { this._planChart.destroy(); } catch (e) { /* ignore */ } this._planChart = null; }
        const el = this.$refs.planElevation;
        if (el) el.innerHTML = '';
    },

    // Render a small live elevation profile of the auto-routed plan (GraphHopper
    // only). Mirrors renderElevation() but reads _routeMeta.elevation and targets
    // the planning toolbar's own container so it never collides with the detail
    // chart. No map-hover coupling — this is a compact preview only.
    async renderPlanElevation() {
        this._destroyPlanChart();
        const el = this.$refs.planElevation;
        if (! el || ! this.planning) return;
        const profile = this.planElevationProfile;
        if (! hasElevation(profile)) return;

        const { xs, ys } = downsampleProfile(profile, 300);
        if (xs.length < 2) return;

        const token = ++this._planElToken;
        const UPlot = await loadUplot();
        if (token !== this._planElToken || ! el.isConnected || ! this.planning) return;
        el.innerHTML = '';
        const isDark = document.documentElement.classList.contains('dark');
        const grid = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const axis = isDark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.35)';
        const du = distanceUnit();
        const eu = elevationUnit();
        // xs come in km, ys in metres; convert both to the user's units for display.
        const xu = xs.map((v) => distanceValue(v * 1000, 2));
        const yu = ys.map((v) => (v == null ? null : elevationValue(v, 1)));

        const opts = {
            width: el.clientWidth || 320,
            height: 120,
            cursor: { drag: { x: false, y: false }, points: { size: 6 } },
            legend: { show: false },
            scales: { x: { time: false }, y: {} },
            axes: [
                {
                    stroke: axis, grid: { stroke: grid, width: 1 }, ticks: { stroke: grid, width: 1 }, size: 26,
                    values: (_u, vals) => vals.map((v) => v.toFixed(1) + ' ' + du),
                },
                {
                    stroke: axis, grid: { stroke: grid, width: 1 }, ticks: { stroke: grid, width: 1 }, size: 44,
                    values: (_u, vals) => vals.map((v) => Math.round(v) + ' ' + eu),
                },
            ],
            series: [
                { label: du },
                { label: labels.elevation || 'Elevation', stroke: '#7066f5', fill: 'rgba(112,102,245,0.15)', width: 2, spanGaps: true },
            ],
        };
        this._planChart = new UPlot(opts, [xu, yu], el);
    },

    // True when the selected track has a usable elevation profile.
    get hasElevationProfile() {
        void this._mut;
        const t = this.selectedTrack;
        return !! t && hasElevation((t.stats && t.stats.elevationProfile) || []);
    },

    // Whether the health profile is complete enough to show a calorie estimate
    // (height + sex + a recorded weight, per the user's opt-in condition).
    get hasHealthForCalories() {
        void this._mut;
        const h = this.healthProfile;
        return !! (h && Number(h.heightCm) > 0 && h.sex && Number(this.latestWeightKg) > 0);
    },
    // Estimated kcal for a track, or null when health data is incomplete / no stats.
    caloriesFor(track) {
        if (! this.hasHealthForCalories || ! track || ! track.stats) return null;
        const s = track.stats;
        return estimateCalories({
            distanceM: s.distanceM,
            durationS: s.durationMovingS || s.durationTotalS || 0,
            ascentM: s.ascentM,
            weightKg: this.latestWeightKg,
            sex: this.healthProfile.sex,
        });
    },

    // Comparison across every track that covers the SAME route as the selected
    // one (a loop/out-and-back repeated) — for spotting improvement in pace or
    // effort. Returns rows sorted by date (newest first), each with duration,
    // average speed and calories, plus which row is the fastest.
    get routeComparison() {
        void this._mut;
        const t = this.selectedTrack;
        if (! t) return [];
        const group = routeGroup(t, this.tracks);
        if (group.length < 2) return []; // nothing to compare against
        const rows = group.map((tr) => {
            const s = tr.stats || {};
            const durS = s.durationMovingS || s.durationTotalS || 0;
            const speedMps = s.avgSpeedMps || (durS > 0 ? (s.distanceM || 0) / durS : 0);
            return {
                id: tr.id,
                name: tr.name,
                when: tr.startedAt || tr.importedAt || null,
                durationS: durS,
                speedMps,
                calories: this.caloriesFor(tr),
                isCurrent: tr.id === t.id,
            };
        });
        // Fastest = highest average speed among timed tracks.
        let bestId = null, bestSpeed = -1;
        for (const r of rows) if (r.speedMps > bestSpeed) { bestSpeed = r.speedMps; bestId = r.id; }
        for (const r of rows) r.isFastest = r.id === bestId && r.speedMps > 0;
        rows.sort((a, b) => new Date(b.when || 0) - new Date(a.when || 0));
        return rows;
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
        el.innerHTML = ''; // drop any orphaned uPlot DOM from a superseded render
        const profile = (track.stats && track.stats.elevationProfile) || [];
        if (! hasElevation(profile)) return;

        const { xs, ys, idx } = downsampleProfile(profile, 400);
        if (xs.length < 2) return;

        // Concurrency guard: openDetail flips both `view` and `selectedTrackId`,
        // firing two watchers → two async renders racing into the same container.
        // A monotonic token lets only the latest win (both awaited loadUplot()).
        const token = (this._elToken = (this._elToken || 0) + 1);
        const UPlot = await loadUplot();
        if (token !== this._elToken || ! el.isConnected) return;
        this._destroyChart();
        el.innerHTML = '';
        const isDark = document.documentElement.classList.contains('dark');
        const grid = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const axis = isDark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.35)';
        const du = distanceUnit();
        const eu = elevationUnit();
        // xs come in km, ys in metres; convert both to the user's units for display.
        const xu = xs.map((v) => distanceValue(v * 1000, 2));
        const yu = ys.map((v) => (v == null ? null : elevationValue(v, 1)));

        const opts = {
            width: el.clientWidth || 600,
            height: 220,
            cursor: { drag: { x: false, y: false }, points: { size: 7 } },
            scales: { x: { time: false }, y: {} },
            axes: [
                {
                    stroke: axis, grid: { stroke: grid, width: 1 }, ticks: { stroke: grid, width: 1 },
                    values: (_u, vals) => vals.map((v) => v.toFixed(1) + ' ' + du),
                },
                {
                    stroke: axis, grid: { stroke: grid, width: 1 }, ticks: { stroke: grid, width: 1 }, size: 52,
                    values: (_u, vals) => vals.map((v) => Math.round(v) + ' ' + eu),
                },
            ],
            series: [
                { label: du },
                { label: labels.elevation || 'Elevation', stroke: '#7066f5', fill: 'rgba(112,102,245,0.15)', width: 2, spanGaps: true, value: (_u, v) => (v == null ? '—' : Math.round(v) + ' ' + eu) },
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
        this._chart = new UPlot(opts, [xu, yu], el);

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
});
