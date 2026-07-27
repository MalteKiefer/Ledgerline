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
import { mergeManifest } from './manifest-merge';

/** How many 409 rebase attempts before we give up and surface an error. */
const MAX_CONFLICT_RETRIES = 5;

function isPlainObj(v) {
    return v !== null && typeof v === 'object' && ! Array.isArray(v);
}

/**
 * Copy `src` onto `target` IN PLACE, preserving the object/array identities that
 * components may have bound to (e.g. health binds this.fasts = data.healthFasts).
 * A 409 rebase produces a fresh merged object; applying it in place keeps those
 * bound references live instead of silently orphaning them.
 */
function applyInPlace(target, src) {
    for (const k of Object.keys(target)) if (! (k in src)) delete target[k];
    for (const k of Object.keys(src)) {
        const s = src[k];
        const t = target[k];
        if (Array.isArray(s) && Array.isArray(t)) t.splice(0, t.length, ...s);
        else if (isPlainObj(s) && isPlainObj(t)) applyInPlace(t, s);
        else target[k] = s;
    }
}

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
        // The manifest we last loaded / committed. Our delta is derived against this
        // so a 409 can rebase (apply only our changes) onto the winning copy instead
        // of blindly overwriting it — otherwise a concurrent writer's records vanish.
        _base: null,

        newId() { return newId(); },
        _blank() { return blankFn(); },

        // Forward-compat: ensure every key of the blank shape exists on a manifest.
        _ensureShape(obj) {
            const blank = this._blank();
            for (const k of Object.keys(blank)) if (! (k in obj)) obj[k] = blank[k];
            return obj;
        },

        async _fetchManifest() {
            const d = await fetch('/store/' + module, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then((r) => r.json());
            const data = d.ciphertext ? window.Vault.openManifest(d.ciphertext) : this._blank();
            return { version: d.version ?? 0, data: this._ensureShape(data) };
        },

        async load() {
            const { version, data } = await this._fetchManifest();
            this.version = version;
            this.data = data;
            this._base = structuredClone(this.data);
            this.loaded = true;
            this.ready = true;
            return this.data;
        },

        reset() { this.data = null; this.version = 0; this.ready = false; this.loaded = false; this._base = null; clearTimeout(this._timer); },

        touch() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.flush(), 800);
        },

        // Seal + PUT with optimistic concurrency. On 409 we REBASE: fetch the winning
        // manifest and replay only our delta (base→data) onto it, then retry — up to
        // MAX_CONFLICT_RETRIES times. This preserves both writers' records instead of
        // last-writer-wins on the whole blob. On 429 honour Retry-After (does not
        // consume the conflict budget). Exhausting the budget surfaces an error;
        // never silently drops a change.
        async flush() {
            if (! this.loaded) return;
            if (this._saving) { this._again = true; return; }
            this._saving = true;
            let ok = false;
            try {
                let conflicts = 0;
                while (! ok && conflicts < MAX_CONFLICT_RETRIES) {
                    const body = JSON.stringify({ ciphertext: window.Vault.sealManifest(this.data), version: this.version });
                    const res = await fetch('/store/' + module, { method: 'PUT', headers: jsonHeaders(), body });
                    if (res.ok) {
                        this.version = (await res.json()).version ?? this.version + 1;
                        this._base = structuredClone(this.data);
                        ok = true;
                    } else if (res.status === 409) {
                        conflicts++;
                        const server = await this._fetchManifest();
                        // Rebase our delta onto the winning manifest, then retry at
                        // the winning version. Apply in place so bound refs stay live.
                        const merged = mergeManifest(this._base ?? server.data, this.data, server.data);
                        applyInPlace(this.data, merged);
                        this.version = server.version;
                    } else if (res.status === 429) {
                        const ra = parseInt(res.headers.get('Retry-After') || '', 10);
                        await new Promise((r) => setTimeout(r, Number.isFinite(ra) && ra > 0 ? ra * 1000 : 1500));
                    } else {
                        throw new Error('module store save failed: ' + module);
                    }
                }
                if (! ok) throw new Error('module store conflict budget exhausted: ' + module);
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
