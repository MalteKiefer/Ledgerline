// Pattern recognition over a receipt's OCR/text (client-side, ZK). Suggests a category
// and tags on upload and extracts the merchant, total and date — pure + testable. The
// same structured fields can later feed a Paperless push. Heuristic, German-first.

// category → keyword pattern (first match wins; order = priority)
const CATEGORY_RULES = [
    ['Geschäftsessen', /restaurant|gastst[äa]tte|pizzeria|trattoria|bistro|imbiss|caf[ée]|kaffee|bar\b|brauhaus|wirtshaus|men[üu]|speisekarte|trinkgeld|bewirtung|mcdonald|burger|doner|d[öo]ner/i],
    ['Reisekosten', /hotel|[üu]bernachtung|pension|hostel|db\b|deutsche bahn|bahn|ticket|flug|airline|lufthansa|ryanair|taxi|mietwagen|rental|boarding/i],
    ['Kfz', /tankstelle|aral|shell|esso|jet\b|total\b|agip|omv|star\b|diesel|super\s?e?\d?\d?|benzin|kraftstoff|kfz|werkstatt|adac/i],
    ['Bürobedarf', /b[üu]robedarf|staples|office|schreibwaren|papier|toner|druckerpatrone|ordner|kugelschreiber/i],
    ['Software', /software|lizenz|licen[sc]e|subscription|abo\b|saas|adobe|microsoft|github|jetbrains|figma|slack|zoom/i],
    ['Hardware', /media\s?markt|saturn|notebook|laptop|monitor|tastatur|festplatte|ssd|arbeitsspeicher|hardware|conrad|reichelt/i],
    ['Telekommunikation', /telekom|vodafone|o2\b|1&1|mobilfunk|internet|dsl|glasfaser|prepaid/i],
    ['Marketing', /werbung|anzeige|marketing|google ads|facebook ads|meta platforms|kampagne/i],
    ['Versicherung', /versicherung|allianz|axa|huk|police|beitrag/i],
    ['Fortbildung', /seminar|schulung|fortbildung|kurs\b|training|udemy|coursera|konferenz|workshop/i],
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
