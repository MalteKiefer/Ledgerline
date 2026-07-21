// Dashboard component — reads the decrypted workspace manifest and gallery store
// to populate widgets (todos, counters, recent notes, birthdays, health).
// Gallery is best-effort: the widget degrades gracefully if unavailable.
import { bootStore, bootGalleryStore } from '../shared/zk-module';
import { sortTodos, upcomingBirthdays } from '../shared/dashboard-utils';
import { getJson } from '../shared/api';
import {
    METRICS, metric,
    kgToLb, lbToKg, cToF, fToC, mgdlToMmoll, mmollToMgdl,
} from '../shared/health-metrics';
import { loadUplot } from '../shared/uplot-loader';

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | ready
    _mut: 0,
    galleryReady: false,
    usage: { files: null, gallery: null },
    quickAdd: { metric: 'weight', v: '', v2: '' },
    _sparkInst: null,

    async init() {
        await this._boot();
        this.$watch('$store.vault.unlocked', async (on) => {
            if (on && this.state !== 'ready') await this._boot();
            if (! on) this.state = 'locked';
        });
        this.$watch('_mut', () => this.renderSpark());
    },

    async _boot() {
        // Workspace store is required; gallery is best-effort (photo widget degrades).
        if (! await bootStore(this.$store)) { this.state = 'locked'; return; }
        this.state = 'ready';
        try { this.galleryReady = await bootGalleryStore(this.$store); } catch (_e) { this.galleryReady = false; }
        this._loadUsage();
        this.$nextTick(() => this.renderSpark());
    },

    get _s() { return window.LLStore?.data ?? null; },
    get _g() { return this.galleryReady ? (window.LLGalleryStore?.data ?? null) : null; },

    // --- Todos widget ---
    get todos() {
        void this._mut;
        return this._s ? sortTodos(this._s.todos ?? [], new Date().toISOString().slice(0, 10)).slice(0, 6) : [];
    },

    completeTodo(id) {
        const t = (this._s?.todos ?? []).find((x) => x.id === id);
        if (t) { t.done = true; window.LLStore.touch(); this._mut++; }
    },

    // --- Counter tiles ---
    get counts() {
        const s = this._s ?? {};
        return {
            notes: (s.notes ?? []).filter((n) => ! n.trashed).length,
            passwords: (s.secrets ?? []).length,
            contacts: (s.contacts ?? []).length,
            bookmarks: (s.bookmarks ?? []).length,
            invoices: (s.invoices ?? []).length,
            files: (s.files ?? []).filter((f) => ! f.trashed).length,
        };
    },

    // --- Recent notes ---
    get recentNotes() {
        return (this._s?.notes ?? []).filter((n) => ! n.trashed)
            .slice().sort((a, b) => (b.updated ?? '').localeCompare(a.updated ?? '')).slice(0, 5);
    },

    // --- Birthdays widget ---
    get birthdays() {
        return this._s ? upcomingBirthdays(this._s.contacts ?? [], new Date().toISOString().slice(0, 10), 30) : [];
    },

    // --- Health widget ---
    get healthProfile() { return this._s?.healthProfile ?? null; },

    get healthLatest() {
        const entries = this._s?.healthEntries ?? [];
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
    _displaySingle(key, v) {
        const u = this.healthProfile?.units ?? {};
        if (key === 'weight' && u.weight === 'lb') return kgToLb(v);
        if (key === 'temp' && u.temp === 'f') return cToF(v);
        if (key === 'glucose' && u.glucose === 'mmoll') return mgdlToMmoll(v);
        return Math.round(v * 10) / 10;
    },

    // Convert a display-unit value back to canonical storage units (mirrors health.js saveEditor).
    _toCanonical(key, v) {
        const u = this.healthProfile?.units ?? {};
        if (key === 'weight' && u.weight === 'lb') return lbToKg(v);
        if (key === 'temp' && u.temp === 'f') return fToC(v);
        if (key === 'glucose' && u.glucose === 'mmoll') return mmollToMgdl(v);
        return v;
    },

    // Unit label for a metric (display unit).
    _unitLabel(key) {
        const u = this.healthProfile?.units ?? {};
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

    saveQuickAdd() {
        const m = this.quickAdd.metric;
        const v = parseFloat(this.quickAdd.v);
        if (! Number.isFinite(v)) return;
        const canon = this._toCanonical(m, v);
        const v2 = m === 'bp' ? (parseFloat(this.quickAdd.v2) || null) : null;
        (this._s.healthEntries ||= []).push({
            id: window.LLStore.newId(),
            ts: new Date().toISOString(),
            metric: m,
            v: canon,
            v2,
            note: '',
        });
        window.LLStore.touch();
        this._mut++;
        this.quickAdd.v = '';
        this.quickAdd.v2 = '';
    },

    // --- Weight sparkline ---
    async renderSpark() {
        const container = this.$refs && this.$refs.spark;
        if (! container) return;

        // Collect last 30 weight entries, ascending by ts.
        const entries = (this._s?.healthEntries ?? [])
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
        try { this.usage.files = await getJson('/files/usage'); } catch (_e) { /* widget shows — */ }
        try { this.usage.gallery = await getJson('/gallery/usage'); } catch (_e) { /* — */ }
    },
});
