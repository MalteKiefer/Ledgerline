// Filename/MIME → category mapping and per-category glyphs, shared by the files
// list. Extension is more reliable than the browser-supplied MIME (often empty
// or application/octet-stream), so it is checked first.

const EXT_CATEGORY = {
    // Images
    jpg: 'IMAGE', jpeg: 'IMAGE', png: 'IMAGE', gif: 'IMAGE', webp: 'IMAGE', bmp: 'IMAGE',
    tif: 'IMAGE', tiff: 'IMAGE', ico: 'IMAGE', heic: 'IMAGE', heif: 'IMAGE', avif: 'IMAGE', jfif: 'IMAGE',
    svg: 'VECTOR', ai: 'VECTOR', eps: 'VECTOR', psd: 'IMAGE', xcf: 'IMAGE', raw: 'IMAGE', cr2: 'IMAGE', nef: 'IMAGE', dng: 'IMAGE',
    // Video
    mp4: 'VIDEO', m4v: 'VIDEO', mov: 'VIDEO', webm: 'VIDEO', mkv: 'VIDEO', avi: 'VIDEO', wmv: 'VIDEO',
    flv: 'VIDEO', mpeg: 'VIDEO', mpg: 'VIDEO', '3gp': 'VIDEO', ogv: 'VIDEO', ts: 'VIDEO',
    // Audio
    mp3: 'AUDIO', wav: 'AUDIO', flac: 'AUDIO', aac: 'AUDIO', ogg: 'AUDIO', oga: 'AUDIO', m4a: 'AUDIO',
    wma: 'AUDIO', opus: 'AUDIO', aiff: 'AUDIO', mid: 'AUDIO', midi: 'AUDIO',
    // Documents
    pdf: 'PDF',
    doc: 'DOCUMENT', docx: 'DOCUMENT', odt: 'DOCUMENT', rtf: 'DOCUMENT', pages: 'DOCUMENT', epub: 'EBOOK', mobi: 'EBOOK', azw3: 'EBOOK',
    // Spreadsheets
    xls: 'SPREADSHEET', xlsx: 'SPREADSHEET', ods: 'SPREADSHEET', csv: 'SPREADSHEET', tsv: 'SPREADSHEET', numbers: 'SPREADSHEET',
    // Presentations
    ppt: 'PRESENTATION', pptx: 'PRESENTATION', odp: 'PRESENTATION', key: 'PRESENTATION',
    // Archives
    zip: 'ARCHIVE', tar: 'ARCHIVE', gz: 'ARCHIVE', tgz: 'ARCHIVE', bz2: 'ARCHIVE', xz: 'ARCHIVE',
    '7z': 'ARCHIVE', rar: 'ARCHIVE', zst: 'ARCHIVE', lz: 'ARCHIVE', cab: 'ARCHIVE', iso: 'DISK', dmg: 'DISK',
    // Code
    js: 'CODE', mjs: 'CODE', ts: 'CODE', jsx: 'CODE', tsx: 'CODE', vue: 'CODE', php: 'CODE', py: 'CODE',
    rb: 'CODE', go: 'CODE', rs: 'CODE', java: 'CODE', kt: 'CODE', c: 'CODE', h: 'CODE', cpp: 'CODE', cc: 'CODE',
    cs: 'CODE', swift: 'CODE', sh: 'CODE', bash: 'CODE', zsh: 'CODE', ps1: 'CODE', sql: 'CODE', html: 'CODE',
    htm: 'CODE', css: 'CODE', scss: 'CODE', less: 'CODE', json: 'CODE', xml: 'CODE', yaml: 'CODE', yml: 'CODE',
    toml: 'CODE', ini: 'CODE', env: 'CODE', lua: 'CODE', pl: 'CODE', r: 'CODE', dart: 'CODE',
    // Plain text
    txt: 'TEXT', md: 'TEXT', markdown: 'TEXT', log: 'TEXT', text: 'TEXT', rst: 'TEXT',
    // Fonts
    ttf: 'FONT', otf: 'FONT', woff: 'FONT', woff2: 'FONT', eot: 'FONT',
};

function extOf(name) {
    const i = (name || '').lastIndexOf('.');
    return i > 0 ? name.slice(i + 1).toLowerCase() : '';
}

// Category from a filename + MIME. Extension wins; MIME is the fallback.
// Client-side counterpart of PHP App\Enums\FileType::fromMime() — keep the two
// category sets in sync (this one is richer: it also uses the extension).
export function fileCategory(name, mime) {
    const byExt = EXT_CATEGORY[extOf(name)];
    if (byExt) return byExt;
    mime = (mime || '').toLowerCase();
    if (mime.startsWith('image/')) return mime.includes('svg') ? 'VECTOR' : 'IMAGE';
    if (mime.startsWith('video/')) return 'VIDEO';
    if (mime.startsWith('audio/')) return 'AUDIO';
    if (mime.startsWith('text/')) return 'TEXT';
    if (mime === 'application/pdf') return 'PDF';
    if (/(epub|mobipocket)/.test(mime)) return 'EBOOK';
    if (/(iso9660|diskimage|apple-disk)/.test(mime)) return 'DISK';
    if (/(zip|tar|gzip|compressed|7z|rar|zstd)/.test(mime)) return 'ARCHIVE';
    if (/(word|opendocument.text|rtf)/.test(mime)) return 'DOCUMENT';
    if (/(excel|spreadsheet|csv)/.test(mime)) return 'SPREADSHEET';
    if (/(powerpoint|presentation)/.test(mime)) return 'PRESENTATION';
    if (/(json|xml|javascript|x-sh|x-php|x-python)/.test(mime)) return 'CODE';
    if (mime.startsWith('font/')) return 'FONT';
    return 'OTHER';
}

// Small monochrome heroicon-style glyph per category, for the file list.
export const CATEGORY_ICON = {
    IMAGE: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
    VECTOR: 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42',
    VIDEO: 'M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z',
    AUDIO: 'M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163V4.883a.75.75 0 00-.943-.724L9.75 6.75m0 0v9.375a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66A2.25 2.25 0 009 12.375V4.5',
    PDF: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    DOCUMENT: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    SPREADSHEET: 'M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m0 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m0 0h7.5m0-9v9m0-9c0-.621.504-1.125 1.125-1.125h7.5c.621 0 1.125.504 1.125 1.125m0 0v1.5c0 .621-.504 1.125-1.125 1.125m0 0h-7.5',
    PRESENTATION: 'M3.75 3v11.25A2.25 2.25 0 006 16.5h12a2.25 2.25 0 002.25-2.25V3m-16.5 0h16.5m-16.5 0h-1.5m18 0h1.5m-16.5 16.5l3-3.75m9 3.75l-3-3.75m-6 0h6m-6 0l-.75.938M15 16.5l.75.938',
    ARCHIVE: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
    DISK: 'M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008V8.25zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008V8.25z',
    CODE: 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5',
    TEXT: 'M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h4.5M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z',
    FONT: 'M3 8.25V6a1.5 1.5 0 011.5-1.5h15A1.5 1.5 0 0121 6v2.25M3.75 6h16.5M9 20.25h6M12 4.5v15.75',
    EBOOK: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
    OTHER: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
};

export function formatBytes(n) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = Number(n) || 0;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
    const num = i === 0 ? String(Math.round(value)) : String(Math.round(value * 100) / 100);
    return `${num} ${units[i]}`;
}

// Per-category chip tint — verbatim from the iOS FileTypeStyle.swift palette so
// web + iOS render identical colours. Folders use FOLDER_TINT.
export const CATEGORY_TINT = {
    PDF: '#e5544b', DOCUMENT: '#3b9fd6', SPREADSHEET: '#59ad6b', IMAGE: '#9e70fa',
    VECTOR: '#8b5cf6', VIDEO: '#e5679e', ARCHIVE: '#d9a441', AUDIO: '#3fae9f',
    EBOOK: '#e2915a', PRESENTATION: '#e07a4f', FONT: '#b07dd6', TEXT: '#64748b',
    CODE: '#6b7280', DISK: '#6b7280', OTHER: '#6b7280',
};

export const FOLDER_TINT = '#3b9fd6';

// Extension → specific label token (mirror of the iOS extLabel map). Absent →
// fall back to the category token. Token resolves to lang key `filetype.<token>`.
const EXT_LABEL = {
    pdf: 'pdf',
    doc: 'word', docx: 'word', odt: 'odt', rtf: 'rtf', pages: 'pages',
    txt: 'text', text: 'text', log: 'text', rst: 'text',
    md: 'markdown', markdown: 'markdown',
    xls: 'excel', xlsx: 'excel', ods: 'ods', csv: 'csv', tsv: 'csv', numbers: 'numbers',
    ppt: 'powerpoint', pptx: 'powerpoint', odp: 'odp', key: 'keynote',
    epub: 'epub', mobi: 'mobi', azw3: 'mobi',
    jpg: 'jpeg', jpeg: 'jpeg', png: 'png', gif: 'gif', webp: 'webp',
    heic: 'heic', heif: 'heic', bmp: 'bmp', tif: 'tiff', tiff: 'tiff', ico: 'ico', svg: 'svg',
    mp4: 'mp4', m4v: 'mp4', mov: 'quicktime', mkv: 'matroska', avi: 'avi', webm: 'webm',
    mp3: 'mp3', wav: 'wav', flac: 'flac', aac: 'aac', m4a: 'm4a', ogg: 'ogg',
    zip: 'zip', rar: 'rar', '7z': 'sevenzip', tar: 'tar', gz: 'gzip', tgz: 'gzip',
    iso: 'iso', dmg: 'dmg',
    js: 'javascript', mjs: 'javascript', ts: 'typescript', tsx: 'typescript',
    py: 'python', php: 'php', html: 'html', htm: 'html', css: 'css',
    json: 'json', xml: 'xml', swift: 'swift', java: 'java',
    sh: 'shell', bash: 'shell', zsh: 'shell', sql: 'sql', yaml: 'yaml', yml: 'yaml',
    ttf: 'font', otf: 'font', woff: 'webfont', woff2: 'webfont',
};

export { EXT_LABEL };

// The chip tint for a file (folders handled by the caller with FOLDER_TINT).
export function categoryTint(name, mime) {
    return CATEGORY_TINT[fileCategory(name, mime)] || CATEGORY_TINT.OTHER;
}

// Lang key for the human-readable type label. Specific extension label wins;
// otherwise the category token (lowercased).
export function fileTypeLabel(name, mime) {
    const byExt = EXT_LABEL[extOf(name)];
    if (byExt) return `filetype.${byExt}`;
    return `filetype.${fileCategory(name, mime).toLowerCase()}`;
}
