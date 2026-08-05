// Mail archive LIST view. Zero-knowledge: the server lists only the
// metadata ledger (id / account / folder / size / archived-at / sealed_key).
// This component pulls the WHOLE ledger for the current account scope, decrypts
// each blob with the vault identity secret, and parses just the envelope
// (From / To / Subject / Date / has-attachment) CLIENT-side. All filtering,
// sorting and pagination then happen locally over that decrypted cache — the
// server never sees sender/subject/etc, so it cannot filter on them for us.

import { getJson, postForm } from '../shared/api.js';
import { fetchBlobBuffer } from '../shared/blob-io.js';
import { parseMessage, parseDate, displayAddress, splitMessage, isSpam, parseAuthResults, rawHeaderBlock } from '../shared/mime.js';
import { formatDate, saveBlobAs } from '../shared/dom.js';
import { mailRemote, mailScripts } from '../shared/prefs.js';
import { loadEnvelopes, putEnvelopes } from '../shared/mail-cache.js';
import { isPgpEncrypted, extractPgpMessage, decrypt as pgpDecrypt } from '../shared/pgp.js';
import { isSmimeEncrypted, decryptSmime } from '../shared/smime.js';
import { padBlob } from '../shared/padme.js';
import { fileSig } from '../shared/file-sig.js';

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
const LEDGER_PAGE = 1000;

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
    fFolder: 'INBOX', // default the archive view to the INBOX folder
    fText: '',
    fFrom: '',
    fTo: '',
    searchBody: true,      // include the message body in text search
    _savingAtt: false,     // an attachment save (to Files/Gallery) is in progress
    _forceRemote: false,   // per-open override to load remote content for this mail
    reindexing: false,     // full-text (content) reindex in progress
    reindexDone: 0,
    reindexTotal: 0,
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

            // Warm the identity-key cache ONCE up front. Every decryptMailBlob
            // needs it; without pre-warming, the concurrent decrypt pool would
            // each call ensureIdentityKeys and — if the very first GET /vaults/keys
            // hits the rate limit — spiral into a 429 storm (no cache to short-
            // circuit the retries). One awaited call fills Vault._idKeys so the
            // 8000 decrypts below all hit the in-memory cache, zero extra fetches.
            if (window.Vault?.unlocked) {
                try { await window.Vault.ensureIdentityKeys(); } catch { /* locked / transient — decrypts will surface it */ }
            }

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
            // Default-to-INBOX only holds if the mailbox actually has an INBOX
            // folder; otherwise fall back to "all folders" so nothing is hidden.
            if (this.fFolder === 'INBOX' && !this.cache.some((r) => r.folder === 'INBOX')) this.fFolder = '';
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
            spam: e.spam === true,
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

    // A searchable plaintext of the message body, for full-text (content) search.
    // Prefers text/plain; strips HTML to text otherwise. Normalised (collapsed
    // whitespace, lower-cased) and capped so the sealed envelope stays bounded.
    _bodyIndex(msg) {
        let t = msg.textBody || '';
        if (!t && msg.htmlBody) t = msg.htmlBody.replace(/<[^>]+>/g, ' ');
        // include attachment filenames in the index too
        const names = (msg.attachments || []).map((a) => a.filename || '').join(' ');
        return `${t} ${names}`.replace(/\s+/g, ' ').trim().slice(0, 20000).toLowerCase();
    },

    // No envelope yet: decrypt the full body ONCE, build the envelope (incl. the
    // searchable body index), and upload it (sealed to our own keys) so future
    // loads / other devices skip the body.
    async _backfill(m) {
        const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', m.id));
        const bytes = await window.Vault.decryptMailBlob(m.sealed_key, buffer);
        const msg = parseMessage(bytes);
        const env = this._envFromParsed(m, msg.envelope);
        env.body = this._bodyIndex(msg);
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

    // How many loaded messages have no body index yet (envelope built before
    // content search existed). Content search only matches indexed messages.
    get unindexedCount() {
        return this.cache.filter((r) => r.ok && !r.hasBody).length;
    },

    // Build the full-text body index for every message that lacks one: decrypt
    // the blob once, extract the searchable body, re-seal the envelope (with the
    // body) and cache it. Bounded concurrency + progress; safe to re-run.
    async reindexContent() {
        if (this.reindexing) return;
        const todo = this.cache.filter((r) => r.ok && !r.hasBody);
        if (!todo.length) return;
        this.reindexing = true;
        this.reindexDone = 0;
        this.reindexTotal = todo.length;
        const fresh = [];
        await this._pool(todo, 4, async (r) => {
            try {
                const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', r.id));
                const bytes = await window.Vault.decryptMailBlob(r.sealedKey, buffer);
                const msg = parseMessage(bytes);
                const env = this._envFromParsed({ id: r.id, created_at: r.archivedAt }, msg.envelope);
                env.body = this._bodyIndex(msg);
                await this._uploadEnvelope(r.id, env).catch(() => {});
                fresh.push(env);
                // live-update the row so search works immediately
                r.body = env.body; r.hasBody = true;
            } catch { /* skip one */ }
            this.reindexDone++;
        });
        if (fresh.length) putEnvelopes(fresh);
        this.reindexing = false;
        window.llToast?.(this.config.reindexDoneMsg.replace(':n', String(fresh.length)));
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
            spam: env.spam === true,
            body: env.body || '',          // searchable body index ('' if not indexed yet)
            hasBody: typeof env.body === 'string',
            ok: true,
        };
    },

    get folders() {
        return [...new Set(this.cache.map((r) => r.folder))].sort();
    },

    // Unread count in the (non-trash) archive — shown in the tab/list header.
    get unreadCount() {
        return this.cache.filter((r) => !r.seen && !r.trashed).length;
    },

    // ---- Read / unread ----
    // Mark the given message ids seen/unseen on the server and in the local cache.
    async setSeen(ids, seen) {
        if (!ids.length) return;
        const set = new Set(ids);
        // Optimistic + in-place: mutate seen on the reactive cache rows so the list's
        // bold/normal :class flips INSTANTLY (no reload, no waiting on the POST). The
        // rows are Alpine-reactive proxies, so an in-place property write re-renders
        // reliably. Revert on server failure.
        const prev = new Map();
        this.cache.forEach((r) => { if (set.has(r.id)) { prev.set(r.id, r.seen); r.seen = seen; } });
        if (this.open && set.has(this.open.id)) this.open.seen = seen;
        try {
            await postForm(this.config.seenBase, { ids, seen: seen ? 1 : 0 });
        } catch {
            this.cache.forEach((r) => { if (prev.has(r.id)) r.seen = prev.get(r.id); });
            if (this.open && prev.has(this.open.id)) this.open.seen = prev.get(this.open.id);
            window.llToast?.(this.config.seenFailed);
        }
    },
    async bulkMarkSeen(seen) {
        if (!this.selected.length) return;
        await this.setSeen([...this.selected], seen);
        this.selected = [];
    },

    get filtered() {
        const q = this.fText.trim().toLowerCase();
        const fromTs = this.fFrom ? Date.parse(this.fFrom) : null;
        const toTs = this.fTo ? Date.parse(this.fTo) + 86_400_000 : null; // inclusive end-of-day
        return this.cache.filter((r) => {
            // The trash is a SINGLE bin across all folders — never filter it by
            // folder (the folder filter only applies to the archive view).
            if (!this.showTrash && this.fFolder && r.folder !== this.fFolder) return false;
            if (q) {
                // Header fields always; body index too when content-search is on.
                const hay = `${r.from} ${r.fromRaw} ${r.to} ${r.subject}`.toLowerCase()
                    + (this.searchBody ? ' ' + (r.body || '') : '');
                if (!hay.includes(q)) return false;
            }
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
    pgp: '',           // '' | 'ok' | 'nokey' | 'fail' — decryption status
    spam: false,
    auth: {},          // { spf, dkim, dmarc }
    rawHead: '',
    showHeaders: false,
    bodyHtml: '',      // sanitized HTML body (inline, default case)
    bodyFrame: '',     // srcdoc for the sandboxed iframe (scripts on, or remote images)
    frameSandbox: '',  // sandbox attribute for the body iframe (scripts vs no-scripts)
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
        // Auto-mark read on open (fire-and-forget; updates cache + server).
        if (!r.seen) this.setSeen([r.id], true);
        this.msg = null;
        this.pgp = '';
        this.spam = false;
        this.auth = {};
        this.rawHead = '';
        this.showHeaders = false;
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
            this._raw = bytes; // ORIGINAL bytes (kept intact for push-back)
            this.msg = parseMessage(bytes);

            // Spam flag + SPF/DKIM/DMARC + raw headers come from the ORIGINAL
            // (outer) headers of the received mail.
            const outer = splitMessage(bytes).headers;
            this.spam = isSpam(outer);
            this.auth = parseAuthResults(outer);
            this.rawHead = rawHeaderBlock(bytes);
            this.showHeaders = false;

            // PGP: if the body is encrypted (inline or PGP/MIME), decrypt it with
            // the vault-sealed private keys and re-parse the plaintext. Headers
            // (from/to/subject) are on the outer message and stay as-is.
            this.pgp = '';
            const rawText = new TextDecoder('latin1').decode(bytes);
            if (isPgpEncrypted(rawText)) {
                const armored = extractPgpMessage(rawText);
                const keys = await this._pgpKeys();
                if (!keys.length) {
                    this.pgp = 'nokey';
                } else if (armored) {
                    try {
                        const { text } = await pgpDecrypt(armored, keys);
                        this.msg = parseMessage(text);
                        this.pgp = 'ok';
                    } catch { this.pgp = 'fail'; }
                }
            } else if (isSmimeEncrypted(rawText)) {
                const keys = await this._smimeKeys();
                if (!keys.length) {
                    this.pgp = 'nokey';
                } else {
                    try {
                        const { text } = await decryptSmime(rawText, keys);
                        this.msg = parseMessage(text);
                        this.pgp = 'ok';
                    } catch { this.pgp = 'fail'; }
                }
            }

            this._forceRemote = false;
            await this._renderBody();
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

    // Attachments shown in the list — inline (cid) images are embedded into the
    // body instead, so they are hidden from the download list.
    get realAttachments() {
        return (this.msg?.attachments || []).filter((a) => !a.inline);
    },

    // Resolve inline cid: image references to data: URIs from the message's own
    // (already decrypted) attachment bytes. These are embedded images (signatures,
    // logos) — NOT remote content — so they render under any CSP via data:.
    _resolveCidImages(html, attachments) {
        if (!html || !/cid:/i.test(html)) return html;
        const map = new Map();
        for (const a of (attachments || [])) {
            if (a.contentId) map.set(a.contentId.toLowerCase(), a);
        }
        if (map.size === 0) return html;
        return html.replace(/src\s*=\s*(["'])cid:([^"']+)\1/gi, (m, q, id) => {
            const key = id.trim().toLowerCase().replace(/^<|>$/g, '');
            const att = map.get(key);
            if (!att || !att.bytes) return m;
            return `src=${q}data:${att.contentType};base64,${bytesToB64(att.bytes)}${q}`;
        });
    },

    // Build the srcdoc for a sandboxed iframe. The iframe has NO allow-same-origin,
    // so it runs in an opaque origin isolated from the app (no cookies/storage/vault).
    // The inner CSP gates remote images on the remote-content choice and scripts on
    // the scripts choice; cid images are already inlined as data: by the caller.
    _buildFrame(html, allowRemote, allowScripts) {
        const imgSrc = allowRemote ? 'img-src https: data:; media-src https: data:; font-src https: data:' : 'img-src data:; font-src data:';
        const scriptSrc = allowScripts ? "script-src 'unsafe-inline' 'unsafe-eval'" : "script-src 'none'";
        const csp = `default-src 'none'; ${imgSrc}; style-src 'unsafe-inline'; ${scriptSrc}`;
        return `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${csp}"><base target="_blank"></head><body style="margin:0;font:14px/1.5 sans-serif;color:#111">${html}</body></html>`;
    },

    // Render the HTML body.
    //  - scripts ON            -> sandboxed iframe WITH allow-scripts (opaque origin)
    //  - remote ON, scripts OFF -> sandboxed iframe WITHOUT scripts (own CSP loads
    //                              remote images; the app CSP would block them inline)
    //  - default                -> DOMPurify inline (external images stripped)
    // cid: inline images are resolved to data: in every case.
    async _renderBody() {
        this.bodyHtml = '';
        this.bodyFrame = '';
        this.frameSandbox = '';
        if (!this.msg?.htmlBody) return;
        const remote = mailRemote() || this._forceRemote;
        const html = this._resolveCidImages(this.msg.htmlBody, this.msg.attachments);
        if (mailScripts()) {
            this.frameSandbox = 'allow-scripts allow-popups allow-popups-to-escape-sandbox';
            this.bodyFrame = this._buildFrame(html, remote, true);
        } else if (remote) {
            // Strip scripts/handlers (belt) then isolate in a scriptless sandbox so
            // remote images load under the iframe's own CSP, not the strict app CSP.
            const clean = await this._sanitizeHtml(html, true);
            this.frameSandbox = 'allow-popups allow-popups-to-escape-sandbox';
            this.bodyFrame = this._buildFrame(clean, true, false);
        } else {
            this.bodyHtml = await this._sanitizeHtml(html, false);
        }
    },

    // Load remote content for the currently open mail only (does not change the
    // global setting).
    get canLoadRemote() {
        return !!(this.open && this.msg?.htmlBody && !mailRemote() && !this._forceRemote);
    },
    async loadRemoteOnce() {
        this._forceRemote = true;
        await this._renderBody();
    },

    // Vault-sealed PGP private keys for decryption ([] if none / locked).
    async _pgpKeys() {
        return (await this._mailKeys())
            .filter((k) => k.type !== 'smime' && k.privateKey)
            .map((k) => ({ privateKey: k.privateKey, passphrase: k.passphrase }));
    },

    async _smimeKeys() {
        return (await this._mailKeys())
            .filter((k) => k.type === 'smime' && k.privateKeyPem && k.certPem)
            .map((k) => ({ privateKeyPem: k.privateKeyPem, certPem: k.certPem }));
    },

    async _mailKeys() {
        const st = window.LLModuleStore?.mailkeys;
        if (!st) return [];
        try { await st.load(); return st.data.keys || []; } catch { return []; }
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

    // Delete the selected messages from their ORIGIN mailbox (destructive
    // write-to-origin). The local immutable archive copy is NOT touched. The
    // server can't read the sealed blob, so we decrypt each message here and
    // send only its (non-secret) Message-Id for the server to locate + delete
    // on the origin. Messages without a Message-Id are skipped.
    async bulkDeleteOrigin() {
        if (!this.selected.length || this.bulkBusy) return;
        if (!await this.$store.confirm.ask(this.config.deleteOriginConfirmMsg)) return;
        this.bulkBusy = true;
        this.progress = 0;
        this.progressTotal = this.selected.length;
        let ok = 0;
        let skipped = 0;
        const rows = this.selected.map((id) => this.cache.find((r) => r.id === id)).filter(Boolean);
        await this._pool(rows, 3, async (r) => {
            try {
                const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', r.id));
                const bytes = await window.Vault.decryptMailBlob(r.sealedKey, buffer);
                const mid = (splitMessage(bytes).headers['message-id'] || '').trim();
                if (!mid) { skipped++; this.progress++; return; }
                const res = await postForm(this.config.deleteOriginBase.replace('__id__', r.id), { message_id: mid, folder: r.folder });
                if ((res?.deleted ?? 0) > 0) ok++; else skipped++;
            } catch { skipped++; }
            this.progress++;
        });
        this.bulkBusy = false;
        this.selected = [];
        window.llToast?.(this.config.deleteBulkDone.replace(':n', String(ok)).replace(':s', String(skipped)));
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

    // ---- Save an attachment into another module ----

    // Whether the "send to Paperless" action applies (PDF + Paperless configured).
    canPaperless(att) {
        return (att.contentType || '').toLowerCase() === 'application/pdf' && !!this.$store.paperless?.configured;
    },

    // Send a PDF attachment to Paperless via the shared transfer dialog (leaves ZK,
    // exactly like the Files/invoice → Paperless flow). The bytes are already
    // decrypted client-side; we just hand the dialog a Blob.
    attachmentToPaperless(att) {
        const store = this.$store.paperless;
        if (!store || !store.configured) return;
        const name = att.filename || 'attachment.pdf';
        store.begin(name, { title: name, created: (this.open?.dateLabel ? undefined : undefined) }, { context: { source: 'mail' } });
        try {
            store.setFile(new Blob([att.bytes], { type: att.contentType || 'application/pdf' }));
        } catch {
            store.fail(this.config.saveFailed);
        }
    },

    // Save an attachment into the personal Files store (zero-knowledge: encrypt in
    // the browser under the personal VK, upload only ciphertext, register the file
    // record at the Files root). Reuses the exact upload contract of the Files
    // module (/files/upload → {id}; padded ciphertext blob). A full reconcile with
    // the COMPLETE live-set follows so the fresh blob is protected from the orphan
    // sweep (a partial reconcile would delete other blobs — never send one).
    async saveAttachmentToFiles(att) {
        if (this._savingAtt) return;
        this._savingAtt = true;
        try {
            if (!window.Vault?.unlocked) { this.unlock(); return; }
            if (!window.LLFilesStore.loaded) await window.LLFilesStore.load();
            if (window.LLFilesStore.degraded) { window.llToast?.(this.config.saveFailed); return; }

            const bytes = att.bytes instanceof Uint8Array ? att.bytes : new Uint8Array(att.bytes);
            const name = att.filename || 'attachment';
            const mime = att.contentType || 'application/octet-stream';
            const enc = window.Vault.encryptContent(bytes, { name, mime });
            const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
            const id = await this._uploadFileBlob(cipher);

            window.LLFilesStore.data.files.push({
                id: crypto.randomUUID(),
                blob: id,
                encFileKey: enc.encFileKey,
                name,
                mime,
                size: bytes.length,
                folder: null, // root
                created: new Date().toISOString(),
                versions: [],
            });
            await window.LLFilesStore.flush();      // durable sealed save
            await this._reconcileFiles();           // protect the fresh blob
            window.llToast?.(this.config.savedToFiles);
        } catch {
            window.llToast?.(this.config.saveFailed);
        } finally {
            this._savingAtt = false;
        }
    },

    _uploadFileBlob(cipherFile) {
        const fd = new FormData();
        fd.append('_token', this.config.csrf);
        fd.append('file', cipherFile, cipherFile.name);
        return fetch(this.config.filesUploadUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        }).then((res) => {
            if (!res.ok) throw new Error('upload failed');
            return res.json();
        }).then((j) => j.id);
    },

    // Whether the "save to Gallery" action applies (image or video attachment).
    canGallery(att) {
        const t = (att.contentType || '').toLowerCase();
        return t.startsWith('image/') || t.startsWith('video/');
    },

    // Save an image/video attachment into the Gallery (zero-knowledge): encrypt
    // the original under the personal VK, upload the ciphertext to /gallery/upload,
    // register a photo record. Thumbnails/medium + ML are derived by the Gallery's
    // own pipeline the next time it opens (the record has no thumbRef → pending),
    // exactly as a deferred (HEIC/video) upload would. A full reconcile protects
    // the fresh blob from the orphan sweep.
    async saveAttachmentToGallery(att) {
        if (this._savingAtt) return;
        this._savingAtt = true;
        try {
            if (!window.Vault?.unlocked) { this.unlock(); return; }
            if (!window.LLGalleryStore.loaded) await window.LLGalleryStore.load();
            if (window.LLGalleryStore.degraded) { window.llToast?.(this.config.saveFailed); return; }

            const bytes = att.bytes instanceof Uint8Array ? att.bytes : new Uint8Array(att.bytes);
            const name = att.filename || 'attachment';
            const mime = att.contentType || 'application/octet-stream';
            const sig = await fileSig(bytes).catch(() => null);
            // Skip if this exact content is already in the library.
            if (sig && window.LLGalleryStore.data.photos.some((p) => p.sig === sig)) {
                window.llToast?.(this.config.savedToGallery);
                return;
            }
            const enc = window.Vault.encryptContent(bytes, { name, mime });
            const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
            const id = await this._uploadGalleryBlob(cipher);

            window.LLGalleryStore.data.photos.unshift({
                id: window.LLGalleryStore.newId(),
                originalRef: id,
                originalKey: enc.encFileKey,
                name,
                mime,
                size: bytes.length,
                media_type: mime.toLowerCase().startsWith('video/') ? 'video' : 'image',
                sig,
                created: new Date().toISOString(),
            });
            await window.LLGalleryStore.flush();
            await this._reconcileGallery();
            window.llToast?.(this.config.savedToGallery);
        } catch {
            window.llToast?.(this.config.saveFailed);
        } finally {
            this._savingAtt = false;
        }
    },

    _uploadGalleryBlob(cipherFile) {
        const fd = new FormData();
        fd.append('_token', this.config.csrf);
        fd.append('file', cipherFile, cipherFile.name);
        return fetch(this.config.galleryUploadUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        }).then((res) => {
            if (!res.ok) throw new Error('upload failed');
            return res.json();
        }).then((j) => j.id);
    },

    // Full Gallery live-set (every photo's blob refs + shard refs) so the fresh
    // blob survives the orphan sweep. Mirrors gallery.js reconcileBlobs.
    async _reconcileGallery() {
        const blobs = [];
        for (const p of window.LLGalleryStore.data.photos) {
            for (const ref of [p.originalRef, p.thumbRef, p.mediumRef, p.motionRef, p.metaRef]) if (ref) blobs.push(ref);
            for (const ref of (p.faceCropRefs || [])) if (ref) blobs.push(ref);
        }
        for (const ref of window.LLGalleryStore.shardRefs()) blobs.push(ref);
        try {
            await fetch(this.config.galleryReconcileUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.config.csrf },
                body: JSON.stringify({ blobs: [...new Set(blobs)], allow_empty: 1 }),
            });
        } catch { /* best effort */ }
    },

    // Report the FULL Files live-set (every file blob + text/emb/version refs +
    // the sealed-store shard refs) so the orphan sweep keeps the just-added blob.
    // Mirrors files.js _reconcileNow — the set MUST be complete or referenced
    // blobs would be reclaimed.
    async _reconcileFiles() {
        const blobs = [];
        for (const f of window.LLFilesStore.data.files) {
            if (f.blob) blobs.push(f.blob);
            if (f.textRef) blobs.push(f.textRef);
            if (f.embRef) blobs.push(f.embRef);
            for (const v of f.versions ?? []) if (v.blob) blobs.push(v.blob);
        }
        for (const ref of window.LLFilesStore.shardRefs()) blobs.push(ref);
        try {
            await fetch(this.config.filesReconcileUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.config.csrf },
                body: JSON.stringify({ blobs: [...new Set(blobs)], allow_empty: 1 }),
            });
        } catch { /* best effort */ }
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
