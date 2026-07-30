// Shared zero-knowledge module scaffolding. Store v3 per-module split: each
// module (notes/todos/bookmarks/contacts/invoices/passwords/health) owns its own
// sealed store at window.LLModuleStore[<module>], so a mutation in one never
// re-seals the others. zkModule() is the mixin every opaque-store module spreads
// in for its lock/unlock lifecycle, mapped store arrays and tag/trash helpers;
// cfg.store names which per-module store backs it.

import { parseTags, addTags, removeTagFrom, popTag } from './tag-chips';

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
        // Tags are edited as removable badge chips. `tagsValue` stays the source of
        // truth (a comma-joined string) so every module's existing load
        // (`tags.join(', ')`) and save (`tagsValue.split(',')`) is unchanged — the chip
        // UI just reads/writes it. `tagDraft` is the in-progress text.
        tagsValue: '',
        tagDraft: '',

        // The committed chips derived from tagsValue (reactive on tagsValue).
        // A METHOD, not a getter: components spread this mixin (`{ ...zkModule() }`), and
        // object spread INVOKES a getter and copies its (static) result — which would freeze
        // the chip list at init and never react to tagsValue. A method survives the spread by
        // reference and stays reactive when called in x-for.
        tagList() { return parseTags(this.tagsValue); },
        // Commit the draft (splitting on comma too) into chips; skip duplicates.
        commitTag() { this.tagsValue = addTags(this.tagsValue, this.tagDraft); this.tagDraft = ''; },
        // Auto-commit when the user types/pastes a comma.
        onTagInput() { if ((this.tagDraft || '').includes(',')) this.commitTag(); },
        // Backspace on an empty draft removes the last chip.
        tagBackspace() { if ((this.tagDraft || '') === '') this.tagsValue = popTag(this.tagsValue); },
        removeTag(tag) { this.tagsValue = removeTagFrom(this.tagsValue, tag); },

        // The sealed store backing this component. Defaults to the per-module store;
        // cfg.instance lets a module use its OWN store (e.g. a sharded LLNotesStore).
        _store() { return cfg.instance ? cfg.instance() : window.LLModuleStore[moduleName]; },

        // Persist this module's manifest (debounced, sealed) after a mutation.
        _save() { this._store().touch(); },

        // Point the mapped component properties at the (already-decrypted) store
        // arrays; false while the vault is still locked. cfg.afterLoad runs once the
        // store is loaded (e.g. a one-time dual-read migration from an old monolith).
        async _bootAssign() {
            const ms = this._store();
            while (! this.$store.vault.ready) { await new Promise((r) => setTimeout(r, 20)); }
            if (! this.$store.vault.unlocked) { this.state = 'locked'; return false; }
            if (! ms.loaded) await ms.load();
            if (cfg.afterLoad) await cfg.afterLoad(this, ms);
            const data = ms.data;
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
