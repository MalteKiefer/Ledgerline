// Content-blob IO shared by the gallery and files views: bounded concurrent
// fetch with 429 backoff, main-thread + worker-pool decrypt, a thumbnail lane
// pool, and a rate-limit-aware delete queue. Every content-blob load funnels
// through here so total in-flight requests stay bounded app-wide.

let _blobActive = 0;
const _blobWaiters = [];
const BLOB_CONCURRENCY = 6;
async function fetchBlobBuffer(url) {
    if (_blobActive >= BLOB_CONCURRENCY) await new Promise((r) => _blobWaiters.push(r));
    _blobActive++;
    try {
        for (let tries = 0; ; tries++) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) return await res.arrayBuffer();
            if (res.status === 429 && tries < 6) {
                const ra = parseInt(res.headers.get('Retry-After') || '', 10);
                const wait = Number.isFinite(ra) && ra > 0 ? ra * 1000 : Math.min(8000, 400 * 2 ** tries) + Math.random() * 300;
                await new Promise((r) => setTimeout(r, wait));
                continue;
            }
            throw new Error('fetch failed');
        }
    } finally {
        _blobActive--;
        const next = _blobWaiters.shift();
        if (next) next();
    }
}

export async function fetchDecrypt(rawBase, ref, key) {
    return window.Vault.decryptFile(await fetchBlobBuffer(`${rawBase}/${ref}`), key);
}

// Bounded lane pool for thumbnail loading — a fast scroll intersects dozens of
// tiles at once; cap the parallel fetch+decrypts and queue the rest.
let _thumbActive = 0;
const _thumbWaiters = [];
const THUMB_LANES = 8;
export function thumbLane(task) {
    if (_thumbActive >= THUMB_LANES) {
        return new Promise((resolve) => _thumbWaiters.push(resolve)).then(() => thumbLane(task));
    }
    _thumbActive++;
    return Promise.resolve().then(task).finally(() => {
        _thumbActive--;
        const next = _thumbWaiters.shift();
        if (next) next();
    });
}

// Small worker pool that runs the CPU-heavy secretstream decrypt off the main
// thread. The main thread still fetches and unwraps the per-blob key (cheap);
// only the pull runs in a worker, so the vault key never leaves the main thread.
const _decryptPool = (() => {
    let workers = null;
    let dead = false; // a worker errored (e.g. CSP blocks wasm) → use fallback
    let rr = 0;
    let seq = 0;
    const pending = new Map();

    function killAll(reason) {
        dead = true;
        for (const [, job] of pending) job.reject(new Error(reason));
        pending.clear();
    }

    function init() {
        if (workers || dead) return workers;
        try {
            const n = Math.max(1, Math.min(3, (navigator.hardwareConcurrency || 4) - 1));
            workers = Array.from({ length: n }, () => {
                const w = new Worker(new URL('../decrypt.worker.js', import.meta.url), { type: 'module' });
                w.onmessage = (e) => {
                    const job = pending.get(e.data.id);
                    if (! job) return;
                    pending.delete(e.data.id);
                    clearTimeout(job.timer);
                    if (e.data.ok) job.resolve(new Uint8Array(e.data.buffer));
                    else job.reject(new Error(e.data.error || 'decrypt failed'));
                };
                w.onerror = () => killAll('worker error');
                return w;
            });
        } catch (e) { workers = null; dead = true; }

        return workers;
    }

    return {
        run(buffer, fk) {
            const pool = init();
            if (! pool || ! pool.length) return Promise.reject(new Error('no workers'));
            const id = ++seq;
            const w = pool[rr++ % pool.length];

            return new Promise((resolve, reject) => {
                const timer = setTimeout(() => {
                    if (pending.delete(id)) reject(new Error('decrypt timeout'));
                }, 15000);
                pending.set(id, { resolve, reject, timer });
                w.postMessage({ id, buffer, fk });
            });
        },
    };
})();

// Fetch a blob and decrypt it in the worker pool, falling back to a main-thread
// decrypt if the pool is unavailable or the vault can't unwrap the key.
export async function fetchDecryptWorker(rawBase, ref, key) {
    const buffer = await fetchBlobBuffer(`${rawBase}/${ref}`);
    try {
        const fk = window.Vault.unwrapContentKey(key);

        return await _decryptPool.run(buffer, fk);
    } catch (e) {
        return window.Vault.decryptFile(buffer, key);
    }
}

// Bounded, rate-limit-aware content-blob deleter shared by the gallery + files
// trash paths. DELETE is idempotent, so a retried/duplicated call is harmless.
const _blobDelQueue = [];
let _blobDelActive = 0;
const BLOB_DEL_LANES = 4;
const BLOB_DEL_MAX_TRIES = 10;
export function queueBlobDelete(url, token) {
    return new Promise((resolve) => {
        _blobDelQueue.push({ url, token, tries: 0, resolve });
        _pumpBlobDeletes();
    });
}
function _pumpBlobDeletes() {
    while (_blobDelActive < BLOB_DEL_LANES && _blobDelQueue.length) {
        _blobDelActive++;
        _runBlobDelete(_blobDelQueue.shift());
    }
}
async function _runBlobDelete(job) {
    try {
        const res = await fetch(job.url, { method: 'DELETE', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': job.token } });
        if (res.status === 429 && job.tries < BLOB_DEL_MAX_TRIES) {
            const ra = parseInt(res.headers.get('Retry-After') || '', 10);
            const wait = Number.isFinite(ra) && ra > 0 ? ra * 1000 : Math.min(500 * 2 ** job.tries, 15000);
            job.tries++;
            _blobDelActive--;
            setTimeout(() => { _blobDelQueue.unshift(job); _pumpBlobDeletes(); }, wait);
            return;
        }
    } catch (e) { /* network error — best effort */ }
    _blobDelActive--;
    job.resolve();
    _pumpBlobDeletes();
}
