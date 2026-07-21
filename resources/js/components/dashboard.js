// Dashboard component — reads the decrypted workspace manifest and gallery store
// to populate widgets (todos, counters, recent notes). Gallery is best-effort:
// the widget degrades gracefully if the gallery store is unavailable.
import { bootStore, bootGalleryStore } from '../shared/zk-module';
import { sortTodos } from '../shared/dashboard-utils';
import { getJson } from '../shared/api';

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | ready
    _mut: 0,
    galleryReady: false,
    usage: { files: null, gallery: null },

    async init() {
        await this._boot();
        this.$watch('$store.vault.unlocked', async (on) => {
            if (on && this.state !== 'ready') await this._boot();
            if (! on) this.state = 'locked';
        });
    },

    async _boot() {
        // Workspace store is required; gallery is best-effort (photo widget degrades).
        if (! await bootStore(this.$store)) { this.state = 'locked'; return; }
        this.state = 'ready';
        try { this.galleryReady = await bootGalleryStore(this.$store); } catch (_e) { this.galleryReady = false; }
        this._loadUsage();
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

    async _loadUsage() {
        try { this.usage.files = await getJson('/files/usage'); } catch (_e) { /* widget shows — */ }
        try { this.usage.gallery = await getJson('/gallery/usage'); } catch (_e) { /* — */ }
    },
});
