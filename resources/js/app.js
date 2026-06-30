import Alpine from 'alpinejs';

/**
 * Type-ahead combobox for selecting a contact's function from the fixed
 * ContactFunction enum. The visible input filters the labels; the submitted
 * value is always one of the enum's backing values (or empty, which the
 * server-side validation rejects). No third-party widget library is used.
 *
 * @param {{value: string, label: string}[]} options
 * @param {string} initial  The pre-selected enum value, if any.
 */
Alpine.data('contactFunctionCombobox', (options, initial = '') => ({
    options,
    open: false,
    selected: initial,
    query: (options.find((option) => option.value === initial) || {}).label || '',

    /** Options matching the current query (by label or value). */
    get filtered() {
        const query = this.query.toLowerCase().trim();

        if (query === '') {
            return this.options;
        }

        return this.options.filter(
            (option) =>
                option.label.toLowerCase().includes(query) ||
                option.value.toLowerCase().includes(query),
        );
    },

    /** Pick an option from the list. */
    choose(option) {
        this.selected = option.value;
        this.query = option.label;
        this.open = false;
    },

    /** Keep the submitted value in sync when the user types an exact label. */
    syncFromQuery() {
        this.open = true;

        const match = this.options.find(
            (option) => option.label.toLowerCase() === this.query.toLowerCase().trim(),
        );

        this.selected = match ? match.value : '';
    },
}));

/**
 * Repeater for a contact's labelled email addresses and phone numbers. Each
 * channel has a free label (suggested via a datalist) and a value. Rows can be
 * added and removed freely; empty rows are stripped server-side.
 *
 * @param {{label: string, value: string}[]} initialEmails
 * @param {{label: string, value: string}[]} initialPhones
 */
Alpine.data('contactChannels', (initialEmails = [], initialPhones = []) => ({
    emails: initialEmails.length
        ? initialEmails.map((row) => ({ ...row }))
        : [{ label: 'Work', value: '' }],
    phones: initialPhones.length
        ? initialPhones.map((row) => ({ ...row }))
        : [{ label: 'Work', value: '' }],

    addEmail() {
        this.emails.push({ label: '', value: '' });
    },

    removeEmail(index) {
        this.emails.splice(index, 1);
    },

    addPhone() {
        this.phones.push({ label: '', value: '' });
    },

    removePhone(index) {
        this.phones.splice(index, 1);
    },
}));

/**
 * Type-ahead combobox for selecting a country from the full ISO 3166 list.
 * Options carry a flag emoji; the submitted value is always an alpha-2 code
 * (or empty). The visible list is capped for rendering performance.
 *
 * @param {{value: string, label: string, flag: string}[]} options
 * @param {string} initial  The pre-selected country code, if any.
 */
Alpine.data('countryCombobox', (options, initial = '') => ({
    options,
    open: false,
    selected: initial,
    query: (options.find((option) => option.value === initial) || {}).label || '',

    get selectedFlag() {
        const option = this.options.find((item) => item.value === this.selected);

        return option ? option.flag : '';
    },

    get filtered() {
        const query = this.query.toLowerCase().trim();

        if (query === '') {
            return this.options.slice(0, 80);
        }

        return this.options
            .filter(
                (option) =>
                    option.label.toLowerCase().includes(query) ||
                    option.value.toLowerCase() === query,
            )
            .slice(0, 80);
    },

    choose(option) {
        this.selected = option.value;
        this.query = option.label;
        this.open = false;
    },

    syncFromQuery() {
        this.open = true;

        const match = this.options.find(
            (option) => option.label.toLowerCase() === this.query.toLowerCase().trim(),
        );

        this.selected = match ? match.value : '';
    },
}));

window.Alpine = Alpine;

Alpine.start();
