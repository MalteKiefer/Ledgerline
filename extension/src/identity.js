// Canonical identity field definitions for the extension.
// Single source of truth: background.js projection, popup.js labels, and
// content.js autocomplete map (IDENTITY_AC) + personalAc set all derive from here.

// Ordered list of identity field keys (mirrors passwords.js TYPES.identity fields
// on the app side — kept in sync manually).
export const IDENTITY_FIELDS = [
    'firstName', 'lastName', 'email', 'phone', 'company',
    'street', 'city', 'state', 'zip', 'country',
];

// Maps HTML autocomplete tokens to identity field keys.
// Used by content.js fillIdentity() and hasPersonalInfoField().
export const IDENTITY_AC = {
    'given-name': 'firstName',
    'family-name': 'lastName',
    'email': 'email',
    'tel': 'phone',
    'organization': 'company',
    'street-address': 'street',
    'address-level2': 'city',
    'address-level1': 'state',
    'postal-code': 'zip',
    'country': 'country',
    'country-name': 'country',
};

// Set of autocomplete tokens that identify a personal-info field.
// Derived from IDENTITY_AC keys so it stays in sync automatically.
export const personalAc = new Set(Object.keys(IDENTITY_AC));

// Label map for the detail view in popup.js.
export const IDENTITY_LABELS = [
    ['firstName', 'First name'],
    ['lastName', 'Last name'],
    ['email', 'Email'],
    ['phone', 'Phone'],
    ['company', 'Company'],
    ['street', 'Street'],
    ['city', 'City'],
    ['state', 'State'],
    ['zip', 'ZIP'],
    ['country', 'Country'],
];

// Name/id/placeholder heuristics used when autocomplete is absent or generic.
export const IDENTITY_HAY = [
    { field: 'firstName', re: /\bfirst.?name\b|\bvorname\b|\bfname\b|\bgiven.?name\b/ },
    { field: 'lastName', re: /\blast.?name\b|\bnachname\b|\bfamily.?name\b|\bsurname\b|\blname\b/ },
    { field: 'email', re: /\bemail\b|\be-?mail\b/ },
    { field: 'phone', re: /\bphone\b|\btel\b|\bmobile\b|\bhandy\b|\btelefon\b/ },
    { field: 'company', re: /\bcompany\b|\borgani[sz]ation\b|\bfirma\b|\bunternehmen\b/ },
    { field: 'street', re: /\bstreet\b|\bstra(ß|ss)e\b|\baddress.?1\b|\baddr\b|\banschrift\b/ },
    { field: 'city', re: /\bcity\b|\bort\b|\bstadt\b|\btown\b/ },
    { field: 'state', re: /\bstate\b|\bprovince\b|\bregion\b|\bbundesland\b/ },
    { field: 'zip', re: /\bzip\b|\bpostal\b|\bpostcode\b|\bplz\b/ },
    { field: 'country', re: /\bcountry\b|\bland\b/ },
];
