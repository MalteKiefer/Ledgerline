// Persistent client cache of DECRYPTED mail envelopes (headers only), keyed by
// message id, in IndexedDB. This is what makes a large archive fast: a full
// message body is decrypted at most once ever per device to build its envelope;
// afterwards the list/search reads envelopes straight from here, and a sync only
// needs to process the genuinely new messages. Degrades to a no-op (returns an
// empty map) when IndexedDB is unavailable (e.g. private mode) — the caller then
// just decrypts per load.
//
// Only IMMUTABLE header fields are cached (from/to/subject/date/attachment).
// Mutable per-message state (seen / trashed / folder / account) always comes
// fresh from the server ledger and is merged on top at read time.

const DB_NAME = 'll-mail';
const STORE = 'envelopes';
const VERSION = 1;

function openDb() {
    return new Promise((resolve, reject) => {
        if (typeof indexedDB === 'undefined') { reject(new Error('no-idb')); return; }
        const req = indexedDB.open(DB_NAME, VERSION);
        req.onupgradeneeded = () => {
            if (!req.result.objectStoreNames.contains(STORE)) req.result.createObjectStore(STORE, { keyPath: 'id' });
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

/** All cached envelopes as a Map(id -> envelope). Empty map on any failure. */
export async function loadEnvelopes() {
    try {
        const db = await openDb();
        return await new Promise((resolve) => {
            const out = new Map();
            const cur = db.transaction(STORE).objectStore(STORE).openCursor();
            cur.onsuccess = (e) => {
                const c = e.target.result;
                if (c) { out.set(c.value.id, c.value); c.continue(); } else resolve(out);
            };
            cur.onerror = () => resolve(out);
        });
    } catch {
        return new Map();
    }
}

/** Upsert many envelopes. Best-effort (silently no-ops if IndexedDB is absent). */
export async function putEnvelopes(items) {
    if (!items || !items.length) return;
    try {
        const db = await openDb();
        await new Promise((resolve) => {
            const tx = db.transaction(STORE, 'readwrite');
            const store = tx.objectStore(STORE);
            for (const it of items) store.put(it);
            tx.oncomplete = () => resolve();
            tx.onerror = () => resolve();
        });
    } catch {
        // ignore — cache is an optimisation, not a source of truth
    }
}
