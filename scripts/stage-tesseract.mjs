// Stage the tesseract.js assets into public/tesseract so OCR runs fully
// self-hosted (same-origin worker + WASM core + language data). Our CSP is
// worker-src/connect-src 'self' and we ship no external CDN, so nothing here may
// be fetched from a third party at runtime. Run at build time (see Dockerfile).
import { mkdir, copyFile, readdir, writeFile } from 'node:fs/promises';

const OUT = 'public/tesseract';
const CORE_SRC = 'node_modules/tesseract.js-core';
const LANGS = ['eng', 'deu'];
// tessdata_fast: small, fast, good enough for document/photo OCR.
const LANG_BASE = 'https://github.com/tesseract-ocr/tessdata_fast/raw/main';

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
for (const lang of LANGS) {
  const res = await fetch(`${LANG_BASE}/${lang}.traineddata`);
  if (!res.ok) throw new Error(`tessdata ${lang} download failed: ${res.status}`);
  await writeFile(`${OUT}/lang/${lang}.traineddata`, Buffer.from(await res.arrayBuffer()));
}

console.log('tesseract assets staged into', OUT);
