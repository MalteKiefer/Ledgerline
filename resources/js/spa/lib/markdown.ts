import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Render a note's Markdown body to sanitised HTML. The body is user-authored
// plaintext stored server-side and rendered only on the client; DOMPurify strips
// scripts / event handlers / dangerous URIs so a note can never inject script.
// Wikilink ([[…]]) rewriting is added in a later stage.
marked.setOptions({ breaks: true, gfm: true });

export function renderMarkdown(md: string): string {
  const raw = marked.parse(md ?? '', { async: false }) as string;
  return DOMPurify.sanitize(raw, {
    ALLOWED_TAGS: [
      'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'br', 'hr',
      'strong', 'em', 'del', 'blockquote', 'code', 'pre',
      'ul', 'ol', 'li', 'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
      'input', // task-list checkboxes (GFM)
    ],
    ALLOWED_ATTR: ['href', 'title', 'src', 'alt', 'type', 'checked', 'disabled', 'class'],
    // Links only to http(s)/mailto; images only http(s)/data — no javascript: URIs.
    ALLOWED_URI_REGEXP: /^(?:https?:|mailto:|data:image\/(?:png|jpe?g|gif|webp);|#)/i,
    ADD_ATTR: ['target', 'rel'],
  });
}
