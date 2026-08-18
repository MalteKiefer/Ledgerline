import hljs from 'highlight.js';

// A file extension → highlight.js language alias, for content whose language
// is known from its NAME (a raw file preview) rather than an explicit fence
// hint (a Markdown code block — see lib/markdown.ts, which has its own,
// simpler fence-language lookup and does not need this map).
const EXT_LANG: Record<string, string> = {
  js: 'javascript', mjs: 'javascript', cjs: 'javascript', jsx: 'javascript',
  ts: 'typescript', tsx: 'typescript',
  py: 'python', rb: 'ruby', php: 'php', go: 'go', rs: 'rust',
  java: 'java', kt: 'kotlin', swift: 'swift', cs: 'csharp',
  c: 'c', h: 'c', cpp: 'cpp', cc: 'cpp', hpp: 'cpp', hh: 'cpp',
  css: 'css', scss: 'scss', less: 'less',
  html: 'xml', htm: 'xml', xml: 'xml', vue: 'xml', svg: 'xml',
  sh: 'bash', bash: 'bash', zsh: 'bash',
  sql: 'sql', json: 'json', yml: 'yaml', yaml: 'yaml',
  toml: 'ini', ini: 'ini', conf: 'ini',
  md: 'markdown', markdown: 'markdown',
  diff: 'diff', patch: 'diff',
};

/** highlight.js's language alias for a filename, or null if unrecognised (falls back to auto-detection). */
export function languageForFilename(name: string): string | null {
  const base = name.toLowerCase();
  if (base === 'dockerfile') return 'dockerfile';
  if (base === 'makefile') return 'makefile';
  const dot = base.lastIndexOf('.');
  const lang = dot >= 0 ? EXT_LANG[base.slice(dot + 1)] : undefined;

  return lang && hljs.getLanguage(lang) ? lang : null;
}

/**
 * Syntax-highlighted HTML for a raw code/text string, via the file's own
 * name (fence-language when recognised, else auto-detection). highlight.js
 * escapes the source text itself before wrapping it in token spans — its
 * output is safe to render via v-html without a separate sanitize pass, the
 * same trust already relied on for Markdown code fences in lib/markdown.ts.
 */
export function highlightCode(code: string, filename: string): string {
  const language = languageForFilename(filename);

  return language ? hljs.highlight(code, { language }).value : hljs.highlightAuto(code).value;
}
