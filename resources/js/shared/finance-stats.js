// Pure finance analytics over the (already-decrypted) invoice records. All client-side
// and zero-knowledge — the server never sees invoice contents. Used by the Finance
// dashboard (VAT advance return) and the Statistics tab (revenue by customer, growth).

const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
const yearOf = (inv) => parseInt(String(inv.issueDate || '').slice(0, 4), 10);
const monthOf = (inv) => parseInt(String(inv.issueDate || '').slice(5, 7), 10) || 0;

/** Net / VAT (grouped by rate) / gross for a single invoice, from its line items. */
export function invoiceTotals(inv) {
    let net = 0; const vatByRate = {};
    for (const l of (inv.lines || [])) {
        const lineNet = (Number(l.qty) || 0) * (Number(l.unitPrice) || 0);
        net += lineNet;
        const r = Number(l.vatRate) || 0;
        vatByRate[r] = (vatByRate[r] || 0) + lineNet * r / 100;
    }
    const vat = Object.values(vatByRate).reduce((a, b) => a + b, 0);
    return { net: round2(net), vat: round2(vat), gross: round2(net + vat), vatByRate };
}

/** Invoices that count as revenue: issued (sent or paid) and not trashed. */
export function realizedInvoices(invoices) {
    return (invoices || []).filter((i) => ! i.trashed && (i.status === 'paid' || i.status === 'sent'));
}

/**
 * VAT advance return (Umsatzsteuer-Voranmeldung) figures for a year: net turnover and
 * VAT owed, broken down by rate and by quarter.
 */
export function vatReturn(invoices, year) {
    const list = realizedInvoices(invoices).filter((i) => yearOf(i) === year);
    const quarters = [1, 2, 3, 4].map((q) => ({ q, net: 0, vat: 0 }));
    const byRate = {}; // rate -> { rate, net, vat }
    let net = 0, vat = 0;
    for (const inv of list) {
        const t = invoiceTotals(inv);
        net += t.net; vat += t.vat;
        for (const [r, v] of Object.entries(t.vatByRate)) {
            const rate = Number(r);
            byRate[rate] = byRate[rate] || { rate, net: 0, vat: 0 };
            byRate[rate].vat += v;
            byRate[rate].net += rate > 0 ? v / (rate / 100) : t.net;
        }
        const q = Math.ceil(monthOf(inv) / 3);
        if (q >= 1 && q <= 4) { quarters[q - 1].net += t.net; quarters[q - 1].vat += t.vat; }
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

/** The distinct years with realized invoices, most recent first. */
export function activeYears(invoices) {
    const years = new Set(realizedInvoices(invoices).map(yearOf).filter(Boolean));
    return [...years].sort((a, b) => b - a);
}
