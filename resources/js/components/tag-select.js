// Autocomplete multi-select for server-rendered admin forms. Reusable for both
// "add groups to a user" and "add users to a group": options are [{id, label, sub}],
// the selection is a list of ids, and one hidden <input name="…[]"> is emitted per
// selected id so it posts as a normal array. Pure UI — no fetch, options are handed
// in from Blade (the workspace is small enough to render the full list).
export default function tagSelect(config = {}) {
    return {
        options: config.options || [],
        selected: (config.selected || []).map((v) => String(v)),
        name: config.name || 'ids[]',
        q: '',
        open: false,

        get chosen() {
            return this.selected
                .map((id) => this.options.find((o) => String(o.id) === id))
                .filter(Boolean);
        },
        get matches() {
            const q = this.q.trim().toLowerCase();
            return this.options.filter((o) => {
                if (this.selected.includes(String(o.id))) return false;
                if (! q) return true;
                return (String(o.label) + ' ' + String(o.sub || '')).toLowerCase().includes(q);
            });
        },
        add(id) {
            id = String(id);
            if (! this.selected.includes(id)) this.selected.push(id);
            this.q = '';
            this.open = false;
        },
        remove(id) {
            this.selected = this.selected.filter((x) => x !== String(id));
        },
    };
}
