// TS port of resources/js/shared/file-categories.js for the Vue SPA. The JS
// original stays until the legacy Alpine files view is deleted at cutover (P7);
// keep the two in sync until then.

type Category = 'IMAGE' | 'VECTOR' | 'VIDEO' | 'AUDIO' | 'PDF' | 'DOCUMENT' | 'SPREADSHEET'
  | 'PRESENTATION' | 'ARCHIVE' | 'DISK' | 'CODE' | 'TEXT' | 'FONT' | 'EBOOK' | 'OTHER';

const EXT_CATEGORY: Record<string, Category> = {
  jpg: 'IMAGE', jpeg: 'IMAGE', png: 'IMAGE', gif: 'IMAGE', webp: 'IMAGE', bmp: 'IMAGE', tif: 'IMAGE', tiff: 'IMAGE', ico: 'IMAGE', heic: 'IMAGE', heif: 'IMAGE', avif: 'IMAGE', jfif: 'IMAGE', psd: 'IMAGE', xcf: 'IMAGE', raw: 'IMAGE', cr2: 'IMAGE', nef: 'IMAGE', dng: 'IMAGE',
  svg: 'VECTOR', ai: 'VECTOR', eps: 'VECTOR',
  mp4: 'VIDEO', m4v: 'VIDEO', mov: 'VIDEO', webm: 'VIDEO', mkv: 'VIDEO', avi: 'VIDEO', wmv: 'VIDEO', flv: 'VIDEO', mpeg: 'VIDEO', mpg: 'VIDEO', '3gp': 'VIDEO', ogv: 'VIDEO', ts: 'VIDEO',
  mp3: 'AUDIO', wav: 'AUDIO', flac: 'AUDIO', aac: 'AUDIO', ogg: 'AUDIO', oga: 'AUDIO', m4a: 'AUDIO', wma: 'AUDIO', opus: 'AUDIO', aiff: 'AUDIO', mid: 'AUDIO', midi: 'AUDIO',
  pdf: 'PDF',
  doc: 'DOCUMENT', docx: 'DOCUMENT', odt: 'DOCUMENT', rtf: 'DOCUMENT', pages: 'DOCUMENT', epub: 'EBOOK', mobi: 'EBOOK', azw3: 'EBOOK',
  xls: 'SPREADSHEET', xlsx: 'SPREADSHEET', ods: 'SPREADSHEET', csv: 'SPREADSHEET', tsv: 'SPREADSHEET', numbers: 'SPREADSHEET',
  ppt: 'PRESENTATION', pptx: 'PRESENTATION', odp: 'PRESENTATION', key: 'PRESENTATION',
  zip: 'ARCHIVE', tar: 'ARCHIVE', gz: 'ARCHIVE', tgz: 'ARCHIVE', bz2: 'ARCHIVE', xz: 'ARCHIVE', '7z': 'ARCHIVE', rar: 'ARCHIVE', zst: 'ARCHIVE', lz: 'ARCHIVE', cab: 'ARCHIVE', iso: 'DISK', dmg: 'DISK',
  js: 'CODE', mjs: 'CODE', ts_: 'CODE', jsx: 'CODE', tsx: 'CODE', vue: 'CODE', php: 'CODE', py: 'CODE', rb: 'CODE', go: 'CODE', rs: 'CODE', java: 'CODE', kt: 'CODE', c: 'CODE', h: 'CODE', cpp: 'CODE', cc: 'CODE', cs: 'CODE', swift: 'CODE', sh: 'CODE', bash: 'CODE', zsh: 'CODE', ps1: 'CODE', sql: 'CODE', html: 'CODE', htm: 'CODE', css: 'CODE', scss: 'CODE', less: 'CODE', json: 'CODE', xml: 'CODE', yaml: 'CODE', yml: 'CODE', toml: 'CODE', ini: 'CODE', env: 'CODE', lua: 'CODE', pl: 'CODE', r: 'CODE', dart: 'CODE',
  txt: 'TEXT', md: 'TEXT', markdown: 'TEXT', log: 'TEXT', text: 'TEXT', rst: 'TEXT',
  ttf: 'FONT', otf: 'FONT', woff: 'FONT', woff2: 'FONT', eot: 'FONT',
};

function extOf(name: string): string {
  const i = (name || '').lastIndexOf('.');
  return i > 0 ? name.slice(i + 1).toLowerCase() : '';
}

export function fileCategory(name: string, mime: string): Category {
  const ext = extOf(name);
  const byExt = EXT_CATEGORY[ext] ?? (ext === 'ts' ? 'CODE' : undefined);
  if (byExt) return byExt;
  const m = (mime || '').toLowerCase();
  if (m.startsWith('image/')) return m.includes('svg') ? 'VECTOR' : 'IMAGE';
  if (m.startsWith('video/')) return 'VIDEO';
  if (m.startsWith('audio/')) return 'AUDIO';
  if (m.startsWith('text/')) return 'TEXT';
  if (m === 'application/pdf') return 'PDF';
  if (/(epub|mobipocket)/.test(m)) return 'EBOOK';
  if (/(iso9660|diskimage|apple-disk)/.test(m)) return 'DISK';
  if (/(zip|tar|gzip|compressed|7z|rar|zstd)/.test(m)) return 'ARCHIVE';
  if (/(word|opendocument.text|rtf)/.test(m)) return 'DOCUMENT';
  if (/(excel|spreadsheet|csv)/.test(m)) return 'SPREADSHEET';
  if (/(powerpoint|presentation)/.test(m)) return 'PRESENTATION';
  if (/(json|xml|javascript|x-sh|x-php|x-python)/.test(m)) return 'CODE';
  if (m.startsWith('font/')) return 'FONT';
  return 'OTHER';
}

const CATEGORY_MSYM: Record<Category, string> = {
  IMAGE: 'image', VECTOR: 'shapes', VIDEO: 'movie', AUDIO: 'music_note', PDF: 'picture_as_pdf',
  DOCUMENT: 'description', SPREADSHEET: 'table', PRESENTATION: 'slideshow', ARCHIVE: 'folder_zip',
  DISK: 'album', CODE: 'code', TEXT: 'article', FONT: 'font_download', EBOOK: 'menu_book', OTHER: 'draft',
};

const CATEGORY_TINT: Record<Category, string> = {
  PDF: '#e5544b', DOCUMENT: '#3b9fd6', SPREADSHEET: '#59ad6b', IMAGE: '#9e70fa', VECTOR: '#8b5cf6',
  VIDEO: '#e5679e', ARCHIVE: '#d9a441', AUDIO: '#3fae9f', EBOOK: '#e2915a', PRESENTATION: '#e07a4f',
  FONT: '#b07dd6', TEXT: '#64748b', CODE: '#6b7280', DISK: '#6b7280', OTHER: '#6b7280',
};

export const FOLDER_TINT = '#3b9fd6';

export function categoryMsym(name: string, mime: string): string {
  return CATEGORY_MSYM[fileCategory(name, mime)];
}
export function categoryTint(name: string, mime: string): string {
  return CATEGORY_TINT[fileCategory(name, mime)];
}
export function formatBytes(n: number): string {
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let v = Number(n) || 0;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
  return `${i === 0 ? Math.round(v) : Math.round(v * 100) / 100} ${units[i]}`;
}
export function isImage(name: string, mime: string): boolean {
  const cat = fileCategory(name, mime);
  return cat === 'IMAGE' || cat === 'VECTOR';
}
