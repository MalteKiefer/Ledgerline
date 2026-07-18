// Cosine helpers for CLIP/embedding similarity (embeddings are pre-normalised,
// so cosine == dot product).

export function normVec(v) {
    let s = 0; for (let i = 0; i < v.length; i++) s += v[i] * v[i];
    const inv = s > 0 ? 1 / Math.sqrt(s) : 0;
    const out = new Float32Array(v.length);
    for (let i = 0; i < v.length; i++) out[i] = v[i] * inv;
    return out;
}

export function dotVec(a, b) {
    if (! a || ! b || a.length !== b.length) return 0;
    let d = 0; for (let i = 0; i < a.length; i++) d += a[i] * b[i];
    return d;
}
