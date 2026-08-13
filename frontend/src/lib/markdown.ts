import { Marked } from 'marked';
import { markedHighlight } from 'marked-highlight';
import DOMPurify from 'dompurify';
// Full highlight.js "common" build: ~190 languages registered out of the box
// (with their aliases: js/ts/html/yml/sh/py…). Loaded only on the notes route
// (this module is in the lazy Notes chunk).
import hljs from 'highlight.js';

// Render a note's Markdown body to sanitised HTML. The body is user-authored
// plaintext stored server-side and rendered only on the client; DOMPurify strips
// scripts / event handlers / dangerous URIs so a note can never inject script.
// Code fences are syntax-highlighted (highlight.js): the fence's language when
// registered, else auto-detection.
const marked = new Marked(
  markedHighlight({
    langPrefix: 'hljs language-',
    highlight(code: string, lang: string): string {
      const language = lang && hljs.getLanguage(lang) ? lang : '';
      return language
        ? hljs.highlight(code, { language }).value
        : hljs.highlightAuto(code).value;
    },
  }),
);
marked.setOptions({ breaks: true, gfm: true });

function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string
  ));
}

// Rewrite [[Title]] / [[Title|Alias]] into internal links BEFORE Markdown parsing.
// A resolver maps a title to a note id (null = no such note yet). Resolved links
// carry data-note-id (handled by the SPA, never a navigation URL); unresolved
// links render as a muted "missing" span so the user can create the note.
function rewriteWikilinks(md: string, resolve: (title: string) => number | null): string {
  return md.replace(/\[\[([^\]|\n]+)(?:\|([^\]\n]*))?\]\]/g, (_m, rawTitle: string, alias?: string) => {
    const title = rawTitle.trim();
    const label = escapeHtml((alias ?? rawTitle).trim() || title);
    const id = resolve(title);
    return id !== null
      ? `<a data-note-id="${id}" class="ll-wikilink">${label}</a>`
      : `<span class="ll-wikilink-missing" title="${escapeHtml(title)}">${label}</span>`;
  });
}

export function renderMarkdown(md: string, resolve?: (title: string) => number | null): string {
  const pre = resolve ? rewriteWikilinks(md ?? '', resolve) : (md ?? '');
  const raw = marked.parse(pre, { async: false }) as string;
  return DOMPurify.sanitize(raw, {
    ALLOWED_TAGS: [
      'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'br', 'hr',
      'strong', 'em', 'del', 'blockquote', 'code', 'pre',
      'ul', 'ol', 'li', 'a', 'img', 'span', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
      'input', // task-list checkboxes (GFM)
    ],
    ALLOWED_ATTR: ['href', 'title', 'src', 'alt', 'type', 'checked', 'disabled', 'class', 'data-note-id'],
    // http(s)/mailto/anchor + same-origin relative paths (attachment images live
    // at /api/v1/notes/…/raw) + data:image; never javascript: URIs.
    ALLOWED_URI_REGEXP: /^(?:https?:|mailto:|data:image\/(?:png|jpe?g|gif|webp);|#|\/)/i,
    ADD_ATTR: ['target', 'rel'],
  });
}
