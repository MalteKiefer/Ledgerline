// Pure finance analytics over the invoice records. All client-side, computed from the
// records already in memory. Used by the Finance dashboard (VAT advance return) and
// the Statistics tab (revenue by customer, growth).

const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
const yearOf = (inv) => parseInt(String(inv.issueDate || '').slice(0, 4), 10);
const monthOf = (inv) => parseInt(String(inv.issueDate || '').slice(5, 7), 10) || 0;

/**
 * Net / VAT (grouped by rate) / gross for a single invoice. An IMPORTED invoice carries the
 * EXACT printed gross (`inv.gross`) — trust it verbatim (derive net/vat by subtracting the VAT
 * out of the gross) so a round-trip through the synthetic net line can't shift it by a cent.
 */
export function invoiceTotals(inv) {
    if (inv && inv.imported && Number.isFinite(Number(inv.gross))) {
        const rate = Number(inv.vatRate) || 0;
        const gross = round2(Number(inv.gross));
        const vat = round2(gross * rate / (100 + rate));
        const net = round2(gross - vat);
        return { net, vat, gross, vatByRate: { [rate]: vat } };
    }
    // Accumulate raw net per VAT rate, then apply a global invoice-level discount
    // proportionally across the rate buckets. MUST stay cent-identical to the PHP
    // FinanceReports::invoiceTotals().
    const rawByRate = {}; let grossNet = 0;
    for (const l of (inv.lines || [])) {
        const lineNet = (Number(l.qty) || 0) * (Number(l.unitPrice) || 0);
        grossNet += lineNet;
        const r = Number(l.vatRate) || 0;
        rawByRate[r] = (rawByRate[r] || 0) + lineNet;
    }
    const discount = discountAmount(inv, grossNet);
    const factor = grossNet !== 0 ? (grossNet - discount) / grossNet : 1;
    const vatByRate = {};
    for (const r of Object.keys(rawByRate)) {
        const netR = rawByRate[r] * factor;
        vatByRate[r] = netR * Number(r) / 100;
    }
    const net = grossNet - discount;
    const vat = Object.values(vatByRate).reduce((a, b) => a + b, 0);
    return { net: round2(net), vat: round2(vat), gross: round2(net + vat), vatByRate };
}

/**
 * The signed global-discount amount on the net taxable base. Positive on a normal
 * invoice (reduces net); on a credit note the base is negative, so the discount is
 * negated to keep the credit an exact reverse. `discountType` = 'percent'|'amount',
 * `discountValue` the percentage / absolute amount. Cent-identical to the PHP
 * FinanceReports::discountAmount().
 */
export function discountAmount(inv, grossNet) {
    const type = inv && inv.discountType;
    const val = Number(inv && inv.discountValue) || 0;
    if (! type || val <= 0 || ! grossNet) return 0;
    let d = type === 'percent' ? grossNet * val / 100 : (grossNet < 0 ? -val : val);
    // Never exceed the base in magnitude, never flip the base's sign.
    if (Math.abs(d) > Math.abs(grossNet)) d = grossNet;
    return d;
}

/**
 * Invoices that count as revenue: issued (final, sent or paid) and not trashed.
 * MUST match the server FinanceReports::realizedInvoices() status set
 * (['final','sent','paid']) cent-for-cent — 'final' (Offen) is an issued invoice
 * per Soll-taxation and its VAT/turnover is already established.
 */
export function realizedInvoices(invoices) {
    return (invoices || []).filter((i) => ! i.trashed && (i.status === 'final' || i.status === 'paid' || i.status === 'sent'));
}

/**
 * VAT advance return (Umsatzsteuer-Voranmeldung) figures for a year: net turnover and
 * VAT owed, broken down by rate and by quarter.
 *
 * `small` = §19 Kleinunternehmer: output VAT is forced to 0 and turnover is booked
 * GROSS into the 0% bucket — cent-identical to FinanceReports::vatReturn()'s
 * smallBusiness() handling so the client card never contradicts the server figure.
 * The caller passes the company's small-business flag (defaults false → VAT-liable).
 */
export function vatReturn(invoices, year, small = false) {
    const list = realizedInvoices(invoices).filter((i) => yearOf(i) === year);
    const quarters = [1, 2, 3, 4].map((q) => ({ q, net: 0, vat: 0 }));
    const byRate = {}; // rate -> { rate, net, vat }
    let net = 0, vat = 0;
    for (const inv of list) {
        const t = invoiceTotals(inv);
        // KU: turnover = gross, no VAT, everything falls in the 0% bucket.
        const rowNet = small ? t.gross : t.net;
        net += rowNet; vat += small ? 0 : t.vat;
        if (small) {
            byRate[0] = byRate[0] || { rate: 0, net: 0, vat: 0 };
            byRate[0].net += rowNet;
        } else {
            for (const [r, v] of Object.entries(t.vatByRate)) {
                const rate = Number(r);
                byRate[rate] = byRate[rate] || { rate, net: 0, vat: 0 };
                byRate[rate].vat += v;
                byRate[rate].net += rate > 0 ? v / (rate / 100) : t.net;
            }
        }
        const q = Math.ceil(monthOf(inv) / 3);
        if (q >= 1 && q <= 4) { quarters[q - 1].net += rowNet; quarters[q - 1].vat += small ? 0 : t.vat; }
    }
    return {
        year,
        net: round2(net),
        vat: round2(vat),
        gross: round2(net + vat),
        count: list.length,
        byRate: Object.values(byRate).map((b) => ({ rate: b.rate, net: round2(b.net), vat: round2(b.vat) })).sort((a, b) => a.rate - b.rate),
        quarters: quarters.map((q) => ({ q: q.q, net: round2(q.net), vat: round2(q.vat) })),
    };
}

/** Revenue (net) per customer for a year, highest first. */
export function revenueByCustomer(invoices, year) {
    const map = {};
    for (const inv of realizedInvoices(invoices).filter((i) => yearOf(i) === year)) {
        const name = (inv.customer && inv.customer.name) || '—';
        const t = invoiceTotals(inv);
        map[name] = map[name] || { name, net: 0, gross: 0, count: 0 };
        map[name].net += t.net; map[name].gross += t.gross; map[name].count++;
    }
    return Object.values(map).map((c) => ({ ...c, net: round2(c.net), gross: round2(c.gross) })).sort((a, b) => b.net - a.net);
}

/** Net revenue per calendar month (index 0 = January) for a year. */
export function monthlyRevenue(invoices, year) {
    const months = Array.from({ length: 12 }, (_, i) => ({ month: i + 1, net: 0 }));
    for (const inv of realizedInvoices(invoices).filter((i) => yearOf(i) === year)) {
        const m = monthOf(inv);
        if (m >= 1 && m <= 12) months[m - 1].net += invoiceTotals(inv).net;
    }
    return months.map((m) => ({ ...m, net: round2(m.net) }));
}

/** Headline KPIs for a year, incl. year-over-year growth vs the previous year. */
export function yearKpis(invoices, year) {
    const list = realizedInvoices(invoices).filter((i) => yearOf(i) === year);
    const net = round2(list.reduce((s, i) => s + invoiceTotals(i).net, 0));
    const prevNet = round2(realizedInvoices(invoices).filter((i) => yearOf(i) === year - 1).reduce((s, i) => s + invoiceTotals(i).net, 0));
    const customers = new Set(list.map((i) => (i.customer && i.customer.name) || '—')).size;
    return {
        year,
        net,
        count: list.length,
        avg: list.length ? round2(net / list.length) : 0,
        customers,
        prevNet,
        growthPct: prevNet > 0 ? round2((net - prevNet) / prevNet * 100) : null,
    };
}

/** Split a gross amount into net + VAT for a given VAT rate (percent). */
export function grossToNetVat(gross, ratePercent) {
    const g = Number(gross) || 0;
    const r = Number(ratePercent) || 0;
    const net = r > 0 ? g / (1 + r / 100) : g;
    return { net: round2(net), vat: round2(g - net) };
}

/**
 * VAT summary of an account's transactions by category, for the USt calculation.
 * Income (amount > 0) and expenses (amount < 0) are grouped by VAT rate; 'private'
 * bookings (deposits/withdrawals) and undecided ('') rows are reported separately and
 * excluded from the VAT totals. `outputVat` = VAT on income, `inputVat` = VAT on
 * expenses (Vorsteuer); `payable` = output − input.
 */
export function accountVatSummary(transactions) {
    const income = {}, expense = {}; // rate -> { net, vat }
    let privateSum = 0, undecided = 0, outputVat = 0, inputVat = 0;
    for (const tx of transactions || []) {
        const cat = tx.vatCat || '';
        const gross = Math.abs(Number(tx.amount) || 0);
        if (cat === 'private') { privateSum += gross; continue; }
        if (! cat) { undecided++; continue; }
        const { net, vat } = grossToNetVat(gross, cat);
        const bucket = (tx.amount || 0) >= 0 ? income : expense;
        bucket[cat] = bucket[cat] || { net: 0, vat: 0 };
        bucket[cat].net += net; bucket[cat].vat += vat;
        if ((tx.amount || 0) >= 0) outputVat += vat; else inputVat += vat;
    }
    const rows = (map) => Object.entries(map).map(([rate, v]) => ({ rate, net: round2(v.net), vat: round2(v.vat) })).sort((a, b) => Number(b.rate) - Number(a.rate));
    return {
        income: rows(income),
        expense: rows(expense),
        outputVat: round2(outputVat),
        inputVat: round2(inputVat),
        payable: round2(outputVat - inputVat),
        privateSum: round2(privateSum),
        undecided,
    };
}

/** The distinct years with realized invoices, most recent first. */
export function activeYears(invoices) {
    const years = new Set(realizedInvoices(invoices).map(yearOf).filter(Boolean));
    return [...years].sort((a, b) => b - a);
}
