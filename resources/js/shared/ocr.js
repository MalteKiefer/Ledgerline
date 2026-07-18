// Lazy, singleton OCR worker (tesseract.js). All assets are self-hosted under
// /tesseract (worker + WASM core + eng/deu data) so nothing is fetched from a
// CDN — the whole OCR runs in the browser, keeping the ZK boundary intact.
let _ocrWorker = null, _ocrInit = null;

export async function ocrWorker() {
    if (_ocrWorker) return _ocrWorker;
    if (! _ocrInit) {
        _ocrInit = (async () => {
            const { createWorker } = await import('tesseract.js');
            _ocrWorker = await createWorker(['eng', 'deu'], 1, {
                workerPath: '/tesseract/worker.min.js',
                corePath: '/tesseract/core',
                langPath: '/tesseract/lang',
                workerBlobURL: false, // same-origin worker (CSP worker-src 'self')
                gzip: false,          // raw .traineddata (not gzipped)
            });
            return _ocrWorker;
        })();
    }
    return _ocrInit;
}

// OCR a Blob / canvas / ImageData → recognised text ('' on any failure).
export async function ocrImage(input) {
    try {
        const w = await ocrWorker();
        const { data } = await w.recognize(input);
        return (data && data.text) ? data.text : '';
    } catch (e) { return ''; }
}
