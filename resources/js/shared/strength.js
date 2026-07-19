// Lazy-loaded zxcvbn-ts strength estimation. The dictionaries are heavy, so the
// packages are imported on first use (kept out of the startup bundle), mirroring
// the leaflet/codemirror lazy-loaders. Returns a 0–4 score + a human crack-time.
// Note: @zxcvbn-ts v4 uses ZxcvbnFactory (no global zxcvbnOptions/zxcvbn).

let _factory = null;

async function load() {
    if (_factory) return _factory;
    const [{ ZxcvbnFactory }, common, en, de] = await Promise.all([
        import('@zxcvbn-ts/core'),
        import('@zxcvbn-ts/language-common'),
        import('@zxcvbn-ts/language-en'),
        import('@zxcvbn-ts/language-de'),
    ]);
    const commonData = common.default ?? common;
    const enData = en.default ?? en;
    const deData = de.default ?? de;
    _factory = new ZxcvbnFactory({
        dictionary: {
            ...commonData.dictionary,
            ...enData.dictionary,
            ...deData.dictionary,
        },
        graphs: commonData.adjacencyGraphs,
        translations: enData.translations,
    });
    return _factory;
}

export async function estimateStrength(pw) {
    if (! pw) return { score: 0, crackTimeDisplay: '' };
    const factory = await load();
    const res = factory.check(pw);
    return {
        score: res.score,
        crackTimeDisplay: String(res.crackTimes?.offlineSlowHashingXPerSecond?.display ?? ''),
    };
}
