// Health tracking component (ZK). All measurements + master profile live in
// the shared opaque /store manifest — server never sees plaintext.
import { zkModule } from '../shared/zk-module';
import {
    METRICS, metric, computeAge, computeBmi, classify,
    kgToLb, lbToKg, cToF, fToC, mgdlToMmoll, mmollToMgdl,
} from '../shared/health-metrics';
import { loadUplot } from '../shared/uplot-loader';

const DEFAULT_PROFILE = () => ({
    birthdate: '',
    heightCm: null,
    sex: '',
    weightGoalKg: null,
    units: { weight: 'kg', glucose: 'mgdl', temp: 'c' },
});

export default (labels = {}) => ({
    ...zkModule({
        map: { healthEntries: 'entries' },
        onLock: (self) => {
            self.selectedMetric = 'weight';
            self.chartRange = '90d';
            self.profile = DEFAULT_PROFILE();
            self.editorOpen = false;
            self.editing = null;
            self._destroyChart();
        },
    }),

    entries: [],
    profile: DEFAULT_PROFILE(),
    selectedMetric: 'weight',
    chartRange: '90d', // 7d | 30d | 90d | 1y | all
    editorOpen: false,
    editing: null, // null = new, object = existing (copy being edited)
    _mut: 0,
    metrics: METRICS,
    _chartInst: null, // current uPlot instance

    // Editor form fields (populated on open)
    _form: { metric: 'weight', v: '', v2: '', ts: '', note: '' },

    async init() {
        await this._initZk();
        if (this.state === 'ready') this._initProfile();
        // Bind on the state transition to 'ready' (not vault.unlocked): _initZk's
        // boot is async, so at the unlocked-tick state is still 'locked' and the
        // bind would be skipped (profile edits would then be lost until reload).
        this.$watch('state', (s) => { if (s === 'ready') this._initProfile(); });

        // Re-render the chart whenever the selected metric, range, or entry
        // list changes (Alpine reactivity — _mut increments on every _save()).
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
        if (! window.LLStore.data) return;
        if (! window.LLStore.data.healthProfile || typeof window.LLStore.data.healthProfile !== 'object') {
            window.LLStore.data.healthProfile = DEFAULT_PROFILE();
        }
        // Ensure nested units object exists (forward-compat for older manifests).
        if (! window.LLStore.data.healthProfile.units) {
            window.LLStore.data.healthProfile.units = { weight: 'kg', glucose: 'mgdl', temp: 'c' };
        }
        // Bind this.profile to the same reference so mutations are reflected in LLStore.data.
        this.profile = window.LLStore.data.healthProfile;
    },

    // Override zkModule._save to track mutations (mirrors passwords.js).
    _save() { this._mut++; window.LLStore.touch(); },

    saveProfile() { this._save(); },

    // --- Chart ---

    /**
     * Return entries for a metric key filtered to the active chartRange window,
     * sorted ascending by ts (oldest first — uPlot needs ascending x).
     *
     * @param {string} key
     * @returns {Array<{ts:string,v:number,v2:number|null}>}
     */
    entriesInRange(key) {
        const all = this.entriesFor(key); // descending from entriesFor
        if (!all.length) return [];

        let cutoff = null;
        if (this.chartRange !== 'all') {
            const days = { '7d': 7, '30d': 30, '90d': 90, '1y': 365 }[this.chartRange] ?? 90;
            cutoff = Date.now() - days * 86400 * 1000;
        }

        const filtered = cutoff ? all.filter((e) => new Date(e.ts).getTime() >= cutoff) : all;
        // Return ascending (oldest first) for uPlot
        return filtered.slice().reverse();
    },

    /**
     * Destroy the current uPlot instance if any.
     */
    _destroyChart() {
        if (this._chartInst) {
            try { this._chartInst.destroy(); } catch (_e) { /* ignore */ }
            this._chartInst = null;
        }
    },

    /**
     * Mount or refresh the uPlot chart in $refs.chart.
     * Called reactively via $watch on selectedMetric / chartRange / _mut.
     */
    async renderChart() {
        // Only render metric detail views, not master data.
        if (this.selectedMetric === '_master') { this._destroyChart(); return; }

        const container = this.$refs && this.$refs.chart;
        if (!container) return;

        const rangeData = this.entriesInRange(this.selectedMetric);

        // Empty state: destroy existing chart and bail out.
        if (!rangeData.length) {
            this._destroyChart();
            return;
        }

        const isDark = document.documentElement.classList.contains('dark');
        const m = metric(this.selectedMetric);
        const tint = this.tintHex(m ? m.tint : 'sky');
        const isBp = this.selectedMetric === 'bp';

        // Build x (timestamps in seconds) and y series arrays.
        const xs = rangeData.map((e) => Math.floor(new Date(e.ts).getTime() / 1000));
        const ys = rangeData.map((e) => this._displaySingle(this.selectedMetric, e.v));
        const ys2 = isBp ? rangeData.map((e) => (e.v2 != null ? e.v2 : null)) : null;

        const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const axisColor = isDark ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.35)';
        const bgColor   = isDark ? '#1c1c1e' : '#ffffff';

        // Reference band thresholds per metric (canonical units).
        // weight and glucose have no hard bands per spec.
        const BANDS = {
            bp:    null, // handled per-series below
            pulse: { amber: [0, 60], ok: [60, 100], amberHigh: [100, 300] },
            spo2:  { red: [0, 92], amber: [92, 95], ok: [95, 101] },
            temp:  { ok: [0, 38], amber: [38, 39], red: [39, 50] },
        };

        const drawBands = (u) => {
            const key = this.selectedMetric;
            const ctx  = u.ctx;
            const xl   = u.bbox.left;
            const xr   = xl + u.bbox.width;

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
                // Systolic bands (series 1 = sys = v)
                fillBand(0, 120,  isDark ? 'rgba(34,197,94,0.07)'   : 'rgba(34,197,94,0.06)');
                fillBand(120, 140, isDark ? 'rgba(251,191,36,0.12)'  : 'rgba(251,191,36,0.10)');
                fillBand(140, 300, isDark ? 'rgba(239,68,68,0.12)'   : 'rgba(239,68,68,0.10)');
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
                if (b.red)       fillBand(...b.red,       isDark ? 'rgba(239,68,68,0.12)'   : 'rgba(239,68,68,0.10)');
                if (b.amber)     fillBand(...b.amber,     isDark ? 'rgba(251,191,36,0.12)'  : 'rgba(251,191,36,0.10)');
                if (b.amberHigh) fillBand(...b.amberHigh, isDark ? 'rgba(251,191,36,0.12)'  : 'rgba(251,191,36,0.10)');
                if (b.ok)        fillBand(...b.ok,        isDark ? 'rgba(34,197,94,0.07)'   : 'rgba(34,197,94,0.06)');
            }

            ctx.restore();
        };

        const series = [
            {}, // x-axis placeholder
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

        // Destroy previous instance before creating a new one.
        this._destroyChart();

        const UPlot = await loadUplot();

        // Container might have been removed while awaiting (metric change, lock).
        const mountEl = this.$refs && this.$refs.chart;
        if (!mountEl) return;

        const opts = {
            width:  mountEl.clientWidth  || 600,
            height: 220,
            cursor: {
                drag: { x: true, y: false, uni: 50 },
            },
            scales: { x: { time: true }, y: {} },
            axes: [
                {
                    stroke: axisColor,
                    grid:   { stroke: gridColor, width: 1 },
                    ticks:  { stroke: gridColor, width: 1 },
                },
                {
                    stroke:     axisColor,
                    grid:       { stroke: gridColor, width: 1 },
                    ticks:      { stroke: gridColor, width: 1 },
                    values:     (_u, vals) => vals.map((v) => (v == null ? '' : v)),
                    labelFont:  '11px system-ui',
                    font:       '11px system-ui',
                },
            ],
            series,
            hooks: {
                drawAxes: [drawBands],
                setSize: [
                    (u) => {
                        const w = mountEl.clientWidth;
                        if (w && Math.abs(w - u.width) > 4) u.setSize({ width: w, height: 220 });
                    },
                ],
            },
        };

        // Patch background fill
        opts.plugins = [{
            hooks: {
                init: (u) => {
                    u.over.style.background = bgColor;
                    u.under.style.background = bgColor;
                },
            },
        }];

        this._chartInst = new UPlot(opts, data, mountEl);

        // Double-click resets zoom
        mountEl.addEventListener('dblclick', () => {
            if (this._chartInst) {
                this._chartInst.setScale('x', { min: xs[0], max: xs[xs.length - 1] });
            }
        }, { once: false });
    },

    // --- Getters ---

    get age() {
        return computeAge(this.profile.birthdate, new Date().toISOString());
    },

    get bmi() {
        const latest = this._latestEntry('weight');
        if (! latest) return null;
        return computeBmi(latest.v, this.profile.heightCm);
    },

    // Filtered, sorted entries for a given metric key.
    entriesFor(key) {
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
        const avg = list.reduce((s, e) => s + e.v, 0) / list.length;
        return this._displaySingle(key, avg);
    },

    minFor(key) {
        const list = this.entriesFor(key);
        if (! list.length) return null;
        const min = Math.min(...list.map((e) => e.v));
        return this._displaySingle(key, min);
    },

    maxFor(key) {
        const list = this.entriesFor(key);
        if (! list.length) return null;
        const max = Math.max(...list.map((e) => e.v));
        return this._displaySingle(key, max);
    },

    // Convert a canonical value pair to a display string for the current units.
    _displayValue(key, v, v2) {
        if (key === 'bp') {
            return v + '/' + (v2 ?? '?');
        }
        return String(this._displaySingle(key, v));
    },

    // Convert a single canonical value to display units.
    _displaySingle(key, v) {
        const u = this.profile.units || {};
        if (key === 'weight' && u.weight === 'lb') return kgToLb(v);
        if (key === 'temp' && u.temp === 'f') return cToF(v);
        if (key === 'glucose' && u.glucose === 'mmoll') return mgdlToMmoll(v);
        return Math.round(v * 10) / 10;
    },

    // Display unit label for a metric.
    unitLabel(key) {
        const u = this.profile.units || {};
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
        const u = this.profile.units || {};
        if (key === 'weight' && u.weight === 'lb') canonV = lbToKg(v);
        if (key === 'temp' && u.temp === 'f') canonV = fToC(v);
        if (key === 'glucose' && u.glucose === 'mmoll') canonV = mmollToMgdl(v);

        let canonV2 = null;
        if (key === 'bp') {
            const raw2 = parseFloat(this._form.v2);
            if (! isNaN(raw2) && raw2 > 0) canonV2 = raw2;
        }

        if (this.editing) {
            // Update in place (same reference, array is bound to LLStore.data).
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
