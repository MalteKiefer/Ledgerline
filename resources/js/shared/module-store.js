// Per-module sealed store (Store v3 split). Each module (notes/todos/bookmarks/
// contacts/invoices/passwords/health/sharing) gets its own opaque sealed row at
// GET/PUT /store/<module>, so a mutation in one module never re-seals the others.
//
// Same optimistic-concurrency + debounced-save contract as the old monolith
// LLStore (which this replaces), factored so every module shares one proven
// flush/409/429 path. All crypto stays in window.Vault (canonical-JSON + suite
// envelope, §5.2/§6.1); the server only ever sees ciphertext + a version.

import { newId } from './sealed-store';
import { jsonHeaders } from './api';

/**
 * @param {string} module  allowlisted module key (matches the server allowlist)
 * @param {() => object} blankFn  fresh empty shape for this module
 */
export function makeStore(module, blankFn) {
    return {
        module,
        data: null,
        version: 0,
        ready: false,
        loaded: false,
        _timer: null,
        _saving: false,
        _again: false,
        _onError: null,

        newId() { return newId(); },
        _blank() { return blankFn(); },

        async load() {
            const res = await fetch('/store/' + module, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const d = await res.json();
            this.version = d.version ?? 0;
            this.data = d.ciphertext ? window.Vault.openManifest(d.ciphertext) : this._blank();
            // Forward-compat: ensure every key of the blank shape exists.
            const blank = this._blank();
            for (const k of Object.keys(blank)) if (! (k in this.data)) this.data[k] = blank[k];
            this.loaded = true;
            this.ready = true;
            return this.data;
        },

        reset() { this.data = null; this.version = 0; this.ready = false; this.loaded = false; clearTimeout(this._timer); },

        touch() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.flush(), 800);
        },

        // Seal + PUT with optimistic concurrency. On 409 adopt the server version
        // and re-PUT our copy (single-user last-write-wins); on 429 honour
        // Retry-After. Never silently drop a destructive edit.
        async flush() {
            if (! this.loaded) return;
            if (this._saving) { this._again = true; return; }
            this._saving = true;
            try {
                const body = JSON.stringify({ ciphertext: window.Vault.sealManifest(this.data), version: this.version });
                const res = await fetch('/store/' + module, { method: 'PUT', headers: jsonHeaders(), body });
                if (res.status === 409) {
                    const cur = await fetch('/store/' + module, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
                    this.version = cur.version ?? this.version;
                    this._again = true;
                } else if (res.status === 429) {
                    const ra = parseInt(res.headers.get('Retry-After') || '', 10);
                    await new Promise((r) => setTimeout(r, Number.isFinite(ra) && ra > 0 ? ra * 1000 : 1500));
                    this._again = true;
                } else if (res.ok) {
                    this.version = (await res.json()).version ?? this.version + 1;
                } else {
                    throw new Error('module store save failed: ' + module);
                }
            } catch (e) {
                if (this._onError) this._onError();
            } finally {
                this._saving = false;
                if (this._again) { this._again = false; this.touch(); }
            }
        },
    };
}

/** The per-module blank shapes (each module's own sealed collection). */
export const MODULE_BLANKS = {
    notes: () => ({ v: 3, notes: [] }),
    todos: () => ({ v: 3, todos: [], todoLists: [] }),
    bookmarks: () => ({ v: 3, bookmarks: [], bookmarkFolders: [] }),
    contacts: () => ({ v: 3, contacts: [] }),
    invoices: () => ({ v: 3, invoices: [], invoiceSeq: 0 }),
    passwords: () => ({ v: 3, secrets: [], secretFolders: [], pwVaultMigrated: false }),
    health: () => ({ v: 3, healthEntries: [], healthProfile: null }),
    sharing: () => ({ v: 3, knownFingerprints: {} }),
    explore: () => ({ v: 3, tracks: [], couplings: {}, settings: { couplingTimeToleranceS: 3600, couplingDistanceToleranceM: 100 } }),
};

/** Build the window-global registry of per-module stores. */
export function buildModuleStores() {
    const stores = {};
    for (const [name, blank] of Object.entries(MODULE_BLANKS)) stores[name] = makeStore(name, blank);
    return stores;
}
