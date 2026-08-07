import { csrfToken, getJson, postForm } from '../shared/api';

// Live backup run list. A run is one timestamped BATCH holding one archive per
// selected source; each archive has its own download / verify / decrypt /
// restore actions, shown in the run's expanded detail row.
export default (labels = {}) => ({
    runs: [],
    expanded: {},
    pollUntil: 0,
    _timer: null,
    // Decrypt modal (carries which run + source archive to decrypt).
    decrypt: { open: false, id: null, source: null },
    // Per-archive verify state, keyed `${runId}:${source}`.
    verify: {}, // { [key]: { pass, busy, result } }

    init() {
        this.load();
        window.addEventListener('backup-ran', () => {
            this.pollUntil = Date.now() + 180000; // 3 min
            this.load();
        });
        this._timer = setInterval(() => {
            if (! document.hidden && (this.anyRunning() || Date.now() < this.pollUntil)) {
                this.load();
            }
        }, 5000);
    },

    anyRunning() {
        return this.runs.some((r) => r.status === 'running');
    },

    async load() {
        try {
            const data = await getJson(labels.runsUrl);
            this.runs = data.runs ?? [];
        } catch (e) { /* keep current on error */ }
    },

    toggle(id) {
        this.expanded[id] = ! this.expanded[id];
    },

    hasArchives(r) {
        return Array.isArray(r.archives) && r.archives.length > 0;
    },

    sourceLabel(source) {
        return (labels.sourceLabels && labels.sourceLabels[source]) || source;
    },

    // --- Download ---
    downloadUrl(id, source) {
        const base = labels.downloadBase.replace('__id__', id);
        return `${base}?source=${encodeURIComponent(source)}`;
    },

    // --- Verify (dry run, per archive) ---
    vkey(id, source) { return `${id}:${source}`; },
    vstate(id, source) {
        const k = this.vkey(id, source);
        if (! this.verify[k]) this.verify[k] = { pass: '', busy: false, result: null };
        return this.verify[k];
    },
    async runVerify(id, source) {
        const st = this.vstate(id, source);
        st.busy = true;
        st.result = null;
        try {
            const body = new URLSearchParams();
            body.set('source', source);
            if (st.pass) body.set('passphrase', st.pass);
            const res = await fetch(labels.verifyBase.replace('__id__', id), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            });
            const data = res.ok ? await res.json() : { ok: false, message: 'Request failed.' };
            st.result = { ok: !! data.ok, message: data.message || '' };
            this.load();
        } catch (e) {
            st.result = { ok: false, message: 'Verification could not be started.' };
        } finally {
            st.busy = false;
        }
    },

    // --- Decrypt (encrypted archive → plaintext download) ---
    openDecrypt(id, source) {
        this.decrypt = { open: true, id, source };
    },
    get decryptAction() {
        return (labels.decryptBase || '').replace('__id__', this.decrypt.id);
    },

    // --- Restore a blob archive (files/invoices) onto live data ---
    async restore(id, source) {
        const ok = await this.$store.confirm.ask(
            (labels.restoreConfirm || 'Restore :source onto live data?').replace(':source', this.sourceLabel(source)),
        );
        if (! ok) return;
        try {
            const res = await fetch(labels.restoreBase.replace('__id__', id), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ source }).toString(),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok) {
                window.llToast?.((labels.restoreDone || 'Restored :count file(s).').replace(':count', data.files ?? 0));
            } else {
                window.llToast?.((labels.restoreFailed || 'Restore failed: :error').replace(':error', data.message || res.status), 'error');
            }
        } catch (e) {
            window.llToast?.((labels.restoreFailed || 'Restore failed: :error').replace(':error', 'network'), 'error');
        }
    },

    async cancel(id) {
        const run = this.runs.find((r) => r.id === id);
        if (run) { run.cancellable = false; run.cancelling = true; }
        try {
            await postForm(labels.cancelBase.replace('__id__', id), null, 'POST');
        } catch (e) { /* poll reconciles */ }
        this.pollUntil = Date.now() + 60000;
        this.load();
    },
});
