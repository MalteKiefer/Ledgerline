// Stage the tesseract.js assets into public/tesseract so OCR runs fully
// self-hosted (same-origin worker + WASM core + language data). Our CSP is
// worker-src/connect-src 'self' and we ship no external CDN, so nothing here may
// be fetched from a third party at runtime. Run at build time (see Dockerfile).
import { mkdir, copyFile, readdir, writeFile } from 'node:fs/promises';

const OUT = 'public/tesseract';
const CORE_SRC = 'node_modules/tesseract.js-core';
const LANGS = ['eng', 'deu'];
// tessdata_fast: small, fast, good enough for document/photo OCR. Several mirrors
// so a flaky/rate-limited source (github.com/raw returns intermittent 503) doesn't
// break the build; jsDelivr + raw.githubusercontent are more reliable than the
// github.com redirect layer.
const LANG_MIRRORS = [
  (lang) => `https://cdn.jsdelivr.net/gh/tesseract-ocr/tessdata_fast@main/${lang}.traineddata`,
  (lang) => `https://raw.githubusercontent.com/tesseract-ocr/tessdata_fast/main/${lang}.traineddata`,
  (lang) => `https://github.com/tesseract-ocr/tessdata_fast/raw/main/${lang}.traineddata`,
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function fetchTraineddata(lang) {
  let lastErr = '';
  for (let attempt = 0; attempt < 3; attempt++) {
    for (const mirror of LANG_MIRRORS) {
      const url = mirror(lang);
      try {
        const res = await fetch(url);
        if (res.ok) {
          const buf = Buffer.from(await res.arrayBuffer());
          if (buf.length > 100000) return buf; // sanity: traineddata is MBs, not an error page
          lastErr = `${url} → ${buf.length} bytes (too small)`;
        } else {
          lastErr = `${url} → ${res.status}`;
        }
      } catch (e) {
        lastErr = `${url} → ${e.message}`;
      }
    }
    await sleep(1500 * (attempt + 1));
  }
  throw new Error(`tessdata ${lang} download failed after retries: ${lastErr}`);
}

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
  await writeFile(`${OUT}/lang/${lang}.traineddata`, await fetchTraineddata(lang));
}

console.log('tesseract assets staged into', OUT);
