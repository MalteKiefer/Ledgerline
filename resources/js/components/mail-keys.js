// PGP key management (Profile -> Encryption keys). Keys live ONLY in the sealed
// `mailkeys` vault store — the whole store is sealed under the vault key, so the
// private key armor inside never reaches the server in the clear. Reading-only:
// import an armored private key, generate a new keypair, delete, copy public.
// Decryption of mail happens in the mail archive, not here.

import { keyInfo, generateKey, publicFromPrivate } from '../shared/pgp.js';
import { importP12 } from '../shared/smime.js';
import { newId } from '../shared/sealed-store.js';

export default (config) => ({
    config: config || {},
    keys: [],
    state: 'loading', // loading | locked | ready
    busy: false,
    error: '',
    mode: 'list', // list | import | generate
    // import form
    impArmored: '',
    impPassphrase: '',
    impName: '',
    // generate form
    genName: '',
    genEmail: '',
    genPassphrase: '',
    // s/mime import form
    smName: '',
    smPass: '',
    _p12: null,
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

    async generate() {
        this.busy = true;
        this.error = '';
        try {
            const kp = await generateKey({ name: this.genName.trim(), email: this.genEmail.trim(), passphrase: this.genPassphrase || undefined });
            this.keys.push({
                id: newId(),
                type: 'pgp',
                name: this.genName.trim() || this.genEmail.trim() || kp.fingerprint.slice(-8),
                fingerprint: kp.fingerprint,
                userId: kp.userId,
                privateKey: kp.privateKey,
                publicKey: kp.publicKey,
                passphrase: this.genPassphrase || '',
                createdAt: new Date().toISOString(),
            });
            this._persist();
            this.genName = ''; this.genEmail = ''; this.genPassphrase = '';
            this.mode = 'list';
        } catch (e) {
            this.error = this.config?.errGenerate || 'Could not generate a key.';
        } finally {
            this.busy = false;
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
