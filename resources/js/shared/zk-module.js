// Shared zero-knowledge module scaffolding. Store v3 per-module split: each
// module (notes/todos/bookmarks/contacts/invoices/passwords/health) owns its own
// sealed store at window.LLModuleStore[<module>], so a mutation in one never
// re-seals the others. zkModule() is the mixin every opaque-store module spreads
// in for its lock/unlock lifecycle, mapped store arrays and tag/trash helpers;
// cfg.store names which per-module store backs it.

// Boot a specific per-module store: wait for the vault, then lazily load it.
export async function bootStore(store, moduleName) {
    while (! store.vault.ready) { await new Promise((r) => setTimeout(r, 20)); }
    if (! store.vault.unlocked) return false;
    const ms = window.LLModuleStore[moduleName];
    if (! ms) throw new Error('unknown module store: ' + moduleName);
    if (! ms.loaded) await ms.load();
    return true;
}

// Same gate for the separate gallery index store (LLGalleryStore).
export async function bootGalleryStore(store) {
    while (! store.vault.ready) { await new Promise((r) => setTimeout(r, 20)); }
    if (! store.vault.unlocked) return false;
    if (! window.LLGalleryStore.loaded) await window.LLGalleryStore.load();
    return true;
}

export function zkModule(cfg) {
    const moduleName = cfg.store;
    return {
        state: 'boot',
        query: '',
        activeTag: '',
        error: '',
        tagsValue: '',

        // The per-module sealed store backing this component.
        _store() { return window.LLModuleStore[moduleName]; },

        // Persist this module's manifest (debounced, sealed) after a mutation.
        _save() { this._store().touch(); },

        // Point the mapped component properties at the (already-decrypted) module
        // store arrays; false while the vault is still locked.
        async _bootAssign() {
            if (! await bootStore(this.$store, moduleName)) { this.state = 'locked'; return false; }
            const data = this._store().data;
            for (const [key, prop] of Object.entries(cfg.map)) this[prop] = data[key];
            this.state = 'ready';
            return true;
        },

        async _initZk() {
            await this._bootAssign();
            this.$watch('$store.vault.unlocked', async (on) => {
                if (on && this.state !== 'ready') await this._bootAssign();
                if (! on) {
                    this.state = 'locked';
                    for (const prop of Object.values(cfg.map)) this[prop] = [];
                    if (cfg.onLock) cfg.onLock(this);
                    this._store().reset();
                }
            });
        },

        // Sorted union of every tag on the rows of a collection (for suggestions).
        _tagsOf(list) {
            const set = new Set();
            for (const x of list) for (const t of x.tags ?? []) set.add(t);
            return [...set].sort((a, b) => a.localeCompare(b));
        },

        // True for both legacy boolean true and an ISO timestamp string (new format).
        _isTrashed(x) { return x.trashed === true || (typeof x.trashed === 'string' && x.trashed.length > 0); },

        _trashCount(list) { return list.filter((x) => this._isTrashed(x)).length; },

        // Trash an item: write an ISO timestamp (canonical form going forward).
        _trash(item) { item.trashed = new Date().toISOString(); this._save(); },

        // Restore a trashed item.
        _restore(item) { item.trashed = false; this._save(); },

        // Permanently drop every trashed row of a collection (in place).
        async _emptyTrashArr(list, confirmMsg) {
            if (! await this.$store.confirm.ask(confirmMsg)) return;
            for (let i = list.length - 1; i >= 0; i--) if (this._isTrashed(list[i])) list.splice(i, 1);
            this._save();
        },
    };
}
