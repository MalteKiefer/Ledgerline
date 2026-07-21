// Files module (nestable ZK file browser). Extracted from app.js.
import { apiRequest, jsonHeaders, postForm, getJson } from '../shared/api';
import { Vault, VaultShareCrypto } from '../vault';
import { fetchDecrypt, fetchBlobBuffer, queueBlobDelete } from '../shared/blob-io';
import { collectSubtree } from './files-subtree';
import { padBlob, padmeSize } from '../shared/padme';
import { saveBlobAs, formatDate } from '../shared/dom';
import { fileCategory, CATEGORY_ICON, categoryTint, fileTypeLabel, FOLDER_TINT, formatBytes } from '../shared/file-categories';
import { normVec as _normVec, dotVec as _dotVec } from '../shared/vector-math';
import { ocrImage } from '../shared/ocr';
import { loadCodeMirror, cmModule } from '../shared/lazy-loaders';
import { bootStore } from '../shared/zk-module';

// Heroicon path for the folder chip glyph (24-outline folder).
const FOLDER_ICON_PATH = 'M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z';

// Files-only module state (fulltext + CLIP-embedding search caches + reconcile dedupe).
const fileText = {};
const fileEmb = {};
let _filesReconAt = 0;

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | locked | unconfigured | ready | error
    extractProgress: null, // {done,total} while text extraction runs, else null
    _extracting: false,
    _contentReady: 0,    // reactive tick: bumped as content decrypts so search re-runs
    _warming: false,
    _contentWarmed: false,
    // Points at the shared opaque store's file arrays once loaded; the tree is
    // plaintext inside the sealed blob, so mutations edit these in place.
    manifest: { v: 1, folders: [], files: [] },
    version: 0,
    cwd: null,
    query: '',
    _q: '',            // debounced search term the row filter actually uses
    _searchTimer: null,
    _semanticHits: null, // Set of image-file ids matching the query via CLIP, or null
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
    activeTag: '',
    view: 'files', // files | favorites | recent | trash
    newFolderName: '',
    newFolderModal: false,
    newFolderShared: false,
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
    dl: { active: false, done: 0, total: 0 },
    busy: 0, // in-flight file operations (sync/save/trash/delete/move/…); drives the spinner badge
    error: '',

    // ---- Shared folders (ZK cross-user, mirrors passwords.js sharedVaults) ----
    sharedFolders: [],      // [{id, vaultId, name, role, version}]
    sharedTree: {},          // {vaultId: {folders: [], files: []}}
    _folderKeys: {},         // vaultId → Uint8Array VK_folder (in-memory only, never persisted)
    _folderVersion: {},      // vaultId → optimistic-lock version
    pendingFolderInvites: [], // [{vault_id, member_id, role, wrapped_vault_key}]
    activeShared: null,      // vaultId currently browsed, or null for personal

    // ---- Shared-folder management state (mirrors passwords.js) ----
    shareFolderDialog: { open: false, vaultId: null, identifier: '', role: 'read', lookingUp: false, resolved: null, fingerprintStatus: null, sharing: false, notice: '' },
    managingFolderVaultId: null,       // vault id open in the folder-members panel
    managingFolderVaultMembers: [],    // [{id, user_id, role, status, recipient_fingerprint, public_key}]
    managingFolderVaultLoading: false,
    rotatingFolderKeys: false,

    // ---- Unified share dialog (People + Link, single entry point) ----
    unifiedShare: { open: false, row: null, isFolder: false, vaultId: null, tab: 'people' },

    // Track an async file operation so the UI can show a "working" spinner badge
    // for every mutation (the user gets feedback even for a slow permanent delete).
    _track(p) {
        this.busy++;
        return Promise.resolve(p).finally(() => { this.busy = Math.max(0, this.busy - 1); });
    },
    dragging: false,
    viewer: { open: false, kind: 'none', src: '', row: null, saving: false, saved: false },
    editorView: null,
    editorLang: '',
    langComp: null,
    languageOptions: [], // populated when the editor (CodeMirror) is loaded on first open

    async init() {
        window.addEventListener('paperless-sent', (e) => this.onPaperlessSent(e.detail));
        this.initDropzone();
        // Switching view clears any selection, so a stale pick doesn't keep the
        // bulk bar / select-all checkbox active.
        this.$watch('view', () => { this.selected = []; });
        // Zero-knowledge gate: the tree lives in the shared opaque store, which can
        // only be opened with an unlocked vault. load() waits for the vault + store.
        await this.load();
        this.$watch('$store.vault.unlocked', (on) => {
            if (on && this.state !== 'ready') this.load();
            if (! on) { this.state = 'locked'; window.LLStore.reset(); }
        });
        // Shared folders: watch state transitions to 'ready' (e.g. manual unlock).
        // Also call directly when Trusted-Device auto-unlock sets state before the
        // watcher is registered (mirrors the passwords.js v1.504.31 bugfix).
        this.$watch('state', (s) => { if (s === 'ready') this._loadSharedFolders(); });
        if (this.state === 'ready') this._loadSharedFolders();
    },

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
        if (this.state !== 'ready') return;
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
        await this.uploadItems(files);
    },

    async walkEntry(entry, prefix, out) {
        if (entry.isFile) {
            const f = await new Promise((res) => entry.file(res, () => res(null)));
            if (f) {
                out.push({ file: f, path: prefix + f.name });
            } else {
                // Surface an unreadable dropped file instead of silently dropping it.
                this.uploads.push({ name: prefix + entry.name, state: 'error', progress: 0, error: labels.saveFailed || 'read failed' });
            }
            return;
        }
        // Read ALL child entries first (a tight readEntries loop, no per-file
        // await in between): the DirectoryReader gets invalidated on large
        // folders if you pause to read files between batches, which truncated
        // big drops (~stopped after a few dozen files). Then recurse.
        const reader = entry.createReader();
        const children = [];
        for (;;) {
            const batch = await new Promise((res) => reader.readEntries(res, () => res([])));
            if (! batch.length) break;
            children.push(...batch);
        }
        for (const child of children) {
            await this.walkEntry(child, prefix + entry.name + '/', out);
        }
    },

    // Open the shared opaque store (waits for the vault) and point the in-memory
    // manifest at its file arrays. The tree is already plaintext inside the sealed
    // blob — no per-row decrypt — so the UI works on it directly and every mutation
    // edits these arrays in place, then a debounced sealed save persists the whole
    // workspace. Mutations must splice in place, never reassign the arrays, so the
    // reference into window.LLStore.data stays intact (see _spliceWhere).
    async load() {
        this.state = 'boot';
        try {
            if (! await bootStore(this.$store)) { this.state = 'locked'; return; }
        } catch (e) { this.state = 'error'; return; }
        this.manifest.folders = window.LLStore.data.fileFolders;
        this.manifest.files = window.LLStore.data.files;
        this.state = 'ready';
        this.refreshUsage();
        // Tell the server which blobs the manifest still references so it can
        // reclaim the quota held by any it no longer does (grace-gated).
        this.reconcileBlobs();
    },

    // Current storage usage (the server can only report opaque blob bytes vs quota).
    refreshUsage() {
        return this._track((async () => {
            try {
                const res = await fetch(config.usageUrl, { cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) this.usage = await res.json();
            } catch (e) { /* keep the last value */ }
        })());
    },

    // Remove matching elements from an array IN PLACE, so the shared reference
    // into window.LLStore.data (which the sealed save reads) is never detached.
    _spliceWhere(arr, pred) {
        for (let i = arr.length - 1; i >= 0; i--) if (pred(arr[i])) arr.splice(i, 1);
    },

    // Best-effort reclaim of content blobs the manifest no longer references
    // (permanent delete / version-cap overflow / edit swap). The server verifies
    // ownership; a still-referenced blob is never passed here.
    _freeBlobs(blobs) {
        const uniq = [...new Set((blobs || []).filter(Boolean))];
        if (! uniq.length) return;
        // Paced + 429-retried so emptying a large trash reclaims every blob's
        // bytes instead of tripping the rate limit and silently dropping them.
        Promise.all(uniq.map((blob) => queueBlobDelete(`${config.blobBase}/${blob}`, config.token)))
            .then(() => this.refreshUsage());
        this.refreshUsage();
    },

    // Best-effort reclaim of blobs stored inside a shared folder vault.
    // Shared blobs live at /vaults/{vaultId}/blobs/{blob}, not config.blobBase.
    _sharedFreeBlobs(vaultId, blobs) {
        const uniq = [...new Set((blobs || []).filter(Boolean))];
        if (! uniq.length) return;
        Promise.all(uniq.map((blob) => queueBlobDelete(`/vaults/${vaultId}/blobs/${blob}`, config.token)))
            .then(() => this.refreshUsage());
        this.refreshUsage();
    },

    // Send the manifest's full live blob set so the server frees the rest of the
    // user's quota ledger. Debounced; runs on load (self-heals quota each visit).
    _reconcileTimer: null,
    reconcileBlobs() {
        clearTimeout(this._reconcileTimer);
        this._reconcileTimer = setTimeout(() => this._reconcileNow(), 1500);
    },
    async _reconcileNow() {
        if (this.state !== 'ready') return;
        // Dedupe across component mounts (Files page + contacts avatar picker).
        if (Date.now() - _filesReconAt < 60000) return;
        _filesReconAt = Date.now();
        const blobs = [];
        for (const f of this.manifest.files) {
            if (f.blob) blobs.push(f.blob);
            if (f.textRef) blobs.push(f.textRef); // extracted-text index blob
            if (f.embRef) blobs.push(f.embRef);  // image CLIP-embedding blob
            for (const v of f.versions ?? []) if (v.blob) blobs.push(v.blob);
        }
        try {
            const res = await fetch(config.reconcileUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ blobs: [...new Set(blobs)] }),
            });
            if (res.ok) this.usage = await res.json();
        } catch (e) { /* best effort */ }
    },

    // Reconcile blobs for a shared folder after a permanent delete.
    // Mirrors _reconcileNow but targets /vaults/{vaultId}/blobs/reconcile.
    async _sharedReconcileNow(vaultId) {
        const tree = this.sharedTree[vaultId];
        if (! tree) return;
        const blobs = [];
        for (const f of tree.files) {
            if (f.blob) blobs.push(f.blob);
            if (f.textRef) blobs.push(f.textRef);
            if (f.embRef) blobs.push(f.embRef);
            for (const v of f.versions ?? []) if (v.blob) blobs.push(v.blob);
        }
        try {
            await fetch(`/vaults/${vaultId}/blobs/reconcile`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ blobs: [...new Set(blobs)] }),
            });
        } catch (_e) { /* best effort */ }
    },

    /* ---- Shared-context helpers ---- */

    // Enter a shared folder vault context: set activeShared, reset cwd and view.
    selectSharedFolder(vaultId) {
        this.activeShared = vaultId;
        this.cwd = null;
        this.view = 'files';
        this.selected = [];
    },

    // True when the user is currently browsing a shared folder (not personal).
    _isSharedContext() { return this.activeShared !== null; },

    // True when the current context allows write operations.
    // Personal context is always writable; shared context requires role ≥ edit.
    _canEditActive() {
        if (! this._isSharedContext()) return true;
        const sf = this.sharedFolders.find((f) => f.vaultId === this.activeShared);
        return sf ? sf.role === 'edit' || sf.role === 'manage' : false;
    },

    // True when the active shared folder allows management (invite/remove/delete).
    _canManageActive() {
        if (! this._isSharedContext()) return true;
        const sf = this.sharedFolders.find((f) => f.vaultId === this.activeShared);
        return sf ? sf.role === 'manage' : false;
    },

    // Active folder/file arrays: personal manifest or shared tree depending on context.
    _activeFolders() {
        return this._isSharedContext()
            ? (this.sharedTree[this.activeShared]?.folders ?? [])
            : this.manifest.folders;
    },
    _activeFiles() {
        return this._isSharedContext()
            ? (this.sharedTree[this.activeShared]?.files ?? [])
            : this.manifest.files;
    },

    /* ---- Shared folders (ZK cross-user, mirrors passwords.js _loadSharedVaults / _saveVault) ----
       Role mapping: server uses viewer/editor/manager; client uses read/edit/manage. */
    _serverToClientRole(r) {
        const map = { viewer: 'read', editor: 'edit', manager: 'manage' };
        return map[r] || 'read';
    },
    _clientToServerRole(r) {
        const map = { read: 'viewer', edit: 'editor', manage: 'manager' };
        return map[r] || 'viewer';
    },

    async _loadSharedFolders() {
        if (! this.$store.vault.unlocked) return;
        try {
            const ids = await Vault.ensureIdentityKeys();
            const rows = await getJson('/vaults?kind=folder').catch(() => []);
            const all = Array.isArray(rows) ? rows : [];
            const active = [];
            this.pendingFolderInvites = all
                .filter((m) => m.status === 'pending')
                .map((m) => ({ vault_id: m.vault_id, member_id: m.id, role: m.role, wrapped_vault_key: m.wrapped_vault_key }));
            for (const m of all) {
                if (m.status === 'pending') continue;
                if (m.status !== 'active') continue;
                try {
                    const vkB64 = await VaultShareCrypto.unwrapVaultKey(m.wrapped_vault_key, ids.pub, ids.sk);
                    // atob decodes standard base64 (ORIGINAL variant), matching vault.js's
                    // base64_variants.ORIGINAL encoding. unb64 is not exported from vault.js.
                    const vk = Uint8Array.from(atob(vkB64), (c) => c.charCodeAt(0));
                    // Cache the raw key bytes for write operations (in-memory only).
                    this._folderKeys[m.vault_id] = vk;
                    const store = await getJson('/vaults/' + m.vault_id + '/store');
                    let manifest = { name: '', folders: [], files: [] };
                    if (store.sealed_manifest) {
                        manifest = await VaultShareCrypto.openVaultManifest(store.sealed_manifest, vk);
                    }
                    this._folderVersion[m.vault_id] = store.version || 0;
                    this.sharedTree[m.vault_id] = { folders: manifest.folders || [], files: manifest.files || [] };
                    // Avoid duplicates if _loadSharedFolders is somehow called twice.
                    if (this.sharedFolders.some((sf) => sf.vaultId === m.vault_id)) continue;
                    active.push({
                        id: m.vault_id,
                        vaultId: m.vault_id,
                        name: manifest.name || '',
                        role: this._serverToClientRole(m.role),
                        version: store.version || 0,
                    });
                } catch (e) {
                    console.warn('[shared folder] failed to load vault', m.vault_id, e);
                }
            }
            this.sharedFolders = active;
        } catch (e) {
            console.warn('[shared folders] load aborted', e);
        }
    },

    // Seal and PUT the shared folder manifest with optimistic concurrency.
    // On 409 the server body contains the current sealed manifest + version;
    // we re-apply the pending mutation and retry once before surfacing an error.
    // mutation = { op: 'upsertFile'|'deleteFile'|'upsertFolder'|'deleteFolder', item }
    //          | { op: 'deleteFiles', ids: string[], folderIds?: string[] }
    async _saveFolder(vaultId, mutation) {
        const entry = this.sharedFolders.find((sf) => sf.vaultId === vaultId);
        const vkBytes = this._folderKeys && this._folderKeys[vaultId];
        if (! entry || ! vkBytes) throw new Error('Vault key not available for ' + vaultId);
        const tree = this.sharedTree[vaultId] || { folders: [], files: [] };
        const manifest = { name: entry.name, folders: tree.folders || [], files: tree.files || [] };
        const sealed = await VaultShareCrypto.sealVaultManifest(manifest, vkBytes);
        const body = JSON.stringify({ sealed_manifest: sealed, expected_version: this._folderVersion[vaultId] });
        let res = await fetch('/vaults/' + vaultId + '/store', { method: 'PUT', headers: jsonHeaders(), body });
        // On 429, honour Retry-After and retry once (mirrors personal LLStore.flush behaviour).
        if (res.status === 429) {
            const retryAfter = parseInt(res.headers.get('Retry-After') || '5', 10);
            await new Promise((r) => setTimeout(r, (isNaN(retryAfter) || retryAfter <= 0 ? 5 : Math.min(retryAfter, 60)) * 1000));
            res = await fetch('/vaults/' + vaultId + '/store', { method: 'PUT', headers: jsonHeaders(), body });
        }
        if (res.ok) {
            const { version } = await res.json();
            this._folderVersion[vaultId] = version;
            return;
        }
        if (res.status === 409 && mutation) {
            // Server has a newer version — merge and retry once.
            // Fix 2: use the server manifest's name so a concurrent rename is not overwritten.
            const data = await res.json();
            const serverManifest = await VaultShareCrypto.openVaultManifest(data.sealed_manifest, vkBytes);
            let serverFolders = serverManifest.folders || [];
            let serverFiles = serverManifest.files || [];
            const serverName = serverManifest.name ?? entry.name;
            if (mutation.op === 'upsertFolder') {
                const idx = serverFolders.findIndex((f) => f.id === mutation.item.id);
                if (idx >= 0) serverFolders[idx] = mutation.item; else serverFolders.push(mutation.item);
            } else if (mutation.op === 'deleteFolder') {
                serverFolders = serverFolders.filter((f) => f.id !== mutation.item.id);
            } else if (mutation.op === 'upsertFile') {
                const idx = serverFiles.findIndex((f) => f.id === mutation.item.id);
                if (idx >= 0) serverFiles[idx] = mutation.item; else serverFiles.push(mutation.item);
            } else if (mutation.op === 'deleteFile') {
                serverFiles = serverFiles.filter((f) => f.id !== mutation.item.id);
            } else if (mutation.op === 'deleteFiles') {
                // Fix 1: plural delete — remove exactly the specified file ids (and folder ids if present).
                const fileIdSet = new Set(mutation.ids ?? []);
                const folderIdSet = new Set(mutation.folderIds ?? []);
                serverFiles = serverFiles.filter((f) => ! fileIdSet.has(f.id));
                if (folderIdSet.size) serverFolders = serverFolders.filter((f) => ! folderIdSet.has(f.id));
            }
            this.sharedTree = { ...this.sharedTree, [vaultId]: { folders: serverFolders, files: serverFiles } };
            this._folderVersion[vaultId] = data.version;
            const sealed2 = await VaultShareCrypto.sealVaultManifest(
                { name: serverName, folders: serverFolders, files: serverFiles },
                vkBytes,
            );
            const res2 = await fetch('/vaults/' + vaultId + '/store', {
                method: 'PUT',
                headers: jsonHeaders(),
                body: JSON.stringify({ sealed_manifest: sealed2, expected_version: data.version }),
            });
            if (res2.ok) {
                const { version } = await res2.json();
                this._folderVersion[vaultId] = version;
                return;
            }
            // Second conflict — surface error and reload folder state.
            await this._loadSharedFolders();
            return;
        }
        throw new Error('Save shared folder failed: ' + res.status);
    },

    // All descendant folder ids of the given folders (inclusive of the roots).
    // Uses the active context's folder list (personal or shared).
    _folderClosure(folderIds) {
        const kill = new Set(folderIds);
        for (let grew = true; grew;) {
            grew = false;
            for (const f of this._activeFolders()) {
                if (! kill.has(f.id) && f.parent && kill.has(f.parent)) { kill.add(f.id); grew = true; }
            }
        }
        return kill;
    },

    // Keep only the newest N versions of a file; reclaim the overflow blobs.
    _trimVersions(entry) {
        const keep = config.maxVersions || 10;
        if (! entry.versions || entry.versions.length <= keep) return;
        const evicted = entry.versions.slice(keep);
        entry.versions = entry.versions.slice(0, keep);
        this._freeBlobs(evicted.map((v) => v.blob));
    },

    usage: { used: 0, quota: 0 },
    versions: { open: false, row: null, list: [], loading: false },

    // Version history lives in the file's manifest row (versions[]) — no server
    // round-trip. Each entry keeps its own wrapped key so its blob decrypts.
    openVersions(row) {
        // Shared files: look in the shared tree if the row carries sharedVaultId.
        const files = row.sharedVaultId
            ? (this.sharedTree[row.sharedVaultId]?.files ?? [])
            : this.manifest.files;
        const f = files.find((x) => x.id === row.id) || row;
        this.versions = {
            open: true, row, loading: false,
            list: (f.versions ?? []).map((v) => ({ ...v, created_at: v.created })),
        };
    },
    // Download a snapshot: fetch its ciphertext blob and decrypt with the
    // version's own wrapped key. Shared files use the vault blob URL + VK_folder.
    async downloadVersion(v) {
        try {
            const parentRow = this.versions.row;
            let buf;
            if (parentRow?.sharedVaultId) {
                const vk = this._folderKeys[parentRow.sharedVaultId];
                if (! vk) throw new Error('folder key not available');
                const res = await fetch(`/vaults/${parentRow.sharedVaultId}/blobs/raw/${v.blob}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (! res.ok) throw new Error('fetch failed');
                buf = await res.arrayBuffer();
                saveBlobAs(window.Vault.decryptFileWith(buf, v.encFileKey, vk), v.name, v.mime);
            } else {
                const res = await fetch(`${config.rawBase}/${v.blob}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (! res.ok) throw new Error('fetch failed');
                saveBlobAs(window.Vault.decryptFile(await res.arrayBuffer(), v.encFileKey), v.name, v.mime);
            }
        } catch (e) { this.error = labels.downloadFailed; }
    },
    // Restore a version: snapshot the current blob as a new version, then point
    // the row at the restored blob + its wrapped key (kept decryptable), cap, save.
    async restoreVersion(v) {
        if (! await this.$store.confirm.ask(labels.restoreConfirm || '')) return;
        const parentRow = this.versions.row;
        const sharedVaultId = parentRow?.sharedVaultId ?? null;
        const files = sharedVaultId
            ? (this.sharedTree[sharedVaultId]?.files ?? [])
            : this.manifest.files;
        const row = files.find((f) => f.id === parentRow.id);
        if (! row) return;
        row.versions = row.versions ?? [];
        row.versions.unshift({ id: crypto.randomUUID(), blob: row.blob, encFileKey: row.encFileKey, size: row.size, mime: row.mime, name: row.name, created: new Date().toISOString() });
        row.blob = v.blob;
        row.size = v.size;
        if (v.mime) row.mime = v.mime;
        row.encFileKey = v.encFileKey;
        this._spliceWhere(row.versions, (x) => x.id === v.id);
        this._trimVersions(row);
        this._logActivity(row, 'restored');
        if (sharedVaultId) {
            await this._saveFolder(sharedVaultId, { op: 'upsertFile', item: row });
        } else {
            this.persist();
        }
        this.versions.open = false;
    },

    /* ---- Per-file activity log (client-side, inside the sealed manifest). Records
       mutations (create/rename/move/version/restore/trash) so a file carries its
       own history; bounded so it can't grow the manifest without limit. --- */
    _logActivity(f, action, detail = null) {
        if (! f) return;
        f.activity = f.activity ?? [];
        const e = { a: action, at: new Date().toISOString() };
        if (detail) e.d = detail;
        f.activity.unshift(e);
        if (f.activity.length > 50) f.activity.length = 50;
    },
    // Mark a file as opened/accessed — drives the Recent / Quick-Access view.
    _touchOpened(id) {
        const f = this._activeFiles().find((x) => x.id === id);
        if (! f) return;
        f.openedAt = new Date().toISOString();
        if (this._isSharedContext()) {
            this._saveFolder(this.activeShared, { op: 'upsertFile', item: f }).catch(() => {});
        } else {
            this.persist();
        }
    },
    // Human-facing label + relative time for an activity entry (rendered in the
    // info panel). The label map lives in `labels` (from the blade).
    activityLabel(a) { return (labels.activity && labels.activity[a]) || a; },

    /* ---- Public share links for a file or a whole folder (zero-knowledge) ----
       Mirrors the gallery: a fresh share key (SK) is generated, each blob's
       per-file key is unwrapped with the vault key and re-wrapped under SK, and
       only the sealed manifest + ciphertext reach the server. SK lives in the
       link fragment and in our own sealed manifest (entry.share.sk) so the owner
       can re-copy the link; it never reaches the server. --- */
    share: { open: false, kind: '', ref: null, name: '', busy: false, password: '', expiresAt: '', link: '', error: '', hasPassword: false },
    openShare(row) {
        const src = row.kind === 'folder' ? this.manifest.folders.find((f) => f.id === row.id) : this.manifest.files.find((f) => f.id === row.id);
        const s = src?.share;
        this.share = {
            open: true, kind: row.kind, ref: row.id, name: row.name, busy: false, error: '',
            password: '', expiresAt: s?.expiresAt || '', hasPassword: ! ! s?.hasPassword,
            link: s?.token ? this._shareLink(s.token, s.sk) : '',
        };
    },
    closeShare() { this.share.open = false; this.share.ref = null; this.share.password = ''; },
    _shareLink(token, sk) { return `${config.shareBase}/${token}#s:${sk}`; },
    _shareSrc() { return this.share.kind === 'folder' ? this.manifest.folders.find((f) => f.id === this.share.ref) : this.manifest.files.find((f) => f.id === this.share.ref); },
    _relPath(f, rootId, byId) {
        const parts = []; let cur = f.folder;
        while (cur != null && cur !== rootId && byId.has(cur)) { parts.unshift(byId.get(cur).name); cur = byId.get(cur).parent; }
        return parts.join('/');
    },
    // Seal a share manifest: the file (or every non-trashed file under the folder),
    // each with its per-file key re-wrapped under the share key.
    async _buildShareManifest(sk) {
        const refs = [], entries = [];
        const wrap = async (f, path) => {
            if (! f.blob || ! f.encFileKey) return;
            const fk = window.Vault.unwrapContentKey(f.encFileKey);
            entries.push({ name: f.name, mime: f.mime || 'application/octet-stream', size: f.size || 0, path: path || '', ref: f.blob, key: await window.ShareCrypto.wrap(fk, sk) });
            refs.push(f.blob);
        };
        if (this.share.kind === 'file') {
            const f = this.manifest.files.find((x) => x.id === this.share.ref);
            if (f) await wrap(f, '');
        } else {
            const rootId = this.share.ref;
            const set = this.subtree(rootId);
            const byId = new Map(this.manifest.folders.map((x) => [x.id, x]));
            for (const f of this.manifest.files) if (! f.trashed && set.has(f.folder)) await wrap(f, this._relPath(f, rootId, byId));
        }
        const manifest = { kind: this.share.kind, name: this.share.name, files: entries };
        const sealed = await window.ShareCrypto.wrap(new TextEncoder().encode(JSON.stringify(manifest)), sk);
        return { sealed, refs: [...new Set(refs)] };
    },
    _shareBody(sealed, refs) {
        const body = { kind: this.share.kind, sealed_manifest: sealed, blob_refs: refs, allow_download: true };
        if (this.share.expiresAt) body.expires_at = new Date(this.share.expiresAt).toISOString();
        if (this.share.password.trim()) body.password = this.share.password.trim();
        return body;
    },
    async createShare() {
        if (this.share.busy) return;
        this.share.busy = true; this.share.error = '';
        try {
            const sk = await window.ShareCrypto.newKey();
            const { sealed, refs } = await this._buildShareManifest(sk);
            if (! refs.length) { this.share.error = labels.shareEmpty || 'empty'; this.share.busy = false; return; }
            const { token } = await postForm(config.fileSharesUrl, this._shareBody(sealed, refs));
            const src = this._shareSrc();
            if (src) src.share = { token, sk, kind: this.share.kind, hasPassword: ! ! this.share.password.trim(), expiresAt: this.share.expiresAt || null, created: new Date().toISOString() };
            this.share.hasPassword = ! ! this.share.password.trim();
            this.share.password = '';
            this.share.link = this._shareLink(token, sk);
            this.persist();
        } catch (e) { this.share.error = labels.shareError || 'error'; } finally { this.share.busy = false; }
    },
    async updateShare() {
        const src = this._shareSrc();
        if (! src?.share || this.share.busy) return;
        this.share.busy = true; this.share.error = '';
        try {
            const { sealed, refs } = await this._buildShareManifest(src.share.sk);
            const body = this._shareBody(sealed, refs);
            if (! this.share.password.trim() && ! src.share.hasPassword) body.clear_password = true;
            await postForm(`${config.fileSharesUrl}/${src.share.token}`, body, 'PUT');
            src.share.expiresAt = this.share.expiresAt || null;
            if (this.share.password.trim()) { src.share.hasPassword = true; this.share.hasPassword = true; } else if (body.clear_password) { src.share.hasPassword = false; this.share.hasPassword = false; }
            this.share.password = '';
            this.persist();
        } catch (e) { this.share.error = labels.shareError || 'error'; } finally { this.share.busy = false; }
    },
    async revokeShare() {
        const src = this._shareSrc();
        if (! src?.share) return;
        this.share.busy = true;
        try { await postForm(`${config.fileSharesUrl}/${src.share.token}`, null, 'DELETE'); } catch (e) { /* best effort */ }
        delete src.share; this.share.link = ''; this.share.busy = false;
        this.persist();
    },
    async copyShareLink() { if (! this.share.link) return; try { await navigator.clipboard.writeText(this.share.link); window.llToast(labels.shareCopied || ''); } catch (e) { /* clipboard blocked */ } },

    // Persist the whole workspace: schedule a debounced, sealed save of the shared
    // opaque store (the file arrays are live references into it). LLStore coalesces
    // rapid edits and handles optimistic-concurrency + retry itself.
    persist() {
        if (this.state === 'ready') window.LLStore.touch();
        return Promise.resolve();
    },
    // Kept as thin aliases so existing call sites don't need to change: LLStore
    // already debounces, and there is no stale whole-tree PUT to cancel anymore
    // (every save seals the current shared state).
    _schedulePersist() { this.persist(); },
    _cancelPendingPersist() {},

    /* ---- Derived views ---- */

    get breadcrumb() {
        const chain = [];
        let cur = this.cwd;
        const byId = new Map(this._activeFolders().map((f) => [f.id, f]));
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
    // Count of items with a live public share link (for the sidebar badge).
    get sharedCount() { return this._activeFolders().filter((d) => d.share).length + this._activeFiles().filter((f) => f.share && ! f.trashed).length; },

    get trashCount() {
        return this._activeFiles().filter((f) => f.trashed).length;
    },

    get favCount() {
        return this._activeFiles().filter((f) => f.favorite && ! f.trashed).length;
    },

    get rows() {
        const q = (this._q || '').trim(); // debounced + lowercased search term
        void this._contentReady; // re-filter as file content decrypts into the index
        const sem = this._semanticHits; // CLIP image-file matches for this query
        const tag = this.activeTag;
        const factor = this.sortDir === 'desc' ? -1 : 1;
        const byName = (a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base', numeric: true });
        const base = this.sortKey === 'size' ? ((a, b) => (a.size || 0) - (b.size || 0))
            : this.sortKey === 'date' ? ((a, b) => new Date(a.created || 0) - new Date(b.created || 0))
                : byName;
        const cmp = (a, b) => factor * (base(a, b) || byName(a, b));
        // Match the filename OR the extracted file content (lazily decrypted into
        // the module-scoped fileText index; warmed in the background on load).
        const search = (list) => q === '' ? list : list.filter((x) => x.name.toLowerCase().includes(q) || (fileText[x.id] || '').includes(q) || (sem && sem.has(x.id)));

        const activeFiles = this._activeFiles();
        const activeFolders = this._activeFolders();

        // Flat views (trash / favorites / recent): a tree-wide file list, not
        // folder-scoped.
        if (this.view === 'trash') {
            return search(activeFiles.filter((f) => f.trashed)).map((f) => ({ ...f, kind: 'file' })).sort(cmp);
        }
        if (this.view === 'favorites') {
            return search(activeFiles.filter((f) => f.favorite && ! f.trashed)).map((f) => ({ ...f, kind: 'file' })).sort(cmp);
        }
        if (this.view === 'recent') {
            // Quick-Access: most-recently opened first, falling back to upload time
            // for files never opened. Only files touched at some point surface high.
            return search(activeFiles.filter((f) => ! f.trashed))
                .map((f) => ({ ...f, kind: 'file' }))
                .sort((a, b) => new Date(b.openedAt || b.created || 0) - new Date(a.openedAt || a.created || 0)).slice(0, 100);
        }
        if (this.view === 'shared') {
            // Everything that currently has a public share link — folders first.
            const folders = activeFolders.filter((d) => d.share).map((d) => ({ ...d, kind: 'folder' }));
            const files = activeFiles.filter((f) => f.share && ! f.trashed).map((f) => ({ ...f, kind: 'file' }));
            return search([...folders, ...files]).sort(cmp);
        }

        // A text search or an active tag filter switches from folder browsing to
        // a flat, tree-wide result set.
        const inScope = (list) => {
            let scoped = (q === '' && tag === '')
                ? list.filter((x) => (x.parent ?? x.folder ?? null) === this.cwd)
                : list;
            // Match filename OR extracted content (folders have no content, so
            // fileText[id] is '' and they fall back to a name match).
            if (q !== '') scoped = scoped.filter((x) => x.name.toLowerCase().includes(q) || (fileText[x.id] || '').includes(q) || (sem && sem.has(x.id)));
            if (tag !== '') scoped = scoped.filter((x) => (x.tags ?? []).includes(tag));
            return scoped;
        };

        const folders = inScope(activeFolders.map((f) => ({ ...f, kind: 'folder' })));
        // Hide trashed files (e.g. deleted over WebDAV): data() returns them with
        // a `trashed` timestamp so sync keeps their state, but they must not show.
        const files = inScope(activeFiles.filter((f) => ! f.trashed).map((f) => ({ ...f, kind: 'file' })));

        const sorted = [...folders.sort(cmp), ...files.sort(cmp)];
        // At personal root (no search/tag), surface the user's shared folders as
        // folder rows with a people badge; clicking enters the shared context.
        if (this.activeShared === null && this.cwd === null && q === '' && tag === '') {
            const sharedRows = this.sharedFolders.map((f) => ({
                kind: 'folder', shared: true, vaultId: f.vaultId, id: f.vaultId, name: f.name, role: f.role,
            })).sort(cmp);
            return [...sharedRows, ...sorted];
        }
        return sorted;
    },

    // Every tag used anywhere in the manifest, for suggestions.
    get allTags() {
        const set = new Set();
        for (const x of [...this._activeFolders(), ...this._activeFiles()]) {
            for (const t of x.tags ?? []) set.add(t);
        }
        return [...set].sort((a, b) => a.localeCompare(b));
    },

    // Rich category (uses the filename extension + MIME) for a row.
    fileCat(row) {
        return fileCategory(row?.name, row?.mime);
    },

    typeLabel(file) {
        return labels.types?.[this.fileCat(file)] ?? file?.mime ?? '';
    },

    // Small type icon path for a file row.
    fileIconPath(row) {
        return CATEGORY_ICON[this.fileCat(row)] ?? CATEGORY_ICON.OTHER;
    },

    // iOS tinted-chip tint for a row (folder tint for folders, category tint for files).
    rowTint(row) { return row.kind === 'folder' ? FOLDER_TINT : categoryTint(row.name, row.mime || ''); },

    // Heroicon path for a row's chip glyph (folder glyph for folders, category glyph for files).
    rowIconPath(row) { return row.kind === 'folder' ? FOLDER_ICON_PATH : (CATEGORY_ICON[fileCategory(row.name, row.mime || '')] || CATEGORY_ICON.OTHER); },

    // Localized specific type label for a file row (via the Blade-passed map).
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

    // Save the note on the current info file. The note is plaintext inside the
    // sealed manifest — just edit the row and schedule a save.
    saveNote() {
        const row = this.infoRow;
        if (! row || row.kind !== 'file') return;
        if (! this._canEditActive()) return;
        const note = this.infoNote.trim();
        if ((row.note || '') === note) return;
        if (row.sharedVaultId) {
            const vaultId = row.sharedVaultId;
            const f = (this.sharedTree[vaultId]?.files ?? []).find((x) => x.id === row.id);
            if (f) { f.note = note; row.note = note; this._saveFolder(vaultId, { op: 'upsertFile', item: f }).catch(() => {}); }
        } else {
            const f = this.manifest.files.find((x) => x.id === row.id);
            if (f) f.note = note;
            row.note = note;
            this.persist();
        }
    },

    // Direct children of a folder (files + subfolders), counted client-side —
    // the count lives only in the decrypted manifest, never on the server.
    folderItemCount(row) {
        if (! row) return 0;
        const files = this._activeFiles().filter((f) => (f.folder ?? null) === row.id).length;
        const folders = this._activeFolders().filter((f) => (f.parent ?? null) === row.id).length;
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

    // Create a note from a title + content. Notes live in the same opaque store,
    // so this just adds a row to the shared notes manifest (no plaintext ever
    // leaves the vault) and schedules a sealed save.
    async migrateAddNote(note) {
        try {
            if (! await bootStore(this.$store)) return false;
            window.LLStore.data.notes.unshift({
                id: window.LLStore.newId(),
                title: note.title || '', content: note.content || '',
                tags: [], pinned: false, trashed: false, updated: new Date().toISOString(),
            });
            window.LLStore.touch();
            return true;
        } catch (e) {
            return false;
        }
    },

    // Decrypt a Markdown file in the browser, create a note from it (title =
    // filename without extension), then optionally delete the source file.
    async applyMigrate() {
        const row = this.migrateRow;
        const del = this.migrateDelete;
        if (! row || this.migrateBusy) return;
        // Note: both files AND notes are zero-knowledge, so the content stays
        // encrypted end to end — no vault-exit warning needed here.
        this.migrateBusy = true;
        this.error = '';
        try {
            const plain = await this.fetchPlain(row);
            const text = new TextDecoder('utf-8').decode(plain);
            const ok = await this.migrateAddNote({
                title: (row.name || '').replace(/\.(md|markdown)$/i, ''),
                content: text,
            });
            if (! ok) {
                this.error = labels.migrateFailed;
                return;
            }

            if (del) {
                const src = this.manifest.files.find((x) => x.id === row.id);
                const blobs = src ? [src.blob, ...(src.textRef ? [src.textRef] : []), ...(src.embRef ? [src.embRef] : []), ...(src.versions ?? []).map((v) => v.blob)] : [];
                this._spliceWhere(this.manifest.files, (x) => x.id === row.id);
                this.persist();
                this._freeBlobs(blobs);
            }
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
        const byId = new Map(this._activeFolders().map((f) => [f.id, f]));
        const chain = [];
        let cur = parentId;
        while (cur != null && byId.has(cur)) {
            chain.unshift(byId.get(cur).name);
            cur = byId.get(cur).parent;
        }
        return [root, ...chain].join(' / ');
    },

    /* ---- New folder modal ---- */

    openNewFolder() {
        this.newFolderName = '';
        this.newFolderShared = false;
        this.newFolderModal = true;
        this.$nextTick(() => { this.$refs.newFolderInput?.focus(); });
    },

    async submitNewFolder() {
        const n = this.newFolderName.trim();
        if (! n) return;
        try {
            if (this.newFolderShared && ! this._isSharedContext()) {
                await this.createSharedFolder(n);
            } else {
                await this.mkdir(n);
            }
            // Close only on success so a failure keeps the entered name + surfaces the error.
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
        if (! this._canEditActive()) return;
        const folder = { id: crypto.randomUUID(), name, parent: this.cwd };
        if (this._isSharedContext()) {
            const vaultId = this.activeShared;
            (this.sharedTree[vaultId]?.folders ?? []).push(folder);
            await this._saveFolder(vaultId, { op: 'upsertFolder', item: folder });
        } else {
            this.manifest.folders.push(folder);
            await this.persist().catch(() => this.load());
        }
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
        if (! this._canEditActive()) return;
        if (this._isSharedContext()) {
            const vaultId = this.activeShared;
            const tree = this.sharedTree[vaultId];
            const list = row.kind === 'folder' ? (tree?.folders ?? []) : (tree?.files ?? []);
            const item = list.find((x) => x.id === row.id);
            if (item) {
                if (row.kind !== 'folder') this._logActivity(item, 'renamed', name);
                item.name = name;
                const op = row.kind === 'folder' ? 'upsertFolder' : 'upsertFile';
                await this._saveFolder(vaultId, { op, item });
            }
        } else {
            const list = row.kind === 'folder' ? this.manifest.folders : this.manifest.files;
            const item = list.find((x) => x.id === row.id);
            if (item) {
                if (row.kind !== 'folder') this._logActivity(item, 'renamed', name);
                item.name = name;
                await this.persist().catch(() => this.load());
            }
        }
    },

    /* ---- Selection ---- */

    rowKey: (row) => `${row.kind}:${row.id}`,

    toggleAll(event) {
        this.selected = event.target.checked ? this.rows.map(this.rowKey) : [];
    },

    get selectionRefs() {
        return this.selected.map((key) => {
            const [kind, id] = key.split(':');
            const list = kind === 'folder' ? this._activeFolders() : this._activeFiles();
            const item = list.find((x) => x.id === id);
            return item ? { kind, id, name: item.name } : null;
        }).filter(Boolean);
    },

    // Expand a folder id to its whole subtree of folder ids.
    // Uses the active context's folder list (personal or shared).
    subtree(id) {
        const set = new Set([id]);
        let grew = true;
        while (grew) {
            grew = false;
            for (const f of this._activeFolders()) {
                if (f.parent != null && set.has(f.parent) && ! set.has(f.id)) {
                    set.add(f.id);
                    grew = true;
                }
            }
        }
        return set;
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
            if (ref.kind === 'folder') {
                for (const id of this.subtree(ref.id)) excluded.add(id);
            }
        }
        const folders = this._activeFolders();
        const byId = new Map(folders.map((x) => [x.id, x]));
        const path = (f) => {
            const parts = [f.name];
            let cur = f.parent;
            while (cur != null && byId.has(cur)) { parts.unshift(byId.get(cur).name); cur = byId.get(cur).parent; }
            return parts.join(' / ');
        };
        return folders
            .filter((f) => ! excluded.has(f.id))
            .map((f) => ({ id: f.id, label: path(f) }))
            .sort((a, b) => a.label.localeCompare(b.label));
    },

    async applyMove() {
        const refs = this.moveRefs;
        this.moveOpen = false;
        this.moveRefs = [];
        if (! refs.length) return;
        if (! this._canEditActive()) return;
        const target = this.moveTarget === '' ? null : this.moveTarget;
        if (this._isSharedContext()) {
            const vaultId = this.activeShared;
            const tree = this.sharedTree[vaultId];
            const movedItems = [];
            for (const ref of refs) {
                if (ref.kind === 'folder') {
                    if (target !== null && this.subtree(ref.id).has(target)) continue;
                    const f = (tree?.folders ?? []).find((x) => x.id === ref.id);
                    if (f) { f.parent = target; movedItems.push({ op: 'upsertFolder', item: f }); }
                } else {
                    const f = (tree?.files ?? []).find((x) => x.id === ref.id);
                    if (f) { f.folder = target; this._logActivity(f, 'moved'); movedItems.push({ op: 'upsertFile', item: f }); }
                }
            }
            this.selected = [];
            // Save once with the last mutation; the full tree is always re-sealed.
            if (movedItems.length) {
                await this._saveFolder(vaultId, movedItems[movedItems.length - 1]);
            }
        } else {
            for (const ref of refs) {
                if (ref.kind === 'folder') {
                    if (target !== null && this.subtree(ref.id).has(target)) continue; // never into own subtree
                    const f = this.manifest.folders.find((x) => x.id === ref.id);
                    if (f) f.parent = target;
                } else {
                    const f = this.manifest.files.find((x) => x.id === ref.id);
                    if (f) { f.folder = target; this._logActivity(f, 'moved'); }
                }
            }
            this.selected = [];
            // If the move fails to save, re-sync from the server so the client never
            // shows items in a folder they were not actually moved to (a later delete
            // of the old folder would then look like data loss).
            await this.persist().catch(() => this.load());
        }
    },

    /* ---- Drag & drop into folders ---- */

    // Parent folder of the current directory (null = root), for the ".." row.
    get parentFolderId() {
        const f = this._activeFolders().find((x) => x.id === this.cwd);
        return f ? (f.parent ?? null) : null;
    },

    onDragStart(event, row) {
        this.dragItem = { kind: row.kind, id: row.id };
        event.dataTransfer.effectAllowed = 'move';
        try { event.dataTransfer.setData('text/plain', row.id); } catch (e) { /* ignore */ }
    },

    onDragEnd() {
        this.dragItem = null;
    },

    // Move the dragged item into a folder (null = root / parent via "..").
    async dropInto(targetFolderId) {
        const item = this.dragItem;
        this.dragItem = null;
        if (! item) return;
        if (! this._canEditActive()) return;
        if (this._isSharedContext()) {
            const vaultId = this.activeShared;
            const tree = this.sharedTree[vaultId];
            if (item.kind === 'folder') {
                if (item.id === targetFolderId) return;
                if (targetFolderId !== null && this.subtree(item.id).has(targetFolderId)) return;
                const f = (tree?.folders ?? []).find((x) => x.id === item.id);
                if (f && (f.parent ?? null) !== targetFolderId) { f.parent = targetFolderId; await this._saveFolder(vaultId, { op: 'upsertFolder', item: f }); }
            } else {
                const f = (tree?.files ?? []).find((x) => x.id === item.id);
                if (f && (f.folder ?? null) !== targetFolderId) { f.folder = targetFolderId; await this._saveFolder(vaultId, { op: 'upsertFile', item: f }); }
            }
        } else {
            if (item.kind === 'folder') {
                if (item.id === targetFolderId) return;
                if (targetFolderId !== null && this.subtree(item.id).has(targetFolderId)) return; // no cycle
                const f = this.manifest.folders.find((x) => x.id === item.id);
                if (f && (f.parent ?? null) !== targetFolderId) { f.parent = targetFolderId; await this.persist().catch(() => this.load()); }
            } else {
                const f = this.manifest.files.find((x) => x.id === item.id);
                if (f && (f.folder ?? null) !== targetFolderId) { f.folder = targetFolderId; await this.persist().catch(() => this.load()); }
            }
        }
    },

    openTags(row) {
        this.tagsRef = { kind: row.kind, id: row.id };
        this.tagsValue = (row.tags ?? []).join(', ');
        this.tagsOpen = true;
    },

    async applyTags() {
        const ref = this.tagsRef;
        this.tagsOpen = false;
        this.tagsRef = null;
        if (! ref) return;
        if (! this._canEditActive()) return;
        const tags = [...new Set(this.tagsValue.split(',').map((t) => t.trim()).filter(Boolean))];
        if (this._isSharedContext()) {
            const vaultId = this.activeShared;
            const tree = this.sharedTree[vaultId];
            const list = ref.kind === 'folder' ? (tree?.folders ?? []) : (tree?.files ?? []);
            const item = list.find((x) => x.id === ref.id);
            if (item) {
                item.tags = tags;
                const op = ref.kind === 'folder' ? 'upsertFolder' : 'upsertFile';
                await this._saveFolder(vaultId, { op, item });
            }
        } else {
            const list = ref.kind === 'folder' ? this.manifest.folders : this.manifest.files;
            const item = list.find((x) => x.id === ref.id);
            if (item) {
                item.tags = tags;
                await this.persist().catch(() => this.load());
            }
        }
    },

    confirmDelete(row) {
        this.deleteRefs = row ? [{ kind: row.kind, id: row.id, name: row.name }] : this.selectionRefs;
        this.deleteOpen = this.deleteRefs.length > 0;
    },

    // Delete via the modal: to the trash (soft) or permanently. A folder brings
    // its whole subtree. Everything is a manifest edit — trash is just a flag;
    // permanent removal also reclaims the content blobs (file + versions).
    applyDelete(permanent = false) {
        const refs = this.deleteRefs;
        this.deleteOpen = false;
        this.deleteRefs = [];
        if (! refs.length) return;
        if (! this._canEditActive()) return;
        const fileIds = new Set(refs.filter((r) => r.kind === 'file').map((r) => r.id));
        const folderIds = refs.filter((r) => r.kind === 'folder').map((r) => r.id);
        const killFolders = this._folderClosure(folderIds);

        if (this._isSharedContext()) {
            const vaultId = this.activeShared;
            const tree = this.sharedTree[vaultId];
            const activeFiles = tree?.files ?? [];
            const activeFolders = tree?.folders ?? [];
            // Directly-selected files + every file inside a deleted folder subtree.
            const targets = activeFiles.filter((f) => fileIds.has(f.id) || killFolders.has(f.folder));

            if (permanent) {
                const blobs = [];
                for (const f of targets) { blobs.push(f.blob); if (f.textRef) blobs.push(f.textRef); if (f.embRef) blobs.push(f.embRef); for (const v of f.versions ?? []) blobs.push(v.blob); }
                const kill = new Set(targets.map((f) => f.id));
                this._spliceWhere(activeFiles, (f) => kill.has(f.id));
                this._spliceWhere(activeFolders, (f) => killFolders.has(f.id));
                // Fix 1: pass the real deleted ids so 409 re-apply removes exactly the right entries.
                const deletedFileIds = [...kill];
                const deletedFolderIds = [...killFolders];
                this._saveFolder(vaultId, { op: 'deleteFiles', ids: deletedFileIds, folderIds: deletedFolderIds })
                    .catch(() => {});
                this._sharedFreeBlobs(vaultId, blobs);
                this._sharedReconcileNow(vaultId).catch(() => {});
            } else {
                const stamp = new Date().toISOString();
                for (const f of targets) {
                    if (! f.trashed) { f.trashed = stamp; this._logActivity(f, 'trashed'); }
                    if (killFolders.has(f.folder)) f.folder = null;
                }
                this._spliceWhere(activeFolders, (f) => killFolders.has(f.id));
                // Use the last trashed file as the mutation marker (full tree is re-sealed).
                const lastTarget = targets[targets.length - 1];
                this._saveFolder(vaultId, lastTarget
                    ? { op: 'upsertFile', item: lastTarget }
                    : { op: 'deleteFolder', item: { id: [...killFolders][0] } })
                    .catch(() => {});
            }
        } else {
            // Directly-selected files + every file inside a deleted folder subtree.
            const targets = this.manifest.files.filter((f) => fileIds.has(f.id) || killFolders.has(f.folder));

            if (permanent) {
                const blobs = [];
                for (const f of targets) { blobs.push(f.blob); if (f.textRef) blobs.push(f.textRef); if (f.embRef) blobs.push(f.embRef); for (const v of f.versions ?? []) blobs.push(v.blob); }
                const kill = new Set(targets.map((f) => f.id));
                this._spliceWhere(this.manifest.files, (f) => kill.has(f.id));
                this._spliceWhere(this.manifest.folders, (f) => killFolders.has(f.id));
                this.persist();
                this._freeBlobs(blobs);
            } else {
                const stamp = new Date().toISOString();
                for (const f of targets) {
                    if (! f.trashed) { f.trashed = stamp; this._logActivity(f, 'trashed'); }
                    // The folder is gone from the tree, so detach to root — a restore
                    // then lands the file at the top level instead of nowhere.
                    if (killFolders.has(f.folder)) f.folder = null;
                }
                this._spliceWhere(this.manifest.folders, (f) => killFolders.has(f.id));
                this.persist();
            }
        }
        this.selected = [];
    },

    // Move an item straight to the trash (used by drag-and-drop onto the trash).
    trashItem(ref) {
        if (! this._canEditActive()) return;
        if (this._isSharedContext()) {
            const vaultId = this.activeShared;
            const tree = this.sharedTree[vaultId];
            let lastItem = null;
            let lastOp = 'upsertFile';
            if (ref.kind === 'folder') {
                const killFolders = this._folderClosure([ref.id]);
                const stamp = new Date().toISOString();
                for (const f of (tree?.files ?? [])) {
                    if (killFolders.has(f.folder)) { if (! f.trashed) f.trashed = stamp; f.folder = null; lastItem = f; }
                }
                this._spliceWhere(tree?.folders ?? [], (f) => killFolders.has(f.id));
                lastOp = lastItem ? 'upsertFile' : 'deleteFolder';
                if (! lastItem) lastItem = { id: ref.id };
            } else {
                const f = (tree?.files ?? []).find((x) => x.id === ref.id);
                if (f && ! f.trashed) { f.trashed = new Date().toISOString(); this._logActivity(f, 'trashed'); lastItem = f; }
            }
            this.selected = [];
            if (lastItem) this._saveFolder(vaultId, { op: lastOp, item: lastItem }).catch(() => {});
        } else {
            if (ref.kind === 'folder') {
                const killFolders = this._folderClosure([ref.id]);
                const stamp = new Date().toISOString();
                for (const f of this.manifest.files) {
                    if (killFolders.has(f.folder)) { if (! f.trashed) f.trashed = stamp; f.folder = null; }
                }
                this._spliceWhere(this.manifest.folders, (f) => killFolders.has(f.id));
            } else {
                const f = this.manifest.files.find((x) => x.id === ref.id);
                if (f && ! f.trashed) { f.trashed = new Date().toISOString(); this._logActivity(f, 'trashed'); }
            }
            this.selected = [];
            this.persist();
        }
    },

    // Restore a trashed file back into the browser (clear its flag).
    restore(row) {
        if (! this._canEditActive()) return;
        if (row.sharedVaultId) {
            const vaultId = row.sharedVaultId;
            const f = (this.sharedTree[vaultId]?.files ?? []).find((x) => x.id === row.id);
            if (! f) return;
            f.trashed = null;
            this._logActivity(f, 'untrashed');
            this._saveFolder(vaultId, { op: 'upsertFile', item: f }).catch(() => {});
        } else {
            const f = this.manifest.files.find((x) => x.id === row.id);
            if (! f) return;
            f.trashed = null;
            this._logActivity(f, 'untrashed');
            this.persist();
        }
    },

    // Permanently delete one trashed file + reclaim its blobs.
    async purge(row) {
        if (! await this.$store.confirm.ask(labels.purgeConfirm || '')) return;
        if (! this._canEditActive()) return;
        if (row.sharedVaultId) {
            const vaultId = row.sharedVaultId;
            const f = (this.sharedTree[vaultId]?.files ?? []).find((x) => x.id === row.id);
            if (! f) return;
            const blobs = [f.blob, ...(f.textRef ? [f.textRef] : []), ...(f.embRef ? [f.embRef] : []), ...(f.versions ?? []).map((v) => v.blob)];
            this._spliceWhere(this.sharedTree[vaultId].files, (x) => x.id === row.id);
            await this._saveFolder(vaultId, { op: 'deleteFile', item: { id: row.id } });
            this._sharedFreeBlobs(vaultId, blobs);
            this._sharedReconcileNow(vaultId).catch(() => {});
        } else {
            const f = this.manifest.files.find((x) => x.id === row.id);
            if (! f) return;
            const blobs = [f.blob, ...(f.textRef ? [f.textRef] : []), ...(f.embRef ? [f.embRef] : []), ...(f.versions ?? []).map((v) => v.blob)];
            this._spliceWhere(this.manifest.files, (x) => x.id === row.id);
            this.persist();
            this._freeBlobs(blobs);
        }
    },

    // Permanently delete every trashed file + reclaim their blobs.
    async emptyTrash() {
        if (! this.trashCount) return;
        if (! await this.$store.confirm.ask(labels.emptyTrashConfirm || '')) return;
        if (! this._canEditActive()) return;
        if (this._isSharedContext()) {
            const vaultId = this.activeShared;
            const tree = this.sharedTree[vaultId];
            const trashed = (tree?.files ?? []).filter((f) => f.trashed);
            const blobs = [];
            for (const f of trashed) { blobs.push(f.blob); if (f.textRef) blobs.push(f.textRef); if (f.embRef) blobs.push(f.embRef); for (const v of f.versions ?? []) blobs.push(v.blob); }
            // Fix 1: collect real ids before splicing so 409 re-apply removes exactly these entries.
            const trashedIds = trashed.map((f) => f.id);
            this._spliceWhere(tree?.files ?? [], (f) => f.trashed);
            await this._saveFolder(vaultId, { op: 'deleteFiles', ids: trashedIds });
            this._sharedFreeBlobs(vaultId, blobs);
            this._sharedReconcileNow(vaultId).catch(() => {});
        } else {
            const trashed = this.manifest.files.filter((f) => f.trashed);
            const blobs = [];
            for (const f of trashed) { blobs.push(f.blob); if (f.textRef) blobs.push(f.textRef); if (f.embRef) blobs.push(f.embRef); for (const v of f.versions ?? []) blobs.push(v.blob); }
            this._spliceWhere(this.manifest.files, (f) => f.trashed);
            this.persist();
            this._freeBlobs(blobs);
        }
    },

    // Toggle a file's favourite (a plain manifest flag).
    toggleFavorite(row) {
        if (! this._canEditActive()) return;
        if (row.sharedVaultId) {
            const vaultId = row.sharedVaultId;
            const f = (this.sharedTree[vaultId]?.files ?? []).find((x) => x.id === row.id);
            if (! f) return;
            f.favorite = ! f.favorite;
            if (row) row.favorite = f.favorite;
            this._saveFolder(vaultId, { op: 'upsertFile', item: f }).catch(() => {});
        } else {
            const f = this.manifest.files.find((x) => x.id === row.id);
            if (! f) return;
            f.favorite = ! f.favorite;
            if (row) row.favorite = f.favorite;
            this.persist();
        }
    },

    /* ---- Content operations ---- */

    upload(fileList) {
        return this.uploadItems([...fileList].map((f) => ({ file: f, path: f.name })));
    },

    // Upload files (optionally with relative paths from a dropped folder),
    // recreating the folder chain in the manifest under the current folder.
    // Existing sibling folders are reused by name so re-drops don't duplicate.
    // OS/editor junk that should never be uploaded — macOS, iOS, Windows, Linux
    // and Android metadata, thumbnail, lock and temp files.
    isJunkUpload(name) {
        const path = name || '';
        const n = path.split('/').pop();
        // Anything inside a junk/system directory of a dropped folder.
        if (/(^|\/)(__MACOSX|\.Spotlight-V100|\.Trashes|\.fseventsd|\.TemporaryItems|\.DocumentRevisions-V100|\$RECYCLE\.BIN|System Volume Information|\.thumbnails|LOST\.DIR|\.git)(\/|$)/i.test(path)) return true;
        return (
            // macOS / iOS
            /^\.DS_Store$/i.test(n) || /^\._/.test(n) || /^\.localized$/i.test(n)
            || /^\.AppleDouble$/i.test(n) || /^\.AppleDB$/i.test(n) || /^\.AppleDesktop$/i.test(n)
            || /^\.apdisk$/i.test(n) || /^Icon\r?$/.test(n)
            // Windows
            || /^Thumbs\.db$/i.test(n) || /^ehthumbs\.db$/i.test(n) || /^ehthumbs_vista\.db$/i.test(n)
            || /^desktop\.ini$/i.test(n) || /^\$RECYCLE\.BIN$/i.test(n) || /^~\$/.test(n) || /\.stackdump$/i.test(n)
            // Linux
            || /^\.directory$/i.test(n) || /^\.Trash-/i.test(n) || /^\.nfs[0-9a-f]+$/i.test(n)
            || /^\.fuse_hidden/i.test(n) || /^\.~lock\./i.test(n)
            // Android
            || /^\.nomedia$/i.test(n) || /^\.pending-/i.test(n) || /^\.trashed-/i.test(n)
            // Generic editor/browser temp + partial downloads
            || /\.(tmp|temp|swp|swo|swn|crdownload|part|partial|bak|old)$/i.test(n)
            || /~$/.test(n) || /^\.#/.test(n)
        );
    },

    async uploadItems(items) {
        // Drop OS/editor junk (e.g. .DS_Store, ._*, Thumbs.db) silently.
        items = (items || []).filter((it) => ! this.isJunkUpload(it.file?.name || it.path));
        if (! items.length) return;
        // Role guard: viewers cannot upload.
        if (! this._canEditActive()) return;
        // A fresh batch when nothing is in flight clears the finished tray.
        if (this.uploadBatches === 0) this.uploads = [];
        this.uploadBatches++;

        // Shared-context: capture vaultId + key at batch start so they are stable
        // for the whole upload even if activeShared changes mid-batch.
        const sharedVaultId = this.activeShared;
        const sharedVk = sharedVaultId ? this._folderKeys[sharedVaultId] : null;

        // Target tree (folders list) for the dirCache / folderFor lookup.
        const activeFolders = sharedVaultId
            ? (this.sharedTree[sharedVaultId]?.folders ?? [])
            : this.manifest.folders;
        const activeFiles = sharedVaultId
            ? (this.sharedTree[sharedVaultId]?.files ?? [])
            : this.manifest.files;

        const dirCache = new Map(); // relative dir path -> folder id
        dirCache.set('', this.cwd);
        const folderFor = (path) => {
            const parts = path.split('/');
            parts.pop(); // drop the filename
            let acc = '';
            let parent = this.cwd;
            for (const seg of parts) {
                acc = acc ? `${acc}/${seg}` : seg;
                if (dirCache.has(acc)) {
                    parent = dirCache.get(acc);
                    continue;
                }
                const existing = activeFolders.find((f) => (f.parent ?? null) === parent && f.name === seg);
                const id = existing ? existing.id : crypto.randomUUID();
                if (! existing) {
                    activeFolders.push({ id, name: seg, parent });
                }
                dirCache.set(acc, id);
                parent = id;
            }
            return parent;
        };

        // Upload URL: shared folders use their own blob endpoint.
        const uploadUrl = sharedVaultId
            ? `/vaults/${sharedVaultId}/blobs/upload`
            : config.uploadUrl;

        // Show the whole batch immediately, then upload a few at a time. Concurrent
        // in-flight XHRs keep transferring even when the tab is backgrounded/frozen
        // (the browser freezes JS, not requests already in flight), whereas a
        // sequential loop would stall between files until the tab is focused again.
        const start = this.uploads.length;
        for (const item of items) this.uploads.push({ name: item.file.name, state: 'pending', progress: 0, error: '' });

        let next = 0;
        const worker = async () => {
            while (next < items.length) {
                const idx = next++;
                const item = items[idx];
                const entry = this.uploads[start + idx]; // reactive element
                try {
                    // Zero-knowledge: encrypt in the browser, upload only ciphertext.
                    // Large files stream — encrypted + uploaded part-by-part so neither
                    // the whole file nor the whole ciphertext is ever held in memory
                    // (constant-memory upload of any size). Small files take the simple
                    // whole-in-memory path.
                    let id, encFileKey;
                    if (item.file.size > 64 * 1024 * 1024) {
                        ({ id, encFileKey } = await this._uploadStreamEncrypted(item.file, entry, sharedVaultId, sharedVk));
                    } else {
                        // Shared path: encrypt under VK_folder via encryptContentWith.
                        // Personal path: encrypt under the personal VK via encryptFile.
                        let enc;
                        if (sharedVk) {
                            const bytes = new Uint8Array(await item.file.arrayBuffer());
                            enc = window.Vault.encryptContentWith(bytes, { name: item.file.name, mime: item.file.type || 'application/octet-stream' }, sharedVk);
                        } else {
                            enc = await window.Vault.encryptFile(item.file);
                        }
                        // Neutral filename — the real name is sealed inside encMeta and
                        // never sent to the server. Padmé-pad the ciphertext so the
                        // stored blob size (recorded in file_blobs.size, and thus in the
                        // DB dump) is length-hidden and can't fingerprint the plaintext —
                        // the same treatment the gallery blob path already applies. The
                        // trailing pad sits after the self-delimiting secretstream frames,
                        // so decryption ignores it (no download-side stripping needed).
                        const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
                        id = await this._uploadOne(cipher, entry, uploadUrl);
                        encFileKey = enc.encFileKey;
                    }
                    entry.state = 'done';
                    entry.progress = 100;
                    const row = {
                        id: crypto.randomUUID(),
                        blob: id,
                        encFileKey,
                        name: item.file.name,
                        mime: item.file.type || 'application/octet-stream',
                        size: item.file.size,
                        folder: folderFor(item.path),
                        created: new Date().toISOString(),
                        versions: [],
                    };
                    if (sharedVaultId) {
                        // Shared upload: store in the shared tree and persist via _saveFolder.
                        // Tag the row with sharedVaultId so download/open can find the key.
                        row.sharedVaultId = sharedVaultId;
                        activeFiles.push(row);
                        this._logActivity(row, 'created');
                        await this._saveFolder(sharedVaultId, { op: 'upsertFile', item: row });
                    } else {
                        // Personal upload: store in the personal manifest.
                        this.manifest.files.push(row);
                        this._logActivity(row, 'created');
                        // Auto-index text/PDF in the background. Images need OCR (slow)
                        // and most uploads aren't documents, so those index only via
                        // the explicit "Index contents" backfill.
                        if (this._textCapable(row) && ! /^image\//.test(row.mime || '')) this._queueExtract(row);
                        // Persist incrementally (debounced) so an interrupted bulk
                        // upload doesn't strand every uploaded blob without a row —
                        // the manifest is saved every ~2s instead of only at the end.
                        this._schedulePersist();
                    }
                } catch (e) {
                    entry.state = 'error';
                    entry.error = e && e.quota ? (labels.quotaExceeded || labels.uploadFailed)
                        : (e && e.unreadable ? (labels.uploadUnreadable || labels.uploadFailed) : labels.uploadFailed);
                }
            }
        };
        // Fewer parallel lanes when the batch has large files: each in-flight
        // large upload holds a rolling part buffer, so serialising them keeps
        // peak memory bounded.
        const hasLarge = items.some((i) => i.file.size > 64 * 1024 * 1024);
        const lanes = Math.min(hasLarge ? 2 : 4, items.length);
        await Promise.all(Array.from({ length: lanes }, worker));

        this.uploadBatches--;
        if (sharedVaultId) {
            // Shared: no personal persist needed; refreshUsage via the shared endpoint.
            this.refreshUsage();
        } else {
            this.persist();
            this.refreshUsage();
        }
        // Auto-dismiss the tray a few seconds after a clean finish (keep it open
        // when something errored so the user sees which file failed).
        if (this.uploadBatches === 0 && ! this.uploads.some((u) => u.state === 'error')) {
            setTimeout(() => { if (! this.uploading) this.uploads = []; }, 4000);
        }
    },

    // XHR upload of a single file, reporting byte progress into the tray entry.
    // ---- Content search: client-side text extraction (zero-knowledge) ----
    // Which files can yield searchable text (plain-text family + PDF).
    _textCapable(f) {
        const ext = ((f.name || '').split('.').pop() || '').toLowerCase();
        return /^text\//.test(f.mime || '') || f.mime === 'application/pdf' || /^image\//.test(f.mime || '')
            || ['txt', 'md', 'markdown', 'csv', 'tsv', 'log', 'json', 'xml', 'yaml', 'yml', 'ini', 'conf', 'html', 'htm', 'js', 'ts', 'jsx', 'tsx', 'css', 'scss', 'py', 'sh', 'bash', 'c', 'h', 'cpp', 'hpp', 'cs', 'java', 'go', 'rs', 'php', 'rb', 'sql', 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'].includes(ext);
    },
    async _extractText(bytes, mime, name) {
        const ext = ((name || '').split('.').pop() || '').toLowerCase();
        if (mime === 'application/pdf' || ext === 'pdf') {
            const t = await this._extractPdfText(bytes);
            // A real text layer → done; otherwise it's a scan → OCR the pages.
            if (t && t.replace(/\s+/g, '').length > 8) return t;
            return await this._ocrPdf(bytes);
        }
        // Images (scans, photos of documents) → OCR. Only keep a result that
        // has enough real characters, so an ordinary photo (which OCRs to noise)
        // doesn't pollute the index.
        if (/^image\//.test(mime || '') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'].includes(ext)) {
            const t = await ocrImage(new Blob([bytes], { type: mime || 'image/png' }));
            return (t && t.replace(/\s+/g, '').length >= 12) ? t : '';
        }
        try {
            let t = new TextDecoder('utf-8', { fatal: false }).decode(bytes);
            if (ext === 'html' || ext === 'htm' || /html/.test(mime || '')) t = t.replace(/<[^>]+>/g, ' ');
            return t;
        } catch (e) { return null; }
    },
    // OCR a scanned PDF: render each page to a canvas (pdf.js) and recognise it.
    // Capped + slow, so it only runs when there is no embedded text layer.
    async _ocrPdf(bytes) {
        try {
            const pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
            const doc = await pdfjs.getDocument({ data: bytes.slice(0), isEvalSupported: false }).promise;
            let out = '';
            const pages = Math.min(doc.numPages, 30);
            for (let i = 1; i <= pages && out.length < 2_000_000; i++) {
                const page = await doc.getPage(i);
                const vp = page.getViewport({ scale: 2 });
                const canvas = document.createElement('canvas');
                canvas.width = vp.width; canvas.height = vp.height;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                out += await ocrImage(canvas) + '\n';
                canvas.width = canvas.height = 0; // free
            }
            try { await doc.destroy(); } catch (e) { /* ignore */ }
            return out;
        } catch (e) { return ''; }
    },
    // PDF text via pdf.js (lazy-loaded + code-split so it never bloats the main
    // bundle; runs entirely in the browser, so the ZK boundary is untouched).
    async _extractPdfText(bytes) {
        try {
            const pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
            const doc = await pdfjs.getDocument({ data: bytes.slice(0), isEvalSupported: false }).promise;
            let out = '';
            const pages = Math.min(doc.numPages, 300);
            for (let i = 1; i <= pages && out.length < 2_000_000; i++) {
                const page = await doc.getPage(i);
                const content = await page.getTextContent();
                out += content.items.map((it) => it.str || '').join(' ') + '\n';
            }
            try { await doc.destroy(); } catch (e) { /* ignore */ }
            return out;
        } catch (e) { return null; }
    },
    // Store extracted text as its own sealed blob (keeps the manifest small).
    async _storeText(text) {
        const bytes = new TextEncoder().encode(text.slice(0, 2_000_000));
        const enc = await window.Vault.encryptFile(new File([bytes], 'text.txt', { type: 'text/plain' }));
        const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
        const id = await this._uploadOne(cipher, {});
        return { ref: id, key: enc.encFileKey };
    },
    _isImage(f) {
        return /^image\//.test(f.mime || '') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'].includes(((f.name || '').split('.').pop() || '').toLowerCase());
    },
    // CLIP-embed an image file via the gallery vision endpoint (transient
    // plaintext window, like /gallery/process). Returns the raw vector or null.
    async _embedImage(bytes, f) {
        if (! config.semanticEnabled || ! config.analyzeUrl) return null; // opt-out: keeps Files fully in-browser
        try {
            const fd = new FormData();
            fd.append('_token', config.token);
            fd.append('file', new File([bytes], f.name || 'image', { type: f.mime || 'image/jpeg' }));
            const res = await fetch(config.analyzeUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            if (! res.ok) return null;
            const d = await res.json();
            return Array.isArray(d.embedding) ? d.embedding : null;
        } catch (e) { return null; }
    },
    async _storeEmbedding(embedding) {
        const bytes = new TextEncoder().encode(JSON.stringify(embedding));
        const enc = await window.Vault.encryptFile(new File([bytes], 'emb.json', { type: 'application/json' }));
        const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
        const id = await this._uploadOne(cipher, {});
        return { ref: id, key: enc.encFileKey };
    },
    async _extractInto(f) {
        const buf = await fetchDecrypt(config.rawBase, f.blob, f.encFileKey);
        const bytes = new Uint8Array(buf);
        let indexed = false;
        const text = await this._extractText(bytes, f.mime, f.name);
        if (text && text.trim()) { const s = await this._storeText(text); f.textRef = s.ref; f.textKey = s.key; fileText[f.id] = text.toLowerCase(); indexed = true; }
        // Image files also get a CLIP embedding for semantic ("photo of X") search.
        if (this._isImage(f)) {
            const emb = await this._embedImage(bytes, f);
            if (emb) { const e = await this._storeEmbedding(emb); f.embRef = e.ref; f.embKey = e.key; fileEmb[f.id] = _normVec(emb); indexed = true; }
        }
        if (! indexed) f.textSkip = true; // nothing extractable — don't retry
        return indexed;
    },
    unextractedCount() {
        return (this.manifest.files || []).filter((f) => ! f.trashed && ! f.textRef && ! f.embRef && ! f.textSkip && this._textCapable(f)).length;
    },
    // Index / OCR ONE file on demand (e.g. "scan this PDF"). Forces a redo even if
    // it was skipped before (so a scan that had no text layer gets OCR'd now).
    async indexFile(f) {
        if (this._extracting || ! this._textCapable(f)) return;
        this._extracting = true;
        this.extractProgress = { done: 0, total: 1 };
        try {
            delete f.textRef; delete f.textKey; f.textSkip = false; delete fileText[f.id];
            const ok = await this._extractInto(f);
            window.LLStore.touch();
            window.llToast?.(ok ? (labels.extractOne || 'File indexed for search.') : (labels.extractEmptyOne || 'No readable text found in this file.'));
        } catch (e) {
            window.llToast?.(labels.extractFailedOne || 'Could not index this file.');
        } finally {
            this._extracting = false; this.extractProgress = null; this._contentReady++;
        }
    },
    // Backfill: index every capable file not done yet.
    async extractAllText() {
        if (this._extracting || this.state !== 'ready') return;
        const todo = (this.manifest.files || []).filter((f) => ! f.trashed && ! f.textRef && ! f.embRef && ! f.textSkip && this._textCapable(f));
        if (! todo.length) { window.llToast?.(labels.extractNone || 'Nothing to index.'); return; }
        if (! await this.$store.confirm.ask((labels.extractConfirm || 'Index :n files for content search?').replace(':n', todo.length))) return;
        this._extracting = true; this.extractProgress = { done: 0, total: todo.length };
        try {
            let since = 0;
            for (const f of todo) {
                try { await this._extractInto(f); } catch (e) { /* leave for a later run */ }
                this.extractProgress = { done: this.extractProgress.done + 1, total: todo.length };
                if (++since >= 5) { since = 0; window.LLStore.touch(); }
            }
            window.LLStore.touch();
            window.llToast?.(labels.extractDone || 'Content indexing complete.');
        } finally { this._extracting = false; this.extractProgress = null; }
    },
    // Background: index a freshly uploaded file (fire-and-forget queue), and warm
    // the in-memory index from already-extracted blobs so a search matches
    // content right after opening Files.
    _queueExtract(f) { (this._extractQ = this._extractQ || []).push(f); this._drainExtractQ(); },
    async _drainExtractQ() {
        if (this._extractQDraining) return;
        this._extractQDraining = true;
        try {
            while ((this._extractQ || []).length) {
                const f = this._extractQ.shift();
                if (! f || f.trashed || f.textRef || f.textSkip || ! this._textCapable(f)) continue;
                try { if (await this._extractInto(f)) window.LLStore.touch(); } catch (e) { /* skip */ }
            }
        } finally { this._extractQDraining = false; }
    },
    // Gently decrypt extracted-text blobs into the in-memory index — ONLY when the
    // user actually searches, once per session, throttled so it never floods
    // /files/raw. Opening Files (or the contacts avatar picker, which also boots
    // this component) must not trigger it. Bumps _contentReady so the results
    // re-filter as content arrives.
    // Debounce the search: re-filtering (and rendering) the whole tree + the
    // content index on every keystroke was slow; run it ~250ms after typing
    // stops. The input keeps updating `query` for display; `_q` drives the filter.
    _debounceSearch() {
        clearTimeout(this._searchTimer);
        this._searchTimer = setTimeout(() => {
            this._q = this.query.trim().toLowerCase();
            if (this._q) { this._ensureContentIndex(); this._semanticSearch(this._q); }
            else this._semanticHits = null;
        }, 250);
    },
    // Semantic image-file search: embed the query in CLIP space and cosine it
    // against the (warmed) image-file embeddings, so "a photo of a passport"
    // finds the scan even when the filename/text don't contain the words.
    async _semanticSearch(q) {
        try {
            if (! config.semanticEnabled) return; // server-assisted; disabled → no query egress
            if (! Object.keys(fileEmb).length) return; // nothing embedded yet
            const res = await fetch(config.embedTextUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ q }) });
            if (! res.ok) return;
            const qv = (await res.json()).embedding;
            if (! Array.isArray(qv)) return;
            const qn = _normVec(qv);
            const hits = new Set();
            for (const id in fileEmb) { if (_dotVec(qn, fileEmb[id]) > 0.22) hits.add(id); }
            if (this._q === q) { this._semanticHits = hits; this._contentReady++; } // ignore stale
        } catch (e) { /* ignore */ }
    },
    async _ensureContentIndex() {
        if (this._warming || this._contentWarmed) return;
        this._warming = true;
        try {
            let n = 0;
            for (const f of (this.manifest.files || [])) {
                if (f.trashed) continue;
                if (f.textRef && fileText[f.id] == null) {
                    try { const buf = await fetchDecrypt(config.rawBase, f.textRef, f.textKey); fileText[f.id] = new TextDecoder().decode(buf).toLowerCase(); }
                    catch (e) { /* skip */ }
                }
                if (f.embRef && fileEmb[f.id] == null) {
                    try { const buf = await fetchDecrypt(config.rawBase, f.embRef, f.embKey); fileEmb[f.id] = _normVec(JSON.parse(new TextDecoder().decode(buf))); }
                    catch (e) { /* skip */ }
                }
                if ((f.textRef || f.embRef) && ++n % 10 === 0) { this._contentReady++; await new Promise((r) => setTimeout(r, 150)); }
            }
            this._contentWarmed = true;
            this._contentReady++;
        } finally { this._warming = false; }
    },

    // url defaults to the personal upload endpoint; shared uploads pass the vault-specific URL.
    _uploadOne(file, entry, url = config.uploadUrl) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', config.token);
            data.append('file', file, file.name);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            entry.state = 'uploading';
            xhr.timeout = 300000; // never let one request wedge the whole batch
            xhr.upload.onprogress = (ev) => {
                if (ev.lengthComputable) entry.progress = Math.round((ev.loaded / ev.total) * 100);
            };
            xhr.onload = () => {
                if (xhr.status === 413) { const err = new Error('quota'); err.quota = true; reject(err); return; }
                if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('upload failed')); return; }
                try { resolve(JSON.parse(xhr.responseText).id); } catch (e) { reject(e); }
            };
            xhr.onerror = () => { const e = new Error('network'); e.unreadable = true; reject(e); };
            xhr.ontimeout = () => reject(new Error('timeout'));
            xhr.onabort = () => reject(new Error('abort'));
            try { xhr.send(data); } catch (e) { const err = new Error('read'); err.unreadable = true; reject(err); }
        });
    },

    // (Removed the old plaintext chunked upload — it streamed raw bytes + the
    // real filename. Large files go through _uploadStreamEncrypted, which uploads
    // only ciphertext with a neutral name.)

    // Constant-memory encrypted upload: encrypt the file 4 MiB at a time and
    // stream the ciphertext straight into S3 multipart parts, so neither the
    // whole file nor the whole ciphertext is ever buffered. Returns the stored
    // blob id + the wrapped per-file key. Handles any size.
    // sharedVaultId / sharedVk: when set, encrypt under VK_folder and post to
    // the vault-specific chunk endpoints; otherwise use personal config URLs.
    async _uploadStreamEncrypted(file, entry, sharedVaultId = null, sharedVk = null) {
        entry.state = 'uploading';
        // Shared path: encrypt under VK_folder; personal: under the personal VK.
        const enc = sharedVk
            ? window.Vault.newContentEncryptorWith(sharedVk)
            : window.Vault.newContentEncryptor();
        const cipherSize = window.Vault.ciphertextSize(file.size);
        // Length-hiding: the stored/ledger size is the Padmé bucket, not the exact
        // ciphertext length, so a large file's plaintext size can't be read off
        // the DB dump. The pad is random bytes streamed AFTER the self-delimiting
        // secretstream frames (past TAG_FINAL), so the decryptor never reads it —
        // exactly as padBlob() does on the buffered path.
        const paddedSize = padmeSize(cipherSize);
        const chunkInitUrl = sharedVaultId
            ? `/vaults/${sharedVaultId}/blobs/upload/init`
            : config.chunkInitUrl;
        const chunkPartUrl = sharedVaultId
            ? `/vaults/${sharedVaultId}/blobs/upload/part`
            : config.chunkPartUrl;
        const chunkCompleteUrl = sharedVaultId
            ? `/vaults/${sharedVaultId}/blobs/upload/complete`
            : config.chunkCompleteUrl;
        const chunkAbortUrl = sharedVaultId
            ? `/vaults/${sharedVaultId}/blobs/upload/abort`
            : config.chunkAbortUrl;
        const init = await fetch(chunkInitUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
            body: JSON.stringify({ name: 'blob.enc', size: paddedSize }),
        });
        if (init.status === 413) { const e = new Error('quota'); e.quota = true; throw e; }
        if (! init.ok) throw new Error('init failed');
        const { token, id: _id, partSize } = await init.json();

        // Rolling ciphertext buffer (array of Uint8Array frames); flushed as an
        // S3 part whenever it reaches partSize. Peak memory ≈ partSize + 4 MiB.
        const buf = [];
        let bufLen = 0;
        let partNum = 0;
        let sent = 0;
        const parts = [];
        const pull = (n) => {
            const out = new Uint8Array(n);
            let filled = 0;
            while (filled < n) {
                const head = buf[0];
                const need = n - filled;
                if (head.length <= need) { out.set(head, filled); filled += head.length; buf.shift(); }
                else { out.set(head.subarray(0, need), filled); buf[0] = head.subarray(need); filled += need; }
            }
            bufLen -= n;
            return out;
        };
        const flush = async (bytes) => {
            partNum++;
            const etag = await this._uploadPart(token, partNum, new Blob([bytes]), entry, sent, paddedSize, chunkPartUrl);
            parts.push({ part: partNum, etag });
            sent += bytes.length;
        };
        const feed = async (frame) => {
            buf.push(frame); bufLen += frame.length;
            while (bufLen >= partSize) { await flush(pull(partSize)); }
        };

        try {
            await feed(enc.header);
            const CH = enc.chunkSize;
            const total = file.size;
            for (let off = 0; off < total || off === 0;) {
                const end = Math.min(off + CH, total);
                const last = end >= total;
                const slice = new Uint8Array(await file.slice(off, end).arrayBuffer());
                await feed(enc.encryptChunk(slice, last));
                off = end;
                if (last) { break; }
            }
            // Stream the Padmé pad (random bytes) after the final frame so the
            // uploaded/stored length is the bucket size, not the exact ciphertext.
            for (let padLeft = paddedSize - cipherSize; padLeft > 0;) {
                const chunk = new Uint8Array(Math.min(padLeft, 1 << 20));
                crypto.getRandomValues(chunk);
                await feed(chunk);
                padLeft -= chunk.length;
            }
            if (bufLen > 0) { await flush(pull(bufLen)); } // final part (any size)

            const comp = await fetch(chunkCompleteUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ token, parts }),
            });
            if (! comp.ok) throw new Error('complete failed');
            entry.progress = 100;
            return { id: (await comp.json()).id, encFileKey: enc.sealKey() };
        } catch (e) {
            fetch(chunkAbortUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': config.token },
                body: JSON.stringify({ token }),
            }).catch(() => {});
            throw e;
        }
    },

    // Upload one part via XHR, reporting overall byte progress into the tray.
    // partUrl defaults to config.chunkPartUrl; shared uploads pass the vault URL.
    _uploadPart(token, part, blob, entry, offsetStart, totalSize, partUrl = config.chunkPartUrl) {
        return new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('_token', config.token);
            data.append('token', token);
            data.append('part', part);
            data.append('chunk', blob, 'chunk');
            const xhr = new XMLHttpRequest();
            xhr.open('POST', partUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 600000;
            xhr.upload.onprogress = (ev) => {
                if (ev.lengthComputable) entry.progress = Math.round(((offsetStart + ev.loaded) / totalSize) * 100);
            };
            xhr.onload = () => {
                if (xhr.status === 413) { const err = new Error('quota'); err.quota = true; reject(err); return; }
                if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('part failed')); return; }
                try { resolve(JSON.parse(xhr.responseText).etag); } catch (e) { reject(e); }
            };
            xhr.onerror = () => { const e = new Error('network'); e.unreadable = true; reject(e); };
            xhr.ontimeout = () => reject(new Error('timeout'));
            try { xhr.send(data); } catch (e) { const err = new Error('read'); err.unreadable = true; reject(err); }
        });
    },

    get uploading() {
        return this.uploads.some((u) => u.state === 'pending' || u.state === 'uploading');
    },
    get uploadsDone() {
        return this.uploads.filter((u) => u.state === 'done' || u.state === 'error').length;
    },
    dismissUploads() {
        this.uploads = [];
    },

    // Fetch a file's ciphertext and decrypt it in the browser back to plaintext
    // bytes. Central to download + preview, so decrypting here makes every
    // consumer zero-knowledge with no per-caller change.
    // Shared files carry row.sharedVaultId; they fetch from the vault blob route
    // and decrypt under VK_folder via decryptFileWith instead of the personal VK.
    async fetchPlain(row) {
        if (row.sharedVaultId) {
            const vk = this._folderKeys[row.sharedVaultId];
            if (! vk) throw new Error('folder key not available');
            const res = await fetch(`/vaults/${row.sharedVaultId}/blobs/raw/${row.blob}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) throw new Error('fetch failed');
            return window.Vault.decryptFileWith(await res.arrayBuffer(), row.encFileKey, vk);
        }
        return fetchDecrypt(config.rawBase, row.blob, row.encFileKey);
    },

    async download(row) {
        this._touchOpened(row.id);
        // Large files: stream-decrypt straight to disk (constant memory) when the
        // browser can write incrementally; otherwise fall back to whole-in-memory.
        if (window.showSaveFilePicker && (row.size || 0) > 64 * 1024 * 1024) {
            try { await this._downloadStreaming(row); return; }
            catch (e) { if (e && e.name === 'AbortError') return; /* else fall back */ }
        }
        this.dl = { active: true, done: 0, total: 1 };
        try {
            saveBlobAs(await this.fetchPlain(row), row.name, row.mime);
        } catch (e) {
            this.error = labels.downloadFailed;
        }
        this.dl.active = false;
    },

    // Constant-memory download: decrypt the framed ciphertext incrementally and
    // write each plaintext chunk to a user-chosen file, so a multi-GB download
    // never buffers in RAM. Uses the File System Access API.
    // Shared files: decrypt under VK_folder via beginDecryptWith; fetch from vault blob URL.
    async _downloadStreaming(row) {
        const handle = await window.showSaveFilePicker({ suggestedName: row.name || 'download' });
        const writable = await handle.createWritable();
        this.dl = { active: true, done: 0, total: row.size || 1 };
        try {
            let dec;
            let rawUrl;
            if (row.sharedVaultId) {
                const vk = this._folderKeys[row.sharedVaultId];
                if (! vk) throw new Error('folder key not available');
                dec = window.Vault.beginDecryptWith(row.encFileKey, vk);
                rawUrl = `/vaults/${row.sharedVaultId}/blobs/raw/${row.blob}`;
            } else {
                dec = window.Vault.beginDecrypt(row.encFileKey);
                rawUrl = `${config.rawBase}/${row.blob}`;
            }
            const res = await fetch(rawUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) throw new Error('fetch failed');
            const reader = res.body.getReader();

            let buf = new Uint8Array(0);
            let started = false;
            let msgLen = -1; // -1 = expecting the 4-byte length prefix
            let done = false;
            const readU32 = (b) => b[0] | (b[1] << 8) | (b[2] << 16) | (b[3] << 24);

            for (;;) {
                const { value, done: streamDone } = await reader.read();
                if (value && value.length) {
                    const merged = new Uint8Array(buf.length + value.length);
                    merged.set(buf); merged.set(value, buf.length); buf = merged;
                }
                // Parse as many complete frames as the buffer currently holds.
                for (;;) {
                    if (! started) {
                        if (buf.length < dec.headerLen) { break; }
                        dec.start(buf.subarray(0, dec.headerLen));
                        buf = buf.subarray(dec.headerLen); started = true;
                        continue;
                    }
                    if (msgLen < 0) {
                        if (buf.length < 4) { break; }
                        msgLen = readU32(buf); buf = buf.subarray(4);
                        continue;
                    }
                    if (buf.length < msgLen) { break; }
                    const { message, final } = dec.pull(buf.subarray(0, msgLen));
                    buf = buf.subarray(msgLen); msgLen = -1;
                    if (message.length) { await writable.write(message); this.dl.done += message.length; }
                    if (final) { done = true; break; }
                }
                if (done) { break; }
                if (streamDone) { break; }
            }
            await writable.close();
        } catch (e) {
            try { await writable.abort(); } catch (_) { /* ignore */ }
            this.error = labels.downloadFailed;
        }
        this.dl.active = false;
    },

    isPdf(row) {
        return row?.kind === 'file' && (row.mime === 'application/pdf' || /\.pdf$/i.test(row.name || ''));
    },

    isZip(row) {
        return row?.kind === 'file' && (row.mime === 'application/zip'
            || /\.(zip|tar|tgz|tbz2)$/i.test(row.name || '') || /\.tar\.(gz|bz2)$/i.test(row.name || ''));
    },

    isImage(row) {
        return row?.kind === 'file' && (row.mime || '').startsWith('image/');
    },

    setLayout(l) {
        this.layout = l;
        try { localStorage.setItem('ll-files-layout', l); } catch (e) { /* ignore */ }
    },

    // Sort from a column header: same column flips direction, a new one starts
    // ascending.
    sortBy(key) {
        if (this.sortKey === key) { this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'; } else { this.sortKey = key; this.sortDir = 'asc'; }
    },
    sortArrow(key) {
        return this.sortKey === key ? (this.sortDir === 'asc' ? '↑' : '↓') : '';
    },

    // Open the Paperless modal immediately, then decrypt the PDF in the
    // background so the dialog never blocks. allowDelete lets the modal offer
    // to remove the stored file after upload.
    async openPaperless(row) {
        // Sending to Paperless takes the file OUT of the zero-knowledge vault: it
        // is decrypted in the browser and uploaded as plaintext to Paperless.
        // Warn before it leaves the encrypted store.
        if (! await this.$store.confirm.ask(labels.paperlessDecryptWarn || '')) return;
        const store = Alpine.store('paperless');
        store.begin(row.name, {}, {
            allowDelete: true,
            context: { source: 'files', rowId: row.id, blob: row.blob },
        });
        try {
            const plain = await this.fetchPlain(row);
            store.setFile(new Blob([plain], { type: 'application/pdf' }));
        } catch (e) {
            store.fail(labels.downloadFailed);
        }
    },

    // After a file-browser upload the user may choose to delete the original;
    // remove it from the manifest and drop its blob (best effort).
    onPaperlessSent(detail) {
        const ctx = detail?.context;
        if (! detail?.deleteAfter || ctx?.source !== 'files') return;
        const row = this.manifest.files.find((x) => x.id === ctx.rowId);
        if (! row) return;
        // If the deleted file is the one open in the viewer, close it.
        if (this.viewer.open && this.viewer.row?.id === row.id) this.closeViewer();
        const blobs = [row.blob, ...(row.versions ?? []).map((v) => v.blob)];
        this._spliceWhere(this.manifest.files, (x) => x.id === ctx.rowId);
        this.persist();
        this._freeBlobs(blobs);
    },

    /* ---- Preview & editor (all in the browser, nothing readable leaves it) ---- */

    async openFile(row) {
        this._touchOpened(row.id);
        this.dl = { active: true, done: 0, total: 1 };
        try {
            const plain = await this.fetchPlain(row);
            this.dl.active = false;
            const mime = row.mime || 'application/octet-stream';

            // SVG is the one "image" type that can carry markup/external refs;
            // never render it inline — let it fall through to download.
            if (mime.startsWith('image/') && ! mime.includes('svg')) {
                this.viewer = { open: true, kind: 'image', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            if (mime === 'application/pdf') {
                this.viewer = { open: true, kind: 'pdf', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            if (mime.startsWith('video/')) {
                this.viewer = { open: true, kind: 'video', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            if (mime.startsWith('audio/')) {
                this.viewer = { open: true, kind: 'audio', src: URL.createObjectURL(new Blob([plain], { type: mime })), row, saving: false, saved: false };
                return;
            }
            // Editable text: valid UTF-8 and reasonably small.
            if (row.size <= 2 * 1024 * 1024) {
                try {
                    const text = new TextDecoder('utf-8', { fatal: true }).decode(plain);
                    this.viewer = { open: true, kind: 'text', src: '', row, saving: false, saved: false };
                    this.$nextTick(() => this.mountEditor(text, row.name));
                    return;
                } catch (e) { /* binary: fall through */ }
            }
            this.viewer = { open: true, kind: 'none', src: '', row, saving: false, saved: false };
        } catch (e) {
            this.dl.active = false;
            this.error = labels.downloadFailed;
        }
    },

    // Images in the current view, in display order — the slideshow set.
    get viewerImages() {
        return this.rows.filter((r) => r.kind === 'file' && (r.mime || '').startsWith('image/'));
    },

    // Position of the open image within that set (-1 if not an image view).
    get viewerIndex() {
        if (this.viewer.kind !== 'image' || ! this.viewer.row) return -1;
        const key = this.rowKey(this.viewer.row);
        return this.viewerImages.findIndex((r) => this.rowKey(r) === key);
    },

    // More than one image ⇒ offer prev/next navigation.
    get viewerHasGallery() {
        return this.viewer.kind === 'image' && this.viewerImages.length > 1;
    },

    // Step to another image, wrapping around so every image is reachable.
    viewerStep(dir) {
        const imgs = this.viewerImages;
        if (imgs.length < 2) return;
        const i = this.viewerIndex;
        if (i < 0) return;
        const next = imgs[(i + dir + imgs.length) % imgs.length];
        if (this.viewer.src) URL.revokeObjectURL(this.viewer.src);
        this.openFile(next);
    },

    async mountEditor(text, filename) {
        const { EditorView, EditorState, Compartment, LanguageDescription, languages, basicSetup } = await loadCodeMirror();
        if (! this.languageOptions.length) {
            this.languageOptions = languages.map((l) => l.name).sort((a, b) => a.localeCompare(b));
        }
        this.langComp = new Compartment();
        this.editorView = new EditorView({
            parent: this.$refs.viewerEditor,
            state: EditorState.create({
                doc: text,
                extensions: [
                    basicSetup,
                    this.langComp.of([]),
                    EditorView.theme({ '&': { height: '60vh' }, '.cm-scroller': { overflow: 'auto' } }),
                ],
            }),
        });
        const detected = filename ? LanguageDescription.matchFilename(languages, filename) : null;
        if (detected) {
            this.applyEditorLanguage(detected);
        }
    },

    onEditorLanguageChange() {
        const desc = cmModule?.languages.find((l) => l.name === this.editorLang);
        desc ? this.applyEditorLanguage(desc) : this.editorView.dispatch({ effects: this.langComp.reconfigure([]) });
    },

    applyEditorLanguage(desc) {
        this.editorLang = desc.name;
        desc.load().then((support) => this.editorView.dispatch({ effects: this.langComp.reconfigure(support) }));
    },

    // Save the edited text: upload a NEW file blob, point the row at it, then
    // discard the old blob — an atomic swap from the manifest's viewpoint.
    // Shared files: encrypt under VK_folder, upload to vault blob URL.
    async saveText() {
        const row = this.viewer.row;
        if (! this.editorView || ! row) return;
        if (! this._canEditActive()) return;
        this.viewer.saving = true;
        this.viewer.saved = false;
        try {
            const text = this.editorView.state.doc.toString();
            const bytes = new TextEncoder().encode(text);

            // Zero-knowledge: encrypt the edited bytes with a FRESH per-file key
            // (exactly like upload) and upload only the ciphertext. Uploading raw
            // bytes here would leak plaintext to the server AND leave the manifest
            // row's old wrapped key stale, making the file undecryptable.
            let enc, uploadUrl;
            if (row.sharedVaultId) {
                const vk = this._folderKeys[row.sharedVaultId];
                if (! vk) throw new Error('folder key not available');
                enc = window.Vault.encryptContentWith(bytes, { name: row.name, mime: row.mime || 'text/plain' }, vk);
                uploadUrl = `/vaults/${row.sharedVaultId}/blobs/upload`;
            } else {
                enc = window.Vault.encryptContent(bytes, { name: row.name, mime: row.mime || 'text/plain' });
                uploadUrl = config.uploadUrl;
            }
            const data = new FormData();
            data.append('_token', config.token);
            data.append('file', new File([enc.blob], 'blob.enc', { type: 'application/octet-stream' }));
            const res = await fetch(uploadUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            if (! res.ok) throw new Error('upload failed');
            const { id } = await res.json();

            if (row.sharedVaultId) {
                const vaultId = row.sharedVaultId;
                const entry = (this.sharedTree[vaultId]?.files ?? []).find((f) => f.id === row.id);
                if (entry) {
                    const oldBlob = entry.blob;
                    if (oldBlob && oldBlob !== id) {
                        entry.versions = entry.versions ?? [];
                        entry.versions.unshift({ id: crypto.randomUUID(), blob: oldBlob, encFileKey: entry.encFileKey, size: entry.size, mime: entry.mime, name: entry.name, created: new Date().toISOString() });
                        this._trimVersions(entry);
                        this._logActivity(entry, 'version');
                    }
                    entry.blob = id;
                    entry.size = bytes.length;
                    entry.encFileKey = enc.encFileKey;
                    await this._saveFolder(vaultId, { op: 'upsertFile', item: entry });
                }
            } else {
                const entry = this.manifest.files.find((f) => f.id === row.id);
                if (entry) {
                    // Snapshot the outgoing blob as a version (kept + decryptable via
                    // its own wrapped key) before pointing the row at the new blob,
                    // then cap the history — the overflow blobs are reclaimed.
                    const oldBlob = entry.blob;
                    if (oldBlob && oldBlob !== id) {
                        entry.versions = entry.versions ?? [];
                        entry.versions.unshift({ id: crypto.randomUUID(), blob: oldBlob, encFileKey: entry.encFileKey, size: entry.size, mime: entry.mime, name: entry.name, created: new Date().toISOString() });
                        this._trimVersions(entry);
                        this._logActivity(entry, 'version');
                    }
                    entry.blob = id;
                    entry.size = bytes.length;
                    entry.encFileKey = enc.encFileKey; // the new blob's wrapped key
                }
                this.persist();
            }

            row.blob = id;
            row.size = bytes.length;
            row.encFileKey = enc.encFileKey;
            this.viewer.saved = true;
            this.refreshUsage();
        } catch (e) {
            this.error = labels.saveFailed;
        }
        this.viewer.saving = false;
    },

    closeViewer() {
        if (this.viewer.src) {
            URL.revokeObjectURL(this.viewer.src);
        }
        if (this.editorView) {
            this.editorView.destroy();
            this.editorView = null;
        }
        this.viewer = { open: false, kind: 'none', src: '', row: null, saving: false, saved: false };
    },

    /* ---- Shared-folder create / share / accept / members / delete ---- */
    /* Mirrors passwords.js createSharedVault / share dialog / acceptInvite /
       openManageMembers / removeMember / deleteSharedVault / _deleteSharedVaultNow.
       Folder-specific differences:
         - POST /vaults sends kind:'folder'
         - sealed manifest shape is {name, folders:[], files:[]} not {name, items:[]}
         - re-seal on rotate uses sharedTree[vaultId] not sharedItems[vaultId]
         - TOFU knownFingerprints lives in the personal manifest (window.LLStore.data) */

    async createSharedFolder(name) {
        const folderName = (name || '').trim();
        if (! folderName) return;
        try {
            const vk = await VaultShareCrypto.newVaultKey();
            const idk = await Vault.ensureIdentityKeys();
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vk, idk.pub);
            const { id } = await apiRequest('POST', '/vaults', { wrapped_vault_key: wrapped, kind: 'folder' });
            // atob decodes standard base64 (ORIGINAL variant), matching vault.js's
            // base64_variants.ORIGINAL encoding. unb64 is not exported from vault.js.
            const vkBytes = Uint8Array.from(atob(vk), (c) => c.charCodeAt(0));
            const manifest = { name: folderName, folders: [], files: [] };
            const sealed = await VaultShareCrypto.sealVaultManifest(manifest, vkBytes);
            const res = await apiRequest('PUT', '/vaults/' + id + '/store', { sealed_manifest: sealed, expected_version: 0 });
            const version = (res && typeof res.version === 'number') ? res.version : 1;
            this._folderKeys[id] = vkBytes;
            this._folderVersion[id] = version;
            this.sharedTree[id] = { folders: [], files: [] };
            this.sharedFolders.push({ id, vaultId: id, name: folderName, role: 'manage', version });
        } catch (e) {
            window.llToast(labels.saveFailed || '');
        }
    },

    // Convert a PERSONAL folder in place into a shared folder: create the vault,
    // migrate the subtree's blobs (re-wrap each per-file key under VK_folder, the
    // ciphertext bytes are unchanged), seal the shared manifest, then free the
    // personal blobs. Client-side only — the server never sees plaintext/VK_folder.
    // Returns the new vaultId. On failure, aborts and leaves the personal folder intact.
    async convertFolderToShared(folderId) {
        const root = this.manifest.folders.find((f) => f.id === folderId);
        if (! root) throw new Error('folder not found');
        const { folders: subFolders, files: subFiles } = collectSubtree(this.manifest.folders, this.manifest.files, folderId);

        // 1. New vault + VK_folder.
        const vkB64 = await VaultShareCrypto.newVaultKey();
        const idk = await Vault.ensureIdentityKeys();
        const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vkB64, idk.pub);
        const { id: vaultId } = await apiRequest('POST', '/vaults', { wrapped_vault_key: wrapped, kind: 'folder' });
        const vkBytes = Uint8Array.from(atob(vkB64), (c) => c.charCodeAt(0));

        const uploadedBlobs = []; // shared blob ids, for rollback on failure
        try {
            // 2. Migrate each file blob: fetch ciphertext, re-wrap key under VK_folder, re-upload.
            // Ciphertext bytes are identical to the personal blob — only the key wrapping changes.
            const newFiles = [];
            for (const file of subFiles) {
                const rawKey = window.Vault.unwrapContentKey(file.encFileKey); // unwrap under personal VK
                const encFileKey = window.Vault.sealContentKeyWith(rawKey, vkBytes); // re-wrap under VK_folder
                const cipher = await fetchBlobBuffer(`${config.rawBase}/${file.blob}`); // raw ciphertext (unchanged)
                const form = new FormData();
                form.append('file', new Blob([cipher], { type: 'application/octet-stream' }), 'blob');
                const up = await apiRequest('POST', '/vaults/' + vaultId + '/blobs/upload', form);
                uploadedBlobs.push(up.id);
                newFiles.push({ ...file, blob: up.id, encFileKey, textRef: undefined, embRef: undefined, versions: [] });
            }

            // 3. Seal + PUT the shared manifest at version 0.
            const manifest = { name: root.name, folders: subFolders, files: newFiles };
            const sealed = await VaultShareCrypto.sealVaultManifest(manifest, vkBytes);
            const res = await apiRequest('PUT', '/vaults/' + vaultId + '/store', { sealed_manifest: sealed, expected_version: 0 });
            this._folderKeys[vaultId] = vkBytes;
            this._folderVersion[vaultId] = (res && typeof res.version === 'number') ? res.version : 1;
            this.sharedTree[vaultId] = { folders: subFolders, files: newFiles };
            this.sharedFolders.push({ id: vaultId, vaultId, name: root.name, role: 'manage', version: this._folderVersion[vaultId] });
        } catch (e) {
            // Rollback: delete uploaded shared blobs + the vault; personal folder stays intact.
            for (const b of uploadedBlobs) { try { await apiRequest('DELETE', '/vaults/' + vaultId + '/blobs/' + b); } catch (_) {} }
            try { await apiRequest('DELETE', '/vaults/' + vaultId); } catch (_) {}
            throw e;
        }

        // 4. Remove the migrated subtree from the personal manifest + free old blobs (only after success).
        const keepFolderIds = new Set(subFolders.map((f) => f.id));
        const oldBlobs = subFiles.flatMap((f) => [f.blob, f.textRef, f.embRef, ...(f.versions ?? []).map((v) => v.blob)].filter(Boolean));
        window.LLStore.data.fileFolders = this.manifest.folders.filter((f) => ! keepFolderIds.has(f.id));
        window.LLStore.data.files = this.manifest.files.filter((f) => ! keepFolderIds.has(f.folder ?? null));
        this.manifest.folders = window.LLStore.data.fileFolders;
        this.manifest.files = window.LLStore.data.files;
        window.LLStore.touch();
        this._freeBlobs(oldBlobs); // existing personal blob-free queue

        return vaultId;
    },

    openShareFolderDialog(vaultId) {
        this.shareFolderDialog = { open: true, vaultId, identifier: '', role: 'read', lookingUp: false, resolved: null, fingerprintStatus: null, sharing: false, notice: '' };
    },
    closeShareFolderDialog() { this.shareFolderDialog = { ...this.shareFolderDialog, open: false }; },

    // ---- Unified share dialog methods ----
    openUnifiedShare(row) {
        const isFolder = row.kind === 'folder';
        // A shared-folder row already has a vaultId; a personal folder converts on first invite.
        const vaultId = (isFolder && row.vaultId) ? row.vaultId : null;
        // For a shared folder (has vaultId) default to People; for files default to Link.
        // Personal (not-yet-shared) folders also default to People (shows the enable button).
        const tab = (isFolder && vaultId) ? 'people' : (isFolder ? 'people' : 'link');
        this.unifiedShare = { open: true, row, isFolder, vaultId, tab };
        // Populate share state for the Link tab without triggering the old standalone dialog.
        this.openShare(row);
        this.share.open = false; // unified dialog owns the UI; old standalone dialog must not open
        if (vaultId) this.openManageFolderMembers(vaultId); // load members for the People tab
        // Reset the invite sub-state for a clean dialog.
        this.shareFolderDialog = { open: false, vaultId, identifier: '', role: 'read', lookingUp: false, resolved: null, fingerprintStatus: null, sharing: false, notice: '' };
    },
    closeUnifiedShare() {
        this.unifiedShare = { ...this.unifiedShare, open: false };
        this.closeShare();
    },
    // Step 1 for a personal (not-yet-shared) folder: confirm + convert, then reveal the invite form.
    async enableSharing() {
        if (! await this.$store.confirm.ask(labels.convertConfirm || '')) return;
        let vaultId;
        try { vaultId = await this.convertFolderToShared(this.unifiedShare.row.id); }
        catch (e) { this.error = (e && e.message) || String(e); return; }
        this.unifiedShare.vaultId = vaultId;
        this.shareFolderDialog.vaultId = vaultId;
        this.activeShared = null; // stay at root; the folder now appears as shared
        this.openManageFolderMembers(vaultId);
    },
    // Invite handler for the People tab (only called once vaultId is set).
    async unifiedInvite() {
        const vaultId = this.unifiedShare.vaultId;
        if (! vaultId) return; // enableSharing() must run first
        // Use the existing invite flow: shareFolderDialog already has the vaultId.
        this.shareFolderDialog.vaultId = vaultId;
        await this.confirmShareFolder();
    },

    async lookUpFolderRecipient() {
        const d = this.shareFolderDialog; if (! d.vaultId || ! d.identifier.trim()) return;
        d.lookingUp = true; d.resolved = null; d.fingerprintStatus = null; d.notice = '';
        try {
            const res = await fetch('/vaults/' + d.vaultId + '/resolve-recipient', {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ identifier: d.identifier.trim() }),
            });
            if (res.status === 422) { d.notice = labels.recipientNotFound || ''; return; }
            if (! res.ok) { d.notice = labels.saveFailed || ''; return; }
            const data = await res.json();
            // Verify server fingerprint matches client-computed one.
            const computed = await VaultShareCrypto.fingerprint(data.public_key);
            if (computed !== data.fingerprint) {
                d.notice = (labels.fingerprintChangedWarn || '') + ' (server/client mismatch)';
                return;
            }
            d.resolved = data; // {user_id, public_key, fingerprint}
            // TOFU check: look up stored fingerprint in personal manifest.
            const store = window.LLStore.data;
            store.knownFingerprints = store.knownFingerprints || {};
            const stored = store.knownFingerprints[data.user_id];
            if (! stored) {
                d.fingerprintStatus = 'new'; // show fingerprint for out-of-band verification
            } else if (stored === data.fingerprint) {
                d.fingerprintStatus = 'verified';
            } else {
                d.fingerprintStatus = 'changed';
                d.notice = labels.fingerprintChangedWarn || '';
                d.resolved = null; // block share
            }
        } finally { d.lookingUp = false; }
    },

    async confirmShareFolder() {
        const d = this.shareFolderDialog;
        if (! d.resolved || d.fingerprintStatus === 'changed') return;
        if (d.fingerprintStatus === 'new') {
            // Store fingerprint in personal manifest on confirmation.
            const store = window.LLStore.data;
            store.knownFingerprints = store.knownFingerprints || {};
            store.knownFingerprints[d.resolved.user_id] = d.resolved.fingerprint;
            // Persist the updated personal manifest so the fingerprint is durably stored.
            if (window.LLStore && typeof window.LLStore.flush === 'function') window.LLStore.flush();
        }
        d.sharing = true; d.notice = '';
        try {
            const vkBytes = this._folderKeys[d.vaultId];
            if (! vkBytes) { d.notice = labels.saveFailed || ''; return; }
            const vkB64 = btoa(String.fromCharCode(...new Uint8Array(vkBytes)));
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vkB64, d.resolved.public_key);
            const res = await fetch('/vaults/' + d.vaultId + '/members', {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ user_id: d.resolved.user_id, role: this._clientToServerRole(d.role), wrapped_vault_key: wrapped, recipient_fingerprint: d.resolved.fingerprint }),
            });
            if (res.status === 422 || (res.status >= 300 && res.status < 500)) {
                const body = await res.json().catch(() => ({}));
                if (res.status === 422 && body && Object.keys(body).length) {
                    d.notice = labels.alreadyMember || '';
                } else {
                    d.notice = labels.saveFailed || '';
                }
                return;
            }
            if (! res.ok) { d.notice = labels.saveFailed || ''; return; }
            d.notice = labels.inviteSent || '';
            d.resolved = null; d.identifier = '';
        } finally { d.sharing = false; }
    },

    async acceptFolderInvite(inv) {
        const id = await Vault.ensureIdentityKeys();
        // Verify the wrapped key actually decrypts before accepting.
        try {
            await VaultShareCrypto.unwrapVaultKey(inv.wrapped_vault_key, id.pub, id.sk);
        } catch (_e) {
            window.llToast(labels.inviteInvalid || '');
            return;
        }
        try {
            await apiRequest('POST', '/vaults/' + inv.vault_id + '/members/' + inv.member_id + '/accept');
            this.pendingFolderInvites = this.pendingFolderInvites.filter((p) => p.member_id !== inv.member_id);
            await this._loadSharedFolders();
        } catch (_e) {
            window.llToast(labels.saveFailed || '');
        }
    },

    // Open the manage-members panel for a shared folder: fetch current member list.
    async openManageFolderMembers(vaultId) {
        this.managingFolderVaultId = vaultId;
        this.managingFolderVaultMembers = [];
        this.managingFolderVaultLoading = true;
        try {
            const members = await apiRequest('GET', '/vaults/' + vaultId + '/members');
            this.managingFolderVaultMembers = Array.isArray(members) ? members : [];
        } catch (_e) {
            window.llToast(labels.saveFailed || '');
        } finally {
            this.managingFolderVaultLoading = false;
        }
    },

    // Change the role of an existing member (manager only).
    async changeFolderMemberRole(memberId, newRole) {
        const vaultId = this.managingFolderVaultId;
        if (! vaultId) return;
        try {
            // Fix 3: use apiRequest for a single audited CSRF/header path, consistent with other member ops.
            await apiRequest('PATCH', '/vaults/' + vaultId + '/members/' + memberId, { role: this._clientToServerRole(newRole) });
            this.managingFolderVaultMembers = this.managingFolderVaultMembers.map((m) =>
                m.id === memberId ? { ...m, role: this._clientToServerRole(newRole) } : m,
            );
        } catch (_e) {
            window.llToast(labels.saveFailed || '');
        }
    },

    // Remove a member from a shared folder via atomic key rotation.
    // Generates a new VK_folder, re-seals the tree for all remaining active members,
    // and atomically replaces the sealed manifest + removes the revoked member.
    async removeFolderMember(memberId, _memberUserId) {
        if (! await this.$store.confirm.ask(labels.removeMemberConfirm || '')) return;
        const vaultId = this.managingFolderVaultId;
        if (! vaultId) return;
        const vkBytes = this._folderKeys && this._folderKeys[vaultId];
        if (! vkBytes) { window.llToast(labels.saveFailed || ''); return; }
        this.rotatingFolderKeys = true;
        try {
            // Generate a new vault key for forward secrecy.
            const newVkB64 = await VaultShareCrypto.newVaultKey();
            const newVkBytes = Uint8Array.from(atob(newVkB64), (c) => c.charCodeAt(0));
            // Remaining active members (exclude the one being removed).
            const remaining = this.managingFolderVaultMembers.filter(
                (m) => m.id !== memberId && m.status === 'active' && m.public_key,
            );
            // Re-wrap the new vault key for each remaining member.
            const members = await Promise.all(remaining.map(async (m) => ({
                user_id: m.user_id,
                wrapped_vault_key: await VaultShareCrypto.wrapVaultKeyFor(newVkB64, m.public_key),
            })));
            // Re-seal the current folder tree under the new key.
            const folder = this.sharedFolders.find((sf) => sf.vaultId === vaultId);
            const tree = this.sharedTree[vaultId] || { folders: [], files: [] };
            const manifest = { name: folder ? folder.name : '', folders: tree.folders || [], files: tree.files || [] };
            const sealed = await VaultShareCrypto.sealVaultManifest(manifest, newVkBytes);
            const expectedVersion = this._folderVersion[vaultId] ?? 0;
            const res = await fetch('/vaults/' + vaultId + '/rotate', {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ sealed_manifest: sealed, expected_version: expectedVersion, members, remove_member_id: memberId }),
            });
            if (res.status === 409) {
                // Version conflict: reload folder state and let the caller retry.
                await this._loadSharedFolders();
                window.llToast(labels.saveConflict || '');
                return;
            }
            if (! res.ok) { window.llToast(labels.saveFailed || ''); return; }
            const { version } = await res.json();
            // Update in-memory state with the new key and version.
            this._folderKeys[vaultId] = newVkBytes;
            this._folderVersion[vaultId] = version;
            this.managingFolderVaultMembers = this.managingFolderVaultMembers.filter((m) => m.id !== memberId);
            window.llToast(labels.memberRemoved || '');
        } catch (_e) {
            window.llToast(labels.saveFailed || '');
        } finally {
            this.rotatingFolderKeys = false;
        }
    },

    // Delete a shared folder permanently (manager only), WITHOUT a confirm prompt —
    // shared by the per-folder delete button and any reset path.
    async _deleteSharedFolderNow(vaultId) {
        await postForm('/vaults/' + vaultId, null, 'DELETE');
        this.sharedFolders = this.sharedFolders.filter((sf) => sf.vaultId !== vaultId);
        delete this.sharedTree[vaultId];
        delete this._folderKeys[vaultId];
        delete this._folderVersion[vaultId];
        if (this.activeShared === vaultId) this.activeShared = null;
        if (this.managingFolderVaultId === vaultId) this.managingFolderVaultId = null;
    },

    async deleteSharedFolder(vaultId) {
        if (! await this.$store.confirm.ask(labels.deleteVaultConfirm || '')) return;
        try { await this._deleteSharedFolderNow(vaultId); }
        catch (_e) { window.llToast(labels.saveFailed || ''); }
    },
});
