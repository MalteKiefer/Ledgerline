// Shared zero-knowledge module scaffolding. bootStore waits for the vault to be
// ready + unlocked and lazily loads the sealed workspace manifest; zkModule() is
// the mixin every opaque-store module (notes/todos/bookmarks/passwords/invoices)
// spreads in for its lock/unlock lifecycle, mapped store arrays and tag/trash
// helpers. Both lean on the window-global LLStore.

export async function bootStore(store) {
    while (! store.vault.ready) { await new Promise((r) => setTimeout(r, 20)); }
    if (! store.vault.unlocked) return false;
    if (! window.LLStore.loaded) await window.LLStore.load();
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
    return {
        state: 'boot',
        query: '',
        activeTag: '',
        error: '',
        tagsValue: '',

        // Persist the manifest (debounced, sealed) after a mutation.
        _save() { window.LLStore.touch(); },

        // Point the mapped component properties at the (already-decrypted) store
        // arrays; false while the vault is still locked.
        async _bootAssign() {
            if (! await bootStore(this.$store)) { this.state = 'locked'; return false; }
            for (const [key, prop] of Object.entries(cfg.map)) this[prop] = window.LLStore.data[key];
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
                    window.LLStore.reset();
                }
            });
        },

        // Sorted union of every tag on the rows of a collection (for suggestions).
        _tagsOf(list) {
            const set = new Set();
            for (const x of list) for (const t of x.tags ?? []) set.add(t);
            return [...set].sort((a, b) => a.localeCompare(b));
        },
        _trashCount(list) { return list.filter((x) => x.trashed).length; },

        // Permanently drop every trashed row of a collection (in place).
        async _emptyTrashArr(list, confirmMsg) {
            if (! await this.$store.confirm.ask(confirmMsg)) return;
            for (let i = list.length - 1; i >= 0; i--) if (list[i].trashed) list.splice(i, 1);
            this._save();
        },
    };
}
