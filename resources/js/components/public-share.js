// publicShare component. Extracted from app.js.
import { getJson, postForm } from '../shared/api';
import { formatDate } from '../shared/dom';

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | password | ready | error | expired | notfound
    error: '',
    sk: null,
    manifest: null, // { name, allowDownload, photos: [] }
    password: '',
    unlocking: false,
    thumbs: {},
    viewer: { open: false, src: '', kind: 'none', photo: null },

    async init() {
        // Share key from the fragment (#s:<b64>) — never sent to the server.
        const m = (location.hash || '').match(/s:([A-Za-z0-9_\-+/=]+)/);
        this.sk = m ? decodeURIComponent(m[1]) : null;
        try {
            const res = await fetch(config.metaUrl, { headers: { Accept: 'application/json' } });
            if (res.status === 404) { this.state = 'notfound'; return; }
            if (res.status === 410) { this.state = 'expired'; return; }
            const meta = await res.json();
            if (! meta.found) { this.state = 'notfound'; return; }
            if (meta.expired) { this.state = 'expired'; return; }
            if (! this.sk) { this.state = 'error'; this.error = labels.noKey || ''; return; }
            if (meta.needsPassword && ! meta.unlocked) { this.state = 'password'; return; }
            await this.loadManifest();
        } catch (e) { this.state = 'error'; }
    },

    async unlock() {
        if (this.unlocking) return;
        this.unlocking = true; this.error = '';
        try {
            await postForm(config.unlockUrl, { password: this.password });
            this.password = '';
            await this.loadManifest();
        } catch (e) { this.error = labels.wrongPassword || ''; } finally { this.unlocking = false; }
    },

    async loadManifest() {
        this.state = 'boot';
        try {
            const { sealed } = await getJson(config.manifestUrl);
            const bytes = await window.ShareCrypto.unwrap(sealed, this.sk);
            this.manifest = JSON.parse(new TextDecoder().decode(bytes));
            this.state = 'ready';
        } catch (e) { this.state = 'error'; this.error = labels.badKey || ''; }
    },

    get photos() { return this.manifest?.photos || []; },

    async _blob(ref, keyJson) {
        const buf = await (await fetch(`${config.blobBase}/${ref}`)).arrayBuffer();
        const fk = await window.ShareCrypto.unwrap(keyJson, this.sk);
        return window.ShareCrypto.decrypt(buf, fk);
    },

    async thumbFor(p) {
        if (! p.tR || this.thumbs[p.id]) return this.thumbs[p.id];
        try {
            const bytes = await this._blob(p.tR, p.tK);
            const url = URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
            this.thumbs[p.id] = url;
            return url;
        } catch (e) { return ''; }
    },

    async openViewer(p) {
        let ref = p.mR || p.tR, key = p.mK || p.tK, kind = 'image', mime = 'image/jpeg';
        if (p.t === 'video' && this.manifest?.allowDownload && p.oR) { ref = p.oR; key = p.oK; kind = 'video'; mime = 'video/mp4'; }
        this.viewer = { open: true, src: '', kind, photo: p };
        try {
            const bytes = await this._blob(ref, key);
            this.viewer.src = URL.createObjectURL(new Blob([bytes], { type: mime }));
        } catch (e) { this.closeViewer(); }
    },
    closeViewer() { if (this.viewer.src) URL.revokeObjectURL(this.viewer.src); this.viewer = { open: false, src: '', kind: 'none', photo: null }; },

    canDownload(p) { return ! ! (this.manifest?.allowDownload && p && p.oR); },
    async download(p) {
        if (! this.canDownload(p)) return;
        try {
            const bytes = await this._blob(p.oR, p.oK);
            const url = URL.createObjectURL(new Blob([bytes]));
            const a = document.createElement('a'); a.href = url; a.download = p.id || 'photo'; a.click();
            setTimeout(() => URL.revokeObjectURL(url), 5000);
        } catch (e) { /* ignore */ }
    },
    fmtDate(v) { return formatDate(v, { year: 'numeric', month: 'long', day: 'numeric' }); },
});
