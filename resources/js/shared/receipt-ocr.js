// Pattern recognition over a receipt's OCR/text (client-side, ZK). Suggests a category
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
    ['Software', /\bsoftware\b|lizenz|licen[sc]e|subscription|\bsaas\b|\badobe\b|microsoft|github|jetbrains|\bfigma\b|\bslack\b|\bzoom\b/i],
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

/** The receipt total: the amount on a "summe/gesamt/total/zu zahlen" line, else the max. */
export function extractTotal(text) {
    const lines = String(text || '').split(/\r?\n/);
    const amtRe = /\d{1,3}(?:[.\s]\d{3})*[.,]\d{2}\b/g;
    let labelled = null, max = null;
    for (const ln of lines) {
        const amounts = ln.match(amtRe);
        if (! amounts) continue;
        const vals = amounts.map(amount).filter((v) => v != null);
        for (const v of vals) if (max == null || v > max) max = v;
        if (/summe|gesamt|total|zu\s*zahlen|betrag|endbetrag|to pay|amount due/i.test(ln)) {
            const v = vals[vals.length - 1];
            if (v != null && (labelled == null || v > labelled)) labelled = v;
        }
    }
    return labelled ?? max;
}

/** First plausible date in the text → ISO yyyy-mm-dd, or ''. */
export function extractDate(text) {
    let m = String(text || '').match(/\b(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})\b/);
    if (m) { const y = m[3].length === 2 ? '20' + m[3] : m[3]; return `${y}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
    m = String(text || '').match(/\b(\d{4})-(\d{2})-(\d{2})\b/);
    return m ? `${m[1]}-${m[2]}-${m[3]}` : '';
}

/** The merchant name: the first meaningful early line (letters, not a number/date/URL). */
export function extractMerchant(text) {
    const lines = String(text || '').split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
    for (const l of lines.slice(0, 8)) {
        if (l.length < 3 || l.length > 42) continue;
        if (/^\d/.test(l) || /\d{2}[.:]\d{2}/.test(l) || /www\.|http|@|steuer|ust-?id|tel\.?:/i.test(l)) continue;
        // Skip label/heading lines that aren't the seller's name.
        if (/^(ihre|ihr|your|rechnung|invoice|beleg|quittung|datum|date|kunden|customer|seite|page|betreff|position)/i.test(l)) continue;
        if (! /[a-zäöüß]/i.test(l)) continue;
        return l.replace(/\s{2,}/g, ' ').trim();
    }
    return '';
}

/**
 * Analyse a receipt's OCR text: { merchant, category, total, date, tags[] }. tags are a
 * de-duplicated suggestion (merchant + category) the user can accept or edit on upload.
 */
export function analyzeReceiptText(text) {
    const low = String(text || '').toLowerCase();
    let category = '';
    for (const [cat, re] of CATEGORY_RULES) { if (re.test(low)) { category = cat; break; } }
    const merchant = extractMerchant(text);
    const total = extractTotal(text);
    const date = extractDate(text);
    const tags = [...new Set([merchant, category].filter(Boolean))];
    return { merchant, category, total, date, tags };
}
