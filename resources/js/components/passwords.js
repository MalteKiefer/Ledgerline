// Password manager (ZK). Extracted from app.js.
import { zkModule } from '../shared/zk-module';
import { PW_WORDS } from '../shared/wordlists';
import { parseCsv as pwParseCsv, detectCsv as pwDetectCsv, cardBrand as pwCardBrand, totpSecret as pwTotpSecret, totp as pwTotp, pwScore as pwStrength } from '../passwords-util';
import { Vault, VaultShareCrypto } from '../vault';
import { apiRequest, jsonHeaders, getJson, postForm } from '../shared/api';
import { estimateStrength } from '../shared/strength';
import { buildBitwardenJson, buildCsv, encryptExport } from '../shared/vault-export';
import { saveBlobAs } from '../shared/dom';

// Base set of secret field keys. versionDiff extends this with the extra
// opaque-array fields so adding a new base secret can't silently leak in diffs.
export const SECRET_FIELDS = ['password', 'totp', 'cvv', 'pin', 'licensekey', 'privateKey'];

/**
 * Canonical type registry — single source of truth for type keys and their
 * field definitions. Exported for use in tests and derived computations.
 * Each value: { icon: string, fields: [key, inputType][] }
 */
export const TYPES = {
    login: { icon: 'user', fields: [['username', 'text'], ['password', 'password'], ['urls', 'urls'], ['totp', 'password'], ['note', 'textarea']] },
    password: { icon: 'key', fields: [['password', 'password'], ['note', 'textarea']] },
    card: { icon: 'credit-card', fields: [['cardholder', 'text'], ['number', 'text'], ['expiry', 'text'], ['cvv', 'password'], ['pin', 'password'], ['note', 'textarea']] },
    wifi: { icon: 'wifi', fields: [['ssid', 'text'], ['password', 'password'], ['security', 'select'], ['hidden', 'checkbox'], ['note', 'textarea']] },
    license: { icon: 'document', fields: [['product', 'text'], ['licensekey', 'textarea'], ['owner', 'text'], ['email', 'text'], ['note', 'textarea']] },
    server: { icon: 'server', fields: [['host', 'text'], ['port', 'text'], ['username', 'text'], ['password', 'password'], ['note', 'textarea']] },
    passkey: { icon: 'finger-print', fields: [['rpId', 'text'], ['userName', 'text'], ['userDisplayName', 'text'], ['note', 'textarea']] },
    identity: { icon: 'identification', fields: [['firstName', 'text'], ['lastName', 'text'], ['email', 'text'], ['phone', 'text'], ['company', 'text'], ['street', 'text'], ['city', 'text'], ['state', 'text'], ['zip', 'text'], ['country', 'text'], ['note', 'textarea']] },
    secure_note: { icon: 'document-text', fields: [['note', 'textarea']] },
};

export default (config = {}, labels = {}) => ({
    ...zkModule({ map: { secrets: 'items', secretFolders: 'folders' }, onLock: (self) => { self.current = null; self.draft = null; self.view = 'list'; self._sharedKeys = {}; self.sharedItems = {}; self.sharedVaults = []; self._sharedVersion = {}; if (self._strengthTimer) { clearTimeout(self._strengthTimer); self._strengthTimer = null; } } }),

    items: [],
    folders: [],
    sharedVaults: [],  // [{id, name, role, shared: true, vaultId}]
    sharedItems: {},   // map of vaultId -> items array
    _sharedVersion: {}, // map of vaultId -> version number
    _sharedKeys: {}, // map of vaultId -> Uint8Array vault key (in-memory only)
    view: 'list', // list | trash
    current: null, // item being viewed (live ref into items)
    draft: null, // editable copy while creating/editing (null = not editing)
    selectedIds: [], // multi-select for bulk actions
    _dragId: null,   // id being dragged (or null)
    _dragOver: null, // tresor id being hovered for drop
    tfaReady: false, // 2fa.directory dataset loaded (gates the "offers 2FA" hint)
    _tfaMap: null, // { domain: documentationUrl }
    filterType: '',
    filterFolder: '', // '' = all, '_none' = no folder, else folder id
    filterTag: '',
    config,
    reveal: {},
    totpNow: {},
    _totpTimer: null,
    historyOpen: false, // inline version-history accordion in the detail view
    wifiQr: '',
    gen: {
        open: false, target: null, mode: 'chars', preview: '',
        length: 20, upper: true, lower: true, digits: true, symbols: true, similar: false,
        words: 4, lang: 'en', sep: '-', capitalize: true, number: true,
    },
    strengthScore: null,  // 0–4 from zxcvbn (null = not yet computed)
    strengthLabel: '',    // localised label (very weak … strong)
    crackTime: '',        // human crack-time string from zxcvbn
    _strengthTimer: null, // debounce handle
    genLangs: ['en', 'de', 'es', 'fr', 'it'],
    pendingInvites: [],  // [{vault_id, member_id, role, wrapped_vault_key}]
    shareDialog: { open: false, vaultId: null, identifier: '', role: 'read', lookingUp: false, resolved: null, fingerprintStatus: null, sharing: false, notice: '' },
    managingVaultId: null, // vault id open in the members panel
    managingVaultMembers: [], // [{id, user_id, role, status, recipient_fingerprint, public_key}]
    managingVaultLoading: false,
    rotatingKeys: false,

    // Field metadata per type: [key, inputType]. Labels come from labels.fields.
    types: TYPES,
    secretFields: [...SECRET_FIELDS],
    securityOptions: ['nopass', 'WEP', 'WPA', 'WPA2', 'WPA3', 'WPA2-Enterprise', 'WPA3-Enterprise'],

    async init() {
        await this._initZk();
        this._migrateVaults();
        this._totpTimer = setInterval(() => this._tickTotp(), 1000);
        this._tickTotp();
        this._autoSelect();
        // Keep the first matching entry selected as the state/filters change.
        this.$watch('state', (s) => { if (s === 'ready') { this._migrateVaults(); this._autoSelect(); this._loadSharedVaults(); } });
        // Trusted-device auto-unlock: _initZk() may already have set state to
        // 'ready' BEFORE the watcher above was registered, so the watch never
        // fires and shared vaults would never load (shown right after create via
        // the optimistic push, but gone on a hard refresh). Load them directly.
        if (this.state === 'ready') this._loadSharedVaults();
        for (const p of ['query', 'filterType', 'filterTag', 'view']) this.$watch(p, () => this._autoSelect());
        this.$watch('filterFolder', () => { this.selectedIds = []; this._autoSelect(); });
        this.$watch('view', () => this.clearSelection());
        this._loadTfa();
        // Strength estimation: watch the generator preview and the draft password field.
        this.$watch('gen.preview', (v) => this._updateStrength(v));
        this.$watch('draft', (d) => { this._updateStrength(d?.fields?.password || ''); });
    },
    // Auto-select the first item in the current list (unless something is being
    // edited or the current selection is still visible).
    _autoSelect() {
        if (this.draft) return;
        const f = this.filtered;
        if (this.current && f.some((x) => x.id === this.current.id)) return;
        this.current = f[0] || null;
        this.reveal = {};
        if (this.current) this._refreshWifiQr(this.current);
    },

    typeList() { return Object.keys(this.types); },
    get creatableTypes() { return Object.keys(this.types).filter((t) => t !== 'passkey'); },
    typeLabel(t) { return (labels.types && labels.types[t]) || t; },
    fieldLabel(k) { return (labels.fields && labels.fields[k]) || k; },
    fieldsOf(t) { return this.types[t]?.fields || []; },
    isSecret(k) { return this.secretFields.includes(k); },

    get liveItems() { return this.items.filter((x) => ! x.trashed); },
    get trashCount() { return this.items.filter((x) => x.trashed).length; },
    countOf(t) { return this.liveItems.filter((x) => x.type === t).length; },
    folderName(id) { return (this.folders.find((f) => f.id === id) || {}).name || ''; },
    get allTags() { const s = new Set(); for (const x of this.liveItems) for (const t of (x.tags || [])) s.add(t); return [...s].sort((a, b) => a.localeCompare(b)); },
    get filtered() {
        if (this.view === 'health') return this.healthProblems.map((o) => o.x);
        const q = this.query.trim().toLowerCase();
        // When a shared vault is selected, serve its items (read-only, no trash/health).
        const isShared = this.view === 'list' && this.sharedVaults.some((sv) => sv.id === this.filterFolder);
        if (isShared) {
            const list = (this.sharedItems[this.filterFolder] || []);
            return list
                .filter((x) => ! this.filterType || x.type === this.filterType)
                .filter((x) => ! this.filterTag || (x.tags || []).includes(this.filterTag))
                .filter((x) => ! q || (x.title || '').toLowerCase().includes(q)
                    || (x.tags || []).some((t) => t.toLowerCase().includes(q))
                    || Object.values(x.fields || {}).some((v) => (typeof v === 'string' && v.toLowerCase().includes(q)) || (Array.isArray(v) && v.some((u) => String(u).toLowerCase().includes(q))))
                    || (x.custom || []).some((c) => (c.value || '').toLowerCase().includes(q) || (c.label || '').toLowerCase().includes(q)))
                .sort((a, b) => ((b.favorite ? 1 : 0) - (a.favorite ? 1 : 0)) || (a.title || '').localeCompare(b.title || ''));
        }
        // Health and Trash span every vault; the list view is scoped to filters.
        const global = this.view === 'trash';
        let list = this.view === 'trash' ? this.items.filter((x) => x.trashed) : this.liveItems;
        // "All vaults" (no folder selected) in the list view spans BOTH the
        // personal manifest AND every shared vault the user can read — otherwise
        // items moved into / created in a shared Tresor (or a converted personal
        // vault) would be invisible unless that vault is explicitly selected.
        if (this.view === 'list' && this.filterFolder === '') {
            const shared = Object.values(this.sharedItems || {}).flat().filter((x) => x && ! x.trashed);
            list = list.concat(shared);
        }
        return list
            .filter((x) => global || ! this.filterType || x.type === this.filterType)
            .filter((x) => global || this.filterFolder === '' || (this.filterFolder === '_none' ? ! x.folder : x.folder === this.filterFolder))
            .filter((x) => global || ! this.filterTag || (x.tags || []).includes(this.filterTag))
            .filter((x) => ! q || (x.title || '').toLowerCase().includes(q)
                || (x.tags || []).some((t) => t.toLowerCase().includes(q))
                || Object.values(x.fields || {}).some((v) => (typeof v === 'string' && v.toLowerCase().includes(q)) || (Array.isArray(v) && v.some((u) => String(u).toLowerCase().includes(q))))
                || (x.custom || []).some((c) => (c.value || '').toLowerCase().includes(q) || (c.label || '').toLowerCase().includes(q)))
            .sort((a, b) => ((b.favorite ? 1 : 0) - (a.favorite ? 1 : 0)) || (a.title || '').localeCompare(b.title || ''));
    },

    /* ---- Vaults (formerly folders) ----
       One-time migration to the vault model: collapse everything into a single
       "Privat" vault and drop the old folders. Vaults carry a `role` so vault
       sharing (read / edit / manage) can be layered on later. Runs once, gated
       by a durable flag in the sealed manifest. */
    _migrateVaults() {
        if (this.state !== 'ready' || ! window.LLStore.data) return;
        const store = window.LLStore.data;
        // Every vault gets a role (owner = manage) so the UI can gate actions.
        for (const v of this.folders) if (! v.role) v.role = 'manage';
        if (store.pwVaultMigrated) return;
        const id = crypto.randomUUID();
        const privat = { id, name: 'Privat', role: 'manage' };
        this.folders.splice(0, this.folders.length, privat); // keep the bound ref
        for (const it of this.items) it.folder = id;
        store.pwVaultMigrated = true;
        this.filterFolder = '';
        this._save();
    },
    // The user's role in a vault: 'read' | 'edit' | 'manage'. Owner = manage.
    vaultRole(id) { const v = this.folders.find((f) => f.id === id); return v ? (v.role || 'manage') : 'manage'; },
    canManageVault(id) { return this.vaultRole(id) === 'manage'; },

    /* ---- Shared vaults (read + write, role-gated) ----
       Maps server role names (viewer/editor/manager) to client (read/edit/manage).
       `_sharedKeys` caches the raw vault key bytes (Uint8Array) per vault id
       in-memory only — never persisted. Write paths are gated on canEditVault(). */
    _serverToClientRole(r) {
        const map = { viewer: 'read', editor: 'edit', manager: 'manage' };
        return map[r] || 'read';
    },
    _clientToServerRole(r) {
        const map = { read: 'viewer', edit: 'editor', manage: 'manager' };
        return map[r] || 'viewer';
    },
    isSharedVault(id) { return this.sharedVaults.some((sv) => sv.id === id); },
    sharedVaultRole(id) { const sv = this.sharedVaults.find((v) => v.id === id); return sv ? sv.role : 'read'; },

    // Whether the given vault (object or id) allows write access.
    canEditVault(vaultOrId) {
        if (! vaultOrId) return true; // personal vault = always editable
        const id = typeof vaultOrId === 'string' ? vaultOrId : vaultOrId.id;
        const v = this.sharedVaults.find((sv) => sv.id === id);
        if (! v) return true; // personal vault
        return v.role === 'edit' || v.role === 'manage';
    },
    // Whether the currently-viewed/editing item can be written.
    canEditCurrent() {
        if (! this.current) return false;
        if (! this.current.shared) return true; // personal
        return this.canEditVault(this.current.vaultId);
    },

    async _loadSharedVaults() {
        try {
            const id = await Vault.ensureIdentityKeys();
            const memberships = await apiRequest('GET', '/vaults');
            const all = Array.isArray(memberships) ? memberships : [];
            const active = all.filter((m) => m.status === 'active');
            // Collect pending invites so the UI can show them for acceptance.
            this.pendingInvites = all
                .filter((m) => m.status === 'pending')
                .map((m) => ({ vault_id: m.vault_id, member_id: m.id, role: m.role, wrapped_vault_key: m.wrapped_vault_key }));
            for (const m of active) {
                try {
                    const vkB64 = await VaultShareCrypto.unwrapVaultKey(m.wrapped_vault_key, id.pub, id.sk);
                    // atob decodes standard base64 (ORIGINAL variant), matching vault.js's
                    // base64_variants.ORIGINAL encoding. unb64 is not exported from vault.js.
                    const vkBytes = Uint8Array.from(atob(vkB64), (c) => c.charCodeAt(0));
                    // Cache the raw key bytes for write operations (in-memory only).
                    this._sharedKeys[m.vault_id] = vkBytes;
                    const store = await apiRequest('GET', '/vaults/' + m.vault_id + '/store');
                    let manifest = null;
                    if (store.sealed_manifest) {
                        manifest = await VaultShareCrypto.openVaultManifest(store.sealed_manifest, vkBytes);
                    }
                    // Avoid duplicates if _loadSharedVaults is somehow called twice.
                    if (this.sharedVaults.some((sv) => sv.id === m.vault_id)) continue;
                    this.sharedVaults.push({
                        id: m.vault_id,
                        name: (manifest && manifest.name) || 'Shared Vault',
                        role: this._serverToClientRole(m.role),
                        shared: true,
                        version: store.version,
                        vaultId: m.vault_id,
                    });
                    this.sharedItems[m.vault_id] = ((manifest && manifest.items) || []).map((item) => ({ ...item, shared: true, vaultId: m.vault_id }));
                    this._sharedVersion[m.vault_id] = store.version;
                } catch (e) {
                    console.warn('[shared vault] failed to load vault', m.vault_id, e);
                }
            }
        } catch (e) {
            console.warn('[shared vaults] load aborted', e);
        }
    },

    // Seal and PUT the shared vault manifest with optimistic concurrency.
    // On 409 the server body contains the current sealed manifest + version;
    // we re-apply the pending mutation and retry once before surfacing an error.
    async _saveVault(vaultId, pendingMutation) {
        const vault = this.sharedVaults.find((sv) => sv.id === vaultId);
        const vkBytes = this._sharedKeys && this._sharedKeys[vaultId];
        if (! vault || ! vkBytes) throw new Error('Vault key not available for ' + vaultId);
        const manifest = { name: vault.name, items: this.sharedItems[vaultId] || [] };
        const sealed = await VaultShareCrypto.sealVaultManifest(manifest, vkBytes);
        const body = JSON.stringify({ sealed_manifest: sealed, expected_version: this._sharedVersion[vaultId] });
        let res = await fetch('/vaults/' + vaultId + '/store', { method: 'PUT', headers: jsonHeaders(), body });
        // On 429, honour Retry-After and retry once (mirrors personal LLStore.flush behaviour).
        if (res.status === 429) {
            const retryAfter = parseInt(res.headers.get('Retry-After') || '5', 10);
            await this._sleep((isNaN(retryAfter) || retryAfter <= 0 ? 5 : Math.min(retryAfter, 60)) * 1000);
            res = await fetch('/vaults/' + vaultId + '/store', { method: 'PUT', headers: jsonHeaders(), body });
        }
        if (res.ok) {
            const { version } = await res.json();
            this._sharedVersion[vaultId] = version;
            return;
        }
        if (res.status === 409 && pendingMutation) {
            // Server has a newer version — merge and retry once.
            const data = await res.json();
            const serverManifest = await VaultShareCrypto.openVaultManifest(data.sealed_manifest, vkBytes);
            let serverItems = (serverManifest.items || []).map((item) => ({ ...item, shared: true, vaultId }));
            if (pendingMutation.op === 'upsert') {
                const idx = serverItems.findIndex((i) => i.id === pendingMutation.item.id);
                if (idx >= 0) serverItems[idx] = pendingMutation.item; else serverItems.push(pendingMutation.item);
            } else if (pendingMutation.op === 'delete') {
                serverItems = serverItems.filter((i) => i.id !== pendingMutation.item.id);
            }
            this.sharedItems = { ...this.sharedItems, [vaultId]: serverItems };
            this._sharedVersion[vaultId] = data.version;
            // Re-point this.current if it belongs to this vault so it tracks the merged array element.
            if (this.current && this.current.vaultId === vaultId) {
                this.current = serverItems.find((i) => i.id === this.current.id) || null;
            }
            const sealed2 = await VaultShareCrypto.sealVaultManifest(
                { name: vault.name, items: serverItems },
                vkBytes,
            );
            const res2 = await fetch('/vaults/' + vaultId + '/store', {
                method: 'PUT',
                headers: jsonHeaders(),
                body: JSON.stringify({ sealed_manifest: sealed2, expected_version: data.version }),
            });
            if (res2.ok) {
                const { version } = await res2.json();
                this._sharedVersion[vaultId] = version;
                return;
            }
            // Second conflict — surface error and reload vault state.
            window.llToast(labels.saveConflict || '');
            await this._loadSharedVaults();
            return;
        }
        throw new Error('Save vault failed: ' + res.status);
    },

    // Route a mutation through the correct save path.
    // Shared items go to _saveVault(); personal items go to _save().
    async _persist(item, op = 'upsert') {
        if (item && item.shared && item.vaultId) {
            return this._saveVault(item.vaultId, { op, item });
        }
        return this._save();
    },

    async addFolder() {
        const raw = await this.$store.confirm.prompt('', { placeholder: labels.folderName || '', ok: labels.save || '' });
        const name = (raw || '').trim(); if (! name) return;
        this.folders.push({ id: crypto.randomUUID(), name, role: 'manage' }); this._save();
    },
    async renameFolder(f) {
        const raw = await this.$store.confirm.prompt('', { value: f.name, ok: labels.save || '' });
        const name = (raw || '').trim(); if (name) { f.name = name; this._save(); }
    },
    // Danger zone: wipe ALL personal password items + personal vaults and reset
    // to a single fresh default vault. Client-side crypto-shred — the server only
    // ever sees the re-sealed manifest. Shared vaults (separate remote stores) are
    // deliberately untouched.
    openReset() { this.resetConfirmText = ''; this.resetOpen = true; },
    _closeReset() { this.resetOpen = false; this.resetConfirmText = ''; },
    async resetVault() {
        if (this.resetWorking || this.resetConfirmText !== (labels.resetConfirmWord || '')) return;
        this.resetWorking = true;
        let failed = 0;
        try {
            // Delete shared vaults the user MANAGES (full server-side cascade
            // teardown = real revocation). Vaults shared WITH the user (member,
            // role read/edit) are deliberately kept. Best-effort per vault.
            const managed = this.sharedVaults.filter((sv) => sv.role === 'manage').map((sv) => sv.id);
            for (const vid of managed) {
                try { await this._deleteSharedVaultNow(vid); } catch (e) { failed++; }
            }
            // Wipe ALL personal password items (incl. standalone passkey items and
            // logins with embedded passkeys) + personal vaults, then seed one fresh
            // default vault. Only secrets/secretFolders are touched — other modules
            // (notes/todos/bookmarks/files/contacts/invoices) are untouched.
            this.items.length = 0;              // same ref as LLStore.data.secrets → in-place clear
            this.folders.length = 0;            // same ref as LLStore.data.secretFolders
            const id = crypto.randomUUID();
            this.folders.push({ id, name: labels.defaultVaultName || 'Privat', role: 'manage' });
            this.current = null; this.draft = null; this.view = 'list'; this.selectedIds = [];
            this.query = ''; this.filterType = ''; this.filterFolder = id;
            this._save();
            this._closeReset();
            window.llToast(failed ? (labels.resetPartial || labels.resetFailed || '') : (labels.resetDone || ''));
        } catch (e) { window.llToast(labels.resetFailed || ''); } finally { this.resetWorking = false; }
    },
    async deleteFolder(f) {
        if (this.folders.length <= 1) return; // never delete the last vault
        if (! await this.$store.confirm.ask(labels.deleteFolderConfirm || '')) return;
        const fallback = this.folders.find((y) => y.id !== f.id);
        for (const x of this.items) if (x.folder === f.id) x.folder = fallback ? fallback.id : null;
        const i = this.folders.findIndex((y) => y.id === f.id); if (i >= 0) this.folders.splice(i, 1);
        if (this.filterFolder === f.id) this.filterFolder = '';
        this._save();
    },

    /* ---- CRUD ---- */
    _blankFields(type) {
        const f = {};
        for (const [k, t] of this.fieldsOf(type)) f[k] = t === 'checkbox' ? false : t === 'urls' ? [''] : (k === 'security' ? 'WPA2' : '');
        return f;
    },
    newItem(type) {
        this.current = null; this.reveal = {}; this.wifiQr = '';
        const isShared = this.isSharedVault(this.filterFolder);
        this.draft = {
            id: null, type, title: '', favorite: false,
            folder: (! isShared && this.filterFolder && this.filterFolder !== '_none') ? this.filterFolder : ((this.folders[0] && this.folders[0].id) || null),
            tags: [], custom: [], icon: '', fields: this._blankFields(type), created: null, updated: null, versions: [],
            shared: isShared || undefined,
            vaultId: isShared ? this.filterFolder : undefined,
        };
        this.tagsValue = '';
        this._updateStrength('');
    },
    openItem(x) { this.current = x; this.draft = null; this.reveal = {}; this.historyOpen = false; this._refreshWifiQr(x); this._updateStrength((x && x.fields && x.fields.password) || ''); },
    editCurrent() {
        if (! this.current) return;
        this.draft = JSON.parse(JSON.stringify(this.current));
        this.draft.custom = (this.draft.custom || []).map((c) => ({ label: c.label || '', value: c.value || '', kind: this.customKind(c) }));
        this.draft.tags = this.draft.tags || [];
        // Migrate an older single url field into the multi-url array.
        if (this.draft.type === 'login' && ! Array.isArray(this.draft.fields.urls)) this.draft.fields.urls = this.draft.fields.url ? [this.draft.fields.url] : [''];
        this.tagsValue = (this.draft.tags || []).join(', ');
        this._updateStrength(this.draft.fields?.password || '');
    },
    cancelEdit() { if (this.draft && ! this.draft.id) this.current = null; this.draft = null; this._updateStrength(''); this._autoSelect(); },
    changeDraftType(t) { if (! this.draft || this.draft.id) return; this.draft.type = t; this.draft.fields = this._blankFields(t); },

    addUrl() { if (this.draft) (this.draft.fields.urls = this.draft.fields.urls || []).push(''); },
    removeUrl(i) { if (this.draft?.fields?.urls) this.draft.fields.urls.splice(i, 1); },
    addCustom() { if (this.draft) (this.draft.custom = this.draft.custom || []).push({ label: '', value: '', kind: 'text' }); },
    removeCustom(i) { if (this.draft?.custom) this.draft.custom.splice(i, 1); },
    // Remove an attached passkey from the current login item (detail view, no draft needed).
    // Confirms via the store confirm dialog, splices the entry, then persists.
    async removePasskey(index) {
        if (! this.current) return;
        if (! await this.$store.confirm.ask(labels.passkeyRemoveConfirm || '')) return;
        const passkeys = this.current.fields?.passkeys;
        if (! Array.isArray(passkeys) || index < 0 || index >= passkeys.length) return;
        passkeys.splice(index, 1);
        try { await this._persist(this.current); } catch (e) { window.llToast(labels.saveFailed || ''); }
    },
    customKinds: ['text', 'secret', 'multiline', 'url'],
    // Kind of a custom field, migrating the older {secret:bool} shape.
    customKind(c) { return c.kind || (c.secret ? 'secret' : 'text'); },
    customSecret(c) { return this.customKind(c) === 'secret'; },

    async save() {
        const d = this.draft; if (! d) return;
        d.title = (d.title || '').trim() || this.typeLabel(d.type);
        d.tags = (this.tagsValue || '').split(',').map((t) => t.trim()).filter(Boolean);
        if (Array.isArray(d.fields.urls)) d.fields.urls = d.fields.urls.map((u) => (u || '').trim()).filter(Boolean);
        d.custom = (d.custom || []).map((c) => ({ label: (c.label || '').trim(), value: (c.value || '').trim(), kind: this.customKind(c) })).filter((c) => c.label || c.value);
        const now = new Date().toISOString();
        const isShared = ! ! (d.shared && d.vaultId);
        if (isShared) {
            // Shared vault item: mutate sharedItems, then PUT the sealed vault.
            if (! d.id) {
                d.id = crypto.randomUUID(); d.created = now; d.updated = now; d.versions = [];
            } else {
                const existing = (this.sharedItems[d.vaultId] || []).find((x) => x.id === d.id);
                if (existing && this._changed(existing, d)) {
                    existing.versions = existing.versions ?? [];
                    existing.versions.unshift({ at: existing.updated || existing.created || now, title: existing.title, fields: JSON.parse(JSON.stringify(existing.fields || {})), custom: JSON.parse(JSON.stringify(existing.custom || [])) });
                    if (existing.versions.length > 100) existing.versions.length = 100;
                    // Carry the updated version history onto the draft so the spread below includes it.
                    d.versions = existing.versions;
                    d.updated = now;
                }
            }
            const item = { ...d };
            const sharedArr = this.sharedItems[d.vaultId] || [];
            const idx = sharedArr.findIndex((x) => x.id === item.id);
            if (idx >= 0) sharedArr[idx] = item; else sharedArr.unshift(item);
            this.sharedItems = { ...this.sharedItems, [d.vaultId]: sharedArr };
            this.current = item;
            this.draft = null; this.reveal = {};
            this._refreshWifiQr(item);
            await this._saveVault(item.vaultId, { op: 'upsert', item });
            if (item.type === 'login') this._fetchIcon(item);
            return;
        }
        // Personal vault path.
        if (! d.id) {
            d.id = crypto.randomUUID(); d.created = now; d.updated = now; d.versions = [];
            this.items.unshift(d);
            this.current = this.items[0];
        } else {
            const it = this.items.find((x) => x.id === d.id);
            if (! it) return;
            // Versioning: snapshot the outgoing values (fields + custom + title)
            // whenever anything content-bearing changed.
            if (this._changed(it, d)) {
                it.versions = it.versions ?? [];
                it.versions.unshift({ at: it.updated || it.created || now, title: it.title, fields: JSON.parse(JSON.stringify(it.fields || {})), custom: JSON.parse(JSON.stringify(it.custom || [])) });
                if (it.versions.length > 100) it.versions.length = 100;
                it.updated = now;
            }
            it.title = d.title; it.favorite = d.favorite; it.folder = d.folder || null; it.tags = d.tags;
            it.fields = JSON.parse(JSON.stringify(d.fields)); it.custom = JSON.parse(JSON.stringify(d.custom));
            this.current = it;
        }
        const saved = this.current;
        this.draft = null; this.reveal = {};
        this._refreshWifiQr(saved);
        this._save();
        if (saved.type === 'login') this._fetchIcon(saved);
    },
    _changed(a, b) {
        if ((a.title || '') !== (b.title || '')) return true;
        if (JSON.stringify(a.fields || {}) !== JSON.stringify(b.fields || {})) return true;
        if (JSON.stringify(a.custom || []) !== JSON.stringify(b.custom || [])) return true;
        return false;
    },
    // Field labels that changed between an old snapshot and the newer state
    // (shown in the edit view's version list).
    _changedKeys(oldSnap, newSnap) {
        const out = [];
        if ((oldSnap.title || '') !== (newSnap.title || '')) out.push(labels.titleLabel || 'Title');
        const of = oldSnap.fields || {}, nf = newSnap.fields || {};
        for (const k of new Set([...Object.keys(of), ...Object.keys(nf)])) {
            if (JSON.stringify(of[k] ?? '') !== JSON.stringify(nf[k] ?? '')) out.push(this.fieldLabel(k));
        }
        if (JSON.stringify(oldSnap.custom || []) !== JSON.stringify(newSnap.custom || [])) out.push(labels.customLabel || 'Custom fields');
        return out;
    },
    // For the edit view: each version paired with what changed vs the newer state.
    versionChanges(item) {
        const vs = item.versions || [];
        const out = [];
        for (let i = 0; i < vs.length; i++) {
            const newer = i === 0 ? item : vs[i - 1];
            out.push({ at: vs[i].at, changed: this._changedKeys(vs[i], newer) });
        }
        return out;
    },
    // Credit-card brand from the number (IIN/BIN ranges).
    cardBrand(number) { return pwCardBrand(number); },
    // Domain of a login's first URL (for the icon fetch + avatar).
    primaryUrl(x) { const u = (x.fields?.urls || [])[0] || x.fields?.url || ''; return u; },
    _domain(x) { try { const u = this.primaryUrl(x); if (! u) return ''; return new URL(/^https?:\/\//.test(u) ? u : 'https://' + u).hostname; } catch (e) { return ''; } },
    // Server-proxied favicon/BIMI fetch, cached as a data URI in the sealed item.
    // Routes the persist through _persist() so shared items go to the correct vault store.
    async _fetchIcon(x) {
        const domain = this._domain(x); if (! domain) return 'skip';
        try {
            const res = await fetch(`${config.iconUrl}?domain=${encodeURIComponent(domain)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.status === 429) return '429';
            if (! res.ok) return 'skip';
            const { icon } = await res.json();
            if (icon && x.icon !== icon) { x.icon = icon; await this._persist(x); }
            return 'ok';
        } catch (e) { return 'skip'; }
    },
    _sleep(ms) { return new Promise((r) => setTimeout(r, ms)); },
    // Usable image src for a login's stored icon. Only data:/http(s) URIs are
    // rendered as <img>; imported items may carry a bare icon name (e.g. Apple
    // Passwords' "person.crop.circle") which must never leak into an img src.
    iconSrc(x) { const v = x && x.icon; return (typeof v === 'string' && /^(data:|https?:)/.test(v)) ? v : ''; },
    // Per-type tint for the icon chip (iOS-style, mirrors the settings list chips).
    typeTint(t) {
        return ({
            login: '#7066f5', password: '#9e70fa', card: '#3b9fd6', wifi: '#3fae9f',
            license: '#d9a441', server: '#6b7280', passkey: '#59ad6b',
            identity: '#e2915a', secure_note: '#8b8f9a',
        })[t] || '#7066f5';
    },
    avatarText(x) { const s = (x.title || this._domain(x) || '?').trim(); return (s[0] || '?').toUpperCase(); },
    avatarColor(x) { let h = 0; const s = x.title || this._domain(x) || x.id || ''; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return `hsl(${h % 360} 45% 55%)`; },

    /* ---- Password health: weak / reused / breached (HIBP) ---- */
    breachMap: {}, // password -> pwned count (session only, never persisted)
    breachChecking: false,
    _mut: 0,
    _reusedCache: null,
    _save() { this._mut++; window.LLStore.touch(); },
    get _pwTypes() { return Object.keys(TYPES).filter((t) => TYPES[t].fields.some(([k]) => k === 'password')); },
    _pw(x) { return (x && x.fields && x.fields.password) || ''; },
    get healthItems() { return this.liveItems.filter((x) => (this._pwTypes.includes(x.type) && this._pw(x)) || (x.type === 'card' && this._cardExpiring(x))); },
    // A card that is expired or expires within ~45 days.
    _cardExpiring(x) {
        const m = String((x.fields && x.fields.expiry) || '').match(/(\d{1,2})\D+(\d{2,4})/);
        if (! m) return false;
        const mm = +m[1]; let yr = +m[2]; if (yr < 100) yr += 2000;
        if (mm < 1 || mm > 12) return false;
        const end = new Date(yr, mm, 1); // first day after the expiry month
        return end.getTime() <= Date.now() + 45 * 86400000;
    },
    // Memoised — issuesFor()/the health list call this O(n) times per render.
    get _reusedSet() {
        const sig = this._mut + '|' + this.items.length;
        if (this._reusedCache && this._reusedCache.sig === sig) return this._reusedCache.val;
        const count = {};
        for (const x of this.healthItems) count[this._pw(x)] = (count[this._pw(x)] || 0) + 1;
        const set = new Set();
        for (const x of this.healthItems) if (count[this._pw(x)] > 1) set.add(x.id);
        this._reusedCache = { sig, val: set };
        return set;
    },
    _pwScore(pw) { return pwStrength(pw); },
    _scoreLabel(score) {
        const map = [
            (labels.strengthVeryWeak || ''),
            (labels.strengthWeak || ''),
            (labels.strengthFair || ''),
            (labels.strengthGood || ''),
            (labels.strengthStrong || ''),
        ];
        return map[score] || '';
    },
    // Debounced async strength update: sets a coarse score immediately from the
    // synchronous fallback, then resolves to the zxcvbn result (~50–200 ms).
    _updateStrength(pw) {
        if (this._strengthTimer) clearTimeout(this._strengthTimer);
        if (! pw) { this.strengthScore = null; this.strengthLabel = ''; this.crackTime = ''; return; }
        // Optimistic synchronous score while zxcvbn loads.
        this.strengthScore = pwStrength(pw);
        this.strengthLabel = this._scoreLabel(this.strengthScore);
        this.crackTime = '';
        this._strengthTimer = setTimeout(async () => {
            try {
                const r = await estimateStrength(pw);
                this.strengthScore = r.score;
                this.strengthLabel = this._scoreLabel(r.score);
                this.crackTime = r.crackTimeDisplay;
            } catch (e) { /* best effort — keep the coarse score */ }
        }, 200);
    },
    issuesFor(x) {
        if (! x) return null;
        if (x.type === 'card') { return this._cardExpiring(x) ? { weak: false, reused: false, breach: null, no2fa: false, expiring: true } : null; }
        const pw = this._pw(x); if (! pw) return null;
        const b = this.breachMap[pw];
        return { weak: this._pwScore(pw) < 3, reused: this._reusedSet.has(x.id), breach: (b == null ? null : b), no2fa: this.supports2fa(x), expiring: false };
    },
    hasIssue(x) { const i = this.issuesFor(x); return ! ! i && (i.weak || i.reused || i.breach > 0 || i.expiring); },
    // Other entries that share this entry's password (for the reuse overview).
    reusedWith(x) {
        if (! x) return [];
        const pw = this._pw(x); if (! pw) return [];
        return this.healthItems.filter((y) => y.id !== x.id && this._pw(y) === pw);
    },
    get healthProblems() {
        const rank = (i) => (i.breach > 0 ? 4 : 0) + (i.expiring ? 3 : 0) + (i.reused ? 2 : 0) + (i.weak ? 1 : 0) + (i.no2fa ? 1 : 0);
        return this.healthItems.map((x) => ({ x, iss: this.issuesFor(x) })).filter((o) => o.iss && rank(o.iss) > 0)
            .sort((a, b) => rank(b.iss) - rank(a.iss));
    },
    get healthCount() { return this.healthProblems.length; },
    async _sha1hex(str) { const buf = await crypto.subtle.digest('SHA-1', new TextEncoder().encode(str)); return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('').toUpperCase(); },
    async checkBreaches() {
        if (this.breachChecking) return;
        this.breachChecking = true;
        try {
            const pws = [...new Set(this.healthItems.map((x) => this._pw(x)))];
            for (const pw of pws) {
                try {
                    const hex = await this._sha1hex(pw); const prefix = hex.slice(0, 5); const suffix = hex.slice(5);
                    const res = await fetch(`${config.breachUrl}?prefix=${prefix}`, { headers: { Accept: 'text/plain', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (! res.ok) { this.breachMap[pw] = 0; continue; }
                    const text = await res.text();
                    let count = 0;
                    for (const line of text.split('\n')) { const idx = line.indexOf(':'); if (idx < 0) continue; if (line.slice(0, idx).trim().toUpperCase() === suffix) { count = parseInt(line.slice(idx + 1), 10) || 1; break; } }
                    this.breachMap[pw] = count;
                } catch (e) { /* skip */ }
            }
        } finally { this.breachChecking = false; }
    },
    async toggleFavorite(x) { x.favorite = ! x.favorite; try { await this._persist(x); } catch (e) { window.llToast(labels.saveFailed || ''); } },
    async trash(x) {
        if (! confirm(labels.deleteConfirm)) return;
        x.trashed = new Date().toISOString();
        if (x.shared && x.vaultId) {
            // Shared item: update the in-memory array reactively.
            const arr = (this.sharedItems[x.vaultId] || []).map((i) => (i.id === x.id ? { ...i, trashed: x.trashed } : i));
            this.sharedItems = { ...this.sharedItems, [x.vaultId]: arr };
        }
        if (this.current === x) { this.current = null; this.draft = null; }
        try { await this._persist(x, 'upsert'); } catch (e) { window.llToast(labels.saveFailed || ''); }
        this._autoSelect();
    },
    async restore(x) {
        x.trashed = null;
        if (x.shared && x.vaultId) {
            const arr = (this.sharedItems[x.vaultId] || []).map((i) => (i.id === x.id ? { ...i, trashed: null } : i));
            this.sharedItems = { ...this.sharedItems, [x.vaultId]: arr };
        }
        try { await this._persist(x, 'upsert'); } catch (e) { window.llToast(labels.saveFailed || ''); }
    },
    async purge(x) {
        if (x.shared && x.vaultId) {
            const arr = (this.sharedItems[x.vaultId] || []).filter((i) => i.id !== x.id);
            this.sharedItems = { ...this.sharedItems, [x.vaultId]: arr };
            if (this.current === x) this.current = null;
            try { await this._saveVault(x.vaultId, { op: 'delete', item: x }); } catch (e) { window.llToast(labels.saveFailed || ''); }
            this._autoSelect();
            return;
        }
        const i = this.items.findIndex((y) => y.id === x.id); if (i >= 0) this.items.splice(i, 1); if (this.current === x) this.current = null; this._save(); this._autoSelect();
    },
    emptyTrash() { return this._emptyTrashArr(this.items, labels.emptyTrashConfirm); },

    /* ---- Multi-select bulk actions ---- */
    isSelected(id) { return this.selectedIds.includes(id); },
    toggleSelect(id) { const i = this.selectedIds.indexOf(id); if (i < 0) this.selectedIds.push(id); else this.selectedIds.splice(i, 1); },
    clearSelection() { this.selectedIds = []; },
    // Select all currently visible items, or clear them if all are already selected.
    toggleSelectAll() {
        const ids = this.filtered.map((x) => x.id);
        const all = ids.length && ids.every((id) => this.selectedIds.includes(id));
        this.selectedIds = all ? this.selectedIds.filter((id) => ! ids.includes(id)) : [...new Set([...this.selectedIds, ...ids])];
    },
    bulkDelete() {
        if (! this.selectedIds.length) return;
        const permanent = this.view === 'trash';
        if (permanent && ! confirm(labels.bulkPurgeConfirm)) return;
        const ids = new Set(this.selectedIds);
        if (permanent) {
            for (let i = this.items.length - 1; i >= 0; i--) if (ids.has(this.items[i].id)) this.items.splice(i, 1);
        } else {
            const now = new Date().toISOString();
            for (const x of this.items) if (ids.has(x.id)) x.trashed = now;
        }
        if (this.current && ids.has(this.current.id)) { this.current = null; this.draft = null; }
        this.selectedIds = [];
        this._save();
        this._autoSelect();
    },

    /* ---- "This site offers 2FA" hint (2fa.directory) ---- */
    async _loadTfa() {
        if (! config.tfaUrl) return;
        try {
            const data = await getJson(config.tfaUrl);
            this._tfaMap = data.entries || {};
            this.tfaReady = true;
        } catch (e) { /* offline or blocked — hint stays hidden */ }
    },
    _host(u) { try { return new URL(/^https?:\/\//.test(u) ? u : 'https://' + u).hostname.replace(/^www\./, '').toLowerCase(); } catch (e) { return ''; } },
    // Walk a login's URLs (and their parent domains) against the 2FA dataset;
    // returns the matched domain key or ''.
    _tfaMatch(x) {
        if (! this.tfaReady || ! this._tfaMap || ! x || x.type !== 'login') return '';
        for (const u of (x.fields?.urls || [])) {
            let d = this._host(u);
            while (d && d.includes('.')) {
                if (d in this._tfaMap) return d;
                d = d.slice(d.indexOf('.') + 1);
            }
        }
        return '';
    },
    // A login with no stored TOTP whose domain is known to support app 2FA.
    supports2fa(x) { return ! (x && x.fields && x.fields.totp) && ! ! this._tfaMatch(x); },
    // The dataset's setup-instructions URL for the login's site (or '').
    tfaDoc(x) { const d = this._tfaMatch(x); const u = d ? (this._tfaMap[d] || '') : ''; return /^https?:\/\//i.test(u) ? u : ''; },

    /* ---- Import (Bitwarden / 1Password / LastPass / KeePass / CSV) ----
       Everything parses in the browser and lands straight in the sealed
       manifest; nothing is uploaded. --- */
    importOpen: false,
    importFormat: 'auto',
    importResult: null,
    importing: false,
    openImport() { this.importOpen = true; this.importFormat = 'auto'; this.importResult = null; },
    async importFile(ev) {
        const file = ev.target.files[0]; if (! file) return;
        ev.target.value = '';
        this.importing = true; this.importResult = null;
        try {
            const text = await file.text();
            const recs = this._parseImport(text, this.importFormat);
            if (! recs.length) { this.importResult = { ok: false, count: 0 }; return; }
            for (const r of recs) { this._normalizeRecord(r); this.items.unshift(r); }
            this._save();
            this.importResult = { ok: true, count: recs.length };
            this._importIcons(recs.filter((r) => r.type === 'login'));
        } catch (e) { this.importResult = { ok: false, count: 0 }; } finally { this.importing = false; }
    },
    async _importIcons(list) { for (const r of list) { try { await this._fetchIcon(r); } catch (e) { /* best effort */ } } },

    /* ---- Export (client-side, personal vault only) ----
       Plaintext and encrypted exports are generated entirely in the browser from
       the already-decrypted items. Nothing touches the server. Shared-vault items
       are intentionally excluded from v1 exports. --- */
    exportOpen: false,
    exportConfirmed: false,
    exportPassphrase: '',
    exportWorking: false,
    exportDone: '',
    exportError: '',
    resetOpen: false,
    resetConfirmText: '',
    resetWorking: false,
    openExport() {
        this.exportOpen = true;
        this.exportConfirmed = false;
        this.exportPassphrase = '';
        this.exportWorking = false;
        this.exportDone = '';
        this.exportError = '';
    },
    _closeExport() {
        this.exportOpen = false;
        this.exportPassphrase = '';
        this.exportError = '';
        this.exportDone = '';
    },
    _exportTimestamp() {
        const d = new Date();
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0') + '_'
            + String(d.getHours()).padStart(2, '0')
            + String(d.getMinutes()).padStart(2, '0');
    },
    async exportJson() {
        if (! this.exportConfirmed) return;
        this.exportWorking = true;
        this.exportError = '';
        this.exportDone = '';
        try {
            const obj = buildBitwardenJson(this.items, this.folders);
            const json = JSON.stringify(obj, null, 2);
            saveBlobAs(json, 'ledgerline-export-' + this._exportTimestamp() + '.json', 'application/json');
            this.exportDone = labels.exportDone || 'Done.';
        } catch (e) {
            this.exportDone = '';
            this.exportError = labels.exportFailed || 'Export failed.';
        } finally { this.exportWorking = false; }
    },
    async exportCsv() {
        if (! this.exportConfirmed) return;
        this.exportWorking = true;
        this.exportError = '';
        this.exportDone = '';
        try {
            const csv = buildCsv(this.items);
            saveBlobAs(csv, 'ledgerline-export-' + this._exportTimestamp() + '.csv', 'text/csv');
            this.exportDone = labels.exportDone || 'Done.';
        } catch (e) {
            this.exportDone = '';
            this.exportError = labels.exportFailed || 'Export failed.';
        } finally { this.exportWorking = false; }
    },
    async exportEncrypted() {
        if (! this.exportPassphrase.trim()) return;
        this.exportWorking = true;
        this.exportError = '';
        this.exportDone = '';
        try {
            const obj = buildBitwardenJson(this.items, this.folders);
            const envelope = await encryptExport(JSON.stringify(obj), this.exportPassphrase);
            saveBlobAs(envelope, 'ledgerline-export-enc-' + this._exportTimestamp() + '.json', 'application/json');
            this.exportDone = labels.exportDone || 'Done.';
            this.exportPassphrase = '';
        } catch (e) {
            this.exportDone = '';
            this.exportError = labels.exportFailed || 'Export failed.';
        } finally { this.exportWorking = false; }
    },

    // Re-fetch the site icon for every login (server-proxied; sequential to
    // respect the endpoint throttle). Updates only entries whose icon changed.
    iconRefreshing: false,
    iconProgress: { done: 0, total: 0 },
    async refreshAllIcons() {
        if (this.iconRefreshing) return;
        this.iconRefreshing = true;
        const list = this.liveItems.filter((x) => x.type === 'login' && this._domain(x));
        this.iconProgress = { done: 0, total: list.length };
        try {
            for (const x of list) {
                // Retry a rate-limited fetch with backoff; pace the rest so we stay
                // under the endpoint throttle.
                for (let tries = 0; ; tries++) {
                    const r = await this._fetchIcon(x).catch(() => 'skip');
                    if (r === '429' && tries < 5) { await this._sleep(1500 * (tries + 1)); continue; }
                    break;
                }
                this.iconProgress.done++;
                await this._sleep(120);
            }
            window.llToast(labels.iconsDone || '');
        } finally { this.iconRefreshing = false; }
    },
    _newRecord(type, title) {
        return { id: crypto.randomUUID(), type, title: (title || '').trim(), favorite: false, folder: null, tags: [], custom: [], icon: '', fields: this._blankFields(type), created: new Date().toISOString(), updated: new Date().toISOString(), versions: [] };
    },
    _normalizeRecord(r) {
        r.fields = { ...this._blankFields(r.type), ...(r.fields || {}) };
        if (r.type === 'login' && ! Array.isArray(r.fields.urls)) r.fields.urls = r.fields.urls ? [r.fields.urls] : [''];
        if (r.type === 'login' && ! r.fields.urls.length) r.fields.urls = [''];
        r.tags = r.tags || [];
        r.custom = (r.custom || []).map((c) => ({ label: c.label || '', value: c.value || '', kind: c.kind || 'text' })).filter((c) => c.label || c.value);
        if (! r.title) r.title = this.typeLabel(r.type);
    },
    _folderId(name) {
        name = (name || '').trim(); if (! name) return null;
        let f = this.folders.find((x) => x.name === name);
        if (! f) { f = { id: crypto.randomUUID(), name }; this.folders.push(f); }
        return f.id;
    },
    _totpSecret(v) { return pwTotpSecret(v); },
    _parseImport(text, fmt) {
        const trimmed = text.trim();
        const isJson = trimmed.startsWith('{') || trimmed.startsWith('[');
        if (fmt === 'bitwarden_json' || (fmt === 'auto' && isJson)) return this._importBitwardenJson(text);
        return this._importCsv(text, fmt);
    },
    _importBitwardenJson(text) {
        const data = JSON.parse(text);
        const folderById = {};
        for (const f of (data.folders || [])) folderById[f.id] = f.name;
        const out = [];
        for (const it of (data.items || [])) {
            let rec;
            if (it.type === 3 && it.card) {
                rec = this._newRecord('card', it.name);
                const c = it.card;
                rec.fields.cardholder = c.cardholderName || '';
                rec.fields.number = c.number || '';
                rec.fields.expiry = (c.expMonth ? String(c.expMonth).padStart(2, '0') : '') + (c.expYear ? '/' + String(c.expYear).slice(-2) : '');
                rec.fields.cvv = c.code || '';
                rec.fields.note = it.notes || '';
            } else if (it.type === 1 && it.login) {
                rec = this._newRecord('login', it.name);
                rec.fields.username = it.login.username || '';
                rec.fields.password = it.login.password || '';
                rec.fields.urls = (it.login.uris || []).map((u) => u.uri).filter(Boolean);
                rec.fields.totp = this._totpSecret(it.login.totp || '');
                rec.fields.note = it.notes || '';
            } else {
                rec = this._newRecord('password', it.name);
                rec.fields.note = it.notes || '';
            }
            for (const f of (it.fields || [])) if (f.name || f.value) rec.custom.push({ label: f.name || '', value: String(f.value ?? ''), kind: f.type === 1 ? 'secret' : 'text' });
            if (it.favorite) rec.favorite = true;
            if (it.folderId && folderById[it.folderId]) rec.folder = this._folderId(folderById[it.folderId]);
            out.push(rec);
        }
        return out;
    },
    _parseCsv(text) { return pwParseCsv(text); },
    _detectCsv(h) { return pwDetectCsv(h); },
    _importCsv(text, fmt) {
        const rows = this._parseCsv(text).filter((r) => r.length && r.some((c) => c !== ''));
        if (rows.length < 2) return [];
        const header = rows[0].map((h) => h.trim().toLowerCase());
        const objs = rows.slice(1).map((r) => { const o = {}; header.forEach((h, i) => { o[h] = r[i] ?? ''; }); return o; });
        const kind = fmt === 'auto' ? this._detectCsv(header) : fmt;
        const out = [];
        for (const o of objs) {
            let rec;
            if (kind === 'bitwarden_csv') {
                if ((o.type || '').toLowerCase() === 'card') { rec = this._newRecord('card', o.name); rec.fields.note = o.notes || ''; } else {
                    rec = this._newRecord('login', o.name);
                    rec.fields.username = o.login_username || ''; rec.fields.password = o.login_password || '';
                    rec.fields.urls = (o.login_uri || '').split(',').map((s) => s.trim()).filter(Boolean);
                    rec.fields.totp = this._totpSecret(o.login_totp || ''); rec.fields.note = o.notes || '';
                }
                if (o.favorite === '1' || (o.favorite || '').toLowerCase() === 'true') rec.favorite = true;
                if (o.folder) rec.folder = this._folderId(o.folder);
            } else if (kind === 'lastpass') {
                rec = this._newRecord('login', o.name);
                rec.fields.username = o.username || ''; rec.fields.password = o.password || '';
                rec.fields.urls = [o.url].filter((u) => u && u !== 'http://sn');
                rec.fields.totp = this._totpSecret(o.totp || ''); rec.fields.note = o.extra || '';
                if (o.grouping) rec.folder = this._folderId(o.grouping);
                if (o.fav === '1') rec.favorite = true;
            } else if (kind === 'keepass') {
                rec = this._newRecord('login', o.title || o.account);
                rec.fields.username = o.username || ''; rec.fields.password = o.password || '';
                rec.fields.urls = [o.url].filter(Boolean); rec.fields.note = o.notes || '';
                if (o.group) rec.folder = this._folderId(o.group);
            } else if (kind === 'onepassword') {
                rec = this._newRecord('login', o.title || o.name);
                rec.fields.username = o.username || ''; rec.fields.password = o.password || '';
                rec.fields.urls = [o.url || o.website].filter(Boolean);
                rec.fields.totp = this._totpSecret(o.otpauth || o.otp || ''); rec.fields.note = o.notes || o.note || '';
                if (o.tags) rec.tags = String(o.tags).split(',').map((t) => t.trim()).filter(Boolean);
            } else {
                rec = this._newRecord('login', o.name || o.title || o.url);
                rec.fields.username = o.username || o.login || ''; rec.fields.password = o.password || '';
                rec.fields.urls = [o.url || o.website || o.uri].filter(Boolean);
                rec.fields.note = o.note || o.notes || '';
            }
            if (rec) out.push(rec);
        }
        return out;
    },

    /* ---- Version history (inline accordion; restores into the open item) ---- */
    // What changed between a stored snapshot and the next-newer state, as an
    // object for a JSON code block. Secret fields show "(changed)" not values.
    // `passkeys` is treated as opaque-secret: the array may contain nested
    // privateKey/publicKey — never serialize its contents into the diff.
    versionDiff(i) {
        const list = (this.current && this.current.versions) || [];
        const older = list[i]; if (! older) return {};
        const newer = i === 0 ? this.current : list[i - 1];
        const of = older.fields || {}; const nf = (newer && newer.fields) || {};
        const secret = [...SECRET_FIELDS, 'passkeys', 'publicKey'];
        const diff = {};
        if ((older.title || '') !== ((newer && newer.title) || '')) diff.title = { from: older.title || '', to: (newer && newer.title) || '' };
        for (const k of new Set([...Object.keys(of), ...Object.keys(nf)])) {
            if (JSON.stringify(of[k] ?? null) === JSON.stringify(nf[k] ?? null)) continue;
            diff[k] = secret.includes(k) ? '(changed)' : { from: of[k] ?? null, to: nf[k] ?? null };
        }
        return diff;
    },
    async restoreVersion(v) {
        const it = this.current; if (! it) return;
        it.versions = it.versions ?? [];
        it.versions.unshift({ at: it.updated || new Date().toISOString(), title: it.title, fields: JSON.parse(JSON.stringify(it.fields || {})) });
        if (it.versions.length > 100) it.versions.length = 100;
        it.title = v.title; it.fields = JSON.parse(JSON.stringify(v.fields || {})); it.updated = new Date().toISOString();
        this._refreshWifiQr(it);
        try { await this._persist(it); } catch (e) { window.llToast(labels.saveFailed || ''); }
    },

    /* ---- Secrets: reveal + copy-with-auto-clear ---- */
    toggleReveal(k) { this.reveal[k] = ! this.reveal[k]; },
    async copy(v) {
        if (v == null || v === '') return;
        try {
            await navigator.clipboard.writeText(String(v));
            window.llToast(labels.copied || '');
            const secs = config.clipboardClearSeconds || 20;
            setTimeout(() => { navigator.clipboard.writeText('').catch(() => {}); }, secs * 1000);
        } catch (e) { /* clipboard blocked */ }
    },
    // Detail-view hotkeys: Ctrl/Cmd+B copies the username, Ctrl/Cmd+C the
    // password. Ctrl+C only fires when no text is selected (so a normal copy of
    // selected text still works) and never while editing or typing in a field.
    _hotkey(e) {
        if (! this.current || this.draft) return;
        if (! (e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey) return;
        const tag = (e.target && e.target.tagName || '').toLowerCase();
        const typing = tag === 'input' || tag === 'textarea' || tag === 'select';
        const k = e.key.toLowerCase();
        if (k === 'b') {
            if (typing) return;
            const u = this.current.fields && this.current.fields.username;
            if (u) { e.preventDefault(); this.copy(u); }
        } else if (k === 'c') {
            if (window.getSelection && String(window.getSelection())) return; // let a real copy through
            if (typing) return;
            const pw = this.current.fields && this.current.fields.password;
            if (pw) { e.preventDefault(); this.copy(pw); }
        }
    },
    // Large-type view (1Password-style): show a secret big, in a readable mono
    // font, each character colour-coded (digits blue, symbols red, letters plain).
    bigType: { open: false, value: '' },
    showBig(v) { if (v == null || v === '') return; this.bigType = { open: true, value: String(v) }; },
    closeBig() { this.bigType = { open: false, value: '' }; },
    bigChars() {
        return String(this.bigType.value).split('').map((c) => ({
            c,
            cls: /[0-9]/.test(c) ? 'text-blue-600 dark:text-blue-400' : (/[^a-zA-Z0-9]/.test(c) ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'),
        }));
    },

    /* ---- Password generator ---- */
    // Uniform random index in [0, max) from the CSPRNG (rejection sampling to
    // avoid modulo bias).
    _randInt(max) {
        const limit = Math.floor(0xffffffff / max) * max;
        const a = new Uint32Array(1);
        do { crypto.getRandomValues(a); } while (a[0] >= limit);
        return a[0] % max;
    },
    genPassword() {
        const g = this.gen; let set = '';
        // With "similar" off we drop look-alike glyphs (i l 1 O 0 etc.).
        if (g.lower) set += g.similar ? 'abcdefghijklmnopqrstuvwxyz' : 'abcdefghijkmnpqrstuvwxyz';
        if (g.upper) set += g.similar ? 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' : 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        if (g.digits) set += g.similar ? '0123456789' : '23456789';
        if (g.symbols) set += '!@#$%^&*()-_=+[]{}';
        if (! set) set = 'abcdefghijkmnpqrstuvwxyz';
        const n = Math.max(6, Math.min(128, g.length | 0));
        let out = ''; for (let i = 0; i < n; i++) out += set[this._randInt(set.length)];
        return out;
    },
    genPassphrase() {
        const g = this.gen;
        const pool = (PW_WORDS[g.lang] || PW_WORDS.en).split(' ');
        const n = Math.max(2, Math.min(12, g.words | 0));
        const picked = [];
        for (let i = 0; i < n; i++) {
            let w = pool[this._randInt(pool.length)];
            if (g.capitalize) w = w.charAt(0).toUpperCase() + w.slice(1);
            picked.push(w);
        }
        const sep = g.sep === 'space' ? ' ' : (g.sep || '');
        let out = picked.join(sep);
        if (g.number) out += sep + (this._randInt(90) + 10); // a 2-digit number
        return out;
    },
    regen() { this.gen.preview = this.gen.mode === 'words' ? this.genPassphrase() : this.genPassword(); },
    openGen(target) { this.gen.target = target; this.regen(); this.gen.open = true; },
    applyGen() {
        const v = this.gen.preview || (this.gen.mode === 'words' ? this.genPassphrase() : this.genPassword());
        if (this.draft && this.gen.target) this.draft.fields[this.gen.target] = v;
        else this.copy(v); // opened standalone from the toolbar: copy to clipboard
        this.gen.open = false;
    },

    /* ---- Wi-Fi QR (built from the item's own fields, client-side) ---- */
    async _refreshWifiQr(x) {
        this.wifiQr = '';
        if (! x || x.type !== 'wifi' || ! x.fields?.ssid) return;
        const esc = (s) => String(s || '').replace(/([\\;,:"])/g, '\\$1');
        // The Wi-Fi QR standard only carries nopass / WEP / WPA; WPA2/WPA3 all map
        // to WPA, and enterprise networks can't be joined by a plain QR (no EAP),
        // so we still encode SSID+password best-effort as WPA.
        const s = x.fields.security || 'WPA2';
        const sec = s === 'nopass' ? 'nopass' : s === 'WEP' ? 'WEP' : 'WPA';
        const payload = `WIFI:T:${sec};S:${esc(x.fields.ssid)};${sec === 'nopass' ? '' : 'P:' + esc(x.fields.password) + ';'}${x.fields.hidden ? 'H:true;' : ''};`;
        try { const mod = await import('qrcode'); const QR = mod.default ?? mod; this.wifiQr = await QR.toDataURL(payload, { margin: 1, width: 220 }); } catch (e) { this.wifiQr = ''; }
    },

    /* ---- TOTP (RFC 6238, client-side via WebCrypto) ---- */
    async _tickTotp() {
        const period = 30, now = Math.floor(Date.now() / 1000), remain = period - (now % period);
        const targets = this.current ? [this.current] : this.filtered;
        for (const x of targets) {
            const secret = x.fields?.totp;
            if (x.type !== 'login' || ! secret) continue;
            try { this.totpNow[x.id] = { code: await this._totp(secret, now, period), remain }; } catch (e) { /* bad secret */ }
        }
    },
    _totp(secret, now, period) { return pwTotp(secret, now, period); },
    hasTotp(x) { return x.type === 'login' && ! ! x.fields?.totp; },
    totpCode(x) { const c = this.totpNow[x.id]?.code || ''; return c ? c.slice(0, 3) + ' ' + c.slice(3) : '··· ···'; },
    totpRemain(x) { return this.totpNow[x.id]?.remain ?? 0; },

    /* ---- Vault sharing (manager) ---- */
    openShareDialog(vaultId) {
        this.shareDialog = { open: true, vaultId, identifier: '', role: 'read', lookingUp: false, resolved: null, fingerprintStatus: null, sharing: false, notice: '' };
    },
    closeShareDialog() { this.shareDialog = { ...this.shareDialog, open: false }; },
    async lookUpRecipient() {
        const d = this.shareDialog; if (! d.vaultId || ! d.identifier.trim()) return;
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
    async confirmShare() {
        const d = this.shareDialog;
        if (! d.resolved || d.fingerprintStatus === 'changed') return;
        if (d.fingerprintStatus === 'new') {
            // Store fingerprint in personal manifest on confirmation.
            const store = window.LLStore.data;
            store.knownFingerprints = store.knownFingerprints || {};
            store.knownFingerprints[d.resolved.user_id] = d.resolved.fingerprint;
            this._save();
        }
        d.sharing = true; d.notice = '';
        try {
            const vkBytes = this._sharedKeys[d.vaultId];
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
    async acceptInvite(inv) {
        const id = await Vault.ensureIdentityKeys();
        // Verify the wrapped key actually decrypts before accepting.
        try {
            await VaultShareCrypto.unwrapVaultKey(inv.wrapped_vault_key, id.pub, id.sk);
        } catch (e) {
            window.llToast(labels.inviteInvalid || '');
            return;
        }
        try {
            await apiRequest('POST', '/vaults/' + inv.vault_id + '/members/' + inv.member_id + '/accept');
            this.pendingInvites = this.pendingInvites.filter((p) => p.member_id !== inv.member_id);
            await this._loadSharedVaults();
        } catch (e) {
            window.llToast(labels.saveFailed || '');
        }
    },
    async createSharedVault() {
        const raw = await this.$store.confirm.prompt('', { placeholder: labels.newSharedVaultName || '', ok: labels.save || '' });
        const name = (raw || '').trim(); if (! name) return;
        try {
            const vk = await VaultShareCrypto.newVaultKey();
            const idk = await Vault.ensureIdentityKeys();
            const wrapped = await VaultShareCrypto.wrapVaultKeyFor(vk, idk.pub);
            const { id } = await apiRequest('POST', '/vaults', { wrapped_vault_key: wrapped });
            const vkBytes = Uint8Array.from(atob(vk), (c) => c.charCodeAt(0));
            const manifest = { name, items: [] };
            const sealed = await VaultShareCrypto.sealVaultManifest(manifest, vkBytes);
            const res = await apiRequest('PUT', '/vaults/' + id + '/store', { sealed_manifest: sealed, expected_version: 0 });
            const version = (res && typeof res.version === 'number') ? res.version : 1;
            this._sharedKeys[id] = vkBytes;
            this._sharedVersion[id] = version;
            this.sharedVaults.push({ id, name, role: 'manage', shared: true, version, vaultId: id });
            this.sharedItems[id] = [];
        } catch (e) {
            window.llToast(labels.saveFailed || '');
        }
    },

    async moveItems(ids, targetId) {
        if (! ids || ! ids.length) return;
        const idSet = new Set(ids);
        // Determine source vault(s): find where each id lives.
        const isTargetShared = this.isSharedVault(targetId);
        const isTargetEditable = this.canEditVault(targetId);
        if (! isTargetEditable) { window.llToast(labels.moveDenied || ''); return; }

        // Gather items to move and validate source editability.
        // Snapshot the original item and its source location for rollback.
        const toMove = [];
        for (const id of ids) {
            const personal = this.items.find((x) => x.id === id);
            if (personal) {
                toMove.push({ item: personal, originalItem: { ...personal }, sourceVaultId: null });
                continue;
            }
            for (const sv of this.sharedVaults) {
                const found = (this.sharedItems[sv.id] || []).find((x) => x.id === id);
                if (found) { toMove.push({ item: found, originalItem: { ...found }, sourceVaultId: sv.id }); break; }
            }
        }
        if (! toMove.length) return;

        // Check each source is editable.
        for (const { sourceVaultId } of toMove) {
            if (! this.canEditVault(sourceVaultId || '')) { window.llToast(labels.moveDenied || ''); return; }
        }

        // Build the moved items for the target.
        const movedItems = toMove.map(({ item }) => {
            if (isTargetShared) {
                const { shared: _s, vaultId: _v, folder: _f, ...rest } = item;
                return { ...rest, shared: true, vaultId: targetId, folder: targetId };
            } else {
                const { shared: _s, vaultId: _v, ...rest } = item;
                return { ...rest, folder: targetId || null };
            }
        });

        // Add to target in-memory (do NOT remove from source yet).
        if (isTargetShared) {
            const arr = [...(this.sharedItems[targetId] || [])];
            for (const mi of movedItems) {
                const idx = arr.findIndex((x) => x.id === mi.id);
                if (idx >= 0) arr[idx] = mi; else arr.unshift(mi);
            }
            this.sharedItems = { ...this.sharedItems, [targetId]: arr };
        } else {
            for (const mi of movedItems) {
                const idx = this.items.findIndex((x) => x.id === mi.id);
                if (idx >= 0) this.items[idx] = mi; else this.items.unshift(mi);
            }
        }

        // Write target FIRST. On failure: restore item(s) to source in-memory,
        // so no item ever disappears from both places.
        if (isTargetShared) {
            try {
                await this._saveVault(targetId);
            } catch (e) {
                // Full rollback: remove from target, restore originals to source.
                const arr = (this.sharedItems[targetId] || []).filter((x) => ! idSet.has(x.id));
                this.sharedItems = { ...this.sharedItems, [targetId]: arr };
                // Restore each item to its source collection.
                for (const { originalItem, sourceVaultId } of toMove) {
                    if (sourceVaultId === null) {
                        const i = this.items.findIndex((x) => x.id === originalItem.id);
                        if (i < 0) this.items.unshift(originalItem);
                        else this.items[i] = originalItem;
                    } else {
                        const srcArr = [...(this.sharedItems[sourceVaultId] || [])];
                        const i = srcArr.findIndex((x) => x.id === originalItem.id);
                        if (i < 0) srcArr.unshift(originalItem); else srcArr[i] = originalItem;
                        this.sharedItems = { ...this.sharedItems, [sourceVaultId]: srcArr };
                    }
                }
                window.llToast(labels.saveFailed || '');
                return;
            }
        } else {
            // Personal target: _save() is synchronous and cannot fail network-wise.
            this._save();
        }

        // Target saved successfully. Now remove from each source.
        const sourceVaultIds = new Set(toMove.map((t) => t.sourceVaultId));
        for (const srcId of sourceVaultIds) {
            if (srcId === null) {
                if (isTargetShared) {
                    // personal→shared: items were copied to sharedItems; remove originals from personal.
                    for (let i = this.items.length - 1; i >= 0; i--) {
                        if (idSet.has(this.items[i].id)) this.items.splice(i, 1);
                    }
                    this._save();
                }
                // personal→personal: items were mutated in-place in this.items by the
                // "Add to target" block; _save() already called in the target write step.
            } else {
                const arr = (this.sharedItems[srcId] || []).filter((x) => ! idSet.has(x.id));
                this.sharedItems = { ...this.sharedItems, [srcId]: arr };
                try { await this._saveVault(srcId); } catch (e) { window.llToast(labels.saveFailed || ''); }
            }
        }

        // Clear current if it was one of the moved items (its ref may be stale).
        if (this.current && idSet.has(this.current.id)) {
            this.current = null;
        }

        // If the active filter pointed at a source shared vault and it is now empty, reset to All.
        const sourceVaultIdSet = new Set(toMove.map((t) => t.sourceVaultId).filter((id) => id !== null));
        for (const srcId of sourceVaultIdSet) {
            if (this.filterFolder === srcId && ! (this.sharedItems[srcId] || []).length) {
                this.filterFolder = '';
            }
        }

        this.selectedIds = [];
        this._autoSelect();
    },

    fmtDate(v) { if (! v) return ''; try { return new Date(v).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return ''; } },

    /* ---- Member management + cryptographic revocation ---- */

    // Open the manage-members panel: fetch the current member list from the server.
    async openManageMembers(vaultId) {
        this.managingVaultId = vaultId;
        this.managingVaultMembers = [];
        this.managingVaultLoading = true;
        try {
            const members = await apiRequest('GET', '/vaults/' + vaultId + '/members');
            this.managingVaultMembers = Array.isArray(members) ? members : [];
        } catch (e) {
            window.llToast(labels.saveFailed || '');
        } finally {
            this.managingVaultLoading = false;
        }
    },

    // Remove a member from a shared vault via atomic key rotation.
    // Generates a new vault key, re-wraps it for every remaining active member,
    // and atomically replaces the sealed manifest + removes the revoked member.
    async removeMember(memberId, memberUserId) {
        if (! await this.$store.confirm.ask(labels.removeMemberConfirm || '')) return;
        const vaultId = this.managingVaultId;
        if (! vaultId) return;
        const vkBytes = this._sharedKeys && this._sharedKeys[vaultId];
        if (! vkBytes) { window.llToast(labels.saveFailed || ''); return; }
        this.rotatingKeys = true;
        try {
            // Generate a new vault key for forward secrecy.
            const newVkB64 = await VaultShareCrypto.newVaultKey();
            const newVkBytes = Uint8Array.from(atob(newVkB64), (c) => c.charCodeAt(0));
            // Remaining active members (exclude the one being removed).
            const remaining = this.managingVaultMembers.filter(
                (m) => m.id !== memberId && m.status === 'active' && m.public_key,
            );
            // Re-wrap the new vault key for each remaining member.
            const members = await Promise.all(remaining.map(async (m) => ({
                user_id: m.user_id,
                wrapped_vault_key: await VaultShareCrypto.wrapVaultKeyFor(newVkB64, m.public_key),
            })));
            // Seal the current manifest under the new key.
            const vault = this.sharedVaults.find((sv) => sv.id === vaultId);
            const manifest = { name: vault ? vault.name : '', items: this.sharedItems[vaultId] || [] };
            const sealed = await VaultShareCrypto.sealVaultManifest(manifest, newVkBytes);
            const expectedVersion = this._sharedVersion[vaultId] ?? 0;
            const res = await fetch('/vaults/' + vaultId + '/rotate', {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ sealed_manifest: sealed, expected_version: expectedVersion, members, remove_member_id: memberId }),
            });
            if (res.status === 409) {
                // Version conflict: reload vault state and retry once.
                await this._loadSharedVaults();
                window.llToast(labels.saveConflict || '');
                return;
            }
            if (! res.ok) { window.llToast(labels.saveFailed || ''); return; }
            const { version } = await res.json();
            // Update in-memory state with the new key and version.
            this._sharedKeys[vaultId] = newVkBytes;
            this._sharedVersion[vaultId] = version;
            this.managingVaultMembers = this.managingVaultMembers.filter((m) => m.id !== memberId);
            window.llToast(labels.memberRemoved || '');
        } catch (e) {
            window.llToast(labels.saveFailed || '');
        } finally {
            this.rotatingKeys = false;
        }
    },

    // Delete a shared vault permanently (manager only).
    // Server DELETE + local cleanup, WITHOUT the confirm prompt — shared by the
    // per-vault delete button and the manager-scoped reset.
    async _deleteSharedVaultNow(vaultId) {
        await postForm('/vaults/' + vaultId, null, 'DELETE');
        this.sharedVaults = this.sharedVaults.filter((sv) => sv.id !== vaultId);
        delete this.sharedItems[vaultId];
        delete this._sharedKeys[vaultId];
        delete this._sharedVersion[vaultId];
        if (this.filterFolder === vaultId) this.filterFolder = '';
        if (this.managingVaultId === vaultId) this.managingVaultId = null;
    },
    async deleteSharedVault(vaultId) {
        if (! await this.$store.confirm.ask(labels.deleteVaultConfirm || '')) return;
        try { await this._deleteSharedVaultNow(vaultId); }
        catch (e) { window.llToast(labels.saveFailed || ''); }
    },
});
