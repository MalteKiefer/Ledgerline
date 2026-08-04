// PGP key management (Profile -> Encryption keys). Keys live ONLY in the sealed
// `mailkeys` vault store — the whole store is sealed under the vault key, so the
// private key armor inside never reaches the server in the clear. Reading-only:
// import an armored private key, generate a new keypair, delete, copy public.
// Decryption of mail happens in the mail archive, not here.

import { keyInfo, generateKey, publicFromPrivate } from '../shared/pgp.js';
import { importP12, generateSmime, importSmimePem } from '../shared/smime.js';
import { newId } from '../shared/sealed-store.js';
import { fetchBlobBuffer } from '../shared/blob-io.js';

export default (config) => ({
    config: config || {},
    keys: [],
    state: 'loading', // loading | locked | ready
    busy: false,
    error: '',
    mode: 'list', // list | import | generate
    // import form
    impMode: 'paste', // paste | computer | app  (tabs in the import modal)
    impArmored: '',
    impPassphrase: '',
    impName: '',
    // "from the app's Files" — a real file browser over the vault Files tree.
    appFilesLoading: false,
    appFileError: '',
    _allFiles: [],        // window.LLFilesStore.data.files
    _allFolders: [],      // window.LLFilesStore.data.fileFolders
    browserCwd: null,     // current folder id (null = root)
    browserPath: [],      // breadcrumb: [{ id, name }]
    // generate form — full spectrum
    genType: 'ecc',          // ecc | rsa
    genCurve: 'curve25519',
    genRsaBits: 3072,
    genPassphrase: '',
    genExpiry: '0',          // '0' | '31536000' (1y) | '63072000' (2y) | '94608000' (3y)
    genSignSubkey: false,    // add a separate signing subkey
    genUserIDs: [{ name: '', email: '' }], // one or more identities
    // s/mime import form (.p12) + PEM import + generate
    smName: '',
    smPass: '',
    smImpMode: 'p12',     // p12 | pem  (S/MIME import tabs)
    smPem: '',
    _p12: null,
    // s/mime generate form
    smGenCn: '',
    smGenEmail: '',
    smGenBits: 3072,
    smGenExpiry: '730',   // days
    _store: null,

    async init() {
        this._store = window.LLModuleStore?.mailkeys;
        const boot = async () => {
            if (this.$store.vault?.unlocked && this._store) {
                try { await this._store.load(); this.keys = [...(this._store.data.keys || [])]; this.state = 'ready'; } catch { this.state = 'ready'; }
            } else {
                this.state = 'locked';
            }
        };
        this.$watch(() => this.$store.vault?.unlocked, (v) => { if (v) boot(); });
        await boot();
    },

    unlock() { this.$dispatch('vault-panel'); },

    _persist() {
        this._store.data.keys = this.keys.map((k) => ({ ...k }));
        this._store.touch();
    },

    async importKey() {
        const armored = this.impArmored.trim();
        if (!armored.includes('BEGIN PGP PRIVATE KEY')) { this.error = this.config?.errNotPrivate || 'Not a private key.'; return; }
        this.busy = true;
        this.error = '';
        try {
            const info = await keyInfo(armored);
            const publicKey = await publicFromPrivate(armored);
            this.keys.push({
                id: newId(),
                type: 'pgp',
                name: this.impName.trim() || info.userId || info.fingerprint.slice(-8),
                fingerprint: info.fingerprint,
                userId: info.userId,
                privateKey: armored,
                publicKey,
                passphrase: this.impPassphrase || '',
                createdAt: new Date().toISOString(),
            });
            this._persist();
            this.impArmored = ''; this.impPassphrase = ''; this.impName = '';
            this.mode = 'list';
        } catch (e) {
            this.error = this.config?.errImport || 'Could not import this key.';
        } finally {
            this.busy = false;
        }
    },

    // ---- generate form helpers ----
    addUserId() { this.genUserIDs.push({ name: '', email: '' }); },
    removeUserId(i) { if (this.genUserIDs.length > 1) this.genUserIDs.splice(i, 1); },

    async generate() {
        const ids = this.genUserIDs
            .map((u) => ({ name: (u.name || '').trim(), email: (u.email || '').trim() }))
            .filter((u) => u.name || u.email);
        if (!ids.length) { this.error = this.config?.errNoIdentity || 'Add at least one name or email.'; return; }
        this.busy = true;
        this.error = '';
        try {
            const kp = await generateKey({
                type: this.genType,
                curve: this.genCurve,
                rsaBits: Number(this.genRsaBits),
                userIDs: ids,
                passphrase: this.genPassphrase || undefined,
                keyExpirationSeconds: Number(this.genExpiry) || 0,
                signSubkey: !!this.genSignSubkey,
            });
            const primary = ids[0];
            this.keys.push({
                id: newId(),
                type: 'pgp',
                name: primary.name || primary.email || kp.fingerprint.slice(-8),
                fingerprint: kp.fingerprint,
                userId: kp.userId,
                privateKey: kp.privateKey,
                publicKey: kp.publicKey,
                passphrase: this.genPassphrase || '',
                createdAt: new Date().toISOString(),
            });
            this._persist();
            this.genUserIDs = [{ name: '', email: '' }];
            this.genPassphrase = '';
            this.genSignSubkey = false;
            this.genExpiry = '0';
            this.mode = 'list';
        } catch (e) {
            this.error = this.config?.errGenerate || 'Could not generate a key.';
        } finally {
            this.busy = false;
        }
    },

    // Open the import modal on a specific tab (resets state).
    openImport() {
        this.mode = 'import';
        this.impMode = 'paste';
        this.impArmored = '';
        this.impPassphrase = '';
        this.impName = '';
        this.appFileError = '';
        this.error = '';
    },

    // "Aus App-Dateien": list the personal Files, so a key stored there can be
    // picked, decrypted client-side and loaded into the import field. ZK — the
    // file is decrypted in the browser, never round-tripped to the server.
    async loadAppFiles() {
        this.appFileError = '';
        this.appFilesLoading = true;
        try {
            if (!window.Vault?.unlocked) { this.appFileError = this.config?.errLocked || 'Unlock the vault first.'; return; }
            if (!window.LLFilesStore.loaded) await window.LLFilesStore.load();
            this._allFiles = [...(window.LLFilesStore.data.files || [])];
            this._allFolders = [...(window.LLFilesStore.data.fileFolders || [])];
            this.browserCwd = null;
            this.browserPath = [];
        } catch {
            this.appFileError = this.config?.errImport || 'Could not load your files.';
        } finally {
            this.appFilesLoading = false;
        }
    },
    // Folders directly under the current directory.
    browserFolders() {
        return this._allFolders
            .filter((f) => (f.parent ?? null) === this.browserCwd)
            .sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    },
    // Files in the current directory (key-looking first).
    browserFiles() {
        return this._allFiles
            .filter((f) => (f.folder ?? null) === this.browserCwd)
            .sort((a, b) => (this._looksKey(b) - this._looksKey(a)) || (a.name || '').localeCompare(b.name || ''));
    },
    _looksKey(f) {
        return /\.(asc|gpg|key|pgp|pem)$/i.test(f.name || '') ? 1 : 0;
    },
    enterFolder(folder) {
        this.browserCwd = folder.id;
        this.browserPath.push({ id: folder.id, name: folder.name });
    },
    browserGoto(idx) {
        // idx = -1 → root; else jump to that breadcrumb crumb.
        if (idx < 0) { this.browserCwd = null; this.browserPath = []; return; }
        this.browserPath = this.browserPath.slice(0, idx + 1);
        this.browserCwd = this.browserPath[idx].id;
    },
    async pickAppFile(f) {
        this.appFileError = '';
        this.busy = true;
        try {
            const buf = await fetchBlobBuffer(`${this.config.filesRawBase}/${f.blob}`);
            const bytes = window.Vault.decryptFile(buf, f.encFileKey);
            this.impArmored = new TextDecoder().decode(bytes);
            if (!this.impName) this.impName = (f.name || '').replace(/\.(asc|gpg|key|pgp|pem|txt)$/i, '');
        } catch {
            this.appFileError = this.config?.errImport || 'Could not read this file.';
        } finally {
            this.busy = false;
        }
    },

    // Import an armored PGP key from a file on disk (.asc / .gpg / .key / .pgp /
    // .txt). Reads it into the copy-paste textarea so the same importKey() path
    // (validate → derive public → seal) handles it.
    async impFileChosen(e) {
        const file = (e.target.files && e.target.files[0]) || null;
        if (!file) return;
        try {
            const text = await file.text();
            this.impArmored = text;
            if (!this.impName) this.impName = file.name.replace(/\.(asc|gpg|key|pgp|txt)$/i, '');
        } catch {
            this.error = this.config?.errImport || 'Could not read this file.';
        } finally {
            e.target.value = '';
        }
    },

    p12Chosen(e) {
        this._p12 = (e.target.files && e.target.files[0]) || null;
    },

    async importSmime() {
        if (!this._p12) { this.error = this.config?.errNoFile || 'Choose a .p12 file.'; return; }
        this.busy = true;
        this.error = '';
        try {
            const bytes = new Uint8Array(await this._p12.arrayBuffer());
            const imp = await importP12(bytes, this.smPass || '');
            this.keys.push({
                id: newId(),
                type: 'smime',
                name: this.smName.trim() || imp.subject || imp.fingerprint.slice(-8),
                fingerprint: imp.fingerprint,
                userId: imp.subject,
                privateKeyPem: imp.privateKeyPem,
                certPem: imp.certPem,
                createdAt: new Date().toISOString(),
            });
            this._persist();
            this.smName = ''; this.smPass = ''; this._p12 = null;
            this.mode = 'list';
        } catch (e) {
            this.error = this.config?.errP12 || 'Could not import this .p12 (wrong passphrase?).';
        } finally {
            this.busy = false;
        }
    },

    // Import an S/MIME identity from pasted PEM (key + certificate).
    async importSmimePemNow() {
        if (!this.smPem.trim()) { this.error = this.config?.errImport || 'Paste a key + certificate PEM.'; return; }
        this.busy = true;
        this.error = '';
        try {
            const imp = await importSmimePem(this.smPem);
            this._pushSmime(imp, this.smName.trim());
            this.smPem = ''; this.smName = ''; this.mode = 'list';
        } catch {
            this.error = this.config?.errP12 || 'Could not import this S/MIME PEM.';
        } finally {
            this.busy = false;
        }
    },

    // Read a .pem/.crt/.key file from disk into the S/MIME PEM field.
    async smPemFileChosen(e) {
        const file = (e.target.files && e.target.files[0]) || null;
        if (!file) return;
        try {
            const text = await file.text();
            this.smPem = this.smPem ? (this.smPem + '\n' + text) : text; // key + cert can be two files
            if (!this.smName) this.smName = file.name.replace(/\.(pem|crt|cer|key|txt)$/i, '');
        } catch {
            this.error = this.config?.errImport || 'Could not read this file.';
        } finally {
            e.target.value = '';
        }
    },

    // Generate a self-signed S/MIME identity in the browser.
    async generateSmimeNow() {
        if (!this.smGenEmail.trim() && !this.smGenCn.trim()) { this.error = this.config?.errNoIdentity || 'Add a name or email.'; return; }
        this.busy = true;
        this.error = '';
        try {
            const imp = await generateSmime({
                commonName: this.smGenCn.trim(),
                email: this.smGenEmail.trim(),
                rsaBits: Number(this.smGenBits),
                expiryDays: Number(this.smGenExpiry) || 730,
            });
            this._pushSmime(imp, this.smGenCn.trim() || this.smGenEmail.trim());
            this.smGenCn = ''; this.smGenEmail = ''; this.mode = 'list';
        } catch {
            this.error = this.config?.errGenerate || 'Could not generate an S/MIME key.';
        } finally {
            this.busy = false;
        }
    },

    _pushSmime(imp, name) {
        this.keys.push({
            id: newId(),
            type: 'smime',
            name: name || imp.subject || imp.fingerprint.slice(-8),
            fingerprint: imp.fingerprint,
            userId: imp.subject,
            privateKeyPem: imp.privateKeyPem,
            certPem: imp.certPem,
            createdAt: new Date().toISOString(),
        });
        this._persist();
    },

    keyType(k) { return k.type === 'smime' ? 'S/MIME' : 'PGP'; },

    async removeKey(id) {
        if (!await this.$store.confirm.ask(this.config?.confirmDelete || 'Delete this key?')) return;
        this.keys = this.keys.filter((k) => k.id !== id);
        this._persist();
    },

    async copyPublic(k) {
        try { await navigator.clipboard.writeText(k.publicKey || k.certPem || ''); window.llToast?.(this.config?.copied || 'Copied'); } catch { /* ignore */ }
    },

    fp(k) { return (k.fingerprint || '').replace(/(.{4})/g, '$1 ').trim().toUpperCase(); },
});
