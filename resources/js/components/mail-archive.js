// Mail archive LIST view (Phase 2, slice 1). Zero-knowledge: the server lists
// only the metadata ledger (id / account / folder / size / archived-at /
// sealed_key). This component fetches that page, then for each row pulls the
// sealed blob, decrypts it with the vault identity secret, and parses just the
// envelope (From / To / Subject / Date / has-attachment) CLIENT-side to render
// the table. Nothing about sender/subject/etc ever reaches the server.

import { getJson } from '../shared/api.js';
import { fetchBlobBuffer } from '../shared/blob-io.js';
import { parseEnvelope, parseDate, displayAddress } from '../shared/mime.js';
import { formatDate } from '../shared/dom.js';

// How many blobs to fetch+decrypt at once — bounded so a 50-row page does not
// open 50 simultaneous requests / decrypts.
const DECRYPT_CONCURRENCY = 6;

export default (config) => ({
    config,
    rows: [],
    accounts: {}, // id -> name (mailbox column)
    loading: false,
    error: '',
    page: 1,
    perPage: 50,
    total: 0,
    lastPage: 1,

    get unlocked() {
        return this.$store.vault?.unlocked === true;
    },

    async init() {
        // Reload the list whenever the vault transitions to unlocked.
        this.$watch('unlocked', (v) => { if (v) this.load(); });
        if (this.unlocked) this.load();
    },

    async load() {
        if (!this.unlocked || this.loading) return;
        this.loading = true;
        this.error = '';
        try {
            await this.ensureAccounts();
            const url = `${this.config.messagesUrl}?page=${this.page}&per_page=${this.perPage}`;
            const res = await getJson(url);
            this.total = res.meta?.total ?? 0;
            this.lastPage = res.meta?.last_page ?? 1;
            this.rows = await this.decryptPage(res.data ?? []);
        } catch (e) {
            this.error = this.config.loadFailed;
        } finally {
            this.loading = false;
        }
    },

    async ensureAccounts() {
        if (Object.keys(this.accounts).length) return;
        try {
            const list = await getJson(this.config.accountsUrl);
            for (const a of list ?? []) this.accounts[a.id] = a.name;
        } catch {
            // Non-fatal: the mailbox column just falls back to the account id.
        }
    },

    // Fetch + decrypt + parse each ledger row, bounded concurrency, order preserved.
    async decryptPage(ledger) {
        const out = new Array(ledger.length);
        let next = 0;
        const worker = async () => {
            while (next < ledger.length) {
                const i = next++;
                out[i] = await this.decryptRow(ledger[i]);
            }
        };
        await Promise.all(Array.from({ length: Math.min(DECRYPT_CONCURRENCY, ledger.length) }, worker));
        return out;
    },

    async decryptRow(m) {
        const base = {
            id: m.id,
            folder: m.folder,
            mailbox: this.accounts[m.account_id] || `#${m.account_id}`,
            archivedAt: m.created_at,
        };
        try {
            const buffer = await fetchBlobBuffer(this.config.rawBase.replace('__id__', m.id));
            const bytes = await window.Vault.decryptMailBlob(m.sealed_key, buffer);
            const env = parseEnvelope(bytes);
            const d = parseDate(env.date);
            return {
                ...base,
                from: displayAddress(env.from) || this.config.unknown,
                to: displayAddress(env.to) || this.config.unknown,
                subject: env.subject || this.config.noSubject,
                date: d ? d.toISOString() : m.created_at,
                dateLabel: formatDate(d ? d.toISOString() : m.created_at, { dateStyle: 'medium', timeStyle: 'short' }),
                hasAttachment: env.hasAttachment,
                ok: true,
            };
        } catch {
            // A single un-decryptable row must not blank the whole table.
            return { ...base, from: '', to: '', subject: this.config.decryptFailed, date: base.archivedAt, dateLabel: formatDate(base.archivedAt), hasAttachment: false, ok: false };
        }
    },

    async goto(p) {
        if (p < 1 || p > this.lastPage || p === this.page) return;
        this.page = p;
        await this.load();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    unlock() {
        this.$dispatch('vault-panel');
    },
});
