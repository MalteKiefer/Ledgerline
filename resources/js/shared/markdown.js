// The markdown stack (marked + DOMPurify + highlight.js + its CSS) is only ever
// needed to preview a note, so it is code-split out of the initial bundle and
// loaded on first use. Returns a memoised { render(md) } that highlights fenced
// code (client-side — notes are zero-knowledge) and DOMPurify-sanitises output.
let _markdown = null;
export async function loadMarkdown() {
    if (_markdown) return _markdown;
    const [{ Marked }, DOMPurify, { markedHighlight }, hljs] = await Promise.all([
        import('marked'),
        import('dompurify'),
        import('marked-highlight'),
        import('highlight.js/lib/common'),
    ]);
    await Promise.all([
        import('github-markdown-css/github-markdown-light.css'),
        import('highlight.js/styles/github.css'),
    ]);
    const hl = hljs.default;
    // marked v18: a per-instance Marked with GFM + hard line breaks and the
    // highlight extension (client-side highlighting — notes are zero-knowledge).
    const marked = new Marked(
        { gfm: true, breaks: true },
        markedHighlight({
            langPrefix: 'hljs language-',
            highlight(code, lang) {
                const language = lang && hl.getLanguage(lang) ? lang : 'plaintext';
                return hl.highlight(code, { language }).value;
            },
        }),
    );
    _markdown = { render: (md) => (md ? DOMPurify.default.sanitize(marked.parse(md)) : '') };
    return _markdown;
}
