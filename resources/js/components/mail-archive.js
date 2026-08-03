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
import { loadEnvelopes, putEnvelopes } from '../shared/mail-cache.js';

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

function b64ToBytes(b64) {
    const bin = atob(b64);
    const out = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
}
// Safety cap on how many ledger rows to pull for one scope (newest first). With
// the envelope index + IndexedDB cache only NEW rows are ever decrypted, so this
// can be large; it only bounds a pathological mailbox.
const MAX_LOAD = 50000;
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
    showTrash: false,
    selected: [],
    bulkBusy: false,
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
        this.$watch('showTrash', () => this.load());
        for (const k of ['fFolder', 'fText', 'fFrom', 'fTo']) this.$watch(k, () => { this.page = 1; });
        if (this.unlocked) this.load();
    },

    async load() {
        if (!this.unlocked || this.loading) return;
        this.loading = true;
        this.error = '';
        this.capped = false;
        this.progress = 0;
        this.progressTotal = 0;
        try {
            await this.ensureAccounts();
            const ledger = await this.fetchLedger();

            // The list/search index is the per-message ENVELOPE, cached in
            // IndexedDB. We only decrypt what is not cached: server-stored
            // envelopes (tiny, fast) or — for messages that have none yet — the
            // full body ONCE (then build + upload its envelope for next time).
            const cached = await loadEnvelopes();
            const needEnv = [];
            const needBackfill = [];
            for (const m of ledger) {
                if (cached.has(m.id)) continue;
                if (m.envelope && m.envelope_key) needEnv.push(m);
                else needBackfill.push(m);
            }
            this.progressTotal = needEnv.length + needBackfill.length;

            const fresh = [];
            await this._pool(needEnv, DECRYPT_CONCURRENCY, async (m) => {
                try { const e = await this._decryptEnvelope(m); fresh.push(e); cached.set(m.id, e); } catch { /* skip */ }
                this.progress++;
            });
            await this._pool(needBackfill, 4, async (m) => {
                try { const e = await this._backfill(m); fresh.push(e); cached.set(m.id, e); } catch { /* skip */ }
                this.progress++;
            });
            if (fresh.length) putEnvelopes(fresh);

            this.cache = ledger.map((m) => this._buildRow(m, cached.get(m.id)));
            this.page = 1;
            this.selected = [];
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
        return this.accounts.find((a) => a.id === id)?.name || (id ? `#${id}` : this.config.unknown);
    },

    // Walk the paginated ledger for the current account scope (newest first).
    async fetchLedger() {
        const rows = [];
        let page = 1;
        let last = 1;
        do {
            const acc = this.fAccount ? `&account_id=${encodeURIComponent(this.fAccount)}` : '';
            const trash = this.showTrash ? '&trashed=1' : '';
            const res = await getJson(`${this.config.messagesUrl}?page=${page}&per_page=${LEDGER_PAGE}${acc}${trash}`);
            rows.push(...(res.data ?? []));
            last = res.meta?.last_page ?? 1;
            page++;
            if (rows.length >= MAX_LOAD) { this.capped = true; break; }
        } while (page <= last);
        return rows.slice(0, MAX_LOAD);
    },

    // Bounded-concurrency runner.
    async _pool(items, conc, fn) {
        let i = 0;
        const worker = async () => { while (i < items.length) { const j = i++; await fn(items[j]); } };
        await Promise.all(Array.from({ length: Math.max(1, Math.min(conc, items.length)) }, worker));
    },

    // Build a cached envelope object from a parsed RFC822 envelope.
    _envFromParsed(m, e) {
        const d = parseDate(e.date);
        const iso = d ? d.toISOString() : m.created_at;
        return {
            id: m.id,
            from: displayAddress(e.from) || this.config.unknown,
            fromRaw: e.from || '',
            to: displayAddress(e.to) || this.config.unknown,
            subject: e.subject || this.config.noSubject,
            date: iso,
            ts: d ? d.getTime() : (Date.parse(m.created_at) || 0),
            hasAttachment: e.hasAttachment === true,
        };
    },

    // Decrypt a server-stored (tiny) envelope blob.
    async _decryptEnvelope(m) {
        const buffer = b64ToBytes(m.envelope);
        const bytes = await window.Vault.decryptMailBlob(m.envelope_key, buffer);
        const obj = JSON.parse(new TextDecoder().decode(bytes));
        obj.id = m.id;
        return obj;
    },

    // No envelope yet: decrypt the full body ONCE, build the envelope, and
    // upload it (sealed to our own keys) so future loads / other devices skip
    // the body.
    async _backfill(m) {
        const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', m.id));
        const bytes = await window.Vault.decryptMailBlob(m.sealed_key, buffer);
        const env = this._envFromParsed(m, parseEnvelope(bytes));
        this._uploadEnvelope(m.id, env).catch(() => {});
        return env;
    },

    async _uploadEnvelope(id, env) {
        const json = new TextEncoder().encode(JSON.stringify(env));
        const sealed = await window.Vault.sealMailBlob(json);
        await postForm(this.config.envelopeBase.replace('__id__', id), {
            envelope: bytesToB64(sealed.blob),
            envelope_key: sealed.sealedKey,
        });
    },

    // Merge an immutable cached envelope with the fresh ledger meta (seen /
    // trashed / folder / account come from the server, never the cache).
    _buildRow(m, env) {
        const base = {
            id: m.id,
            sealedKey: m.sealed_key,
            folder: m.folder,
            accountId: m.account_id,
            mailbox: this.accountName(m.account_id),
            archivedAt: m.created_at,
            seen: m.seen !== false,
            trashed: m.trashed === true,
        };
        if (!env) {
            return { ...base, from: '', fromRaw: '', to: '', subject: this.config.decryptFailed, date: base.archivedAt, ts: Date.parse(base.archivedAt) || 0, dateLabel: formatDate(base.archivedAt), hasAttachment: false, ok: false };
        }
        return {
            ...base,
            from: env.from,
            fromRaw: env.fromRaw,
            to: env.to,
            subject: env.subject,
            date: env.date,
            ts: env.ts,
            dateLabel: formatDate(env.date, { dateStyle: 'medium', timeStyle: 'short' }),
            hasAttachment: env.hasAttachment,
            ok: true,
        };
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
            // Prefer the HTML body (the designed mail); fall back to plain text
            // only when there is no HTML part. Scripts ON -> isolated sandbox
            // iframe; scripts OFF -> DOMPurify inline. Remote images load only
            // when the user has opted in.
            if (this.msg.htmlBody) {
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
        try {
            await postForm(this.config.pushbackBase.replace('__id__', this.open.id), {
                raw_b64: bytesToB64(this._raw),
                folder: this.open.folder,
            });
            window.llToast?.(this.config.pushDone);
        } catch {
            window.llToast?.(this.config.pushFailed);
        } finally {
            this.pushing = false;
        }
    },

    // Soft-hide (trash) or restore messages by id — immutable, never a hard
    // delete. `ids` defaults to the open message.
    async trashIds(ids) {
        if (!ids.length) return;
        try {
            await postForm(this.config.trashBase, { ids });
            this.cache = this.cache.filter((r) => !ids.includes(r.id));
            window.llToast?.(this.config.trashDone.replace(':n', String(ids.length)));
        } catch {
            window.llToast?.(this.config.trashFailed);
        }
    },

    async restoreIds(ids) {
        if (!ids.length) return;
        try {
            await postForm(this.config.restoreBase, { ids });
            this.cache = this.cache.filter((r) => !ids.includes(r.id));
            window.llToast?.(this.config.restoreDone.replace(':n', String(ids.length)));
        } catch {
            window.llToast?.(this.config.trashFailed);
        }
    },

    async trashOpen() {
        if (!this.open) return;
        if (!await this.$store.confirm.ask(this.config.trashConfirm)) return;
        const id = this.open.id;
        this.closeMessage();
        await this.trashIds([id]);
    },

    async restoreOpen() {
        if (!this.open) return;
        const id = this.open.id;
        this.closeMessage();
        await this.restoreIds([id]);
    },

    // ---- multi-select ----------------------------------------------------
    isSelected(id) { return this.selected.includes(id); },
    toggleSelect(id) {
        const i = this.selected.indexOf(id);
        if (i >= 0) this.selected.splice(i, 1); else this.selected.push(id);
    },
    get selectedCount() { return this.selected.length; },
    get pageIds() { return this.pageRows.map((r) => r.id); },
    get allPageSelected() {
        const ids = this.pageIds;
        return ids.length > 0 && ids.every((id) => this.selected.includes(id));
    },
    toggleSelectAllPage() {
        const ids = this.pageIds;
        if (this.allPageSelected) {
            this.selected = this.selected.filter((id) => !ids.includes(id));
        } else {
            for (const id of ids) if (!this.selected.includes(id)) this.selected.push(id);
        }
    },
    clearSelection() { this.selected = []; },

    async bulkTrash() {
        if (!this.selected.length) return;
        if (!await this.$store.confirm.ask(this.config.trashConfirm)) return;
        await this.trashIds([...this.selected]);
        this.selected = [];
    },
    async bulkRestore() {
        if (!this.selected.length) return;
        await this.restoreIds([...this.selected]);
        this.selected = [];
    },
    async bulkPushBack() {
        if (!this.selected.length || this.bulkBusy) return;
        if (!await this.$store.confirm.ask(this.config.pushConfirmMsg)) return;
        this.bulkBusy = true;
        this.progress = 0;
        this.progressTotal = this.selected.length;
        let ok = 0;
        const rows = this.selected.map((id) => this.cache.find((r) => r.id === id)).filter(Boolean);
        await this._pool(rows, 3, async (r) => {
            try {
                const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', r.id));
                const bytes = await window.Vault.decryptMailBlob(r.sealedKey, buffer);
                await postForm(this.config.pushbackBase.replace('__id__', r.id), { raw_b64: bytesToB64(bytes), folder: r.folder });
                ok++;
            } catch { /* skip one */ }
            this.progress++;
        });
        this.bulkBusy = false;
        this.selected = [];
        window.llToast?.(this.config.pushBulkDone.replace(':n', String(ok)));
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
