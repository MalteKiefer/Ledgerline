// Pattern recognition over a receipt's OCR/text (client-side). Suggests a category
// and tags on upload and extracts the merchant, total and date — pure + testable. The
// same structured fields can later feed a Paperless push. Heuristic, German-first.

// category → keyword pattern (first match wins; order = priority)
// Order = priority (first match wins). Short/ambiguous tokens are anchored with \b on
// BOTH sides so e.g. "kündbar" no longer matches "bar" (→ Geschäftsessen), and generic
// words like "total"/"super" are avoided entirely (they appear on any receipt).
const CATEGORY_RULES = [
    ['Telekommunikation', /\btelekom\b|vodafone|\bo2\b|1&1|mobilfunk|\bdsl\b|glasfaser|prepaid|magenta/i],
    ['Reisekosten', /\bhotel\b|[üu]bernachtung|pension|hostel|deutsche bahn|\bflug\b|airline|lufthansa|ryanair|\btaxi\b|mietwagen|boarding|\bbahncard\b/i],
    ['Kfz', /tankstelle|\baral\b|\bshell\b|\besso\b|\bagip\b|\bomv\b|diesel|benzin|kraftstoff|\bkfz\b|werkstatt|\badac\b/i],
    ['Bürobedarf', /b[üu]robedarf|staples|schreibwaren|toner|druckerpatrone|kugelschreiber/i],
    ['Software', /\bsoftware\b|lizenz|licen[sc]e|subscription|\bsaas\b|\badobe\b|microsoft|github|jetbrains|\bfigma\b|\bslack\b|\bzoom\b|google one|google workspace|google drive|google cloud|dropbox|\bnotion\b|atlassian|openai|anthropic|\bapple\b.*(icloud|storage)|icloud|netcup|hetzner|ionos|strato|\bovh\b|contabo|digitalocean|linode|vultr|cloudflare|njalla|namecheap|godaddy|mullvad|\bproton(mail| ag|\.me)?\b|tutao|tutanota|hosting|webspace|vserver|\bvps\b|\bdomain\b|\bvpn\b|wyze|backblaze|\bageras\b/i],
    ['Hardware', /media\s?markt|\bsaturn\b|notebook|\blaptop\b|\bmonitor\b|tastatur|festplatte|\bssd\b|conrad|reichelt/i],
    ['Marketing', /\bwerbung\b|google ads|facebook ads|meta platforms|\bkampagne\b/i],
    ['Versicherung', /versicherung|\ballianz\b|\baxa\b|\bhuk\b|\bpolice\b/i],
    ['Fortbildung', /\bseminar\b|\bschulung\b|fortbildung|udemy|coursera|\bkonferenz\b|\bworkshop\b/i],
    ['Geschäftsessen', /restaurant|gastst[äa]tte|pizzeria|trattoria|bistro|imbiss|\bcaf[ée]\b|\bkaffee\b|\bbar\b|brauhaus|wirtshaus|speisekarte|trinkgeld|bewirtung|mcdonald|\bburger\b|d[öo]ner/i],
];

/** Parse a German/EN amount ("12,90", "1.234,56", "9.99") → number or null. */
function amount(s) {
    let t = String(s).replace(/[^\d.,]/g, '');
    if (! t) return null;
    const lc = t.lastIndexOf(','), ld = t.lastIndexOf('.');
    if (lc > ld) t = t.replace(/\./g, '').replace(',', '.');
    else if (ld > lc) t = t.replace(/,/g, '');
    else t = t.replace(',', '.');
    const n = parseFloat(t);
    return Number.isFinite(n) ? Math.round(n * 100) / 100 : null;
}

// Amounts on a line: decimal amounts anywhere, plus integers directly next to € / EUR
// (so "Total: 45 €" is caught but the year "2026" or a quantity "3" on the line is not).
function amountsIn(line) {
    const out = [];
    const re = /(\d{1,3}(?:[.\s]\d{3})*[.,]\d{2})|€\s*(\d{1,3}(?:[.\s]\d{3})*)(?![.,]\d)|(\d{1,3}(?:[.\s]\d{3})*)(?![.,]\d)\s*(?:€|eur\b)/gi;
    let m;
    while ((m = re.exec(line))) { const v = amount(m[1] || m[2] || m[3]); if (v != null) out.push(v); }
    return out;
}

/**
 * The receipt total: the amount on a gross-total line (Gesamtsumme / Rechnungsbetrag /
 * Total …), else the max. "Amount due / zu zahlen" is ignored when 0 (a paid invoice
 * shows due = 0 but the real total is the paid gross, e.g. Mullvad "paid 60 / due 0").
 */
export function extractTotal(text) {
    const lines = String(text || '').split(/\r?\n/);
    let labelled = null, max = null;
    for (const ln of lines) {
        const vals = amountsIn(ln);
        if (! vals.length) continue;
        for (const v of vals) if (max == null || v > max) max = v;
        // Net subtotal / tax lines are NOT the payable gross — "Zwischensumme" matches the
        // "summe" keyword but is the net amount (Apple/iCloud 8,40 net vs 9,99 gross).
        if (/zwischensumme|zwischensal|nettosumme|nettobetrag|nettogesamt|netto-?summe|subtotal|\bmwst\b|umsatzsteuer|\bust\b|mehrwertsteuer|\bvat\b|sales tax/i.test(ln)) continue;
        if (/summe|gesamt|rechnungsbetrag|endbetrag|grand total|\btotal\b|amount paid|\bpaid\b|bezahlt|gezahlt|zu zahlen/i.test(ln)) {
            const v = vals[vals.length - 1];
            if (v != null && v !== 0 && (labelled == null || v > labelled)) labelled = v;
        }
    }
    return labelled ?? max;
}

const MONTHS = {
    januar: 1, februar: 2, 'märz': 3, maerz: 3, april: 4, mai: 5, juni: 6, juli: 7,
    august: 8, september: 9, oktober: 10, november: 11, dezember: 12,
    january: 1, february: 2, march: 3, june: 6, july: 7, october: 10, december: 12,
    jan: 1, feb: 2, mar: 3, apr: 4, jun: 6, jul: 7, aug: 8, sep: 9, sept: 9, oct: 10, okt: 10, nov: 11, dec: 12, dez: 12,
};

const okDate = (y, mo, d) => mo >= 1 && mo <= 12 && d >= 1 && d <= 31 && y >= 2000 && y <= 2100;

/** First plausible date in the text → ISO yyyy-mm-dd, or ''. Validates the day/month. */
export function extractDate(text) {
    const s = String(text || '');
    // DD.MM.YYYY (day first — German/most receipts). Scan for the first VALID one.
    for (const mm of s.matchAll(/\b(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})\b/g)) {
        const y = mm[3].length === 2 ? 2000 + Number(mm[3]) : Number(mm[3]);
        const d = Number(mm[1]), mo = Number(mm[2]);
        if (okDate(y, mo, d)) return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    }
    let m = s.match(/\b(\d{4})-(\d{2})-(\d{2})\b/);
    if (m && okDate(Number(m[1]), Number(m[2]), Number(m[3]))) return `${m[1]}-${m[2]}-${m[3]}`;
    // "27. Juli 2026" / "27-MAR-2025" — day, month name (space/dot/dash separators).
    m = s.match(/\b(\d{1,2})[.\s-]+([A-Za-zäöüÄÖÜ]{3,})[.\s-]+(\d{4})\b/);
    if (m) { const mo = MONTHS[m[2].toLowerCase()]; if (mo) return `${m[3]}-${String(mo).padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
    m = s.match(/\b([A-Za-zäöüÄÖÜ]+)\.?\s+(\d{1,2}),?\s+(\d{4})\b/);
    if (m) { const mo = MONTHS[m[1].toLowerCase()]; if (mo) return `${m[3]}-${String(mo).padStart(2, '0')}-${m[2].padStart(2, '0')}`; }
    return '';
}

// Prefix match (no trailing \b) so German compounds like "Rechnungsdatum" /
// "Kundennummer" are skipped too. Kept specific to avoid eating real names.
const MERCHANT_SKIP = /^(ihre|ihr\b|your|rechnung|invoice|beleg|quittung|gutschrift|credit ?note|datum|date|kunde|customer|seite|page|betreff|subject|from\b|bill ?to|ship ?to|paid\b|vat\b|ust|steuer|item|menge|position|betrag|summe|total|details|leistungen|verkauft|sold by|umsatzsteuer|payment|sequenz|order\b|bestell)/i;
const COMPANY_SUFFIX = /\b(gmbh|mbh|ug|ag|kg|ohg|gbr|ltd|limited|llc|inc|corp|b\.?v\.?|s\.?[àa]\.?r\.?l|s\.?a\.?|ab|oy|llp|plc)\b|& co/i;
// Collapse letter-spaced runs ("I n t e l l y T e c" → "IntellyTec"): 3+ single-letter
// tokens in a row (some PDFs render tracked/letter-spaced headings as separate glyphs).
// Requires ≥3 to avoid merging genuine initials ("J R Ewing" stays).
const despace = (s) => String(s).replace(/(?:\b[A-Za-zÄÖÜäöü] ){2,}\b[A-Za-zÄÖÜäöü]\b/g, (m) => m.replace(/ /g, ''));
// Trim a letterhead line to just the company name: split on | / • / · separators, drop a
// trailing document word/label, and cut an address tail (", PF 3004", ", Industriestr. 25").
const cleanMerchant = (l) => despace(l).split(/\s*[|•·]\s*/)[0]
    .replace(/\s*(bill|ship)\s*to\b.*$/i, '')
    .replace(/\s+(place\s*\/?\s*date|place of invoice|date of invoice|invoice (requested|number|date|no)\b|customer\b|kundennummer\b).*$/i, '')
    .replace(/,?\s*(pf\b|postfach|\d|[^,]*(?:stra(?:ß|ss)e|str\.|weg|ring|platz|allee|gasse)\b).*$/i, '')
    .replace(/\s+(invoice|rechnung|receipt|quittung|beleg)\s*$/i, '')
    .replace(/\s{2,}/g, ' ').trim().slice(0, 50);

// Well-known brands whose invoices don't carry a clean "Brand GmbH" letterhead line
// (marketplaces, US companies, e-mail receipts, retailers). Used as a merchant fallback
// when no company-legal-form line is found. Order = priority (first match wins).
const BRANDS = [
    ['Amazon', /\bamazon\b/i], ['Apple', /\bapple\b/i], ['Google', /google/i], ['PayPal', /paypal/i],
    ['Backblaze', /backblaze/i], ['Microsoft', /microsoft/i], ['Netflix', /netflix/i], ['Spotify', /spotify/i],
    ['eBay', /\bebay\b/i], ['Dropbox', /dropbox/i], ['Cloudflare', /cloudflare/i], ['Adobe', /\badobe\b/i],
    ['DeepL', /\bdeepl\b/i], ['Telekom', /\btelekom\b|magenta/i], ['Vodafone', /vodafone/i],
    ['Kaufland', /kaufland/i], ['Edeka', /\bedeka\b/i], ['REWE', /\brewe\b/i], ['Lidl', /\blidl\b/i], ['Aldi', /\baldi\b/i],
    ['IKEA', /\bikea\b/i], ['Deutsche Bahn', /deutsche bahn|\bbahn\.de\b/i], ['Hetzner', /hetzner/i], ['netcup', /netcup/i],
];
export function detectBrand(text) { for (const [n, re] of BRANDS) if (re.test(String(text || ''))) return n; return ''; }

/**
 * The merchant/seller name. Prefer a line carrying a company legal form (GmbH, Ltd, LLC,
 * AB, …) in the letterhead — that is almost always the seller, not the recipient or a
 * label — then a known brand, then the first meaningful line.
 */
export function extractMerchant(text) {
    const lines = String(text || '').split(/\r?\n/).map((s) => s.replace(/\s{2,}/g, ' ').trim()).filter(Boolean);
    // 1. A company-legal-form line in the letterhead — almost always the seller.
    for (const l of lines.slice(0, 15)) {
        if (l.length < 3 || MERCHANT_SKIP.test(l) || ! COMPANY_SUFFIX.test(l)) continue;
        const c = cleanMerchant(l);
        if (c.length >= 3 && c.length <= 50) return c;
    }
    // 2. A known brand keyword (Amazon, Adobe, Telekom, Kaufland, …) — beats a random
    //    first line, which is often the recipient, a greeting or a table header.
    const brand = detectBrand(String(text || ''));
    if (brand) return brand;
    // 3. First meaningful line.
    for (const l of lines.slice(0, 8)) {
        if (l.length < 3 || l.length > 42) continue;
        if (/^\d/.test(l) || /\d{2}[.:]\d{2}/.test(l) || /www\.|http|@|steuer|ust-?id|tel\.?:/i.test(l)) continue;
        if (MERCHANT_SKIP.test(l) || ! /[a-zäöüß]/i.test(l)) continue;
        return cleanMerchant(l);
    }
    return '';
}

// An invoice/receipt number when the document labels one ("Rechnungsnr.", "Invoice No",
// "Beleg-Nr", …). Conservative — only after an explicit label, so a random order/customer
// number is not picked up. → the token, or ''.
const NUMBER_RE = /(?:rechnungs?\s*-?\s*(?:nr|nummer)|invoice\s*(?:no|number|#)|beleg\s*-?\s*nr|rg\s*-?\s*nr|receipt\s*(?:no|number))\.?\s*[:#]?\s*([A-Za-z]?[0-9][A-Za-z0-9./-]{1,24})/i;
export function extractNumber(text) {
    const m = String(text || '').match(NUMBER_RE);
    if (! m) return '';
    const t = m[1].replace(/[.,;:]+$/, '');
    if (/^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}$/.test(t)) return '';      // a numeric date, not a number
    if (/^\d{1,2}[.\/-][A-Za-z]{3,}[.\/-]\d{2,4}$/.test(t)) return ''; // "27-MAR-2025"
    if (t.replace(/[^A-Za-z0-9]/g, '').length < 3) return '';          // too short/ambiguous ("25")
    return t;
}

// The document's VAT rate → '19' | '16' | '7' | '0' | '' (matches the booking vatCat
// values, minus 'private'). Small-business / tax-free notes map to '0'; otherwise the
// HIGHEST explicit rate that appears on a line mentioning VAT (MwSt/USt/VAT/…) wins — the
// reduced 7 % on a mixed receipt shouldn't hide the 19 % that drives the tax. Line-based,
// so it needs the line-preserving PDF text extraction.
export function extractVatRate(text) {
    const s = String(text || '');
    if (/kleinunternehmer|§\s?19\s?ust|steuerfrei|reverse[-\s]?charge|nicht steuerbar|tax[-\s]?free/i.test(s)) return '0';
    const rates = new Set();
    for (const ln of s.split(/\r?\n/)) {
        if (! /mwst|ust\b|u\.?st\.?|umsatzsteuer|\bvat\b|\btax\b|zzgl|steuer/i.test(ln)) continue;
        for (const m of ln.matchAll(/\b(\d{1,2})(?:[.,]\d+)?\s*%/g)) if (['19', '16', '7'].includes(m[1])) rates.add(m[1]);
    }
    return rates.has('19') ? '19' : rates.has('16') ? '16' : rates.has('7') ? '7' : '';
}

// The document currency → 'USD' | 'GBP' | 'CHF' | 'EUR' | '' (default '' is treated as EUR
// downstream). Prefers an explicit ISO code, then a symbol; a bare '$' only wins when no €
// is present (many EU invoices show both a logo $ and a € total).
export function extractCurrency(text) {
    const s = String(text || '');
    const hasEur = /\bEUR\b|€/.test(s);
    if (/\bUSD\b|US\$/.test(s)) return 'USD';
    if (/\bGBP\b|£\s?\d/.test(s)) return 'GBP';
    if (/\bCHF\b/.test(s)) return 'CHF';
    if (hasEur) return 'EUR';
    if (/\$\s?\d/.test(s)) return 'USD';
    return '';
}

/**
 * Analyse a receipt's OCR text: { merchant, category, total, date, number, vat, currency,
 * tags[] }. tags are a de-duplicated suggestion (merchant + category) the user can edit.
 */
export function analyzeReceiptText(text) {
    const low = String(text || '').toLowerCase();
    let category = '';
    for (const [cat, re] of CATEGORY_RULES) { if (re.test(low)) { category = cat; break; } }
    const merchant = extractMerchant(text);
    const total = extractTotal(text);
    const date = extractDate(text);
    const number = extractNumber(text);
    const vat = extractVatRate(text);
    const currency = extractCurrency(text);
    const tags = [...new Set([merchant, category].filter(Boolean))];
    return { merchant, category, total, date, number, vat, currency, tags };
}
