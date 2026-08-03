// Mail archive LIST view (Phase 2). Zero-knowledge: the server lists only the
// metadata ledger (id / account / folder / size / archived-at / sealed_key).
// This component pulls the WHOLE ledger for the current account scope, decrypts
// each blob with the vault identity secret, and parses just the envelope
// (From / To / Subject / Date / has-attachment) CLIENT-side. All filtering,
// sorting and pagination then happen locally over that decrypted cache — the
// server never sees sender/subject/etc, so it cannot filter on them for us.

import { getJson, postForm } from '../shared/api.js';
import { fetchBlobBuffer } from '../shared/blob-io.js';
import { parseEnvelope, parseMessage, parseDate, displayAddress } from '../shared/mime.js';
import { formatDate, saveBlobAs } from '../shared/dom.js';
import { mailRemote, mailScripts } from '../shared/prefs.js';

const DECRYPT_CONCURRENCY = 8;

// Base64-encode a Uint8Array without blowing the call stack on large messages.
function bytesToB64(bytes) {
    let bin = '';
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
    }
    return btoa(bin);
}
// Safety cap on how many messages to pull+decrypt into the client cache for one
// scope — the server orders newest-first, so this keeps the most recent.
const MAX_LOAD = 3000;
const LEDGER_PAGE = 200;

export default (config) => ({
    config,
    cache: [],       // all decrypted rows for the current account scope
    accounts: [],    // [{id, name}] for the mailbox filter
    loading: false,
    progress: 0,
    progressTotal: 0,
    capped: false,
    error: '',
    // filters
    fAccount: '',
    fFolder: '',
    fText: '',
    fFrom: '',
    fTo: '',
    // pagination (client-side, over filtered)
    page: 1,
    perPage: 50,

    get unlocked() {
        return this.$store.vault?.unlocked === true;
    },

    async init() {
        this.$watch('unlocked', (v) => { if (v && this.cache.length === 0) this.load(); });
        this.$watch('fAccount', () => this.load());
        for (const k of ['fFolder', 'fText', 'fFrom', 'fTo']) this.$watch(k, () => { this.page = 1; });
        if (this.unlocked) this.load();
    },

    async load() {
        if (!this.unlocked || this.loading) return;
        this.loading = true;
        this.error = '';
        this.capped = false;
        this.progress = 0;
        try {
            await this.ensureAccounts();
            const ledger = await this.fetchLedger();
            this.progressTotal = ledger.length;
            this.cache = await this.decryptAll(ledger);
            this.page = 1;
        } catch (e) {
            this.error = this.config.loadFailed;
        } finally {
            this.loading = false;
        }
    },

    async ensureAccounts() {
        if (this.accounts.length) return;
        try {
            const res = await getJson(this.config.accountsUrl);
            this.accounts = (res.accounts ?? []).map((a) => ({ id: a.id, name: a.name }));
        } catch {
            // Non-fatal; the mailbox column falls back to the account id.
        }
    },

    accountName(id) {
        return this.accounts.find((a) => a.id === id)?.name || `#${id}`;
    },

    // Walk the paginated ledger for the current account scope (newest first,
    // capped at MAX_LOAD).
    async fetchLedger() {
        const rows = [];
        let page = 1;
        let last = 1;
        do {
            const acc = this.fAccount ? `&account_id=${encodeURIComponent(this.fAccount)}` : '';
            const res = await getJson(`${this.config.messagesUrl}?page=${page}&per_page=${LEDGER_PAGE}${acc}`);
            rows.push(...(res.data ?? []));
            last = res.meta?.last_page ?? 1;
            page++;
            if (rows.length >= MAX_LOAD) { this.capped = true; break; }
        } while (page <= last);
        return rows.slice(0, MAX_LOAD);
    },

    async decryptAll(ledger) {
        const out = new Array(ledger.length);
        let next = 0;
        const worker = async () => {
            while (next < ledger.length) {
                const i = next++;
                out[i] = await this.decryptRow(ledger[i]);
                this.progress++;
            }
        };
        await Promise.all(Array.from({ length: Math.min(DECRYPT_CONCURRENCY, ledger.length) }, worker));
        return out;
    },

    async decryptRow(m) {
        const base = {
            id: m.id,
            sealedKey: m.sealed_key, // kept so the modal can re-decrypt the full body
            folder: m.folder,
            accountId: m.account_id,
            mailbox: this.accountName(m.account_id),
            archivedAt: m.created_at,
        };
        try {
            const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', m.id));
            const bytes = await window.Vault.decryptMailBlob(m.sealed_key, buffer);
            const env = parseEnvelope(bytes);
            const d = parseDate(env.date);
            const iso = d ? d.toISOString() : m.created_at;
            return {
                ...base,
                from: displayAddress(env.from) || this.config.unknown,
                fromRaw: env.from,
                to: displayAddress(env.to) || this.config.unknown,
                subject: env.subject || this.config.noSubject,
                date: iso,
                ts: d ? d.getTime() : Date.parse(m.created_at) || 0,
                dateLabel: formatDate(iso, { dateStyle: 'medium', timeStyle: 'short' }),
                hasAttachment: env.hasAttachment,
                ok: true,
            };
        } catch {
            return { ...base, from: '', fromRaw: '', to: '', subject: this.config.decryptFailed, date: base.archivedAt, ts: Date.parse(base.archivedAt) || 0, dateLabel: formatDate(base.archivedAt), hasAttachment: false, ok: false };
        }
    },

    get folders() {
        return [...new Set(this.cache.map((r) => r.folder))].sort();
    },

    get filtered() {
        const q = this.fText.trim().toLowerCase();
        const fromTs = this.fFrom ? Date.parse(this.fFrom) : null;
        const toTs = this.fTo ? Date.parse(this.fTo) + 86_400_000 : null; // inclusive end-of-day
        return this.cache.filter((r) => {
            if (this.fFolder && r.folder !== this.fFolder) return false;
            if (q && !(`${r.from} ${r.fromRaw} ${r.to} ${r.subject}`.toLowerCase().includes(q))) return false;
            if (fromTs !== null && r.ts < fromTs) return false;
            if (toTs !== null && r.ts >= toTs) return false;
            return true;
        }).sort((a, b) => b.ts - a.ts);
    },

    get lastPage() {
        return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
    },

    get pageRows() {
        const start = (this.page - 1) * this.perPage;
        return this.filtered.slice(start, start + this.perPage);
    },

    get filtersActive() {
        return this.fFolder !== '' || this.fText !== '' || this.fFrom !== '' || this.fTo !== '';
    },

    resetFilters() {
        this.fFolder = ''; this.fText = ''; this.fFrom = ''; this.fTo = '';
        this.page = 1;
    },

    goto(p) {
        if (p < 1 || p > this.lastPage || p === this.page) return;
        this.page = p;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    // ---- message / attachments modal ------------------------------------
    open: null,        // the row being viewed
    openLoading: false,
    openError: '',
    msg: null,         // { textBody, htmlBody, attachments }
    bodyHtml: '',      // sanitized HTML body (scripts OFF)
    bodyFrame: '',     // srcdoc for the sandboxed iframe (scripts ON)
    _raw: null,        // decrypted RFC822 bytes of the open message (for push-back)
    pushing: false,
    pushed: false,
    pushError: '',
    _urls: [],         // object URLs to revoke on close

    get bodyText() {
        if (!this.msg) return '';
        return this.msg.textBody || '';
    },

    async openMessage(r) {
        this.open = r;
        this.msg = null;
        this.bodyHtml = '';
        this.bodyFrame = '';
        this._raw = null;
        this.pushed = false;
        this.pushError = '';
        this.openError = '';
        this.openLoading = true;
        try {
            const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', r.id));
            const bytes = await window.Vault.decryptMailBlob(r.sealedKey, buffer);
            this._raw = bytes;
            this.msg = parseMessage(bytes);
            // Prefer the HTML body; fall back to plain text. How HTML is rendered
            // depends on the per-user prefs: scripts ON -> isolated sandbox iframe;
            // scripts OFF -> DOMPurify inline. Remote content (images) is only
            // loaded when the user has opted in.
            if (!this.msg.textBody && this.msg.htmlBody) {
                if (mailScripts()) {
                    this.bodyFrame = this._buildFrame(this.msg.htmlBody, mailRemote());
                } else {
                    this.bodyHtml = await this._sanitizeHtml(this.msg.htmlBody, mailRemote());
                }
            }
        } catch {
            this.openError = this.config.decryptFailed;
        } finally {
            this.openLoading = false;
        }
    },

    // Sanitize attacker-controlled mail HTML for inline render: DOMPurify strips
    // scripts/event handlers (XSS), we additionally forbid <style> (so mail CSS
    // cannot restyle the whole app) and block remote images (tracking pixels).
    async _sanitizeHtml(html, allowRemote) {
        const DOMPurify = (await import('dompurify')).default;
        // The hook reads this flag each call (hooks are global/one-time).
        this._allowRemote = allowRemote;
        if (!DOMPurify._llMailHook) {
            const self = this;
            DOMPurify.addHook('afterSanitizeAttributes', (node) => {
                if (node.nodeName === 'IMG' && !self._allowRemote && /^https?:/i.test(node.getAttribute('src') || '')) {
                    node.removeAttribute('src');
                    node.setAttribute('alt', node.getAttribute('alt') || '[remote image blocked]');
                }
                if (node.nodeName === 'A') { node.setAttribute('target', '_blank'); node.setAttribute('rel', 'noopener noreferrer'); }
            });
            DOMPurify._llMailHook = true;
        }
        return DOMPurify.sanitize(html, {
            FORBID_TAGS: ['style', 'script', 'title', 'head', 'meta', 'link', 'base', 'iframe', 'object', 'embed'],
            FORBID_ATTR: ['background'],
        });
    },

    // Build the srcdoc for the sandboxed iframe used when the user has opted into
    // running mail scripts. The iframe has NO allow-same-origin, so scripts run
    // in an opaque origin isolated from the app (no access to cookies, storage,
    // or the vault). The inner CSP additionally gates remote content on the
    // remote-content preference.
    _buildFrame(html, allowRemote) {
        const csp = allowRemote
            ? "default-src 'none'; img-src * data: cid:; media-src *; style-src 'unsafe-inline' *; font-src * data:; script-src 'unsafe-inline' 'unsafe-eval'"
            : "default-src 'none'; img-src data: cid:; style-src 'unsafe-inline'; font-src data:; script-src 'unsafe-inline' 'unsafe-eval'";
        return `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${csp}"><base target="_blank"></head><body style="margin:0;font:14px/1.5 sans-serif;color:#111">${html}</body></html>`;
    },

    closeMessage() {
        for (const u of this._urls) URL.revokeObjectURL(u);
        this._urls = [];
        this.open = null;
        this.msg = null;
        this._raw = null;
    },

    // Push the open message BACK to its origin IMAP mailbox (IMAP APPEND). The
    // client sends the decrypted RFC822 to the server, which APPENDs it — the
    // one deliberate write-to-origin action, behind a confirm.
    async pushBack() {
        if (!this._raw || this.pushing) return;
        if (!await this.$store.confirm.ask(this.config.pushConfirmMsg)) return;
        this.pushing = true;
        this.pushError = '';
        try {
            await postForm(this.config.pushbackBase.replace('__id__', this.open.id), {
                raw_b64: bytesToB64(this._raw),
                folder: this.open.folder,
            });
            this.pushed = true;
        } catch {
            this.pushError = this.config.pushFailed;
        } finally {
            this.pushing = false;
        }
    },

    // Only these types are ever rendered INLINE (same-origin blob URL). Mail
    // attachments are fully attacker-controlled, so opening e.g. text/html or
    // image/svg+xml inline would execute script on the app origin (XSS).
    // Everything else falls back to a download (saveBlobAs never executes).
    // SVG is deliberately excluded (it can carry script); PDFs render in the
    // browser's own sandboxed viewer.
    _viewable(att) {
        return ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain']
            .includes((att.contentType || '').toLowerCase());
    },

    canView(att) {
        return this._viewable(att);
    },

    viewAttachment(att) {
        if (!this._viewable(att)) { this.downloadAttachment(att); return; }
        const url = URL.createObjectURL(new Blob([att.bytes], { type: att.contentType }));
        this._urls.push(url);
        window.open(url, '_blank', 'noopener');
    },

    downloadAttachment(att) {
        // application/octet-stream forces a save, never an inline render.
        saveBlobAs(new Blob([att.bytes], { type: 'application/octet-stream' }), att.filename);
    },

    fmtSize(n) {
        if (n < 1024) return `${n} B`;
        if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
        return `${(n / 1024 / 1024).toFixed(1)} MB`;
    },

    unlock() {
        this.$dispatch('vault-panel');
    },
});
