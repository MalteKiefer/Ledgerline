// Self-contained rich-text editor (no external dependency — the package registry
// is unreachable in this environment). A contenteditable surface + toolbar
// (document.execCommand) emitting semantic/inline-styled HTML suitable for an HTML
// e-mail (headings, bold/italic/underline, colour, alignment, lists, quote, link,
// image, rule). Output is DOMPurify-sanitised on input/paste (client side); the
// server re-sanitises (HtmlMailSanitizer) on save + before send.
//
// The initial HTML is server-rendered INTO the contenteditable (already sanitised).
export default () => ({
    _dp: null,
    async init() {
        // Emit inline CSS (<span style="color">, text-align) rather than <font>/align
        // attributes so the output survives the server allowlist + e-mail clients.
        try { document.execCommand('styleWithCSS', false, true); } catch (e) { /* not supported */ }
        this._sync();
        try {
            const mod = await import('dompurify');
            this._dp = mod.default || mod;
        } catch (e) { /* sanitised server-side regardless */ }
        this.$refs.area.addEventListener('paste', () => setTimeout(() => this._clean(), 0));
    },
    _cfg() {
        return {
            ALLOWED_TAGS: ['a', 'b', 'strong', 'i', 'em', 'u', 's', 'br', 'p', 'div', 'span', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote', 'img', 'hr'],
            ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'style', 'src', 'alt'],
        };
    },
    _clean() {
        if (this._dp) this.$refs.area.innerHTML = this._dp.sanitize(this.$refs.area.innerHTML, this._cfg());
        this._sync();
    },
    _sync() { this.$refs.hidden.value = this.$refs.area.innerHTML; },
    _focus() { this.$refs.area.focus(); },
    cmd(c, v = null) { this._focus(); try { document.execCommand('styleWithCSS', false, true); } catch (e) { /* ignore */ } document.execCommand(c, false, v); this._sync(); },
    heading(tag) { this.cmd('formatBlock', tag); }, // h1/h2/h3/p/blockquote
    color(hex) { if (hex) this.cmd('foreColor', hex); },
    align(dir) { this.cmd('justify' + dir); }, // Left/Center/Right/Full
    hr() { this.cmd('insertHorizontalRule'); },
    unlink() { this.cmd('unlink'); },
    link() {
        const url = (window.prompt(this.$refs.hidden.dataset.linkPrompt || 'URL', 'https://') || '').trim();
        if (! url || ! /^(https?:|mailto:)/i.test(url)) return;
        this.cmd('createLink', url);
    },
    image() {
        const url = (window.prompt(this.$refs.hidden.dataset.imgPrompt || 'Image URL', 'https://') || '').trim();
        if (! url || ! /^(https?:|data:image\/)/i.test(url)) return;
        this.cmd('insertImage', url);
    },
});
