// Shared square-crop modal. `await window.llCrop(blobOrUrl)` opens it and
// resolves to a 256px square JPEG as a Uint8Array (or null if cancelled). Pan by
// dragging, zoom with the slider; the visible square window is drawn to a canvas.
// Rendered once in the layout so contacts + gallery reuse the same UI.
export default () => ({
    open: false,
    url: '',
    scale: 1, minScale: 1, maxScale: 8,
    tx: 0, ty: 0, natW: 0, natH: 0,
    VP: 300, // viewport px used for the crop math
    _img: null, _objUrl: null, _resolve: null, _drag: null,

    init() { window.llCrop = (src) => this._start(src); },

    async _start(src) {
        const isBlob = src instanceof Blob;
        const url = isBlob ? URL.createObjectURL(src) : src;
        this._objUrl = isBlob ? url : null;
        this.url = url;
        try { await this._load(url); } catch (e) { if (this._objUrl) URL.revokeObjectURL(this._objUrl); return null; }
        this.open = true;
        return new Promise((res) => { this._resolve = res; });
    },
    _load(url) {
        return new Promise((res, rej) => {
            const img = new Image();
            img.onload = () => { this._img = img; this.natW = img.naturalWidth; this.natH = img.naturalHeight; this.minScale = this.VP / Math.min(this.natW, this.natH); this.scale = this.minScale; this._center(); res(); };
            img.onerror = rej;
            img.src = url;
        });
    },
    _center() { this.tx = (this.VP - this.natW * this.scale) / 2; this.ty = (this.VP - this.natH * this.scale) / 2; this._clamp(); },
    _clamp() {
        const dw = this.natW * this.scale, dh = this.natH * this.scale;
        this.tx = Math.min(0, Math.max(this.VP - dw, this.tx));
        this.ty = Math.min(0, Math.max(this.VP - dh, this.ty));
    },
    setScale(v) {
        const c = this.VP / 2;
        const sx = (c - this.tx) / this.scale, sy = (c - this.ty) / this.scale;
        this.scale = Math.max(this.minScale, Math.min(this.minScale * this.maxScale, +v));
        this.tx = c - sx * this.scale; this.ty = c - sy * this.scale; this._clamp();
    },
    startDrag(e) { this._drag = { x: e.clientX, y: e.clientY, tx: this.tx, ty: this.ty }; },
    onDrag(e) { if (! this._drag) return; this.tx = this._drag.tx + (e.clientX - this._drag.x); this.ty = this._drag.ty + (e.clientY - this._drag.y); this._clamp(); },
    endDrag() { this._drag = null; },
    imgStyle() { return `width:${Math.round(this.natW * this.scale)}px;height:${Math.round(this.natH * this.scale)}px;transform:translate(${Math.round(this.tx)}px,${Math.round(this.ty)}px);`; },

    cancel() { this._finish(null); },
    confirm() {
        const OUT = 256;
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = OUT;
        const sSize = this.VP / this.scale;
        const sx = -this.tx / this.scale, sy = -this.ty / this.scale;
        canvas.getContext('2d').drawImage(this._img, sx, sy, sSize, sSize, 0, 0, OUT, OUT);
        canvas.toBlob(async (b) => { this._finish(b ? new Uint8Array(await b.arrayBuffer()) : null); }, 'image/jpeg', 0.85);
    },
    _finish(bytes) { this.open = false; if (this._objUrl) URL.revokeObjectURL(this._objUrl); this._objUrl = null; const r = this._resolve; this._resolve = null; if (r) r(bytes); },
});
