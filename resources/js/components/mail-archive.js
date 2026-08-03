// Mail archive LIST view (Phase 2). Zero-knowledge: the server lists only the
// metadata ledger (id / account / folder / size / archived-at / sealed_key).
// This component pulls the WHOLE ledger for the current account scope, decrypts
// each blob with the vault identity secret, and parses just the envelope
// (From / To / Subject / Date / has-attachment) CLIENT-side. All filtering,
// sorting and pagination then happen locally over that decrypted cache — the
// server never sees sender/subject/etc, so it cannot filter on them for us.

import { getJson } from '../shared/api.js';
import { fetchBlobBuffer } from '../shared/blob-io.js';
import { parseEnvelope, parseMessage, parseDate, displayAddress } from '../shared/mime.js';
import { formatDate, saveBlobAs } from '../shared/dom.js';

const DECRYPT_CONCURRENCY = 8;
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
    _urls: [],         // object URLs to revoke on close

    get bodyText() {
        if (!this.msg) return '';
        if (this.msg.textBody) return this.msg.textBody;
        // Fall back to a plain-text preview of an HTML-only mail.
        return (this.msg.htmlBody || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    },

    async openMessage(r) {
        this.open = r;
        this.msg = null;
        this.openError = '';
        this.openLoading = true;
        try {
            const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', r.id));
            const bytes = await window.Vault.decryptMailBlob(r.sealedKey, buffer);
            this.msg = parseMessage(bytes);
        } catch {
            this.openError = this.config.decryptFailed;
        } finally {
            this.openLoading = false;
        }
    },

    closeMessage() {
        for (const u of this._urls) URL.revokeObjectURL(u);
        this._urls = [];
        this.open = null;
        this.msg = null;
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
