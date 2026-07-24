// Health tracking component (ZK). All measurements + master profile live in
// the shared opaque /store manifest — server never sees plaintext.
import { zkModule } from '../shared/zk-module';
import {
    METRICS, metric, computeAge, computeBmi, classify,
    kgToLb, lbToKg, cToF, fToC, mgdlToMmoll, mmollToMgdl,
    csvRows, csvCell,
} from '../shared/health-metrics';
import { loadUplot } from '../shared/uplot-loader';
import { saveBlobAs, formatDate } from '../shared/dom';
import {
    FAST_TEMPLATES, activeFast, fastProgress, formatDuration, formatDurationHMS, templateLabel, isValidFast,
} from '../shared/health-fasting';
import { healthUnits, setPrefs, prefs } from '../shared/prefs';
import { postForm } from '../shared/api';

const DEFAULT_PROFILE = () => ({
    birthdate: '',
    heightCm: null,
    sex: '',
    weightGoalKg: null,
    units: { weight: 'kg', glucose: 'mgdl', temp: 'c' },
});

export default (labels = {}) => ({
    ...zkModule({
        store: 'health',
        map: { healthEntries: 'entries', healthFasts: 'fasts' },
        onLock: (self) => {
            self.selectedMetric = 'weight';
            self.chartRange = '90d';
            self.profile = DEFAULT_PROFILE();
            self.editorOpen = false;
            self.editing = null;
            self.view = 'main';
            self._stopFastClock();
            self.fastEditorOpen = false;
            self.fastEditing = null;
            self._destroyChart();
            self._destroyReportCharts();
        },
    }),

    entries: [],
    fasts: [],
    profile: DEFAULT_PROFILE(),
    selectedMetric: 'weight',
    fastTemplates: FAST_TEMPLATES,
    _now: Date.now(),        // ticking clock so a running fast's elapsed updates live
    _fastClock: null,
    fastEditorOpen: false,
    fastEditing: null,       // the fast being edited
    _fastForm: { start: '', end: '', targetHours: 16, note: '' },
    chartRange: '90d', // 7d | 30d | 90d | 1y | all
    editorOpen: false,
    editing: null, // null = new, object = existing (copy being edited)
    _mut: 0,
    metrics: METRICS,
    _chartInst: null, // current uPlot instance
    // report mode
    view: 'main', // 'main' | 'report'
    reportGeneratedAt: '',
    _reportChartInsts: [], // uPlot instances mounted in the report view
    userName: labels.userName || '',

    // Editor form fields (populated on open)
    _form: { metric: 'weight', v: '', v2: '', ts: '', note: '' },
    _chartAbort: null, // AbortController for chart event listeners

    async init() {
        await this._initZk();
        if (this.state === 'ready') { this._initProfile(); this._initFasting(); this.$nextTick(() => this.renderChart()); }
        // Bind on the state transition to 'ready' (not vault.unlocked): _initZk's
        // boot is async, so at the unlocked-tick state is still 'locked' and the
        // bind would be skipped (profile edits would then be lost until reload).
        // $nextTick so the x-if="state==='ready'" subtree (chart ref) is mounted.
        this.$watch('state', (s) => { if (s === 'ready') { this._initProfile(); this._initFasting(); this.$nextTick(() => this.renderChart()); } });

        // Re-render the chart whenever the selected metric, range, or entry
        // list changes (Alpine reactivity — _mut increments on every _save()).
        // Watchers don't fire for initial values, so the first render is the
        // explicit renderChart() on the ready transition above.
        this.$watch('selectedMetric', () => this.renderChart());
        this.$watch('chartRange', () => this.renderChart());
        this.$watch('_mut', () => this.renderChart());
    },

    // Localized metric label (passed from Blade via @js since the factory's
    // `labels` closure is not visible inside Blade x-for expressions).
    metricLabel(key) { return (labels.metricLabels && labels.metricLabels[key]) || key; },

    // Ensure healthProfile exists on the store data and bind this.profile to it.
    // Mirrors how passwords.js handles folders with _migrateVaults.
    _initProfile() {
        if (! window.LLModuleStore.health.data) return;
        if (! window.LLModuleStore.health.data.healthProfile || typeof window.LLModuleStore.health.data.healthProfile !== 'object') {
            window.LLModuleStore.health.data.healthProfile = DEFAULT_PROFILE();
        }
        // Ensure nested units object exists (forward-compat for older manifests).
        if (! window.LLModuleStore.health.data.healthProfile.units) {
            window.LLModuleStore.health.data.healthProfile.units = { weight: 'kg', glucose: 'mgdl', temp: 'c' };
        }
        // Bind this.profile to the same reference so mutations are reflected in the store data.
        this.profile = window.LLModuleStore.health.data.healthProfile;
        this._seedUnitPrefs();
    },

    // One-time migration: units used to live in the sealed profile; they are now a
    // global preference. If the global prefs are still at default but this user had
    // chosen non-default sealed units, adopt them so nothing silently switches units.
    _seedUnitPrefs() {
        const g = prefs();
        const globalDefault = g.weight === 'kg' && g.temp === 'c' && g.glucose === 'mgdl';
        const u = this.profile.units || {};
        const patch = {};
        if (u.weight === 'lb') patch.weight = 'lb';
        if (u.temp === 'f') patch.temp = 'f';
        if (u.glucose === 'mmoll') patch.glucose = 'mmoll';
        if (! globalDefault || Object.keys(patch).length === 0) return;
        setPrefs(patch); // reflect immediately
        postForm('/preferences', patch).catch(() => {});
    },

    // Override zkModule._save to track mutations (mirrors passwords.js).
    _save() { this._mut++; window.LLModuleStore.health.touch(); },

    saveProfile() { this._save(); },

    // ---- Intermittent fasting (ZK — lives in the sealed `health` store) --------

    // Ensure the healthFasts array exists on the store data + bind this.fasts to it,
    // then run the live clock if a fast is active. (An older manifest has no key.)
    _initFasting() {
        const data = window.LLModuleStore.health.data;
        if (! data) return;
        if (! Array.isArray(data.healthFasts)) data.healthFasts = [];
        this.fasts = data.healthFasts;
        if (this.activeFast) this._startFastClock(); else this._stopFastClock();
    },

    // The mapped `fasts` array is bound to the (non-Alpine) store — touch _mut so
    // every fasting getter recomputes after a save.
    get activeFast() { void this._mut; return activeFast(this.fasts); },
    get fastHistory() {
        void this._mut;
        return (this.fasts || []).filter((f) => f && f.end)
            .slice().sort((a, b) => Date.parse(b.start) - Date.parse(a.start));
    },
    // Progress of the running fast, recomputed each clock tick (_now).
    get activeFastProgress() {
        void this._mut;
        const f = this.activeFast;
        return f ? fastProgress(f, this._now) : null;
    },

    fastElapsedLabel(fast, end = null) {
        const now = end ? Date.parse(end) : this._now;
        const p = fastProgress(fast, now);
        return formatDuration(p.elapsed);
    },
    fastTargetLabel(fast) { return formatDuration((Number(fast?.targetHours) || 0) * 3600); },
    // Live elapsed with seconds (HH:MM:SS) for the big running-fast timer.
    fastElapsedHMS(fast) { return formatDurationHMS(fastProgress(fast, this._now).elapsed); },
    fastWindowLabel(hours) { return templateLabel(hours); },
    fastPct(fast) {
        const p = fastProgress(fast, this._now);
        return Math.min(100, Math.round(p.fraction * 100));
    },
    // Final progress of a (finished or running) fast — for the history reached badge.
    fastProgressOf(fast) { return fast ? fastProgress(fast, fast.end ? Date.parse(fast.end) : this._now) : null; },
    fmtDate(iso) { return iso ? formatDate(iso, { dateStyle: 'medium', timeStyle: 'short' }) : ''; },

    _startFastClock() {
        if (this._fastClock) return;
        this._now = Date.now();
        this._fastClock = setInterval(() => { this._now = Date.now(); }, 1000);
    },
    _stopFastClock() {
        if (this._fastClock) { clearInterval(this._fastClock); this._fastClock = null; }
    },

    // Pull the latest sealed store so a fast started on ANOTHER device (mobile) is
    // visible before we decide whether a new fast may start. Only one fast may ever
    // be active across all clients.
    async _reloadFasting() {
        try {
            await window.LLModuleStore.health.flush();
            await window.LLModuleStore.health.load();
        } catch (e) { /* offline / transient — fall back to in-memory state */ }
        this._initProfile();
        const data = window.LLModuleStore.health.data;
        if (data) { this.entries = data.healthEntries || (data.healthEntries = []); this._initFasting(); }
        this._mut++;
    },

    async startFast(hours) {
        const h = Number(hours) || 16;
        // Re-check against the freshest server state — never start a second fast.
        await this._reloadFasting();
        if (this.activeFast) { window.llToast?.(labels.fastAlreadyRunning || 'A fast is already running.'); return; }
        this.fasts.push({
            id: window.LLModuleStore.health.newId(),
            start: new Date().toISOString(),
            end: null,
            targetHours: h,
            note: '',
        });
        this._save();
        this._startFastClock();
    },

    async stopFast() {
        const f = this.activeFast;
        if (! f) return;
        if (! await this.$store.confirm.ask(labels.fastStopConfirm || 'End this fast now?')) return;
        f.end = new Date().toISOString();
        this._save();
        this._stopFastClock();
    },

    openFastEditor(fast) {
        this.fastEditing = fast;
        const toLocal = (iso) => {
            if (! iso) return '';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return '';
            const pad = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        };
        this._fastForm = {
            start: toLocal(fast.start),
            end: fast.end ? toLocal(fast.end) : '',
            targetHours: Number(fast.targetHours) || 16,
            note: fast.note || '',
        };
        this.fastEditorOpen = true;
    },
    closeFastEditor() { this.fastEditorOpen = false; this.fastEditing = null; },

    saveFastEdit() {
        const f = this.fastEditing;
        if (! f) return;
        const start = this._fastForm.start ? new Date(this._fastForm.start).toISOString() : f.start;
        const end = this._fastForm.end ? new Date(this._fastForm.end).toISOString() : null;
        const candidate = { start, end, targetHours: Number(this._fastForm.targetHours) || 16, note: this._fastForm.note || '' };
        if (! isValidFast(candidate)) { window.llToast?.(labels.fastInvalid || 'Please check the start, end and target.'); return; }
        // Editing a finished fast back to running (end cleared) must not create a
        // second active fast.
        if (! end && this.activeFast && this.activeFast !== f) {
            window.llToast?.(labels.fastAlreadyRunning || 'A fast is already running.');
            return;
        }
        Object.assign(f, candidate);
        this._save();
        if (this.activeFast) this._startFastClock(); else this._stopFastClock();
        this.closeFastEditor();
    },

    async deleteFast(fast) {
        if (! await this.$store.confirm.ask(labels.fastDeleteConfirm || 'Delete this fast?')) return;
        const i = this.fasts.indexOf(fast);
        if (i >= 0) this.fasts.splice(i, 1);
        this._save();
        if (! this.activeFast) this._stopFastClock();
    },

    // --- Chart ---

    /**
     * Return entries for a metric key filtered to the active chartRange window,
     * sorted ascending by ts (oldest first — uPlot needs ascending x).
     *
     * @param {string} key
     * @returns {Array<{ts:string,v:number,v2:number|null}>}
     */
    entriesInRange(key, range = this.chartRange) {
        const all = this.entriesFor(key); // descending from entriesFor
        if (!all.length) return [];

        let cutoff = null;
        if (range !== 'all') {
            const days = { '7d': 7, '30d': 30, '90d': 90, '1y': 365 }[range] ?? 90;
            cutoff = Date.now() - days * 86400 * 1000;
        }

        const filtered = cutoff ? all.filter((e) => new Date(e.ts).getTime() >= cutoff) : all;
        // Return ascending (oldest first) for uPlot
        return filtered.slice().reverse();
    },

    /**
     * Destroy the current uPlot instance and abort any attached chart listeners.
     */
    _destroyChart() {
        if (this._chartAbort) {
            this._chartAbort.abort();
            this._chartAbort = null;
        }
        if (this._chartInst) {
            try { this._chartInst.destroy(); } catch (_e) { /* ignore */ }
            this._chartInst = null;
        }
    },

    /**
     * Mount or refresh the uPlot chart in $refs.chart.
     * Called reactively via $watch on selectedMetric / chartRange / _mut.
     * Delegates chart construction to _buildChart().
     */
    async renderChart() {
        // Only render metric detail views, not master data or report view.
        if (this.selectedMetric === '_master' || this.view === 'report') {
            this._destroyChart();
            return;
        }

        const container = this.$refs && this.$refs.chart;
        if (!container) return;

        const rangeData = this.entriesInRange(this.selectedMetric);

        // Empty state: destroy existing chart and bail out.
        if (!rangeData.length) {
            this._destroyChart();
            return;
        }

        // Destroy previous instance before creating a new one.
        this._destroyChart();

        const inst = await this._buildChart(container, this.selectedMetric, rangeData);
        if (!inst) return;

        this._chartInst = inst;

        // One AbortController per render — aborted by _destroyChart() before next render.
        this._chartAbort = new AbortController();
        const { signal } = this._chartAbort;
        const xs = rangeData.map((e) => Math.floor(new Date(e.ts).getTime() / 1000));

        // Double-click resets zoom to full data range.
        container.addEventListener('dblclick', () => {
            if (this._chartInst) {
                this._chartInst.setScale('x', { min: xs[0], max: xs[xs.length - 1] });
            }
        }, { signal });

        // Wheel zoom around cursor position (~10 % per tick, clamped to data bounds).
        container.addEventListener('wheel', (ev) => {
            if (!this._chartInst) return;
            ev.preventDefault();
            const u = this._chartInst;
            const scaleX = u.scales.x;
            const curMin = scaleX.min;
            const curMax = scaleX.max;
            const span   = curMax - curMin;
            const factor = ev.deltaY < 0 ? 0.9 : 1.1;
            // Fraction of width where the cursor sits (0..1).
            const rect = container.getBoundingClientRect();
            const frac = Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width));
            const pivot = curMin + frac * span;
            let newMin = pivot - frac * span * factor;
            let newMax = pivot + (1 - frac) * span * factor;
            // Clamp to data bounds.
            if (newMin < xs[0]) newMin = xs[0];
            if (newMax > xs[xs.length - 1]) newMax = xs[xs.length - 1];
            if (newMax - newMin < 60) return; // min 1-minute window
            u.setScale('x', { min: newMin, max: newMax });
        }, { signal, passive: false });
    },

    // --- CSV export ---

    /**
     * Export all entries for `key` as a CSV file download.
     * Uses RFC 4180 escaping via csvCell.
     *
     * @param {string} key
     */
    exportCsv(key) {
        const rows = csvRows(this.entries, key, this._units());
        const csv = rows.map((row) => row.map(csvCell).join(',')).join('\r\n');
        saveBlobAs(new Blob([csv], { type: 'text/csv' }), 'health-' + key + '.csv');
    },

    // --- Report mode ---

    /**
     * Enter report/print view.
     * Destroys the main chart instance first (only one uPlot allowed per container),
     * then triggers async rendering of per-metric charts.
     */
    enterReport() {
        this._destroyChart();
        this._destroyReportCharts();
        this.reportGeneratedAt = new Date().toISOString();
        this.view = 'report';
        // Defer chart rendering until Alpine has rendered the report DOM.
        this.$nextTick(() => this.renderReport());
    },

    /**
     * Exit report view and return to normal metric view.
     */
    exitReport() {
        this._destroyReportCharts();
        this.view = 'main';
    },

    /**
     * Destroy all report-view uPlot instances.
     */
    _destroyReportCharts() {
        for (const inst of this._reportChartInsts) {
            try { inst.destroy(); } catch (_e) { /* ignore */ }
        }
        this._reportChartInsts = [];
    },

    /**
     * Render a uPlot chart into a given DOM element for a specific metric.
     * Extracted from renderChart() to share the option-building logic without
     * duplication. Returns the uPlot instance (or null if no data / no UPlot).
     *
     * @param {HTMLElement} el
     * @param {string} key
     * @param {Array} rangeData  ascending entries
     * @returns {Promise<object|null>}
     */
    async _buildChart(el, key, rangeData) {
        if (!el || !rangeData.length) return null;

        const isDark = document.documentElement.classList.contains('dark');
        const m = metric(key);
        const tint = this.tintHex(m ? m.tint : 'sky');
        const isBp = key === 'bp';

        const xs  = rangeData.map((e) => Math.floor(new Date(e.ts).getTime() / 1000));
        const ys  = rangeData.map((e) => this._displaySingle(key, e.v));
        const ys2 = isBp ? rangeData.map((e) => (e.v2 != null ? e.v2 : null)) : null;

        const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const axisColor = isDark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.35)';
        const bgColor   = isDark ? '#1c1c1e' : '#ffffff';

        // temp BANDS are stored in °C; convert to display unit so band positions
        // align with the y-values that _buildChart plots via _displaySingle().
        const tempInDisplayUnit = (c) => (this._units().temp === 'f' ? cToF(c) : c);
        const BANDS = {
            bp:    null,
            pulse: { amber: [0, 60], ok: [60, 100], amberHigh: [100, 300] },
            spo2:  { red: [0, 92], amber: [92, 95], ok: [95, 101] },
            temp:  {
                ok:    [tempInDisplayUnit(0),  tempInDisplayUnit(38)],
                amber: [tempInDisplayUnit(38), tempInDisplayUnit(39)],
                red:   [tempInDisplayUnit(39), tempInDisplayUnit(50)],
            },
        };

        const drawBands = (u) => {
            const ctx = u.ctx;
            const xl  = u.bbox.left;
            const xr  = xl + u.bbox.width;

            ctx.save();
            ctx.beginPath();
            ctx.rect(xl, u.bbox.top, u.bbox.width, u.bbox.height);
            ctx.clip();

            const fillBand = (yLow, yHigh, color) => {
                const py1 = Math.round(u.valToPos(yHigh, 'y', true));
                const py2 = Math.round(u.valToPos(yLow,  'y', true));
                ctx.fillStyle = color;
                ctx.fillRect(xl, py1, xr - xl, py2 - py1);
            };

            if (key === 'bp') {
                fillBand(0, 120,  isDark ? 'rgba(34,197,94,0.07)'  : 'rgba(34,197,94,0.06)');
                fillBand(120, 140, isDark ? 'rgba(251,191,36,0.12)' : 'rgba(251,191,36,0.10)');
                fillBand(140, 300, isDark ? 'rgba(239,68,68,0.12)'  : 'rgba(239,68,68,0.10)');
            } else if (key === 'weight') {
                const goalKg = this.profile.weightGoalKg;
                if (goalKg) {
                    const goalDisplay = this._displaySingle('weight', goalKg);
                    const py = Math.round(u.valToPos(goalDisplay, 'y', true));
                    ctx.strokeStyle = isDark ? 'rgba(112,102,245,0.6)' : 'rgba(112,102,245,0.5)';
                    ctx.lineWidth = 1.5;
                    ctx.setLineDash([6, 4]);
                    ctx.beginPath();
                    ctx.moveTo(xl, py);
                    ctx.lineTo(xr, py);
                    ctx.stroke();
                    ctx.setLineDash([]);
                }
            } else if (BANDS[key]) {
                const b = BANDS[key];
                if (b.red)       fillBand(...b.red,       isDark ? 'rgba(239,68,68,0.12)'  : 'rgba(239,68,68,0.10)');
                if (b.amber)     fillBand(...b.amber,     isDark ? 'rgba(251,191,36,0.12)' : 'rgba(251,191,36,0.10)');
                if (b.amberHigh) fillBand(...b.amberHigh, isDark ? 'rgba(251,191,36,0.12)' : 'rgba(251,191,36,0.10)');
                if (b.ok)        fillBand(...b.ok,        isDark ? 'rgba(34,197,94,0.07)'  : 'rgba(34,197,94,0.06)');
            }

            ctx.restore();
        };

        const series = [
            {},
            {
                label: isBp ? 'Sys' : (m ? m.unit : ''),
                stroke: tint,
                width: 2,
                spanGaps: false,
            },
        ];
        if (isBp) {
            series.push({
                label: 'Dia',
                stroke: isDark ? '#f87171' : '#ef4444',
                width: 2,
                spanGaps: false,
            });
        }

        const data = isBp ? [xs, ys, ys2] : [xs, ys];

        const UPlot = await loadUplot();

        // Guard: element might have been removed while awaiting the lazy chunk.
        if (!el.isConnected) return null;

        const opts = {
            width:  el.clientWidth || 600,
            height: 200,
            cursor: { drag: { x: true, y: false, uni: 50 } },
            scales: { x: { time: true }, y: {} },
            axes: [
                {
                    stroke: axisColor,
                    grid:  { stroke: gridColor, width: 1 },
                    ticks: { stroke: gridColor, width: 1 },
                },
                {
                    stroke:    axisColor,
                    grid:      { stroke: gridColor, width: 1 },
                    ticks:     { stroke: gridColor, width: 1 },
                    values:    (_u, vals) => vals.map((v) => (v == null ? '' : v)),
                    labelFont: '11px system-ui',
                    font:      '11px system-ui',
                },
            ],
            series,
            hooks: {
                drawAxes: [drawBands],
                setSize: [
                    (u) => {
                        const w = el.clientWidth;
                        if (w && Math.abs(w - u.width) > 4) u.setSize({ width: w, height: 200 });
                    },
                ],
            },
            plugins: [{
                hooks: {
                    init: (u) => {
                        u.over.style.background = bgColor;
                        u.under.style.background = bgColor;
                    },
                },
            }],
        };

        return new UPlot(opts, data, el);
    },

    /**
     * Mount uPlot charts for all metrics that have entries in the report view.
     * Each metric block has a [data-report-chart="<key>"] attribute in the blade template.
     * We use querySelector rather than $refs because x-ref is static (not reactive).
     */
    async renderReport() {
        this._destroyReportCharts();
        for (const m of METRICS) {
            // A doctor report always charts the FULL history, not the live view's range.
            const data = this.entriesInRange(m.key, 'all');
            if (!data.length) continue;
            // Find the container element for this metric's chart.
            const el = this.$el && this.$el.querySelector('[data-report-chart="' + m.key + '"]');
            if (!el) continue;
            const inst = await this._buildChart(el, m.key, data);
            if (inst) this._reportChartInsts.push(inst);
        }
    },

    // reference note per metric for the report table — uses localized strings
    // passed in via the Blade labels.referenceNotes object.
    _referenceNote(key) {
        return (labels.referenceNotes && labels.referenceNotes[key]) || '';
    },

    // --- Getters ---

    get age() {
        void this._mut; // profile lives on the (non-Alpine) store — recompute on save
        return computeAge(this.profile.birthdate, new Date().toISOString());
    },

    get bmi() {
        // Both the weight entries AND the profile live on the sealed store, which
        // is NOT an Alpine proxy — without touching _mut this getter wouldn't
        // recompute when a weight is added or the height edited (the store-getter
        // gotcha), so the BMI stayed stale/empty.
        void this._mut;
        const latest = this._latestEntry('weight');
        if (! latest) return null;
        return computeBmi(latest.v, this.profile.heightCm);
    },

    // Filtered, sorted entries for a given metric key.
    entriesFor(key) {
        // `entries` is bound to the sealed store array (NOT an Alpine proxy), so
        // every store-derived reader must touch _mut to recompute after a save —
        // otherwise latestFor/avgFor/minFor/maxFor/bmi stay stale until reload.
        void this._mut;
        return this.entries
            .filter((e) => e.metric === key)
            .sort((a, b) => (b.ts < a.ts ? -1 : b.ts > a.ts ? 1 : 0));
    },

    _latestEntry(key) {
        const list = this.entriesFor(key);
        return list.length ? list[0] : null;
    },

    // --- Stats ---

    latestFor(key) {
        const e = this._latestEntry(key);
        if (! e) return null;
        return this._displayValue(key, e.v, e.v2);
    },

    avgFor(key) {
        const list = this.entriesFor(key);
        if (! list.length) return null;
        // Blood pressure aggregates BOTH series (systolic / diastolic).
        if (key === 'bp') {
            const mean = (xs) => Math.round(xs.reduce((s, x) => s + x, 0) / xs.length);
            const dia = list.map((e) => e.v2).filter((x) => x != null);
            return this._displayValue(key, mean(list.map((e) => e.v)), dia.length ? mean(dia) : null);
        }
        return this._displaySingle(key, list.reduce((s, e) => s + e.v, 0) / list.length);
    },

    minFor(key) {
        const list = this.entriesFor(key);
        if (! list.length) return null;
        if (key === 'bp') {
            const dia = list.map((e) => e.v2).filter((x) => x != null);
            return this._displayValue(key, Math.min(...list.map((e) => e.v)), dia.length ? Math.min(...dia) : null);
        }
        return this._displaySingle(key, Math.min(...list.map((e) => e.v)));
    },

    maxFor(key) {
        const list = this.entriesFor(key);
        if (! list.length) return null;
        if (key === 'bp') {
            const dia = list.map((e) => e.v2).filter((x) => x != null);
            return this._displayValue(key, Math.max(...list.map((e) => e.v)), dia.length ? Math.max(...dia) : null);
        }
        return this._displaySingle(key, Math.max(...list.map((e) => e.v)));
    },

    // Convert a canonical value pair to a display string for the current units.
    _displayValue(key, v, v2) {
        if (key === 'bp') {
            return v + '/' + (v2 ?? '?');
        }
        return String(this._displaySingle(key, v));
    },

    // Display units now come from the GLOBAL user preference (set on the appearance
    // page, "like the language"), not the sealed health profile. weight/temp/glucose
    // tokens are byte-compatible (kg|lb, c|f, mgdl|mmoll).
    _units() { return healthUnits(); },

    // Convert a single canonical value to display units.
    _displaySingle(key, v) {
        const u = this._units();
        if (key === 'weight' && u.weight === 'lb') return kgToLb(v);
        if (key === 'temp' && u.temp === 'f') return cToF(v);
        if (key === 'glucose' && u.glucose === 'mmoll') return mgdlToMmoll(v);
        return Math.round(v * 10) / 10;
    },

    // Display unit label for a metric.
    unitLabel(key) {
        const u = this._units();
        if (key === 'weight') return u.weight === 'lb' ? 'lb' : 'kg';
        if (key === 'temp') return u.temp === 'f' ? '°F' : '°C';
        if (key === 'glucose') return u.glucose === 'mmoll' ? 'mmol/L' : 'mg/dL';
        return metric(key)?.unit ?? '';
    },

    // Classification colour dot for a metric's latest entry.
    classifyLatest(key) {
        const e = this._latestEntry(key);
        if (! e) return 'ok';
        return classify(key, e.v, e.v2);
    },

    // Tint colour for a metric chip, resolved from the tailwind name to a hex.
    // The tint string comes from METRICS and is a tailwind colour name; we map
    // to a hardcoded hex so the style attribute is static (no CDN needed).
    tintHex(tintName) {
        const map = {
            sky: '#0ea5e9', rose: '#f43f5e', pink: '#ec4899',
            blue: '#3b82f6', amber: '#f59e0b', green: '#22c55e',
        };
        return map[tintName] || '#6b7280';
    },

    // --- Editor ---

    openAdd() {
        this.editing = null;
        const now = new Date();
        // datetime-local value: YYYY-MM-DDTHH:MM
        const pad = (n) => String(n).padStart(2, '0');
        const local = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
            + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
        this._form = { metric: this.selectedMetric, v: '', v2: '', ts: local, note: '' };
        this.editorOpen = true;
    },

    openEdit(entry) {
        this.editing = entry;
        const dt = new Date(entry.ts);
        const pad = (n) => String(n).padStart(2, '0');
        const local = dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate())
            + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
        // Convert stored canonical value to display unit for editing.
        const displayV = this._displaySingle(entry.metric, entry.v);
        const displayV2 = entry.v2 != null ? String(entry.v2) : '';
        this._form = { metric: entry.metric, v: String(displayV), v2: displayV2, ts: local, note: entry.note || '' };
        this.editorOpen = true;
    },

    closeEditor() {
        this.editorOpen = false;
        this.editing = null;
    },

    saveEditor() {
        const v = parseFloat(this._form.v);
        if (isNaN(v) || v <= 0) return;
        const ts = this._form.ts ? new Date(this._form.ts).toISOString() : new Date().toISOString();
        const key = this._form.metric;

        // Convert display units back to canonical storage units.
        let canonV = v;
        const u = this._units();
        if (key === 'weight' && u.weight === 'lb') canonV = lbToKg(v);
        if (key === 'temp' && u.temp === 'f') canonV = fToC(v);
        if (key === 'glucose' && u.glucose === 'mmoll') canonV = mmollToMgdl(v);

        let canonV2 = null;
        if (key === 'bp') {
            const raw2 = parseFloat(this._form.v2);
            if (! isNaN(raw2) && raw2 > 0) canonV2 = raw2;
        }

        if (this.editing) {
            // Update in place (same reference, array is bound to the store data).
            const idx = this.entries.findIndex((e) => e.id === this.editing.id);
            if (idx >= 0) {
                this.entries[idx] = { ...this.entries[idx], v: canonV, v2: canonV2, ts, note: this._form.note };
            }
        } else {
            this.entries.unshift({
                id: crypto.randomUUID(),
                metric: key,
                v: canonV,
                v2: canonV2,
                ts,
                note: this._form.note,
            });
        }

        this._save();
        this.closeEditor();
    },

    // Expose classify for use in Alpine x-bind expressions.
    classify(key, v, v2) { return classify(key, v, v2); },

    async deleteEntry(entry) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm || '')) return;
        const idx = this.entries.findIndex((e) => e.id === entry.id);
        if (idx >= 0) this.entries.splice(idx, 1);
        this._save();
    },
});
