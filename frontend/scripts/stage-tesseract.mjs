// Stage the tesseract.js assets into public/tesseract so OCR runs fully
// self-hosted (same-origin worker + WASM core + language data). Our CSP is
// worker-src/connect-src 'self' and we ship no external CDN, so nothing here may
// be fetched from a third party at runtime. Run at build time (see Dockerfile).
import { mkdir, copyFile, readdir, writeFile } from 'node:fs/promises';

const OUT = 'dist/tesseract';
const CORE_SRC = 'node_modules/tesseract.js-core';
const LANGS = ['eng', 'deu'];
// tessdata_fast: small, fast, good enough for document/photo OCR.
// Fetched from raw.githubusercontent.com directly (the canonical raw-content
// CDN) rather than the github.com/…/raw/… redirect shortcut, which started
// 404ing for this file regardless of retry (2026-08-17) — same repo/file,
// one less redirect hop, no behaviour change once the underlying host works.
const LANG_BASE = 'https://raw.githubusercontent.com/tesseract-ocr/tessdata_fast/main';

await mkdir(`${OUT}/core`, { recursive: true });
await mkdir(`${OUT}/lang`, { recursive: true });

// Worker script (same-origin so worker-src 'self' allows it; we set
// workerBlobURL:false in the app so no blob: worker is created).
await copyFile('node_modules/tesseract.js/dist/worker.min.js', `${OUT}/worker.min.js`);

// WASM core: only the LSTM variants (we run engine oem 1); tesseract still
// picks SIMD / relaxed-SIMD / plain among them at runtime. Dropping the legacy
// non-LSTM cores roughly halves the payload.
for (const f of await readdir(CORE_SRC)) {
  if (/\.(wasm|js)$/.test(f) && f.includes('lstm')) await copyFile(`${CORE_SRC}/${f}`, `${OUT}/core/${f}`);
}

// Language data (raw .traineddata; the app loads it with gzip:false).
// A couple of retries with backoff: this hits an external host at build
// time, and a transient 404/429/5xx there shouldn't fail the whole image
// build if a second attempt would succeed.
async function fetchWithRetry(url, attempts = 3) {
  for (let i = 1; i <= attempts; i += 1) {
    const res = await fetch(url);
    if (res.ok) return res;
    if (i === attempts) throw new Error(`download failed: ${res.status}`);
    await new Promise((r) => setTimeout(r, 2000 * i));
  }
  throw new Error('unreachable');
}

for (const lang of LANGS) {
  const res = await fetchWithRetry(`${LANG_BASE}/${lang}.traineddata`);
  await writeFile(`${OUT}/lang/${lang}.traineddata`, Buffer.from(await res.arrayBuffer()));
}

console.log('tesseract assets staged into', OUT);
