// Memoised lazy loaders for heavy, rarely-used libraries so they never bloat
// the main bundle (users who never open a map / edit code don't download them).

let leafletModule = null;
export async function loadLeaflet() {
    if (! leafletModule) {
        const L = (await import('leaflet')).default;
        await import('leaflet.markercluster'); // augments L with markerClusterGroup
        await Promise.all([
            import('leaflet/dist/leaflet.css'),
            import('leaflet.markercluster/dist/MarkerCluster.css'),
            import('leaflet.markercluster/dist/MarkerCluster.Default.css'),
        ]);
        const [icon, icon2x, shadow] = await Promise.all([
            import('leaflet/dist/images/marker-icon.png'),
            import('leaflet/dist/images/marker-icon-2x.png'),
            import('leaflet/dist/images/marker-shadow.png'),
        ]);
        // Leaflet's default marker resolves its images by a relative URL that
        // 404s under a bundler; point it at the bundled assets so pins render.
        L.Icon.Default.mergeOptions({
            iconUrl: icon.default,
            iconRetinaUrl: icon2x.default,
            shadowUrl: shadow.default,
        });
        leafletModule = L;
    }
    return leafletModule;
}

// MapLibre GL is heavy (~800 KiB). Lazy-load it (and its CSS) so it never lands
// in the startup bundle — only the Explore view pulls it, on open.
let maplibreModule = null;
export async function mapLibre() {
    if (! maplibreModule) {
        const m = await import('maplibre-gl');
        await import('maplibre-gl/dist/maplibre-gl.css');
        maplibreModule = m.default ?? m;
    }
    return maplibreModule;
}

// Exported as a live binding: app.js reads cmModule after loadCodeMirror() has
// populated it (ESM keeps the imported reference in sync).
export let cmModule = null;
export async function loadCodeMirror() {
    if (! cmModule) {
        const [core, state, language, data] = await Promise.all([
            import('codemirror'),
            import('@codemirror/state'),
            import('@codemirror/language'),
            import('@codemirror/language-data'),
        ]);
        cmModule = {
            EditorView: core.EditorView,
            basicSetup: core.basicSetup,
            EditorState: state.EditorState,
            Compartment: state.Compartment,
            LanguageDescription: language.LanguageDescription,
            languages: data.languages,
        };
    }
    return cmModule;
}
