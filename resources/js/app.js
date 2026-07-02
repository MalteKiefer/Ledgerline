import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import { Vault } from './vault';
import { EditorView, basicSetup } from 'codemirror';
import { EditorState, Compartment } from '@codemirror/state';
import { LanguageDescription } from '@codemirror/language';
import { languages } from '@codemirror/language-data';
import JSZip from 'jszip';
import L from 'leaflet';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet's default marker resolves its images by a relative URL that 404s
// under a bundler; point it at the bundled assets so pins render.
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

/**
 * Type-ahead combobox for selecting a contact's function from the fixed
 * ContactFunction enum. The visible input filters the labels; the submitted
 * value is always one of the enum's backing values (or empty, which the
 * server-side validation rejects). No third-party widget library is used.
 *
 * @param {{value: string, label: string}[]} options
 * @param {string} initial  The pre-selected enum value, if any.
 */
Alpine.data('contactFunctionCombobox', (options, initial = '') => ({
    options,
    open: false,
    selected: initial,
    query: (options.find((option) => option.value === initial) || {}).label || '',

    /** Options matching the current query (by label or value). */
    get filtered() {
        const query = this.query.toLowerCase().trim();

        if (query === '') {
            return this.options;
        }

        return this.options.filter(
            (option) =>
                option.label.toLowerCase().includes(query) ||
                option.value.toLowerCase().includes(query),
        );
    },

    /** Pick an option from the list. */
    choose(option) {
        this.selected = option.value;
        this.query = option.label;
        this.open = false;
    },

    /** Keep the submitted value in sync when the user types an exact label. */
    syncFromQuery() {
        this.open = true;

        const match = this.options.find(
            (option) => option.label.toLowerCase() === this.query.toLowerCase().trim(),
        );

        this.selected = match ? match.value : '';
    },
}));

/**
 * Repeater for a contact's labelled email addresses and phone numbers. Each
 * channel has a free label (suggested via a datalist) and a value. Rows can be
 * added and removed freely; empty rows are stripped server-side.
 *
 * @param {{label: string, value: string}[]} initialEmails
 * @param {{label: string, value: string}[]} initialPhones
 */
Alpine.data('contactChannels', (initialEmails = [], initialPhones = []) => ({
    emails: initialEmails.length
        ? initialEmails.map((row) => ({ ...row }))
        : [{ label: 'Work', value: '' }],
    phones: initialPhones.length
        ? initialPhones.map((row) => ({ ...row }))
        : [{ label: 'Work', value: '' }],

    addEmail() {
        this.emails.push({ label: '', value: '' });
    },

    removeEmail(index) {
        this.emails.splice(index, 1);
    },

    addPhone() {
        this.phones.push({ label: '', value: '' });
    },

    removePhone(index) {
        this.phones.splice(index, 1);
    },
}));

/**
 * Type-ahead combobox for selecting a country from the full ISO 3166 list.
 * Options carry a flag emoji; the submitted value is always an alpha-2 code
 * (or empty). The visible list is capped for rendering performance.
 *
 * @param {{value: string, label: string, flag: string}[]} options
 * @param {string} initial  The pre-selected country code, if any.
 */
Alpine.data('countryCombobox', (options, initial = '') => ({
    options,
    open: false,
    selected: initial,
    query: (options.find((option) => option.value === initial) || {}).label || '',

    get selectedFlag() {
        const option = this.options.find((item) => item.value === this.selected);

        return option ? option.flag : '';
    },

    get filtered() {
        const query = this.query.toLowerCase().trim();

        if (query === '') {
            return this.options.slice(0, 80);
        }

        return this.options
            .filter(
                (option) =>
                    option.label.toLowerCase().includes(query) ||
                    option.value.toLowerCase() === query,
            )
            .slice(0, 80);
    },

    choose(option) {
        this.selected = option.value;
        this.query = option.label;
        this.open = false;
    },

    syncFromQuery() {
        this.open = true;

        const match = this.options.find(
            (option) => option.label.toLowerCase() === this.query.toLowerCase().trim(),
        );

        this.selected = match ? match.value : '';
    },
}));

/**
 * Spotlight-style global search palette. Opens a centred modal, searches live
 * as the user types (debounced), and supports keyboard navigation. Results come
 * from the JSON suggest endpoint; the URLs are app routes.
 */
Alpine.data('spotlight', () => ({
    open: false,
    query: '',
    groups: [],
    flat: [],
    activeIndex: -1,
    loading: false,
    controller: null,

    openPalette() {
        this.open = true;
        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
    },

    close() {
        this.open = false;
        this.query = '';
        this.groups = [];
        this.flat = [];
        this.activeIndex = -1;
    },

    async runSearch() {
        const term = this.query.trim();

        if (term === '') {
            this.groups = [];
            this.flat = [];
            this.activeIndex = -1;

            return;
        }

        this.loading = true;

        try {
            if (this.controller) {
                this.controller.abort();
            }

            this.controller = new AbortController();

            const response = await fetch(
                '/search/suggest?q=' + encodeURIComponent(term),
                { headers: { Accept: 'application/json' }, signal: this.controller.signal },
            );

            if (!response.ok) {
                this.groups = [];
                this.flat = [];

                return;
            }

            const data = await response.json();
            this.groups = data.groups || [];
            this.flat = this.groups.flatMap((group) => group.results);
            this.activeIndex = this.flat.length ? 0 : -1;
        } catch (error) {
            // Aborted or network error: leave the previous results in place.
        } finally {
            this.loading = false;
        }
    },

    move(delta) {
        if (!this.flat.length) {
            return;
        }

        this.activeIndex = (this.activeIndex + delta + this.flat.length) % this.flat.length;
    },

    isActive(item) {
        return this.activeIndex >= 0 && this.flat[this.activeIndex] && this.flat[this.activeIndex].url === item.url;
    },

    go() {
        const item = this.flat[this.activeIndex];

        if (item) {
            window.location.href = item.url;
        } else if (this.query.trim() !== '') {
            this.seeAll();
        }
    },

    seeAll() {
        window.location.href = '/search?q=' + encodeURIComponent(this.query.trim());
    },
}));

/**
 * Generic value/label type-ahead combobox (e.g. for picking a customer).
 *
 * @param {{value: string|number, label: string}[]} options
 * @param {string} initial
 */
Alpine.data('selectCombobox', (options, initial = '') => ({
    options,
    open: false,
    selected: initial,
    query: (options.find((option) => String(option.value) === String(initial)) || {}).label || '',

    get filtered() {
        const query = this.query.toLowerCase().trim();

        if (query === '') {
            return this.options.slice(0, 50);
        }

        return this.options
            .filter((option) => option.label.toLowerCase().includes(query))
            .slice(0, 50);
    },

    choose(option) {
        this.selected = option.value;
        this.query = option.label;
        this.open = false;
    },

    syncFromQuery() {
        this.open = true;

        const match = this.options.find(
            (option) => option.label.toLowerCase() === this.query.toLowerCase().trim(),
        );

        this.selected = match ? match.value : '';
    },
}));

/**
 * Tag input: free-text chips with suggestions. Submits one hidden tags[] field
 * per chip. Enter or comma adds the current text; duplicates are ignored.
 *
 * @param {string[]} initial
 */
Alpine.data('tagInput', (initial = []) => ({
    tags: Array.isArray(initial) ? [...initial] : [],
    query: '',

    add() {
        const value = this.query.trim().replace(/,$/, '').trim();

        if (value !== '' && !this.tags.some((tag) => tag.toLowerCase() === value.toLowerCase())) {
            this.tags.push(value);
        }

        this.query = '';
    },

    remove(index) {
        this.tags.splice(index, 1);
    },

    onKey(event) {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            this.add();
        }
    },
}));

/**
 * Drag-and-drop file field. Wraps a hidden native file input, reflects the
 * chosen file name, and highlights while dragging over.
 */
Alpine.data('dropzone', () => ({
    fileName: '',
    over: false,

    onDrop(event) {
        this.over = false;
        const files = event.dataTransfer?.files;

        if (files && files.length) {
            this.$refs.input.files = files;
            this.fileName = files[0].name;
        }
    },

    onChange() {
        this.fileName = this.$refs.input.files.length ? this.$refs.input.files[0].name : '';
    },
}));

/**
 * Repeatable invoice line editor. Each row submits as lines[i][field]; the
 * server recomputes all totals, so the live net here is only a preview.
 *
 * @param {{description: string, quantity: number, unit: string, unit_price: string, tax_rate: number}[]} initial
 */
Alpine.data('invoiceLines', (initial = []) => ({
    lines: initial.length
        ? initial
        : [{ description: '', quantity: 1, unit: '', unit_price: '', tax_rate: 19 }],

    add() {
        this.lines.push({ description: '', quantity: 1, unit: '', unit_price: '', tax_rate: 19 });
    },

    remove(index) {
        this.lines.splice(index, 1);
        if (this.lines.length === 0) {
            this.add();
        }
    },

    /** Live net preview (quantity * unit price), for display only. */
    get net() {
        return this.lines.reduce(
            (sum, line) => sum + (parseFloat(line.quantity || 0) * parseFloat(line.unit_price || 0)),
            0,
        );
    },
}));

/**
 * File explorer: multiselect, a shared "move to folder" modal (for a single row
 * or the whole selection), inline rename, and a bulk-delete modal.
 *
 * @param {number[]} allIds  Ids of the files currently listed.
 */
Alpine.data('filesExplorer', (allIds = [], config = {}) => ({
    allIds,
    folderIds: config.folderIds ?? [],
    selected: [],
    selectedFolders: [],
    moveOpen: false,
    moveIds: [],
    moveFolderIds: [],
    target: '',
    deleteOpen: false,
    renaming: null,
    enc: { active: false, done: 0, total: 0 },
    dl: { active: false, done: 0, total: 0 },

    get selectionCount() {
        return this.selected.length + this.selectedFolders.length;
    },

    // Drag-and-drop upload (whole-window dropzone, folders included).
    dragging: false,
    uploading: false,
    progress: { done: 0, total: 0 },
    conflictOpen: false,
    conflictCount: 0,
    pendingFiles: [],
    uploadItems: [],

    initDropzone() {
        let depth = 0;
        window.addEventListener('dragenter', (e) => {
            if (e.dataTransfer?.types?.includes('Files')) { depth++; this.dragging = true; }
        });
        window.addEventListener('dragleave', () => { depth = Math.max(0, depth - 1); if (! depth) this.dragging = false; });
        window.addEventListener('drop', () => { depth = 0; this.dragging = false; });
    },

    async drop(event) {
        this.dragging = false;
        const items = event.dataTransfer.items;
        let files = [];

        // Prefer the entries API so dropped folders (and subfolders) are walked.
        if (items && items.length && items[0].webkitGetAsEntry) {
            const entries = [...items].map((i) => i.webkitGetAsEntry()).filter(Boolean);
            for (const entry of entries) {
                await this.walkEntry(entry, '', files);
            }
        } else {
            files = [...event.dataTransfer.files].map((f) => ({ file: f, path: f.name }));
        }

        await this.startUpload(files);
    },

    // Check for same-name clashes first; ask how to handle them if any.
    async startUpload(files) {
        if (! files.length) {
            return;
        }

        // Zero-knowledge: once a vault exists, every upload is encrypted in the
        // browser. Names are opaque, so there is no server-side conflict check.
        await this.$store.vault.boot();
        if (this.$store.vault.configured) {
            if (! this.$store.vault.unlocked) {
                window.dispatchEvent(new CustomEvent('vault-panel'));
                return;
            }
            await this.uploadFiles(files, 'rename');
            return;
        }

        let conflicts = [];
        try {
            const data = new FormData();
            data.append('_token', config.token);
            if (config.folderId) data.append('folder_id', config.folderId);
            if (config.customerId) data.append('customer_id', config.customerId);
            if (config.projectId) data.append('project_id', config.projectId);
            files.forEach((item) => data.append('paths[]', item.path));
            const r = await fetch(config.conflictsUrl, { method: 'POST', headers: { Accept: 'application/json' }, body: data });
            if (r.ok) conflicts = (await r.json()).conflicts || [];
        } catch (e) { /* if the check fails, fall through and upload */ }

        if (conflicts.length) {
            this.pendingFiles = files;
            this.conflictCount = conflicts.length;
            this.conflictOpen = true;
            return;
        }

        await this.uploadFiles(files, 'rename');
    },

    resolveConflict(strategy) {
        this.conflictOpen = false;
        const files = this.pendingFiles;
        this.pendingFiles = [];
        this.uploadFiles(files, strategy);
    },

    walkEntry(entry, prefix, out) {
        return new Promise((resolve) => {
            if (entry.isFile) {
                entry.file((f) => { out.push({ file: f, path: prefix + f.name }); resolve(); }, () => resolve());
                return;
            }
            const reader = entry.createReader();
            const readBatch = () => reader.readEntries(async (batch) => {
                if (! batch.length) { resolve(); return; }
                for (const child of batch) {
                    await this.walkEntry(child, prefix + entry.name + '/', out);
                }
                readBatch(); // readEntries yields in chunks; keep going until empty
            }, () => resolve());
            readBatch();
        });
    },

    async uploadFiles(files, strategy = 'rename') {
        this.uploading = true;
        this.progress = { done: 0, total: files.length };
        this.uploadItems = files.map((item) => ({ name: item.path, done: false }));

        const encrypting = this.$store.vault.configured && this.$store.vault.unlocked;

        // For encrypted uploads the server cannot read folder names, so recreate
        // any dropped-folder structure here: create the encrypted folder tree and
        // map each relative directory to the folder id it became.
        const folderMap = encrypting ? await this.ensureEncryptedFolders(files) : null;
        const dirOf = (path) => { const p = path.split('/'); p.pop(); return p.join('/'); };

        // Send in batches to stay within the per-request file limit.
        const chunkSize = 25;
        for (let i = 0; i < files.length; i += chunkSize) {
            const chunk = files.slice(i, i + chunkSize);
            const data = new FormData();
            data.append('_token', config.token);
            data.append('on_conflict', strategy);
            if (config.folderId) data.append('folder_id', config.folderId);
            if (config.customerId) data.append('customer_id', config.customerId);
            if (config.projectId) data.append('project_id', config.projectId);
            if (encrypting) {
                data.append('encrypted', '1');
                for (const item of chunk) {
                    const { blob, encMeta, encFileKey } = await Vault.encryptFile(item.file);
                    data.append('files[]', blob, 'blob');
                    data.append('enc_metadata[]', encMeta);
                    data.append('enc_file_key[]', encFileKey);
                    data.append('folder_ids[]', folderMap.get(dirOf(item.path)) ?? '');
                }
            } else {
                chunk.forEach((item) => {
                    data.append('files[]', item.file);
                    data.append('paths[]', item.path);
                });
            }
            try {
                await fetch(config.uploadUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
            } catch (e) { /* continue with remaining batches */ }
            for (let j = i; j < i + chunk.length; j++) {
                this.uploadItems[j].done = true;
            }
            this.progress.done = Math.min(files.length, i + chunk.length);
        }

        window.location.reload();
    },

    // Recreate a dropped folder tree as encrypted folders. Returns a Map from
    // each relative directory path to the folder id ('' = current folder).
    // Existing folders (matched by decrypted name under the same parent) are
    // reused instead of duplicated; missing segments are created parents-first.
    async ensureEncryptedFolders(files) {
        const rootId = config.folderId ?? null;
        const map = new Map();
        map.set('', rootId);

        const dirs = new Set();
        for (const item of files) {
            const parts = item.path.split('/');
            parts.pop(); // drop the filename
            let acc = '';
            for (const seg of parts) {
                acc = acc ? `${acc}/${seg}` : seg;
                dirs.add(acc);
            }
        }
        if (! dirs.size) {
            return map;
        }

        // Snapshot existing folders so a re-dropped tree reuses them. Created
        // folders are pushed back in so sibling segments dedupe within this run.
        let existing = [];
        try {
            existing = await (await fetch(config.foldersListUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })).json();
        } catch (e) { /* fall back to always-create */ }

        const sameParent = (a, b) => (a ?? null) === (b ?? null);
        const folderName = (f) => {
            if (f.enc_name) {
                try { return Vault.decryptFileMeta(f.enc_name).name; } catch (e) { return null; }
            }
            return f.name;
        };

        // Shallowest first, so each folder's parent already exists in the map.
        const ordered = [...dirs].sort((a, b) => a.split('/').length - b.split('/').length);
        for (const dir of ordered) {
            const cut = dir.lastIndexOf('/');
            const parentPath = cut === -1 ? '' : dir.slice(0, cut);
            const name = cut === -1 ? dir : dir.slice(cut + 1);
            const parentId = map.get(parentPath);

            const match = existing.find((f) => sameParent(f.parent_id, parentId) && folderName(f) === name);
            if (match) {
                map.set(dir, match.id);
                continue;
            }

            const encName = Vault.sealName(name);
            const body = new FormData();
            body.append('_token', config.token);
            body.append('enc_name', encName);
            if (parentId != null) body.append('parent_id', parentId);

            const r = await fetch(config.foldersUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            const id = (await r.json()).id;
            map.set(dir, id);
            existing.push({ id, parent_id: parentId ?? null, name: '', enc_name: encName });
        }

        return map;
    },

    toggleAll(event) {
        this.selected = event.target.checked ? [...this.allIds] : [];
        this.selectedFolders = event.target.checked ? [...this.folderIds] : [];
    },

    /** Open the move modal for a single file, or with no argument the selection. */
    openMove(id = null) {
        this.moveIds = id === null ? [...this.selected] : [id];
        this.moveFolderIds = id === null ? [...this.selectedFolders] : [];
        this.target = '';
        this.moveOpen = true;
    },

    /** Open the move modal for a single folder. */
    openMoveFolder(id) {
        this.moveIds = [];
        this.moveFolderIds = [id];
        this.target = '';
        this.moveOpen = true;
    },

    openBulkDelete() {
        if (this.selectionCount) {
            this.deleteOpen = true;
        }
    },

    startRename(id) {
        this.renaming = id;
        this.$nextTick(() => this.$refs['rename-' + id]?.focus());
    },

    // --- Encrypting existing files and folders (zero-knowledge, in place) ---

    async requireVault() {
        await this.$store.vault.boot();
        if (! this.$store.vault.configured || ! this.$store.vault.unlocked) {
            window.dispatchEvent(new CustomEvent('vault-panel'));
            return false;
        }
        return true;
    },

    async encryptOneFile(id, name, mime) {
        const bytes = await fetchCipher(`${config.fileBase}/${id}/download`);
        const sealed = Vault.encryptContent(bytes, { name, mime: mime || 'application/octet-stream' });
        const data = new FormData();
        data.append('_token', config.token);
        data.append('_method', 'PUT');
        data.append('file', sealed.blob, 'blob');
        data.append('enc_metadata', sealed.encMeta);
        data.append('enc_file_key', sealed.encFileKey);
        await fetch(`${config.fileBase}/${id}/encrypt`, {
            method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data,
        });
    },

    async encryptOneFolderName(id, name) {
        const data = new FormData();
        data.append('_token', config.token);
        data.append('_method', 'PUT');
        data.append('enc_name', Vault.sealName(name));
        await fetch(`${config.folderBase}/${id}`, {
            method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data,
        });
    },

    // Build the work list for a folder subtree: every plaintext file, then every
    // plaintext folder name (files first so a folder is renamed once done).
    async folderWork(id) {
        const tree = await (await fetch(`${config.folderBase}/${id}/descendants`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })).json();
        return [
            ...tree.files.map((f) => ({ kind: 'file', id: f.id, name: f.name, mime: f.mime_type })),
            ...tree.folders.map((d) => ({ kind: 'folder', id: d.id, name: d.name })),
        ];
    },

    // Run an encryption work list with a visible progress bar, then reload.
    async runEncryption(items) {
        if (! items.length) {
            return;
        }
        this.enc = { active: true, done: 0, total: items.length };
        for (const it of items) {
            if (it.kind === 'file') {
                await this.encryptOneFile(it.id, it.name, it.mime);
            } else {
                await this.encryptOneFolderName(it.id, it.name);
            }
            this.enc.done++;
        }
        window.location.reload();
    },

    async encryptFolder(id) {
        if (! await this.requireVault()) return;
        await this.runEncryption(await this.folderWork(id));
    },

    async bulkEncrypt() {
        if (! await this.requireVault()) return;
        const work = [];
        for (const id of this.selected) {
            const row = document.querySelector(`tr[data-kind="file"] input[value="${id}"]`)?.closest('tr');
            if (row && row.dataset.fname !== undefined) {
                work.push({ kind: 'file', id, name: row.dataset.fname, mime: row.dataset.fmime });
            }
        }
        for (const id of this.selectedFolders) {
            work.push(...await this.folderWork(id));
        }
        await this.runEncryption(work);
    },

    // Download the selection (files + folders, recursively) as a single zip,
    // built entirely in the browser so encrypted files come out decrypted and
    // no plaintext ever reaches the server.
    async bulkDownload() {
        if (! this.selectionCount) {
            return;
        }
        await this.$store.vault.boot();

        // Manifest: every file in scope with the fields to build paths + decrypt.
        const body = new FormData();
        body.append('_token', config.token);
        this.selected.forEach((id) => body.append('file_ids[]', id));
        this.selectedFolders.forEach((id) => body.append('folder_ids[]', id));

        let files;
        try {
            files = (await (await fetch(config.manifestUrl, {
                method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body,
            })).json()).files;
        } catch (e) {
            return;
        }
        if (! files || ! files.length) {
            return;
        }

        if (files.some((f) => f.is_encrypted) && ! this.$store.vault.unlocked) {
            window.dispatchEvent(new CustomEvent('vault-panel'));
            return;
        }

        // Folder names, to rebuild each file's relative path (decrypt as needed).
        let folders = [];
        try {
            folders = await (await fetch(config.foldersListUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })).json();
        } catch (e) { /* no folders: files land at the root */ }
        const fmap = new Map(folders.map((f) => [f.id, f]));
        const folderName = (f) => {
            if (f.enc_name) {
                try { return Vault.decryptFileMeta(f.enc_name).name; } catch (e) { return 'folder'; }
            }
            return f.name || 'folder';
        };
        const folderPath = (id) => {
            const parts = [];
            let cur = id;
            while (cur != null && fmap.has(cur)) {
                const f = fmap.get(cur);
                parts.unshift(folderName(f));
                cur = f.parent_id;
            }
            return parts;
        };

        const total = files.reduce((n, f) => n + (f.size || 0), 0);
        if (total > 2 * 1024 * 1024 * 1024 && ! window.confirm(config.largeZipConfirm)) {
            return;
        }

        const zip = new JSZip();
        const used = new Set();
        this.dl = { active: true, done: 0, total: files.length };
        for (const f of files) {
            try {
                const fileName = f.is_encrypted
                    ? (Vault.decryptFileMeta(f.enc_metadata).name || `file-${f.id}`)
                    : (f.name || `file-${f.id}`);
                let path = [...folderPath(f.folder_id), fileName].join('/');
                let candidate = path;
                let i = 1;
                while (used.has(candidate)) {
                    const dot = path.lastIndexOf('.');
                    candidate = dot > 0 ? `${path.slice(0, dot)} (${++i})${path.slice(dot)}` : `${path} (${++i})`;
                }
                used.add(candidate);

                const bytes = await fetchCipher(`${config.fileBase}/${f.id}/download`);
                zip.file(candidate, f.is_encrypted ? Vault.decryptFile(bytes, f.enc_file_key) : bytes);
            } catch (e) { /* skip a file that cannot be fetched/decrypted */ }
            this.dl.done++;
        }

        const blob = await zip.generateAsync({ type: 'blob' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'files.zip';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 10000);
        this.dl.active = false;
    },

    // Bulk bar rename is only shown for a single selection.
    bulkRename() {
        if (this.selected.length === 1) {
            this.startRename(this.selected[0]);
        } else if (this.selectedFolders.length === 1) {
            window.dispatchEvent(new CustomEvent('rename-folder', { detail: this.selectedFolders[0] }));
        }
    },
}));

/**
 * Gallery uploader: accepts dropped/selected images and uploads them one at a
 * time via XMLHttpRequest, exposing a per-file progress list (Immich-style).
 * Reloads the page when the batch finishes so the timeline shows the new photos.
 *
 * @param {string} url       The gallery store endpoint.
 * @param {string} token     CSRF token.
 * @param {string} feedUrl   The infinite-scroll feed endpoint.
 * @param {boolean} hasMore  Whether a second page exists.
 */
Alpine.data('gallery', (url, token, feedUrl = '', hasMore = false, mapZoom = 13, monthsUrl = '', reverseUrl = '') => ({
    dragging: false,
    queue: [],
    selected: [],
    active: 0,
    maxConcurrent: 3,
    summary: null,
    lastSelected: null,
    maxSelection: 1000,
    capNotice: false,

    // Infinite scroll.
    page: 1,
    hasMore,
    loading: false,

    // Timeline scrubber.
    months: [],

    // Viewer.
    viewerOpen: false,
    current: {},
    list: [],
    index: 0,
    editing: false,
    motionPlaying: false,
    showDetails: false,
    miniMap: null,
    placeTimer: null,
    mapZoom,

    initGallery() {
        // Show the drop overlay only while files are dragged over the window.
        let depth = 0;
        window.addEventListener('dragenter', (e) => {
            if (e.dataTransfer?.types?.includes('Files')) { depth++; this.dragging = true; }
        });
        window.addEventListener('dragleave', () => { depth = Math.max(0, depth - 1); if (! depth) this.dragging = false; });
        window.addEventListener('drop', () => { depth = 0; this.dragging = false; });

        // Tear the mini-map down when the viewer closes so it re-initialises
        // cleanly for the next photo, and stop any playing video.
        this.$watch('viewerOpen', (open) => {
            if (! open) {
                this.destroyMiniMap();
                document.querySelectorAll('video').forEach((v) => v.pause());
            }
        });

        // Live-update the mini-map and, while editing, the reverse-geocoded place
        // as the coordinates change.
        this.$watch('current.lat', () => { if (this.viewerOpen) { this.renderMiniMap(); this.queuePlaceRefresh(); } });
        this.$watch('current.lng', () => { if (this.viewerOpen) { this.renderMiniMap(); this.queuePlaceRefresh(); } });

        // Apply a location chosen in the map picker to the photo being edited.
        window.addEventListener('location-picked', (e) => {
            if (e.detail?.context === 'single') {
                this.current.lat = e.detail.lat;
                this.current.lng = e.detail.lng;
            }
        });

        this.loadMonths();
    },

    pick(event) {
        this.enqueue(event.target.files);
        event.target.value = '';
    },

    drop(event) {
        this.dragging = false;
        this.enqueue(event.dataTransfer.files);
    },

    /* ---- Infinite scroll ---- */

    async loadMore() {
        if (this.loading || ! this.hasMore) {
            return;
        }
        this.loading = true;
        const next = this.page + 1;

        try {
            const sep = feedUrl.includes('?') ? '&' : '?';
            const r = await fetch(`${feedUrl}${sep}page=${next}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await r.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const target = this.$refs.timeline;
            doc.querySelectorAll('section[data-day]').forEach((section) => {
                const day = section.dataset.day;
                const existing = target.querySelector(`section[data-day="${day}"] [data-day-grid]`);
                if (existing) {
                    section.querySelectorAll('[data-day-grid] > *').forEach((tile) => existing.appendChild(tile));
                } else {
                    target.appendChild(section);
                }
            });
            this.page = next;
            this.hasMore = html.includes('data-has-more="1"');
        } catch (e) { /* leave hasMore as-is so a later scroll retries */ } finally {
            this.loading = false;
        }

        // Fast scrolling can blow past the sentinel; keep loading while it is
        // still within reach of the viewport.
        this.$nextTick(() => this.fillViewport());
    },

    fillViewport() {
        const el = this.$refs.sentinel;
        if (! el || ! this.hasMore || this.loading) {
            return;
        }
        if (el.getBoundingClientRect().top < window.innerHeight + 800) {
            this.loadMore();
        }
    },

    /* ---- Timeline scrubber ---- */

    loadMonths() {
        if (! monthsUrl) {
            return;
        }
        fetch(monthsUrl, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then(({ months }) => { this.months = months; })
            .catch(() => {});
    },

    // Scroll to a month, loading further pages until its section is present.
    async scrollToMonth(ym) {
        for (let guard = 0; guard < 80; guard++) {
            const el = this.$refs.timeline.querySelector(`section[data-month="${ym}"]`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            if (! this.hasMore) {
                return;
            }
            await this.loadMore();
        }
    },

    /* ---- Viewer ---- */

    openViewer(el) {
        this.list = [...document.querySelectorAll('[data-photo]')];
        this.index = this.list.indexOf(el);
        this.setCurrent();
        this.viewerOpen = true;
    },

    setCurrent() {
        const d = this.list[this.index]?.dataset;
        this.current = d ? { ...d } : {};
        this.editing = false;
        this.motionPlaying = false;
        this.showDetails = false;
        this.$nextTick(() => this.renderMiniMap());
    },

    renderMiniMap() {
        this.destroyMiniMap();

        const el = this.$refs.miniMap;
        const lat = parseFloat(this.current.lat);
        const lng = parseFloat(this.current.lng);
        if (! el || ! Number.isFinite(lat) || ! Number.isFinite(lng)) {
            return;
        }

        this.miniMap = L.map(el, {
            attributionControl: false,
            zoomControl: false,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
        }).setView([lat, lng], this.mapZoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.miniMap);

        const icon = L.divIcon({
            className: '',
            html: '<div class="h-3 w-3 rounded-full bg-red-500 ring-2 ring-white shadow"></div>',
            iconSize: [12, 12],
            iconAnchor: [6, 6],
        });
        L.marker([lat, lng], { icon }).addTo(this.miniMap);

        this.$nextTick(() => this.miniMap && this.miniMap.invalidateSize());
    },

    destroyMiniMap() {
        if (this.miniMap) {
            this.miniMap.remove();
            this.miniMap = null;
        }
    },

    // While editing coordinates, reverse-geocode the new spot (debounced) and
    // update the shown place so the user sees the new location immediately.
    queuePlaceRefresh() {
        if (! this.editing || ! reverseUrl) {
            return;
        }
        const lat = parseFloat(this.current.lat);
        const lng = parseFloat(this.current.lng);
        if (! Number.isFinite(lat) || ! Number.isFinite(lng)) {
            this.current.placeLines = '';
            this.current.place = '';
            return;
        }
        clearTimeout(this.placeTimer);
        this.placeTimer = setTimeout(async () => {
            try {
                const r = await fetch(`${reverseUrl}?lat=${lat}&lon=${lng}`, { headers: { Accept: 'application/json' } });
                if (! r.ok) return;
                const data = await r.json();
                this.current.place = data.place || '';
                this.current.placeLines = (data.lines || []).join('|');
            } catch (e) { /* leave the previous place shown */ }
        }, 600);
    },

    next() {
        if (this.index < this.list.length - 1) { this.index++; this.setCurrent(); }
    },

    prev() {
        if (this.index > 0) { this.index--; this.setCurrent(); }
    },

    enqueue(fileList) {
        this.summary = null;
        // Only image types the browser can actually render get a live preview;
        // HEIC and friends fall back to an icon instead of a broken image.
        const previewable = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
        for (const file of fileList) {
            const isVideo = file.type.startsWith('video/');
            if (! isVideo && ! file.type.startsWith('image/')) {
                continue;
            }
            this.queue.push({
                name: file.name,
                file,
                isVideo,
                preview: previewable.includes(file.type) ? URL.createObjectURL(file) : null,
                progress: 0,
                state: 'pending', // pending | uploading | done | duplicate | skipped | error
                reason: '',
            });
        }
        this.pump();
    },

    // Keep up to maxConcurrent uploads in flight; report a summary when the
    // whole batch settles.
    pump() {
        while (this.active < this.maxConcurrent) {
            const item = this.queue.find((entry) => entry.state === 'pending');
            if (! item) {
                break;
            }
            this.upload(item);
        }

        if (this.active === 0 && ! this.queue.some((entry) => entry.state === 'pending')) {
            this.onComplete();
        }
    },

    upload(item) {
        item.state = 'uploading';
        this.active++;

        const data = new FormData();
        data.append('photo', item.file);
        data.append('_token', token);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                item.progress = Math.round((e.loaded / e.total) * 100);
            }
        };
        const settle = () => { this.active--; this.pump(); };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                item.progress = 100;
                let body = {};
                try { body = JSON.parse(xhr.responseText); } catch (e) { /* plain response */ }
                item.state = body.skipped ? 'skipped' : (body.duplicate ? 'duplicate' : 'done');
                item.reason = body.reason || '';
            } else {
                item.state = 'error';
            }
            settle();
        };
        xhr.onerror = () => { item.state = 'error'; settle(); };
        xhr.send(data);
    },

    onComplete() {
        const created = this.queue.filter((e) => e.state === 'done').length;
        const duplicates = this.queue.filter((e) => e.state === 'duplicate').map((e) => e.name);
        const skipped = this.queue.filter((e) => e.state === 'skipped').map((e) => e.name);
        const errored = this.queue.filter((e) => e.state === 'error').map((e) => e.name);

        // Any new photos → refresh to show them. Only keep the tray open when
        // nothing was added (everything was a duplicate, skipped or failed) so
        // that list stays visible to review.
        if (created > 0) {
            window.location.reload();
            return;
        }

        this.summary = { created, duplicates, skipped, errored };
    },

    dismissUploads() {
        this.queue.forEach((e) => e.preview && URL.revokeObjectURL(e.preview));
        this.queue = [];
        this.summary = null;
        if (this.$refs.timeline) {
            window.location.reload();
        }
    },

    toggleAll(event) {
        this.applySelection(event.target.checked ? this.allIds() : []);
    },

    allIds() {
        return [...document.querySelectorAll('[data-photo-id]')].map((el) => Number(el.dataset.photoId));
    },

    /* ---- Selection (click, shift-range, select-all, per-day) ---- */

    selectableIds() {
        return [...document.querySelectorAll('[data-select]')].map((cb) => Number(cb.value));
    },

    // Photo ids under a given day section.
    dayIds(day) {
        return [...document.querySelectorAll(`section[data-day="${day}"] [data-select]`)].map((cb) => Number(cb.value));
    },

    dayFullySelected(day) {
        const ids = this.dayIds(day);
        return ids.length > 0 && ids.every((id) => this.selected.includes(id));
    },

    // Select or clear all photos of one day at once.
    toggleDay(day) {
        const ids = this.dayIds(day);
        if (this.dayFullySelected(day)) {
            const remove = new Set(ids);
            this.selected = this.selected.filter((id) => ! remove.has(id));
        } else {
            this.applySelection([...this.selected, ...ids]);
        }
    },

    // Cap every multi-selection at maxSelection; warn and keep the first N.
    applySelection(ids) {
        const unique = [...new Set(ids)];
        if (unique.length > this.maxSelection) {
            this.selected = unique.slice(0, this.maxSelection);
            this.notifyCap();
        } else {
            this.selected = unique;
        }
    },

    notifyCap() {
        this.capNotice = true;
        clearTimeout(this._capTimer);
        this._capTimer = setTimeout(() => { this.capNotice = false; }, 5000);
    },

    toggleSelect(event, id) {
        // Shift-click extends the selection from the last clicked tile to here.
        if (event.shiftKey && this.lastSelected !== null) {
            const ids = this.selectableIds();
            const from = ids.indexOf(this.lastSelected);
            const to = ids.indexOf(id);
            if (from !== -1 && to !== -1) {
                const [a, b] = from < to ? [from, to] : [to, from];
                const set = new Set(this.selected);
                for (let i = a; i <= b; i++) {
                    set.add(ids[i]);
                }
                this.applySelection([...set]);
                this.lastSelected = id;
                return;
            }
        }

        if (this.selected.includes(id)) {
            this.selected = this.selected.filter((x) => x !== id);
        } else if (this.selected.length >= this.maxSelection) {
            this.notifyCap(); // at the cap: refuse to add more
        } else {
            this.selected = [...this.selected, id];
        }
        this.lastSelected = id;
    },

    selectAllVisible() {
        this.applySelection(this.selectableIds());
    },

    onKeydown(event) {
        if ((event.metaKey || event.ctrlKey) && (event.key === 'a' || event.key === 'A')) {
            const tag = document.activeElement?.tagName;
            if (this.viewerOpen || tag === 'INPUT' || tag === 'TEXTAREA') {
                return;
            }
            event.preventDefault();
            this.selectAllVisible();
        }
    },
}));

/**
 * Photo map: loads geotagged photos and plots them on an OpenStreetMap map with
 * marker clustering (counts, scroll/pinch zoom). Each marker is the photo's
 * thumbnail; clicking opens it. Clusters expand on click / zoom.
 *
 * @param {string} pointsUrl  Endpoint returning { points: [...] }.
 */
Alpine.data('photoMap', (pointsUrl, mapZoom = 13) => ({
    lightbox: null,

    init() {
        const map = L.map(this.$refs.map, { scrollWheelZoom: true }).setView([20, 0], 2);
        this.mapZoom = mapZoom;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        const clusters = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });

        fetch(pointsUrl, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then(({ points }) => {
                if (! points.length) {
                    return;
                }
                for (const p of points) {
                    const icon = L.divIcon({
                        className: '',
                        html: `<img src="${p.thumb}" class="h-12 w-12 rounded-md object-cover shadow ring-2 ring-white">`,
                        iconSize: [48, 48],
                        iconAnchor: [24, 24],
                    });
                    const marker = L.marker([p.lat, p.lng], { icon });
                    marker.on('click', () => { this.lightbox = { src: p.medium, download: p.original }; });
                    clusters.addLayer(marker);
                }
                map.addLayer(clusters);
                map.fitBounds(clusters.getBounds().pad(0.2), { maxZoom: this.mapZoom });
            });
    },
}));

/**
 * Code editor (CodeMirror 6): line numbers, in-file search (Ctrl/Cmd+F),
 * syntax highlighting with detection by filename and a manual language picker.
 * The document is synced into a hidden input on submit.
 */
Alpine.data('codeEditor', (initial = '', filename = '') => ({
    view: null,
    langComp: new Compartment(),
    language: '',
    languageOptions: languages.map((l) => l.name).sort((a, b) => a.localeCompare(b)),

    init() {
        this.view = new EditorView({
            parent: this.$refs.editor,
            state: EditorState.create({
                doc: initial,
                extensions: [
                    basicSetup,
                    this.langComp.of([]),
                    EditorView.theme({ '&': { height: '60vh' }, '.cm-scroller': { overflow: 'auto' } }),
                ],
            }),
        });

        const detected = filename ? LanguageDescription.matchFilename(languages, filename) : null;
        if (detected) {
            this.applyLanguage(detected);
        }
    },

    onLanguageChange() {
        const desc = languages.find((l) => l.name === this.language);
        desc ? this.applyLanguage(desc) : this.view.dispatch({ effects: this.langComp.reconfigure([]) });
    },

    applyLanguage(desc) {
        this.language = desc.name;
        desc.load().then((support) => this.view.dispatch({ effects: this.langComp.reconfigure(support) }));
    },

    sync() {
        this.$refs.content.value = this.view.state.doc.toString();
    },
}));

/**
 * Editor for an encrypted file: fetches the ciphertext, decrypts it to text in
 * the browser, edits it in CodeMirror, and on save re-encrypts and PUTs the new
 * ciphertext. The server never sees the plaintext. Binary content is refused.
 */
Alpine.data('encCodeEditor', (downloadUrl, saveUrl, token, encMeta, encFileKey) => ({
    state: 'loading', // loading | locked | binary | ready | saving | error
    error: '',
    view: null,
    langComp: new Compartment(),
    language: '',
    languageOptions: languages.map((l) => l.name).sort((a, b) => a.localeCompare(b)),
    meta: null,

    async init() {
        await this.$store.vault.boot();
        if (! this.$store.vault.unlocked) {
            this.state = 'locked';
            return;
        }
        try {
            this.meta = Vault.decryptFileMeta(encMeta);
            const cipher = await fetchCipher(downloadUrl);
            const plain = Vault.decryptFile(cipher, encFileKey);
            let text;
            try {
                text = new TextDecoder('utf-8', { fatal: true }).decode(plain);
            } catch (e) {
                this.state = 'binary';
                return;
            }
            this.state = 'ready';
            this.$nextTick(() => this.mount(text));
        } catch (e) {
            this.state = 'error';
        }
    },

    mount(text) {
        this.view = new EditorView({
            parent: this.$refs.editor,
            state: EditorState.create({
                doc: text,
                extensions: [
                    basicSetup,
                    this.langComp.of([]),
                    EditorView.theme({ '&': { height: '60vh' }, '.cm-scroller': { overflow: 'auto' } }),
                ],
            }),
        });
        const detected = this.meta?.name ? LanguageDescription.matchFilename(languages, this.meta.name) : null;
        if (detected) {
            this.applyLanguage(detected);
        }
    },

    onLanguageChange() {
        const desc = languages.find((l) => l.name === this.language);
        desc ? this.applyLanguage(desc) : this.view.dispatch({ effects: this.langComp.reconfigure([]) });
    },

    applyLanguage(desc) {
        this.language = desc.name;
        desc.load().then((support) => this.view.dispatch({ effects: this.langComp.reconfigure(support) }));
    },

    async save() {
        if (! this.view) {
            return;
        }
        this.state = 'saving';
        this.error = '';
        const bytes = new TextEncoder().encode(this.view.state.doc.toString());
        const sealed = Vault.encryptContent(bytes, { name: this.meta.name, mime: this.meta.mime });

        const data = new FormData();
        data.append('_token', token);
        data.append('_method', 'PUT');
        data.append('encrypted', '1');
        data.append('file', sealed.blob, 'blob');
        data.append('enc_metadata', sealed.encMeta);
        data.append('enc_file_key', sealed.encFileKey);

        try {
            const r = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                body: data,
            });
            if (! r.ok) {
                throw new Error('save failed');
            }
            window.location.reload();
        } catch (e) {
            this.state = 'ready';
            this.error = 'save';
        }
    },
}));

/**
 * Interactive location picker: an OSM map where you drop a pin by clicking, or
 * search an address (forward geocode) and pick a result. Opened via an
 * `open-location-picker` window event ({context, lat, lng}); on apply it emits
 * `location-picked` ({context, lat, lng}) for the opener to consume.
 */
Alpine.data('locationPicker', (searchUrl) => ({
    open: false,
    context: null,
    lat: null,
    lng: null,
    query: '',
    results: [],
    searching: false,
    map: null,
    marker: null,
    searchTimer: null,

    initPicker() {
        window.addEventListener('open-location-picker', (e) => {
            this.context = e.detail?.context ?? null;
            const lat = parseFloat(e.detail?.lat);
            const lng = parseFloat(e.detail?.lng);
            this.lat = Number.isFinite(lat) ? lat : null;
            this.lng = Number.isFinite(lng) ? lng : null;
            this.query = '';
            this.results = [];
            this.open = true;
            this.$nextTick(() => this.mountMap());
        });
    },

    mountMap() {
        if (this.map) {
            this.map.remove();
            this.map = null;
            this.marker = null;
        }
        const hasPin = this.lat != null && this.lng != null;
        this.map = L.map(this.$refs.pickerMap).setView(hasPin ? [this.lat, this.lng] : [20, 0], hasPin ? 13 : 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);
        if (hasPin) {
            this.setMarker(this.lat, this.lng, false);
        }
        this.map.on('click', (e) => this.setMarker(e.latlng.lat, e.latlng.lng, false));
        this.$nextTick(() => this.map && this.map.invalidateSize());
    },

    setMarker(lat, lng, recenter = true) {
        this.lat = lat;
        this.lng = lng;
        if (this.marker) {
            this.marker.setLatLng([lat, lng]);
        } else {
            this.marker = L.marker([lat, lng]).addTo(this.map);
        }
        if (recenter) {
            this.map.setView([lat, lng], Math.max(this.map.getZoom(), 13));
        }
    },

    queueSearch() {
        clearTimeout(this.searchTimer);
        if (! this.query.trim()) {
            this.results = [];
            return;
        }
        this.searchTimer = setTimeout(() => this.runSearch(), 500);
    },

    async runSearch() {
        this.searching = true;
        try {
            const r = await fetch(`${searchUrl}?q=${encodeURIComponent(this.query.trim())}`, { headers: { Accept: 'application/json' } });
            this.results = (await r.json()).results || [];
        } catch (e) {
            this.results = [];
        } finally {
            this.searching = false;
        }
    },

    choose(res) {
        this.results = [];
        this.query = res.display;
        this.setMarker(res.lat, res.lon, true);
    },

    apply() {
        if (this.lat == null || this.lng == null) {
            return;
        }
        window.dispatchEvent(new CustomEvent('location-picked', {
            detail: { context: this.context, lat: this.lat, lng: this.lng },
        }));
        this.close();
    },

    close() {
        this.open = false;
        this.results = [];
        if (this.map) {
            this.map.remove();
            this.map = null;
            this.marker = null;
        }
    },
}));

/**
 * Zero-knowledge encryption vault store. Wraps the crypto module so views can
 * check state and drive setup / unlock / recover / lock.
 */
Alpine.store('vault', {
    configured: false,
    unlocked: false,
    busy: false,
    ready: null,

    // Idempotent: repeated calls return the same in-flight/settled promise, so
    // components can `await boot()` to be sure the cached key was restored.
    boot() {
        if (! this.ready) {
            this.ready = (async () => {
                await Vault.boot();
                this.unlocked = Vault.unlocked();
                try {
                    this.configured = (await Vault.status()).configured;
                } catch (e) { /* offline: leave defaults */ }
            })();
        }
        return this.ready;
    },

    async setup(passphrase) {
        const code = await Vault.setup(passphrase);
        this.configured = true;
        this.unlocked = true;
        this.announceUnlocked();
        return code;
    },

    async unlock(passphrase) {
        await Vault.unlock(passphrase);
        this.unlocked = true;
        this.announceUnlocked();
    },

    async recover(code) {
        await Vault.recover(code);
        this.unlocked = true;
        this.announceUnlocked();
    },

    async changePassphrase(currentPass, newPass) {
        await Vault.changePassphrase(currentPass, newPass);
        this.unlocked = true;
        this.announceUnlocked();
    },

    lock() {
        Vault.lock();
        this.unlocked = false;
    },

    // Let the encrypted-name/type/preview components on the page re-decrypt
    // themselves once the key is available, without a manual page reload.
    announceUnlocked() {
        window.dispatchEvent(new CustomEvent('vault-unlocked'));
    },
});

/* ---- Encrypted-file read paths (zero-knowledge, browser-side) ---- */

// Trigger a browser download of decrypted bytes with the real name/mime.
function saveDecrypted(bytes, name, mime) {
    const url = URL.createObjectURL(new Blob([bytes], { type: mime || 'application/octet-stream' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = name || 'download';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10000);
}

// Fetch a file's raw (ciphertext) bytes from the download route.
async function fetchCipher(url) {
    const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (! r.ok) {
        throw new Error('fetch failed');
    }
    return new Uint8Array(await r.arrayBuffer());
}

// Decrypt an encrypted file and save it under its real name. If the vault is
// locked, prompt to unlock instead.
window.vaultDownload = async (url, encMeta, encFileKey) => {
    await Alpine.store('vault').boot();
    if (! Alpine.store('vault').unlocked) {
        window.dispatchEvent(new CustomEvent('vault-panel'));
        return;
    }
    const meta = Vault.decryptFileMeta(encMeta);
    const cipher = await fetchCipher(url);
    saveDecrypted(Vault.decryptFile(cipher, encFileKey), meta.name, meta.mime);
};

// Encrypt an existing plaintext file in place: fetch its bytes, seal them with
// the vault key, and PUT the ciphertext + wrapped key/metadata. Prompts to set
// up / unlock the vault if needed.
window.vaultEncrypt = async (downloadUrl, encryptUrl, name, mime, token) => {
    await Alpine.store('vault').boot();
    if (! Alpine.store('vault').configured || ! Alpine.store('vault').unlocked) {
        window.dispatchEvent(new CustomEvent('vault-panel'));
        return;
    }
    const bytes = await fetchCipher(downloadUrl); // plaintext bytes for a plaintext file
    const sealed = Vault.encryptContent(bytes, { name, mime });

    const data = new FormData();
    data.append('_token', token);
    data.append('_method', 'PUT');
    data.append('file', sealed.blob, 'blob');
    data.append('enc_metadata', sealed.encMeta);
    data.append('enc_file_key', sealed.encFileKey);

    const r = await fetch(encryptUrl, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        body: data,
    });
    if (r.ok) {
        window.location.reload();
    }
};

// Intercept a folder create/rename form: when a vault is set up, seal the name
// into enc_name and drop the plaintext name before submitting. Plaintext when
// no vault exists; prompts to unlock when the vault is locked.
window.encryptFolderSubmit = async (e) => {
    e.preventDefault();
    const form = e.target;
    const store = Alpine.store('vault');
    await store.boot();
    if (! store.configured) {
        form.submit();
        return;
    }
    if (! store.unlocked) {
        window.dispatchEvent(new CustomEvent('vault-panel'));
        return;
    }
    const nameInput = form.querySelector('input[name="name"]');
    const enc = Vault.sealName(nameInput.value);
    nameInput.removeAttribute('name'); // send only the ciphertext
    let hidden = form.querySelector('input[name="enc_name"]');
    if (! hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'enc_name';
        form.appendChild(hidden);
    }
    hidden.value = enc;
    form.submit();
};

// Row state for an encrypted folder: decrypts its name for the link and the
// rename input, alongside the usual rename/menu toggles.
Alpine.data('encFolderRow', (encName, lockedLabel = '…') => ({
    rename: false,
    menu: false,
    tagsOpen: false,
    folderName: lockedLabel,
    async init() {
        await this.$store.vault.boot();
        this.reveal();
        window.addEventListener('vault-unlocked', () => this.reveal());
    },
    reveal() {
        if (this.$store.vault.unlocked) {
            try {
                this.folderName = Vault.decryptFileMeta(encName).name;
            } catch (e) { /* leave the placeholder */ }
        }
    },
}));

// Client-side name sort for the file browser. The server cannot order encrypted
// rows (their plaintext name is empty), so when the active sort is "name" we
// reorder the visible rows by their real (decrypted) name once the vault is
// unlocked — folders first, then files, honouring the sort direction. Only the
// current page is reordered; cross-page ordering stays server-driven.
Alpine.data('folderSort', (sort, dir) => ({
    async run() {
        if (sort !== 'name') {
            return;
        }
        window.addEventListener('vault-unlocked', () => this.reorder());
        await this.$store.vault.boot();
        await this.reorder();
    },
    async reorder() {
        const tbody = this.$el;
        const rows = Array.from(tbody.querySelectorAll('tr[data-kind]'));
        const unlocked = this.$store.vault.unlocked;

        const nameOf = (tr) => {
            let n = tr.dataset.name || '';
            if (! n && tr.dataset.enc && unlocked) {
                try {
                    n = Vault.decryptFileMeta(tr.dataset.enc).name || '';
                } catch (e) { /* leave empty */ }
            }
            return n;
        };
        const factor = dir === 'desc' ? -1 : 1;
        const kind = (tr) => (tr.dataset.kind === 'folder' ? 0 : 1);

        rows.sort((a, b) => (kind(a) - kind(b))
            || factor * nameOf(a).localeCompare(nameOf(b), undefined, { sensitivity: 'base', numeric: true }));

        // Re-append in the new order (moves existing nodes, preserving state).
        rows.forEach((tr) => tbody.appendChild(tr));
    },
}));

// Decrypt the <option> labels of a folder <select> that carry data-enc.
Alpine.data('encOptions', () => ({
    async init() {
        await this.$store.vault.boot();
        this.reveal();
        window.addEventListener('vault-unlocked', () => this.reveal());
    },
    reveal() {
        if (! this.$store.vault.unlocked) {
            return;
        }
        this.$el.querySelectorAll('option[data-enc]').forEach((opt) => {
            try {
                opt.textContent = Vault.decryptFileMeta(opt.dataset.enc).name;
            } catch (e) { /* leave the placeholder */ }
        });
    },
}));

// Name label for an encrypted file (list row / detail heading): decrypts the
// real name once the vault is unlocked, otherwise shows a lock placeholder.
Alpine.data('encName', (encMeta, lockedLabel = '…') => ({
    label: lockedLabel,
    async init() {
        await this.$store.vault.boot();
        this.reveal();
        window.addEventListener('vault-unlocked', () => this.reveal());
    },
    reveal() {
        if (this.$store.vault.unlocked) {
            try {
                this.label = Vault.decryptFileMeta(encMeta).name;
            } catch (e) { /* leave the placeholder */ }
        }
    },
}));

// Classify a mime into one of the FileType categories (mirrors the PHP
// FileType::fromMime), so an encrypted file can show its real type once the
// browser decrypts its metadata.
function classifyMime(mime) {
    mime = (mime || '').toLowerCase();
    if (mime.startsWith('image/')) return 'IMAGE';
    if (mime === 'application/pdf') return 'PDF';
    if ([
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv', 'application/csv',
    ].includes(mime)) return 'SPREADSHEET';
    if ([
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'application/rtf', 'text/plain', 'text/markdown',
    ].includes(mime)) return 'DOCUMENT';
    if ([
        'application/zip', 'application/x-tar', 'application/gzip',
        'application/x-7z-compressed', 'application/x-rar-compressed', 'application/vnd.rar',
    ].includes(mime)) return 'ARCHIVE';
    return 'OTHER';
}

// Type-column label for an encrypted file: decrypts the mime and maps it to the
// localized category label; shows the "encrypted" placeholder until unlocked.
Alpine.data('encType', (encMeta, labels) => ({
    typeLabel: labels.ENCRYPTED ?? '',
    async init() {
        await this.$store.vault.boot();
        this.reveal();
        window.addEventListener('vault-unlocked', () => this.reveal());
    },
    reveal() {
        if (this.$store.vault.unlocked) {
            try {
                const mime = Vault.decryptFileMeta(encMeta).mime;
                this.typeLabel = labels[classifyMime(mime)] ?? mime;
            } catch (e) { /* leave the placeholder */ }
        }
    },
}));

// Detail-page type label for an encrypted file: "Category · mime".
Alpine.data('encMime', (encMeta, labels) => ({
    label: labels.ENCRYPTED ?? '',
    async init() {
        await this.$store.vault.boot();
        this.reveal();
        window.addEventListener('vault-unlocked', () => this.reveal());
    },
    reveal() {
        if (this.$store.vault.unlocked) {
            try {
                const mime = Vault.decryptFileMeta(encMeta).mime;
                this.label = `${labels[classifyMime(mime)] ?? mime} · ${mime}`;
            } catch (e) { /* leave the placeholder */ }
        }
    },
}));

// Inline preview for an encrypted file: fetches + decrypts, then renders an
// image or PDF via an object URL, or offers a download for anything else.
Alpine.data('encPreview', (url, encMeta, encFileKey) => ({
    state: 'loading', // loading | image | pdf | none | locked | error
    src: '',
    name: '',
    async init() {
        await this.$store.vault.boot();
        await this.reveal();
        window.addEventListener('vault-unlocked', () => this.reveal());
    },
    async reveal() {
        if (! this.$store.vault.unlocked) {
            this.state = 'locked';
            return;
        }
        this.state = 'loading';
        try {
            const meta = Vault.decryptFileMeta(encMeta);
            this.name = meta.name;
            const cipher = await fetchCipher(url);
            const plain = Vault.decryptFile(cipher, encFileKey);
            const mime = meta.mime || 'application/octet-stream';
            if (mime.startsWith('image/')) {
                this.src = URL.createObjectURL(new Blob([plain], { type: mime }));
                this.state = 'image';
            } else if (mime === 'application/pdf') {
                this.src = URL.createObjectURL(new Blob([plain], { type: mime }));
                this.state = 'pdf';
            } else {
                this.state = 'none';
            }
        } catch (e) {
            this.state = 'error';
        }
    },
    download() {
        window.vaultDownload(url, encMeta, encFileKey);
    },
}));

Alpine.plugin(intersect);

window.Alpine = Alpine;

// Boot the vault store on every page: it restores a cached (unlocked) vault key
// from sessionStorage so encrypted files decrypt on the detail/edit pages too,
// not only on the files browser where the unlock panel lives.
document.addEventListener('alpine:init', () => Alpine.store('vault').boot());

Alpine.start();
