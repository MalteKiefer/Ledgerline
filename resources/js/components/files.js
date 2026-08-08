// Files module — plaintext-relational (pivot). No vault, no sealed store, no
// per-blob crypto: the browser talks to the per-row REST endpoints (shared with
// the mobile API) and the file bytes are plaintext, so a raw URL is directly
// usable in <img>/<iframe>/<video> and downloads. Includes personal files plus
// cross-user shared folders + public share links (restored v1.548.0).
import { getJson, apiRequest, postForm, jsonHeaders, csrfToken } from '../shared/api';
import { parseTags, addTags, removeTagFrom, popTag } from '../shared/tag-chips';
import { formatDate } from '../shared/dom';
import { fileCategory, CATEGORY_ICON, categoryTint, fileTypeLabel, FOLDER_TINT, formatBytes } from '../shared/file-categories';

// Heroicon path for the folder chip glyph (24-outline folder).
const FOLDER_ICON_PATH = 'M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z';

// Threshold above which an upload streams as chunks instead of one request.
const CHUNK_THRESHOLD = 8 * 1024 * 1024;

// Server row → client shape. The client keeps camelCase field names the browser
// UI already used (folder / parent / created / trashed); mapped to the DB
// columns (file_folder_id / parent_id / created_at / deleted_at) at the boundary.
const normFolder = (f) => ({
    id: f.id,
    name: f.name ?? '',
    parent: f.parent_id ?? null,
    version: f.version ?? 0,
    updated: f.updated_at ?? null,
});
const normFile = (f) => ({
    id: f.id,
    name: f.name ?? '',
    mime: f.mime ?? 'application/octet-stream',
    size: f.size ?? 0,
    folder: f.file_folder_id ?? null,
    tags: Array.isArray(f.tags) ? f.tags : [],
    note: f.note ?? '',
    favorite: !! f.favorite,
    labelIds: Array.isArray(f.labels) ? f.labels.map((l) => l.id) : [],
    version: f.version ?? 0,
    created: f.created_at ?? null,
    updated: f.updated_at ?? null,
    trashed: f.deleted_at ?? null,
});

const normLabel = (l) => ({ id: l.id, name: l.name ?? '', color: l.color ?? '#6b7280' });

// Server share row → client shape (never carries the password hash).
const normShare = (s) => ({
    id: s.id,
    token: s.token,
    kind: s.kind,
    allowDownload: !! s.allow_download,
    needsPassword: !! s.needs_password,
    expiresAt: s.expires_at ?? null,
    version: s.version ?? 0,
});

export default (config = {}, labels = {}, initial = {}) => ({
    folders: (initial.folders || []).map(normFolder),
    files: (initial.files || []).map(normFile),
    trash: [],           // trashed files, loaded on demand
    trashLoaded: false,
    usage: initial.usage || { used: 0, quota: 0 },
    maxVersions: initial.maxVersions || 10,

    // Labels (coloured taxonomy) + server content-search state (stage 2).
    fileLabels: (initial.labels || []).map(normLabel),
    activeLabel: null,       // filter files by a label id (null = off)
    contentHits: null,       // Set<id> of server content-search matches (null = no active content search)
    contentSearching: false,
    labelModal: false,       // label manager open
    labelDraft: { id: null, name: '', color: '#6b7280' },

    cwd: null,
    query: '',
    _q: '',            // debounced search term the row filter actually uses
    _searchTimer: null,
    sortDir: 'asc',
    sortKey: 'name', // name | size | date
    layout: (typeof localStorage !== 'undefined' && localStorage.getItem('ll-files-layout')) || 'list', // list | grid
    renaming: null,   // item id currently renamed inline
    renameValue: '',
    moveRefs: [],     // [{kind, id}] for the move modal
    moveTarget: '',
    moveOpen: false,
    deleteRefs: [],   // [{kind, id, name}]
    deleteOpen: false,
    selected: [],     // ['kind:id', …]
    tagsRef: null,    // {kind, id} being tagged
    tagsOpen: false,
    tagsValue: '',
    // Badge-chip tag editing (x-tag-field contract) over `tagsValue`.
    tagDraft: '',
    tagList() { return parseTags(this.tagsValue); }, // method (see zk-module tagList note)
    commitTag() { this.tagsValue = addTags(this.tagsValue, this.tagDraft); this.tagDraft = ''; },
    onTagInput() { if ((this.tagDraft || '').includes(',')) this.commitTag(); },
    tagBackspace() { if ((this.tagDraft || '') === '') this.tagsValue = popTag(this.tagsValue); },
    removeTag(tag) { this.tagsValue = removeTagFrom(this.tagsValue, tag); },
    activeTag: '',
    view: 'files', // files | favorites | recent | trash
    newFolderName: '',
    newFolderModal: false,
    infoOpen: false,
    infoRow: null,
    infoNote: '',
    migrateOpen: false,
    migrateRow: null,
    migrateDelete: true,
    migrateBusy: false,
    dragItem: null, // {kind, id} being dragged into a folder
    uploads: [], // per-file upload tray: [{ name, state, progress, error }]
    uploadBatches: 0, // concurrent uploadItems() runs still in flight
    busy: 0, // in-flight file operations; drives the spinner badge
    error: '',
    dragging: false,
    viewer: { open: false, kind: 'none', src: '', row: null, saving: false, saved: false },
    versions: { open: false, row: null, list: [], loading: false },
    // Public share link dialog. There is no share list endpoint (like the gallery),
    // so a link created this session is cached per row-key for update/revoke.
    share: { open: false, kind: null, id: null, name: '', busy: false, password: '', expiresAt: '', allowDownload: true, link: '', error: '', current: null },
    _shareCache: {}, // 'file:12' | 'folder:3' -> normShare
    editorText: '', // plain-text editor buffer (syntax highlighting is a later phase)

    // CSRF token for the multipart XHR uploads (JSON requests carry it via jsonHeaders()).
    _token() { return config.token || csrfToken(); },

    // Track an async file operation so the UI can show a "working" spinner badge.
    _track(p) {
        this.busy++;
        return Promise.resolve(p).finally(() => { this.busy = Math.max(0, this.busy - 1); });
    },

    async init() {
        window.addEventListener('paperless-sent', (e) => this.onPaperlessSent(e.detail));
        this.initDropzone();
        // Switching view clears any selection; opening the trash lazily loads it.
        this.$watch('view', (v) => { this.selected = []; if (v === 'trash') this._loadTrash(); });
        await this._track(this.load());
    },

    initDropzone() {
        let depth = 0;
        window.addEventListener('dragenter', (e) => {
            if (e.dataTransfer?.types?.includes('Files')) { depth++; this.dragging = true; }
        });
        window.addEventListener('dragleave', () => { depth = Math.max(0, depth - 1); if (! depth) this.dragging = false; });
        window.addEventListener('drop', () => { depth = 0; this.dragging = false; });
    },

    // Load the folder tree + active files + usage snapshot.
    async load() {
        try {
            const d = await getJson('/files/entries');
            this.folders = (d.folders || []).map(normFolder);
            this.files = (d.files || []).map(normFile);
            if (d.labels) this.fileLabels = d.labels.map(normLabel);
            if (d.usage) this.usage = d.usage;
        } catch (e) { /* keep the inlined initial data */ }
    },

    async _loadTrash() {
        if (this.trashLoaded) return;
        try { const d = await getJson('/files/trash'); this.trash = (d.files || []).map(normFile); this.trashLoaded = true; } catch (e) { /* keep */ }
    },

    // Refresh only the usage figure after a byte-changing mutation.
    async refreshUsage() {
        try { const d = await getJson('/files/entries'); if (d.usage) this.usage = d.usage; } catch (e) { /* keep last value */ }
    },

    _removeFile(id) { const i = this.files.findIndex((x) => x.id === id); if (i >= 0) this.files.splice(i, 1); },

    // Save a file row's fields with optimistic concurrency: on 409 the server
    // returns the current version, we re-stamp and retry (our field change lands
    // on the latest version rather than clobbering or being lost).
    async _saveFile(f, patch) {
        for (let i = 0; i < 4; i++) {
            let res;
            try { res = await fetch('/files/entries/' + f.id, { method: 'PUT', headers: jsonHeaders(), body: JSON.stringify({ ...patch, version: f.version }) }); }
            catch (e) { break; }
            if (res.ok) { const d = await res.json().catch(() => ({})); if (d.file) Object.assign(f, normFile(d.file)); return true; }
            if (res.status === 409) { const d = await res.json().catch(() => ({})); if (typeof d.version === 'number') { f.version = d.version; continue; } }
            break;
        }
        window.llToast?.(labels.saveFailed);
        return false;
    },

    /* ---- Derived views ---- */

    get breadcrumb() {
        const chain = [];
        let cur = this.cwd;
        const byId = new Map(this.folders.map((f) => [f.id, f]));
        while (cur != null && byId.has(cur)) {
            chain.unshift(byId.get(cur));
            cur = byId.get(cur).parent;
        }
        return chain;
    },

    get currentFolderName() {
        return this.breadcrumb.length ? this.breadcrumb[this.breadcrumb.length - 1].name : null;
    },

    get trashView() { return this.view === 'trash'; },
    get trashCount() { return this.trash.length; },
    get favCount() { return this.files.filter((f) => f.favorite && ! f.trashed).length; },

    get rows() {
        const q = (this._q || '').trim(); // debounced + lowercased search term
        const tag = this.activeTag;
        const factor = this.sortDir === 'desc' ? -1 : 1;
        const byName = (a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base', numeric: true });
        const base = this.sortKey === 'size' ? ((a, b) => (a.size || 0) - (b.size || 0))
            : this.sortKey === 'date' ? ((a, b) => new Date(a.created || 0) - new Date(b.created || 0))
                : byName;
        const cmp = (a, b) => factor * (base(a, b) || byName(a, b));
        // A file matches the query by name/tag OR (for content search) if the server
        // full-text search returned its id. A label filter narrows to that label.
        const hit = (x) => x.name.toLowerCase().includes(q) || (x.tags ?? []).some((t) => t.toLowerCase().includes(q)) || (this.contentHits ? this.contentHits.has(x.id) : false);
        const labelOk = (x) => this.activeLabel == null || (x.labelIds || []).includes(this.activeLabel);
        const search = (list) => (q === '' ? list : list.filter(hit)).filter(labelOk);

        if (this.view === 'trash') {
            return search(this.trash).map((f) => ({ ...f, kind: 'file' })).sort(cmp);
        }
        if (this.view === 'favorites') {
            return search(this.files.filter((f) => f.favorite && ! f.trashed)).map((f) => ({ ...f, kind: 'file' })).sort(cmp);
        }
        if (this.view === 'recent') {
            return search(this.files.filter((f) => ! f.trashed))
                .map((f) => ({ ...f, kind: 'file' }))
                .sort((a, b) => new Date(b.updated || b.created || 0) - new Date(a.updated || a.created || 0)).slice(0, 100);
        }

        // A text search or an active tag switches from folder browsing to a flat,
        // tree-wide result set; otherwise scope to the current folder.
        const inScope = (list) => {
            // A query, a tag, a label filter or an active content search all flatten
            // the view (tree-wide) instead of scoping to the current folder.
            const flat = q !== '' || tag !== '' || this.activeLabel != null;
            let scoped = flat ? list : list.filter((x) => (x.parent ?? x.folder ?? null) === this.cwd);
            if (q !== '') scoped = scoped.filter((x) => x.name.toLowerCase().includes(q) || (x.tags ?? []).some((t) => t.toLowerCase().includes(q)) || (this.contentHits ? this.contentHits.has(x.id) : false));
            if (tag !== '') scoped = scoped.filter((x) => (x.tags ?? []).includes(tag));
            return scoped;
        };

        // Folders carry no labels; when a label filter is active, show only matching files.
        const folders = this.activeLabel != null ? [] : inScope(this.folders.map((f) => ({ ...f, kind: 'folder' })));
        const labelOkF = (x) => this.activeLabel == null || (x.labelIds || []).includes(this.activeLabel);
        const files = inScope(this.files.filter((f) => ! f.trashed).map((f) => ({ ...f, kind: 'file' }))).filter(labelOkF);
        return [...folders.sort(cmp), ...files.sort(cmp)];
    },

    // Every tag used anywhere, for suggestions.
    get allTags() {
        const set = new Set();
        for (const x of [...this.folders, ...this.files]) for (const t of x.tags ?? []) set.add(t);
        return [...set].sort((a, b) => a.localeCompare(b));
    },

    // Rich category (uses the filename extension + MIME) for a row.
    // ---- ZIP download + storage stats (stage 5) ----
    stats: { open: false, used: 0, byType: {}, duplicates: [] },
    async _zip(body) {
        try {
            const res = await fetch('/files/zip', { method: 'POST', headers: { Accept: 'application/zip', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this._token(), 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            if (! res.ok) { window.llToast?.(labels.saveFailed); return; }
            const blob = await res.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'files-' + Date.now() + '.zip';
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 4000);
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    downloadSelectionZip() {
        const ids = this.selectionRefs.filter((r) => r.kind === 'file').map((r) => r.id);
        if (! ids.length) return;
        this._zip({ ids });
    },
    downloadFolderZip() { if (this.cwd != null) this._zip({ folder_id: this.cwd }); },
    async openStats() {
        this.stats.open = true;
        try { const d = await getJson('/files/stats'); this.stats.used = d.used || 0; this.stats.byType = d.by_type || {}; this.stats.duplicates = d.duplicates || []; }
        catch (e) { this.stats.byType = {}; this.stats.duplicates = []; }
    },
    get statsRows() { return Object.entries(this.stats.byType || {}).map(([k, v]) => ({ type: k, size: v })).sort((a, b) => b.size - a.size); },

    // ---- Folder (directory) upload: recreate the tree, then upload each file ----
    async uploadDirectory(fileList) {
        const files = [...(fileList || [])];
        if (! files.length) return;
        const folderCache = {}; // relative dir path -> folder id
        const ensureFolder = async (parts) => {
            let parentId = this.cwd;
            let path = '';
            for (const part of parts) {
                path = path ? path + '/' + part : part;
                if (folderCache[path] != null) { parentId = folderCache[path]; continue; }
                try {
                    const d = await postForm('/files/folders', { name: part, parent_id: parentId });
                    const id = d.folder?.id;
                    if (id != null) { this.folders.push(normFolder(d.folder)); folderCache[path] = id; parentId = id; }
                } catch (e) { /* keep going */ }
            }
            return parentId;
        };
        for (const file of files) {
            const rel = (file.webkitRelativePath || file.name).split('/');
            const dirs = rel.slice(0, -1).slice(1); // drop the top-level chosen dir name
            const folderId = dirs.length ? await ensureFolder(dirs) : this.cwd;
            await this._uploadOne(file, folderId);
        }
        await this.load();
    },
    async _uploadOne(file, folderId) {
        try {
            const fd = new FormData();
            fd.append('file', file);
            if (folderId != null) fd.append('file_folder_id', folderId);
            const res = await fetch('/files/entries', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this._token() }, body: fd });
            if (res.status === 413) window.llToast?.(labels.quotaExceeded || labels.saveFailed);
        } catch (e) { /* skip */ }
    },

    isImageRow(row) { return row?.kind === 'file' && /^image\//.test(row?.mime || ''); },
    thumbUrl(row) { return '/files/entries/' + row.id + '/thumb'; },
    fileCat(row) { return fileCategory(row?.name, row?.mime); },
    typeLabel(file) { return labels.types?.[this.fileCat(file)] ?? file?.mime ?? ''; },
    // Category key → human label for the storage-stats "by type" list. Exposed as
    // a method because the closure `labels` isn't reachable from Blade expressions.
    typeName(cat) { return labels.types?.[cat] || cat; },
    fileIconPath(row) { return CATEGORY_ICON[this.fileCat(row)] ?? CATEGORY_ICON.OTHER; },
    rowTint(row) { return row.kind === 'folder' ? FOLDER_TINT : categoryTint(row.name, row.mime || ''); },
    rowIconPath(row) { return row.kind === 'folder' ? FOLDER_ICON_PATH : (CATEGORY_ICON[fileCategory(row.name, row.mime || '')] || CATEGORY_ICON.OTHER); },
    rowLabel(row) {
        if (row.kind === 'folder') return labels.folderLabel || 'Folder';
        const tok = fileTypeLabel(row.name, row.mime || '').replace('filetype.', '');
        return (labels.filetypeLabels && labels.filetypeLabels[tok]) || (labels.filetypeLabels && labels.filetypeLabels.other) || '';
    },
    fmtSize: formatBytes,
    fmtDate: formatDate,

    /* ---- Information ---- */

    openInfo(row) {
        this.infoRow = row;
        this.infoNote = row.note || '';
        this.infoOpen = true;
    },

    saveNote() {
        const row = this.infoRow;
        if (! row || row.kind !== 'file') return;
        const note = this.infoNote.trim();
        if ((row.note || '') === note) return;
        const f = this.files.find((x) => x.id === row.id);
        if (! f) return;
        f.note = note; row.note = note;
        this._saveFile(f, { note });
    },

    // Direct children of a folder (files + subfolders).
    folderItemCount(row) {
        if (! row) return 0;
        const files = this.files.filter((f) => (f.folder ?? null) === row.id && ! f.trashed).length;
        const folders = this.folders.filter((f) => (f.parent ?? null) === row.id).length;
        return files + folders;
    },

    /* ---- Migrate a Markdown file into a note ---- */

    isMarkdown(row) {
        if (! row || row.kind !== 'file') return false;
        return /\.(md|markdown)$/i.test(row.name || '') || (row.mime || '').includes('markdown');
    },
    openMigrate(row) {
        this.migrateRow = row;
        this.migrateDelete = true;
        this.migrateOpen = true;
    },
    async migrateAddNote(note) {
        try { await postForm('/notes', { title: note.title || '', body: note.content || '', tags: [] }); return true; }
        catch (e) { return false; }
    },
    // Read the file's plaintext bytes via its raw URL, create a note, then
    // optionally delete the source file.
    async applyMigrate() {
        const row = this.migrateRow;
        const del = this.migrateDelete;
        if (! row || this.migrateBusy) return;
        this.migrateBusy = true;
        this.error = '';
        try {
            const res = await fetch(this.rawUrl(row), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) throw new Error('read');
            const text = await res.text();
            const ok = await this.migrateAddNote({ title: (row.name || '').replace(/\.(md|markdown)$/i, ''), content: text });
            if (! ok) { this.error = labels.migrateFailed; return; }
            if (del) await this._forceDelete({ id: row.id });
            this.migrateOpen = false;
        } catch (e) {
            this.error = labels.migrateFailed;
        } finally {
            this.migrateBusy = false;
        }
    },

    // Human-readable path of the folder an item lives in ("All files / A / B").
    infoFolderPath(row) {
        const root = labels.rootFolder ?? '';
        if (! row) return root;
        const parentId = row.kind === 'folder' ? (row.parent ?? null) : (row.folder ?? null);
        if (parentId == null) return root;
        const byId = new Map(this.folders.map((f) => [f.id, f]));
        const chain = [];
        let cur = parentId;
        while (cur != null && byId.has(cur)) { chain.unshift(byId.get(cur).name); cur = byId.get(cur).parent; }
        return [root, ...chain].join(' / ');
    },

    /* ---- New folder modal ---- */

    openNewFolder() {
        this.newFolderName = '';
        this.newFolderModal = true;
        this.$nextTick(() => { this.$refs.newFolderInput?.focus(); });
    },
    async submitNewFolder() {
        const n = this.newFolderName.trim();
        if (! n) return;
        try {
            await this.mkdir(n);
            this.newFolderModal = false;
            this.newFolderName = '';
        } catch (e) {
            this.error = (e && e.message) ? e.message : String(e);
        }
    },

    /* ---- Structure operations ---- */

    async mkdir(name) {
        name = (name || '').trim();
        if (! name) return;
        const d = await postForm('/files/folders', { name, parent_id: this.cwd });
        this.folders.push(normFolder(d.folder));
    },

    startRename(row) {
        this.renaming = row.id;
        this.renameValue = row.name;
        this.$nextTick(() => this.$refs['rename']?.focus());
    },
    async applyRename(row) {
        const name = this.renameValue.trim();
        this.renaming = null;
        if (! name || name === row.name) return;
        if (row.kind === 'folder') {
            const f = this.folders.find((x) => x.id === row.id);
            if (! f) return;
            try { const d = await apiRequest('PUT', '/files/folders/' + row.id, { name }); if (d.folder) Object.assign(f, normFolder(d.folder)); }
            catch (e) { window.llToast?.(labels.saveFailed); }
        } else {
            const f = this.files.find((x) => x.id === row.id);
            if (f) await this._saveFile(f, { name });
        }
    },

    /* ---- Selection ---- */

    rowKey: (row) => `${row.kind}:${row.id}`,

    toggleAll(event) {
        this.selected = event.target.checked ? this.rows.map(this.rowKey) : [];
    },

    get selectionRefs() {
        return this.selected.map((key) => {
            const [kind, idStr] = key.split(':');
            const id = Number(idStr);
            const list = kind === 'folder' ? this.folders : this.files;
            const item = list.find((x) => x.id === id);
            return item ? { kind, id, name: item.name } : null;
        }).filter(Boolean);
    },

    // Expand a folder id to its whole subtree of folder ids.
    subtree(id) {
        const set = new Set([id]);
        let grew = true;
        while (grew) {
            grew = false;
            for (const f of this.folders) {
                if (f.parent != null && set.has(f.parent) && ! set.has(f.id)) { set.add(f.id); grew = true; }
            }
        }
        return set;
    },

    // All descendant folder ids of the given folders (inclusive of the roots).
    _folderClosure(folderIds) {
        const kill = new Set(folderIds);
        for (let grew = true; grew;) {
            grew = false;
            for (const f of this.folders) {
                if (! kill.has(f.id) && f.parent != null && kill.has(f.parent)) { kill.add(f.id); grew = true; }
            }
        }
        return kill;
    },

    openMove(row) {
        this.moveRefs = row ? [{ kind: row.kind, id: row.id }] : this.selectionRefs;
        this.moveTarget = '';
        this.moveOpen = this.moveRefs.length > 0;
    },

    // Folders eligible as a move target (never a selected folder's own subtree).
    get moveOptions() {
        const excluded = new Set();
        for (const ref of this.moveRefs) {
            if (ref.kind === 'folder') for (const id of this.subtree(ref.id)) excluded.add(id);
        }
        const byId = new Map(this.folders.map((x) => [x.id, x]));
        const path = (f) => {
            const parts = [f.name];
            let cur = f.parent;
            while (cur != null && byId.has(cur)) { parts.unshift(byId.get(cur).name); cur = byId.get(cur).parent; }
            return parts.join(' / ');
        };
        return this.folders
            .filter((f) => ! excluded.has(f.id))
            .map((f) => ({ id: f.id, label: path(f) }))
            .sort((a, b) => a.label.localeCompare(b.label));
    },

    async applyMove() {
        const refs = this.moveRefs;
        this.moveOpen = false;
        this.moveRefs = [];
        if (! refs.length) return;
        const target = this.moveTarget === '' ? null : Number(this.moveTarget);
        for (const ref of refs) {
            if (ref.kind === 'folder') {
                if (target !== null && this.subtree(ref.id).has(target)) continue; // never into own subtree
                await this._moveFolder(ref.id, target);
            } else {
                const f = this.files.find((x) => x.id === ref.id);
                if (f) await this._saveFile(f, { file_folder_id: target });
            }
        }
        this.selected = [];
    },

    async _moveFolder(id, parentId) {
        const f = this.folders.find((x) => x.id === id);
        if (! f) return;
        const prev = f.parent;
        f.parent = parentId;
        try {
            const res = await fetch('/files/folders/' + id + '/move', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ parent_id: parentId }) });
            if (! res.ok) { f.parent = prev; window.llToast?.(labels.saveFailed); return; }
            const d = await res.json().catch(() => ({}));
            if (d.folder) Object.assign(f, normFolder(d.folder));
        } catch (e) { f.parent = prev; window.llToast?.(labels.saveFailed); }
    },

    /* ---- Drag & drop into folders ---- */

    get parentFolderId() {
        const f = this.folders.find((x) => x.id === this.cwd);
        return f ? (f.parent ?? null) : null;
    },

    onDragStart(event, row) {
        this.dragItem = { kind: row.kind, id: row.id };
        event.dataTransfer.effectAllowed = 'move';
        try { event.dataTransfer.setData('text/plain', row.id); } catch (e) { /* ignore */ }
    },
    onDragEnd() { this.dragItem = null; },

    async dropInto(targetFolderId) {
        const item = this.dragItem;
        this.dragItem = null;
        if (! item) return;
        if (item.kind === 'folder') {
            if (item.id === targetFolderId) return;
            if (targetFolderId !== null && this.subtree(item.id).has(targetFolderId)) return; // no cycle
            const f = this.folders.find((x) => x.id === item.id);
            if (f && (f.parent ?? null) !== targetFolderId) await this._moveFolder(item.id, targetFolderId);
        } else {
            const f = this.files.find((x) => x.id === item.id);
            if (f && (f.folder ?? null) !== targetFolderId) await this._saveFile(f, { file_folder_id: targetFolderId });
        }
    },

    /* ---- Tags ---- */

    openTags(row) {
        this.tagsRef = { kind: row.kind, id: row.id };
        this.tagsValue = (row.tags ?? []).join(', ');
        this.tagsOpen = true;
    },
    async applyTags() {
        const ref = this.tagsRef;
        this.tagsOpen = false;
        this.tagsRef = null;
        if (! ref || ref.kind !== 'file') return; // folders carry no tags in the relational model
        const tags = [...new Set(this.tagsValue.split(',').map((t) => t.trim()).filter(Boolean))];
        const f = this.files.find((x) => x.id === ref.id);
        if (f) await this._saveFile(f, { tags });
    },

    /* ---- Delete / trash / restore ---- */

    confirmDelete(row) {
        this.deleteRefs = row ? [{ kind: row.kind, id: row.id, name: row.name }] : this.selectionRefs;
        this.deleteOpen = this.deleteRefs.length > 0;
    },

    async applyDelete(permanent = false) {
        const refs = this.deleteRefs;
        this.deleteOpen = false;
        this.deleteRefs = [];
        if (! refs.length) return;
        await this._track((async () => {
            for (const ref of refs) {
                if (ref.kind === 'folder') await this._deleteFolder(ref.id);
                else if (permanent) await this._forceDelete({ id: ref.id });
                else await this._trashFile(ref.id);
            }
            this.selected = [];
            this.refreshUsage();
        })());
    },

    async _trashFile(id) {
        const f = this.files.find((x) => x.id === id);
        try {
            await apiRequest('DELETE', '/files/entries/' + id);
            this._removeFile(id);
            if (f) { f.trashed = new Date().toISOString(); if (this.trashLoaded) this.trash.unshift(f); }
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async _forceDelete(ref) {
        try {
            await apiRequest('DELETE', '/files/entries/' + ref.id + '/force');
            this._removeFile(ref.id);
            const j = this.trash.findIndex((x) => x.id === ref.id);
            if (j >= 0) this.trash.splice(j, 1);
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    // Soft-deletes the folder + its whole subtree server-side; reflect locally.
    async _deleteFolder(id) {
        try {
            await apiRequest('DELETE', '/files/folders/' + id);
            const kill = this._folderClosure([id]);
            this.folders = this.folders.filter((f) => ! kill.has(f.id));
            this.files = this.files.filter((f) => ! (f.folder != null && kill.has(f.folder)));
            if (kill.has(this.cwd)) this.cwd = null;
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },

    // Move an item straight to the trash (drag-and-drop onto the trash).
    trashItem(ref) {
        if (ref.kind === 'folder') this._deleteFolder(ref.id);
        else this._trashFile(ref.id);
        this.selected = [];
    },

    async restore(row) {
        try {
            const d = await postForm('/files/entries/' + row.id + '/restore', {});
            const i = this.trash.findIndex((x) => x.id === row.id);
            if (i >= 0) this.trash.splice(i, 1);
            if (d.file) this.files.unshift(normFile(d.file));
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async purge(row) {
        if (! await this.$store.confirm.ask(labels.purgeConfirm || '')) return;
        await this._track(this._forceDelete(row));
        this.refreshUsage();
    },
    async emptyTrash() {
        if (! this.trashLoaded) await this._loadTrash();
        if (! this.trash.length) return;
        if (! await this.$store.confirm.ask(labels.emptyTrashConfirm || '')) return;
        try { await this._track(postForm('/files/entries/trash/empty', {})); this.trash = []; this.refreshUsage(); }
        catch (e) { window.llToast?.(labels.saveFailed); }
    },

    async toggleFavorite(row) {
        const f = this.files.find((x) => x.id === row.id) || this.trash.find((x) => x.id === row.id);
        if (! f) return;
        const next = ! f.favorite;
        f.favorite = next; if (row) row.favorite = next;
        try { const d = await postForm('/files/entries/' + f.id + '/toggle', { field: 'favorite', value: next }); if (d.file) Object.assign(f, normFile(d.file)); }
        catch (e) { f.favorite = ! next; if (row) row.favorite = ! next; window.llToast?.(labels.saveFailed); }
    },

    /* ---- Content operations (plaintext bytes) ---- */

    rawUrl(row) { return '/files/entries/' + row.id + '/raw'; },

    upload(fileList) {
        return this.uploadItems([...fileList].map((f) => ({ file: f, path: f.name })));
    },

    async drop(event) {
        this.dragging = false;
        const items = event.dataTransfer.items;
        let files = [];
        if (items && items.length && items[0].webkitGetAsEntry) {
            const entries = [...items].map((i) => i.webkitGetAsEntry()).filter(Boolean);
            for (const entry of entries) await this.walkEntry(entry, '', files);
        } else {
            files = [...event.dataTransfer.files].map((f) => ({ file: f, path: f.name }));
        }
        await this.uploadItems(files);
    },

    async walkEntry(entry, prefix, out) {
        if (entry.isFile) {
            const f = await new Promise((res) => entry.file(res, () => res(null)));
            if (f) out.push({ file: f, path: prefix + f.name });
            else this.uploads.push({ name: prefix + entry.name, state: 'error', progress: 0, error: labels.saveFailed || 'read failed' });
            return;
        }
        const reader = entry.createReader();
        const children = [];
        for (;;) {
            const batch = await new Promise((res) => reader.readEntries(res, () => res([])));
            if (! batch.length) break;
            children.push(...batch);
        }
        for (const child of children) await this.walkEntry(child, prefix + entry.name + '/', out);
    },

    // OS/editor junk that should never be uploaded.
    isJunkUpload(name) {
        const path = name || '';
        const n = path.split('/').pop();
        if (/(^|\/)(__MACOSX|\.Spotlight-V100|\.Trashes|\.fseventsd|\.TemporaryItems|\.DocumentRevisions-V100|\$RECYCLE\.BIN|System Volume Information|\.thumbnails|LOST\.DIR|\.git)(\/|$)/i.test(path)) return true;
        return (
            /^\.DS_Store$/i.test(n) || /^\._/.test(n) || /^\.localized$/i.test(n)
            || /^\.AppleDouble$/i.test(n) || /^\.AppleDB$/i.test(n) || /^\.AppleDesktop$/i.test(n)
            || /^\.apdisk$/i.test(n) || /^Icon\r?$/.test(n)
            || /^Thumbs\.db$/i.test(n) || /^ehthumbs\.db$/i.test(n) || /^ehthumbs_vista\.db$/i.test(n)
            || /^desktop\.ini$/i.test(n) || /^\$RECYCLE\.BIN$/i.test(n) || /^~\$/.test(n) || /\.stackdump$/i.test(n)
            || /^\.directory$/i.test(n) || /^\.Trash-/i.test(n) || /^\.nfs[0-9a-f]+$/i.test(n)
            || /^\.fuse_hidden/i.test(n) || /^\.~lock\./i.test(n)
            || /^\.nomedia$/i.test(n) || /^\.pending-/i.test(n) || /^\.trashed-/i.test(n)
            || /\.(tmp|temp|swp|swo|swn|crdownload|part|partial|bak|old)$/i.test(n)
            || /~$/.test(n) || /^\.#/.test(n)
        );
    },

    async uploadItems(items) {
        items = (items || []).filter((it) => ! this.isJunkUpload(it.file?.name || it.path));
        if (! items.length) return;
        if (this.uploadBatches === 0) this.uploads = [];
        this.uploadBatches++;

        // Recreate the folder chains up front (sequentially, deduped by path) so
        // parallel file uploads never race to create the same folder twice.
        const dirCache = new Map();
        dirCache.set('', this.cwd);
        const ensureDir = async (path) => {
            const parts = path.split('/');
            parts.pop(); // drop the filename
            let acc = '';
            let parent = this.cwd;
            for (const seg of parts) {
                acc = acc ? `${acc}/${seg}` : seg;
                if (dirCache.has(acc)) { parent = dirCache.get(acc); continue; }
                let existing = this.folders.find((f) => (f.parent ?? null) === parent && f.name === seg);
                if (! existing) {
                    try { const d = await postForm('/files/folders', { name: seg, parent_id: parent }); existing = normFolder(d.folder); this.folders.push(existing); }
                    catch (e) { existing = null; }
                }
                const id = existing ? existing.id : parent;
                dirCache.set(acc, id);
                parent = id;
            }
            return parent;
        };
        for (const it of items) it._folderId = await ensureDir(it.path);

        const start = this.uploads.length;
        for (const item of items) this.uploads.push({ name: item.file.name, state: 'pending', progress: 0, error: '' });

        let next = 0;
        const worker = async () => {
            while (next < items.length) {
                const idx = next++;
                const item = items[idx];
                const entry = this.uploads[start + idx];
                try {
                    const fileRow = item.file.size > CHUNK_THRESHOLD
                        ? await this._uploadChunked(item.file, entry, item._folderId)
                        : await this._uploadWhole(item.file, entry, item._folderId);
                    entry.state = 'done';
                    entry.progress = 100;
                    if (fileRow) this.files.unshift(normFile(fileRow));
                } catch (e) {
                    entry.state = 'error';
                    entry.error = e && e.quota ? (labels.quotaExceeded || labels.uploadFailed)
                        : (e && e.unreadable ? (labels.uploadUnreadable || labels.uploadFailed) : labels.uploadFailed);
                }
            }
        };
        const hasLarge = items.some((i) => i.file.size > CHUNK_THRESHOLD);
        const lanes = Math.min(hasLarge ? 2 : 4, items.length);
        await Promise.all(Array.from({ length: lanes }, worker));

        this.uploadBatches--;
        this.refreshUsage();
        if (this.uploadBatches === 0 && ! this.uploads.some((u) => u.state === 'error')) {
            setTimeout(() => { if (! this.uploading) this.uploads = []; }, 4000);
        }
    },

    // Whole-file upload via XHR (byte progress into the tray entry).
    _uploadWhole(file, entry, folderId) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', this._token());
            data.append('file', file, file.name);
            if (folderId != null) data.append('file_folder_id', folderId);
            data.append('name', file.name);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/files/entries');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            entry.state = 'uploading';
            xhr.timeout = 300000;
            xhr.upload.onprogress = (ev) => { if (ev.lengthComputable) entry.progress = Math.round((ev.loaded / ev.total) * 100); };
            xhr.onload = () => {
                if (xhr.status === 413) { const err = new Error('quota'); err.quota = true; reject(err); return; }
                if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('upload failed')); return; }
                try { resolve(JSON.parse(xhr.responseText).file); } catch (e) { reject(e); }
            };
            xhr.onerror = () => { const e = new Error('network'); e.unreadable = true; reject(e); };
            xhr.ontimeout = () => reject(new Error('timeout'));
            xhr.onabort = () => reject(new Error('abort'));
            try { xhr.send(data); } catch (e) { const err = new Error('read'); err.unreadable = true; reject(err); }
        });
    },

    // Chunked upload for large files: init → parts (by index) → complete.
    async _uploadChunked(file, entry, folderId) {
        entry.state = 'uploading';
        const initBody = { name: file.name, size: file.size };
        if (folderId != null) initBody.file_folder_id = folderId;
        const init = await fetch('/files/upload/chunk/init', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(initBody) });
        if (init.status === 413) { const e = new Error('quota'); e.quota = true; throw e; }
        if (! init.ok) throw new Error('init failed');
        const { id, partSize } = await init.json();
        const ps = partSize || CHUNK_THRESHOLD;
        try {
            let index = 0;
            let sent = 0;
            const total = file.size;
            for (let off = 0; off < total || (total === 0 && index === 0); off += ps) {
                const end = Math.min(off + ps, total);
                await this._uploadChunkPart(id, index, file.slice(off, end), entry, sent, total);
                sent += (end - off);
                index++;
                if (total === 0) break;
            }
            const comp = await fetch('/files/upload/chunk/complete', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ id }) });
            if (comp.status === 413) { const e = new Error('quota'); e.quota = true; throw e; }
            if (! comp.ok) throw new Error('complete failed');
            entry.progress = 100;
            return (await comp.json()).file;
        } catch (e) {
            fetch('/files/upload/chunk/abort', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ id }) }).catch(() => {});
            throw e;
        }
    },

    _uploadChunkPart(id, index, blob, entry, offsetStart, totalSize) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', this._token());
            data.append('id', id);
            data.append('index', index);
            data.append('chunk', blob, 'chunk');
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/files/upload/chunk/part');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 600000;
            xhr.upload.onprogress = (ev) => { if (ev.lengthComputable && totalSize) entry.progress = Math.round(((offsetStart + ev.loaded) / totalSize) * 100); };
            xhr.onload = () => {
                if (xhr.status === 413) { const err = new Error('quota'); err.quota = true; reject(err); return; }
                if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('part failed')); return; }
                resolve();
            };
            xhr.onerror = () => { const e = new Error('network'); e.unreadable = true; reject(e); };
            xhr.ontimeout = () => reject(new Error('timeout'));
            try { xhr.send(data); } catch (e) { const err = new Error('read'); err.unreadable = true; reject(err); }
        });
    },

    get uploading() { return this.uploads.some((u) => u.state === 'pending' || u.state === 'uploading'); },
    get uploadsDone() { return this.uploads.filter((u) => u.state === 'done' || u.state === 'error').length; },
    dismissUploads() { this.uploads = []; },

    /* ---- Download ---- */

    // Bytes are plaintext, so a plain navigation to the raw URL (with
    // ?download=1 → Content-Disposition: attachment) downloads the file.
    download(row) {
        const a = document.createElement('a');
        a.href = this.rawUrl(row) + '?download=1';
        a.rel = 'noopener';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => a.remove(), 0);
    },

    /* ---- Preview & editor (plaintext; the raw URL is used directly) ---- */

    isPdf(row) {
        return row?.kind === 'file' && (row.mime === 'application/pdf' || /\.pdf$/i.test(row.name || ''));
    },

    async openFile(row) {
        const mime = row.mime || 'application/octet-stream';
        const url = this.rawUrl(row);
        // SVG is the one "image" type that can carry markup — never render inline.
        if (mime.startsWith('image/') && ! mime.includes('svg')) { this.viewer = { open: true, kind: 'image', src: url, row, saving: false, saved: false }; return; }
        if (mime === 'application/pdf') { this.viewer = { open: true, kind: 'pdf', src: url, row, saving: false, saved: false }; return; }
        if (mime.startsWith('video/')) { this.viewer = { open: true, kind: 'video', src: url, row, saving: false, saved: false }; return; }
        if (mime.startsWith('audio/')) { this.viewer = { open: true, kind: 'audio', src: url, row, saving: false, saved: false }; return; }
        // Editable text: valid UTF-8 and reasonably small.
        if ((row.size || 0) <= 2 * 1024 * 1024) {
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) {
                    const buf = await res.arrayBuffer();
                    try {
                        const text = new TextDecoder('utf-8', { fatal: true }).decode(new Uint8Array(buf));
                        this.editorText = text;
                        this.viewer = { open: true, kind: 'text', src: '', row, saving: false, saved: false };
                        return;
                    } catch (e) { /* binary: fall through */ }
                }
            } catch (e) { /* fall through */ }
        }
        this.viewer = { open: true, kind: 'none', src: '', row, saving: false, saved: false };
    },

    // Images in the current view, in display order — the slideshow set.
    get viewerImages() {
        return this.rows.filter((r) => r.kind === 'file' && (r.mime || '').startsWith('image/'));
    },
    get viewerIndex() {
        if (this.viewer.kind !== 'image' || ! this.viewer.row) return -1;
        const key = this.rowKey(this.viewer.row);
        return this.viewerImages.findIndex((r) => this.rowKey(r) === key);
    },
    get viewerHasGallery() {
        return this.viewer.kind === 'image' && this.viewerImages.length > 1;
    },
    viewerStep(dir) {
        const imgs = this.viewerImages;
        if (imgs.length < 2) return;
        const i = this.viewerIndex;
        if (i < 0) return;
        this.openFile(imgs[(i + dir + imgs.length) % imgs.length]);
    },

    // Save the edited text as a new revision (server archives the current one
    // into history).
    async saveText() {
        const row = this.viewer.row;
        if (this.viewer.kind !== 'text' || ! row) return;
        this.viewer.saving = true;
        this.viewer.saved = false;
        try {
            const bytes = new TextEncoder().encode(this.editorText);
            const data = new FormData();
            data.append('_token', this._token());
            data.append('file', new File([bytes], row.name, { type: row.mime || 'text/plain' }));
            const res = await fetch('/files/entries/' + row.id + '/content', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            if (! res.ok) throw new Error('save failed');
            const d = await res.json().catch(() => ({}));
            if (d.file) {
                const f = this.files.find((x) => x.id === row.id);
                if (f) Object.assign(f, normFile(d.file));
                Object.assign(row, normFile(d.file));
            }
            this.viewer.saved = true;
            this.refreshUsage();
        } catch (e) { this.error = labels.saveFailed; }
        this.viewer.saving = false;
    },

    closeViewer() {
        this.editorText = '';
        this.viewer = { open: false, kind: 'none', src: '', row: null, saving: false, saved: false };
    },

    /* ---- Version history ---- */

    async openVersions(row) {
        this.versions = { open: true, row, loading: true, list: [] };
        try { const d = await getJson('/files/entries/' + row.id + '/versions'); this.versions.list = (d.versions || []).map((v) => ({ ...v })); }
        catch (e) { /* keep empty */ } finally { this.versions.loading = false; }
    },
    downloadVersion(v) {
        const row = this.versions.row;
        if (! row) return;
        const a = document.createElement('a');
        a.href = '/files/entries/' + row.id + '/versions/' + v.id + '/raw?download=1';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => a.remove(), 0);
    },
    async restoreVersion(v) {
        if (! await this.$store.confirm.ask(labels.restoreConfirm || '')) return;
        const row = this.versions.row;
        if (! row) return;
        try {
            const d = await postForm('/files/entries/' + row.id + '/versions/' + v.id + '/restore', {});
            if (d.file) { const f = this.files.find((x) => x.id === row.id); if (f) Object.assign(f, normFile(d.file)); }
            this.versions.open = false;
            this.refreshUsage();
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },

    /* ---- Layout / sort / search ---- */

    setLayout(l) {
        this.layout = l;
        try { localStorage.setItem('ll-files-layout', l); } catch (e) { /* ignore */ }
    },
    sortBy(key) {
        if (this.sortKey === key) { this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'; } else { this.sortKey = key; this.sortDir = 'asc'; }
    },
    sortArrow(key) {
        return this.sortKey === key ? (this.sortDir === 'asc' ? '↑' : '↓') : '';
    },
    _debounceSearch() {
        clearTimeout(this._searchTimer);
        this._searchTimer = setTimeout(() => { this._q = this.query.trim().toLowerCase(); this._runContentSearch(); }, 250);
    },

    // Server full-text/OCR content search: folds matching file ids into the row
    // filter so a query finds text INSIDE pdfs/images/text, not just names/tags.
    async _runContentSearch() {
        const q = (this.query || '').trim();
        if (q.length < 2) { this.contentHits = null; return; }
        this.contentSearching = true;
        try {
            const d = await getJson('/files/search?q=' + encodeURIComponent(q));
            this.contentHits = new Set((d.files || []).map((f) => f.id));
        } catch (e) { this.contentHits = null; }
        this.contentSearching = false;
    },

    // ---- Labels (coloured taxonomy) ----
    toggleLabelFilter(id) { this.activeLabel = this.activeLabel === id ? null : id; },
    labelById(id) { return this.fileLabels.find((l) => l.id === id) || null; },
    fileLabelObjects(row) { return (row.labelIds || []).map((id) => this.labelById(id)).filter(Boolean); },
    openLabelModal() { this.labelDraft = { id: null, name: '', color: '#6b7280' }; this.labelModal = true; },
    editLabel(l) { this.labelDraft = { id: l.id, name: l.name, color: l.color }; this.labelModal = true; },
    async saveLabel() {
        const d = this.labelDraft;
        if (! (d.name || '').trim()) return;
        try {
            const body = { name: d.name.trim(), color: d.color || '#6b7280' };
            const res = d.id
                ? await postForm('/files/labels/' + d.id, body, 'PUT')
                : await postForm('/files/labels', body);
            if (res.label) {
                const lbl = normLabel(res.label);
                const i = this.fileLabels.findIndex((x) => x.id === lbl.id);
                if (i >= 0) this.fileLabels[i] = lbl; else this.fileLabels.push(lbl);
                this.fileLabels.sort((a, b) => a.name.localeCompare(b.name));
            }
            this.labelModal = false;
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async deleteLabel(l) {
        if (! await this.$store.confirm.ask(labels.labelDeleteConfirm || labels.purgeConfirm || '')) return;
        try {
            await apiRequest('DELETE', '/files/labels/' + l.id);
            this.fileLabels = this.fileLabels.filter((x) => x.id !== l.id);
            if (this.activeLabel === l.id) this.activeLabel = null;
            for (const f of this.files) f.labelIds = (f.labelIds || []).filter((id) => id !== l.id);
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async toggleFileLabel(row, id) {
        const f = this.files.find((x) => x.id === row.id) || row;
        const has = (f.labelIds || []).includes(id);
        const next = has ? f.labelIds.filter((x) => x !== id) : [...(f.labelIds || []), id];
        try {
            const res = await postForm('/files/entries/' + f.id + '/labels', { label_ids: next });
            if (res.file) { const nf = normFile(res.file); Object.assign(f, nf); if (row !== f) Object.assign(row, nf); }
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },

    /* ---- Paperless (plaintext bytes fetched from the raw URL) ---- */

    async openPaperless(row) {
        const store = Alpine.store('paperless');
        store.begin(row.name, {}, { allowDelete: true, context: { source: 'files', rowId: row.id } });
        try {
            const res = await fetch(this.rawUrl(row), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) throw new Error('read');
            const blob = await res.blob();
            store.setFile(new Blob([blob], { type: row.mime || 'application/pdf' }));
        } catch (e) { store.fail(labels.downloadFailed); }
    },
    onPaperlessSent(detail) {
        const ctx = detail?.context;
        if (! detail?.deleteAfter || ctx?.source !== 'files') return;
        if (this.viewer.open && this.viewer.row?.id === ctx.rowId) this.closeViewer();
        this._forceDelete({ id: ctx.rowId });
        this.refreshUsage();
    },

    /* ---- Public share link (plaintext; owner side) ---- */
    _shareKey(kind, id) { return `${kind}:${id}`; },
    _shareLink(token) { return `${config.shareBase}/${token}`; },
    openShare(row) {
        const cur = this._shareCache[this._shareKey(row.kind, row.id)] || null;
        this.share = {
            open: true, kind: row.kind, id: row.id, name: row.name || '', busy: false,
            password: '', expiresAt: '',
            allowDownload: cur ? cur.allowDownload : true,
            link: cur ? this._shareLink(cur.token) : '', error: '', current: cur,
        };
    },
    closeShare() { this.share.open = false; this.share.password = ''; },
    async copyShareLink() {
        if (! this.share.link) return;
        try { await navigator.clipboard.writeText(this.share.link); window.llToast?.(labels.shareCopied || ''); } catch (e) { /* clipboard blocked */ }
    },
    async createShare() {
        if (! this.share.kind || this.share.busy) return;
        this.share.busy = true; this.share.error = '';
        try {
            const body = { kind: this.share.kind, allow_download: this.share.allowDownload };
            if (this.share.kind === 'folder') body.file_folder_id = this.share.id;
            else body.file_id = this.share.id;
            if (this.share.expiresAt) body.expires_at = new Date(this.share.expiresAt).toISOString();
            if (this.share.password.trim()) body.password = this.share.password.trim();
            const { share } = await postForm(config.sharesUrl, body);
            const s = normShare(share);
            this._shareCache[this._shareKey(this.share.kind, this.share.id)] = s;
            this.share.current = s;
            this.share.password = '';
            this.share.link = this._shareLink(s.token);
        } catch (e) { this.share.error = labels.shareError || 'Error'; } finally { this.share.busy = false; }
    },
    async updateShare() {
        if (! this.share.current || this.share.busy) return;
        this.share.busy = true; this.share.error = '';
        try {
            const body = { allow_download: this.share.allowDownload, version: this.share.current.version };
            if (this.share.expiresAt) body.expires_at = new Date(this.share.expiresAt).toISOString();
            if (this.share.password.trim()) body.password = this.share.password.trim();
            else if (! this.share.current.needsPassword) body.remove_password = true;
            const { share } = await postForm(`${config.sharesUrl}/${this.share.current.id}`, body, 'PUT');
            const s = normShare(share);
            this._shareCache[this._shareKey(this.share.kind, this.share.id)] = s;
            this.share.current = s;
            this.share.password = '';
        } catch (e) { this.share.error = labels.shareError || 'Error'; } finally { this.share.busy = false; }
    },
    async revokeShare() {
        if (! this.share.current) return;
        this.share.busy = true;
        try { await apiRequest('DELETE', `${config.sharesUrl}/${this.share.current.id}`); } catch (e) { /* best effort */ }
        delete this._shareCache[this._shareKey(this.share.kind, this.share.id)];
        this.share.current = null; this.share.link = ''; this.share.busy = false;
    },

    // ---- Cross-user folder sharing (owner side) + shared-with-me (member side) ----
    swm: { open: false, view: 'list', shares: [], current: null, files: [], folders: [], role: 'viewer' },

    async shareFolderWithUser(row) {
        if (! row || row.kind !== 'folder') return;
        const email = await this.$store.confirm.prompt(labels.folderShareEmail || 'Recipient e-mail', { value: '' });
        if (! email || ! email.trim()) return;
        try {
            const res = await this._req('POST', '/files/folder-shares', { file_folder_id: row.id, email: email.trim(), role: 'editor' });
            if (res.status === 422) { window.llToast?.(labels.folderShareNotFound || 'No such user.'); return; }
            if (! res.ok) { window.llToast?.(labels.saveFailed); return; }
            window.llToast?.(labels.folderShareDone || 'Folder shared.');
        } catch (e) { window.llToast?.(labels.saveFailed); }
    },
    async openSharedWithMe() {
        this.swm.open = true; this.swm.view = 'list';
        try { const d = await getJson('/shared-with-me'); this.swm.shares = d.shares || []; } catch (e) { this.swm.shares = []; }
    },
    async browseShare(share) {
        this.swm.current = share; this.swm.view = 'browse';
        try {
            const d = await getJson('/shared-with-me/' + share.id);
            this.swm.files = d.files || []; this.swm.folders = d.folders || []; this.swm.role = d.role || 'viewer';
        } catch (e) { this.swm.files = []; this.swm.folders = []; }
    },
    swmRawUrl(share, file) { return '/shared-with-me/' + share.id + '/files/' + file.id + '/raw'; },
    closeSwm() { this.swm.open = false; this.swm.view = 'list'; this.swm.current = null; this.swm.files = []; this.swm.folders = []; },
});
