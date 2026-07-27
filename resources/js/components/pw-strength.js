// Password strength meter for server-rendered forms (admin user creation).
// Reuses the lazy zxcvbn estimator shared with the password manager, so the
// heavy dictionaries stay out of the startup bundle and load on first keystroke.
// The five level labels are passed in from Blade (localised) since an Alpine.data
// factory cannot read Blade translation helpers itself.
import { estimateStrength } from '../shared/strength.js';

export default function pwStrength(labels = []) {
    return {
        s: 0,
        shown: false,
        labels,

        async score(pw) {
            this.shown = !! pw;
            if (! pw) {
                this.s = 0;
                return;
            }
            const { score } = await estimateStrength(pw);
            this.s = score;
        },

        get label() {
            return this.labels[this.s] ?? '';
        },
        get pct() {
            return ((this.s + 1) / 5) * 100;
        },
        get color() {
            return ['#dc2626', '#f97316', '#d9a441', '#3b9fd6', '#59ad6b'][this.s] ?? '#6b7280';
        },
    };
}
