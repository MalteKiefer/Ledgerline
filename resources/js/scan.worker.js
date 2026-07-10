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

// Full cosine (normalises both operands) — face embeddings are not pre-normed.
function cosineFull(a, b) {
    let d = 0, na = 0, nb = 0;
    const n = Math.min(a.length, b.length);
    for (let i = 0; i < n; i++) { d += a[i] * b[i]; na += a[i] * a[i]; nb += b[i] * b[i]; }
    return (na && nb) ? d / (Math.sqrt(na) * Math.sqrt(nb)) : 0;
}

// Greedy single-link face clustering (buffalo_l embeddings; same-person pairs sit
// well above 0.5 cosine). Seeds may carry existing people so new faces merge into
// them. Returns clusters of 2+ members with a running-mean centroid; new clusters
// get id=null (the main thread assigns a store id). No DOM/crypto.
function clusterFaces({ faces, seeds, prev, incremental }) {
    const clusters = [];
    const placed = new Set();
    for (const s of seeds) {
        clusters.push({ id: s.id, name: s.name || '', hidden: ! ! s.hidden, centroid: s.centroid.slice(), count: s.members.length, members: s.members });
        for (const m of s.members) placed.add(m.photoId + ':' + m.idx);
    }
    let fi = 0;
    for (const face of faces) {
        if ((++fi & 127) === 0) self.postMessage({ progress: fi, total: faces.length });
        const key = face.meta.photoId + ':' + face.meta.idx;
        if (placed.has(key)) continue;
        placed.add(key);
        let best = null, bestSim = 0.5;
        for (const c of clusters) { const sim = cosineFull(face.emb, c.centroid); if (sim > bestSim) { bestSim = sim; best = c; } }
        if (best) {
            const n = best.count || best.members.length;
            for (let i = 0; i < best.centroid.length; i++) best.centroid[i] = (best.centroid[i] * n + face.emb[i]) / (n + 1);
            best.count = n + 1;
            best.members.push(face.meta);
        } else {
            clusters.push({ id: null, name: '', hidden: false, centroid: face.emb.slice(), count: 1, members: [face.meta] });
        }
    }
    return clusters.filter((c) => c.members.length >= 2)
        .sort((a, b) => b.members.length - a.members.length)
        .map((c) => {
            let name = c.name || '', hidden = ! ! c.hidden;
            if (! incremental) {
                // Carry a previous person's name/hidden onto the matching new cluster.
                let bestSim = 0.6, match = null;
                for (const pp of prev) { if (! pp.centroid) continue; const s = cosineFull(c.centroid, pp.centroid); if (s > bestSim) { bestSim = s; match = pp; } }
                if (match) { name = match.name || ''; hidden = ! ! match.hidden; }
            }
            return { id: c.id, name, hidden, centroid: c.centroid, faces: c.members };
        });
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
        if (e.data.type === 'faces') self.postMessage({ done: true, built: clusterFaces(e.data) });
        else self.postMessage({ done: true, groups: computeGroups(e.data) });
    } catch (err) {
        self.postMessage({ error: true });
    }
};
