// Notes — plaintext-relational (pivot Phase 1). No vault, no sealed store: the page
// inlines the initial rows (fast first paint) and every mutation is a small JSON
// request to the per-row endpoints (shared with the mobile API). Markdown preview is
// still rendered client-side (cosmetic; the body is plaintext on the server).
import { loadMarkdown } from '../shared/markdown';
import { getJson, apiRequest, postForm } from '../shared/api';
import { parseTags, addTags, removeTagFrom, popTag } from '../shared/tag-chips';

const norm = (n) => ({
    id: n.id,
    title: n.title ?? '',
    body: n.body ?? '',
    tags: Array.isArray(n.tags) ? n.tags : [],
    pinned: !! n.pinned,
    version: n.version ?? 0,
    updated_at: n.updated_at ?? null,
});

export default (labels = {}, initial = []) => ({
    notes: (initial || []).map(norm),
    trash: [],
    trashLoaded: false,
    currentId: null,
    view: 'active', // active | trash
    query: '',
    activeTag: '',
    previewHtml: '',
    previewTimer: null,
    // Tag chips (x-tag-field contract) over `tagsValue`.
    tagsValue: '',
    tagDraft: '',
    tagList() { return parseTags(this.tagsValue); },
    commitTag() { this.tagsValue = addTags(this.tagsValue, this.tagDraft); this.tagDraft = ''; },
    onTagInput() { if ((this.tagDraft || '').includes(',')) this.commitTag(); },
    tagBackspace() { if ((this.tagDraft || '') === '') this.tagsValue = popTag(this.tagsValue); },
    removeTag(tag) { this.tagsValue = removeTagFrom(this.tagsValue, tag); },

    init() {
        const nid = new URLSearchParams(location.search).get('note');
        if (nid) { const it = this.notes.find((n) => String(n.id) === String(nid)); if (it) this.open(it); }
    },

    get list() { return this.view === 'trash' ? this.trash : this.notes; },
    get allTags() {
        const s = new Set();
        for (const n of this.notes) for (const t of (n.tags || [])) s.add(t);
        return [...s].sort((a, b) => a.localeCompare(b));
    },
    get trashCount() { return this.trash.length; },
    get current() { return this.list.find((n) => n.id === this.currentId) ?? null; },

    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.list;
        if (this.activeTag !== '') list = list.filter((n) => (n.tags ?? []).includes(this.activeTag));
        if (q !== '') {
            list = list.filter((n) => (n.title ?? '').toLowerCase().includes(q)
                || (n.body ?? '').toLowerCase().includes(q)
                || (n.tags ?? []).some((t) => t.toLowerCase().includes(q)));
        }
        return [...list].sort((a, b) => (Number(b.pinned) - Number(a.pinned)) || String(b.updated_at ?? '').localeCompare(String(a.updated_at ?? '')));
    },

    excerpt(n) { return (n.body ?? '').replace(/[#*_`>\[\]()-]/g, '').replace(/\s+/g, ' ').trim().slice(0, 80); },

    async setView(v) {
        this.view = v;
        this.currentId = null;
        if (v === 'trash' && ! this.trashLoaded) {
            try { const d = await getJson('/notes/trash'); this.trash = (d.notes || []).map(norm); this.trashLoaded = true; } catch (e) { /* keep */ }
        }
    },

    open(n) {
        this.currentId = n.id;
        this.tagsValue = (n.tags ?? []).join(', ');
        this.refreshPreview();
    },
    closeCurrent() { this.currentId = null; },

    async newNote() {
        try {
            const d = await postForm('/notes', { title: '', body: '' });
            const note = norm(d.note);
            this.notes.unshift(note);
            this.open(note);
        } catch (e) { window.llToast?.(labels.saveFailed || 'Save failed.'); }
    },

    schedulePreview() { clearTimeout(this.previewTimer); this.previewTimer = setTimeout(() => this.refreshPreview(), 250); },
    async refreshPreview() {
        if (! this.current) { this.previewHtml = ''; return; }
        const id = this.currentId;
        const md = await loadMarkdown();
        if (this.currentId === id) this.previewHtml = md.render(this.current.body || '');
    },

    _saveTimer: null,
    save() {
        const n = this.current;
        if (! n) return;
        n.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        clearTimeout(this._saveTimer);
        const id = n.id;
        this._saveTimer = setTimeout(() => this._persist(id), 600);
    },
    async _persist(id) {
        const n = this.notes.find((x) => x.id === id);
        if (! n) return;
        try {
            const d = await apiRequest('PUT', '/notes/' + id, { title: n.title, body: n.body, tags: n.tags, pinned: n.pinned, version: n.version });
            if (d && d.note) { n.version = d.note.version; n.updated_at = d.note.updated_at; }
        } catch (e) { window.llToast?.(labels.saveFailed || 'Save failed.'); }
    },

    togglePin(n) { n.pinned = ! n.pinned; this._persist(n.id); },

    async trashNote(n) {
        try {
            await apiRequest('DELETE', '/notes/' + n.id);
            const i = this.notes.findIndex((x) => x.id === n.id);
            if (i >= 0) this.notes.splice(i, 1);
            if (this.trashLoaded) this.trash.unshift(n); else this.trash.push(n);
            if (this.currentId === n.id) this.currentId = null;
        } catch (e) { window.llToast?.(labels.saveFailed || 'Save failed.'); }
    },
    async restore(n) {
        try {
            const d = await postForm('/notes/' + n.id + '/restore', {});
            const i = this.trash.findIndex((x) => x.id === n.id);
            if (i >= 0) this.trash.splice(i, 1);
            this.notes.unshift(norm(d.note));
            if (this.currentId === n.id) this.currentId = null;
        } catch (e) { window.llToast?.(labels.saveFailed || 'Save failed.'); }
    },
    async remove(n) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm)) return;
        try {
            await apiRequest('DELETE', '/notes/' + n.id + '/force');
            const i = this.trash.findIndex((x) => x.id === n.id);
            if (i >= 0) this.trash.splice(i, 1);
            if (this.currentId === n.id) this.currentId = null;
        } catch (e) { window.llToast?.(labels.saveFailed || 'Save failed.'); }
    },
    async emptyTrash() {
        if (! this.trash.length || ! await this.$store.confirm.ask(labels.emptyTrashConfirm)) return;
        try { await postForm('/notes/trash/empty', {}); this.trash = []; this.currentId = null; }
        catch (e) { window.llToast?.(labels.saveFailed || 'Save failed.'); }
    },
});
