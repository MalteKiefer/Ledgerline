// Todos — plaintext-relational (pivot Phase 1). Inlined initial lists+tasks, JSON
// per-row endpoints (shared with the mobile API). The client keeps the old field
// surface (listId, due as datetime-local) and maps to server columns at the boundary.
import { getJson, apiRequest, postForm } from '../shared/api';
import { parseTags, addTags, removeTagFrom, popTag } from '../shared/tag-chips';

// ISO (server) → datetime-local input value "YYYY-MM-DDTHH:mm" (local wall time).
function toLocalInput(iso) {
    if (! iso) return '';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const normTask = (t) => ({
    id: t.id,
    listId: t.todo_list_id ?? null,
    title: t.title ?? '',
    description: t.description ?? '',
    url: t.url ?? '',
    priority: t.priority ?? 'normal',
    marked: !! t.marked,
    done: !! t.done,
    tags: Array.isArray(t.tags) ? t.tags : [],
    due: toLocalInput(t.due),
    recurrence: t.recurrence ?? 'none',
    version: t.version ?? 0,
    updated_at: t.updated_at ?? null,
});

export default (labels = {}, initialLists = [], initialTasks = []) => ({
    lists: [...(initialLists || [])].map((l) => ({ id: l.id, name: l.name })).sort((a, b) => (a.name || '').localeCompare(b.name || '')),
    tasks: (initialTasks || []).map(normTask),
    trash: [],
    trashLoaded: false,
    view: 'all', // all | marked | trash | <list id>
    newListName: '',
    editorOpen: false,
    editing: null,
    query: '',
    activeTag: '',
    tagsValue: '',
    tagDraft: '',
    tagList() { return parseTags(this.tagsValue); },
    commitTag() { this.tagsValue = addTags(this.tagsValue, this.tagDraft); this.tagDraft = ''; },
    onTagInput() { if ((this.tagDraft || '').includes(',')) this.commitTag(); },
    tagBackspace() { if ((this.tagDraft || '') === '') this.tagsValue = popTag(this.tagsValue); },
    removeTag(tag) { this.tagsValue = removeTagFrom(this.tagsValue, tag); },

    init() {
        // Load trash lazily the first time the trash view opens.
        this.$watch('view', (v) => { if (v === 'trash') this.setTrashView(); });
    },

    listName(id) { return (this.lists.find((l) => l.id === id) || {}).name || ''; },

    async addList() {
        const name = this.newListName.trim();
        if (! name) return;
        try {
            const d = await postForm('/todo-lists', { name });
            this.lists.push({ id: d.list.id, name: d.list.name });
            this.lists.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            this.newListName = '';
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async renameList(l) {
        const name = (prompt(labels.renameList, l.name) || '').trim();
        if (! name || name === l.name) return;
        try {
            await apiRequest('PUT', '/todo-lists/' + l.id, { name });
            l.name = name;
            this.lists.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async deleteList(l) {
        if (! await this.$store.confirm.ask(labels.deleteListConfirm)) return;
        try {
            await apiRequest('DELETE', '/todo-lists/' + l.id);
            for (const t of this.tasks) if (t.listId === l.id) t.listId = null;
            const i = this.lists.findIndex((x) => x.id === l.id);
            if (i >= 0) this.lists.splice(i, 1);
            if (this.view === l.id) this.view = 'all';
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },

    get allTags() {
        const s = new Set();
        for (const t of this.tasks) for (const g of (t.tags || [])) s.add(g);
        return [...s].sort((a, b) => a.localeCompare(b));
    },
    get trashCount() { return this.trash.length; },

    get filteredTasks() {
        const q = this.query.trim().toLowerCase();
        let list = this.view === 'trash' ? this.trash : this.tasks;
        if (this.view === 'marked') list = list.filter((t) => t.marked);
        else if (this.view !== 'all' && this.view !== 'trash') list = list.filter((t) => t.listId === this.view);
        if (this.activeTag !== '') list = list.filter((t) => (t.tags ?? []).includes(this.activeTag));
        if (q !== '') {
            list = list.filter((t) => (t.title ?? '').toLowerCase().includes(q)
                || (t.description ?? '').toLowerCase().includes(q)
                || (t.tags ?? []).some((g) => g.toLowerCase().includes(q)));
        }
        const prio = { high: 0, normal: 1, low: 2 };
        return [...list].sort((a, b) =>
            (Number(a.done) - Number(b.done))
            || (Number(b.marked) - Number(a.marked))
            || ((prio[a.priority] ?? 1) - (prio[b.priority] ?? 1))
            || ((a.due ?? '￿').localeCompare(b.due ?? '￿')));
    },

    async setTrashView() {
        if (this.trashLoaded) return;
        try { const d = await getJson('/todos/trash'); this.trash = (d.todos || []).map(normTask); this.trashLoaded = true; } catch (e) { /* keep */ }
    },

    newTask() {
        const listId = (this.view !== 'all' && this.view !== 'marked' && this.view !== 'trash') ? this.view : null;
        this.editing = { id: null, listId, title: '', description: '', url: '', priority: 'normal', marked: false, tags: [], due: '', recurrence: 'none', done: false };
        this.tagsValue = '';
        this.editorOpen = true;
    },
    editTask(t) {
        this.editing = { ...t, tags: [...(t.tags ?? [])] };
        this.tagsValue = (this.editing.tags || []).join(', ');
        this.editorOpen = true;
    },
    closeEditor() { this.editorOpen = false; this.editing = null; },

    _toPayload(e) {
        let url = (e.url || '').trim();
        if (url && ! /^https?:\/\//i.test(url)) url = '';
        return {
            todo_list_id: e.listId ?? null,
            title: (e.title || '').trim(),
            description: e.description || '',
            url,
            priority: e.priority || 'normal',
            marked: !! e.marked,
            done: !! e.done,
            tags: e.tags,
            due: e.due || null,
            recurrence: e.recurrence && e.recurrence !== 'none' ? e.recurrence : 'none',
        };
    },
    async saveTask() {
        const e = this.editing;
        if (! e || ! (e.title || '').trim()) return;
        e.tags = this.tagsValue.split(',').map((s) => s.trim()).filter(Boolean);
        try {
            if (e.id) {
                const d = await apiRequest('PUT', '/todos/' + e.id, { ...this._toPayload(e), version: e.version });
                const t = this.tasks.find((x) => x.id === e.id);
                if (t) Object.assign(t, normTask(d.todo));
            } else {
                const d = await postForm('/todos', this._toPayload(e));
                this.tasks.unshift(normTask(d.todo));
            }
            this.closeEditor();
        } catch (e2) { window.llToast?.(labels.saveFailed); }
    },

    async _toggle(t, field) {
        const value = ! t[field];
        t[field] = value;
        try { await postForm('/todos/' + t.id + '/toggle', { field, value }); }
        catch (e) { t[field] = ! value; window.llToast?.(labels.saveFailed); }
    },
    toggleDone(t) { return this._toggle(t, 'done'); },
    toggleMark(t) { return this._toggle(t, 'marked'); },

    async trashTask(t) {
        try {
            await apiRequest('DELETE', '/todos/' + t.id);
            const i = this.tasks.findIndex((x) => x.id === t.id);
            if (i >= 0) this.tasks.splice(i, 1);
            if (this.trashLoaded) this.trash.unshift(t);
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async restoreTask(t) {
        try {
            const d = await postForm('/todos/' + t.id + '/restore', {});
            const i = this.trash.findIndex((x) => x.id === t.id);
            if (i >= 0) this.trash.splice(i, 1);
            this.tasks.unshift(normTask(d.todo));
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async deleteForever(t) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm)) return;
        try {
            await apiRequest('DELETE', '/todos/' + t.id + '/force');
            const i = this.trash.findIndex((x) => x.id === t.id);
            if (i >= 0) this.trash.splice(i, 1);
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async emptyTrash() {
        if (! this.trash.length || ! await this.$store.confirm.ask(labels.emptyTrashConfirm)) return;
        try { await postForm('/todos/trash/empty', {}); this.trash = []; }
        catch (e) { window.llToast?.(labels.saveFailed); }
    },

    isRecurring(t) { return !! t.recurrence && t.recurrence !== 'none'; },
    priorityClass(p) { return p === 'high' ? 'bg-red-500' : (p === 'low' ? 'bg-gray-300' : 'bg-amber-400'); },
    dueLabel(t) { if (! t.due) return ''; try { return new Date(t.due).toLocaleString(); } catch (e) { return t.due; } },
    isOverdue(t) { return t.due && ! t.done && new Date(t.due).getTime() < Date.now(); },
});
