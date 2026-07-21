// Collect a folder + all its descendant folders/files, re-rooting the root
// folder's parent to null (so the subtree stands alone in a new manifest).
// Pure — no Alpine/DOM — so it is unit-testable.
export function collectSubtree(folders, files, rootId) {
    const keepFolders = new Set([rootId]);
    let grew = true;
    while (grew) {
        grew = false;
        for (const f of folders) {
            if (f.parent && keepFolders.has(f.parent) && ! keepFolders.has(f.id)) { keepFolders.add(f.id); grew = true; }
        }
    }
    const outFolders = folders.filter((f) => keepFolders.has(f.id))
        .map((f) => ({ ...f, parent: f.id === rootId ? null : (f.parent ?? null) }));
    const outFiles = files.filter((f) => keepFolders.has(f.folder ?? null));
    return { folders: outFolders, files: outFiles };
}
