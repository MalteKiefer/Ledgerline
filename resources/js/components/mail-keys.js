// PGP key management (Profile -> Encryption keys). Keys live ONLY in the sealed
// `mailkeys` vault store — the whole store is sealed under the vault key, so the
// private key armor inside never reaches the server in the clear. Reading-only:
// import an armored private key, generate a new keypair, delete, copy public.
// Decryption of mail happens in the mail archive, not here.

import { keyInfo, generateKey, publicFromPrivate } from '../shared/pgp.js';
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

    async removeKey(id) {
        if (!await this.$store.confirm.ask(this.config?.confirmDelete || 'Delete this key?')) return;
        this.keys = this.keys.filter((k) => k.id !== id);
        this._persist();
    },

    async copyPublic(k) {
        try { await navigator.clipboard.writeText(k.publicKey); window.llToast?.(this.config?.copied || 'Copied'); } catch { /* ignore */ }
    },

    fp(k) { return (k.fingerprint || '').replace(/(.{4})/g, '$1 ').trim().toUpperCase(); },
});
