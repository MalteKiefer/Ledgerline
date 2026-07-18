import { jsonHeaders } from '../shared/api';

// QR device pairing (profile page). Starts a pairing, shows the QR, polls its
// state, and lets the owner approve/reject the device that scanned it. The app
// exchanges the code for a bearer once approved — no token ever touches this UI.
// `opts.cli` switches the code channel: the app pairing renders a scannable QR,
// the command-line pairing shows a copyable text code (shorter-lived).
export default (opts = {}) => ({
    method: 'app', // 'app' = QR (mobile), 'cli' = copy/paste code
    get cli() { return this.method === 'cli'; },
    active: false, qr: '', code: '', copied: false, id: null, status: '', deviceName: '', expiresAt: 0, remaining: 0, devices: [], _timer: null, _tick: null,
    init() { this.loadDevices(); },
    async loadDevices() {
        try {
            const r = await fetch('/devices', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) this.devices = (await r.json()).devices || [];
        } catch (e) { /* keep current list */ }
    },
    async revokeDevice(id) {
        try {
            const r = await fetch(`/devices/${id}`, { method: 'DELETE', headers: jsonHeaders() });
            if (r.ok) this.loadDevices();
        } catch (e) { /* ignore */ }
    },
    // Remote kill switch: flag a client to erase its local state on next contact.
    async wipeDevice(id) {
        if (! await this.$store.confirm.ask(opts.wipeConfirm || 'Wipe this client on its next connection?')) return;
        try {
            const r = await fetch(`/devices/${id}/wipe`, { method: 'POST', headers: jsonHeaders() });
            if (r.ok) this.loadDevices();
        } catch (e) { /* ignore */ }
    },
    async start(kind) {
        if (kind) this.method = kind;
        this._stopTimers();
        this.copied = false;
        try {
            const r = await fetch(this.cli ? '/device-pairings/cli' : '/device-pairings', { method: 'POST', headers: jsonHeaders() });
            if (! r.ok) {
                window.llToast?.(r.status === 429 ? (opts.rateLimited || 'Too many attempts — wait a moment') : (opts.startFailed || 'Could not start pairing'));
                return;
            }
            const d = await r.json();
            this.id = d.id; this.qr = d.qr || ''; this.code = d.code || ''; this.status = 'pending_scan'; this.active = true;
            this.expiresAt = Date.parse(d.expires_at) || 0;
            this._countdown();
            this._poll();
        } catch (e) { /* ignore */ }
    },
    async copyCode() {
        try { await navigator.clipboard.writeText(this.code); this.copied = true; setTimeout(() => { this.copied = false; }, 1500); } catch (e) { /* ignore */ }
    },
    // "Generate a new code" — start a fresh pairing (invalidates the old one).
    regenerate() { return this.start(); },
    _poll() {
        clearTimeout(this._timer);
        this._timer = setTimeout(async () => {
            // Keep polling through 'approved' until the app actually collects its
            // token (status becomes 'consumed'), so the device list refreshes live.
            if (! this.active || ['consumed', 'rejected', 'expired'].includes(this.status)) return;
            try {
                const r = await fetch(`/device-pairings/${this.id}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) {
                    const d = await r.json();
                    this.status = d.status; this.deviceName = d.device_name || '';
                    if (this.status === 'consumed') this.loadDevices();
                }
            } catch (e) { /* keep polling */ }
            this._poll();
        }, 2000);
    },
    _countdown() {
        clearInterval(this._tick);
        const step = () => {
            this.remaining = Math.max(0, Math.round((this.expiresAt - Date.now()) / 1000));
            if (this.remaining <= 0 && ['pending_scan', 'pending_approval'].includes(this.status)) {
                this.status = 'expired';
                clearInterval(this._tick);
            }
        };
        step();
        this._tick = setInterval(step, 1000);
    },
    get remainingText() {
        const s = Math.max(0, this.remaining);
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    },
    approve() { return this._act('approve'); },
    reject() { return this._act('reject'); },
    async _act(what) {
        try {
            const r = await fetch(`/device-pairings/${this.id}/${what}`, { method: 'POST', headers: jsonHeaders() });
            if (r.ok) { const d = await r.json(); this.status = d.status; clearInterval(this._tick); }
        } catch (e) { /* ignore */ }
    },
    _stopTimers() { clearTimeout(this._timer); clearInterval(this._tick); },
    reset() { this._stopTimers(); this.active = false; this.qr = ''; this.id = null; this.status = ''; this.deviceName = ''; this.remaining = 0; },
});
