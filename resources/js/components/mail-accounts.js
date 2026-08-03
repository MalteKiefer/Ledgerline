import { getJson, postForm } from '../shared/api';
import { parseTags, addTags, removeTagFrom, popTag } from '../shared/tag-chips';

// Mail archive settings (accounts + sync status only): IMAP account CRUD +
// on-demand "sync now" + a polled status/progress indicator. There is
// deliberately NO message-reading UI here — the account password is the
// only plaintext secret involved, and it never round-trips back from the
// server (create/update responses simply omit the key; see
// Api\MailAccountController::present()).
//
// `config` carries the route URLs (built server-side via Blade `route()`, with
// `__id__` as a stand-in for the account id on per-account endpoints — the
// same convention as `backupRuns`/`paperlessSettings`) plus every label the
// component needs, so no string lives duplicated in JS and Blade.
export default (config) => ({
    config,
    accounts: [],
    loading: true,
    error: '',

    modalOpen: false,
    form: null, // { id, name, host, port, username, password, encryption, backfill_since, enabled }
    saving: false,
    saveError: '',

    // Folders are edited as removable badge chips via <x-tag-field>, which
    // reads/writes these exact five members on the PARENT Alpine scope (see
    // resources/views/components/tag-field.blade.php) — `tagsValue` stays the
    // comma-joined source of truth, same convention as every other module's
    // tag field (shared/tag-chips.js).
    tagsValue: '',
    tagDraft: '',
    tagList() { return parseTags(this.tagsValue); },
    commitTag() { this.tagsValue = addTags(this.tagsValue, this.tagDraft); this.tagDraft = ''; },
    onTagInput() { if ((this.tagDraft || '').includes(',')) this.commitTag(); },
    tagBackspace() { if ((this.tagDraft || '') === '') this.tagsValue = popTag(this.tagsValue); },
    removeTag(tag) { this.tagsValue = removeTagFrom(this.tagsValue, tag); },

    _timer: null,

    init() {
        this.load();
        // Poll while any account is mid-sync so the status/count update without
        // a page reload (same "poll while something is running" shape as
        // backupRuns, just scoped to /status per syncing account).
        this._timer = setInterval(() => {
            if (! document.hidden && this.accounts.some((a) => a.status === 'syncing')) this.pollSyncing();
        }, 4000);
    },

    async load() {
        this.loading = true;
        this.error = '';
        try {
            const data = await getJson(this.config.accountsUrl);
            this.accounts = data.accounts ?? [];
        } catch (e) {
            this.error = this.config.loadFailed;
        } finally {
            this.loading = false;
        }
    },

    async pollSyncing() {
        const syncing = this.accounts.filter((a) => a.status === 'syncing');
        await Promise.all(syncing.map(async (a) => {
            try {
                const s = await getJson(this.config.statusBase.replace('__id__', a.id));
                a.status = s.status;
                a.last_error = s.last_error;
                a.last_synced_at = s.last_synced_at;
                a.message_count = s.message_count;
            } catch (e) { /* transient — the next tick retries */ }
        }));
    },

    openCreate() {
        this.form = { id: null, name: '', host: '', port: 993, username: '', password: '', encryption: 'ssl', backfill_since: '', enabled: true };
        this.tagsValue = '';
        this.tagDraft = '';
        this.saveError = '';
        this.modalOpen = true;
    },

    openEdit(a) {
        this.form = {
            id: a.id, name: a.name, host: a.host, port: a.port, username: a.username,
            password: '', encryption: a.encryption, backfill_since: a.backfill_since || '', enabled: !! a.enabled,
        };
        this.tagsValue = (a.folders ?? []).join(', ');
        this.tagDraft = '';
        this.saveError = '';
        this.modalOpen = true;
    },

    closeModal() {
        this.modalOpen = false;
        this.form = null;
    },

    async save() {
        if (! this.form || this.saving) return;
        this.saving = true;
        this.saveError = '';
        const body = {
            name: this.form.name,
            host: this.form.host,
            port: Number(this.form.port) || 0,
            username: this.form.username,
            password: this.form.password,
            encryption: this.form.encryption,
            folders: this.tagList(),
            backfill_since: this.form.backfill_since || null,
            enabled: !! this.form.enabled,
        };
        try {
            if (this.form.id) {
                await postForm(this.config.updateBase.replace('__id__', this.form.id), body, 'PUT');
            } else {
                await postForm(this.config.accountsUrl, body, 'POST');
            }
            this.closeModal();
            await this.load();
        } catch (e) {
            this.saveError = this.config.saveFailed;
        } finally {
            this.saving = false;
        }
    },

    async remove(a) {
        if (! await this.$store.confirm.ask(this.config.deleteConfirm)) return;
        try {
            await postForm(this.config.deleteBase.replace('__id__', a.id), null, 'DELETE');
            this.accounts = this.accounts.filter((x) => x.id !== a.id);
        } catch (e) {
            this.error = this.config.deleteFailed;
        }
    },

    async syncNow(a) {
        if (a.status === 'syncing') return;
        const prev = a.status;
        a.status = 'syncing'; // optimistic — the next poll tick reconciles with the real state
        try {
            await postForm(this.config.syncBase.replace('__id__', a.id), null, 'POST');
        } catch (e) {
            a.status = prev;
            this.error = this.config.syncFailed;
            return;
        }
        this.pollSyncing();
    },

    // Tint per status, matching the app-wide `.ll-chip { background: <tint> }`
    // convention (object `:style` binding — never a raw string one).
    statusTint(a) {
        if (a.status === 'error') return '#ef4444';
        if (a.status === 'syncing') return '#d9a441';

        return '#3b9fd6';
    },

    lastSyncedLabel(a) {
        if (! a.last_synced_at) return this.config.neverSynced;

        return this.config.lastSynced.replace(':when', new Date(a.last_synced_at).toLocaleString());
    },

    messageCountLabel(a) {
        return this.config.messageCount.replace(':count', String(a.message_count ?? 0));
    },
});
