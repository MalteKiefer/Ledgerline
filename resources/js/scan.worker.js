// Off-main-thread duplicate detection. The gallery's duplicate scan is an
// O(n^2) pairwise comparison (CLIP-cosine + pHash-Hamming) that stutters the UI
// on a large library. This worker runs that pure-number math off the main
// thread: normalised embedding vectors + the 64-bit pHash split into two 32-bit
// halves + per-item video flags go in; the duplicate groups (as index arrays)
// come out. No crypto and no DOM — the caller decrypts and maps back to photos.

// Population count of a 32-bit integer (SWAR). Operating on two 32-bit halves is
// both correct (no sign issues) and far faster than a BigInt loop.
function popcount32(x) {
    x = x - ((x >>> 1) & 0x55555555);
    x = (x & 0x33333333) + ((x >>> 2) & 0x33333333);
    x = (x + (x >>> 4)) & 0x0f0f0f0f;
    return (x * 0x01010101) >>> 24;
}

function dot(a, b) {
    let d = 0;
    const n = Math.min(a.length, b.length);
    for (let k = 0; k < n; k++) d += a[k] * b[k];
    return d;
}

// Union-find + pairwise scan; returns groups of >1 as arrays of item indices.
function computeGroups({ emb, phHi, phLo, phNull, vid, N }) {
    const parent = new Array(N);
    for (let i = 0; i < N; i++) parent[i] = i;
    const find = (i) => { while (parent[i] !== i) { parent[i] = parent[parent[i]]; i = parent[i]; } return i; };
    const union = (i, j) => { const a = find(i), b = find(j); if (a !== b) parent[a] = b; };

    for (let i = 0; i < N; i++) {
        for (let j = i + 1; j < N; j++) {
            const hd = (! phNull[i] && ! phNull[j])
                ? popcount32((phHi[i] ^ phHi[j]) >>> 0) + popcount32((phLo[i] ^ phLo[j]) >>> 0)
                : 64;
            let dup;
            if (vid[i] || vid[j]) {
                // A video's CLIP vector is only its poster frame, so scene-similar
                // clips score high — require the difference-hash to nearly match.
                dup = hd <= 4;
            } else {
                // Stills: high cosine OR a near-identical difference-hash.
                dup = (emb[i] && emb[j] && dot(emb[i], emb[j]) >= 0.97) || hd <= 3;
            }
            if (dup) union(i, j);
        }
        if ((i & 63) === 0) self.postMessage({ progress: i, total: N });
    }

    const groups = new Map();
    for (let i = 0; i < N; i++) { const r = find(i); if (! groups.has(r)) groups.set(r, []); groups.get(r).push(i); }
    return [...groups.values()].filter((g) => g.length > 1);
}

self.onmessage = (e) => {
    try {
        self.postMessage({ done: true, groups: computeGroups(e.data) });
    } catch (err) {
        self.postMessage({ error: true });
    }
};
