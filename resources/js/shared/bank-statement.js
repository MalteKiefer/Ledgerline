// Bank-statement parsing for the Finance module. Pure + testable — all statement
// parsing runs client-side; the parsed transactions are then persisted over REST.
// Hybrid strategy: auto-detect the well-known formats (MT940, and CSVs whose header we
// recognise), and fall back to a user-driven column mapping for any other CSV.
//
// Every parser normalises to the common transaction shape:
//   { date, valueDate, amount, currency, purpose, counterparty, iban, bic,
//     bookingText, eref }
// amount is a signed number (negative = money out). date/valueDate are ISO yyyy-mm-dd.

// ---- Value helpers ----

/** Parse a German/decimal amount ("1.992,43", "-175,28", "213,51", "150.00") → number. */
export function parseAmount(s) {
    if (typeof s === 'number') return Number.isFinite(s) ? s : null;
    let t = String(s == null ? '' : s).trim().replace(/\s| |EUR|€/gi, '');
    if (! t) return null;
    let sign = 1;
    if (/^\(.*\)$/.test(t)) { sign = -1; t = t.slice(1, -1); }
    if (/-$/.test(t)) { sign = -1; t = t.replace(/-$/, ''); }
    if (/^-/.test(t)) { sign = -1; t = t.replace(/^-/, ''); }
    t = t.replace(/^\+/, '');
    const lastComma = t.lastIndexOf(',');
    const lastDot = t.lastIndexOf('.');
    if (lastComma > lastDot) t = t.replace(/\./g, '').replace(',', '.');   // 1.992,43
    else if (lastDot > lastComma) t = t.replace(/,/g, '');                 // 1,992.43
    else t = t.replace(',', '.');
    const n = parseFloat(t);
    return Number.isFinite(n) ? sign * Math.round(n * 100) / 100 : null;
}

/** Normalise a date (DD.MM.YY[YY], YYYY-MM-DD, YYMMDD, DD/MM/YYYY) → ISO yyyy-mm-dd. */
export function parseDate(s) {
    const t = String(s == null ? '' : s).trim();
    if (! t) return null;
    let m = t.match(/^(\d{4})-(\d{2})-(\d{2})/);                       // ISO
    if (m) return `${m[1]}-${m[2]}-${m[3]}`;
    m = t.match(/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{2}|\d{4})/);         // DD.MM.YY(YY)
    if (m) { const y = m[3].length === 2 ? '20' + m[3] : m[3]; return `${y}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
    m = t.match(/^(\d{2})(\d{2})(\d{2})$/);                            // YYMMDD (MT940)
    if (m) return `20${m[1]}-${m[2]}-${m[3]}`;
    return null;
}

// ---- Format detection ----

/** 'mt940' | 'csv' | 'unknown' from the file's content (+ optional name). */
export function detectFormat(text, filename = '') {
    const s = String(text || '');
    if (/^:20:/m.test(s) && /^:61:/m.test(s)) return 'mt940';
    if (/\.sta$|\.mt940$/i.test(filename) && /:61:/.test(s)) return 'mt940';
    if (/[,;\t]/.test(s.split(/\r?\n/)[0] || '')) return 'csv';
    return 'unknown';
}

// ---- MT940 ----

const MT940_DC = { C: 1, D: -1, RC: -1, RD: 1 }; // reversal credit lowers, reversal debit raises

/** Parse the ?NN sub-fields of an MT940 :86: field into { code, purpose, name, iban, bic, eref }. */
export function parseMt940Field86(raw) {
    const s = String(raw || '');
    const out = { bookingText: '', purpose: '', counterparty: '', iban: '', bic: '', eref: '' };
    const parts = s.split('?').slice(1); // first chunk before ?00 is the GVC code
    const purpose = [], name = [];
    for (const p of parts) {
        const code = p.slice(0, 2), val = p.slice(2);
        if (code === '00') out.bookingText = val.trim();
        else if (code >= '20' && code <= '29') purpose.push(val);
        else if (code === '30') out.bic = val.trim();
        else if (code === '31') out.iban = val.trim();
        else if (code === '32' || code === '33') name.push(val);
    }
    out.purpose = purpose.join('').replace(/\s+/g, ' ').trim();
    out.counterparty = name.join('').replace(/\s+/g, ' ').trim();
    const eref = out.purpose.match(/EREF\+([^\s]+)/i); if (eref) out.eref = eref[1];
    const svwz = out.purpose.match(/SVWZ\+(.*)$/i); if (svwz) out.purpose = svwz[1].trim();
    return out;
}

/**
 * Parse an MT940 statement file into { transactions, openingBalance, closingBalance,
 * currency, account }. Handles multiple concatenated statements and wrapped (folded)
 * :86: lines.
 */
export function parseMt940(text) {
    // Re-join wrapped lines: a line that doesn't start with ':' or '-' continues the prior.
    const rawLines = String(text || '').split(/\r?\n/);
    const lines = [];
    for (const ln of rawLines) {
        if (/^:\w{2,3}:/.test(ln) || ln === '-') lines.push(ln);
        else if (lines.length) lines[lines.length - 1] += ln;
        // else: leading noise, ignore
    }
    const txns = [];
    let currency = 'EUR', account = '', opening = null, closing = null, pending = null;
    const pushPending = () => { if (pending) { txns.push(pending); pending = null; } };
    for (const ln of lines) {
        const tag = (ln.match(/^:(\w{2,3}):/) || [])[1];
        const body = ln.replace(/^:\w{2,3}:/, '');
        if (tag === '25') account = body.trim();
        else if (tag === '60F' || tag === '60M') {
            const m = body.match(/^([CD])(\d{6})([A-Z]{3})([\d.,]+)/);
            if (m) { currency = m[3]; if (opening == null) opening = (m[1] === 'D' ? -1 : 1) * parseAmount(m[4]); }
        } else if (tag === '62F' || tag === '62M') {
            const m = body.match(/^([CD])(\d{6})([A-Z]{3})([\d.,]+)/);
            if (m) { currency = m[3]; closing = (m[1] === 'D' ? -1 : 1) * parseAmount(m[4]); }
        } else if (tag === '61') {
            pushPending();
            // valueDate(6) entryDate(4)? mark(C/D/RC/RD) funds? amount... Nxxx ref
            const m = body.match(/^(\d{6})(\d{4})?(RC|RD|C|D)([A-Z])?([\d.,]+)/);
            if (m) {
                const sign = MT940_DC[m[3]] ?? 1;
                const valueDate = parseDate(m[1]);
                const entry = m[2] ? `${valueDate.slice(0, 4)}-${m[2].slice(0, 2)}-${m[2].slice(2)}` : valueDate;
                pending = {
                    date: entry, valueDate, amount: sign * (parseAmount(m[5]) || 0), currency,
                    purpose: '', counterparty: '', iban: '', bic: '', bookingText: '', eref: '',
                };
            }
        } else if (tag === '86' && pending) {
            const f = parseMt940Field86(body);
            pending.purpose = f.purpose; pending.counterparty = f.counterparty;
            pending.iban = f.iban; pending.bic = f.bic; pending.bookingText = f.bookingText; pending.eref = f.eref;
        }
    }
    pushPending();
    return { transactions: txns, openingBalance: opening, closingBalance: closing, currency, account };
}

// ---- CSV ----

/** Split a delimited line honouring double-quoted fields. */
function splitLine(line, delim) {
    const out = []; let cur = '', q = false;
    for (let i = 0; i < line.length; i++) {
        const c = line[i];
        if (q) {
            if (c === '"' && line[i + 1] === '"') { cur += '"'; i++; }
            else if (c === '"') q = false;
            else cur += c;
        } else if (c === '"') q = true;
        else if (c === delim) { out.push(cur); cur = ''; }
        else cur += c;
    }
    out.push(cur);
    return out.map((s) => s.trim());
}

/** Parse CSV text → { delimiter, header:[], rows:[[]] }. Detects ';' , ',' or tab. */
export function parseCsv(text) {
    const lines = String(text || '').split(/\r?\n/).filter((l) => l.trim() !== '');
    if (! lines.length) return { delimiter: ';', header: [], rows: [] };
    const first = lines[0];
    const delimiter = [';', '\t', ','].map((d) => [d, (first.match(new RegExp(`\\${d === '\t' ? 't' : d}`, 'g')) || []).length])
        .sort((a, b) => b[1] - a[1])[0][0];
    const header = splitLine(lines[0], delimiter);
    const rows = lines.slice(1).map((l) => splitLine(l, delimiter));
    return { delimiter, header, rows };
}

// The target fields a CSV column can map to. date + amount are required.
export const TX_FIELDS = ['date', 'valueDate', 'amount', 'purpose', 'counterparty', 'iban', 'bic', 'bookingText', 'eref', 'category'];
export const TX_REQUIRED = ['date', 'amount'];

// VAT categories a booking can carry (for the USt calculation). 'private' = Einlage /
// Entnahme (owner deposit/withdrawal), excluded from VAT. '' = not yet decided.
export const VAT_CATS = ['19', '16', '7', '0', 'private'];

/**
 * Best-effort guess of a booking's VAT category from its source category/purpose text.
 * Returns '' when it cannot tell (the user then picks one).
 */
export function guessVatCat(tx) {
    const s = `${tx.category || ''} ${tx.bookingText || ''} ${tx.purpose || ''}`.toLowerCase();
    if (/privat|einlage|entnahme|privateinlage|privatentnahme|einkommensteuer|gehalt|lohn/.test(s)) return 'private';
    let m = s.match(/(\d{1,2})\s*%/); // "19%", "Umsatzsteuer 7 %"
    if (m && VAT_CATS.includes(m[1])) return m[1];
    if (/umsatzsteuerfrei|steuerfrei|0\s*%/.test(s)) return '0';
    return '';
}

// Header signatures of banks we recognise → column-name mapping (auto, no user step).
const KNOWN_CSV = [
    {
        name: 'sparkasse',
        needs: ['Buchungstag', 'Verwendungszweck', 'Betrag'],
        map: { date: 'Buchungstag', valueDate: 'Valutadatum', amount: 'Betrag', currency: 'Waehrung', purpose: 'Verwendungszweck', counterparty: 'Beguenstigter/Zahlungspflichtiger', iban: 'Kontonummer', bic: 'BLZ', bookingText: 'Buchungstext' },
    },
    {
        name: 'generic-iso',
        needs: ['Buchungsdatum', 'Empfänger', 'Betrag'],
        map: { date: 'Buchungsdatum', valueDate: 'Wertstellungsdatum', amount: 'Betrag', purpose: 'Verwendungszweck', counterparty: 'Empfänger', iban: 'IBAN', bookingText: 'Transaktionstyp', eref: 'end_to_end_id', category: 'Kategorie' },
    },
];

/** Auto-detect a known CSV mapping from its header, or null (→ manual mapping). */
export function detectCsvMapping(header) {
    const hset = new Set((header || []).map((h) => h.trim()));
    for (const k of KNOWN_CSV) {
        if (k.needs.every((n) => hset.has(n))) {
            const map = {};
            for (const [field, col] of Object.entries(k.map)) if (hset.has(col)) map[field] = col;
            return { name: k.name, map };
        }
    }
    return null;
}

/**
 * Apply a {field: columnName} mapping to parsed CSV rows → normalised transactions.
 * Rows missing a date or amount are skipped (returned in `skipped`).
 */
export function applyCsvMapping(header, rows, map) {
    const idx = {};
    for (const [field, col] of Object.entries(map || {})) { const i = header.indexOf(col); if (i >= 0) idx[field] = i; }
    const get = (row, field) => (idx[field] != null ? (row[idx[field]] ?? '') : '');
    const transactions = []; let skipped = 0;
    for (const row of rows) {
        if (! row || ! row.length) continue;
        const date = parseDate(get(row, 'date'));
        const amount = parseAmount(get(row, 'amount'));
        if (! date || amount == null) { skipped++; continue; }
        const purposeRaw = String(get(row, 'purpose') || '');
        const svwz = purposeRaw.match(/SVWZ\+(.*?)(?:EREF\+|MREF\+|CRED\+|$)/i);
        transactions.push({
            date,
            valueDate: parseDate(get(row, 'valueDate')) || date,
            amount,
            currency: (get(row, 'currency') || 'EUR').trim() || 'EUR',
            purpose: (svwz ? svwz[1] : purposeRaw).replace(/\s+/g, ' ').trim(),
            counterparty: String(get(row, 'counterparty') || '').replace(/\s+/g, ' ').trim(),
            iban: String(get(row, 'iban') || '').trim(),
            bic: String(get(row, 'bic') || '').trim(),
            bookingText: String(get(row, 'bookingText') || '').trim(),
            eref: String(get(row, 'eref') || '').trim() || (purposeRaw.match(/EREF\+([^\s]+)/i)?.[1] ?? ''),
            category: String(get(row, 'category') || '').trim(),
        });
    }
    return { transactions, skipped };
}

// ---- Dedup ----

/** A stable signature for a transaction (for dedup on re-import). */
export function txSignature(tx) {
    if (tx.eref) return 'e:' + tx.eref + '|' + tx.amount;
    return [tx.date, tx.amount.toFixed(2), (tx.counterparty || '').toLowerCase(), (tx.purpose || '').slice(0, 40).toLowerCase()].join('|');
}

/** Return only the incoming transactions not already present in `existing`. */
export function dedupeTransactions(existing, incoming) {
    const seen = new Set((existing || []).map(txSignature));
    const fresh = [];
    for (const tx of incoming || []) {
        const sig = txSignature(tx);
        if (seen.has(sig)) continue;
        seen.add(sig);
        fresh.push(tx);
    }
    return fresh;
}

// ---- Enrich-on-reimport ----
// Fields a later import may fill in on an already-known transaction.
const ENRICH_FIELDS = ['iban', 'bic', 'counterparty', 'purpose', 'bookingText', 'eref', 'valueDate'];

/**
 * Split incoming transactions against `existing`: brand-new ones (`fresh`), and ones
 * that already exist but carry info the stored record was missing (`updates` — each a
 * { sig, patch } to apply). A re-import thus enriches (adds IBAN/BIC/purpose/…) instead
 * of silently dropping the row. Signatures must match (same source or shared EREF).
 */
export function enrichExisting(existing, incoming) {
    const bySig = new Map((existing || []).map((t) => [txSignature(t), t]));
    const usedSig = new Set();
    const fresh = [], updates = [];
    for (const tx of incoming || []) {
        const sig = txSignature(tx);
        const match = bySig.get(sig);
        if (! match) {
            if (! usedSig.has(sig)) { usedSig.add(sig); fresh.push(tx); }
            continue;
        }
        const patch = {};
        for (const f of ENRICH_FIELDS) {
            if (! String(match[f] || '').trim() && String(tx[f] || '').trim()) patch[f] = tx[f];
        }
        if (Object.keys(patch).length) updates.push({ sig, patch });
    }
    return { fresh, updates };
}

// ---- Payment-type classification ----
const TYPE_RULES = [
    ['card', /karten|sepa-elv|(?:^|[^a-z])elv|point of sale|\bpos\b|debitk|girocard|visa|mastercard/i],
    ['debit', /lastschrift|einzug|abbuchung|abschlag|direct ?debit/i],
    ['credit', /gutschr|gehalt|lohn|rente|zahlungseingang/i],
    ['standingorder', /dauerauftr|standing ?order/i],
    ['fee', /entgelt|gebühr|geb\.|kontoführ/i],
    ['transfer', /überweis|ueberweis|echtzeit|transfer|zahlung an/i],
];

/** Classify a transaction's payment type from its booking text/purpose. */
export function classifyTxType(tx) {
    const s = `${tx.bookingText || ''} ${tx.purpose || ''}`;
    for (const [type, re] of TYPE_RULES) if (re.test(s)) return type;
    return (tx.amount || 0) >= 0 ? 'credit' : 'other';
}
