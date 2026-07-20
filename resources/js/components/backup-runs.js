import { csrfToken, getJson, postForm } from '../shared/api';

// Live backup run list: loads recent runs as JSON, refreshes after "back up
// now" (no page reload) and polls while any run is still running. Each finished
// run can be expanded to its log or downloaded.
export default (labels = {}) => ({
    runs: [],
    expanded: {},
    pollUntil: 0, // keep polling until this timestamp (covers queue lag + run time)
    _timer: null,
    decrypt: { open: false, id: null },
    // Guided restore + non-destructive verify (dry run).
    restore: { open: false, run: null },
    verifyPass: '',
    verifyBusy: false,
    verifyResult: null, // { ok, message }
    // Per-row actions live in a 3-dot menu, teleported to <body> and positioned
    // by the trigger's rect so the runs table's horizontal scroll can't clip it.
    menuRunId: null,
    menuX: 0,
    menuY: 0,
    get menuRun() { return this.runs.find((r) => r.id === this.menuRunId) || null; },
    toggleMenu(r, ev) {
        if (this.menuRunId === r.id) { this.menuRunId = null; return; }
        const rect = ev.currentTarget.getBoundingClientRect();
        this.menuX = Math.round(rect.right);
        this.menuY = Math.round(rect.bottom + 4);
        this.menuRunId = r.id;
    },
    closeMenu() { this.menuRunId = null; },

    openDecrypt(id) {
        this.decrypt = { open: true, id };
    },
    get decryptAction() {
        return (labels.decryptBase || '').replace('__id__', this.decrypt.id);
    },

    openRestore(r) {
        this.restore = { open: true, run: r };
        this.verifyPass = '';
        this.verifyBusy = false;
        this.verifyResult = null;
    },
    closeRestore() {
        this.restore = { open: false, run: null };
    },
    restoreDecryptAction() {
        return this.restore.run ? (labels.decryptBase || '').replace('__id__', this.restore.run.id) : '';
    },
    restoreDownloadUrl() {
        return this.restore.run ? this.downloadUrl(this.restore.run.id) : '';
    },
    async runVerify() {
        const r = this.restore.run;
        if (! r) return;
        this.verifyBusy = true;
        this.verifyResult = null;
        try {
            const body = new URLSearchParams();
            if (this.verifyPass) body.set('passphrase', this.verifyPass);
            const res = await fetch((labels.verifyBase || '').replace('__id__', r.id), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            });
            const data = res.ok ? await res.json() : { ok: false, message: 'Request failed.' };
            this.verifyResult = { ok: !! data.ok, message: data.message || '' };
            this.load(); // refresh the row's stored verify badge
        } catch (e) {
            this.verifyResult = { ok: false, message: 'Verification could not be started.' };
        } finally {
            this.verifyBusy = false;
        }
    },

    init() {
        this.load();
        // A job was triggered (here or elsewhere): poll for a window so the new
        // run appears and updates even if the queue is slow to pick it up.
        window.addEventListener('backup-ran', () => {
            this.pollUntil = Date.now() + 180000; // 3 min
            this.load();
        });
        // Poll while something is running, or within a post-trigger window.
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

    downloadUrl(id) {
        return labels.downloadBase.replace('__id__', id);
    },

    async cancel(id) {
        // Flip the flag optimistically so the button turns into "cancelling…"
        // right away; the manager stops at its next checkpoint.
        const run = this.runs.find((r) => r.id === id);
        if (run) { run.cancellable = false; run.cancelling = true; }
        try {
            await postForm(labels.cancelBase.replace('__id__', id), null, 'POST');
        } catch (e) { /* poll will reconcile */ }
        this.pollUntil = Date.now() + 60000;
        this.load();
    },
});
