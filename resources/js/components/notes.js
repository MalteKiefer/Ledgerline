// notes component. Extracted from app.js.
import { zkModule } from '../shared/zk-module';
import { loadMarkdown } from '../shared/markdown';
import { jsonHeaders } from '../shared/api';

// One-time dual-read migration from the old single-blob module store (/store/notes)
// to the new sharded store (LLNotesStore). Runs only while the sharded store is empty;
// after moving the notes it clears the old monolith so a later "delete all" can never
// re-import them. Best-effort — a failure just leaves the notes in the old store.
async function migrateFromMonolith(ms) {
    if ((ms.data.notes?.length ?? 0) > 0) return; // already sharded
    let d = null;
    try {
        d = await fetch('/store/notes', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
    } catch (e) { return; }
    if (! d || ! d.ciphertext) return;
    let old = null;
    try { old = window.Vault.openManifest(d.ciphertext); } catch (e) { return; }
    if (! Array.isArray(old.notes) || old.notes.length === 0) return;
    ms.data.notes.push(...old.notes);
    await ms.flush(); // persist into the sharded store first
    try {
        const empty = window.Vault.sealManifest({ v: 3, notes: [] });
        await fetch('/store/notes', { method: 'PUT', headers: jsonHeaders(), body: JSON.stringify({ ciphertext: empty, version: d.version ?? 0 }) });
    } catch (e) { /* the length guard still prevents re-import this session */ }
}

export default (labels = {}) => ({
    ...zkModule({ store: 'notes', instance: () => window.LLNotesStore, afterLoad: (self, ms) => migrateFromMonolith(ms), map: { notes: 'notes' }, onLock: (self) => { self.currentId = null; } }),
    notes: [],
    currentId: null,
    view: 'active', // active | trash
    previewHtml: '',
    previewTimer: null,

    async init() {
        await this._initZk();
        // Deep link (?note=<id>, e.g. from the dashboard "recent notes" widget) → open it.
        const nid = new URLSearchParams(location.search).get('note');
        if (nid) {
            const it = this.notes.find((n) => n.id === nid);
            if (it) { this.view = it.trashed ? 'trash' : 'active'; this.open(it); }
        }
    },

    get allTags() { return this._tagsOf(this.notes); },
    get trashCount() { return this._trashCount(this.notes); },
    get current() { return this.notes.find((n) => n.id === this.currentId) ?? null; },

    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.notes.filter((n) => this.view === 'trash' ? n.trashed : ! n.trashed);
        if (this.activeTag !== '') list = list.filter((n) => (n.tags ?? []).includes(this.activeTag));
        if (q !== '') {
            list = list.filter((n) => (n.title ?? '').toLowerCase().includes(q)
                || (n.content ?? '').toLowerCase().includes(q)
                || (n.tags ?? []).some((t) => t.toLowerCase().includes(q)));
        }
        return [...list].sort((a, b) => (Number(b.pinned) - Number(a.pinned)) || (b.updated ?? '').localeCompare(a.updated ?? ''));
    },

    excerpt(n) { return (n.content ?? '').replace(/[#*_`>\[\]()-]/g, '').replace(/\s+/g, ' ').trim().slice(0, 80); },

    async open(n) {
        this.currentId = n.id;
        this.tagsValue = (n.tags ?? []).join(', ');
        this.refreshPreview();
    },

    newNote() {
        const note = { id: window.LLNotesStore.newId(), title: '', content: '', tags: [], pinned: false, trashed: false, updated: new Date().toISOString() };
        this.notes.unshift(note);
        this._save();
        this.open(note);
    },

    schedulePreview() {
        clearTimeout(this.previewTimer);
        this.previewTimer = setTimeout(() => this.refreshPreview(), 250);
    },
    // Render the current note's markdown IN THE BROWSER (server never sees it).
    // The markdown stack is lazy-loaded on first preview (kept out of the
    // initial bundle); guard against a stale render if the note changed while
    // it loaded.
    async refreshPreview() {
        if (! this.current) { this.previewHtml = ''; return; }
        const id = this.currentId;
        const md = await loadMarkdown();
        if (this.currentId === id) this.previewHtml = md.render(this.current.content || '');
    },

    save() {
        const n = this.current;
        if (! n) return;
        n.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        n.updated = new Date().toISOString();
        this._save();
    },

    togglePin(n) { n.pinned = ! n.pinned; n.updated = new Date().toISOString(); this._save(); },
    trash(n) { n.trashed = new Date().toISOString(); if (this.currentId === n.id) this.currentId = null; this._save(); },
    restore(n) { n.trashed = false; this._save(); },
    async remove(n) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm)) return;
        const i = this.notes.findIndex((x) => x.id === n.id);
        if (i >= 0) this.notes.splice(i, 1);
        if (this.currentId === n.id) this.currentId = null;
        this._save();
    },
    emptyTrash() { return this._emptyTrashArr(this.notes, labels.emptyTrashConfirm); },
});
