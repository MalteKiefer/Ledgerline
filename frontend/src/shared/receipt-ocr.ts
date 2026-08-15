// Pattern recognition over a receipt/invoice's OCR/text-layer text (client-side).
// Suggests a category and tags on upload, and extracts merchant, address, total,
// date, invoice number, VAT rate, VAT-ID and currency — pure + testable. This is
// the recognition half of `POST /api/v1/invoices/ocr` (the server returns ONLY
// line-structured text — recognition lives here so it stays identical across
// web/iOS/Android and improvable without a server deploy). Heuristic, German-first
// (ported from the pre-SPA `shared/receipt-ocr.js`, plus VAT-ID extraction which
// never existed there).

export interface ReceiptAnalysis {
  merchant: string;
  category: string;
  total: number | null;
  date: string;
  number: string;
  vat: string;
  vatId: string;
  currency: string;
  orderRef: string;
  tags: string[];
}

// category → keyword pattern (first match wins; order = priority). Short/ambiguous
// tokens are anchored with \b on BOTH sides so e.g. "kündbar" doesn't match "bar"
// (→ Geschäftsessen), and generic words like "total"/"super" are avoided entirely.
const CATEGORY_RULES: [string, RegExp][] = [
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
  ['Steuerberatung', /steuerberat|buchf[üu]hrung|kontieren|jahresabschluss|gewinnermittlung|lohnbuchhaltung|wirtschaftspr[üu]f/i],
];

/** Parse a German/EN amount ("12,90", "1.234,56", "9.99") → number or null. */
function amount(s: string): number | null {
  let t = String(s).replace(/[^\d.,]/g, '');
  if (!t) return null;
  const lc = t.lastIndexOf(','); const ld = t.lastIndexOf('.');
  if (lc > ld) t = t.replace(/\./g, '').replace(',', '.');
  else if (ld > lc) t = t.replace(/,/g, '');
  else t = t.replace(',', '.');
  const n = parseFloat(t);
  return Number.isFinite(n) ? Math.round(n * 100) / 100 : null;
}

// Amounts on a line: decimal amounts anywhere, plus integers directly next to € / EUR
// (so "Total: 45 €" is caught but a bare year "2026" or quantity "3" is not). The
// integer part is EITHER a proper thousands-grouped run ("1.071" / "1 071") OR — since
// not every template groups thousands — a bare digit run ("1071"); grouped is tried
// first so "1.071,00" isn't misread as "071,00" via the ungrouped fallback.
function amountsIn(line: string): number[] {
  const out: number[] = [];
  const re = /(\d{1,3}(?:[.\s]\d{3})+[.,]\d{2}|\d+[.,]\d{2})|€\s*(\d{1,3}(?:[.\s]\d{3})*)(?![.,]\d)|(\d{1,3}(?:[.\s]\d{3})*)(?![.,]\d)\s*(?:€|eur\b)/gi;
  let m: RegExpExecArray | null;
  while ((m = re.exec(line))) { const v = amount(m[1] || m[2] || m[3]); if (v != null) out.push(v); }
  return out;
}

/**
 * The receipt total: the amount on a gross-total line (Gesamtsumme / Rechnungsbetrag /
 * Total …), else the max. "Amount due / zu zahlen" is ignored when 0 (a paid invoice
 * shows due = 0 but the real total is the paid gross, e.g. Mullvad "paid 60 / due 0").
 */
export function extractTotal(text: string): number | null {
  const lines = String(text || '').split(/\r?\n/);
  let labelled: number | null = null; let max: number | null = null;
  for (const ln of lines) {
    const vals = amountsIn(ln);
    if (!vals.length) continue;
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

const MONTHS: Record<string, number> = {
  januar: 1, februar: 2, 'märz': 3, maerz: 3, april: 4, mai: 5, juni: 6, juli: 7,
  august: 8, september: 9, oktober: 10, november: 11, dezember: 12,
  january: 1, february: 2, march: 3, june: 6, july: 7, october: 10, december: 12,
  jan: 1, feb: 2, mar: 3, apr: 4, jun: 6, jul: 7, aug: 8, sep: 9, sept: 9, oct: 10, okt: 10, nov: 11, dec: 12, dez: 12,
};

const okDate = (y: number, mo: number, d: number) => mo >= 1 && mo <= 12 && d >= 1 && d <= 31 && y >= 2000 && y <= 2100;

/** First plausible date in the text → ISO yyyy-mm-dd, or ''. Validates the day/month. */
export function extractDate(text: string): string {
  const s = String(text || '');
  // DD.MM.YYYY (day first — German/most receipts). Scan for the first VALID one.
  for (const mm of s.matchAll(/\b(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})\b/g)) {
    const y = mm[3].length === 2 ? 2000 + Number(mm[3]) : Number(mm[3]);
    const d = Number(mm[1]); const mo = Number(mm[2]);
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
const MERCHANT_SKIP = /^(ihre|ihr\b|your|rechnung|invoice|beleg|quittung|gutschrift|credit ?note|datum|date|kunde|customer|seite|page|betreff|subject|from\b|bill ?to|ship ?to|paid\b|vat\b|ust|steuer|item|menge|position|betrag|summe|total|details|leistungen|verkauft|sold by|umsatzsteuer|payment|sequenz|order\b|bestell|herrn\b|frau\b|firma\b|sehr geehrte)/i;
// "ab" (Swedish Aktiebolag, e.g. "Spotify AB") is deliberately EXCLUDED from the
// case-insensitive alternation below and checked separately, case-SENSITIVE
// (only ALL-CAPS "AB" counts) — "ab" is also an extremely common German word
// ("...buchen wir am 22.07. ab.") and lower/mixed-case "ab" inside a German
// sentence was misread as a company suffix, hijacking the merchant match onto
// that sentence instead of the real "Telekom Deutschland GmbH" letterhead line
// a few lines away (verified against a real Telekom invoice).
const COMPANY_SUFFIX = /\b(gmbh|mbh|ug|ag|kg|ohg|gbr|ltd|limited|llc|inc|corp|b\.?v\.?|s\.?[àa]\.?r\.?l|s\.?a\.?|oy|llp|plc)\b|& co/i;
const COMPANY_SUFFIX_AB = /\bAB\b/;
const hasCompanySuffix = (l: string): boolean => COMPANY_SUFFIX.test(l) || COMPANY_SUFFIX_AB.test(l);
// Collapse letter-spaced runs ("I n t e l l y T e c" → "IntellyTec"): 3+ single-letter
// tokens in a row (some PDFs render tracked/letter-spaced headings as separate glyphs).
const despace = (s: string) => String(s).replace(/(?:\b[A-Za-zÄÖÜäöü] ){2,}\b[A-Za-zÄÖÜäöü]\b/g, (m) => m.replace(/ /g, ''));
// Trim a letterhead line to just the company name: split on | / • / · separators, drop a
// trailing document word/label, and cut an address tail (", PF 3004", ", Industriestr. 25").
const cleanMerchant = (l: string) => despace(l).split(/\s*[|•·]\s*/)[0]
  .replace(/\s*(bill|ship)\s*to\b.*$/i, '')
  .replace(/\s+(place\s*\/?\s*date|place of invoice|date of invoice|invoice (requested|number|date|no)\b|customer\b|kundennummer\b).*$/i, '')
  .replace(/,?\s*(pf\b|postfach|\d|[^,]*(?:stra(?:ß|ss)e|str\.|weg|ring|platz|allee|gasse)\b).*$/i, '')
  .replace(/\s+(invoice|rechnung|receipt|quittung|beleg)\s*$/i, '')
  .replace(/\s{2,}/g, ' ').trim().slice(0, 50);

// Well-known brands whose invoices don't carry a clean "Brand GmbH" letterhead line
// (marketplaces, US companies, e-mail receipts, retailers). Used as a merchant fallback.
const BRANDS: [string, RegExp][] = [
  ['Amazon', /\bamazon\b/i], ['Apple', /\bapple\b/i], ['Google', /google/i], ['PayPal', /paypal/i],
  ['Backblaze', /backblaze/i], ['Microsoft', /microsoft/i], ['Netflix', /netflix/i], ['Spotify', /spotify/i],
  ['eBay', /\bebay\b/i], ['Dropbox', /dropbox/i], ['Cloudflare', /cloudflare/i], ['Adobe', /\badobe\b/i],
  ['DeepL', /\bdeepl\b/i], ['Telekom', /\btelekom\b|magenta/i], ['Vodafone', /vodafone/i],
  ['Kaufland', /kaufland/i], ['Edeka', /\bedeka\b/i], ['REWE', /\brewe\b/i], ['Lidl', /\blidl\b/i], ['Aldi', /\baldi\b/i],
  ['IKEA', /\bikea\b/i], ['Deutsche Bahn', /deutsche bahn|\bbahn\.de\b/i], ['Hetzner', /hetzner/i], ['netcup', /netcup/i],
];
export function detectBrand(text: string): string { for (const [n, re] of BRANDS) if (re.test(String(text || ''))) return n; return ''; }

// A contact-detail line (phone/fax/website/tax-id) — never a merchant name on its own,
// but on a merged multi-column letterhead (pdftotext -layout flattens side-by-side
// columns onto one line) it can appear glued to an unrelated name; excluding it keeps
// that glued line out of the "first meaningful line" fallback entirely.
const CONTACT_LINE = /www\.|https?:|@|steuer(?:nummer)?|ust-?id|\btel(?:efon)?\.?:|\bfax\.?:|\btelefax\.?:|\bmobil:/i;

/**
 * The merchant/seller name. Prefer a line carrying a company legal form (GmbH, Ltd, LLC,
 * AB, …) in the letterhead — that is almost always the seller, not the recipient or a
 * label — then a known brand, then the first meaningful line. `excludeNames` (the
 * viewer's own name/company, when known) filters out lines that are just the
 * RECIPIENT's own letterhead re-appearing in a merged multi-column layout — otherwise a
 * scanned invoice can misidentify the document's owner as its own merchant.
 */
export function extractMerchant(text: string, excludeNames: string[] = []): string {
  const lines = String(text || '').split(/\r?\n/).map((s) => s.replace(/\s{2,}/g, ' ').trim()).filter(Boolean);
  const own = excludeNames.map((n) => n.trim().toLowerCase()).filter((n) => n.length >= 3);
  const isOwn = (l: string) => own.some((n) => l.toLowerCase().includes(n));
  // 1. A company-legal-form line in the letterhead — almost always the seller.
  for (const l of lines.slice(0, 15)) {
    if (l.length < 3 || MERCHANT_SKIP.test(l) || isOwn(l) || !hasCompanySuffix(l)) continue;
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
    if (/^\d/.test(l) || /\d{2}[.:]\d{2}/.test(l) || CONTACT_LINE.test(l)) continue;
    if (MERCHANT_SKIP.test(l) || isOwn(l) || !/[a-zäöüß]/i.test(l)) continue;
    return cleanMerchant(l);
  }
  return '';
}

// An invoice/receipt number when the document labels one ("Rechnungsnr.", "Invoice No",
// "Beleg-Nr", …). Conservative — only after an explicit label, and the value MUST sit
// on the same line as the label ([^\S\r\n] between label and value, never a bare \s
// that could also match a newline): a two-column layout can place a payment-
// instruction sentence ("Bitte nutzen Sie Ihre Rechnungs-Nr. als Verwendungszweck…")
// at the same text-extraction y-position as the recipient address block, ending the
// line right after the label with nothing else on it — an unanchored \s* would then
// cross the newline and let the address on the NEXT line satisfy the capture group as
// a plausible-looking "number" (netcup: matched "DE-95512Neudross" out of
// "DE-95512 Neudrossenfeld"). Requiring same-line correctly still matches labels that
// share a line with unrelated text before them (e.g. fonial's two-column
// "Kiefer Networks   Rechnungsnummer:   2026061702224").
// The value may contain internal spaces between digit groups (e.g. Telekom's
// "25 5828 2901 2681") — [^\S\r\n] keeps that on the same line without also
// swallowing the next line the way a bare \s would.
const NUMBER_RE = /(?:rechnungs?\s*-?\s*(?:nr|nummer)|invoice\s*(?:no|number|#)|beleg\s*-?\s*nr|rg\s*-?\s*nr|receipt\s*(?:no|number))\.?[^\S\r\n]*[:#]?[^\S\r\n]*([A-Za-z]{0,3}-?[0-9][A-Za-z0-9./-]{0,24}(?:[^\S\r\n][A-Za-z0-9./-]{1,8}){0,4})/i;
export function extractNumber(text: string): string {
  const m = String(text || '').match(NUMBER_RE);
  if (!m) return '';
  const t = m[1].replace(/\s+/g, '').replace(/[.,;:]+$/, '');
  if (/^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}$/.test(t)) return '';      // a numeric date, not a number
  if (/^\d{1,2}[.\/-][A-Za-z]{3,}[.\/-]\d{2,4}$/.test(t)) return ''; // "27-MAR-2025"
  if (t.replace(/[^A-Za-z0-9]/g, '').length < 3) return '';          // too short/ambiguous ("25")
  return t;
}

// The document's VAT rate → '19' | '16' | '7' | '0' | '' (matches the booking vatCat
// values, minus 'private'). Small-business / tax-free notes map to '0'; otherwise the
// HIGHEST explicit rate on a VAT-mentioning line wins — line-based, needs the
// line-preserving PDF text extraction (the server's text endpoint already gives that).
export function extractVatRate(text: string): string {
  const s = String(text || '');
  if (/kleinunternehmer|§\s?19\s?ust|steuerfrei|reverse[-\s]?charge|nicht steuerbar|tax[-\s]?free/i.test(s)) return '0';
  const rates = new Set<string>();
  for (const ln of s.split(/\r?\n/)) {
    if (!/mwst|ust\b|u\.?st\.?|umsatzsteuer|\bvat\b|\btax\b|zzgl|steuer/i.test(ln)) continue;
    for (const m of ln.matchAll(/\b(\d{1,2})(?:[.,]\d+)?\s*%/g)) if (['19', '16', '7'].includes(m[1])) rates.add(m[1]);
  }
  return rates.has('19') ? '19' : rates.has('16') ? '16' : rates.has('7') ? '7' : '';
}

// EU VAT-ID (Umsatzsteuer-Identifikationsnummer): 2-letter country code + 8-12
// alphanumeric characters, normalised (spaces/dots/dashes stripped). German DE + 9
// digits is checked with a stricter digit-only pattern; other member states use a
// looser alphanumeric shape (e.g. Austria ATU12345678, Netherlands NL123456789B01).
// [^\S\r\n] (space/tab, not newline) inside the captured value keeps a greedy match on
// the SAME line — a bare \s would happily cross into the next line and swallow it too.
const VAT_ID_LABEL = /(?:\buid\b|ust\.?[-\s]?id(?:\.?-?nr\.?)?|umsatzsteuer[-\s]?id(?:entifikationsnummer)?|vat\s*(?:reg(?:istration)?)?\.?\s*(?:no|number|id)|tax\s*id)\.?[^\S\r\n]*[:#]?[^\S\r\n]*([A-Za-z]{2}[A-Za-z0-9 .-]{4,16})/i;
const VAT_ID_BARE = /\b(DE\d{9}|ATU?\d{8}|BE0?\d{9,10}|NL\d{9}B\d{2}|FR[A-Z0-9]{2}\d{9}|IT\d{11}|ES[A-Z0-9]\d{7}[A-Z0-9]|CHE\d{9})\b/i;

/** The seller's EU VAT-ID (Ust-IdNr.), normalised (no spaces/dots/dashes), or ''. */
export function extractVatId(text: string): string {
  const s = String(text || '');
  const labelled = s.match(VAT_ID_LABEL);
  const raw = labelled ? labelled[1] : (s.match(VAT_ID_BARE)?.[1] ?? '');
  const norm = raw.replace(/[\s.-]/g, '').toUpperCase();
  if (!/^[A-Z]{2}[A-Z0-9]{2,12}$/.test(norm)) return '';
  if (norm.startsWith('DE') && !/^DE\d{9}$/.test(norm)) return ''; // DE is digit-only; reject a bad OCR read
  return norm;
}

// A merchant-printed payment/order reference that groups several documents belonging
// to one charge (Amazon prints "Zahlungsreferenznummer" on every invoice of an order —
// when a shipment splits into several invoices, ALL of them repeat the SAME reference,
// which is what lets the matcher sum them onto the one card-statement line). Kept
// generically named (not "amazonRef") since other marketplaces may print an equivalent
// field under a different label later. [^\S\r\n] keeps the match on one line.
const ORDER_REF_RE = /zahlungsreferenznummer[^\S\r\n]*[:#]?[^\S\r\n]*([A-Za-z0-9]{6,32})/i;

/** A merchant-printed payment/order reference (e.g. Amazon's Zahlungsreferenznummer), or ''. */
export function extractOrderRef(text: string): string {
  const m = String(text || '').match(ORDER_REF_RE);
  return m ? m[1] : '';
}

// The document currency → 'USD' | 'GBP' | 'CHF' | 'EUR' | '' (default '' is treated as EUR
// downstream). Prefers an explicit ISO code, then a symbol; a bare '$' only wins when no €
// is present (many EU invoices show both a logo $ and a € total).
export function extractCurrency(text: string): string {
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
 * A suggested document name: "YYYYMMDD; Issuer; InvoiceNumber" — sorts
 * chronologically, groups by issuer, and the invoice number makes it possible to
 * find a specific document again. A part that wasn't recognised is simply
 * omitted (never a placeholder); `;` and filesystem-hostile characters are
 * stripped from the free-text parts so the format stays parseable.
 */
export function buildReceiptName(date: string, merchant: string, number: string): string {
  const clean = (s: string) => String(s || '').replace(/[;/\\:*?"<>|]/g, '').replace(/\s{2,}/g, ' ').trim();
  const parts = [date ? date.replace(/-/g, '') : '', clean(merchant), clean(number)].filter(Boolean);
  return parts.join('; ');
}

/**
 * Analyse a receipt/invoice's OCR text into structured, editable fields.
 * `excludeNames` — the viewer's own name/company, when known (e.g. from the
 * company profile) — keeps a merged multi-column letterhead from misreading the
 * document's OWNER as its own merchant.
 */
export function analyzeReceiptText(text: string, excludeNames: string[] = []): ReceiptAnalysis {
  const low = String(text || '').toLowerCase();
  let category = '';
  for (const [cat, re] of CATEGORY_RULES) { if (re.test(low)) { category = cat; break; } }
  const merchant = extractMerchant(text, excludeNames);
  const total = extractTotal(text);
  const date = extractDate(text);
  const number = extractNumber(text);
  const vat = extractVatRate(text);
  const vatId = extractVatId(text);
  const currency = extractCurrency(text);
  const orderRef = extractOrderRef(text);
  const tags = [...new Set([merchant, category].filter(Boolean))];
  return { merchant, category, total, date, number, vat, vatId, currency, orderRef, tags };
}
