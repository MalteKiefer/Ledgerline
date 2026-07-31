// Dashboard component — reads plaintext-relational module data over REST
// to populate widgets (todos, counters, recent notes, health, gallery).
// Gallery is best-effort: the widget degrades gracefully if unavailable.
import { sortTodos, yearsAgoPhotos } from '../shared/dashboard-utils';
import { getJson, postForm } from '../shared/api';
import {
    METRICS, metric,
    kgToLb, lbToKg, cToF, fToC, mgdlToMmoll, mmollToMgdl,
} from '../shared/health-metrics';
import { loadUplot } from '../shared/uplot-loader';
import { formatBytes } from '../shared/file-categories';
import { activeFast, fastProgress, formatDuration, formatDurationHMS, templateLabel } from '../shared/health-fasting';
import { healthUnits } from '../shared/prefs';

// Map plaintext-relational health rows (snake_case) to the shape the widgets +
// pure helpers expect. v/v2 may arrive as JSON STRINGS → coerce to Number;
// fasts map start_at→start, end_at→end, target_hours→targetHours.
const dashEntry = (e) => ({
    id: e.id,
    metric: e.metric,
    ts: e.ts,
    v: e.v == null || e.v === '' ? 0 : Number(e.v),
    v2: e.v2 == null || e.v2 === '' ? null : Number(e.v2),
});
const dashFast = (f) => ({
    id: f.id,
    start: f.start_at ?? null,
    end: f.end_at ?? null,
    targetHours: Number(f.target_hours) || 0,
});

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | ready
    _mut: 0,
    galleryReady: false,
    usage: { files: null, gallery: null },
    quickAdd: { metric: 'weight', v: '', v2: '' },
    _sparkInst: null,
    _thumbCache: {}, // photoId -> objectURL
    _thumbPending: {}, // photoId -> in-flight promise
    _fastNow: Date.now(), // clock for the running-fast widget
    _fastClock: null,

    async init() {
        await this._boot();
        this.$watch('_mut', () => this.renderSpark());
        // Start/stop the 1s clock off the reactive activeFast getter (not a one-shot
        // state check): the health data loads async, so activeFast is false at
        // state-ready and a state-gated start would never fire on reload. The
        // watcher starts it the moment a running fast appears.
        this.$watch('activeFast', (f) => { if (f) this._startFastClock(); else this._stopFastClock(); });
        // Cover the already-active-at-boot case (watchers don't fire for the initial value).
        if (this.activeFast) this._startFastClock();
    },

    destroy() {
        this._revokeThumbCache();
        this._stopFastClock();
    },

    _startFastClock() {
        if (this._fastClock) return;
        this._fastNow = Date.now();
        // 1s so the running-fast banner shows live seconds (only ticks while a fast runs).
        this._fastClock = setInterval(() => { this._fastNow = Date.now(); }, 1000);
    },
    _stopFastClock() { if (this._fastClock) { clearInterval(this._fastClock); this._fastClock = null; } },

    // --- Running-fast widget (intermittent fasting; always shown while active) ---
    get activeFast() { void this._mut; return this._health ? activeFast(this._health.healthFasts) : null; },
    get activeFastProgress() {
        void this._mut;
        const f = this.activeFast;
        return f ? fastProgress(f, this._fastNow) : null;
    },
    fastWindowLabel(hours) { return templateLabel(hours); },
    fastElapsedLabel(fast) { return formatDuration(fastProgress(fast, this._fastNow).elapsed); },
    // Live elapsed with seconds (HH:MM:SS) for the running-fast banner.
    fastElapsedHMS(fast) { return formatDurationHMS(fastProgress(fast, this._fastNow).elapsed); },
    fastTargetLabel(fast) { return formatDuration((Number(fast?.targetHours) || 0) * 3600); },
    fastPct(fast) { return Math.min(100, Math.round(fastProgress(fast, this._fastNow).fraction * 100)); },

    async _boot() {
        // All modules are plaintext-relational now (pivot) — no vault gate; the
        // session auth is enough. Load every widget's data over plain REST.
        await this._loadRelational();

        this.state = 'ready';
        this._loadUsage();
        this.$nextTick(() => this.renderSpark());
    },

    _revokeThumbCache() {
        for (const url of Object.values(this._thumbCache)) {
            try { URL.revokeObjectURL(url); } catch (_e) { /* ignore */ }
        }
        this._thumbCache = {};
        this._thumbPending = {};
    },

    // Plaintext-relational widgets (pivot): notes/todos/bookmarks/health/files via REST.
    _relNotes: [],
    _relTodos: [],
    _relBookmarksCount: 0,
    _relFiles: [],
    _relHealth: null, // { healthEntries, healthFasts }
    _relInvoices: [],
    _relPhotos: [],
    async _loadRelational() {
        const [nt, td, bm, fe, hd, fin, gal] = await Promise.all([
            getJson('/notes/list').catch(() => ({ notes: [] })),
            getJson('/todos/list').catch(() => ({ todos: [] })),
            getJson('/bookmarks/list').catch(() => ({ bookmarks: [] })),
            getJson('/files/entries').catch(() => ({ files: [], usage: null })),
            getJson('/health/data').catch(() => ({ entries: [], fasts: [] })),
            getJson('/finance/data').catch(() => ({ invoices: [] })),
            getJson('/gallery/data').catch(() => ({ photos: [], usage: null })),
        ]);
        this._relInvoices = fin.invoices ?? [];
        this._relPhotos = (gal.photos ?? []).map((p) => ({ id: p.id, taken_at: p.taken_at, created: p.created_at }));
        if (gal.usage) this.usage.gallery = gal.usage;
        this._relNotes = (nt.notes ?? []).map((n) => ({ id: n.id, title: n.title, updated: n.updated_at }));
        this._relTodos = (td.todos ?? []).map((t) => ({
            id: t.id, title: t.title, done: !! t.done, marked: !! t.marked,
            priority: t.priority, due: (t.due ?? '').slice(0, 10),
        }));
        this._relBookmarksCount = (bm.bookmarks ?? []).length;
        this._relFiles = fe.files ?? [];
        if (fe.usage) this.usage.files = fe.usage;
        this._relHealth = { healthEntries: (hd.entries ?? []).map(dashEntry), healthFasts: (hd.fasts ?? []).map(dashFast) };
        this._mut++;
    },

    // Re-pull just the health snapshot (after a quick-add) so the chips + sparkline
    // + fasting banner recompute.
    async _refreshHealth() {
        try {
            const hd = await getJson('/health/data');
            this._relHealth = { healthEntries: (hd.entries ?? []).map(dashEntry), healthFasts: (hd.fasts ?? []).map(dashFast) };
            this._mut++;
        } catch (_e) { /* keep in-memory state */ }
    },

    // Per-module data getters. Notes/todos/bookmarks/health/files/finance/gallery
    // are all plaintext-relational (pivot), loaded over REST.
    get _health() { return this._relHealth; },
    get _invoices() { return { invoices: this._relInvoices }; },
    get _g() { return { photos: this._relPhotos }; },

    // --- Todos widget ---
    get todos() {
        void this._mut;
        return sortTodos(this._relTodos, new Date().toISOString().slice(0, 10)).slice(0, 6);
    },

    async completeTodo(id) {
        const t = this._relTodos.find((x) => x.id === id);
        if (! t) return;
        t.done = true;
        this._relTodos = this._relTodos.filter((x) => x.id !== id);
        this._mut++;
        try { await postForm('/todos/' + id + '/toggle', { field: 'done', value: true }); } catch (_e) { /* best-effort */ }
    },

    // --- Counter tiles ---
    get counts() {
        void this._mut; // recompute after an in-memory mutation (plain arrays aren't deeply reactive)
        return {
            notes: this._relNotes.length,
            bookmarks: this._relBookmarksCount,
            invoices: (this._invoices?.invoices ?? []).length,
            files: this._relFiles.filter((f) => ! f.deleted_at).length,
        };
    },

    // --- Recent notes ---
    get recentNotes() {
        void this._mut;
        return this._relNotes.slice()
            .sort((a, b) => (b.updated ?? '').localeCompare(a.updated ?? '')).slice(0, 5);
    },

    // --- Health widget ---
    get healthLatest() {
        void this._mut;
        const entries = this._health?.healthEntries ?? [];
        return METRICS.map((m) => {
            const last = entries
                .filter((e) => e.metric === m.key)
                .sort((a, b) => (b.ts ?? '').localeCompare(a.ts ?? ''))[0];
            return last ? { key: m.key, label: m.labelKey, tint: m.tint, display: this._displayHealth(m.key, last.v, last.v2) } : null;
        }).filter(Boolean);
    },

    // Convert a canonical value pair to a display string (mirrors health.js _displayValue).
    _displayHealth(key, v, v2) {
        if (key === 'bp') return v + '/' + (v2 ?? '?');
        return String(this._displaySingle(key, v));
    },

    // Convert a single canonical value to display units (mirrors health.js _displaySingle).
    // Units come from the global preference (shared/prefs.js), not per-record.
    _displaySingle(key, v) {
        const u = healthUnits();
        if (key === 'weight' && u.weight === 'lb') return kgToLb(v);
        if (key === 'temp' && u.temp === 'f') return cToF(v);
        if (key === 'glucose' && u.glucose === 'mmoll') return mgdlToMmoll(v);
        return Math.round(v * 10) / 10;
    },

    // Convert a display-unit value back to canonical storage units (mirrors health.js saveEditor).
    _toCanonical(key, v) {
        const u = healthUnits();
        if (key === 'weight' && u.weight === 'lb') return lbToKg(v);
        if (key === 'temp' && u.temp === 'f') return fToC(v);
        if (key === 'glucose' && u.glucose === 'mmoll') return mmollToMgdl(v);
        return v;
    },

    // Unit label for a metric (display unit).
    _unitLabel(key) {
        const u = healthUnits();
        if (key === 'weight') return u.weight === 'lb' ? 'lb' : 'kg';
        if (key === 'temp') return u.temp === 'f' ? '°F' : '°C';
        if (key === 'glucose') return u.glucose === 'mmoll' ? 'mmol/L' : 'mg/dL';
        return metric(key)?.unit ?? '';
    },

    // Map tint name to hex (mirrors health.js tintHex).
    _tintHex(tintName) {
        const map = {
            sky: '#0ea5e9', rose: '#f43f5e', pink: '#ec4899',
            blue: '#3b82f6', amber: '#f59e0b', green: '#22c55e',
        };
        return map[tintName] || '#6b7280';
    },

    async saveQuickAdd() {
        const m = this.quickAdd.metric;
        const v = parseFloat(this.quickAdd.v);
        if (! Number.isFinite(v)) return;
        const canon = this._toCanonical(m, v);
        const v2 = m === 'bp' ? (parseFloat(this.quickAdd.v2) || null) : null;
        this.quickAdd.v = '';
        this.quickAdd.v2 = '';
        try {
            await postForm('/health/entries', { metric: m, ts: new Date().toISOString(), v: canon, v2, note: '' });
            await this._refreshHealth();
        } catch (_e) { /* best-effort */ }
    },

    // --- Weight sparkline ---
    async renderSpark() {
        const container = this.$refs && this.$refs.spark;
        if (! container) return;

        // Collect last 30 weight entries, ascending by ts.
        const entries = (this._health?.healthEntries ?? [])
            .filter((e) => e.metric === 'weight')
            .sort((a, b) => (a.ts ?? '').localeCompare(b.ts ?? ''))
            .slice(-30);

        if (! entries.length) {
            if (this._sparkInst) {
                try { this._sparkInst.destroy(); } catch (_e) { /* ignore */ }
                this._sparkInst = null;
            }
            return;
        }

        // Destroy prior instance before recreating.
        if (this._sparkInst) {
            try { this._sparkInst.destroy(); } catch (_e) { /* ignore */ }
            this._sparkInst = null;
        }

        const UPlot = await loadUplot();
        if (! container.isConnected) return;

        const xs = entries.map((e) => Math.floor(new Date(e.ts).getTime() / 1000));
        const ys = entries.map((e) => this._displaySingle('weight', e.v));

        const opts = {
            width:  container.clientWidth || 280,
            height: 48,
            cursor: { show: false },
            legend: { show: false },
            scales: { x: { time: true }, y: {} },
            axes:   [{ show: false }, { show: false }],
            series: [
                {},
                { stroke: '#7066f5', width: 2, spanGaps: false },
            ],
            plugins: [{
                hooks: {
                    init: (u) => {
                        u.over.style.background  = 'transparent';
                        u.under.style.background = 'transparent';
                        u.root.style.background  = 'transparent';
                    },
                },
            }],
        };

        this._sparkInst = new UPlot(opts, [xs, ys], container);
    },

    async _loadUsage() {
        // Files usage now comes from the /files/entries payload in _loadRelational().
    },

    // --- On This Day widget ---
    // Groups past-year photos whose month+day match today, sorted nearest first.
    get onThisDay() {
        return yearsAgoPhotos(this._relPhotos, new Date().toISOString().slice(0, 10));
    },

    // Gallery photos are plaintext now (pivot) — the thumbnail is a direct URL.
    async thumbUrl(photo) {
        return photo?.id ? '/gallery/photos/' + photo.id + '/thumb' : '';
    },

    // --- Storage widget ---
    // Exposes the usage data loaded by _loadUsage() in a form suitable for
    // the storage bars. Returns { used, quota } for each store, or null while loading.
    // formatBytes is imported for use in Blade via a window-exposed helper.
    _fmtBytes(n) {
        return n == null ? '—' : formatBytes(n);
    },
});
