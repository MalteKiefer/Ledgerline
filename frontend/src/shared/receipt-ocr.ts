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
// first so "1.071,00" isn't misread as "071,00" via the ungrouped fallback. The
// trailing `(?!\d)` on both decimal alternatives rejects a MORE-than-2-digit tail: a
// three-group thousands figure with no cents at all ("791.004", a KB data-volume
// reading) would otherwise be read as "791.00" by silently truncating the extra
// trailing digit — verified against a real Telekom invoice whose "Insgesamt
// verbrauchtes Datenvolumen: 791.004 KB" line got picked as the receipt total
// (791,00 €) over the real "Rechnungsbetrag 39,85 €".
// The spelled-out currency word ("EUR"/"Euro") is matched letter-tolerant — a real
// self-generated Eigenbeleg PDF prints "150 E u r o" (each letter individually
// positioned, a PDF text-layer justification artifact, same class as "W a l d k
// i r c h" seen on an address line elsewhere) — `eur\b` alone can't match that,
// and neither can it match plain "Euro" at all (its own trailing "o" blocks the
// \b). `e[ \t]?u[ \t]?r[ \t]?o\b` accepts zero OR one space between each letter,
// so it covers both the compact and the letter-spaced form while still rejecting
// a real following word ("Eurozone", "Euroschein" — \b still applies at the end).
const CURRENCY_WORD = 'eur\\b|e[ \\t]?u[ \\t]?r[ \\t]?o\\b';
// The bare-integer amount is EITHER a proper thousands-grouped run OR a plain
// digit run of 4+ (no separator at all) — a real self-issued Eigenbeleg writes
// a whole-euro amount by hand with no grouping ("3741 Euro"). Without the 4+
// fallback, `\d{1,3}` alone caps the FIRST attempt at 3 digits, that capture
// gets rejected by the trailing (?!\d) guard (a 4th digit still follows), and
// the global regex scan then retries starting at the 2nd digit instead —
// silently truncating "3741" down to just "741". A short bare run (1-3 bare
// digits with nothing after) is intentionally NOT extended this way: it stays
// capped so an adjacent bare year etc. can't be misread as a 4+ digit amount.
const BARE_DIGITS = '\\d{1,3}(?:[.\\s]\\d{3})*|\\d{4,}';
function amountsIn(line: string): number[] {
  const out: number[] = [];
  const re = new RegExp(
    `(\\d{1,3}(?:[.\\s]\\d{3})+[.,]\\d{2}(?!\\d)|\\d+[.,]\\d{2}(?!\\d))|` +
    `€[ \\t]{0,3}(${BARE_DIGITS})(?![.,]\\d)(?!\\d)|` +
    `(${BARE_DIGITS})(?![.,]\\d)(?!\\d)[ \\t]{0,3}(?:€|${CURRENCY_WORD})`,
    'gi',
  );
  let m: RegExpExecArray | null;
  while ((m = re.exec(line))) { const v = amount(m[1] || m[2] || m[3]); if (v != null) out.push(v); }
  return out;
}

/**
 * The receipt total: the amount on a gross-total line (Gesamtsumme / Rechnungsbetrag /
 * Total …), else the max. "Amount due / zu zahlen" is ignored when 0 (a paid invoice
 * shows due = 0 but the real total is the paid gross, e.g. Mullvad "paid 60 / due 0").
 */
const TOTAL_EXCLUDE_RE = /zwischensumme|zwischensal|nettosumme|nettobetrag|nettogesamt|netto-?summe|subtotal|\bmwst\b|umsatzsteuer|\bust\b|mehrwertsteuer|\bvat\b|sales tax/i;
// "gesamt" is intentionally left unanchored: Backblaze prints its own total as
// a bare "Insgesamt: ($2.57)" line (verified real invoice) — restricting to a
// leading \b would reject that genuine label along with the German ADVERB
// "insgesamt" ("in total", e.g. "Insgesamt verbrauchtes Datenvolumen"), which
// is excluded instead at the source: the digit over-match fix in amountsIn()
// above already keeps a bare data-volume figure like "791.004 KB" from ever
// producing a value on that line, so it never reaches this label check at all.
const TOTAL_LABEL_RE = /summe|gesamt|rechnungsbetrag|endbetrag|grand total|\btotal\b|amount paid|\bpaid\b|bezahlt|gezahlt|zu zahlen/i;
export function extractTotal(text: string): number | null {
  const lines = String(text || '').split(/\r?\n/);
  let labelled: number | null = null; let max: number | null = null;
  for (let i = 0; i < lines.length; i++) {
    const ln = lines[i];
    const vals = amountsIn(ln);
    if (!vals.length) continue;
    for (const v of vals) if (max == null || v > max) max = v;
    // Net subtotal / tax lines are NOT the payable gross — "Zwischensumme" matches the
    // "summe" keyword but is the net amount (Apple/iCloud 8,40 net vs 9,99 gross).
    if (TOTAL_EXCLUDE_RE.test(ln)) continue;
    let isLabelled = TOTAL_LABEL_RE.test(ln);
    // A "hero" total figure can sit alone on its own line, with the caption label
    // printed on the very next line instead of alongside it — a real Grover
    // subscription invoice does exactly this ("24,80 €" on one line, "ZU ZAHLEN"
    // on the line right after). Restricted to a BARE value line (nothing but the
    // number itself, once whitespace/currency-symbol padding is stripped) — a
    // real invoice's date-range line ("... 26.08.2024 - 25.09.2024", itself a
    // date misread as a decimal amount) must NOT qualify just because an
    // unrelated "BEZAHLT" badge happens to appear several blank lines later;
    // it carries other real text (a company name, a label word), so it's
    // rejected by this bareness check before the label search even runs.
    if (!isLabelled && /^[\d.,\s€]+$/.test(ln)) {
      let j = i + 1;
      while (j < lines.length && lines[j].trim() === '') j++;
      if (j < lines.length && !TOTAL_EXCLUDE_RE.test(lines[j]) && TOTAL_LABEL_RE.test(lines[j])) isLabelled = true;
    }
    if (isLabelled) {
      const v = vals[vals.length - 1];
      if (v != null && v !== 0 && (labelled == null || v > labelled)) labelled = v;
    }
  }
  return labelled ?? max;
}

const MONTHS: Record<string, number> = {
  januar: 1, februar: 2, 'märz': 3, maerz: 3, april: 4, mai: 5, juni: 6, juli: 7,
  august: 8, september: 9, oktober: 10, november: 11, dezember: 12,
  january: 1, february: 2, march: 3, may: 5, june: 6, july: 7, october: 10, december: 12,
  jan: 1, feb: 2, mar: 3, apr: 4, jun: 6, jul: 7, aug: 8, sep: 9, sept: 9, oct: 10, okt: 10, nov: 11, dec: 12, dez: 12,
};

const okDate = (y: number, mo: number, d: number) => mo >= 1 && mo <= 12 && d >= 1 && d <= 31 && y >= 2000 && y <= 2100;

// A date next to an explicit label ("Datum: 11.03.2026", "Rechnungsdatum
// 22.06.2026", "Invoice Date: 2026/06/23") is checked FIRST, before any bare date
// found elsewhere in the document. Without this, an earlier UNLABELLED date that
// happens to appear first in the text-extraction order wins over the document's
// own real date — verified against a real Telekom invoice: its own "Datum
// 11.06.2026" line lost to an earlier, unlabelled SEPA-debit sentence ("Den
// Betrag von 39,85 € buchen wir am 23.06.2026 ab.") purely because of where each
// happened to land in the extracted text. The captured value can be either
// day-first (DD.MM.YYYY) or year-first (YYYY/MM/DD, e.g. a Ubiquiti invoice) —
// disambiguated by whether the first captured number is 4 digits.
const DATE_LABEL_RE = /(?:rechnungsdatum|invoice\s*date|date\s*of\s*invoice|belegdatum|\bdatum)\b\.?[^\S\r\n]*:?[^\S\r\n]*(\d{1,4})[.\/-](\d{1,2})[.\/-](\d{1,4})/i;
function extractLabelledDate(s: string): string {
  const m = s.match(DATE_LABEL_RE);
  if (!m) return '';
  const [, a, b, c] = m;
  if (a.length === 4) {
    const y = Number(a); const mo = Number(b); const d = Number(c);
    return okDate(y, mo, d) ? `${a}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}` : '';
  }
  const d = Number(a); const mo = Number(b);
  const y = c.length === 2 ? 2000 + Number(c) : Number(c);
  return okDate(y, mo, d) ? `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}` : '';
}

/** First plausible date in the text → ISO yyyy-mm-dd, or ''. Validates the day/month. */
export function extractDate(text: string): string {
  const s = String(text || '');
  const labelled = extractLabelledDate(s);
  if (labelled) return labelled;
  // DD.MM.YYYY (day first — German/most receipts). Scan for the first VALID one.
  for (const mm of s.matchAll(/\b(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})\b/g)) {
    const y = mm[3].length === 2 ? 2000 + Number(mm[3]) : Number(mm[3]);
    const d = Number(mm[1]); const mo = Number(mm[2]);
    if (okDate(y, mo, d)) return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  }
  // ISO order, hyphen OR slash (e.g. a Ubiquiti receipt prints "2026/06/23").
  let m = s.match(/\b(\d{4})[\/-](\d{2})[\/-](\d{2})\b/);
  if (m && okDate(Number(m[1]), Number(m[2]), Number(m[3]))) return `${m[1]}-${m[2]}-${m[3]}`;
  // "27. Juli 2026" / "27-MAR-2025" / "24/Sep/2024" (a real Tresorit invoice's
  // "Invoice Date:  24/Sep/2024" — day, month name, slash separators) — day,
  // month name (space/dot/dash/slash separators).
  m = s.match(/\b(\d{1,2})[.\s\/-]+([A-Za-zäöüÄÖÜ]{3,})[.\s\/-]+(\d{4})\b/);
  if (m) { const mo = MONTHS[m[2].toLowerCase()]; if (mo) return `${m[3]}-${String(mo).padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
  m = s.match(/\b([A-Za-zäöüÄÖÜ]+)\.?\s+(\d{1,2}),?\s+(\d{4})\b/);
  if (m) { const mo = MONTHS[m[1].toLowerCase()]; if (mo) return `${m[3]}-${String(mo).padStart(2, '0')}-${m[2].padStart(2, '0')}`; }
  return '';
}

// Prefix match (no trailing \b) so German compounds like "Rechnungsdatum" /
// "Kundennummer" are skipped too. Kept specific to avoid eating real names.
// "ausstellungsdatum"/"fällig" don't start with the generic "date"/"rechnung"
// prefixes above (they're their own German compounds) — a real ente.io invoice
// left both unskipped, and cleanMerchant's address-tail stripper (which cuts
// off everything from the first digit onward, meant for a housenumber) then
// silently ate the trailing date off "Ausstellungsdatum 1. September 2024",
// leaving the bare label word "Ausstellungsdatum" as the returned "merchant".
const MERCHANT_SKIP = /^(ihre|ihr\b|your|rechnung|invoice|receipt\b|beleg|quittung|gutschrift|credit ?note|datum|date|kunde|customer|seite|page|betreff|subject|from\b|bill ?to|ship ?to|paid\b|vat\b|ust|steuer|item|menge|position|betrag|summe|total|details|leistungen|verkauft|sold by|umsatzsteuer|payment|sequenz|order\b|bestell|herrn\b|frau\b|firma\b|sehr geehrte|ausstellungsdatum|f[äa]llig)/i;
// "ab" (Swedish Aktiebolag, e.g. "Spotify AB") is deliberately EXCLUDED from the
// case-insensitive alternation below and checked separately, case-SENSITIVE
// (only ALL-CAPS "AB" counts) — "ab" is also an extremely common German word
// ("...buchen wir am 22.07. ab.") and lower/mixed-case "ab" inside a German
// sentence was misread as a company suffix, hijacking the merchant match onto
// that sentence instead of the real "Telekom Deutschland GmbH" letterhead line
// a few lines away (verified against a real Telekom invoice).
const COMPANY_SUFFIX = /\b(gmbh|mbh|ug|ag|kg|ohg|gbr|ltd|limited|llc|inc|corp(?:oration)?|b\.?v\.?|s\.?[àa]\.?r\.?l|s\.?a\.?|oy|llp|plc)\b|& co/i;
const COMPANY_SUFFIX_AB = /\bAB\b/;
const hasCompanySuffix = (l: string): boolean => COMPANY_SUFFIX.test(l) || COMPANY_SUFFIX_AB.test(l);
// Collapse letter-spaced runs ("I n t e l l y T e c" → "IntellyTec"): 3+ single-letter
// tokens in a row (some PDFs render tracked/letter-spaced headings as separate glyphs).
const despace = (s: string) => String(s).replace(/(?:\b[A-Za-zÄÖÜäöü] ){2,}\b[A-Za-zÄÖÜäöü]\b/g, (m) => m.replace(/ /g, ''));
// Trim a letterhead line to just the company name: split on | / • / · separators
// (or a bare "." used the same way, WHITESPACE ON BOTH SIDES ONLY — a real
// IntellyTec letterhead reads "IntellyTec GmbH . Grünenborn 1 . 53797 Lohmar";
// requiring space on both sides keeps a genuine abbreviation period like
// "Str." or "Msg." — which only has a following space, never a leading one —
// from being misread as a separator), drop a trailing document word/label,
// and cut an address tail (", PF 3004", ", Industriestr. 25"). "Rechnungs-
// empfänger"/"Empfänger"/"recipient" (a column-header word, "invoice
// recipient") is stripped the same way as "customer"/"kundennummer" — a real
// ente.io invoice merges "ente.io" (the real merchant) with that header word on
// one pdftotext-flattened two-column line ("ente.io Rechnungsempfänger").
const cleanMerchant = (l: string) => despace(l).split(/\s*[|•·]\s*|\s+\.\s+/)[0]
  .replace(/\s*(bill|ship)\s*to\b.*$/i, '')
  .replace(/\s+(place\s*\/?\s*date|place of invoice|date of invoice|invoice (requested|number|date|no)\b|customer\b|kundennummer\b|rechnungsempf[äa]nger\b|empf[äa]nger\b|recipient\b).*$/i, '')
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
  // Grover: its consumer-subscription template puts a generic "Deine Rechnung
  // BEZAHLT" status header up top and only names "Grover Deutschland GmbH" in
  // the footer imprint — too far down for the letterhead scan (step 1) to see,
  // and a real invoice's footer-scoped fallback attempt regressed several
  // OTHER, already-correct brand matches (Microsoft/Apple/Amazon's own EU
  // legal-entity disclaimers sit in the same footer zone and outranked their
  // short, established brand name) — a dedicated keyword is the safe fix.
  ['Grover', /\bgrover\b/i],
  // Tresorit: same shape as Grover — its own "Tresorit AG" legal-entity line
  // sits only in the footer imprint, well past the first-15-line letterhead
  // window and the first-8-line fallback scan; the header instead opens with
  // the customer's own name/address (a real invoice was misread as the
  // customer's own city, "Neudrossenfeld", for lack of any earlier candidate).
  ['Tresorit', /\btresorit\b/i],
];
export function detectBrand(text: string): string { for (const [n, re] of BRANDS) if (re.test(String(text || ''))) return n; return ''; }

// A payment-method sentence ("wurde per PayPal bezahlt", "bezahlt mit Kreditkarte",
// "Kreditkarte mit den Endziffern …", "paid via PayPal") names how the invoice was
// SETTLED, not who ISSUED it. Left in the brand-detection text, "PayPal" (a real
// BRANDS entry, since PayPal issues its own transaction receipts too) would hijack
// the match on ANY unrelated seller's invoice that happened to be paid through it —
// verified against a real invoice (Andy Hempel/datonga.com, a rack-mount seller
// with no legal-form suffix on his letterhead) that was misdetected as "PayPal"
// purely because of the trailing "Der Rechnungsbetrag wurde per PayPal bezahlt."
// line. Lines matching this are excluded before the brand scan.
const PAYMENT_METHOD_LINE = /\b(bezahlt|gezahlt|zahlungsart|payment\s*method|paid)\b.{0,40}\b(paypal|kreditkarte|credit\s*card|lastschrift|klarna|sofort[üu]berweisung|giropay|debit\s*card|banküberweisung)\b|\b(paypal|kreditkarte|credit\s*card|lastschrift|klarna|debit\s*card)\b.{0,40}\b(bezahlt|gezahlt|paid)\b/i;
function stripPaymentMethodLines(text: string): string {
  return String(text || '').split(/\r?\n/).filter((l) => !PAYMENT_METHOD_LINE.test(l)).join('\n');
}

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
  // A merged two-column line ("A Medium Corporation    Kiefer Networks" — the
  // real seller on the left, the recipient's own name on the right, flattened
  // onto one row by pdftotext -layout, then collapsed to single spaces here)
  // previously lost the WHOLE line to isOwn — even though the seller portion
  // before the own name is perfectly valid. Cut from the own name onward
  // instead (same shape as the customer/kundennummer/address-tail strips in
  // cleanMerchant below) and keep re-validating the REMAINDER; a line that's
  // nothing BUT the own name (no real content before it) still reduces to an
  // empty/too-short string and is rejected exactly as before.
  const escapeRe = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const ownStripRe = own.length ? new RegExp(`\\s*(?:${own.map(escapeRe).join('|')}).*$`, 'i') : null;
  const stripOwnTail = (l: string): string => (ownStripRe ? l.replace(ownStripRe, '').trim() : l);
  // 1. A company-legal-form line in the letterhead — almost always the seller.
  for (const l of lines.slice(0, 15)) {
    if (l.length < 3 || MERCHANT_SKIP.test(l)) continue;
    const candidate = isOwn(l) ? stripOwnTail(l) : l;
    if (candidate.length < 3 || isOwn(candidate) || !hasCompanySuffix(candidate)) continue;
    const c = cleanMerchant(candidate);
    if (c.length >= 3 && c.length <= 50) return c;
  }
  // 2. A known brand keyword (Amazon, Adobe, Telekom, Kaufland, …) — beats a random
  //    first line, which is often the recipient, a greeting or a table header. A
  //    payment-method sentence ("wurde per PayPal bezahlt") is excluded first so a
  //    payment PROCESSOR mention never outranks the document's actual issuer.
  const brand = detectBrand(stripPaymentMethodLines(String(text || '')));
  if (brand) return brand;
  // 3. First meaningful line. The length cap applies to the CLEANED candidate, not
  //    the raw line — a letterhead line often carries a "| address" tail via
  //    cleanMerchant's pipe-split ("Andy Hempel | Anemonenweg 24 | 71672 Marbach am
  //    Neckar") that pushes the raw line past any reasonable cap while the company
  //    name itself is short; checking the raw line first rejected such letterheads
  //    outright and fell through to an empty merchant (verified against the same
  //    Andy Hempel/datonga.com invoice from the brand-hijack fix above).
  for (const l of lines.slice(0, 8)) {
    if (l.length < 3) continue;
    if (/^\d/.test(l) || /\d{2}[.:]\d{2}/.test(l) || CONTACT_LINE.test(l)) continue;
    // A label ending in a bare colon with nothing after it ("PO Number:") is an
    // empty form field, not a company name — real letterheads never end in ":".
    if (l.trimEnd().endsWith(':')) continue;
    if (MERCHANT_SKIP.test(l) || !/[a-zäöüß]/i.test(l)) continue;
    const candidate = isOwn(l) ? stripOwnTail(l) : l;
    if (candidate.length < 3 || isOwn(candidate)) continue;
    const c = cleanMerchant(candidate);
    if (c.length < 3 || c.length > 50) continue;
    // cleanMerchant's address-tail stripper cuts a candidate off at its first
    // bare digit — meant to remove a trailing housenumber ("Musterstr. 5" ->
    // "Musterstr."), but on a merged two-column line where the OWN address sits
    // beside an unrelated label ("Adalbert-Stifter-Str. 6  Account Number:
    // A01694959" — a real Tresorit invoice's customer address block, glued to
    // the account-number field by pdftotext), what survives is a BARE street
    // name with nothing else — never a real company name on its own. Rejecting
    // it here lets the loop fall through to the next candidate line instead.
    if (/\b(stra(?:ß|ss)e|str\.|weg|ring|platz|allee|gasse)\.?$/i.test(c)) continue;
    return c;
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
// swallowing the next line the way a bare \s would. IDs sometimes carry an
// underscore (Backblaze's "021abe1f7af3_158"), hence "_" in the value classes.
const NUMBER_VALUE = '([A-Za-z]{0,3}-?[0-9][A-Za-z0-9_./-]{0,24}(?:[^\\S\\r\\n][A-Za-z0-9_./-]{1,8}){0,4})';
const NUMBER_RE = new RegExp(`(?:rechnungs?\\s*-?\\s*(?:nr|nummer)|invoice\\s*(?:no|number|#)|beleg\\s*-?\\s*nr|rg\\s*-?\\s*nr|receipt\\s*(?:no|number))\\.?[^\\S\\r\\n]*[:#]?[^\\S\\r\\n]*${NUMBER_VALUE}`, 'i');
// A bare "Rechnung"/"Invoice"/"Receipt" — no "-nr"/"-nummer" suffix — covers
// phrasing like a dunning letter's "Ihrer Rechnung nc-5287300" or "unserer
// Rechnung nc-5287300 vom …". Safe against prose because the value group
// still requires a digit within the first few characters, so it can't match
// a sentence continuing after the word ("Rechnungsdatum", "Rechnung wurde …")
// — but a bare small number ("Rechnung 2026") could coincidentally be a year,
// not an ID, so this path additionally requires the value to contain a
// letter or be at least 5 digits long (checked in extractNumber below). Unlike
// NUMBER_VALUE above, this is a SINGLE contiguous token with no multi-chunk
// continuation: that clause exists for Telekom's space-grouped digit format
// after an explicit label, but here — with only a bare, much weaker trigger
// word — it would happily annex the next word of an ordinary sentence too
// ("Rechnung 2026 sorgfältig" → "2026sorgfä", which still "looks like an ID").
const NUMBER_VALUE_BARE = '([A-Za-z]{0,3}-?[0-9][A-Za-z0-9_./-]{0,24})';
const NUMBER_RE_BARE = new RegExp(`\\b(?:rechnung|invoice|receipt)\\.?[^\\S\\r\\n]*[:#]?[^\\S\\r\\n]*${NUMBER_VALUE_BARE}`, 'i');
function isPlausibleNumber(t: string): boolean {
  if (/^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}$/.test(t)) return false;      // a numeric date, not a number
  if (/^\d{1,2}[.\/-][A-Za-z]{3,}[.\/-]\d{2,4}$/.test(t)) return false; // "27-MAR-2025"
  if (t.replace(/[^A-Za-z0-9]/g, '').length < 3) return false;         // too short/ambiguous ("25")
  return true;
}
function looksLikeAnId(t: string): boolean {
  return /[A-Za-z]/.test(t) || t.replace(/[^0-9]/g, '').length >= 5;
}
// Some vendors (netcup's original invoice, Backblaze) print a two-column
// key/value form whose PDF text extraction linearises into two separate line
// runs — every label first, then every value, in matching order — rather
// than "label: value" on one line (which NUMBER_RE/NUMBER_RE_BARE above
// handle). Only tried once both same-line attempts find nothing: find a
// label line that is, on its own, exactly one of the known words; walk to
// the start of its contiguous run of short label-only lines to get its
// ordinal position; then take the line at that same ordinal offset in the
// next run of lines after the label block. The candidate must fully look
// like a code (CODE_LINE_RE, no internal spaces) — rejects picking up an
// unrelated sentence/table header that happens to precede a "RECHNUNG"
// section heading.
const NUMBER_LABEL_LINES = new Set([
  'rechnungs-nr', 'rechnungs nr', 'rechnungsnr', 'rechnungsnummer', 'rechnung',
  'invoice no', 'invoice number', 'invoice #', 'invoice',
  'beleg-nr', 'beleg nr', 'belegnr', 'rg-nr', 'rg nr', 'rgnr',
  'receipt no', 'receipt number', 'receipt',
]);
const CODE_LINE_RE = /^[A-Za-z]{0,4}-?[0-9][A-Za-z0-9_./-]{0,30}$/;
function looksLikeLabelLine(s: string): boolean {
  const t = s.trim();
  return t.length > 0 && t.length <= 40 && !/[0-9@]/.test(t) && /[A-Za-zÄÖÜäöüß]/.test(t);
}
function normLabelLine(s: string): string {
  return s.trim().toLowerCase().replace(/[.:]+$/, '').replace(/\s+/g, ' ');
}
function extractNumberFromBlock(text: string): string {
  const lines = String(text || '').split(/\r\n|\r|\n/);
  for (let i = 0; i < lines.length; i++) {
    if (!looksLikeLabelLine(lines[i]) || !NUMBER_LABEL_LINES.has(normLabelLine(lines[i]))) continue;
    let start = i;
    while (start > 0 && looksLikeLabelLine(lines[start - 1])) start--;
    let end = i;
    while (end < lines.length - 1 && looksLikeLabelLine(lines[end + 1])) end++;
    if (end - start < 1) continue; // need a real block (≥2 label lines), not a lone heading
    const ordinal = i - start;
    let vStart = end + 1;
    while (vStart < lines.length && lines[vStart].trim() === '') vStart++;
    const idx = vStart + ordinal;
    if (idx >= lines.length) continue;
    const candidate = lines[idx].trim().replace(/[.,;:]+$/, '');
    if (!CODE_LINE_RE.test(candidate) || !isPlausibleNumber(candidate)) continue;
    return candidate;
  }
  return '';
}
export function extractNumber(text: string): string {
  const s = String(text || '');
  let m = s.match(NUMBER_RE);
  if (m) {
    const t = m[1].replace(/\s+/g, '').replace(/[.,;:]+$/, '');
    if (isPlausibleNumber(t)) return t;
  }
  m = s.match(NUMBER_RE_BARE);
  if (m) {
    const t = m[1].replace(/\s+/g, '').replace(/[.,;:]+$/, '');
    if (isPlausibleNumber(t) && looksLikeAnId(t)) return t;
  }
  return extractNumberFromBlock(s);
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
// A self-issued Eigenbeleg's fixed "Beleggrund" reason checklist prints EVERY
// possible option (Privatentnahme/Privateinlage, Trinkgeld, Betriebsausgabe,
// Sachgeschenke, Sonstiges) regardless of which one is actually ticked — the
// checkbox glyph itself collapses to an identical bullet for both a checked
// and an unchecked box once run through OCR/text-extraction, so which reason
// was really selected can't be recovered from the text at all. Running the
// generic category-keyword scan against that boilerplate is unreliable by
// construction: "Trinkgeld" (a fixed checklist option, present on literally
// every Eigenbeleg) always matches the Geschäftsessen rule, mis-categorising
// a private withdrawal/deposit as a business meal. Detected via the two fixed
// template headings ("Beleggrund" + "Belegdaten") that appear together only
// on this app-generated document type — category is left unset rather than
// guessed. Matched against a WHITESPACE-STRIPPED copy of the text, not the
// raw text: the PDF's own justification artifact splits this heading at an
// unpredictable point every time ("Beleg grund", "Beleggrund", even
// "Bele ggru nd" on one real document — never at a fixed, matchable
// boundary), so a soft `beleg\s*grund` pattern still missed some real
// instances; stripping ALL whitespace first is immune to wherever the split
// happens to land.
const strip = (s: string) => s.replace(/\s+/g, '');

export function analyzeReceiptText(text: string, excludeNames: string[] = []): ReceiptAnalysis {
  const raw = String(text || '');
  const low = raw.toLowerCase();
  const stripped = strip(low);
  const isEigenbeleg = stripped.includes('beleggrund') && stripped.includes('belegdaten');
  let category = '';
  if (!isEigenbeleg) {
    for (const [cat, re] of CATEGORY_RULES) { if (re.test(low)) { category = cat; break; } }
  }
  // The document's own "Eigenbeleg" title is subject to the exact same
  // unpredictable justification splitting as "Beleggrund" above — a real
  // instance rendered it as "Eigen beleg" (a plain two-word split, not the
  // single-letter-per-token pattern `despace()` collapses), and the generic
  // trailing-document-word stripper in cleanMerchant (meant to cut a real
  // company name's own "... Invoice"/"... Rechnung" suffix) then mistook the
  // second half "beleg" for that generic suffix and cut it off, leaving just
  // "Eigen". Rather than chase every possible split point that PDF
  // generation happens to produce, an already-confirmed Eigenbeleg gets its
  // canonical name set directly — extractMerchant is inherently unreliable
  // for this document type, whose only "letterhead" IS its own unstable title.
  const merchant = isEigenbeleg ? 'Eigenbeleg' : extractMerchant(text, excludeNames);
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
