/**
 * Single-flight uPlot loader — lazy chunk, never in the startup bundle.
 *
 * loadUplot() returns a Promise that resolves to the uPlot constructor.
 * Repeated calls return the same promise; the CSS is injected only once.
 */

let _p;

export function loadUplot() {
    if (!_p) {
        _p = import('uplot').then(async (m) => {
            await import('uplot/dist/uPlot.min.css');
            return m.default;
        });
    }
    return _p;
}
