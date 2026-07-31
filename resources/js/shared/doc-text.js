// Client-side document text extraction (runs entirely in the browser). Shared by the
// files module (search index) and the finance receipts (OCR + tag suggestion). PDFs use
// their embedded text layer when present, otherwise the pages are rendered and OCR'd;
// images are OCR'd; anything else is decoded as text. pdf.js is lazy-loaded/code-split.

import { ocrImage } from './ocr';

const IMG_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'avif'];

/** PDF text via the embedded text layer (fast, no OCR). */
export async function extractPdfText(bytes, maxPages = 300) {
    try {
        const pdfjs = await import('pdfjs-dist');
        pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
        const doc = await pdfjs.getDocument({ data: bytes.slice(0), isEvalSupported: false }).promise;
        let out = '';
        const pages = Math.min(doc.numPages, maxPages);
        for (let i = 1; i <= pages && out.length < 2_000_000; i++) {
            const page = await doc.getPage(i);
            const content = await page.getTextContent();
            // Preserve line structure via each item's y-position (transform[5]): a jump
            // starts a new line, same row joins with a space. Without this the whole page
            // collapses to one "line" and the line-based recognisers (total/merchant) break
            // — e.g. the total is read as the last number on the page, not the labelled one.
            let lastY = null;
            for (const it of content.items) {
                const y = it.transform ? it.transform[5] : null;
                if (lastY !== null && y !== null && Math.abs(y - lastY) > 3) out += '\n';
                else if (out && ! out.endsWith('\n')) out += ' ';
                out += it.str || '';
                lastY = y;
            }
            out += '\n';
        }
        try { await doc.destroy(); } catch (e) { /* ignore */ }
        return out;
    } catch (e) { return null; }
}

/** OCR a scanned PDF: render each page (pdf.js) and recognise it. Capped + slow. */
export async function ocrPdf(bytes, maxPages = 30) {
    try {
        const pdfjs = await import('pdfjs-dist');
        pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
        const doc = await pdfjs.getDocument({ data: bytes.slice(0), isEvalSupported: false }).promise;
        let out = '';
        const pages = Math.min(doc.numPages, maxPages);
        for (let i = 1; i <= pages && out.length < 2_000_000; i++) {
            const page = await doc.getPage(i);
            const vp = page.getViewport({ scale: 2 });
            const canvas = document.createElement('canvas');
            canvas.width = vp.width; canvas.height = vp.height;
            await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
            out += await ocrImage(canvas) + '\n';
            canvas.width = canvas.height = 0; // free
        }
        try { await doc.destroy(); } catch (e) { /* ignore */ }
        return out;
    } catch (e) { return ''; }
}

/**
 * Extract searchable text from a document's bytes. PDF → text layer, else OCR; image →
 * OCR (only kept when it has enough real characters, so a plain photo of a wall doesn't
 * produce noise); other → UTF-8 decode (HTML stripped).
 */
export async function extractDocText(bytes, mime, name) {
    const ext = ((name || '').split('.').pop() || '').toLowerCase();
    if (mime === 'application/pdf' || ext === 'pdf') {
        const t = await extractPdfText(bytes);
        if (t && t.replace(/\s+/g, '').length > 8) return t;
        return await ocrPdf(bytes);
    }
    if (/^image\//.test(mime || '') || IMG_EXT.includes(ext)) {
        const t = await ocrImage(new Blob([bytes], { type: mime || 'image/png' }));
        return (t && t.replace(/\s+/g, '').length >= 12) ? t : '';
    }
    try {
        let t = new TextDecoder('utf-8', { fatal: false }).decode(bytes);
        if (ext === 'html' || ext === 'htm' || /html/.test(mime || '')) t = t.replace(/<[^>]+>/g, ' ');
        return t;
    } catch (e) { return null; }
}
