// Pure heuristics for importing historical invoice PDFs into invoice records.
// Invoices are zero-knowledge, so this runs entirely client-side (pdf.js extracts the
// text; these functions turn it into a draft). The filename carries the most reliable
// signal (date + number + customer); the PDF text supplies the money (net/VAT/gross).
// Two layout families are handled: the older "Rechnungsnr./Rechnungsdatum/Zu zahlen EUR"
// sheet and the newer "R-xxxx / Datum: / Nettobetrag/Gesamtbetrag" sheet. Line items
// vary too much to reconstruct reliably, so the draft carries ONE net-total summary
// line (the totals then reconcile) that the user can refine before saving.

/** Parse a German amount like "1.234,56" / "227,88 €" → 1234.56 (or null). */
export function parseAmount(raw) {
    if (raw == null) return null;
    const m = String(raw).replace(/[^\d.,-]/g, '').match(/-?[\d.]*,?\d*/);
    if (! m || m[0] === '') return null;
    const n = parseFloat(m[0].replace(/\./g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : null;
}

/** "05.02.2022" → "2022-02-05" (ISO); null if not a dd.mm.yyyy date. */
export function parseGermanDate(raw) {
    const m = String(raw || '').match(/(\d{2})\.(\d{2})\.(\d{4})/);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : null;
}

/** Reliable fields from the filename: {date, number, customer}. */
export function parseInvoiceFilename(name) {
    const base = String(name || '').replace(/\.pdf$/i, '').trim();
    const out = { date: null, number: null, customer: null };

    // Leading YYYYMMDD_ → issue date.
    const d = base.match(/^(\d{4})(\d{2})(\d{2})[_\s]/);
    if (d) out.date = `${d[1]}-${d[2]}-${d[3]}`;

    // Invoice number after "Rechnung"/"Rechnungsnr." (R-2024-00001 / R-00124 / 21).
    const num = base.match(/Rechnung(?:snr\.)?\s+(R-\d{4}-\d+|R-\d+|\d+)/i);
    if (num) out.number = num[1];

    // Customer = the trailing " - <name>" segment, minus copy markers (" 2", " (1)").
    const cust = base.match(/\s-\s(.+)$/);
    if (cust) {
        out.customer = cust[1].replace(/\s*\(?\d+\)?$/, '').trim() || cust[1].trim();
    }
    return out;
}

/** Money + dates + VAT from the extracted PDF text. */
export function parseInvoiceText(text) {
    const t = String(text || '').replace(/ /g, ' ');
    const out = { date: null, dueDate: null, number: null, net: null, vat: null, gross: null, vatRate: null, smallBusiness: false, firstDesc: null };

    // Number (fallback when the filename lacks it).
    const num = t.match(/(R-\d{4}-\d+|R-\d{4,})/) || t.match(/Rechnungsnr\.?\s+(\d+)/i);
    if (num) out.number = num[1];

    // Issue date: "Datum: dd.mm.yyyy" (family B) or "Rechnungsdatum dd.mm.yyyy" (A).
    const date = t.match(/(?:Rechnungsdatum|Datum:?)\s+(\d{2}\.\d{2}\.\d{4})/i);
    if (date) out.date = parseGermanDate(date[1]);

    // Due date: explicit label or the "bis zum dd.mm.yyyy" sentence.
    const due = t.match(/F[äa]lligkeitsdatum\s+(\d{2}\.\d{2}\.\d{4})/i) || t.match(/bis zum\s+(\d{2}\.\d{2}\.\d{4})/i);
    if (due) out.dueDate = parseGermanDate(due[1]);

    // Date fallback (column-separated layouts where the label isn't adjacent): the
    // first dd.mm.yyyy in the document — the invoice date sits near the top/number, so
    // the earliest date is almost always it (period/due dates come later).
    if (! out.date) {
        const first = t.match(/(\d{2}\.\d{2}\.\d{4})/);
        if (first) out.date = parseGermanDate(first[1]);
    }

    // Small-business (Kleinunternehmer, §19 UStG) → no VAT.
    if (/§\s*19|Kleinunternehmer|keine Umsatzsteuer/i.test(t)) out.smallBusiness = true;

    // VAT rate from "19% MwSt" / "USt. 19%" / "Umsatzsteuer 19%".
    const rate = t.match(/(\d{1,2})\s*%\s*(?:MwSt|USt|Umsatzsteuer)/i) || t.match(/(?:USt|Umsatzsteuer)\.?\s+(\d{1,2})\s*%/i);
    out.vatRate = out.smallBusiness ? 0 : (rate ? parseInt(rate[1], 10) : null);

    // Totals — first try label-adjacent amounts (works when the amount follows its
    // label in reading order): Nettobetrag/Nettogesamt/Zwischensumme … / MwSt/USt/
    // Umsatzsteuer … / Gesamtbetrag/Rechnungsbetrag/Gesamt EUR/Zu zahlen EUR.
    let m = t.match(/(?:Nettobetrag|Nettogesamt|Zwischensumme(?: ohne USt\.?)?):?\s*€?\s*([\d.,]+)/i);
    if (m) out.net = parseAmount(m[1]);
    m = t.match(/(?:zzgl\.?\s*\d{1,2}%\s*MwSt\.?|MwSt\.?|Umsatzsteuer(?:\s*\d{1,2}%)?|USt\.?\s*\d{1,2}%\s*von\s*[\d.,]+):?\s*€?\s*([\d.,]+)/i);
    if (m) out.vat = parseAmount(m[1]);
    m = t.match(/(?:Gesamtbetrag|Rechnungsbetrag|Gesamt\s+EUR|Zu zahlen EUR):?\s*€?\s*([\d.,]+)/i);
    if (m) out.gross = parseAmount(m[1]);

    const amts = [...t.matchAll(/(\d{1,3}(?:\.\d{3})*,\d{2})/g)].map((x) => parseAmount(x[1])).filter((n) => n != null);

    if (out.smallBusiness) {
        // No VAT: the gross is the last money amount (Gesamt/Zu zahlen) and net = gross.
        out.vat = 0;
        if (out.gross == null && amts.length) out.gross = amts[amts.length - 1];
        out.net = out.gross;
    } else if (out.net == null || out.gross == null) {
        // Robust fallback for column-separated layouts (labels grouped, amounts grouped
        // elsewhere): among the money amounts find the LATEST self-consistent triple
        // where net + VAT ≈ gross. Format-agnostic and self-validating.
        const tail = amts.slice(-14);
        let best = null;
        for (let k = tail.length - 1; k >= 0 && ! best; k--) {
            for (let i = 0; i < k; i++) {
                for (let j = 0; j < k; j++) {
                    if (i === j) continue;
                    if (Math.abs(tail[i] + tail[j] - tail[k]) <= 0.02 && tail[k] > 0) {
                        if (! best || tail[k] > best.gross) {
                            best = { gross: tail[k], net: Math.max(tail[i], tail[j]), vat: Math.min(tail[i], tail[j]) };
                        }
                    }
                }
            }
        }
        if (best) {
            if (out.gross == null) out.gross = best.gross;
            if (out.net == null) out.net = best.net;
            if (out.vat == null) out.vat = best.vat;
        } else if (out.gross == null && amts.length) {
            out.gross = amts[amts.length - 1];
        }
    }

    // Reconcile the three when one is missing.
    if (out.net == null && out.gross != null && out.vat != null) out.net = round2(out.gross - out.vat);
    if (out.gross == null && out.net != null) out.gross = round2(out.net + (out.vat || 0));
    if (out.vat == null && out.net != null && out.gross != null) out.vat = round2(out.gross - out.net);
    if (out.vatRate == null && out.net) out.vatRate = out.vat ? Math.round((out.vat / out.net) * 100) : 0;

    // First line-item description (for a nicer summary line), best-effort.
    const desc = t.match(/^\s*(?:1|Pos)\s+(.{3,80}?)\s{2,}/m) || t.match(/^Beschreibung.*\n\s*(.{3,80}?)\s{2,}/im);
    if (desc) out.firstDesc = desc[1].trim();

    return out;
}

function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

/**
 * Build an invoice draft from the filename + text parses. Filename wins for
 * number/customer/date; text supplies money. Returns a record shaped like the invoices
 * component's own drafts, plus `_warnings` for the review UI.
 * @param {object} f  parseInvoiceFilename result
 * @param {object} p  parseInvoiceText result
 * @param {object} opts  { id, currency, summaryLabel }
 */
export function buildImportedInvoice(f, p, opts = {}) {
    const warnings = [];
    const number = f.number || p.number || null;
    const issueDate = f.date || p.date || null;
    const net = p.net;
    const vatRate = p.vatRate == null ? 0 : p.vatRate;

    if (! number) warnings.push('number');
    if (! issueDate) warnings.push('date');
    if (net == null) warnings.push('amount');

    const line = {
        desc: p.firstDesc || opts.summaryLabel || 'Rechnung',
        qty: 1,
        unit: '',
        unitPrice: net == null ? 0 : net,
        vatRate,
    };

    return {
        id: opts.id,
        number,
        status: 'paid',
        issueDate,
        dueDate: p.dueDate || issueDate,
        currency: opts.currency || 'EUR',
        lang: 'de',
        customer: { name: f.customer || '', attn: '', address: '', email: '', vatId: '', contactId: null },
        lines: [line],
        note: '',
        footer: '',
        trashed: false,
        imported: true,
        _warnings: warnings,
        _parsedGross: p.gross,
    };
}
