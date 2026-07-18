import { csrfToken } from '../shared/api';

// Paperless settings page: connection test and on-demand cache refresh, both
// over AJAX so the page needn't reload.
export default (config) => ({
    config,
    busy: null,
    testResult: '', testOk: false,
    syncError: '',
    counts: config.counts,

    async test() {
        this.busy = 'test'; this.testResult = ''; this.syncError = '';
        try {
            const res = await fetch(config.testUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ paperless_url: this.$refs.url.value, paperless_token: this.$refs.token.value }),
            });
            const b = await res.json();
            this.testOk = !! b.ok; this.testResult = b.detail || '';
        } catch (e) { this.testOk = false; this.testResult = config.failed; }
        this.busy = null;
    },

    async sync() {
        this.busy = 'sync'; this.syncError = ''; this.testResult = '';
        try {
            const res = await fetch(config.syncUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            });
            const b = await res.json();
            if (b.ok) { this.counts = b.counts; } else { this.syncError = b.detail || config.failed; }
        } catch (e) { this.syncError = config.failed; }
        this.busy = null;
    },
});
