/**
 * Pure bookmark + folder logic for the extension.
 * Operates on a plain manifest object (mutates in place).
 * No chrome APIs, no fetch, no imports from resources/js.
 */

/**
 * Generate a 32-hex-char random id (mirrors app newId).
 * @returns {string}
 */
export function newId() {
    const b = new Uint8Array(16);
    crypto.getRandomValues(b);
    return [...b].map(x => x.toString(16).padStart(2, '0')).join('');
}

/**
 * @param {object} x
 * @returns {boolean}
 */
export function isTrashed(x) {
    return x.trashed === true || (typeof x.trashed === 'string' && x.trashed.length > 0);
}

/**
 * @param {unknown} u
 * @returns {boolean}
 */
export function isSafeUrl(u) {
    if (typeof u !== 'string') return false;
    try {
        const parsed = new URL(u);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        return false;
    }
}

/**
 * @param {object} manifest
 * @returns {{ bookmarks: object[], folders: object[] }}
 */
export function listBookmarks(manifest) {
    const bookmarks = (manifest.bookmarks ?? []).filter(b => !isTrashed(b));
    const folders = manifest.bookmarkFolders ?? [];
    return { bookmarks, folders };
}

/**
 * @param {object} manifest
 * @param {string} id
 * @returns {object|null}
 */
export function getBookmark(manifest, id) {
    return (manifest.bookmarks ?? []).find(b => b.id === id) ?? null;
}

/**
 * @param {object} manifest
 * @param {object} rec
 * @returns {{ id: string }}
 */
export function addBookmark(manifest, rec) {
    if (!Array.isArray(manifest.bookmarks)) manifest.bookmarks = [];
    const item = {
        id: newId(),
        folderId: rec.folderId ?? null,
        title: String(rec.title || '').slice(0, 500),
        url: String(rec.url || '').slice(0, 2048),
        description: String(rec.description || '').slice(0, 2000),
        tags: Array.isArray(rec.tags) ? rec.tags.slice(0, 50).map(t => String(t).slice(0, 60)) : [],
        favorite: !!rec.favorite,
        readLater: !!rec.readLater,
        read: false, // matches the web module's bookmark shape (read-later "done" flag)
    };
    manifest.bookmarks.unshift(item);
    return { id: item.id };
}

/**
 * @param {object} manifest
 * @param {string} id
 * @param {object} patch
 * @returns {{ ok: boolean }}
 */
export function updateBookmark(manifest, id, patch) {
    const item = getBookmark(manifest, id);
    if (!item) return { ok: false };
    if ('folderId' in patch) item.folderId = patch.folderId ?? null;
    if ('title' in patch) item.title = String(patch.title || '').slice(0, 500);
    if ('url' in patch) item.url = String(patch.url || '').slice(0, 2048);
    if ('description' in patch) item.description = String(patch.description || '').slice(0, 2000);
    if ('tags' in patch) item.tags = Array.isArray(patch.tags) ? patch.tags.slice(0, 50).map(t => String(t).slice(0, 60)) : [];
    if ('favorite' in patch) item.favorite = !!patch.favorite;
    if ('readLater' in patch) item.readLater = !!patch.readLater;
    return { ok: true };
}

/**
 * @param {object} manifest
 * @param {string} id
 * @param {string} nowIso
 * @returns {{ ok: boolean }}
 */
export function trashBookmark(manifest, id, nowIso) {
    const item = getBookmark(manifest, id);
    if (!item) return { ok: false };
    item.trashed = nowIso;
    return { ok: true };
}

/**
 * @param {object} manifest
 * @param {string} id
 * @returns {{ ok: boolean }}
 */
export function restoreBookmark(manifest, id) {
    const item = getBookmark(manifest, id);
    if (!item) return { ok: false };
    delete item.trashed;
    return { ok: true };
}

/**
 * @param {object} manifest
 * @param {{ name: string, parentId: string|null }} param1
 * @returns {{ id: string }}
 */
export function createFolder(manifest, { name, parentId }) {
    if (!Array.isArray(manifest.bookmarkFolders)) manifest.bookmarkFolders = [];
    const folder = {
        id: newId(),
        name: String(name || '').slice(0, 120),
        parentId: parentId ?? null,
        color: '',
        icon: '',
    };
    manifest.bookmarkFolders.push(folder);
    return { id: folder.id };
}

/**
 * @param {object} manifest
 * @param {string} id
 * @param {string} name
 * @returns {{ ok: boolean }}
 */
export function renameFolder(manifest, id, name) {
    const folder = (manifest.bookmarkFolders ?? []).find(f => f.id === id);
    if (!folder) return { ok: false };
    folder.name = String(name || '').slice(0, 120);
    return { ok: true };
}

/**
 * Reparents direct children to root, clears folderId on bookmarks pointing at
 * the deleted folder, then removes the folder.
 * @param {object} manifest
 * @param {string} id
 * @returns {{ ok: boolean }}
 */
export function deleteFolder(manifest, id) {
    const folders = manifest.bookmarkFolders ?? [];
    const exists = folders.some(f => f.id === id);
    if (!exists) return { ok: false };

    // Reparent direct children to root
    for (const f of folders) {
        if (f.parentId === id) f.parentId = null;
    }

    // Clear folderId on bookmarks pointing at the deleted folder
    for (const b of (manifest.bookmarks ?? [])) {
        if (b.folderId === id) b.folderId = null;
    }

    // Remove the folder
    manifest.bookmarkFolders = folders.filter(f => f.id !== id);
    return { ok: true };
}
