// Tiny self-contained rich-text editor (no external dependency — the package
// registry is unreachable in this environment). A contenteditable surface + a
// small toolbar (document.execCommand) that emits semantic HTML (<strong>, <em>,
// <a>, <ul>…) suitable for an HTML e-mail signature/body. Output is DOMPurify-
// sanitised on input/paste (client side); the server re-sanitises on save.
//
// The initial HTML is server-rendered INTO the contenteditable (already sanitised
// on save), so no value needs threading through the x-data args.
export default () => ({
    _dp: null,
    async init() {
        this._sync();
        try {
            const mod = await import('dompurify');
            this._dp = mod.default || mod;
        } catch (e) { /* sanitise server-side regardless */ }
        // Sanitise anything pasted in (strip scripts/styles/handlers).
        this.$refs.area.addEventListener('paste', () => setTimeout(() => this._clean(), 0));
    },
    _cfg() {
        return {
            ALLOWED_TAGS: ['a', 'b', 'strong', 'i', 'em', 'u', 's', 'br', 'p', 'div', 'span', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote'],
            ALLOWED_ATTR: ['href', 'title', 'target', 'rel'],
        };
    },
    _clean() {
        if (this._dp) this.$refs.area.innerHTML = this._dp.sanitize(this.$refs.area.innerHTML, this._cfg());
        this._sync();
    },
    _sync() { this.$refs.hidden.value = this.$refs.area.innerHTML; },
    cmd(c, v = null) { this.$refs.area.focus(); document.execCommand(c, false, v); this._sync(); },
    link() {
        const url = (window.prompt(this.$refs.hidden.dataset.linkPrompt || 'URL', 'https://') || '').trim();
        if (! url || ! /^(https?:|mailto:)/i.test(url)) return;
        this.cmd('createLink', url);
    },
    insert(text) { this.cmd('insertText', text); },
});
