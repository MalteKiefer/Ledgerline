// Payment methods (bank accounts, cards, PayPal, cash, …) for the Finance module.
// Pure, testable helpers — all rendering/masking logic lives here so the component
// and its Blade stay thin. Records are plaintext-relational rows served over the
// /finance/* REST endpoints.

// The supported types, each with its monochrome icon and tint (iOS palette).
export const PAYMENT_TYPES = [
    { type: 'bank', icon: 'building-library', tint: '#3b9fd6' },
    { type: 'card', icon: 'credit-card', tint: '#7066f5' },
    { type: 'paypal', icon: 'globe-alt', tint: '#3fae9f' },
    { type: 'cash', icon: 'banknotes', tint: '#59ad6b' },
    { type: 'other', icon: 'wallet', tint: '#6b7280' },
];

const TYPE_MAP = Object.fromEntries(PAYMENT_TYPES.map((t) => [t.type, t]));

export function paymentIcon(type) { return (TYPE_MAP[type] || TYPE_MAP.other).icon; }
export function paymentTint(type) { return (TYPE_MAP[type] || TYPE_MAP.other).tint; }
export function isPaymentType(type) { return Object.prototype.hasOwnProperty.call(TYPE_MAP, type); }

/** A blank record for a given type (falls back to 'other'). */
export function blankPaymentMethod(type = 'bank') {
    return {
        type: isPaymentType(type) ? type : 'other',
        label: '', holder: '',
        iban: '', bic: '', bankName: '', accountNumber: '', url: '',
        cardNetwork: 'visa', cardNumber: '', cardExpiry: '',
        email: '', note: '', trashed: false,
    };
}

/** Normalise an IBAN: uppercase, strip everything but A-Z0-9. */
export function normalizeIban(iban) {
    return String(iban || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
}

/** Group an IBAN into blocks of four for display: "DE89370400440532013000". */
export function formatIban(iban) {
    return normalizeIban(iban).replace(/(.{4})/g, '$1 ').trim();
}

/** Last four significant characters of an IBAN/account number, or ''. */
export function last4(value) {
    const s = String(value || '').replace(/\s/g, '');
    return s.length >= 4 ? s.slice(-4) : s;
}

/** A masked IBAN keeping the country prefix and last four: "DE89 •••• •••• 3000". */
export function maskIban(iban) {
    const n = normalizeIban(iban);
    if (! n) return '';
    if (n.length <= 8) return formatIban(n);
    const head = n.slice(0, 4);
    const tail = n.slice(-4);
    const midGroups = Math.max(1, Math.ceil((n.length - 8) / 4));
    return [head, ...Array(midGroups).fill('••••'), tail].join(' ');
}

/** Digits only from a card number. */
export function cardDigits(number) { return String(number || '').replace(/\D/g, ''); }

/** A masked card number keeping only the last four: "•••• •••• •••• 1234". */
export function maskCard(number) {
    const d = cardDigits(number);
    if (! d) return '';
    const tail = d.slice(-4);
    return `•••• •••• •••• ${tail}`;
}

/** Guess the card network from the number's prefix (visa/mastercard/amex/other). */
export function cardNetworkOf(number) {
    const d = cardDigits(number);
    if (/^4/.test(d)) return 'visa';
    if (/^(5[1-5]|2[2-7])/.test(d)) return 'mastercard';
    if (/^3[47]/.test(d)) return 'amex';
    return 'other';
}

/** The secondary line shown under a payment method's label in the list. */
export function paymentSubtitle(pm) {
    if (! pm) return '';
    switch (pm.type) {
        case 'bank': return pm.iban ? maskIban(pm.iban) : (pm.bankName || pm.accountNumber || '');
        case 'card': {
            const net = pm.cardNetwork ? pm.cardNetwork.replace(/^\w/, (c) => c.toUpperCase()) : '';
            const masked = pm.cardNumber ? maskCard(pm.cardNumber) : '';
            return [net, masked].filter(Boolean).join(' · ');
        }
        case 'paypal': return pm.email || '';
        case 'cash': return '';
        default: return pm.note || '';
    }
}

/** True if a record has the minimum fields to be worth saving. */
export function isValidPaymentMethod(pm) {
    if (! pm || ! isPaymentType(pm.type)) return false;
    if (! String(pm.label || '').trim()) return false;
    switch (pm.type) {
        case 'bank': return !! (String(pm.iban || '').trim() || String(pm.accountNumber || '').trim());
        case 'card': return !! String(pm.cardNumber || '').trim();
        case 'paypal': return !! String(pm.email || '').trim();
        default: return true; // cash / other only need a label
    }
}

/** Active (non-trashed) payment methods, banks first then cards, by label. */
export function sortedPaymentMethods(list) {
    const order = { bank: 0, card: 1, paypal: 2, cash: 3, other: 4 };
    return (list || []).filter((p) => ! p.trashed)
        .sort((a, b) => (order[a.type] ?? 9) - (order[b.type] ?? 9) || String(a.label || '').localeCompare(String(b.label || '')));
}
