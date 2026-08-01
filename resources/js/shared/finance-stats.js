// Pure finance analytics over the (already-decrypted) invoice records. All client-side
// and zero-knowledge — the server never sees invoice contents. Used by the Finance
// dashboard (VAT advance return) and the Statistics tab (revenue by customer, growth).

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
    let subtotal = 0;
    for (const l of (inv.lines || [])) subtotal += (Number(l.qty) || 0) * (Number(l.unitPrice) || 0);
    const disc = inv.discount || null;
    let frac = 0;
    if (disc && Number(disc.value) > 0 && subtotal > 0) {
        frac = disc.type === 'amount' ? Math.min(1, Number(disc.value) / subtotal) : Math.min(1, Number(disc.value) / 100);
    }
    let net = 0; const vatByRate = {};
    for (const l of (inv.lines || [])) {
        const lineNet = (Number(l.qty) || 0) * (Number(l.unitPrice) || 0) * (1 - frac);
        net += lineNet;
        const r = Number(l.vatRate) || 0;
        vatByRate[r] = (vatByRate[r] || 0) + lineNet * r / 100;
    }
    const vat = Object.values(vatByRate).reduce((a, b) => a + b, 0);
    return { net: round2(net), vat: round2(vat), gross: round2(net + vat), vatByRate, subtotal: round2(subtotal), discountAmount: round2(subtotal * frac) };
}

/** Invoices that count as revenue: issued (final, sent or paid) and not trashed. */
export function realizedInvoices(invoices) {
    return (invoices || []).filter((i) => ! i.trashed && (i.status === 'paid' || i.status === 'sent' || i.status === 'final'));
}

/** The date an invoice is booked under a given VAT scheme. Ist (cash) → payment date; Soll → issue date. */
function taxDate(inv, ist) {
    if (ist) return inv.paidAt || inv.issueDate || '';
    return inv.issueDate || '';
}

/**
 * VAT advance return (Umsatzsteuer-Voranmeldung) figures for a year: net turnover and
 * VAT owed, broken down by rate and by quarter.
 *
 * scheme: 'ist' (Ist-Versteuerung, cash-basis — VAT due on PAYMENT, only paid invoices,
 * booked to the payment date) or 'soll' (Soll-Versteuerung, accrual — VAT due on issue,
 * every issued/final/sent/paid invoice, booked to the issue date). Default 'ist'.
 */
export function vatReturn(invoices, year, scheme = 'ist') {
    const ist = scheme !== 'soll';
    const base = ist
        ? (invoices || []).filter((i) => ! i.trashed && i.status === 'paid')
        : realizedInvoices(invoices);
    const list = base.filter((i) => Number((taxDate(i, ist) || '').slice(0, 4)) === year);
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
        const mon = Number((taxDate(inv, ist) || '').slice(5, 7));
        const q = Math.ceil(mon / 3);
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

/**
 * EÜR (Einnahmen-Überschuss-Rechnung) for a year — a cash-basis income/expense report.
 * Income = PAID invoices (cash basis; year from paidAt, falling back to issueDate).
 * Expenses = receipts (attached to bank transactions) + manual project expenses, grouped
 * by category. Net/VAT split derived from each receipt's detected VAT rate; manual project
 * expenses are treated as net (no VAT tracked). Pure + zero-knowledge (client-side).
 */
export function euerReport(invoices, transactions, projects, year) {
    const y = String(year);
    let incomeNet = 0, incomeVat = 0;
    const incomeMonths = Array.from({ length: 12 }, () => 0);
    for (const inv of (invoices || [])) {
        if (inv.trashed || inv.status !== 'paid') continue;
        const paidYear = String(inv.paidAt || inv.issueDate || '').slice(0, 4);
        if (paidYear !== y) continue;
        const t = invoiceTotals(inv);
        incomeNet = round2(incomeNet + t.net); incomeVat = round2(incomeVat + t.vat);
        const m = (parseInt(String(inv.paidAt || inv.issueDate || '').slice(5, 7), 10) || 1) - 1;
        incomeMonths[m] = round2(incomeMonths[m] + t.net);
    }
    const byCat = {}; let expNet = 0, expVat = 0;
    const expMonths = Array.from({ length: 12 }, () => 0);
    const add = (cat, gross, rate, month) => {
        const vat = rate ? round2(gross * rate / (100 + rate)) : 0;
        const net = round2(gross - vat);
        expNet = round2(expNet + net); expVat = round2(expVat + vat);
        const k = cat || '—';
        (byCat[k] ||= { category: k, net: 0, vat: 0, gross: 0 });
        byCat[k].net = round2(byCat[k].net + net); byCat[k].vat = round2(byCat[k].vat + vat); byCat[k].gross = round2(byCat[k].gross + gross);
        if (month >= 0 && month < 12) expMonths[month] = round2(expMonths[month] + net);
    };
    for (const tx of (transactions || [])) {
        for (const r of (tx.receipts || [])) {
            if (r.trashed) continue;
            const dt = String(r.date || tx.date || '');
            if (dt.slice(0, 4) !== y) continue;
            const gross = Number(r.total) || 0; if (! gross) continue;
            const rate = Number(r.vat) || 0;
            const cats = Array.isArray(r.categories) ? r.categories : (r.category ? [r.category] : []);
            add(cats[0] || '', gross, rate, (parseInt(dt.slice(5, 7), 10) || 1) - 1);
        }
    }
    for (const p of (projects || [])) {
        for (const ex of (p.expenses || [])) {
            const dt = String(ex.date || '');
            if (dt.slice(0, 4) !== y) continue;
            const gross = Number(ex.amount) || 0; if (! gross) continue;
            add(ex.category || '', gross, 0, (parseInt(dt.slice(5, 7), 10) || 1) - 1);
        }
    }
    return {
        year: y,
        incomeNet, incomeVat, expNet, expVat,
        surplus: round2(incomeNet - expNet),
        vatPayable: round2(incomeVat - expVat),
        byCategory: Object.values(byCat).sort((a, b) => b.net - a.net),
        months: Array.from({ length: 12 }, (_, i) => ({ m: i + 1, income: incomeMonths[i], expense: expMonths[i], surplus: round2(incomeMonths[i] - expMonths[i]) })),
    };
}
