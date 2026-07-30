// Pure heuristics for importing historical invoice PDFs into invoice records.
// Invoices are zero-knowledge, so this runs entirely client-side (pdf.js extracts the
// text; these functions turn it into a draft). The filename carries the most reliable
// signal (date + number + customer); the PDF text supplies the money (net/VAT/gross).
// Two layout families are handled: the older "Rechnungsnr./Rechnungsdatum/Zu zahlen EUR"
// sheet and the newer "R-xxxx / Datum: / Nettobetrag/Gesamtbetrag" sheet. Line items
// vary too much to reconstruct reliably, so the draft carries ONE net-total summary
// line (the totals then reconcile) that the user can refine before saving.

/**
 * Parse a money amount in either notation — German "1.234,56" / "227,88 €" OR English
 * "1,234.56" / "€157.50" → 1234.56 / 157.5. The LAST separator is the decimal point.
 */
export function parseAmount(raw) {
    if (raw == null) return null;
    const s = String(raw).replace(/[^\d.,-]/g, '');
    if (! /\d/.test(s)) return null;
    const lastComma = s.lastIndexOf(','), lastDot = s.lastIndexOf('.');
    let n;
    if (lastComma > lastDot) n = parseFloat(s.replace(/\./g, '').replace(',', '.')); // comma decimal (DE)
    else if (lastDot > lastComma) n = parseFloat(s.replace(/,/g, '')); // dot decimal (EN)
    else n = parseFloat(s);
    return Number.isFinite(n) ? n : null;
}

/** All money amounts in the text, in order (both DE and EN notations). */
function collectAmounts(text) {
    return [...String(text).matchAll(/\d[\d.,]*[.,]\d{2}\b/g)].map((m) => parseAmount(m[0])).filter((n) => n != null);
}

/** "05.02.2022" → "2022-02-05" (ISO); null if not a dd.mm.yyyy date. */
export function parseGermanDate(raw) {
    const m = String(raw || '').match(/(\d{2})\.(\d{2})\.(\d{4})/);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : null;
}

const EN_MONTHS = { jan: '01', feb: '02', mar: '03', apr: '04', may: '05', jun: '06', jul: '07', aug: '08', sep: '09', oct: '10', nov: '11', dec: '12' };

/**
 * Parse an English-style date — "Feb 29, 2016" / "February 29 2016" / "29 Feb 2016" —
 * to ISO (yyyy-mm-dd), or null. Used for the USD/English invoice family; the German
 * dd.mm.yyyy path is unaffected.
 */
export function parseEnglishDate(raw) {
    const s = String(raw || '');
    let m = s.match(/\b([A-Za-z]{3})[a-z]*\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/); // Mon DD, YYYY
    if (m) { const mo = EN_MONTHS[m[1].toLowerCase()]; if (mo) return `${m[3]}-${mo}-${String(m[2]).padStart(2, '0')}`; }
    m = s.match(/\b(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z]{3})[a-z]*\.?\s+(\d{4})\b/); // DD Mon YYYY
    if (m) { const mo = EN_MONTHS[m[2].toLowerCase()]; if (mo) return `${m[3]}-${mo}-${String(m[1]).padStart(2, '0')}`; }
    return null;
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

/**
 * The invoice number as printed in the PDF — the AUTHORITATIVE source (filenames are
 * often wrong or renamed, and the format changed over the years). Handles every
 * generation: R-YYYY-NNNNN (dated), R-NNNNN, and the older plain integer under a
 * "Rechnungsnr."/"Rechnungsnummer" label (which may be column-separated onto the next
 * line). Never returns the customer number (K-…).
 */
export function parseInvoiceNumber(text) {
    const t = String(text || '');
    const NUM = 'R-\\d{4}-\\d+|R-\\d+|\\d{4}-\\d+|\\d{1,6}';
    // Labeled forms first (most reliable): "Rechnung #:", "Rechnung Nr.:",
    // "Rechnungsnr.", "Rechnungsnummer" — the value may sit on the next line.
    let m = t.match(new RegExp(`Rechnung\\s*(?:#|Nr\\.?)\\s*:?\\s*[\\r\\n]*\\s*(${NUM})`, 'i'));
    if (m) return m[1];
    m = t.match(new RegExp(`Rechnungs(?:nr\\.?|nummer)\\s*:?\\s*[\\r\\n]*\\s*(${NUM})`, 'i'));
    if (m) return m[1];
    // Bare forms, in order of specificity.
    m = t.match(/\bR-\d{4}-\d{2,}\b/); if (m) return m[0]; // R-YYYY-NNNNN
    m = t.match(/\bR-\d{2,}\b/); if (m) return m[0]; // R-NNNNN
    m = t.match(/\b(?:19|20)\d{2}-\d{2,}\b/); if (m) return m[0]; // YYYY-NNN (2026-001)

    // Values-BEFORE-labels column layout (older debitoor sheet): the values print as a
    // block ("1 / 10.04.2014 / 30.04.2014 / 146,58") ABOVE the label block ("Rechnung /
    // Rechnungsnr. / Rechnungsdatum / Fälligkeitsdatum / Zu zahlen EUR"). Align the value
    // that sits at "Rechnungsnr."'s offset within the labels — a bare integer that is
    // neither a date nor a money amount.
    const lines = t.split(/[\r\n]+/).map((s) => s.trim());
    const flat = (s) => s.replace(/\s+/g, '').toLowerCase();
    const isLabel = (s) => /^(rechnungs?-?nr\.?|rechnungsnummer|rechnungsdatum|datum|f[äa]lligkeitsdatum|f[äa]lligam|zuzahlen(eur)?|kundennummer|leistungsdatum|lieferdatum)$/.test(flat(s));
    const nrIdx = lines.findIndex((l) => /^(rechnungs?-?nr\.?|rechnungsnummer)$/.test(flat(l)));
    if (nrIdx > 0) {
        // "Rechnung" title starts the label block (search up to 3 lines back).
        let titleIdx = -1;
        for (let i = nrIdx - 1; i >= 0 && i >= nrIdx - 3; i--) { if (flat(lines[i]) === 'rechnung') { titleIdx = i; break; } }
        if (titleIdx > 0) {
            // Count the contiguous data-label run after the title (Rechnungsnr., …).
            let n = 0;
            for (let i = titleIdx + 1; i < lines.length && isLabel(lines[i]); i++) n++;
            const offset = nrIdx - titleIdx - 1; // Rechnungsnr.'s position within that run
            // The values are the N lines DIRECTLY above the title, same order as the labels.
            if (n > 0 && titleIdx - n >= 0) {
                const cand = lines[titleIdx - n + offset];
                if (cand && /^(R-\d{4}-\d+|R-\d+|\d{4}-\d+|\d{1,6})$/.test(cand) && ! /^\d{1,2}\.\d{1,2}\.\d{4}$/.test(cand)) return cand;
            }
        }
    }
    return null;
}

/**
 * Re-glue a single letter that justified-text rendering (e.g. debitoor) stranded with a
 * space before a lowercase run: "W artung" → "Wartung", "D-79183 W aldkirch" → "…Waldkirch".
 * SAFE by design — only a LONE letter is merged, so real word boundaries ("Pflege der",
 * "Installation sowie") are untouched. Multi-letter mid-word splits baked into the PDF text
 * ("Softw are", "Sw itch") are NOT repaired here (that would need a dictionary and risk
 * wrong merges); the import review's multiline field lets the user fix those.
 */
export function deSpaceWord(s) {
    return String(s || '').replace(/\b([A-Za-zÄÖÜäöüß])\s+(?=[a-zäöüß]{2,})/g, '$1');
}

/**
 * How badly a PDF's embedded text layer is justified-mangled — the count of whitespace-
 * separated tokens that are a SINGLE letter ("W", "e"), the signature of debitoor-style
 * spaces baked mid-word ("W artung", "Softw are", "Sw itch"). A clean invoice sits near 0–3;
 * a mangled one runs 8–27. Used to decide whether to fall back to rasterise+OCR.
 */
export function mangleScore(text) {
    const toks = String(text || '').split(/\s+/).filter((x) => /[A-Za-zÄÖÜäöüß]/.test(x));
    return toks.filter((x) => x.replace(/[^A-Za-zÄÖÜäöüß]/g, '').length === 1).length;
}

/** True when the text layer is justified-mangled enough to prefer OCR (see mangleScore). */
export function looksMangled(text) {
    return mangleScore(text) >= 8;
}

/**
 * The recipient (customer) block from the PDF text — more reliable than the filename,
 * which is frequently mojibake (e.g. "N++rnberg", "#U00f6nning") or renamed. The block
 * is the address that follows the "Kiefer Networks …" sender one-liner and precedes the
 * "Rechnung"/number/USt markers. Returns { name, address } or null.
 * @param {string} text  newline-preserving PDF text (see the importer's extractor)
 * @param {RegExp} senderRe  matches the seller's own one-liner (to skip past it)
 */
export function parseCustomer(text, sender = 'kiefernetworks') {
    // Only the top address block (the footer repeats the sender).
    const lines = String(text || '').split(/[\r\n]+/).map((s) => s.replace(/\s+/g, ' ').trim()).slice(0, 24);
    // Some PDFs letter-space the section headers ("R E C H N U N G   A N"); compare with
    // ALL whitespace removed so those markers/stop-words are still recognised.
    const flat = (s) => s.replace(/\s+/g, '').toLowerCase();
    const isSender = (s) => flat(s).includes(sender);
    const isStop = (s) => /^(rechnungsdetails|rechnungs[üu]bersicht|beschreibung|von|zahlungs|notizen|steuer|pos|item|quantity|menge|rechnung(nr|snr|snummer|sdatum)?|kundennummer|datum|leistungs|status|f[äa]llig|ust)/.test(flat(s));

    let start = -1, viaMarker = false;
    // 1) explicit recipient marker ("RECHNUNG AN" / "BILL TO"), incl. letter-spaced.
    for (let i = 0; i < lines.length; i++) {
        if (/rechnungan|billto|rechnungsempf/.test(flat(lines[i]))) { start = i + 1; viaMarker = true; break; }
    }
    // 2) else the sender one-liner (with an address separator / postcode).
    if (start < 0) for (let i = 0; i < lines.length; i++) if (isSender(lines[i]) && /[|\-–—]|\d{5}/.test(lines[i])) { start = i + 1; break; }
    if (start < 0) for (let i = 0; i < lines.length; i++) if (isSender(lines[i])) { start = i + 1; break; }
    if (start < 0) return null;

    // Header-style layouts print the SENDER as a block (wordmark → name → address →
    // Bank/IBAN) before the recipient. When we entered via the sender (no explicit "Rechnung
    // an" marker) and a bank cluster sits in the immediate run after `start`, skip past it so
    // the FIRST post-cluster address is the real recipient — not the seller ("Malte Kiefer").
    // (The footer bank line is far below this 24-line window, so it can't false-trigger.)
    if (! viaMarker) {
        const isBankish = (s) => /iban|bic\b|\bblz\b|kontonr|kontoinhaber|volksbank|sparkasse|^bank\b|steuernummer|amtsgericht|gesch[äa]ftsf[üu]hrer/i.test(s);
        let j = -1;
        for (let k = start; k < lines.length && k < start + 10; k++) if (lines[k] && isBankish(lines[k])) j = k;
        if (j >= 0) start = j + 1;
    }

    const deSpace = deSpaceWord;
    // The recipient's VAT id ("USt.-IdNr. DE265814432" / "UST: DE28...") — the FIRST one in
    // the recipient block is the customer's (the seller's own sits in the footer / above the
    // skipped bank cluster).
    const vatOf = (s) => { const m = s.match(/\bUSt\.?(?:-?\s?IdNr\.?)?\s*:?\s*([A-Z]{2}\s?\d[\d\s]{5,})/i); return m ? m[1].replace(/\s+/g, '') : null; };

    const block = [];
    let vatId = '';
    for (let i = start; i < lines.length && block.length < 5; i++) {
        let ln = lines[i];
        if (! ln) { if (block.length) break; else continue; }
        // Capture the VAT id before the `ust` stop-word discards its line.
        const v = vatOf(ln);
        if (v) { vatId = v; break; } // the VAT id ends the recipient address block
        if (isStop(ln)) break;
        // Two-column sheets can merge "Kiefer Networks  <Customer>" on one line — strip
        // the seller's own name so the recipient survives.
        if (isSender(ln)) {
            ln = ln.replace(/kiefer\s*networks/ig, '').replace(/^[\s,·|–-]+/, '').trim();
            if (! ln) continue;
        }
        block.push(deSpace(ln));
    }
    if (! block.length) return null;
    return { name: block[0], address: block.slice(1).join('\n'), vatId };
}

/**
 * The first line-item description — the line right after the "BESCHREIBUNG … BETRAG"
 * table header (handling letter-spaced headers), with any trailing quantity/price/amount
 * columns stripped. Returns null so the caller can use a generic summary label.
 */
export function parseFirstDesc(text) {
    const lines = String(text || '').split(/[\r\n]+/).map((s) => s.replace(/\s+/g, ' ').trim());
    const flat = (s) => s.replace(/\s+/g, '').toLowerCase();
    const h = lines.findIndex((l) => /beschreibung|description|item/.test(flat(l)) && /(menge|betrag|einzelpreis|anzahl|quantity|amount|preis|rate)/.test(flat(l)));
    if (h < 0) return null;
    for (let i = h + 1; i < lines.length && i < h + 4; i++) {
        let ln = lines[i];
        if (! ln) continue;
        ln = ln.replace(/(\s+[\d.,]+(?:\s*€|\s*%)?){1,4}\s*$/, '').trim(); // strip trailing qty/price/amount cols
        if (ln.length >= 3 && /[a-zäöüß]/i.test(ln)) return ln.slice(0, 120);
    }
    return null;
}

/**
 * Parse ONE table row into its columns { desc, qty, unit, unitPrice, amount }, or null if
 * the line isn't a "<desc> <qty> [unit] <unitPrice> <amount>" row (e.g. a sub-description
 * bullet with no trailing number columns). Validated by qty × unitPrice ≈ amount so a
 * stray numeric line can't masquerade as an item. The unit column is optional.
 */
function parseItemRow(ln) {
    if (! ln) return null;
    ln = ln.replace(/[$€£]/g, ' ').replace(/\s+/g, ' ').trim(); // drop currency symbols (EN "$ 34" / DE "12,00 €")
    // Build + validate a candidate (qty × unitPrice ≈ amount rejects a mis-parse).
    const mk = (desc, q, u, p, a) => {
        const qty = parseAmount(q), unitPrice = parseAmount(p), amount = parseAmount(a);
        if (! qty || unitPrice == null || amount == null || Math.abs(qty * unitPrice - amount) > 0.02) return null;
        return { desc: (desc || '').trim().slice(0, 120), qty, unit: (u || '').trim().slice(0, 24), unitPrice, amount };
    };
    let m, r;
    // WITH a leading description + unit: "<desc> <qty> <unit> <unitPrice> <amount>".
    m = ln.match(/^(.*?[a-zäöüß].*?)\s+(\d+(?:[.,]\d+)?)\s+([A-Za-zäöüÄÖÜß][A-Za-zäöüÄÖÜß().]*(?:\s[A-Za-zäöüÄÖÜß().]+)?)\s+([\d.,]+)\s+([\d.,]+)$/);
    if (m && (r = mk(m[1], m[2], m[3], m[4], m[5]))) return r;
    // WITH a leading description, no unit column.
    m = ln.match(/^(.*?[a-zäöüß].*?)\s+(\d+(?:[.,]\d+)?)\s+([\d.,]+)\s+([\d.,]+)$/);
    if (m && (r = mk(m[1], m[2], '', m[3], m[4]))) return r;
    // COLUMN-ONLY + unit (the description sits on a preceding line — many sheets put
    // "TP-LINK …\n1 Stück 69,89 69,89"): "<qty> <unit> <unitPrice> <amount>".
    m = ln.match(/^(\d+(?:[.,]\d+)?)\s+([A-Za-zäöüÄÖÜß][A-Za-zäöüÄÖÜß().]*)\s+([\d.,]+)\s+([\d.,]+)$/);
    if (m && (r = mk('', m[1], m[2], m[3], m[4]))) return r;
    // COLUMN-ONLY, no unit: "<qty> <unitPrice> <amount>".
    m = ln.match(/^(\d+(?:[.,]\d+)?)\s+([\d.,]+)\s+([\d.,]+)$/);
    if (m && (r = mk('', m[1], '', m[2], m[3]))) return r;
    return null;
}

/**
 * Parse ALL line-item rows between the "BESCHREIBUNG … BETRAG" table header and the totals
 * block (Gesamt/Zwischensumme/Zu zahlen/… or the §19 footer). Each returned item is
 * { desc, qty, unit, unitPrice, amount }. Sub-description lines (bullets/prose with no
 * trailing number columns) are skipped. Returns [] when there is no recognisable table.
 * The caller keeps these real items only when their amounts sum to the invoice net (so a
 * partial/mis-parse never under-counts the total — see buildImportedInvoice).
 */
export function parseLineItems(text) {
    const lines = String(text || '').split(/[\r\n]+/).map((s) => s.replace(/\s+/g, ' ').trim());
    const flat = (s) => s.replace(/\s+/g, '').toLowerCase();
    const h = lines.findIndex((l) => /beschreibung|description|item/.test(flat(l)) && /(menge|betrag|einzelpreis|anzahl|quantity|amount|preis|rate)/.test(flat(l)));
    if (h < 0) return [];
    const STOP = /^(gesamt|zwischensumme|nettobetrag|nettogesamt|zuzahlen|zahlbetrag|mwst|umsatzsteuer|ust\b|zzgl|rechnungsbetrag|gesamtbetrag|summe|gem[äa]ß|kiefernetworks|subtotal|total|balance|amountdue|notes|terms|paypal|bankdetails)/;
    const items = [];
    let buf = []; // pending description lines (for column-only rows whose desc is above)
    for (let i = h + 1; i < lines.length; i++) {
        const ln = lines[i];
        if (! ln) continue;
        if (STOP.test(flat(ln))) break; // reached the totals / footer
        const it = parseItemRow(ln);
        if (it) {
            // Column-only row (no inline description) → take the buffered text above it.
            // Keep newlines so a multi-line description (title + sub-bullets) survives intact.
            if (! it.desc) it.desc = buf.map((s) => deSpaceWord(s.trim())).join('\n').trim().slice(0, 400);
            else it.desc = deSpaceWord(it.desc);
            items.push(it);
            buf = [];
        } else {
            buf.push(ln); // description / sub-bullet / category line — attach to the next row
            if (buf.length > 12) buf.shift();
        }
    }
    return items;
}

/** The first line item (kept for callers/tests that want just one). */
export function parseFirstLineItem(text) {
    return parseLineItems(text)[0] || null;
}

/**
 * The running sequence number for the CURRENT year's series (YYYY-NNN), so importing
 * this year's invoices advances the app's counter. Historical years / other formats
 * return null (they are archival and must not move the current counter).
 */
export function importedSeq(number, currentYear) {
    const m = String(number || '').match(/^(\d{4})-0*(\d+)$/);
    if (m && Number(m[1]) === Number(currentYear)) return parseInt(m[2], 10);
    return null;
}

/** Money + dates + VAT + number + customer from the extracted PDF text. */
export function parseInvoiceText(text) {
    let t = String(text || '').replace(/ /g, ' ');
    t = t.replace(/(\d)\s+([.,])(\d{2})(?!\d)/g, '$1$2$3'); // glue a whitespace-split decimal: "160 .00" -> "160.00"
    t = t.replace(/\b(\d{1,2})\s*\.\s*(\d{1,2})\s*\.\s*(\d{4})\b/g, '$1.$2.$3'); // glue a spaced date: "28 . 08 . 2020" -> "28.08.2020"
    const out = { date: null, dateLabeled: null, dueDate: null, number: null, net: null, vat: null, gross: null, vatRate: null, smallBusiness: false, firstDesc: null, currency: null, discount: null };

    // Currency: $ → USD, £ → GBP, CHF, else EUR (the German sheets are EUR; the older
    // Vonderland invoices are USD).
    if (/\bUSD\b|\$/.test(t)) out.currency = 'USD';
    else if (/\bGBP\b|£/.test(t)) out.currency = 'GBP';
    else if (/\bCHF\b/.test(t)) out.currency = 'CHF';
    else out.currency = 'EUR';

    // The printed invoice number is AUTHORITATIVE (filenames are often wrong/renamed).
    out.number = parseInvoiceNumber(t);

    // Issue date: labeled "Datum:"/"Rechnungsdatum" (may sit on the next line).
    const date = t.match(/(?:Rechnungsdatum|Datum)\s*:?\s*(\d{2}\.\d{2}\.\d{4})/i);
    if (date) { out.dateLabeled = parseGermanDate(date[1]); out.date = out.dateLabeled; }

    // Due date: "Fälligkeitsdatum"/"Fällig am" label or the "bis zum dd.mm.yyyy" sentence.
    const due = t.match(/F[äa]llig(?:keitsdatum|\s*am)\s*:?\s*(\d{2}\.\d{2}\.\d{4})/i) || t.match(/bis zum\s+(\d{2}\.\d{2}\.\d{4})/i);
    if (due) out.dueDate = parseGermanDate(due[1]);

    // Fallbacks for column-separated layouts where pdf.js groups all LABELS together
    // and all VALUES together, so the label-adjacent regexes above can't bind (e.g. the
    // older Kiefer sheet: "Rechnungsdatum / Fälligkeitsdatum / Zu zahlen" then a separate
    // "17.06.2014 / 17.07.2014 / 36,00" group). Use the ordered date list positionally:
    // the issue date is the earliest; the due date is the first date strictly after it.
    const allDates = [...t.matchAll(/(\d{2})\.(\d{2})\.(\d{4})/g)]
        .map((m) => `${m[3]}-${m[2]}-${m[1]}`).filter((d) => /^\d{4}-\d{2}-\d{2}$/.test(d));
    if (! out.date && allDates.length) out.date = allDates[0];
    if (! out.dueDate && out.date) {
        const later = allDates.find((d) => d > out.date);
        if (later) out.dueDate = later;
    }
    // English/USD family ("Feb 29, 2016") — no dd.mm.yyyy. Collect all English dates in
    // order; the labels may sit BEFORE or AFTER their values (some templates group the
    // values first), so bind positionally: issue = earliest, due = first date after it.
    if (! out.date || ! out.dueDate) {
        const enDates = [...t.matchAll(/\b([A-Za-z]{3})[a-z]*\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/g)]
            .map((mm) => { const mo = EN_MONTHS[mm[1].toLowerCase()]; return mo ? `${mm[3]}-${mo}-${String(mm[2]).padStart(2, '0')}` : null; })
            .filter(Boolean);
        if (! out.date && enDates.length) { out.date = enDates[0]; out.dateLabeled = enDates[0]; }
        if (! out.dueDate && out.date) { const later = enDates.find((d) => d > out.date); if (later) out.dueDate = later; }
    }

    // Discount (Rabatt/Nachlass/Discount) printed as a negative line ("Rabatt 10% -19,19"):
    // its amount is subtracted from the item sum to reach the net, so a discounted invoice
    // still reconciles (item sum - discount ≈ net) and imports every real position.
    const disc = t.match(/(?:Rabatt|Nachlass|Discount)[^\n]*?-\s*([\d.,]+)/i);
    if (disc) { const d = parseAmount(disc[1]); if (d) out.discount = d; }

    // Small-business (Kleinunternehmer, §19 UStG) → no VAT.
    if (/§\s*19|Kleinunternehmer|keine Umsatzsteuer/i.test(t)) out.smallBusiness = true;

    // VAT rate from "19% MwSt" / "USt. 19%" / "Umsatzsteuer 19%" / "Steuer (19%)".
    const rate = t.match(/(\d{1,2})\s*%\s*(?:MwSt|USt|Umsatzsteuer|Steuer)/i)
        || t.match(/(?:USt|Umsatzsteuer|Steuer)\.?\s*\(?\s*(\d{1,2})\s*%/i);
    out.vatRate = out.smallBusiness ? 0 : (rate ? parseInt(rate[1], 10) : null);

    // Totals — first try label-adjacent amounts. The amount must be on the SAME line as
    // its label (horizontal whitespace only, `[^\S\r\n]`): in column-separated layouts
    // pdf.js groups all labels then all values, so a newline-crossing `\s*` would grab
    // the FIRST value of the value group (often the invoice number) right after "Zu
    // zahlen EUR". Same-line matching binds only a genuinely adjacent amount and lets the
    // grouped case fall through to the §19 last-amount / triple-reconcile fallback below.
    const H = '[^\\S\\r\\n]*'; // horizontal whitespace (no newline)
    let m = t.match(new RegExp(`(?:Nettobetrag|Nettogesamt|Zwischensumme(?: ohne USt\\.?)?):?${H}€?${H}([\\d.,]+)${H}€?`, 'i'));
    if (m) out.net = parseAmount(m[1]);
    m = t.match(new RegExp(`(?:zzgl\\.?${H}\\d{1,2}%${H}MwSt\\.?|MwSt\\.?|Umsatzsteuer(?:${H}\\d{1,2}%)?|USt\\.?${H}\\d{1,2}%${H}von${H}[\\d.,]+|Steuer${H}\\(?${H}\\d{1,2}${H}%${H}\\)?):?${H}€?${H}([\\d.,]+)${H}€?`, 'i'));
    if (m) out.vat = parseAmount(m[1]);
    m = t.match(new RegExp(`(?:Gesamtbetrag|Rechnungsbetrag|Gesamt${H}EUR|Zu zahlen EUR|\\bGesamt|\\bGESAMT):?${H}€?${H}([\\d.,]+)${H}€?`, 'i'));
    if (m) out.gross = parseAmount(m[1]);

    // English/USD gross: the LAST "Total / Balance Due / Amount Due / Grand Total"
    // (never "Subtotal"), currency symbol optional, integer amounts allowed ("$ 272").
    if (out.gross == null) {
        const en = [...t.matchAll(new RegExp(`(?<!Sub)(?:Balance${H}Due|Amount${H}Due|Grand${H}Total|Total)${H}:?${H}[$€£]?${H}([\\d][\\d.,]*)`, 'gi'))];
        if (en.length) out.gross = parseAmount(en[en.length - 1][1]);
        // Values-first English templates print "Total:"/"Balance Due:" AFTER their amount,
        // so the label-adjacent match above finds nothing → use the last money amount.
        if (out.gross == null && out.currency !== 'EUR') {
            const a = collectAmounts(t);
            if (a.length) out.gross = a[a.length - 1];
        }
        // English invoices are typically no-VAT sonstige-Leistung → net = gross.
        if (out.gross != null && out.net == null && out.currency !== 'EUR') { out.net = out.gross; out.vat = out.vat ?? 0; out.smallBusiness = true; }
    }

    const amts = collectAmounts(t);

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

    // First line-item description (for a nicer summary line than a generic label) and the
    // full parsed line-item table (qty/unit/unitPrice per row) — used when the items sum
    // to the net (so multi-item invoices import every real position with its unit).
    out.firstDesc = parseFirstDesc(text);
    out.items = parseLineItems(text);
    out.firstItem = out.items[0] || null;

    // Recipient block from the text (more reliable than a mojibake/renamed filename).
    out.customer = parseCustomer(text);

    return out;
}

function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }

/**
 * LEAN import draft — the ONLY fields the redesigned import captures: recipient, number,
 * issue date, gross total (Summe), VAT rate (Steuer), currency. No line-item table is
 * reconstructed (the original PDF is the authoritative record, shown inline); the user
 * confirms/fixes these six fields against the PDF before committing. `net`/`vat` are
 * derived from gross + rate at commit time, so the whole record is one synthetic net line.
 * @param {object} f  parseInvoiceFilename result
 * @param {object} p  parseInvoiceText result
 * @param {object} opts  { id, currency, currentYear, defaultVat }
 */
export function buildImportDraft(f, p, opts = {}) {
    const warnings = [];
    const number = p.number || f.number || null;
    const issueDate = p.dateLabeled || f.date || p.date || null;
    const recipient = {
        name: (p.customer && p.customer.name) || f.customer || '',
        address: (p.customer && p.customer.address) || '',
        vatId: (p.customer && p.customer.vatId) || '',
    };
    // Gross (Summe) is primary; fall back to net(+vat) when only those were parsed.
    let gross = p.gross;
    if (gross == null && p.net != null) gross = round2(p.net + (p.vat || 0));
    // VAT rate (Steuer): parsed value, else small-business 0, else the company default.
    let vatRate = p.vatRate;
    if (vatRate == null) vatRate = p.smallBusiness ? 0 : (opts.defaultVat != null ? opts.defaultVat : 19);
    const seq = opts.currentYear ? importedSeq(number, opts.currentYear) : null;

    if (! number) warnings.push('number');
    if (! issueDate) warnings.push('date');
    if (gross == null) warnings.push('amount');
    if (! recipient.name) warnings.push('recipient');

    const rec = {
        id: opts.id,
        number,
        issueDate,
        dueDate: p.dueDate || issueDate,
        currency: p.currency || opts.currency || 'EUR',
        gross: gross == null ? null : round2(gross),
        vatRate,
        recipient,
        selected: true,
        _warnings: warnings,
    };
    if (seq != null) rec.seq = seq;
    return rec;
}

/**
 * Build an invoice draft from the filename + text parses. The PDF TEXT is the primary
 * source (filenames are often wrong, renamed or mojibake); the filename is only a
 * fallback for what the text lacks. Returns a record shaped like the invoices
 * component's own drafts, plus `_warnings` for the review UI.
 * @param {object} f  parseInvoiceFilename result
 * @param {object} p  parseInvoiceText result
 * @param {object} opts  { id, currency, summaryLabel }
 */
export function buildImportedInvoice(f, p, opts = {}) {
    const warnings = [];
    // Text wins for the number (authoritative printed value across all format
    // generations); the filename is only used when the text yields nothing.
    const number = p.number || f.number || null;
    // Prefer a LABELED text date, then the filename's YYYYMMDD, then the text's
    // first-date fallback.
    const issueDate = p.dateLabeled || f.date || p.date || null;
    // Prefer the text-extracted recipient (clean UTF-8) over the mojibake-prone filename.
    const custName = (p.customer && p.customer.name) || f.customer || '';
    const custAddress = (p.customer && p.customer.address) || '';
    const custVatId = (p.customer && p.customer.vatId) || '';
    const net = p.net;
    const vatRate = p.vatRate == null ? 0 : p.vatRate;
    // Current-year invoices carry a seq so the app's number counter advances past them.
    const seq = opts.currentYear ? importedSeq(number, opts.currentYear) : null;

    if (! number) warnings.push('number');
    if (! issueDate) warnings.push('date');
    if (net == null) warnings.push('amount');

    // Use the REAL parsed line items (each with its qty + unit + unit price) when their
    // amounts sum to the invoice net — i.e. the table was read completely. This covers
    // both single- and multi-item invoices. If the items don't reconcile to the net
    // (partial/failed parse), fall back to ONE qty-1 net summary line so the total never
    // under-counts; the user refines line items in the review UI.
    const items = Array.isArray(p.items) ? p.items : [];
    const itemsSum = round2(items.reduce((s, it) => s + (it.amount ?? it.qty * it.unitPrice), 0));
    // A discount (Rabatt) is subtracted from the item sum, so reconcile against net minus it.
    const discount = p.discount || 0;
    const itemsWhole = items.length > 0 && net != null && Math.abs(itemsSum - discount - net) <= 0.02;
    const lines = itemsWhole
        ? items.map((it) => ({ desc: it.desc || opts.summaryLabel || 'Rechnung', qty: it.qty, unit: it.unit || '', unitPrice: it.unitPrice, vatRate }))
        : [{ desc: p.firstDesc || opts.summaryLabel || 'Rechnung', qty: 1, unit: '', unitPrice: net == null ? 0 : net, vatRate }];
    // Discount as its own negative line so the invoice total matches the printed net.
    if (itemsWhole && discount > 0) lines.push({ desc: 'Rabatt', qty: 1, unit: '', unitPrice: -round2(discount), vatRate });

    const rec = {
        id: opts.id,
        number,
        status: 'paid',
        issueDate,
        dueDate: p.dueDate || issueDate,
        currency: p.currency || opts.currency || 'EUR',
        lang: 'de',
        customer: { name: custName, attn: '', address: custAddress, email: '', vatId: custVatId, contactId: null },
        lines,
        note: '',
        footer: '',
        trashed: false,
        imported: true,
        _warnings: warnings,
        _parsedGross: p.gross,
    };
    if (seq != null) rec.seq = seq; // advances the current-year number counter
    return rec;
}

/**
 * Build a draft from a parsed embedded e-invoice XML (see shared/einvoice-xml). This is
 * the RELIABLE path — the XML carries every real line item, so no text scraping is
 * needed. Same record shape + seq/warnings contract as buildImportedInvoice.
 * @param {object} p  parseEInvoiceXml result
 * @param {object} opts  { id, currency, currentYear, summaryLabel }
 */
export function buildInvoiceFromXml(p, opts = {}) {
    const warnings = [];
    if (! p.number) warnings.push('number');
    if (! p.issueDate) warnings.push('date');
    if (p.gross == null && p.net == null) warnings.push('amount');
    const seq = opts.currentYear ? importedSeq(p.number, opts.currentYear) : null;
    const lines = (p.lines && p.lines.length)
        ? p.lines.map((l) => ({ desc: l.desc || '', qty: l.qty ?? 1, unit: l.unit || '', unitPrice: l.unitPrice ?? 0, vatRate: l.vatRate ?? 0 }))
        : [{ desc: opts.summaryLabel || 'Rechnung', qty: 1, unit: '', unitPrice: p.net ?? 0, vatRate: p.vatRate || 0 }];
    const rec = {
        id: opts.id,
        number: p.number,
        status: 'paid',
        issueDate: p.issueDate,
        dueDate: p.dueDate || p.issueDate,
        currency: p.currency || opts.currency || 'EUR',
        lang: 'de',
        customer: { name: p.customer?.name || '', attn: '', address: p.customer?.address || '', email: p.customer?.email || '', vatId: p.customer?.vatId || '', contactId: null },
        lines,
        note: '', footer: '', trashed: false, imported: true,
        _warnings: warnings,
        _parsedGross: p.gross,
    };
    if (seq != null) rec.seq = seq;
    return rec;
}
