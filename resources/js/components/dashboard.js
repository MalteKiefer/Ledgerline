// Dashboard component — reads the decrypted workspace manifest and gallery store
// to populate widgets (todos, counters, recent notes, birthdays, health).
// Gallery is best-effort: the widget degrades gracefully if unavailable.
import { bootGalleryStore } from '../shared/zk-module';
import { sortTodos, upcomingBirthdays, yearsAgoPhotos } from '../shared/dashboard-utils';
import { contactDisplayName } from '../shared/contact-utils';
import { getJson } from '../shared/api';
import {
    METRICS, metric,
    kgToLb, lbToKg, cToF, fToC, mgdlToMmoll, mmollToMgdl,
} from '../shared/health-metrics';
import { loadUplot } from '../shared/uplot-loader';
import { fetchDecryptWorker, thumbLane } from '../shared/blob-io';
import { formatBytes } from '../shared/file-categories';

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | ready
    _mut: 0,
    galleryReady: false,
    usage: { files: null, gallery: null },
    quickAdd: { metric: 'weight', v: '', v2: '' },
    _sparkInst: null,
    _thumbCache: {}, // photoId -> objectURL
    _thumbPending: {}, // photoId -> in-flight promise

    async init() {
        await this._boot();
        this.$watch('$store.vault.unlocked', async (on) => {
            if (on && this.state !== 'ready') await this._boot();
            if (! on) { this.state = 'locked'; this._revokeThumbCache(); }
        });
        this.$watch('_mut', () => this.renderSpark());
    },

    destroy() {
        this._revokeThumbCache();
    },

    async _boot() {
        // Multi-module dashboard: wait for the vault, then load every per-module
        // store the widgets read (each is its own sealed manifest). Gallery + files
        // are best-effort (their widgets degrade gracefully if unavailable).
        const vault = this.$store.vault;
        while (! vault.ready) { await new Promise((r) => setTimeout(r, 20)); }
        if (! vault.unlocked) { this.state = 'locked'; return; }

        await Promise.all(['notes', 'todos', 'contacts', 'passwords', 'health', 'bookmarks', 'invoices']
            .map((m) => (window.LLModuleStore[m].loaded ? null : window.LLModuleStore[m].load())));
        if (! window.LLFilesStore.loaded) await window.LLFilesStore.load();

        this.state = 'ready';
        try { this.galleryReady = await bootGalleryStore(this.$store); } catch (_e) { this.galleryReady = false; }
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

    // Per-module data getters (store v3 split — each module owns its sealed store).
    get _notes() { return window.LLModuleStore.notes?.data ?? null; },
    get _todos() { return window.LLModuleStore.todos?.data ?? null; },
    get _contacts() { return window.LLModuleStore.contacts?.data ?? null; },
    get _passwords() { return window.LLModuleStore.passwords?.data ?? null; },
    get _health() { return window.LLModuleStore.health?.data ?? null; },
    get _files() { return window.LLFilesStore?.data ?? null; },
    get _g() { return this.galleryReady ? (window.LLGalleryStore?.data ?? null) : null; },

    // --- Todos widget ---
    get todos() {
        void this._mut;
        return this._todos ? sortTodos(this._todos.todos ?? [], new Date().toISOString().slice(0, 10)).slice(0, 6) : [];
    },

    completeTodo(id) {
        const t = (this._todos?.todos ?? []).find((x) => x.id === id);
        if (t) { t.done = true; window.LLModuleStore.todos.touch(); this._mut++; }
    },

    // --- Counter tiles ---
    get counts() {
        void this._mut; // recompute after a manifest mutation (store .data is not Alpine-reactive)
        return {
            notes: (this._notes?.notes ?? []).filter((n) => ! n.trashed).length,
            passwords: (this._passwords?.secrets ?? []).length,
            contacts: (this._contacts?.contacts ?? []).length,
            bookmarks: (window.LLModuleStore.bookmarks?.data?.bookmarks ?? []).length,
            invoices: (window.LLModuleStore.invoices?.data?.invoices ?? []).length,
            files: (this._files?.files ?? []).filter((f) => ! f.trashed).length,
        };
    },

    // --- Recent notes ---
    get recentNotes() {
        void this._mut;
        return (this._notes?.notes ?? []).filter((n) => ! n.trashed)
            .slice().sort((a, b) => (b.updated ?? '').localeCompare(a.updated ?? '')).slice(0, 5);
    },

    // --- Birthdays widget ---
    get birthdays() {
        void this._mut;
        if (! this._contacts) return [];
        const contacts = this._contacts.contacts ?? [];
        const byId = new Map(contacts.map((c) => [c.id, c]));
        return upcomingBirthdays(contacts, new Date().toISOString().slice(0, 10), 30)
            // Resolve the real display name (form contacts have first/last, not displayName/fn).
            .map((b) => ({ ...b, name: contactDisplayName(byId.get(b.id) ?? {}) || b.name }));
    },

    // --- Health widget ---
    get healthProfile() { return this._health?.healthProfile ?? null; },

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
        (this._health.healthEntries ||= []).push({
            id: window.LLModuleStore.health.newId(),
            ts: new Date().toISOString(),
            metric: m,
            v: canon,
            v2,
            note: '',
        });
        window.LLModuleStore.health.touch();
        this._mut++;
        this.quickAdd.v = '';
        this.quickAdd.v2 = '';
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
        try { this.usage.files = await getJson('/files/usage'); } catch (_e) { /* widget shows — */ }
        try { this.usage.gallery = await getJson('/gallery/usage'); } catch (_e) { /* — */ }
    },

    // --- On This Day widget ---
    // Groups past-year photos whose month+day match today, sorted nearest first.
    get onThisDay() {
        return this._g ? yearsAgoPhotos(this._g.photos ?? [], new Date().toISOString().slice(0, 10)) : [];
    },

    // Decrypt and cache a photo thumbnail. Reuses the same decrypt path as gallery.js
    // (thumbLane + fetchDecryptWorker, photo.thumbRef + photo.thumbKey).
    // Capped at 12 total decrypts; returns '' when photo has no thumb or cap is reached.
    async thumbUrl(photo) {
        if (! photo?.thumbRef) return '';
        if (this._thumbCache[photo.id]) return this._thumbCache[photo.id];
        if (this._thumbPending[photo.id]) return this._thumbPending[photo.id];
        // Cap total: once 12 object URLs are cached, stop decrypting more.
        if (Object.keys(this._thumbCache).length >= 12) return '';
        const job = thumbLane(async () => {
            const bytes = await fetchDecryptWorker(config.rawBase, photo.thumbRef, photo.thumbKey);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this._thumbCache[photo.id] = url;
            return url;
        }).catch(() => '').finally(() => { delete this._thumbPending[photo.id]; });
        this._thumbPending[photo.id] = job;
        return job;
    },

    // --- Storage widget ---
    // Exposes the usage data loaded by _loadUsage() in a form suitable for
    // the storage bars. Returns { used, quota } for each store, or null while loading.
    // formatBytes is imported for use in Blade via a window-exposed helper.
    _fmtBytes(n) {
        return n == null ? '—' : formatBytes(n);
    },

    // --- Password Health widget ---
    // Cheap subset: reused passwords, expiring cards, logins without TOTP.
    // No HIBP, no zxcvbn.
    get pwHealth() {
        void this._mut;
        const secrets = this._passwords?.secrets ?? [];
        // Reused: count passwords appearing more than once across all items.
        const pw = {};
        for (const s of secrets) {
            const val = s.fields?.password;
            if (val) pw[val] = (pw[val] || 0) + 1;
        }
        const reused = Object.values(pw).filter((n) => n > 1).reduce((a, n) => a + n, 0);

        // Expiring/expired cards: expiry within 45 days or already past.
        const soon = Date.now() + 45 * 86400000;
        let cards = 0;
        for (const s of secrets) {
            if (s.type !== 'card') continue;
            const exp = this._cardExpiry(s);
            if (exp && exp.getTime() <= soon) cards++;
        }

        // Logins without a TOTP field.
        const no2fa = secrets.filter((s) => s.type === 'login' && ! s.fields?.totp).length;

        return { reused, cards, no2fa };
    },

    // Parse card expiry from fields.expiry (format "MM/YY" or "MM/YYYY").
    // Returns a Date for the first day after the expiry month, or null if unparseable.
    _cardExpiry(s) {
        const m = String(s.fields?.expiry ?? '').match(/(\d{1,2})\D+(\d{2,4})/);
        if (! m) return null;
        const mm = +m[1]; let yr = +m[2]; if (yr < 100) yr += 2000;
        if (mm < 1 || mm > 12) return null;
        return new Date(yr, mm, 1); // first day after the expiry month
    },
});
