// fileShare component. Extracted from app.js.
import { jsonHeaders } from '../shared/api';
import { formatBytes } from '../shared/file-categories';

export default (config = {}, labels = {}) => ({
    state: 'boot', // boot | password | ready | error | expired | notfound
    error: '',
    sk: null,
    manifest: null, // { kind, name, files: [] }
    password: '',
    unlocking: false,
    thumbs: {},
    viewer: { open: false, kind: 'none', src: '', file: null },

    async init() {
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
            const res = await fetch(config.unlockUrl, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ password: this.password }) });
            if (! res.ok) { this.error = labels.wrongPassword || ''; return; }
            this.password = '';
            await this.loadManifest();
        } catch (e) { this.error = labels.wrongPassword || ''; } finally { this.unlocking = false; }
    },
    async loadManifest() {
        this.state = 'boot';
        try {
            const res = await fetch(config.manifestUrl, { headers: { Accept: 'application/json' } });
            if (! res.ok) throw new Error('manifest');
            const { sealed } = await res.json();
            const bytes = await window.ShareCrypto.unwrap(sealed, this.sk);
            this.manifest = JSON.parse(new TextDecoder().decode(bytes));
            this.state = 'ready';
        } catch (e) { this.state = 'error'; this.error = labels.badKey || ''; }
    },
    cwd: '', // current relative folder path within a shared folder ('' = its root)
    get allFiles() { return this.manifest?.files || []; },
    get isFolder() { return this.manifest?.kind === 'folder'; },
    // Files that sit directly in the current folder.
    get filesHere() { return this.allFiles.filter((f) => (f.path || '') === this.cwd); },
    // Immediate subfolder names under the current folder (derived from the paths).
    get subfolders() {
        const prefix = this.cwd === '' ? '' : this.cwd + '/';
        const set = new Set();
        for (const f of this.allFiles) {
            const p = f.path || '';
            if (this.cwd !== '' && p !== this.cwd && ! p.startsWith(prefix)) continue;
            const rest = this.cwd === '' ? p : p.slice(prefix.length);
            if (rest) set.add(rest.split('/')[0]);
        }
        return [...set].sort((a, b) => a.localeCompare(b));
    },
    get crumbs() {
        if (! this.cwd) return [];
        const segs = this.cwd.split('/');
        return segs.map((name, i) => ({ name, path: segs.slice(0, i + 1).join('/') }));
    },
    enterFolder(name) { this.cwd = this.cwd === '' ? name : this.cwd + '/' + name; },
    goTo(path) { this.cwd = path || ''; },
    // How many files live anywhere under a subfolder of the current folder.
    folderFileCount(name) {
        const base = (this.cwd === '' ? '' : this.cwd + '/') + name;
        return this.allFiles.filter((f) => { const p = f.path || ''; return p === base || p.startsWith(base + '/'); }).length;
    },
    isImage(f) { return /^image\//.test(f.mime || '') && ! /svg/.test(f.mime || ''); },
    isPdf(f) { return (f.mime || '') === 'application/pdf'; },
    async _blob(f) {
        const buf = await (await fetch(`${config.blobBase}/${f.ref}`)).arrayBuffer();
        const fk = await window.ShareCrypto.unwrap(f.key, this.sk);
        return window.ShareCrypto.decrypt(buf, fk);
    },
    async thumbFor(f) {
        if (! this.isImage(f) || this.thumbs[f.ref]) return this.thumbs[f.ref];
        try { const b = await this._blob(f); const url = URL.createObjectURL(new Blob([b], { type: f.mime })); this.thumbs[f.ref] = url; return url; } catch (e) { return ''; }
    },
    async open(f) {
        if (this.isImage(f) || this.isPdf(f)) {
            this.viewer = { open: true, kind: this.isPdf(f) ? 'pdf' : 'image', src: '', file: f };
            try { const b = await this._blob(f); this.viewer.src = URL.createObjectURL(new Blob([b], { type: f.mime })); } catch (e) { this.closeViewer(); }
        } else { this.download(f); }
    },
    closeViewer() { if (this.viewer.src) URL.revokeObjectURL(this.viewer.src); this.viewer = { open: false, kind: 'none', src: '', file: null }; },
    async download(f) {
        try {
            const b = await this._blob(f);
            const url = URL.createObjectURL(new Blob([b], { type: f.mime || 'application/octet-stream' }));
            const a = document.createElement('a'); a.href = url; a.download = f.name || 'file'; a.click();
            setTimeout(() => URL.revokeObjectURL(url), 5000);
        } catch (e) { /* ignore */ }
    },
    fmtSize: formatBytes,
});
