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
 * Stable merge key for a record (id / credentialId / seq), namespaced so key kinds
 * never collide, or undefined when unkeyable. Mirrors web shared/manifest-merge.js.
 */
function keyOf(rec) {
    if (! isPlainObject(rec)) return undefined;
    if (rec.id != null) return 'id\0' + rec.id;
    if (rec.credentialId != null) return 'cred\0' + rec.credentialId;
    if (rec.seq != null) return 'seq\0' + rec.seq;
    if (rec.photoId != null && rec.idx != null) return 'pf\0' + rec.photoId + '\0' + rec.idx;
    return undefined;
}

function keyable(arr) {
    if (! Array.isArray(arr)) return false;
    const seen = new Set();
    for (const r of arr) {
        const k = keyOf(r);
        if (k === undefined || seen.has(k)) return false;
        seen.add(k);
    }
    return true;
}

function scalarArray(arr) {
    return Array.isArray(arr) && arr.every((v) => v === null || typeof v !== 'object');
}

/** Set-union merge for scalar arrays (fields.urls[] strings): both writers' additions survive. */
function mergeScalarSet(base, ours, server) {
    const baseSet = new Set(base);
    const ourSet = new Set(ours);
    const deleted = new Set([...baseSet].filter((v) => ! ourSet.has(v)));
    const result = server.filter((v) => ! deleted.has(v));
    const have = new Set(result);
    for (const v of ours) {
        if (! baseSet.has(v) && ! have.has(v)) { result.push(v); have.add(v); }
    }
    return result.map(clone);
}

/**
 * Merge an array of keyed records: drop records we deleted, upsert ours, deep-merge a
 * record changed on both sides. Records key by keyOf() (id/credentialId/seq); scalar
 * arrays merge as a set-union. Mirrors web shared/manifest-merge.js.
 */
export function mergeArrayById(base, ours, server) {
    if (scalarArray(ours) && scalarArray(server) && scalarArray(base)) {
        return mergeScalarSet(base, ours, server);
    }
    if (! keyable(ours) || ! keyable(server) || ! keyable(base)) {
        return changed(base, ours) ? clone(ours) : clone(server);
    }

    const baseKeys = new Set(base.map(keyOf));
    const ourKeys = new Set(ours.map(keyOf));
    const deleted = new Set([...baseKeys].filter((k) => ! ourKeys.has(k)));
    const baseByKey = new Map(base.map((r) => [keyOf(r), r]));

    const result = server.filter((r) => ! deleted.has(keyOf(r))).map(clone);
    const indexByKey = new Map(result.map((r, i) => [keyOf(r), i]));

    for (const rec of ours) {
        const k = keyOf(rec);
        const b = baseByKey.get(k);
        if (b !== undefined && ! changed(b, rec)) continue;
        if (indexByKey.has(k)) {
            const idx = indexByKey.get(k);
            const serverRec = result[idx];
            // Both sides changed this record — deep-merge so a concurrently-added
            // embedded passkey / nested field survives (mirrors the web fix).
            if (b !== undefined && isPlainObject(b) && isPlainObject(rec) && isPlainObject(serverRec) && changed(b, serverRec)) {
                result[idx] = mergeManifest(b, rec, serverRec);
            } else {
                result[idx] = clone(rec);
            }
        } else {
            indexByKey.set(k, result.length);
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
            // Recurse into a concurrently-edited nested id-array / object (e.g. a login
            // item's fields.passkeys[] edited on another device) so it is merged, not
            // overwritten wholesale. Mirrors the web shared/manifest-merge.js.
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
