// Client-side rebase merge for per-module sealed manifests (Store v3, ZK).
//
// The server is blind last-writer-wins on the whole opaque blob (it cannot merge
// ciphertext). So when our optimistic PUT is rejected (409), we must NOT re-PUT our
// own stale copy — that silently drops whatever the winning writer added. Instead we
// derive OUR delta (what changed between the base we loaded and our current copy) and
// replay ONLY that delta onto the freshly-fetched winning manifest.
//
// The merge is generic over the module shapes: top-level values are arrays of
// id-keyed records (notes/todos/tracks/…), plain objects / id-maps (settings,
// couplings, knownFingerprints, healthProfile), or scalars (v, invoiceSeq). Each is
// merged so that concurrent record-level changes from both writers survive.

function isPlainObject(v) {
    return v !== null && typeof v === 'object' && ! Array.isArray(v);
}

function clone(v) {
    return v === undefined ? v : structuredClone(v);
}

/** Change detection for merge purposes only (never a crypto/canonical comparison). */
function changed(a, b) {
    return JSON.stringify(a) !== JSON.stringify(b);
}

/**
 * Merge an array of id-keyed records: start from the server's list, drop records we
 * deleted (in base, absent from ours), then upsert records we added or modified.
 * Foreign records the server has that we never saw are preserved.
 */
export function mergeArrayById(base, ours, server) {
    const hasId = (arr) => arr.every((r) => isPlainObject(r) && 'id' in r);
    // If either side isn't a clean id-keyed record list, we cannot merge safely by
    // id — fall back to "ours if we changed it, else the server's".
    if (! hasId(ours) || ! hasId(server) || ! hasId(base)) {
        return changed(base, ours) ? clone(ours) : clone(server);
    }

    const baseIds = new Set(base.map((r) => r.id));
    const ourIds = new Set(ours.map((r) => r.id));
    const deleted = new Set([...baseIds].filter((id) => ! ourIds.has(id)));
    const baseById = new Map(base.map((r) => [r.id, r]));

    // Server list minus anything we deleted, preserving server order.
    const result = server.filter((r) => ! deleted.has(r.id)).map(clone);
    const indexById = new Map(result.map((r, i) => [r.id, i]));

    for (const rec of ours) {
        const b = baseById.get(rec.id);
        // Only touch records we actually added (no base) or modified.
        if (b !== undefined && ! changed(b, rec)) continue;
        if (indexById.has(rec.id)) {
            const idx = indexById.get(rec.id);
            const serverRec = result[idx];
            // Both writers changed the SAME record (server diverged from base AND so
            // did we). Taking our whole record here would discard every nested change
            // the winning writer made to this id — e.g. an invoice version / receipt
            // the other device appended, whose sealed PDF blob would then be orphaned
            // and reclaimed. Recursively rebase the record so both survive: nested
            // id-arrays (versions[]/receipts[]/…) union by id, object fields merge key
            // by key, scalars take ours-if-we-changed-them.
            if (b !== undefined && isPlainObject(b) && isPlainObject(rec) && isPlainObject(serverRec) && changed(b, serverRec)) {
                result[idx] = mergeManifest(b, rec, serverRec);
            } else {
                // Only we changed it (the server's copy still equals base), or the
                // record isn't a mergeable object → ours wins wholesale.
                result[idx] = clone(rec);
            }
        } else {
            indexById.set(rec.id, result.length);
            result.push(clone(rec));
        }
    }
    return result;
}

/**
 * Merge a plain object / id-map key by key: keep the server's value for keys we did
 * not touch, take ours for keys we added or changed, and drop keys we deleted.
 */
export function mergeObjectByKey(base, ours, server) {
    const result = clone(server) ?? {};
    for (const k of Object.keys(base)) {
        if (! (k in ours)) delete result[k]; // we removed this key
    }
    for (const k of Object.keys(ours)) {
        if (changed(base[k], ours[k])) {
            const sv = result[k]; // the server's value for this key
            // If BOTH sides changed this key and its value is itself an id-array or a
            // nested object, recurse so a concurrently-edited nested collection (e.g. a
            // login item's fields.passkeys[] / fields.urls[] edited on another device)
            // is merged rather than overwritten wholesale.
            if (changed(base[k], sv) && Array.isArray(ours[k]) && Array.isArray(sv)) {
                result[k] = mergeArrayById(Array.isArray(base[k]) ? base[k] : [], ours[k], sv);
            } else if (changed(base[k], sv) && isPlainObject(ours[k]) && isPlainObject(sv)) {
                result[k] = mergeObjectByKey(isPlainObject(base[k]) ? base[k] : {}, ours[k], sv);
            } else {
                result[k] = clone(ours[k]);
            }
        } else if (! (k in result)) {
            result[k] = clone(ours[k]);
        }
    }
    return result;
}

/**
 * Rebase our manifest onto the server's winning manifest, applying only our delta.
 *
 * @param {object} base    the manifest we originally loaded / last committed
 * @param {object} ours    our current, mutated copy (based on `base`)
 * @param {object} server  the winning manifest we just fetched after a 409
 * @returns {object} a new merged manifest preserving both writers' changes
 */
export function mergeManifest(base, ours, server) {
    const b = base ?? {};
    const result = clone(server) ?? {};
    for (const k of Object.keys(ours ?? {})) {
        const bv = b[k];
        const ov = ours[k];
        const sv = server ? server[k] : undefined;
        if (Array.isArray(ov)) {
            result[k] = mergeArrayById(Array.isArray(bv) ? bv : [], ov, Array.isArray(sv) ? sv : []);
        } else if (isPlainObject(ov)) {
            result[k] = mergeObjectByKey(isPlainObject(bv) ? bv : {}, ov, isPlainObject(sv) ? sv : {});
        } else {
            // scalar: keep ours if we changed it, else the server's (or ours if the
            // server has no such key).
            result[k] = changed(bv, ov) ? clone(ov) : (server && k in server ? clone(sv) : clone(ov));
        }
    }
    return result;
}
