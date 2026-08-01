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

// JSON-safe clone. structuredClone throws a DataCloneError once Alpine has wrapped a
// record in its reactive Proxy ("[object Object] could not be cloned"), which would
// abort a 409 rebase and lose the write. Merge operates on JSON-contract store data,
// so a JSON round-trip is both correct and proxy-safe. (undefined → undefined.)
function clone(v) {
    return v == null ? v : JSON.parse(JSON.stringify(v));
}

/** Change detection for merge purposes only (never a crypto/canonical comparison). */
function changed(a, b) {
    return JSON.stringify(a) !== JSON.stringify(b);
}

/**
 * Stable merge key for a record. Records SHOULD carry `id`, but several sealed
 * collections are keyed differently or are not objects at all: embedded passkeys
 * key on `credentialId`, invoice versions on `seq`. Returns a namespaced key so two
 * different key kinds never collide, or undefined when the element is unkeyable.
 */
function keyOf(rec) {
    if (! isPlainObject(rec)) return undefined;
    if (rec.id != null) return 'id\0' + rec.id;
    if (rec.credentialId != null) return 'cred\0' + rec.credentialId;
    // NOTE: `seq` is deliberately NOT a merge key. seq is a per-invoice counter, so two
    // devices versioning the same invoice both mint the same seq — keying on it let the
    // loser's 409 rebase overwrite the winner's committed version. Invoice versions now
    // carry a stable random id (backfilled on load), so they key on id above.
    // A gallery person.faces membership keys naturally on (photoId, idx) — the
    // face-scan worker builds these without an id, so a composite natural key lets
    // concurrent face tags from two devices union instead of clobbering.
    if (rec.photoId != null && rec.idx != null) return 'pf\0' + rec.photoId + '\0' + rec.idx;
    return undefined;
}

/** True if every element of arr yields a UNIQUE keyOf() — safe to merge by key. */
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

/**
 * True if every element is a STRING — a set (tags, urls, album photoIds) safe to union.
 * Numeric arrays are deliberately excluded: a positional numeric vector (a face centroid
 * / embedding) is NOT a set — set-unioning two recomputed centroids yields a garbage
 * ~2x-length vector. Numeric arrays fall through to last-writer-wins (correct for a
 * recomputable vector), never to a union.
 */
function scalarArray(arr) {
    return Array.isArray(arr) && arr.every((v) => typeof v === 'string');
}

/**
 * Set-union merge for scalar arrays (fields.urls[] strings, album.photoIds[] strings):
 * keep the server's members, drop the ones we deleted (in base, absent from ours),
 * add the ones we introduced (in ours, absent from base). Both writers' additions
 * survive; dedup preserves order (server first, then our new members).
 */
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
 * Merge an array of keyed records: start from the server's list, drop records we
 * deleted (in base, absent from ours), then upsert records we added or modified.
 * A record changed on BOTH sides is deep-merged (nested arrays/objects union), never
 * clobbered. Records are keyed by keyOf() (id / credentialId / seq). Scalar arrays
 * merge as a set-union. Only a genuinely unkeyable OBJECT array falls back to
 * last-writer-wins — and that fallback is the data-loss path we work to avoid, so
 * every sealed collection should carry a stable key.
 */
export function mergeArrayById(base, ours, server) {
    // Scalar arrays (strings/numbers): set-union so concurrent additions both survive.
    if (scalarArray(ours) && scalarArray(server) && scalarArray(base)) {
        return mergeScalarSet(base, ours, server);
    }
    // Keyed object arrays: merge by keyOf(). If any side isn't cleanly keyable we
    // cannot align records safely → fall back to "ours if we changed it, else server".
    if (! keyable(ours) || ! keyable(server) || ! keyable(base)) {
        return changed(base, ours) ? clone(ours) : clone(server);
    }

    const baseKeys = new Set(base.map(keyOf));
    const ourKeys = new Set(ours.map(keyOf));
    const deleted = new Set([...baseKeys].filter((k) => ! ourKeys.has(k)));
    const baseByKey = new Map(base.map((r) => [keyOf(r), r]));

    // Server list minus anything we deleted, preserving server order.
    const result = server.filter((r) => ! deleted.has(keyOf(r))).map(clone);
    const indexByKey = new Map(result.map((r, i) => [keyOf(r), i]));

    for (const rec of ours) {
        const k = keyOf(rec);
        const b = baseByKey.get(k);
        // Only touch records we actually added (no base) or modified.
        if (b !== undefined && ! changed(b, rec)) continue;
        if (indexByKey.has(k)) {
            const idx = indexByKey.get(k);
            const serverRec = result[idx];
            // Both writers changed the SAME record (server diverged from base AND so
            // did we). Taking our whole record here would discard every nested change
            // the winning writer made to this record — e.g. an invoice version / receipt
            // / passkey the other device appended, whose sealed blob would then be
            // orphaned. Recursively rebase the record so both survive.
            if (b !== undefined && isPlainObject(b) && isPlainObject(rec) && isPlainObject(serverRec) && changed(b, serverRec)) {
                result[idx] = mergeManifest(b, rec, serverRec);
            } else {
                // Only we changed it (the server's copy still equals base) → ours wins.
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
