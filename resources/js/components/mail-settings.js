// Per-user mail-archive display settings: whether to load remote content
// (tracking pixels / external images) and whether to run mail scripts. Both
// default OFF. Persists via the shared /preferences endpoint and updates the
// live prefs cache so the open archive picks up the change without a reload.

import { prefs, setPrefs } from '../shared/prefs.js';
import { postForm } from '../shared/api.js';

export default (config) => ({
    remote: prefs().mail_remote === true,
    scripts: prefs().mail_scripts === true,
    saving: false,
    saved: false,
    error: '',

    async save() {
        this.saving = true;
        this.saved = false;
        this.error = '';
        try {
            await postForm(config.url, { mail_remote: this.remote, mail_scripts: this.scripts });
            setPrefs({ mail_remote: this.remote, mail_scripts: this.scripts });
            this.saved = true;
        } catch {
            this.error = config.failed;
        } finally {
            this.saving = false;
        }
    },
});
