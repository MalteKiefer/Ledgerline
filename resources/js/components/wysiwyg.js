// Self-contained rich-text editor for HTML mail (headings, bold/italic/underline/
// strike, font family, font size in pt, colour, alignment, lists, quote, link,
// image [URL / upload], divider). No external dependency. Dialogs are
// real in-editor modals (no window.prompt). Output DOMPurify-sanitised client side;
// the server re-sanitises (HtmlMailSanitizer).

export default () => ({
    _dp: null,
    _savedRange: null,
    // link modal
    linkOpen: false, linkUrl: '', linkText: '',
    // image modal
    imgOpen: false, imgTab: 'url', imgUrl: '', imgBusy: false,

    async init() {
        try { document.execCommand('styleWithCSS', false, true); } catch (e) { /* ignore */ }
        this._sync();
        try { const mod = await import('dompurify'); this._dp = mod.default || mod; } catch (e) { /* server sanitises */ }
        this.$refs.area.addEventListener('paste', () => setTimeout(() => this._clean(), 0));
    },
    _cfg() {
        return {
            ALLOWED_TAGS: ['a', 'b', 'strong', 'i', 'em', 'u', 's', 'br', 'p', 'div', 'span', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote', 'img', 'hr'],
            ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'style', 'src', 'alt', 'width'],
        };
    },
    _clean() { if (this._dp) this.$refs.area.innerHTML = this._dp.sanitize(this.$refs.area.innerHTML, this._cfg()); this._sync(); },
    _sync() { this.$refs.hidden.value = this.$refs.area.innerHTML; },
    _focus() { this.$refs.area.focus(); },
    cmd(c, v = null) { this._focus(); try { document.execCommand('styleWithCSS', false, true); } catch (e) { /* ignore */ } document.execCommand(c, false, v); this._sync(); },
    heading(tag) { this.cmd('formatBlock', tag); },
    color(hex) { if (hex) this.cmd('foreColor', hex); },
    align(dir) { this.cmd('justify' + dir); },
    hr() { this.cmd('insertHorizontalRule'); },
    unlink() { this.cmd('unlink'); },

    // Wrap the current selection in a <span> carrying an inline style (font family /
    // size in pt) — execCommand can't set a pt size, and inline style survives the
    // server allowlist + e-mail clients. No-op without a (non-collapsed) selection.
    _wrapStyle(prop, val) {
        this._focus();
        const sel = window.getSelection();
        if (! sel || ! sel.rangeCount) return;
        const range = sel.getRangeAt(0);
        if (range.collapsed) return;
        const span = document.createElement('span');
        span.style[prop] = val;
        try { span.appendChild(range.extractContents()); range.insertNode(span); } catch (e) { /* cross-node selection */ }
        sel.removeAllRanges();
        this._sync();
    },
    setFont(family) { if (family) this._wrapStyle('fontFamily', family); },
    setSize(pt) { if (pt) this._wrapStyle('fontSize', pt + 'pt'); },

    // ---- Selection persistence across a modal ----
    _saveRange() { const s = window.getSelection(); this._savedRange = (s && s.rangeCount) ? s.getRangeAt(0).cloneRange() : null; },
    _restoreRange() { this._focus(); const s = window.getSelection(); s.removeAllRanges(); if (this._savedRange) s.addRange(this._savedRange); },

    // ---- Link modal ----
    openLink() {
        this._saveRange();
        const sel = window.getSelection();
        this.linkText = sel ? sel.toString() : '';
        this.linkUrl = '';
        this.linkOpen = true;
    },
    applyLink() {
        const url = this.linkUrl.trim();
        if (! /^(https?:|mailto:)/i.test(url)) return;
        this._restoreRange();
        if (this._savedRange && this._savedRange.collapsed && this.linkText.trim()) {
            document.execCommand('insertHTML', false, `<a href="${url.replace(/"/g, '&quot;')}">${this.linkText.replace(/[<>&]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]))}</a>`);
        } else {
            document.execCommand('createLink', false, url);
        }
        this.linkOpen = false; this._sync();
    },

    // ---- Image modal ----
    openImage() { this._saveRange(); this.imgTab = 'url'; this.imgUrl = ''; this.imgOpen = true; },
    _insertImg(src) {
        this._restoreRange();
        document.execCommand('insertImage', false, src);
        this.imgOpen = false; this._sync();
    },
    insertImageUrl() {
        const url = this.imgUrl.trim();
        if (! /^https?:\/\//i.test(url)) return;
        this._insertImg(url);
    },
    async uploadImage(ev) {
        const file = ev.target.files?.[0]; if (! file) return;
        if (! /^image\//.test(file.type)) return;
        this.imgBusy = true;
        try {
            // Embed as a data: URI so the image is self-contained in the e-mail.
            const dataUrl = await new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(file); });
            this._insertImg(dataUrl);
        } catch (e) { /* ignore */ }
        this.imgBusy = false;
        ev.target.value = '';
    },
});
