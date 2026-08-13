// Client-side invoice PDF generation for the SPA — ported from the Blade/Alpine
// invoice print pipeline (resources/js/components/invoices.js + the print-template
// blocks in resources/views/invoices/index.blade.php) and the pure EPC-QR helper
// (resources/js/shared/epc-qr.js). Ported rather than imported because those live
// outside the SPA tsconfig `include` and `allowJs` is off, so a direct import would
// break `vue-tsc`. The rendered sheet is rasterised (html2canvas) into an A4,
// multi-page jsPDF exactly as the Blade app does, then uploaded to the server.

export interface PrintLine {
  desc: string;
  qty: number;
  unit?: string;
  unitPrice: number;
  vatRate: number;
}

export interface PrintCustomer {
  name?: string;
  attn?: string;
  address?: string;
  email?: string;
  vatId?: string;
}

export interface PrintContact {
  name?: string;
  role?: string;
  email?: string;
  phone?: string;
}

/** The invoice snapshot fed to the print templates (mirrors the Alpine `_printing` shape). */
export interface PrintInvoice {
  number: string | null;
  status: string;
  type: string;
  issueDate: string;
  dueDate: string;
  currency: string;
  lang: 'de' | 'en';
  customer: PrintCustomer;
  lines: PrintLine[];
  note: string;
  footer: string;
  imported: boolean;
  gross: number | null;
  vatRate: number | null;
  discountType: string | null;
  discountValue: number | null;
  skontoPercent: number | null;
  skontoDays: number | null;
}

/** The company/invoice-defaults snapshot (mirrors the Alpine `company` config). */
export interface PrintCompany {
  name: string;
  address: string;
  email: string;
  phone: string;
  vat_id: string;
  iban: string;
  bic: string;
  bank_name: string;
  website: string;
  contacts: PrintContact[];
  logo: string | null;
  accent: string;
  heading: string;
  template: string;
  font: string;
  footer_text: string;
  payment_terms_text: string;
  payment_methods: string;
  small_business: boolean;
  currency: string;
}

export interface InvoiceTotals {
  net: number;
  vat: number;
  gross: number;
  vatByRate: Record<string, number>;
  subtotal: number;
  grossNet: number;
  discount: number;
  discountAmount: number;
}

const round2 = (n: number): number => Math.round(((Number(n) || 0) + Number.EPSILON) * 100) / 100;

export function lineNet(l: PrintLine): number {
  return (parseFloat(String(l.qty)) || 0) * (parseFloat(String(l.unitPrice)) || 0);
}

/** Global invoice discount amount (percent or fixed), clamped to [0, grossNet]. */
export function discountAmount(inv: PrintInvoice, grossNet: number): number {
  if (!inv || !inv.discountType || !(Number(inv.discountValue) > 0)) return 0;
  const v = Number(inv.discountValue) || 0;
  const raw = inv.discountType === 'percent' ? (grossNet * v) / 100 : v;
  return round2(Math.min(Math.max(raw, 0), grossNet));
}

/**
 * Totals: net, VAT grouped by rate, gross. Mirrors invoices.js computeTotals — for
 * an imported invoice the stored gross+vatRate are authoritative; otherwise net per
 * rate with the global discount applied proportionally to the taxable base. Exposes
 * both `grossNet`/`discount` and `subtotal`/`discountAmount` (the klassisch template
 * uses the latter names; the other templates use the former).
 */
export function computeTotals(inv: PrintInvoice | null): InvoiceTotals {
  const t: InvoiceTotals = { net: 0, vat: 0, gross: 0, vatByRate: {}, subtotal: 0, grossNet: 0, discount: 0, discountAmount: 0 };
  if (!inv) return t;

  if (inv.imported && Number.isFinite(Number(inv.gross))) {
    const rate = parseFloat(String(inv.vatRate)) || 0;
    t.gross = round2(Number(inv.gross));
    t.vat = round2((t.gross * rate) / (100 + rate));
    t.net = round2(t.gross - t.vat);
    t.vatByRate[rate] = t.vat;
    t.subtotal = t.net;
    t.grossNet = t.net;
    return t;
  }

  const rawByRate: Record<string, number> = {};
  let grossNet = 0;
  for (const l of inv.lines || []) {
    const net = lineNet(l);
    const rate = parseFloat(String(l.vatRate)) || 0;
    grossNet += net;
    rawByRate[rate] = (rawByRate[rate] || 0) + net;
  }
  const discount = discountAmount(inv, grossNet);
  const factor = grossNet !== 0 ? (grossNet - discount) / grossNet : 1;
  for (const r of Object.keys(rawByRate)) {
    const netR = rawByRate[r] * factor;
    const v = (netR * Number(r)) / 100;
    t.vatByRate[r] = v;
    t.vat += v;
  }
  t.net = grossNet - discount;
  t.discount = discount;
  t.discountAmount = discount;
  t.grossNet = grossNet;
  t.subtotal = grossNet;
  t.gross = t.net + t.vat;
  return t;
}

/** §19 small-business invoices carry no VAT rows; else the sorted set of used rates. */
export function vatRatesOf(inv: PrintInvoice | null, smallBusiness: boolean): number[] {
  if (smallBusiness) return [];
  return Object.keys(computeTotals(inv).vatByRate)
    .map(Number)
    .sort((a, b) => a - b);
}

export function hasDiscount(inv: PrintInvoice | null): boolean {
  return !!(inv && inv.discountType && Number(inv.discountValue) > 0);
}

function addDays(iso: string, days: number): string {
  const d = new Date(iso + 'T00:00:00');
  d.setDate(d.getDate() + (days || 0));
  return d.toISOString().slice(0, 10);
}

/** Skonto "pay by" date = issue date + skonto days (printed early-payment note). */
export function skontoDate(inv: PrintInvoice | null): string {
  if (!inv || !inv.skontoDays || !(Number(inv.skontoPercent) > 0)) return '';
  try {
    return addDays(inv.issueDate || new Date().toISOString().slice(0, 10), parseInt(String(inv.skontoDays), 10) || 0);
  } catch {
    return '';
  }
}

export function fmtMoney(n: number, currency: string, lang: string): string {
  const cur = currency || 'EUR';
  const loc = (lang || 'de') === 'en' ? 'en' : 'de';
  try {
    return new Intl.NumberFormat(loc, { style: 'currency', currency: cur }).format(n || 0);
  } catch {
    return (n || 0).toFixed(2) + ' ' + cur;
  }
}

export function fmtQty(n: number | string, lang: string): string {
  const loc = (lang || 'de') === 'en' ? 'en' : 'de';
  try {
    return new Intl.NumberFormat(loc, { maximumFractionDigits: 2 }).format(parseFloat(String(n)) || 0);
  } catch {
    return String(n ?? '');
  }
}

// ---- EPC069-12 / GiroCode payload (ported from shared/epc-qr.js) ----

function normalizeIban(iban: string): string {
  return String(iban || '').replace(/\s+/g, '').toUpperCase();
}

interface EpcArgs {
  name?: string;
  iban?: string;
  bic?: string;
  amount?: number;
  currency?: string;
  reference?: string;
}

function canEpcQr({ name, iban, amount, currency }: EpcArgs): boolean {
  const ib = normalizeIban(iban ?? '');
  const amt = Number(amount);
  return (
    String(name ?? '').trim() !== '' &&
    /^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(ib) &&
    (currency || 'EUR') === 'EUR' &&
    amt >= 0.01 &&
    amt <= 999999999.99
  );
}

function buildEpcPayload({ name, iban, bic, amount, currency = 'EUR', reference = '' }: EpcArgs): string | null {
  const amt = Number(amount);
  if (!canEpcQr({ name, iban, amount: amt, currency })) return null;
  const clip = (s: string | undefined, n: number): string =>
    String(s || '')
      .replace(/[\r\n]+/g, ' ')
      .trim()
      .slice(0, n);
  return [
    'BCD',
    '002',
    '1',
    'SCT',
    clip(bic, 11),
    clip(name, 70),
    normalizeIban(iban ?? ''),
    'EUR' + amt.toFixed(2),
    '',
    '',
    clip(reference, 140),
  ].join('\n');
}

/** Build the GiroCode data-URL for an invoice (EUR/SEPA only; '' when not payable). */
export async function epcQrDataUrl(inv: PrintInvoice, company: PrintCompany, totals: InvoiceTotals): Promise<string> {
  const payload = buildEpcPayload({
    name: company.name || '',
    iban: company.iban || '',
    bic: company.bic || '',
    amount: totals.gross,
    currency: inv.currency || 'EUR',
    reference: inv.number ? (inv.lang === 'en' ? 'Invoice ' : 'Rechnung ') + inv.number : '',
  });
  if (!payload) return '';
  try {
    // qrcode ships no type declarations; keep the static specifier so Vite bundles it.
    // @ts-expect-error untyped module
    const mod = await import('qrcode');
    const QR = mod.default ?? mod;
    return await QR.toDataURL(payload, { margin: 0, width: 260 });
  } catch {
    return '';
  }
}

/**
 * Rasterise a print node into an A4 (multi-page) PDF Blob. Lazy-loads html2canvas +
 * jsPDF so they stay out of the main bundle. Identical raster/page logic to the Blade
 * app's _renderAndUploadInvoicePdf.
 */
export async function renderInvoicePdfBlob(node: HTMLElement): Promise<Blob> {
  const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas'), import('jspdf')]);
  const canvas = await html2canvas(node, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false });
  const img = canvas.toDataURL('image/jpeg', 0.92);
  const pdf = new jsPDF({ unit: 'pt', format: 'a4' });
  const pw = pdf.internal.pageSize.getWidth();
  const ph = (canvas.height * pw) / canvas.width;
  const pageH = pdf.internal.pageSize.getHeight();
  pdf.addImage(img, 'JPEG', 0, 0, pw, ph);
  let remaining = ph - pageH;
  let y = 0;
  while (remaining > 0) {
    pdf.addPage();
    y -= pageH;
    pdf.addImage(img, 'JPEG', 0, y, pw, ph);
    remaining -= pageH;
  }
  return pdf.output('blob');
}

/** Load the self-hosted invoice webfonts (public/fonts/fonts.css) once, so the chosen
 *  invoice_font actually renders in the raster. Resolves after fonts are ready. */
export async function ensureInvoiceFonts(): Promise<void> {
  const HREF = '/fonts/fonts.css';
  if (!document.querySelector(`link[data-ll-invoice-fonts]`)) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = HREF;
    link.setAttribute('data-ll-invoice-fonts', '');
    document.head.appendChild(link);
  }
  try {
    await (document as Document & { fonts?: { ready?: Promise<unknown> } }).fonts?.ready;
  } catch {
    /* ignore */
  }
}
