// Finance component (plaintext-relational — pivot). No vault, no sealed store:
// the /finance page server-renders the shell and inlines the current data, and
// every mutation is a per-row JSON/multipart request to the /finance/* endpoints
// (mirrored by the mobile API). Invoice PDFs + receipt files live plaintext on the
// server; previews use the raw URLs directly (no client decryption). The rendered/
// printed invoice, ZUGFeRD/receipt parsing, bank-statement parsing, GoBD gap/dup
// WARNINGS and money math stay client-side; the server owns GoBD numbering.
import { nextSeqForYear, duplicateNumbers as dupNumbers, missingNumbers as gapNumbers, invoicesInYear, invoiceYear } from '../shared/invoice-numbering';
import { parseInvoiceFilename, parseInvoiceText, buildImportDraft } from '../shared/invoice-pdf-import';
import { contactDisplayName } from '../shared/contact-utils';
import { getJson, apiRequest, jsonHeaders, csrfToken } from '../shared/api';
import { parseTags, addTags, removeTagFrom, popTag } from '../shared/tag-chips';
import { buildEpcPayload } from '../shared/epc-qr';
import { saveBlobAs, formatDate } from '../shared/dom';
import { buildZugferdXml, zugferdFilename } from '../shared/zugferd';
import { fileSig } from '../shared/file-sig';
import { autoPick, suggestBookings } from '../shared/receipt-match';
import { projectTree as buildProjectTree, rolledTotal as projectRolled, ownTotal as projectOwn, projectReceipts as receiptsForProject } from '../shared/finance-projects';
import { vatReturn, revenueByCustomer, monthlyRevenue, yearKpis, activeYears, accountVatSummary, discountAmount } from '../shared/finance-stats';
import { buildRevenueCsv, buildExpenseCsv } from '../shared/datev-export';
import { matchInvoice } from '../shared/invoice-match';
import { extractDocText } from '../shared/doc-text';
import { analyzeReceiptText } from '../shared/receipt-ocr';
import { normMerchant, matchPartner, learnedCategoryFor } from '../shared/merchant-learn';
import { buildReceiptName } from '../shared/receipt-name';
import { amountMatches } from '../shared/amount-search';
import { PAYMENT_TYPES, paymentTint, paymentSubtitle, isValidPaymentMethod, sortedPaymentMethods, blankPaymentMethod, cardNetworkOf } from '../shared/payment-methods';
import { detectFormat, parseMt940, parseCsv, detectCsvMapping, applyCsvMapping, enrichExisting, classifyTxType, guessVatCat, VAT_CATS, txSignature as txSig, TX_FIELDS, TX_REQUIRED } from '../shared/bank-statement';

// ---- snake_case (server row) ↔ the camelCase the component + pure helpers use ----
// Money/date columns arrive as decimal STRINGS / ISO datetimes → coerce. The invoice
// document language + footer have no dedicated column, so they ride inside the
// (free-form JSON) `customer` snapshot and are lifted back to the top level here.
const iso10 = (v) => (v ? String(v).slice(0, 10) : '');
const num = (v) => (v == null || v === '' ? undefined : Number(v));

const normInvoice = (row) => {
    const customer = (row.customer && typeof row.customer === 'object') ? { ...row.customer } : {};
    const lang = customer.lang || 'de';
    const footer = customer.footer ?? '';
    delete customer.lang; delete customer.footer;
    if (customer.partnerId === undefined) customer.partnerId = null;
    if (customer.invoiceEmail === undefined) customer.invoiceEmail = '';
    return {
        id: row.id,
        number: row.number ?? null,
        seq: row.seq ?? null,
        year: row.year ?? null,
        status: row.status || 'draft',
        type: row.type || 'invoice',
        cancelsInvoiceId: row.cancels_invoice_id ?? null,
        discountType: row.discount_type ?? null,
        discountValue: num(row.discount_value),
        skontoPercent: num(row.skonto_percent),
        skontoDays: row.skonto_days ?? null,
        issueDate: iso10(row.issue_date),
        dueDate: iso10(row.due_date),
        currency: row.currency || 'EUR',
        vatRate: num(row.vat_rate),
        gross: num(row.gross),
        imported: !! row.imported,
        paidAt: iso10(row.paid_at) || null,
        sentAt: row.sent_at ?? null,
        remindedAt: row.reminded_at ?? null,
        reminderCount: row.reminder_count ?? 0,
        paymentAccount: row.payment_account ?? null,
        customer,
        lang,
        footer,
        lines: Array.isArray(row.lines) ? row.lines : [],
        note: row.note ?? '',
        versions: Array.isArray(row.versions) ? row.versions : [],
        versionSeq: row.version_seq ?? 0,
        pdfPath: row.pdf_path ?? null,
        version: row.version ?? 0,
        updated: row.updated_at ?? null,
        trashed: row.deleted_at ? row.deleted_at : false,
    };
};

const normPartner = (row) => ({
    id: row.id,
    name: row.name || '',
    category: row.category ?? '',
    kind: row.kind ?? '',
    url: row.url ?? '',
    logo: row.logo ?? '',
    note: row.note ?? '',
    address: row.address ?? '',
    email: row.email ?? '',
    invoiceEmail: row.invoice_email ?? '',
    phone: row.phone ?? '',
    vatId: row.vat_id ?? '',
    hourlyRate: row.hourly_rate ?? null,
    currency: row.currency ?? '',
    contacts: Array.isArray(row.contacts) ? row.contacts : [],
    version: row.version ?? 0,
    trashed: row.deleted_at ? true : false,
});

// The server column `name` is the client's `label`; account identifiers keep their
// camelCase client names (bankName/accountNumber/email = paypal_email).
const normPayment = (row) => ({
    id: row.id,
    type: row.type || 'other',
    label: row.name || '',
    business: !! row.business,
    url: row.url ?? '',
    icon: row.icon ?? '',
    iban: row.iban ?? '',
    bic: row.bic ?? '',
    bankName: row.bank ?? '',
    accountNumber: row.account_no ?? '',
    cardNumber: row.card_number ?? '',
    cardNetwork: row.card_network ?? 'visa',
    cardExpiry: row.card_expiry ?? '',
    email: row.paypal_email ?? '',
    holder: row.holder ?? '', note: row.note ?? '',
    version: row.version ?? 0,
    trashed: row.deleted_at ? true : false,
});

// `account` = the payment_method_id the tx belongs to; `iban` = the counterparty IBAN.
const normTx = (row) => ({
    id: row.id,
    account: row.payment_method_id,
    date: iso10(row.date),
    amount: row.amount != null ? Number(row.amount) : 0,
    vatCat: row.vat_cat ?? null,
    sig: row.sig ?? null,
    invoiceId: row.invoice_id ?? null,
    invoiceNumber: row.invoice_number ?? null,
    projectId: row.finance_project_id ?? null,
    counterparty: row.counterparty ?? '',
    iban: row.counterparty_iban ?? '',
    bic: row.bic ?? '',
    purpose: row.purpose ?? '',
    bookingText: row.booking_text ?? '',
    eref: row.eref ?? '',
    receipts: Array.isArray(row.receipts) ? row.receipts : [],
    version: row.version ?? 0,
    trashed: row.deleted_at ? true : false,
});

const normProject = (row) => ({
    id: row.id,
    parentId: row.parent_id ?? null,
    name: row.name || '',
    kind: row.kind || 'business',
    note: row.note ?? '',
    expenses: Array.isArray(row.expenses) ? row.expenses : [],
    version: row.version ?? 0,
});

const normCategory = (row) => ({ id: row.id, name: row.name || '', color: row.color ?? null, icon: row.icon ?? null });

// A standalone receipt ("Fremdbeleg"): a finance_receipts row (no bank transaction
// required). Mapped to the same client shape the embedded-receipt UI expects, so
// one detail modal / list handles both — source is tracked on the {r,tx,src} pair.
const normStandalone = (row) => ({
    id: row.id,
    blob_path: row.blob_path || '',
    name: row.name || 'receipt',
    mime: row.mime ?? null,
    size: row.size ?? 0,
    kind: row.kind || 'receipt',
    category: row.category ?? null,
    tags: Array.isArray(row.tags) ? row.tags : [],
    vat: row.vat ?? null,
    note: row.note ?? null,
    ocr: row.ocr ?? null,
    sig: row.sig ?? null,
    partnerId: row.partner_id ?? null,
    projectId: row.finance_project_id ?? null,
    txId: row.bank_transaction_id ?? null,
    uploadedAt: row.created_at ?? '',
    version: row.version ?? 0,
});

export default (config = {}, labels = {}, initial = {}) => ({
    company: config.company || {},
    _labelsByLang: config.labelsByLang || {},
    // §19 Kleinunternehmer: no VAT is computed/shown on invoices when true.
    smallBusiness: !! config.smallBusiness,

    // ---- Relational collections (active rows), hydrated from the inlined snapshot ----
    invoices: (initial.invoices || []).map(normInvoice),
    partners: (initial.partners || []).map(normPartner),
    paymentMethods: (initial.paymentMethods || []).map(normPayment),
    transactions: (initial.transactions || []).map(normTx),
    projects: (initial.projects || []).map(normProject),
    financeCategories: (initial.financeCategories || []).map(normCategory),
    standaloneReceipts: (initial.standaloneReceipts || []).map(normStandalone), // "Fremdbelege" (no bank transaction)
    invTrash: [], // trashed invoices (loaded on demand from /finance/trash)

    // Read-only server insights (additive; best-effort, never block the page).
    duplicates: { invoices: [], transactions: [] }, // suspected-duplicate groups from GET /finance/duplicates
    catSuggestions: [],  // [{tx_id, merchant, suggested_category}] from GET /finance/category-suggestions
    aging: null,         // { buckets, openCount, openGross } from GET /finance/reports (open-items widget)
    // Server-authoritative tax figures (combine invoices + bank transactions).
    vatAdvance: null,    // { outputVat, inputVat, payable, byRate, ... } from GET /finance/reports/vat-advance
    vatQuarter: '',      // '' = full year | '1'..'4' selected quarter
    euer: null,          // { income, expenses, profit } from GET /finance/reports/euer
    euerYear: new Date().getFullYear(),

    query: '',           // invoice-list search
    view: 'list',        // 'list' | 'edit' | 'imported'
    current: null,       // the invoice being edited
    filterStatus: '',    // '' | draft | sent | paid
    _printing: null,     // invoice rendered into the hidden print sheet
    dirty: false,        // a LOCKED invoice has unsaved edits (drafts autosave; locked don't)
    pdfBusy: false,      // a version PDF is being rendered
    editUnlocked: false, // locked invoice: fields stay disabled until "Bearbeiten" + confirm
    _lockBaseline: null, // JSON of a locked invoice as opened, to revert unsaved edits on leave
    _saveTimer: null,    // debounce handle for the draft autosave
    section: 'dashboard', // 'dashboard' | 'receipts' | 'invoices' | 'payments' | 'projects' | 'partners' | 'stats' | 'settings'

    // Badge-chip tag editing (x-tag-field contract) over `tagsValue`.
    tagsValue: '',
    tagDraft: '',
    tagList() { return parseTags(this.tagsValue); },
    commitTag() { this.tagsValue = addTags(this.tagsValue, this.tagDraft); this.tagDraft = ''; },
    onTagInput() { if ((this.tagDraft || '').includes(',')) this.commitTag(); },
    tagBackspace() { if ((this.tagDraft || '') === '') this.tagsValue = popTag(this.tagsValue); },
    removeTag(tag) { this.tagsValue = removeTagFrom(this.tagsValue, tag); },

    // Global business/private scope, applied consistently across every finance tab.
    financeScope: 'all', // 'all' | 'business' | 'private'
    setFinanceScope(s) { this.financeScope = s; this.projPage = 1; this.recPage = 1; this.invPage = 1; this.txPage = 1; },
    _scopeMatch(isPrivate) { return this.financeScope === 'all' || (this.financeScope === 'private') === !! isPrivate; },
    _txPrivate(tx) { return (tx && tx.vatCat) === 'private'; },
    _pmPrivate(pm) { return ! (pm && pm.business); },
    _receiptPrivate(d) {
        if (! d || ! d.r) return false;
        if (d.r.projectId) return this.effectiveKind(d.r.projectId) === 'private';
        return this._txPrivate(d.tx);
    },

    async init() {
        const [sec, deepId] = (location.hash || '').replace('#', '').split('/');
        if (['dashboard', 'receipts', 'invoices', 'payments', 'projects', 'partners', 'stats', 'settings'].includes(sec)) this.section = sec;
        for (const prop of ['section', 'current', 'receiptDoc', 'payAccount', 'payView', 'openProjectId', 'openPartnerId', 'partnersView']) {
            this.$watch(prop, () => this._writeHash());
        }
        // Load the invoice trash lazily the first time the bin is opened.
        this.$watch('showInvTrash', (v) => { if (v) this._loadInvTrash(); });
        this._restoreDeepLink(sec, deepId);
        // Read-only server insights, best-effort — must never break the page.
        this._loadDuplicates();
        this._loadCatSuggestions();
        this._loadAging();
        this._loadVatAdvance();
        this._loadEuer();
        // Refetch the unified payable when the quarter selector changes.
        this.$watch('vatQuarter', () => this._loadVatAdvance());
        this.$watch('euerYear', () => this._loadEuer());
    },

    // Unified USt-Voranmeldung: output − input VAT = Zahllast (server combines both sides).
    async _loadVatAdvance() {
        try {
            const q = this.vatQuarter ? '&quarter=' + this.vatQuarter : '';
            this.vatAdvance = await getJson('/finance/reports/vat-advance?year=' + new Date().getFullYear() + q);
        } catch (e) { /* leave null */ }
    },
    // Simplified EÜR (income − expenses = profit) for the selected year.
    async _loadEuer() {
        try { this.euer = await getJson('/finance/reports/euer?year=' + (this.euerYear || new Date().getFullYear())); }
        catch (e) { /* leave null */ }
    },
    get euerExpensePeak() { return Math.max(1, ...((this.euer?.expenses?.byCategory) || []).map((c) => Math.abs(c.amount))); },

    // Open-invoice aging buckets (current / 1-30 / 31-60 / 60+). Read-only display.
    async _loadAging() {
        try { const d = await getJson('/finance/reports'); this.aging = d?.aging || null; }
        catch (e) { /* leave null */ }
    },
    // Overdue = an issued (sent) invoice past its due date. Derived, no column.
    isOverdue(inv) { const i = inv || this.current; return !! i && (i.status === 'sent' || i.status === 'final') && !! i.dueDate && i.dueDate < this._today(); },
    daysOverdue(inv) {
        const i = inv || this.current;
        if (! this.isOverdue(i)) return 0;
        return Math.max(0, Math.floor((Date.parse(this._today()) - Date.parse(i.dueDate)) / 86400000));
    },

    // Suspected-duplicate groups (invoices + transactions). Read-only display.
    async _loadDuplicates() {
        try {
            const d = await getJson('/finance/duplicates');
            this.duplicates = {
                invoices: Array.isArray(d?.invoices) ? d.invoices : [],
                transactions: Array.isArray(d?.transactions) ? d.transactions : [],
            };
        } catch (e) { /* leave empty */ }
    },
    // merchant->category suggestions for uncategorised transactions.
    async _loadCatSuggestions() {
        try {
            const d = await getJson('/finance/category-suggestions');
            this.catSuggestions = Array.isArray(d?.suggestions) ? d.suggestions : [];
        } catch (e) { /* leave empty */ }
    },
    _findInvoice(id) { return (this.invoices || []).find((i) => i.id === id) || null; },
    _findTx(id) { return (this.transactions || []).find((t) => t.id === id) || null; },
    // Resolve each suspect group's row ids to the actual records for a readable
    // banner (invoice number/customer/date/gross; tx date/amount/counterparty).
    // Only keep groups where >1 record still resolves (a since-deleted row drops out).
    get dupeInvoiceGroups() {
        return (this.duplicates?.invoices || [])
            .map((g) => ({ reason: g.reason, items: (g.ids || []).map((id) => this._findInvoice(id)).filter(Boolean) }))
            .filter((g) => g.items.length > 1);
    },
    get dupeTxGroups() {
        return (this.duplicates?.transactions || [])
            .map((g) => ({ reason: g.reason, items: (g.ids || []).map((id) => this._findTx(id)).filter(Boolean) }))
            .filter((g) => g.items.length > 1);
    },
    get hasDuplicates() { return this.duplicateCount > 0; },
    get duplicateCount() { return this.dupeInvoiceGroups.length + this.dupeTxGroups.length; },
    // The suggestion for a still-uncategorised tx (hides itself once a category is set).
    suggestionFor(tx) {
        if (! tx || tx.vatCat) return null;
        return (this.catSuggestions || []).find((s) => s.tx_id === tx.id) || null;
    },
    // Apply a suggested category through the EXISTING per-transaction save path
    // (the same one used when the owner picks a category manually in the list).
    async applySuggestion(tx, category) { if (tx && category) await this.setVatCat(tx, category); },

    // Build #section/<id> from the current open detail and replace it into the URL.
    _writeHash() {
        let h = this.section;
        if (this.section === 'invoices' && this.current) h += '/' + this.current.id;
        else if (this.section === 'receipts' && this.receiptDoc?.r) h += '/' + this.receiptDoc.r.id;
        else if (this.section === 'payments' && this.payView === 'account' && this.payAccount) h += '/' + this.payAccount.id;
        else if (this.section === 'projects' && this.openProjectId) h += '/' + this.openProjectId;
        else if (this.section === 'partners' && this.partnersView === 'detail' && this.openPartnerId) h += '/' + this.openPartnerId;
        try { history.replaceState(null, '', '#' + h); } catch (e) { /* ignore */ }
    },
    _restoreDeepLink(sec, id) {
        if (! id) return;
        if (sec === 'invoices') { const inv = (this.invoices || []).find((i) => String(i.id) === id); if (inv) this.open(inv); }
        else if (sec === 'receipts') { const d = this.allReceipts.find((x) => x.r && String(x.r.id) === id); if (d) this.openReceiptDoc(d); }
        else if (sec === 'payments') { const pm = (this.paymentMethods || []).find((p) => String(p.id) === id); if (pm) this.openAccount(pm); }
        else if (sec === 'projects') { if ((this.projects || []).some((p) => String(p.id) === id)) this.openProjectDetail(Number(id)); }
        else if (sec === 'partners') { const p = (this.partners || []).find((x) => String(x.id) === id); if (p) this.openPartner(p); }
    },

    setSection(s) {
        this.section = s; // the $watch above writes the hash
        try { window.scrollTo({ top: 0 }); } catch (e) { /* ignore */ }
    },

    // ---- REST plumbing --------------------------------------------------------
    _newId() { try { return crypto.randomUUID(); } catch (e) { return 'id-' + Math.random().toString(36).slice(2) + Date.now(); } },
    // A fetch that surfaces status + body so a 409 version_conflict can be handled.
    async _req(method, url, body) {
        const res = await fetch(url, { method, headers: jsonHeaders(), body: body != null ? JSON.stringify(body) : undefined });
        let data = {}; try { data = await res.json(); } catch (e) { data = {}; }
        return { ok: res.ok, status: res.status, data };
    },
    // Create a row: POST /finance/<seg>; returns the normalized row or null.
    async _create(seg, payload, key, norm) {
        const r = await this._req('POST', '/finance/' + seg, payload);
        if (! r.ok || ! r.data[key]) { window.llToast?.(labels.save_failed || 'Save failed.'); return null; }
        return norm(r.data[key]);
    },
    // Update a row with optimistic version; on 409 reload the snapshot. Returns the
    // normalized row or null.
    async _update(seg, id, payload, key, norm) {
        const r = await this._req('PUT', '/finance/' + seg + '/' + id, payload);
        if (r.status === 409) { await this.reload(); window.llToast?.(labels.save_failed || 'Saved on another device — reloaded.'); return null; }
        if (! r.ok || ! r.data[key]) { window.llToast?.(labels.save_failed || 'Save failed.'); return null; }
        return norm(r.data[key]);
    },
    async _destroy(seg, id) { try { await apiRequest('DELETE', '/finance/' + seg + '/' + id); return true; } catch (e) { window.llToast?.(labels.delete_failed || 'Could not delete — please try again.'); return false; } },

    // Re-pull the whole snapshot (used after a conflict / a bulk import).
    async reload() {
        try {
            const d = await getJson('/finance/data');
            this.invoices = (d.invoices || []).map(normInvoice);
            this.partners = (d.partners || []).map(normPartner);
            this.paymentMethods = (d.paymentMethods || []).map(normPayment);
            this.transactions = (d.transactions || []).map(normTx);
            this.projects = (d.projects || []).map(normProject);
            this.financeCategories = (d.financeCategories || []).map(normCategory);
            this.standaloneReceipts = (d.standaloneReceipts || []).map(normStandalone);
            // Re-find open detail refs by id so views stay coherent.
            if (this.current) this.current = this.invoices.find((i) => i.id === this.current.id) || null;
            if (this.payAccount) this.payAccount = this.paymentMethods.find((p) => p.id === this.payAccount.id) || null;
            if (this.receiptDoc) {
                if (this.receiptDoc.src === 'std') {
                    const r = this.standaloneReceipts.find((x) => x.id === this.receiptDoc.r.id);
                    this.receiptDoc = r ? { r, tx: null, src: 'std' } : null;
                } else {
                    const tx = this.transactions.find((t) => t.id === this.receiptDoc.tx.id);
                    const r = tx ? (tx.receipts || []).find((x) => x.id === this.receiptDoc.r.id) : null;
                    this.receiptDoc = (tx && r) ? { r, tx, src: 'tx' } : null;
                }
            }
        } catch (e) { /* keep in-memory state */ }
    },

    async _loadInvTrash() {
        try { const d = await getJson('/finance/trash'); this.invTrash = (d.invoices || []).map(normInvoice); }
        catch (e) { /* leave as is */ }
    },

    // ---- Server payloads (client → snake_case) ----
    _toServerInvoice(inv) {
        const t = this.computeTotals(inv);
        const customer = { ...(inv.customer || {}), lang: inv.lang || 'de', footer: inv.footer || '' };
        return {
            number: inv.number ?? null,
            status: inv.status || 'draft',
            type: inv.type || 'invoice',
            issue_date: inv.issueDate || null,
            due_date: inv.dueDate || null,
            currency: inv.currency || 'EUR',
            vat_rate: inv.imported ? (inv.vatRate ?? null) : null,
            gross: Number.isFinite(t.gross) ? t.gross : null,
            net: Number.isFinite(t.net) ? t.net : null,
            vat: Number.isFinite(t.vat) ? t.vat : null,
            discount_type: inv.discountType || null,
            discount_value: (inv.discountType && Number.isFinite(Number(inv.discountValue))) ? Number(inv.discountValue) : null,
            skonto_percent: Number.isFinite(Number(inv.skontoPercent)) && Number(inv.skontoPercent) > 0 ? Number(inv.skontoPercent) : null,
            skonto_days: (inv.skontoDays != null && inv.skontoDays !== '') ? parseInt(inv.skontoDays, 10) : null,
            imported: !! inv.imported,
            paid_at: inv.paidAt || null,
            payment_account: inv.paymentAccount ?? null,
            partner_id: (inv.customer && inv.customer.partnerId) ?? null,
            customer,
            lines: inv.lines || [],
            note: inv.note ?? null,
            versions: inv.versions ?? null,
        };
    },
    _toServerPartner(p) {
        return {
            name: p.name || '', category: p.category || null, kind: p.kind || null,
            url: p.url || null, logo: p.logo || null, note: p.note || null,
            address: p.address || null, email: p.email || null, invoice_email: p.invoiceEmail || null, phone: p.phone || null,
            hourly_rate: (p.hourlyRate === '' || p.hourlyRate == null) ? null : p.hourlyRate, currency: p.currency || null,
            vat_id: p.vatId || null, contacts: Array.isArray(p.contacts) ? p.contacts : [],
        };
    },
    _toServerPayment(pm) {
        return {
            type: pm.type, name: pm.label || '', holder: pm.holder || null, business: !! pm.business,
            url: pm.url || null, icon: pm.icon || null,
            iban: pm.iban || null, bic: pm.bic || null, bank: pm.bankName || null,
            account_no: pm.accountNumber || null, card_number: pm.cardNumber || null,
            card_network: pm.cardNetwork || null, card_expiry: pm.cardExpiry || null,
            paypal_email: pm.email || null, note: pm.note || null,
        };
    },
    _toServerProject(p) {
        return { name: p.name || '', parent_id: p.parentId ?? null, kind: p.kind || 'business', note: p.note || null, expenses: Array.isArray(p.expenses) ? p.expenses : [] };
    },
    _toServerTx(tx) {
        return {
            payment_method_id: tx.account,
            date: tx.date || null,
            amount: tx.amount,
            vat_cat: tx.vatCat || null,
            sig: tx.sig || null,
            invoice_id: tx.invoiceId || null,
            invoice_number: tx.invoiceNumber || null,
            finance_project_id: tx.projectId || null,
            counterparty: tx.counterparty || null,
            counterparty_iban: tx.iban || null,
            bic: tx.bic || null,
            purpose: tx.purpose || null,
            booking_text: tx.bookingText || null,
            eref: tx.eref || null,
            receipts: tx.receipts || [],
        };
    },

    // ---- Per-entity persisters (mutate in place, then persist) ----
    async _persistInvoice(inv) {
        if (! inv?.id) return;
        const row = await this._update('invoices', inv.id, this._toServerInvoice(inv), 'invoice', normInvoice);
        if (row) { inv.version = row.version; inv.updated = row.updated; if (row.number) inv.number = row.number; if (row.seq != null) inv.seq = row.seq; if (row.year != null) inv.year = row.year; }
    },
    async _persistPartner(p) { if (! p?.id) return; const row = await this._update('partners', p.id, this._toServerPartner(p), 'partner', normPartner); if (row) p.version = row.version; },
    async _persistPayment(pm) { if (! pm?.id) return; const row = await this._update('payment-methods', pm.id, this._toServerPayment(pm), 'payment_method', normPayment); if (row) pm.version = row.version; },
    async _persistProject(p) { if (! p?.id) return; const row = await this._update('projects', p.id, this._toServerProject(p), 'project', normProject); if (row) p.version = row.version; },
    async _persistTx(tx) { if (! tx?.id) return; const row = await this._update('transactions', tx.id, this._toServerTx(tx), 'transaction', normTx); if (row) tx.version = row.version; },

    // ---- Finance dashboard: income at a glance ----
    get financeStats() {
        const year = new Date().getFullYear();
        let paidYear = 0, outstandingYear = 0, countYear = 0, paidAll = 0;
        for (const inv of (this.invoices || [])) {
            if (inv.trashed) continue;
            const g = this.computeTotals(inv).gross || 0;
            const y = parseInt((inv.issueDate || '').slice(0, 4), 10);
            if (inv.status === 'paid') { paidAll += g; if (y === year) paidYear += g; }
            if (y === year) { countYear++; if (inv.status === 'sent' || inv.status === 'final') outstandingYear += g; }
        }
        return { year, paidYear, outstandingYear, countYear, paidAll };
    },

    // ---- VAT advance return (Umsatzsteuer-Voranmeldung), current year ----
    get vatReturn() { return vatReturn(this.invoices, new Date().getFullYear(), this.smallBusiness); },

    // ---- Statistics tab (year-scoped; the year is selectable) ----
    statsYear: new Date().getFullYear(),
    get statsYears() { const ys = activeYears(this.invoices); return ys.length ? ys : [new Date().getFullYear()]; },
    get statsKpis() { return yearKpis(this.invoices, this.statsYear); },
    get statsCustomers() { return revenueByCustomer(this.invoices, this.statsYear); },
    get statsMonths() { return monthlyRevenue(this.invoices, this.statsYear); },
    get statsVat() { return vatReturn(this.invoices, this.statsYear, this.smallBusiness); },
    get statsMonthPeak() { return Math.max(1, ...this.statsMonths.map((m) => m.net)); },
    // GoBD accounting export (Rechnungsausgangsbuch / Belege) as semicolon CSV with a
    // UTF-8 BOM — universally importable (Steuerberater / DATEV generic mapping / Excel).
    // German column headers (GoBD/accountant-oriented) come from the builder defaults.
    exportRevenueCsv() {
        const csv = buildRevenueCsv(this.invoices, this.statsYear);
        saveBlobAs(new Blob([csv], { type: 'text/csv;charset=utf-8' }), `umsatz-${this.statsYear}.csv`, 'text/csv');
    },
    exportExpenseCsv() {
        const csv = buildExpenseCsv(this.transactions, this.projects, this.statsYear);
        saveBlobAs(new Blob([csv], { type: 'text/csv;charset=utf-8' }), `belege-${this.statsYear}.csv`, 'text/csv');
    },
    monthLabel(m) {
        const loc = document.documentElement.lang || 'de';
        try { return new Intl.DateTimeFormat(loc, { month: 'short' }).format(new Date(2000, (m || 1) - 1, 1)); }
        catch (e) { return String(m); }
    },

    // ---- Payment methods (bank accounts, cards, …) ----
    payEditing: null,       // the record being created/edited (a working copy)
    payIsNew: false,
    payTypeOptions: PAYMENT_TYPES,
    get sortedPayments() { return sortedPaymentMethods(this.paymentMethods); },
    payTint(type) { return paymentTint(type); },
    paySubtitle(pm) { return paymentSubtitle(pm); },
    payTypeLabel(type) { return labels['pay_type_' + type] || type; },
    paySaveAttempted: false,
    newPayment(type = 'bank') { this.payEditing = blankPaymentMethod(type); this.payIsNew = true; this.paySaveAttempted = false; },
    editPayment(pm) { this.payEditing = JSON.parse(JSON.stringify(pm)); this.payEditing.id = pm.id; this.payIsNew = false; this.paySaveAttempted = false; },
    cancelPayment() { this.payEditing = null; },
    get payMissing() {
        const pm = this.payEditing, miss = [];
        if (! pm) return miss;
        if (! String(pm.label || '').trim()) miss.push('label');
        if (pm.type === 'bank' && ! String(pm.iban || '').trim() && ! String(pm.accountNumber || '').trim()) { miss.push('iban'); miss.push('accountNumber'); }
        if (pm.type === 'card' && ! String(pm.cardNumber || '').trim()) miss.push('cardNumber');
        if (pm.type === 'paypal' && ! String(pm.email || '').trim()) miss.push('email');
        return miss;
    },
    payErr(field) { return this.paySaveAttempted && this.payMissing.includes(field); },
    payCardInput() { if (this.payEditing?.type === 'card') this.payEditing.cardNetwork = cardNetworkOf(this.payEditing.cardNumber); },
    async savePayment() {
        const pm = this.payEditing;
        if (! isValidPaymentMethod(pm)) { this.paySaveAttempted = true; window.llToast?.(labels.pay_invalid || 'Please fill in the required fields.'); return; }
        let target = null;
        if (this.payIsNew) {
            const row = await this._create('payment-methods', this._toServerPayment(pm), 'payment_method', normPayment);
            if (! row) return;
            this.paymentMethods.push(row); target = row;
        } else {
            const i = this.paymentMethods.findIndex((p) => p.id === pm.id);
            if (i >= 0) { Object.assign(this.paymentMethods[i], pm); target = this.paymentMethods[i]; await this._persistPayment(target); }
        }
        if (target && target.type === 'bank' && target.url) this._fetchBankIcon(target);
        this.payEditing = null;
    },
    _bankHost(url) {
        try { return new URL(/^https?:\/\//i.test(url) ? url : 'https://' + url).hostname; } catch (e) { return ''; }
    },
    async _fetchBankIcon(pm) {
        const host = this._bankHost(pm.url);
        if (! host || ! config.iconUrl) return;
        try {
            const res = await fetch(`${config.iconUrl}?domain=${encodeURIComponent(host)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            const { icon } = await res.json();
            if (icon && pm.icon !== icon) { pm.icon = icon; await this._persistPayment(pm); }
        } catch (e) { /* best effort */ }
    },
    payIconSrc(pm) { const v = pm && pm.icon; return (typeof v === 'string' && /^(data:|https?:)/.test(v)) ? v : ''; },
    async removePayment(pm) {
        if (! await this.$store.confirm.ask(labels.pay_delete_confirm || 'Delete this payment method?')) return;
        await this._destroy('payment-methods', pm.id);
        const i = this.paymentMethods.indexOf(pm);
        if (i >= 0) this.paymentMethods.splice(i, 1);
        // Drop that account's transactions too (best-effort).
        for (let j = this.transactions.length - 1; j >= 0; j--) {
            if (this.transactions[j].account === pm.id) { const tx = this.transactions[j]; this.transactions.splice(j, 1); this._destroy('transactions', tx.id); }
        }
        if (this.payEditing && this.payEditing.id === pm.id) this.payEditing = null;
        if (this.payAccount && this.payAccount.id === pm.id) this.backToPayments();
    },
    async toggleBusiness(pm) {
        const on = ! pm.business;
        const changed = [];
        for (const p of this.paymentMethods) { if (p.business && p !== pm) { p.business = false; changed.push(p); } }
        pm.business = on; changed.push(pm);
        for (const p of changed) await this._persistPayment(p);
    },

    // ---- Account detail + bank-statement import ----
    payView: 'list',        // 'list' | 'account'
    payAccount: null,       // the payment method whose statement is open
    stmt: null,             // import wizard state
    openAccount(pm) {
        this.payAccount = pm; this.payView = 'account'; this.txPage = 1;
        this.resetTxFilters();
        this.txYear = new Date().getFullYear();
        this.rematchAll(true);
        try { window.scrollTo({ top: 0 }); } catch (e) { /* */ }
    },
    backToPayments() { this.payView = 'list'; this.payAccount = null; },
    txYear: new Date().getFullYear(),
    setTxYear(y) { this.txYear = y; this.txPage = 1; },
    get accountTxYears() {
        const id = this.payAccount?.id;
        const set = new Set([new Date().getFullYear()]);
        for (const t of (this.transactions || [])) {
            if (t.account !== id || ! this._scopeMatch(this._txPrivate(t))) continue;
            const y = parseInt(String(t.date || '').slice(0, 4), 10);
            if (y) set.add(y);
        }
        return [...set].sort((a, b) => b - a);
    },
    _accountBase() {
        const id = this.payAccount?.id;
        const yr = this.txYear ? String(this.txYear) : null;
        return (this.transactions || []).filter((t) => t.account === id && this._scopeMatch(this._txPrivate(t)) && (! yr || String(t.date || '').startsWith(yr)));
    },
    txSearch: '', txDir: '', txType: '', txCat: '', txCounterparty: '',
    get txFiltersActive() { return !! (this.txSearch.trim() || this.txDir || this.txType || this.txCat || this.txCounterparty); },
    resetTxFilters() { this.txSearch = ''; this.txDir = ''; this.txType = ''; this.txCat = ''; this.txCounterparty = ''; this.txPage = 1; },
    get accountCounterparties() { return [...new Set(this._accountBase().map((t) => t.counterparty).filter(Boolean))].sort((a, b) => a.localeCompare(b)); },
    get accountTxTypeOptions() { const seen = new Set(this._accountBase().map((t) => classifyTxType(t))); return ['card', 'debit', 'credit', 'standingorder', 'fee', 'transfer', 'other'].filter((t) => seen.has(t)); },
    get accountTx() {
        const q = this.txSearch.trim().toLowerCase();
        const raw = this.txSearch.trim();
        return this._accountBase().filter((t) => {
            if (this.txDir === 'in' && ! (Number(t.amount) > 0)) return false;
            if (this.txDir === 'out' && ! (Number(t.amount) < 0)) return false;
            if (this.txType && classifyTxType(t) !== this.txType) return false;
            if (this.txCat && (this.txCat === 'none' ? !! t.vatCat : t.vatCat !== this.txCat)) return false;
            if (this.txCounterparty && (t.counterparty || '') !== this.txCounterparty) return false;
            if (q) {
                const hit = [t.counterparty, t.purpose, t.iban, t.bookingText, t.eref].some((v) => String(v || '').toLowerCase().includes(q)) || amountMatches(t.amount, raw);
                if (! hit) return false;
            }
            return true;
        }).sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    },
    get scopedPayments() { return this.sortedPayments.filter((pm) => this._scopeMatch(this._pmPrivate(pm))); },
    // ---- Pagination ----
    perPageOptions: [5, 10, 15, 20, 25, 50, 100],
    txPage: 1,
    txPerPage: 25,
    get txPageCount() { return Math.max(1, Math.ceil(this.accountTx.length / this.txPerPage)); },
    get pagedAccountTx() { const s = (this.txPage - 1) * this.txPerPage; return this.accountTx.slice(s, s + this.txPerPage); },
    setTxPerPage(n) { this.txPerPage = n; this.txPage = 1; },
    txGoto(p) { this.txPage = Math.min(this.txPageCount, Math.max(1, p)); },
    invPage: 1,
    invPerPage: 25,
    get invPageCount() { return Math.max(1, Math.ceil(this.filtered.length / this.invPerPage)); },
    get pagedInvoices() { const s = (this.invPage - 1) * this.invPerPage; return this.filtered.slice(s, s + this.invPerPage); },
    setInvPerPage(n) { this.invPerPage = n; this.invPage = 1; },
    invGoto(p) { this.invPage = Math.min(this.invPageCount, Math.max(1, p)); },
    recPage: 1,
    recPerPage: 25,
    get recPageCount() { return Math.max(1, Math.ceil(this.filteredReceipts.length / this.recPerPage)); },
    get pagedReceipts() { const s = (this.recPage - 1) * this.recPerPage; return this.filteredReceipts.slice(s, s + this.recPerPage); },
    setRecPerPage(n) { this.recPerPage = n; this.recPage = 1; },
    recGoto(p) { this.recPage = Math.min(this.recPageCount, Math.max(1, p)); },
    parPage: 1,
    parPerPage: 10,
    get parPageCount() { return Math.max(1, Math.ceil(this.filteredPartners.length / this.parPerPage)); },
    get pagedPartners() { const s = (this.parPage - 1) * this.parPerPage; return this.filteredPartners.slice(s, s + this.parPerPage); },
    setParPerPage(n) { this.parPerPage = n; this.parPage = 1; },
    parGoto(p) { this.parPage = Math.min(this.parPageCount, Math.max(1, p)); },
    catPage: 1,
    catPerPage: 10,
    get catPageCount() { return Math.max(1, Math.ceil((this.financeCategories || []).length / this.catPerPage)); },
    get pagedCategories() { const s = (this.catPage - 1) * this.catPerPage; return this.sortedFinanceCategories.slice(s, s + this.catPerPage); },
    setCatPerPage(n) { this.catPerPage = n; this.catPage = 1; },
    catGoto(p) { this.catPage = Math.min(this.catPageCount, Math.max(1, p)); },
    _pageSlice(arr, page, per) { const s = (Math.max(1, page) - 1) * per; return (arr || []).slice(s, s + per); },
    _pageCount(len, per) { return Math.max(1, Math.ceil((len || 0) / per)); },
    accountTxCount(pm) { return (this.transactions || []).filter((t) => t.account === pm.id).length; },
    accountBalance(pm) { return (this.transactions || []).filter((t) => t.account === pm.id).reduce((s, t) => s + (t.amount || 0), 0); },
    get accountIncome() { return this.accountTx.filter((t) => t.amount > 0).reduce((s, t) => s + t.amount, 0); },
    get accountExpense() { return this.accountTx.filter((t) => t.amount < 0).reduce((s, t) => s + t.amount, 0); },

    // ---- Receipts (Belege) — files attached to bank transactions ----
    receiptTx: null,        // the transaction whose receipts panel is open
    receiptBusy: false,
    get documentableTx() { return this.accountTx.filter((t) => t.vatCat !== 'private'); },
    get unlinkedIncomeCount() { const id = this.payAccount?.id; return (this.transactions || []).filter((t) => t.account === id && t.amount > 0 && ! t.invoiceId).length; },
    get missingReceipts() { return this.documentableTx.filter((t) => ! (t.receipts && t.receipts.length)).length; },
    receiptCount(tx) { return (tx.receipts || []).filter((r) => ! r.trashed).length; },

    // ---- Belege document manager (flattened receipts across all bookings) ----
    receiptCatSuggestions: ['Geschäftsessen', 'Bewirtung', 'Bürobedarf', 'Reisekosten', 'Fortbildung', 'Software', 'Hardware', 'Marketing', 'Miete', 'Versicherung', 'Kfz', 'Telekommunikation', 'Sonstiges'],
    receiptQuery: '',

    // The raw (plaintext) URL of a stored receipt file (no decryption).
    _receiptRawUrl(tx, r) { return `/finance/transactions/${tx.id}/receipts/${r.id}/raw`; },
    // Raw-URL / bytes for a receipt doc, source-aware (standalone vs. transaction-embedded).
    _docRawUrl(doc) { return doc?.src === 'std' ? `/finance/receipts/${doc.r.id}/raw` : this._receiptRawUrl(doc.tx, doc.r); },
    async _docBytes(doc) {
        const res = await fetch(this._docRawUrl(doc), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (! res.ok) throw new Error('fetch failed');
        return new Uint8Array(await res.arrayBuffer());
    },
    _invoicePdfUrl(inv) { return `/finance/invoices/${inv.id}/pdf`; },
    // The transaction a receipt object belongs to.
    _txOf(r) { return (this.transactions || []).find((t) => (t.receipts || []).some((x) => x === r || (x.id && x.id === r.id))) || null; },
    // Fetch a stored receipt's plaintext bytes (for OCR / re-analysis / export).
    async _receiptBytes(tx, r) {
        const res = await fetch(this._receiptRawUrl(tx, r), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (! res.ok) throw new Error('fetch failed');
        return new Uint8Array(await res.arrayBuffer());
    },

    // Attach a file to a transaction (multipart). Syncs the tx (receipts + version) in
    // place and returns the newly-created receipt entry (server id + blob_path).
    async _attachReceipt(tx, bytes, name, mime, extra = {}) {
        const fd = new FormData();
        fd.append('file', new File([bytes], name || 'receipt', { type: mime || 'application/octet-stream' }));
        if (name) fd.append('name', name);
        if (extra.kind) fd.append('kind', extra.kind);
        if (extra.category) fd.append('category', extra.category);
        for (const t of (extra.tags || [])) fd.append('tags[]', t);
        if (extra.contactId) fd.append('contact_id', extra.contactId);
        if (extra.partnerId != null) fd.append('partner_id', extra.partnerId);
        if (extra.vat) fd.append('vat', extra.vat);
        try {
            const res = await fetch(`/finance/transactions/${tx.id}/receipts`, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }, body: fd });
            if (! res.ok) return null;
            const data = await res.json();
            this._applyTx(tx, normTx(data.transaction));
            return tx.receipts[tx.receipts.length - 1] || null;
        } catch (e) { return null; }
    },
    // Copy the server's fresh receipts + version onto a tx in place (keeps identity).
    _applyTx(tx, server) { tx.receipts = server.receipts; tx.version = server.version; },
    // Delete a receipt: the file endpoint when it has a stored blob, else drop the
    // (fileless, e.g. invoice-link) entry and persist the transaction.
    async _deleteReceiptEntry(tx, r) {
        if (r.blob_path && r.id) {
            const res = await this._req('DELETE', `/finance/transactions/${tx.id}/receipts/${r.id}`);
            if (res.ok && res.data.transaction) { this._applyTx(tx, normTx(res.data.transaction)); return; }
        }
        const arr = tx.receipts || []; const i = arr.indexOf(r); if (i >= 0) arr.splice(i, 1);
        await this._persistTx(tx);
    },

    // Upload from the Belege tab: attach each to a booking whose amount matches (OCR);
    // unmatched/ambiguous ones go to a manual assignment step (held in memory).
    receiptAssign: [],   // [{ bytes, name, mime, sig, ocr, a, total, currency, date, _url, cands }]
    autoUploadBusy: false,
    async uploadReceiptsAuto(fileList) {
        const files = [...(fileList || [])];
        if (! files.length) return;
        this.autoUploadBusy = true;
        await this._ensureContactsLoaded();
        const seen = this._existingReceiptSigs();
        let attached = 0, dupes = 0;
        for (const file of files) {
            try {
                const bytes = new Uint8Array(await file.arrayBuffer());
                const sig = await fileSig(bytes.slice(0));
                if (sig && seen.has(sig)) { dupes++; continue; }
                const mime = file.type || 'application/octet-stream';
                const { ocr, a } = await this._analyze(bytes.slice(0), mime, file.name);
                const total = a ? a.total : null;
                const rcpt = { total, date: a ? a.date : undefined, currency: a ? a.currency : undefined };
                const pick = autoPick(rcpt, this.transactions, 3);
                if (pick) {
                    const rc = await this._attachReceipt(pick, bytes, file.name, mime, { category: a?.category, tags: a?.tags, vat: a?.vat });
                    if (! rc) continue;
                    if (sig) { rc.sig = sig; seen.add(sig); }
                    if (ocr) rc.ocr = ocr;
                    if (a) this._applyAnalysis(rc, a);
                    await this._autoPartner(rc, pick); this._renameReceipt(rc, pick); this._applyReceiptVat(rc, pick);
                    await this._persistTx(pick);
                    attached++;
                } else {
                    const url = URL.createObjectURL(new Blob([bytes], { type: mime }));
                    if (sig) seen.add(sig);
                    this.receiptAssign.push({ bytes, name: file.name, mime, sig, ocr, a, total, currency: a ? a.currency : undefined, date: a ? a.date : undefined, _url: url, cands: suggestBookings(rcpt, this.transactions, { rates: config.fxRates, limit: 12 }).map((s) => s.t) });
                }
            } catch (e) { /* skip */ }
        }
        this.autoUploadBusy = false;
        if (this.receiptAssign.length) this._loadAssignPreview();
        if (attached) window.llToast?.((labels.receipt_auto_attached || ':n receipts matched by amount.').replace(':n', attached));
        if (dupes) window.llToast?.((labels.receipt_dupes_skipped || ':n duplicate(s) skipped.').replace(':n', dupes));
    },
    _existingReceiptSigs() {
        const set = new Set();
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (r.sig) set.add(r.sig);
        for (const r of (this.standaloneReceipts || [])) if (r.sig) set.add(r.sig);
        return set;
    },
    // Upload STANDALONE receipts ("Fremdbelege") — no bank transaction required.
    // The Belege tab uses this so receipts can be filed even with no imported
    // statement. If a booking matches the amount, it is linked (informational).
    async uploadStandaloneReceipts(fileList) {
        const files = [...(fileList || [])];
        if (! files.length) return;
        this.autoUploadBusy = true;
        const seen = this._existingReceiptSigs();
        let added = 0, dupes = 0, failed = 0;
        for (const file of files) {
            let bytes, sig = '';
            try {
                bytes = new Uint8Array(await file.arrayBuffer());
                try { sig = await fileSig(bytes.slice(0)); } catch (e) { sig = ''; }
                if (sig && seen.has(sig)) { dupes++; continue; }
            } catch (e) { failed++; continue; }
            const mime = file.type || 'application/octet-stream';

            // Analysis (OCR / category / VAT / auto-link) is BEST-EFFORT and must
            // never block the upload — a failing text extractor previously skipped
            // the whole file silently ("nothing happens"). Any failure just yields
            // an unannotated receipt.
            let a = null, ocr = '';
            try { const r = await this._analyze(bytes.slice(0), mime, file.name); ocr = r.ocr; a = r.a; } catch (e) { /* upload plain */ }
            let link = null;
            try { if ((this.transactions || []).length) link = autoPick({ total: a ? a.total : null, date: a ? a.date : undefined, currency: a ? a.currency : undefined }, this.transactions, 3); } catch (e) { /* */ }

            try {
                const fd = new FormData();
                fd.append('file', new File([bytes], file.name || 'receipt', { type: mime }));
                if (file.name) fd.append('name', file.name);
                if (a?.category) fd.append('category', a.category);
                for (const t of (a?.tags || [])) fd.append('tags[]', t);
                if (a?.vat) fd.append('vat', a.vat);
                if (ocr) fd.append('ocr', ocr);
                if (sig) fd.append('sig', sig);
                if (link) fd.append('bank_transaction_id', link.id);
                const res = await fetch('/finance/receipts', { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }, body: fd });
                if (! res.ok) { failed++; continue; }
                const data = await res.json();
                if (data?.receipt) { this.standaloneReceipts.unshift(normStandalone(data.receipt)); if (sig) seen.add(sig); added++; }
                else { failed++; }
            } catch (e) { failed++; }
        }
        this.autoUploadBusy = false;
        if (added) window.llToast?.((labels.receipt_uploaded || ':n receipt(s) uploaded.').replace(':n', added));
        if (dupes) window.llToast?.((labels.receipt_dupes_skipped || ':n duplicate(s) skipped.').replace(':n', dupes));
        if (failed) window.llToast?.((labels.receipt_upload_failed || ':n upload(s) failed.').replace(':n', failed));
    },
    // Extract text + analysis from receipt bytes (client-side; ZK-free plaintext pivot).
    async _analyze(bytes, mime, name) {
        const text = await extractDocText(bytes, mime, name);
        if (! text || text.replace(/\s+/g, '').length < 8) return { ocr: '', a: null };
        return { ocr: text.slice(0, 200000), a: analyzeReceiptText(text) };
    },
    async assignPending(idx, tx) {
        const p = this.receiptAssign[idx]; if (! p || ! tx) return;
        const rc = await this._attachReceipt(tx, p.bytes, p.name, p.mime, { category: p.a?.category, tags: p.a?.tags, vat: p.a?.vat });
        if (rc) {
            if (p.sig) rc.sig = p.sig;
            if (p.ocr) rc.ocr = p.ocr;
            if (p.a) this._applyAnalysis(rc, p.a);
            await this._autoPartner(rc, tx); this._renameReceipt(rc, tx); this._applyReceiptVat(rc, tx);
            await this._persistTx(tx);
        }
        if (p._url) { try { URL.revokeObjectURL(p._url); } catch (e) { /* */ } }
        this.receiptAssign.splice(idx, 1);
        this.assignQuery = '';
        if (this.receiptAssign.length) this._loadAssignPreview(); else this.closeAssignPreview();
    },
    dropPending(idx) {
        const p = this.receiptAssign[idx]; if (! p) return;
        if (p._url) { try { URL.revokeObjectURL(p._url); } catch (e) { /* */ } }
        this.receiptAssign.splice(idx, 1);
        this.assignQuery = '';
        if (this.receiptAssign.length) this._loadAssignPreview(); else this.closeAssignPreview();
    },
    assignPreview: null, // { url, mime, name }
    _loadAssignPreview() {
        this.closeAssignPreview();
        const p = this.receiptAssign[0]; if (! p) return;
        this.assignPreview = { url: p._url, mime: p.mime || '', name: p.name || '' };
    },
    closeAssignPreview() { this.assignPreview = null; },
    get assignPreviewIsImage() { return /^image\//.test(this.assignPreview?.mime || '') || /\.(png|jpe?g|gif|webp|bmp|avif)$/i.test(this.assignPreview?.name || ''); },
    get assignPreviewIsPdf() { return this.assignPreview?.mime === 'application/pdf' || /\.pdf$/i.test(this.assignPreview?.name || ''); },
    assignQuery: '',
    assignCandidates() {
        const raw = this.assignQuery.trim();
        const q = raw.toLowerCase();
        let list = (this.transactions || []);
        if (q) list = list.filter((t) =>
            (t.counterparty || '').toLowerCase().includes(q) || (t.purpose || '').toLowerCase().includes(q) ||
            (t.date || '').includes(q) || amountMatches(t.amount, raw));
        return list.sort((a, b) => (b.date || '').localeCompare(a.date || '')).slice(0, 20);
    },
    receiptDoc: null,     // the { r, tx } currently edited in the detail modal
    _receiptContacts: [],
    _docSortKey(d) { return d.src === 'std' ? (d.r.uploadedAt || '') : (d.tx?.date || ''); },
    get allReceipts() {
        const out = [];
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (! r.trashed) out.push({ r, tx, src: 'tx' });
        for (const r of (this.standaloneReceipts || [])) out.push({ r, tx: null, src: 'std' });
        return out.sort((a, b) => this._docSortKey(b).localeCompare(this._docSortKey(a)));
    },
    showReceiptTrash: false,
    get trashedReceipts() {
        // Standalone receipts trash server-side (removed from the active snapshot),
        // so only embedded (transaction) receipts appear in the client trash view.
        const out = [];
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (r.trashed) out.push({ r, tx, src: 'tx' });
        return out.sort((a, b) => String(b.r.trashed).localeCompare(String(a.r.trashed)));
    },
    // Persist a receipt doc to the right store (embedded → its transaction; standalone → /finance/receipts).
    async _persistDoc(doc) {
        if (! doc) return;
        if (doc.src === 'std') { await this._persistStandalone(doc.r); return; }
        await this._persistTx(doc.tx);
    },
    async _persistStandalone(r) {
        if (! r) return;
        const body = { name: r.name, category: r.category, tags: r.tags || [], vat: r.vat, note: r.note, partner_id: r.partnerId, finance_project_id: r.projectId, bank_transaction_id: r.txId, version: r.version };
        const res = await this._req('PUT', '/finance/receipts/' + r.id, body);
        if (res.status === 409) { await this.reload(); window.llToast?.(labels.save_failed || 'Saved on another device — reloaded.'); return; }
        if (! res.ok || ! res.data?.receipt) { window.llToast?.(labels.save_failed || 'Save failed.'); return; }
        Object.assign(r, normStandalone(res.data.receipt));
    },
    async restoreReceipt(d) {
        if (! d?.r) return;
        if (d.src === 'std') { await this._req('POST', '/finance/receipts/' + d.r.id + '/restore'); return; }
        d.r.trashed = false; await this._persistTx(d.tx);
    },
    async deleteReceiptForever(d) {
        if (! d?.r || ! await this.$store.confirm.ask(labels.receipt_delete_confirm || 'Remove this receipt?')) return;
        if (d.src === 'std') { await this._removeStandalone(d.r, true); return; }
        await this._deleteReceiptEntry(d.tx, d.r);
    },
    async emptyReceiptTrash() {
        if (! this.trashedReceipts.length) return;
        if (! await this.$store.confirm.ask(labels.trash_empty_confirm || 'Permanently delete all receipts in the trash?')) return;
        for (const { r, tx } of [...this.trashedReceipts]) await this._deleteReceiptEntry(tx, r);
    },
    // Soft-delete (or force-delete) a standalone receipt server-side + drop it from the list.
    async _removeStandalone(r, force = false) {
        try {
            await this._req('DELETE', '/finance/receipts/' + r.id + (force ? '/force' : ''));
        } catch (e) { /* */ }
        this.standaloneReceipts = (this.standaloneReceipts || []).filter((x) => x.id !== r.id);
    },
    get filteredReceipts() {
        const q = this.receiptQuery.trim().toLowerCase();
        let list = this.allReceipts.filter((d) => this._scopeMatch(this._receiptPrivate(d)));
        if (q) list = list.filter(({ r, tx }) =>
            (r.name || '').toLowerCase().includes(q) || (r.note || '').toLowerCase().includes(q) ||
            (r.category || '').toLowerCase().includes(q) || (r.tags || []).join(' ').toLowerCase().includes(q) ||
            (r.merchant || '').toLowerCase().includes(q) || (r.ocr || '').toLowerCase().includes(q) ||
            (tx.counterparty || '').toLowerCase().includes(q) || (tx.purpose || '').toLowerCase().includes(q));
        return list;
    },
    async openReceiptDoc(doc) {
        this.receiptDoc = doc;
        this.tagsValue = (doc.r.tags || []).join(", ");
        this._loadDocPreview();
        try { this._receiptContacts = []; }
        catch (e) { /* leave empty */ }
    },
    closeReceiptDoc() { this.closeDocPreview(); this.receiptDoc = null; },
    // Inline preview of the open receipt (plaintext raw URL) shown beside its info.
    docPreview: null, // { url, mime, name }
    _loadDocPreview() {
        this.closeDocPreview();
        const doc = this.receiptDoc, r = doc?.r;
        if (! r || ! r.blob_path) return; // invoice-linked receipts have no stored file
        this.docPreview = { url: this._docRawUrl(doc), mime: r.mime || '', name: r.name || '' };
    },
    closeDocPreview() { this.docPreview = null; },
    get docPreviewIsImage() { return /^image\//.test(this.docPreview?.mime || '') || /\.(png|jpe?g|gif|webp|bmp|avif)$/i.test(this.docPreview?.name || ''); },
    get docPreviewIsPdf() { return this.docPreview?.mime === 'application/pdf' || /\.pdf$/i.test(this.docPreview?.name || ''); },
    contactName(id) { const c = (this._receiptContacts || []).find((x) => x.id === id); return c ? contactDisplayName(c) : ''; },
    async saveReceiptDoc() {
        if (! this.receiptDoc) return;
        this.receiptDoc.r.tags = (this.tagsValue || '').split(',').map((t) => t.trim()).filter(Boolean);
        if (this.receiptDoc.tx) await this._learnFromReceipt(this.receiptDoc.r, this.receiptDoc.tx);
        await this._persistDoc(this.receiptDoc);
    },
    // Move a receipt to another booking (re-link) — move the entry between receipts[] lists.
    async relinkReceiptTo(tx) {
        const doc = this.receiptDoc; if (! doc || ! tx || tx.id === doc.tx.id) { this.receiptRelink = false; return; }
        const arr = doc.tx.receipts || []; const i = arr.indexOf(doc.r);
        if (i >= 0) arr.splice(i, 1);
        tx.receipts = tx.receipts || []; tx.receipts.push(doc.r);
        const oldTx = doc.tx; doc.tx = tx; this.receiptRelink = false;
        await this._persistTx(oldTx); await this._persistTx(tx);
    },
    receiptRelink: false,
    get relinkCandidates() {
        const q = (this.relinkQuery || '').trim().toLowerCase();
        let list = (this.transactions || []).filter((t) => t.account === this.receiptDoc?.tx.account);
        if (q) list = list.filter((t) => (t.counterparty || '').toLowerCase().includes(q) || (t.purpose || '').toLowerCase().includes(q) || (t.date || '').includes(q) || amountMatches(t.amount, (this.relinkQuery || '').trim()));
        return list.sort((a, b) => (b.date || '').localeCompare(a.date || '')).slice(0, 12);
    },
    relinkQuery: '',
    renameReceiptDoc() {
        if (! this.receiptDoc) return;
        const doc = this.receiptDoc, cur = doc.r.name || '';
        this.$store.confirm.prompt(labels.receipt_rename || 'Rename receipt', { value: cur }).then((v) => {
            if (v != null && v.trim()) { doc.r.name = v.trim(); this._persistDoc(doc); }
        });
    },
    async deleteReceiptDoc() {
        const doc = this.receiptDoc; if (! doc || doc.r.locked) return;
        if (! await this.$store.confirm.ask(labels.receipt_delete_confirm || 'Remove this receipt?')) return;
        if (doc.src === 'std') await this._removeStandalone(doc.r);
        else await this._deleteReceiptEntry(doc.tx, doc.r);
        this.receiptDoc = null;
    },

    // ---- Re-run OCR/recognition on already-uploaded receipts ----
    reanalyzeBusy: false,
    async reanalyzeReceipt(doc, save = true) {
        const r = doc?.r; if (! r || r.kind === 'invoice' || ! r.blob_path) return false;
        try {
            const bytes = await this._docBytes(doc);
            const text = await extractDocText(bytes, r.mime, r.name);
            if (text && text.replace(/\s+/g, '').length >= 8) {
                r.ocr = text.slice(0, 200000);
                this._applyAnalysis(r, analyzeReceiptText(text));
            }
            if (doc.tx) { // learning + auto-fill only makes sense against a booking
                await this._ensureContactsLoaded();
                await this._autoPartner(r, doc.tx);
                this._renameReceipt(r, doc.tx);
                this._applyReceiptVat(r, doc.tx);
            }
            if (save) { await this._persistDoc(doc); if (this.receiptDoc === doc) this.tagsValue = (r.tags || []).join(', '); }
            return true;
        } catch (e) { return false; }
    },
    reanalyzeTotal: 0,
    reanalyzeProgress: 0,
    async reanalyzeAllReceipts(force = false) {
        if (this.reanalyzeBusy) return;
        const docs = this.allReceipts.filter((d) => d.r.kind !== 'invoice' && (force || ! d.r.ocr));
        if (! docs.length) return;
        this.reanalyzeBusy = true;
        this.reanalyzeTotal = docs.length; this.reanalyzeProgress = 0;
        let n = 0;
        const changedTx = new Set();
        const changedStd = [];
        for (const doc of docs) {
            if (await this.reanalyzeReceipt(doc, false)) { n++; if (doc.src === 'std') changedStd.push(doc); else changedTx.add(doc.tx); }
            this.reanalyzeProgress++;
        }
        for (const tx of changedTx) await this._persistTx(tx);
        for (const doc of changedStd) await this._persistStandalone(doc.r);
        this.reanalyzeBusy = false;
        window.llToast?.((labels.reanalyze_done || ':n receipts recognised.').replace(':n', n));
    },

    // ---- Business partners (Geschäftspartner) ----
    partnerEditing: null,
    newPartner() { this.partnerEditing = { name: '', url: '', address: '', email: '', invoiceEmail: '', phone: '', vatId: '', category: '', note: '', hourlyRate: null, currency: '', contacts: [] }; },
    _newContact() { return { id: this._newId(), name: '', email: '', phone: '', role: '' }; },
    addPartnerContact(p) { if (! p) return; (p.contacts ||= []).push(this._newContact()); },
    removePartnerContact(p, i) { if (p && Array.isArray(p.contacts)) p.contacts.splice(i, 1); },
    partnerContactsFor(name) { const p = matchPartner(this.partners, name); return (p && Array.isArray(p.contacts)) ? p.contacts : []; },
    editPartner(p) { this.partnerEditing = JSON.parse(JSON.stringify(p)); this.partnerEditing.id = p.id; },
    cancelPartner() { this.partnerEditing = null; },
    async savePartner() {
        const p = this.partnerEditing; if (! p || ! String(p.name || '').trim()) return;
        let saved = null;
        if (p.id) {
            const i = this.partners.findIndex((x) => x.id === p.id);
            if (i >= 0) { Object.assign(this.partners[i], p); saved = this.partners[i]; await this._persistPartner(saved); }
        } else {
            const row = await this._create('partners', this._toServerPartner(p), 'partner', normPartner);
            if (! row) return;
            this.partners.push(row); saved = row;
        }
        this.partnerEditing = null;
        if (saved && (saved.url || saved.email || saved.invoiceEmail)) this._fetchPartnerLogo(saved);
    },
    // Logo host: prefer the website, else the domain of the invoice/general email.
    _partnerHost(p) {
        if (! p) return '';
        const fromUrl = this._bankHost(p.url);
        if (fromUrl) return fromUrl;
        for (const addr of [p.invoiceEmail, p.email]) {
            const at = String(addr || '').indexOf('@');
            if (at < 0) continue;
            const dom = String(addr).slice(at + 1).trim().toLowerCase();
            if (dom && dom.includes('.') && ! /\s/.test(dom)) return dom;
        }
        return '';
    },
    async _fetchPartnerLogo(p) {
        const host = this._partnerHost(p);
        if (! host || ! config.iconUrl) return;
        try {
            const res = await fetch(`${config.iconUrl}?domain=${encodeURIComponent(host)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            const { icon } = await res.json();
            if (icon && p.logo !== icon) { p.logo = icon; await this._persistPartner(p); }
        } catch (e) { /* best effort */ }
    },
    partnerLogoSrc(p) { const v = p && p.logo; return (typeof v === 'string' && /^(data:|https?:)/.test(v)) ? v : ''; },
    // Guard a user-entered URL used as a link href: only http(s) is clickable,
    // any other scheme (e.g. javascript:) collapses to '#' — no script sink.
    safeHref(u) { return (typeof u === 'string' && /^https?:\/\//i.test(u.trim())) ? u : '#'; },
    async removePartner(p) {
        if (! await this.$store.confirm.ask(labels.partner_delete_confirm || 'Delete this business partner?')) return;
        await this._destroy('partners', p.id);
        const i = this.partners.indexOf(p); if (i >= 0) this.partners.splice(i, 1);
    },
    get sortedPartners() { return [...(this.partners || [])].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''))); },

    // ---- Business-partners tab (list/table ↔ detail) ----
    partnersView: 'list', // 'list' | 'detail'
    openPartnerId: null,
    partnerSearch: '',
    partnerEditMode: false,
    get filteredPartners() {
        const q = this.partnerSearch.trim().toLowerCase();
        let list = this.sortedPartners;
        if (q) list = list.filter((p) => [p.name, p.contact, p.email, p.phone, p.vatId, p.address, p.url, p.category, p.note].some((v) => String(v || '').toLowerCase().includes(q)));
        return list;
    },
    openPartner(p) { this.openPartnerId = p.id; this.partnersView = 'detail'; this.partnerEditMode = false; },
    backToPartners() { this.partnersView = 'list'; this.openPartnerId = null; this.partnerEditMode = false; },
    get openPartnerRec() { return (this.partners || []).find((p) => p.id === this.openPartnerId) || null; },
    async deleteOpenPartner() {
        const p = this.openPartnerRec; if (! p) return;
        if (! await this.$store.confirm.ask(labels.partner_delete_confirm || 'Delete this business partner?')) return;
        await this._destroy('partners', p.id);
        const i = this.partners.indexOf(p); if (i >= 0) this.partners.splice(i, 1);
        this.backToPartners();
    },
    invoicesForPartner(id) { return (this.invoices || []).filter((i) => ! i.trashed && i.customer && i.customer.partnerId === id).sort((a, b) => (b.issueDate || '').localeCompare(a.issueDate || '')); },
    receiptsForPartner(id) { return this.allReceipts.filter((d) => d.r && d.r.partnerId === id); },
    partnerLinkCount(id) { return this.invoicesForPartner(id).length + this.receiptsForPartner(id).length; },

    _catLocale() { return document.documentElement.lang || undefined; },
    get allCategories() {
        const loc = this._catLocale();
        return [...new Set([...(this.financeCategories || []).map((c) => c.name), ...this.receiptCatSuggestions])].sort((a, b) => a.localeCompare(b, loc));
    },
    get sortedCatSuggestions() {
        const loc = this._catLocale();
        // Hide any default that has been adopted (now exists as a custom category)
        // so it shows once, as the editable row.
        const owned = new Set((this.financeCategories || []).map((c) => String(c.name || '').toLowerCase()));
        return this.receiptCatSuggestions.filter((c) => ! owned.has(c.toLowerCase())).sort((a, b) => a.localeCompare(b, loc));
    },
    get sortedFinanceCategories() { const loc = this._catLocale(); return [...(this.financeCategories || [])].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), loc)); },
    // ---- Custom category CRUD (name + colour + monochrome icon) ----
    // Icon names — each exists in components/icon.blade.php (kept in sync with the
    // controller's CATEGORY_ICONS + _category_icon.blade.php).
    // Kept in sync with FinanceController::CATEGORY_ICONS + _category_icon.blade.php.
    get catIconOptions() {
        return [
            'hashtag', 'tag', 'banknotes', 'credit-card', 'wallet', 'currency-euro', 'currency-dollar', 'currency-pound',
            'currency-yen', 'currency-rupee', 'receipt-percent', 'receipt-refund', 'calculator', 'building-library', 'building-office', 'building-office-2',
            'building-storefront', 'briefcase', 'chart-bar', 'chart-bar-square', 'chart-pie', 'presentation-chart-line', 'presentation-chart-bar', 'arrow-trending-up',
            'arrow-trending-down', 'table-cells', 'list-bullet', 'queue-list', 'document', 'document-text', 'document-check', 'document-duplicate',
            'document-magnifying-glass', 'document-currency-euro', 'document-currency-dollar', 'document-plus', 'document-minus', 'clipboard-document', 'clipboard-document-check', 'clipboard-document-list',
            'newspaper', 'book-open', 'folder', 'folder-open', 'archive-box', 'archive-box-arrow-down', 'inbox', 'inbox-stack',
            'rectangle-stack', 'square-3-stack-3d', 'rectangle-group', 'server', 'server-stack', 'cpu-chip', 'shopping-cart', 'shopping-bag',
            'gift', 'gift-top', 'truck', 'cube', 'cube-transparent', 'wrench', 'wrench-screwdriver', 'cog-6-tooth',
            'cog-8-tooth', 'bolt', 'fire', 'light-bulb', 'command-line', 'beaker', 'scale', 'swatch',
            'paint-brush', 'pencil-square', 'scissors', 'envelope', 'at-symbol', 'phone', 'phone-arrow-up-right', 'chat-bubble-left',
            'chat-bubble-left-right', 'chat-bubble-oval-left', 'megaphone', 'video-camera', 'microphone', 'musical-note', 'speaker-wave', 'signal',
            'rss', 'cloud', 'cloud-arrow-up', 'cloud-arrow-down', 'globe', 'globe-alt', 'globe-europe-africa', 'globe-americas',
            'globe-asia-australia', 'map', 'map-pin', 'route', 'home', 'home-modern', 'camera', 'photo',
            'film', 'printer', 'device-tablet', 'users', 'user-group', 'academic-cap', 'hand-thumb-up', 'hand-thumb-down',
            'hand-raised', 'trophy', 'flag', 'ticket', 'bell', 'bell-alert', 'bookmark', 'star',
            'heart', 'sparkles', 'calendar', 'calendar-days', 'calendar-date-range', 'clock', 'sun', 'moon',
            'plus-circle', 'minus-circle', 'check-badge', 'exclamation-circle', 'question-mark-circle', 'adjustments-horizontal', 'adjustments-vertical', 'funnel',
            'bars-arrow-down', 'bars-arrow-up', 'eye-slash', 'key', 'lock-closed', 'shield', 'shield-check', 'wifi',
            'paper-clip', 'backspace', 'battery-100', 'thermometer', 'cake',
        ];
    },
    get catColorOptions() {
        return ['#7066f5', '#3b9fd6', '#59ad6b', '#e2915a', '#d9a441', '#3fae9f',
            '#9e70fa', '#6b7280', '#e0567a', '#8b5cf6', '#0ea5e9', '#ef4444'];
    },
    catColor(c) { return (c && c.color) || '#6b7280'; },
    catIcon(c) { return (c && c.icon) || 'hashtag'; },
    newCategory: { name: '', color: '#7066f5', icon: 'hashtag' },
    catEditing: null,
    openNewCategory() { this.newCategory = { name: '', color: this.catColorOptions[0], icon: 'hashtag' }; this.catEditing = { id: null, name: '', color: this.catColorOptions[0], icon: 'hashtag' }; },
    editCategory(c) { this.catEditing = { id: c.id, name: c.name, color: this.catColor(c), icon: this.catIcon(c) }; },
    // Editing a built-in default (a plain suggestion string) materialises it as a
    // real, fully-editable category with the chosen colour + icon. The default
    // then drops out of the read-only suggestions list (sortedCatSuggestions
    // dedupes against existing categories) and appears as a custom row.
    editDefault(name) { this.catEditing = { id: null, name: String(name || ''), color: this.catColorOptions[0], icon: 'hashtag' }; },
    cancelCategory() { this.catEditing = null; },
    async saveCategory() {
        const e = this.catEditing; if (! e) return;
        const n = String(e.name || '').trim(); if (! n) return;
        const payload = { name: n, color: e.color || null, icon: e.icon || null };
        if (e.id == null) {
            // Only block a duplicate CUSTOM category — a name matching a default
            // suggestion is allowed (that IS how a default gets adopted/edited).
            const dup = (this.financeCategories || []).some((c) => c.name.toLowerCase() === n.toLowerCase());
            if (! dup) { const row = await this._create('categories', payload, 'category', normCategory); if (row) this.financeCategories.push(row); }
        } else {
            const row = await this._update('categories', e.id, payload, 'category', normCategory);
            if (row) { const i = (this.financeCategories || []).findIndex((c) => c.id === e.id); if (i >= 0) Object.assign(this.financeCategories[i], row); }
        }
        this.catEditing = null;
    },
    // Kept for the simple add-row / programmatic callers.
    async removeFinanceCategory(c) {
        if (! await this.$store.confirm.ask(labels.cats_delete_confirm || 'Delete this category?')) return;
        await this._destroy('categories', c.id);
        const i = this.financeCategories.indexOf(c); if (i >= 0) this.financeCategories.splice(i, 1);
    },

    // ---- Cost projects (nestable): bundle receipts + manual "hand" expenses ----
    projectEditing: null,   // { id?, name, parentId, note, kind } in the create/edit modal
    openProjectId: null,    // the project whose detail is shown
    expenseEditing: null,   // { projectId, id?, amount, date, note, account, category } in the expense modal
    get projectRows() { return buildProjectTree(this.projects); },
    get openProject() { return (this.projects || []).find((p) => p.id === this.openProjectId) || null; },
    projectTotal(id) { return projectRolled(this.projects, id, this.allReceipts); },
    projectOwnTotal(id) { const p = (this.projects || []).find((x) => x.id === id); return p ? Math.round(projectOwn(p, this.allReceipts) * 100) / 100 : 0; },
    projectSubs(id) { return (this.projects || []).filter((p) => (p.parentId || null) === (id || null)); },
    projectReceiptList(id) { return receiptsForProject(this.allReceipts, id); },
    projectName(id) { const p = (this.projects || []).find((x) => x.id === id); return p ? p.name : ''; },
    projectOptions(excludeId) {
        const banned = new Set([excludeId]);
        if (excludeId) { const walk = (pid) => { for (const c of this.projectSubs(pid)) { banned.add(c.id); walk(c.id); } }; walk(excludeId); }
        return buildProjectTree(this.projects).filter((x) => ! banned.has(x.project.id));
    },
    newProject(parentId = null) { const par = parentId ? (this.projects || []).find((x) => x.id === parentId) : null; this.projectEditing = { name: '', parentId: parentId || '', note: '', kind: par ? (par.kind || 'business') : 'business' }; },
    editProject(p) { this.projectEditing = { id: p.id, name: p.name || '', parentId: p.parentId || '', note: p.note || '', kind: p.kind || 'business' }; },
    cancelProject() { this.projectEditing = null; },
    async saveProject() {
        const e = this.projectEditing; if (! e || ! String(e.name || '').trim()) return;
        const parent = e.parentId ? this.projects.find((x) => x.id === e.parentId) : null;
        const kind = parent ? this.effectiveKind(parent.id) : (e.kind === 'private' ? 'private' : 'business');
        if (e.id) {
            const p = this.projects.find((x) => x.id === e.id);
            if (p) { p.name = e.name.trim(); p.parentId = e.parentId || null; p.note = e.note || ''; p.kind = kind; await this._persistProject(p); }
        } else {
            const row = await this._create('projects', this._toServerProject({ name: e.name.trim(), parentId: e.parentId || null, note: e.note || '', kind, expenses: [] }), 'project', normProject);
            if (row) this.projects.push(row);
        }
        await this._normalizeKinds();
        this.projectEditing = null;
    },
    async removeProject(p) {
        if (! p) return;
        if (! await this.$store.confirm.ask(labels.project_delete_confirm || 'Delete this project and its sub-projects? Bundled receipts are kept but un-assigned.')) return;
        const ids = new Set([p.id]);
        const walk = (pid) => { for (const c of this.projectSubs(pid)) { ids.add(c.id); walk(c.id); } };
        walk(p.id);
        const changedTx = new Set();
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (ids.has(r.projectId)) { r.projectId = null; changedTx.add(tx); }
        for (const id of ids) await this._destroy('projects', id);
        this.projects = this.projects.filter((x) => ! ids.has(x.id));
        if (ids.has(this.openProjectId)) this.openProjectId = null;
        for (const tx of changedTx) await this._persistTx(tx);
    },
    newExpense(projectId) { this.expenseEditing = { projectId, amount: null, date: new Date().toISOString().slice(0, 10), note: '', account: '', category: '' }; },
    editExpense(project, exp) { this.expenseEditing = { projectId: project.id, id: exp.id, amount: exp.amount, date: exp.date || '', note: exp.note || '', account: exp.account || '', category: exp.category || '' }; },
    cancelExpense() { this.expenseEditing = null; },
    async saveExpense() {
        const e = this.expenseEditing; if (! e) return;
        const amt = Number(e.amount); if (! Number.isFinite(amt) || amt <= 0) { window.llToast?.(labels.project_expense_invalid || 'Enter an amount.'); return; }
        const p = this.projects.find((x) => x.id === e.projectId); if (! p) return;
        p.expenses = p.expenses || [];
        const fields = { amount: amt, date: e.date || '', note: e.note || '', account: e.account || '', category: e.category || '' };
        if (e.id) { const ex = p.expenses.find((x) => x.id === e.id); if (ex) Object.assign(ex, fields); }
        else { p.expenses.push({ id: this._newId(), ...fields }); }
        this.expenseEditing = null; await this._persistProject(p);
    },
    expenseAccountName(id) { const pm = (this.paymentMethods || []).find((x) => x.id === id); return pm ? pm.label : ''; },
    async removeExpense(project, exp) {
        if (! project || ! exp) return;
        const p = this.projects.find((x) => x.id === project.id); if (! p) return;
        const i = (p.expenses || []).indexOf(exp); if (i >= 0) { p.expenses.splice(i, 1); await this._persistProject(p); }
    },
    async setReceiptProject(id) { const r = this.receiptDoc?.r; if (! r) return; r.projectId = id || null; await this._persistDoc(this.receiptDoc); },
    openProjectDetail(id) { this.openProjectId = id; this.subPage = 1; this.expPage = 1; this.prcPage = 1; },
    get projectKindSummary() {
        let business = 0, priv = 0;
        for (const p of (this.projects || [])) { const t = this.projectOwnTotal(p.id); if (this.effectiveKind(p.id) === 'private') priv += t; else business += t; }
        return { business: Math.round(business * 100) / 100, private: Math.round(priv * 100) / 100 };
    },
    projectKindLabel(kind) { return kind === 'private' ? (labels.project_kind_private || 'Private') : (labels.project_kind_business || 'Business'); },
    effectiveKind(id) {
        const byId = new Map((this.projects || []).map((p) => [p.id, p]));
        let cur = byId.get(id), guard = 0;
        while (cur && cur.parentId && byId.get(cur.parentId) && guard++ < 100) cur = byId.get(cur.parentId);
        return cur && cur.kind === 'private' ? 'private' : 'business';
    },
    async _normalizeKinds() { for (const p of (this.projects || [])) { const k = this.effectiveKind(p.id); if (p.kind !== k) { p.kind = k; await this._persistProject(p); } } },
    get scopedProjectRows() { return this.projectRows.filter((r) => this._scopeMatch(this.effectiveKind(r.project.id) === 'private')); },
    projPage: 1, projPerPage: 15,
    get projPageCount() { return this._pageCount(this.scopedProjectRows.length, this.projPerPage); },
    get pagedProjectRows() { return this._pageSlice(this.scopedProjectRows, this.projPage, this.projPerPage); },
    setProjPerPage(n) { this.projPerPage = n; this.projPage = 1; },
    projGoto(p) { this.projPage = Math.min(this.projPageCount, Math.max(1, p)); },
    subPage: 1, subPerPage: 10,
    get subPageCount() { return this._pageCount(this.projectSubs(this.openProjectId).length, this.subPerPage); },
    get pagedSubs() { return this._pageSlice(this.projectSubs(this.openProjectId), this.subPage, this.subPerPage); },
    setSubPerPage(n) { this.subPerPage = n; this.subPage = 1; },
    subGoto(p) { this.subPage = Math.min(this.subPageCount, Math.max(1, p)); },
    expPage: 1, expPerPage: 10,
    get expPageCount() { return this._pageCount((this.openProject?.expenses || []).length, this.expPerPage); },
    get pagedExpenses() { return this._pageSlice(this.openProject?.expenses || [], this.expPage, this.expPerPage); },
    setExpPerPage(n) { this.expPerPage = n; this.expPage = 1; },
    expGoto(p) { this.expPage = Math.min(this.expPageCount, Math.max(1, p)); },
    prcPage: 1, prcPerPage: 10,
    get prcPageCount() { return this._pageCount(this.projectReceiptList(this.openProjectId).length, this.prcPerPage); },
    get pagedProjectReceipts() { return this._pageSlice(this.projectReceiptList(this.openProjectId), this.prcPage, this.prcPerPage); },
    setPrcPerPage(n) { this.prcPerPage = n; this.prcPage = 1; },
    prcGoto(p) { this.prcPage = Math.min(this.prcPageCount, Math.max(1, p)); },
    receiptPicker: false,
    receiptPickerQuery: '',
    openReceiptPicker() { this.receiptPickerQuery = ''; this.receiptPicker = true; },
    closeReceiptPicker() { this.receiptPicker = false; },
    pickerReceipts() {
        const q = (this.receiptPickerQuery || '').trim().toLowerCase();
        let list = this.allReceipts.filter((d) => d.r.kind !== 'invoice');
        if (q) list = list.filter(({ r, tx }) => (r.name || '').toLowerCase().includes(q) || (r.merchant || '').toLowerCase().includes(q) || (tx.counterparty || '').toLowerCase().includes(q) || (tx.purpose || '').toLowerCase().includes(q));
        return list.slice(0, 100);
    },
    async toggleReceiptToProject(d) {
        if (! d?.r || ! this.openProjectId) return;
        d.r.projectId = d.r.projectId === this.openProjectId ? null : this.openProjectId;
        await this._persistTx(d.tx);
    },

    // Unified partner picker for a receipt: existing contacts + standalone partners.
    partnerOptions() {
        const q = (this.receiptDoc?.r.partnerQuery || '').trim().toLowerCase();
        const contacts = (this._receiptContacts || []).map((c) => ({ kind: 'contact', id: c.id, name: contactDisplayName(c) || '' }));
        const partners = (this.partners || []).map((p) => ({ kind: 'partner', id: p.id, name: p.name || '' }));
        let list = [...partners, ...contacts].filter((o) => o.name);
        if (q) list = list.filter((o) => o.name.toLowerCase().includes(q));
        return list.sort((a, b) => a.name.localeCompare(b.name)).slice(0, 10);
    },
    async setReceiptPartner(opt) {
        const r = this.receiptDoc?.r; if (! r) return;
        if (! opt) { r.contactId = null; r.partnerId = null; r.partnerName = ''; }
        else if (opt.kind === 'contact') { r.contactId = opt.id; r.partnerId = null; r.partnerName = opt.name; }
        else { r.partnerId = opt.id; r.contactId = null; r.partnerName = opt.name; }
        r.partnerQuery = '';
        if (! r.category && opt && opt.name) { const learned = this._learnedCategory(opt.name); if (learned) r.category = learned; }
        await this._persistDoc(this.receiptDoc);
    },
    receiptPartnerName(r) {
        if (r.partnerName) return r.partnerName;
        if (r.contactId) return this.contactName(r.contactId) || '';
        if (r.partnerId) { const p = (this.partners || []).find((x) => x.id === r.partnerId); return p ? p.name : ''; }
        return '';
    },
    async _ensureContactsLoaded() {
        if ((this._receiptContacts || []).length) return;
        try { this._receiptContacts = []; }
        catch (e) { /* leave empty */ }
    },
    _normName(s) { return normMerchant(s); },
    _partnerByName(name) { return matchPartner(this.partners, name); },
    // Find or CREATE a business partner server-side (its id becomes a real FK on invoices).
    async _findOrCreatePartner(name) {
        let p = this._partnerByName(name);
        if (! p) {
            const row = await this._create('partners', this._toServerPartner({ name: String(name).trim(), contacts: [] }), 'partner', normPartner);
            if (row) { this.partners.push(row); p = row; }
            else p = { id: null, name: String(name).trim() };
        }
        return p;
    },
    _learnedCategory(name) { return learnedCategoryFor(this.partners, name); },
    async _learnFromReceipt(r, tx) {
        const name = String((tx && tx.counterparty) || r.merchant || '').trim();
        if (name.length < 2 || ! r.category) return;
        const p = await this._findOrCreatePartner(name);
        if (p && p.id && p.category !== r.category) { p.category = r.category; await this._persistPartner(p); }
    },
    async _autoPartner(r, tx) {
        const name = String((tx && tx.counterparty) || r.merchant || '').trim();
        if (name.length < 2) return;
        const learned = this._learnedCategory(name);
        if (learned) r.category = learned;
        if (r.contactId || r.partnerId) return;
        const nk = this._normName(name);
        const contact = (this._receiptContacts || []).find((c) => this._normName(contactDisplayName(c)) === nk);
        if (contact) { r.contactId = contact.id; r.partnerName = contactDisplayName(contact); return; }
        const partner = await this._findOrCreatePartner(name);
        if (partner && partner.id) { r.partnerId = partner.id; r.partnerName = partner.name; }
    },
    openReceipts(tx) { this.receiptTx = tx; },
    closeReceipts() { this.receiptTx = null; },
    async uploadReceipts(fileList) {
        const tx = this.receiptTx;
        if (! tx) return;
        const files = [...(fileList || [])];
        if (! files.length) return;
        this.receiptBusy = true;
        const seen = this._existingReceiptSigs();
        let ok = 0, dupes = 0;
        for (const file of files) {
            try {
                const bytes = new Uint8Array(await file.arrayBuffer());
                const sig = await fileSig(bytes.slice(0));
                if (sig && seen.has(sig)) { dupes++; continue; }
                const rc = await this._attachReceipt(tx, bytes, file.name, file.type || 'application/octet-stream', {});
                if (! rc) continue;
                if (sig) { rc.sig = sig; seen.add(sig); }
                ok++;
                await this._ocrReceipt(bytes.slice(0), rc, tx);
                await this._persistTx(tx); // persist enrichment before the next attach replaces receipts[]
            } catch (e) { /* skip this file */ }
        }
        this.receiptBusy = false;
        if (! ok && ! dupes) window.llToast?.(labels.receipt_failed || 'Upload failed.');
        if (dupes) window.llToast?.((labels.receipt_dupes_skipped || ':n duplicate(s) skipped.').replace(':n', dupes));
    },
    // Background OCR of a freshly-attached receipt: enrich fields only (caller persists).
    async _ocrReceipt(bytes, r, tx) {
        try {
            const { ocr, a } = await this._analyze(bytes, r.mime, r.name);
            if (! a) return;
            r.ocr = ocr;
            this._applyAnalysis(r, a);
            await this._ensureContactsLoaded();
            await this._autoPartner(r, tx);
            this._renameReceipt(r, tx);
            this._applyReceiptVat(r, tx);
        } catch (e) { /* best effort */ }
    },
    _applyAnalysis(r, a) {
        if (! r.category && a.category) r.category = a.category;
        if ((! r.tags || ! r.tags.length) && a.tags.length) r.tags = a.tags;
        if (a.merchant && ! r.merchant) r.merchant = a.merchant;
        if (a.date && ! r.date) r.date = a.date;
        if (a.total != null && r.total == null) r.total = a.total;
        if (a.number && ! r.number) r.number = a.number;
        if (a.vat && ! r.vat) r.vat = a.vat;
        if (a.currency && ! r.currency) r.currency = a.currency;
    },
    _applyReceiptVat(r, tx) {
        if (tx && r && r.vat && (tx.vatCat == null || tx.vatCat === '')) tx.vatCat = r.vat;
    },
    _renameReceipt(r, tx) {
        if (! r) return false;
        const ext = ((r.name || '').match(/\.[^.]+$/) || [])[0] || (r.mime === 'application/pdf' ? '.pdf' : '');
        const partner = this.receiptPartnerName(r) || r.merchant || (tx && tx.counterparty) || '';
        const name = buildReceiptName({
            date: r.date || (tx && tx.date) || '',
            partner,
            number: r.number || r.invoiceNumber || '',
            belegWord: labels.receipt || 'Beleg',
            invoiceWord: labels.invoice_word || 'Rechnung',
            ext,
        });
        if (name && name !== r.name) { r.name = name; return true; }
        return false;
    },
    // Quick-look a receipt/invoice in a modal (plaintext raw URL). App invoices with no
    // stored PDF open the invoice view instead.
    receiptPreview: null, // { url, mime, name }
    openReceipt(r) {
        if (r.kind === 'invoice' && ! r.blob_path) return this.openInvoiceById(r.invoiceId, r.invoiceNumber);
        const tx = this._txOf(r);
        if (! tx || ! r.blob_path) { window.llToast?.(labels.downloadFailed || 'Could not open file.'); return; }
        this.closeReceiptPreview();
        this.receiptPreview = { url: this._receiptRawUrl(tx, r), mime: r.mime || '', name: r.name || '' };
    },
    closeReceiptPreview() { this.receiptPreview = null; },
    get previewIsImage() { return /^image\//.test(this.receiptPreview?.mime || '') || /\.(png|jpe?g|gif|webp|bmp|avif)$/i.test(this.receiptPreview?.name || ''); },
    get previewIsPdf() { return this.receiptPreview?.mime === 'application/pdf' || /\.pdf$/i.test(this.receiptPreview?.name || ''); },
    openReceiptInTab() { if (this.receiptPreview?.url) window.open(this.receiptPreview.url, '_blank'); },

    // Send a receipt to Paperless — fetch the plaintext file + pre-fill the transfer modal.
    async sendReceiptToPaperless(doc) {
        const r = doc?.r; if (! r || ! r.blob_path) return;
        const store = this.$store.paperless;
        if (! store || ! store.configured) return;
        if (! await this.$store.confirm.ask(labels.paperlessWarn || labels.paperless_warn || '')) return;
        const created = r.date || (r.ocr ? analyzeReceiptText(r.ocr).date : '') || (doc.tx.date || '');
        const title = r.merchant || (r.name || '').replace(/\.[^.]+$/, '') || 'Beleg';
        store.begin(r.name || 'beleg.pdf', { title, created: created || undefined }, { context: { source: 'receipt' } });
        const partner = this.receiptPartnerName(r); if (partner) store.corrQuery = partner;
        if (r.category) store.tagQuery = r.category;
        try {
            const bytes = await this._receiptBytes(doc.tx, r);
            store.setFile(new Blob([bytes], { type: r.mime || 'application/pdf' }));
        } catch (e) { store.fail(labels.downloadFailed || 'Could not open file.'); }
    },
    // ---- Bulk receipt export (ZIP) for the tax advisor ----
    exportBusy: false,
    exportDone: 0,
    exportTotal: 0,
    accountReceiptTotal(pm) { return (this.transactions || []).filter((t) => t.account === pm.id).reduce((n, t) => n + (t.receipts || []).length, 0); },
    _csvCell(v) { const s = String(v ?? ''); return /[",;\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; },
    async downloadAllReceipts(pm) {
        const txs = (this.transactions || []).filter((t) => t.account === pm.id && (t.receipts || []).length);
        const total = txs.reduce((n, t) => n + t.receipts.length, 0);
        if (! total) { window.llToast?.(labels.export_none || 'No receipts to export.'); return; }
        this.exportBusy = true; this.exportDone = 0; this.exportTotal = total;
        const files = {}; const used = new Set(); const errors = [];
        const rows = [['Datum', 'Gegenkonto', 'Betrag', 'Waehrung', 'USt', 'Zweck', 'Datei', 'Status'].map((c) => this._csvCell(c)).join(';')];
        const clean = (s) => String(s ?? '').replace(/[/\\:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim();
        for (const tx of txs) {
            for (const r of tx.receipts) {
                const ymd = (tx.date || '').replace(/-/g, '') || 'ohne-datum';
                const party = clean(tx.counterparty || tx.purpose || 'Buchung').slice(0, 60) || 'Buchung';
                const invNo = clean(tx.invoiceNumber || tx.eref || '');
                const ext = ((r.name || '').match(/\.[^.]+$/) || [(r.mime === 'application/pdf' ? '.pdf' : '')])[0] || '';
                let name = [ymd, party, invNo].filter(Boolean).join('; ') + ext;
                let n = name, i = 2;
                while (used.has(n)) { n = name.replace(/(\.[^.]+)?$/, ` (${i++})$1`); }
                used.add(n); name = n;
                let status = 'ok';
                try {
                    if (! r.blob_path) throw new Error('no file');
                    const bytes = await this._receiptBytes(tx, r);
                    files[name] = bytes;
                } catch (e) { status = 'FEHLER'; errors.push(name); }
                rows.push([tx.date, tx.counterparty, (tx.amount || 0).toFixed(2), tx.currency || 'EUR', tx.vatCat || '', tx.purpose, name, status].map((c) => this._csvCell(c)).join(';'));
                this.exportDone++;
            }
        }
        files['belege-index.csv'] = new TextEncoder().encode('﻿' + rows.join('\r\n'));
        try {
            const { zip } = await import('fflate');
            const zipped = await new Promise((resolve, reject) => zip(files, { level: 6 }, (err, data) => err ? reject(err) : resolve(data)));
            const stamp = new Date().toISOString().slice(0, 10);
            saveBlobAs(zipped, `belege-${String(pm.label || 'konto').replace(/[^\w.\-]+/g, '_')}-${stamp}.zip`, 'application/zip');
            if (errors.length) window.llToast?.((labels.export_partial || ':n receipts could not be exported.').replace(':n', errors.length));
            else window.llToast?.((labels.export_done || ':n receipts exported.').replace(':n', total));
        } catch (e) { window.llToast?.(labels.export_failed || 'Export failed.'); }
        this.exportBusy = false;
    },
    async removeReceipt(tx, r) {
        if (r.locked) return; // auto-attached invoice — cannot be removed, only added to
        if (! await this.$store.confirm.ask(labels.receipt_delete_confirm || 'Remove this receipt?')) return;
        // Soft-trash: keep the file (blob_path stays) so it can be restored.
        r.trashed = new Date().toISOString();
        await this._persistTx(tx);
    },

    // ---- Invoice ↔ transaction matching (incoming payments) ----
    _invoiceVatCat(inv) {
        const rates = Object.keys(this.computeTotals(inv).vatByRate).map((r) => String(parseInt(r, 10)));
        const cand = ['19', '16', '7', '0'].find((c) => rates.includes(c));
        return cand || '';
    },
    async _linkInvoice(tx, inv, save = true) {
        if (! tx || ! inv || tx.invoiceId === inv.id) return false;
        tx.invoiceId = inv.id;
        tx.invoiceNumber = inv.number;
        const vc = this._invoiceVatCat(inv); if (vc) tx.vatCat = vc;
        inv.status = 'paid';
        inv.paidAt = tx.date || this._today();
        inv.paymentAccount = String(tx.account);
        inv.paymentTxId = tx.id;
        tx.receipts = tx.receipts || [];
        if (! tx.receipts.some((r) => r.kind === 'invoice' && r.invoiceId === inv.id)) {
            const rec = { id: this._newId(), kind: 'invoice', invoiceId: inv.id, invoiceNumber: inv.number, name: (labels.invoice_word || 'Invoice') + ' ' + inv.number, locked: true };
            tx.receipts.push(rec);
        }
        if (save) { await this._persistTx(tx); await this._persistInvoice(inv); }
        return true;
    },
    async linkInvoice(tx, inv) { if (await this._linkInvoice(tx, inv)) window.llToast?.((labels.match_linked || 'Linked invoice :n.').replace(':n', inv.number)); this.invoicePicker = null; },
    async rematchAll(silent = false) {
        const id = this.payAccount?.id;
        let n = 0;
        for (const tx of (this.transactions || [])) {
            if (tx.account === id && tx.amount > 0 && ! tx.invoiceId) {
                const inv = matchInvoice(tx, this.invoices);
                if (inv && await this._linkInvoice(tx, inv)) n++;
            }
        }
        if (! silent) window.llToast?.((labels.match_done || ':n invoices matched.').replace(':n', n));
        return n;
    },
    invoicePicker: null,
    openInvoicePicker(tx) { this.invoicePicker = tx; },
    get pickerInvoices() {
        return (this.invoices || []).filter((i) => ! i.trashed && i.number && i.status !== 'draft')
            .sort((a, b) => (b.issueDate || '').localeCompare(a.issueDate || ''));
    },
    async openInvoiceById(id, number = null) {
        let inv = (this.invoices || []).find((i) => i.id === id);
        if (! inv && number) {
            const n = String(number).trim();
            inv = (this.invoices || []).find((i) => ! i.trashed && String(i.number || '').trim() === n);
            if (inv) {
                const changed = new Set();
                for (const tx of (this.transactions || [])) {
                    if (tx.invoiceNumber === inv.number && tx.invoiceId !== inv.id) { tx.invoiceId = inv.id; changed.add(tx); }
                    for (const r of (tx.receipts || [])) if (r.kind === 'invoice' && r.invoiceNumber === inv.number && r.invoiceId !== inv.id) { r.invoiceId = inv.id; changed.add(tx); }
                }
                for (const tx of changed) await this._persistTx(tx);
            }
        }
        if (! inv) { window.llToast?.(labels.match_gone || 'Invoice not found.'); return; }
        this.receiptTx = null;
        this.setSection('invoices');
        this.open(inv);
    },

    async _readStatement(file) {
        const buf = await file.arrayBuffer();
        const utf8 = new TextDecoder('utf-8', { fatal: false }).decode(buf);
        if (utf8.includes('�')) { try { return new TextDecoder('windows-1252').decode(buf); } catch (e) { /* keep utf8 */ } }
        return utf8;
    },
    txFields: TX_FIELDS,
    txFieldLabel(f) { return labels['txf_' + f] || f; },
    async importStatement(fileList) {
        const file = (fileList || [])[0];
        if (! file || ! this.payAccount) return;
        let text;
        try { text = await this._readStatement(file); } catch (e) { window.llToast?.(labels.stmt_read_failed || 'Could not read the file.'); return; }
        const fmt = detectFormat(text, file.name);
        if (fmt === 'mt940') {
            const r = parseMt940(text);
            this._previewStatement(r.transactions, { name: file.name, format: 'MT940' });
        } else if (fmt === 'csv') {
            const c = parseCsv(text);
            const known = detectCsvMapping(c.header);
            if (known) {
                const { transactions } = applyCsvMapping(c.header, c.rows, known.map);
                this._previewStatement(transactions, { name: file.name, format: 'CSV · ' + known.name });
            } else {
                this.stmt = { stage: 'map', name: file.name, header: c.header, rows: c.rows, mapping: this._guessMapping(c.header), transactions: [], fresh: [], dupes: 0 };
            }
        } else {
            window.llToast?.(labels.stmt_unknown || 'Unsupported statement format.');
        }
    },
    _guessMapping(header) {
        const m = {};
        const rules = { date: /datum|date|buchung/i, amount: /betrag|amount|umsatz|wert/i, purpose: /zweck|verwendung|purpose|description|referen/i, counterparty: /empf|beguenst|name|payee|gegen/i, iban: /iban/i, bic: /bic|swift/i };
        for (const [field, re] of Object.entries(rules)) { const col = (header || []).find((h) => re.test(h)); if (col) m[field] = col; }
        return m;
    },
    stmtMapReady() { return TX_REQUIRED.every((f) => this.stmt?.mapping?.[f]); },
    applyStmtMapping() {
        if (! this.stmt || ! this.stmtMapReady()) return;
        const { transactions } = applyCsvMapping(this.stmt.header, this.stmt.rows, this.stmt.mapping);
        this._previewStatement(transactions, { name: this.stmt.name, format: 'CSV' });
    },
    _previewStatement(transactions, meta) {
        const existing = (this.transactions || []).filter((t) => t.account === this.payAccount.id);
        const { fresh, updates } = enrichExisting(existing, transactions);
        const dupes = transactions.length - fresh.length - updates.length;
        this.stmt = { stage: 'preview', name: meta.name, format: meta.format, transactions, fresh, updates, dupes };
    },
    // Bulk-import the parsed fresh rows (server dedups by sig), enrich existing rows,
    // then reload and auto-match incoming payments to invoices.
    async confirmStatementImport() {
        if (! this.stmt || ! this.payAccount) return;
        const acct = this.payAccount.id;
        const rows = (this.stmt.fresh || []).map((tx) => ({
            date: tx.date,
            amount: tx.amount,
            sig: txSig(tx),
            vat_cat: (tx.vatCat != null ? tx.vatCat : guessVatCat(tx)) || null,
            counterparty: tx.counterparty || null,
            counterparty_iban: tx.iban || null,
            bic: tx.bic || null,
            purpose: tx.purpose || null,
            booking_text: tx.bookingText || null,
            eref: tx.eref || null,
        }));
        let created = 0;
        if (rows.length) {
            const r = await this._req('POST', '/finance/transactions/bulk', { payment_method_id: acct, transactions: rows });
            created = (r.data && r.data.created) || 0;
        }
        // Enrich existing records with the newly-available fields.
        for (const u of (this.stmt.updates || [])) {
            const target = this.transactions.find((t) => t.account === acct && txSig(t) === u.sig);
            if (target) { Object.assign(target, u.patch); await this._persistTx(target); }
        }
        await this.reload(); // pull the newly-created rows (with server ids)
        await this.rematchAll(true); // auto-link incoming payments to invoices
        const m = (this.stmt.updates || []).length;
        window.llToast?.((labels.stmt_imported || ':n transactions imported.').replace(':n', created) + (m ? ' · ' + (labels.stmt_enriched || ':n updated').replace(':n', m) : ''));
        this.stmt = null;
    },
    txType(tx) { return classifyTxType(tx); },
    txTypeLabel(tx) { return labels['txtype_' + classifyTxType(tx)] || ''; },
    txTypeName(type) { return labels['txtype_' + type] || type; },

    // ---- VAT category per booking (for the USt calculation) ----
    vatCats: VAT_CATS,
    vatCatLabel(cat) { return cat ? (labels['vatcat_' + cat] || cat) : (labels.vatcat_none || '—'); },
    async setVatCat(tx, cat) { tx.vatCat = cat; await this._persistTx(tx); },
    get accountVat() { return accountVatSummary(this.accountTx); },
    cancelStatement() { this.stmt = null; },

    // ---- Derived ----
    get activeInvoices() { return (this.invoices || []).filter((i) => ! i.trashed); },
    showInvTrash: false,
    get trashedInvoices() { return [...this.invTrash].sort((a, b) => String(b.trashed).localeCompare(String(a.trashed))); },
    async deleteInvoiceForever(inv) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm || 'Delete this invoice permanently?')) return;
        await this._destroy('invoices', inv.id + '/force');
        const i = this.invTrash.indexOf(inv); if (i >= 0) this.invTrash.splice(i, 1);
    },
    async emptyInvoiceTrash() {
        if (! this.trashedInvoices.length) return;
        if (! await this.$store.confirm.ask(labels.trash_empty_confirm || 'Permanently delete all invoices in the trash?')) return;
        for (const inv of [...this.invTrash]) await this._destroy('invoices', inv.id + '/force');
        this.invTrash = [];
    },
    invYear: '', invCustomer: '', invLinked: '', // '' | 'linked' | 'open'
    get invFiltersActive() { return !! (this.query.trim() || this.filterStatus || this.invYear || this.invCustomer || this.invLinked); },
    resetInvFilters() { this.query = ''; this.filterStatus = ''; this.invYear = ''; this.invCustomer = ''; this.invLinked = ''; this.invPage = 1; },
    get invoiceYears() { return [...new Set(this.activeInvoices.map((i) => invoiceYear(i)).filter(Boolean))].sort((a, b) => b.localeCompare(a)); },
    get invoiceCustomers() { return [...new Set(this.activeInvoices.map((i) => i.customer?.name).filter(Boolean))].sort((a, b) => a.localeCompare(b)); },
    get filtered() {
        if (this.financeScope === 'private') return [];
        const q = this.query.trim().toLowerCase();
        const raw = this.query.trim();
        let list = this.activeInvoices;
        if (this.filterStatus) list = list.filter((i) => i.status === this.filterStatus);
        if (this.invYear) list = list.filter((i) => invoiceYear(i) === String(this.invYear));
        if (this.invCustomer) list = list.filter((i) => (i.customer?.name || '') === this.invCustomer);
        if (this.invLinked === 'linked') list = list.filter((i) => this.isInvoiceLinked(i));
        else if (this.invLinked === 'open') list = list.filter((i) => ! this.isInvoiceLinked(i));
        if (q) list = list.filter((i) => (i.number || '').toLowerCase().includes(q) || (i.customer?.name || '').toLowerCase().includes(q) || amountMatches(this.computeTotals(i).gross, raw));
        return [...list].sort((a, b) => (b.issueDate || '').localeCompare(a.issueDate || '') || (b.number || '').localeCompare(a.number || ''));
    },
    get totals() { return this.computeTotals(this.current); },

    _today() { return new Date().toISOString().slice(0, 10); },
    fmtDate(d) { return d ? formatDate(d) : ''; },

    // ---- Eigenbeleg (self-issued voucher for a booking with no original receipt) ----
    eigenbeleg: null, // fields when the modal is open
    _egTx: null,      // the booking it belongs to
    egBusy: false,
    egGrundOptions: ['privatentnahme', 'privateinlage', 'trinkgeld', 'betriebsausgabe', 'sachgeschenk', 'sonstiges'],
    newEigenbeleg(tx) {
        if (! tx) return;
        const amt = Number(tx.amount) || 0;
        const grund = tx.vatCat === 'private' ? (amt >= 0 ? 'privateinlage' : 'privatentnahme') : 'betriebsausgabe';
        const ort = String(this.company.address || '').split(/\r?\n/).map((s) => s.trim()).filter(Boolean).pop() || '';
        this._egTx = tx;
        this.eigenbeleg = {
            grund,
            grundOther: '',
            recipient: tx.counterparty || '',
            address: '',
            ort,
            date: tx.date || this._today(),
            createdAt: this._today(),
            buchungstext: String(tx.purpose || tx.bookingText || '').slice(0, 300),
            gross: Math.abs(amt),
            vatRate: this._defaultVat(),
            reason: '',
            issuer: this.company.name || '',
            signature: '',
        };
    },
    cancelEigenbeleg() { this.eigenbeleg = null; this._egTx = null; this._egCtx = null; this._egDrawing = false; },

    _egDrawing: false,
    _egCtx: null,
    egSigInit() {
        const c = this.$refs.egCanvas; if (! c) return;
        const ratio = window.devicePixelRatio || 1;
        c.width = Math.max(1, c.clientWidth) * ratio;
        c.height = Math.max(1, c.clientHeight) * ratio;
        const ctx = c.getContext('2d');
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#111';
        this._egCtx = ctx;
        if (this.eigenbeleg?.signature) { const img = new Image(); img.onload = () => ctx.drawImage(img, 0, 0, c.clientWidth, c.clientHeight); img.src = this.eigenbeleg.signature; }
    },
    _egPos(e) { const c = this.$refs.egCanvas; const r = c.getBoundingClientRect(); const p = e.touches ? e.touches[0] : e; return { x: p.clientX - r.left, y: p.clientY - r.top }; },
    egSigStart(e) { if (! this._egCtx) this.egSigInit(); if (! this._egCtx) return; this._egDrawing = true; const { x, y } = this._egPos(e); this._egCtx.beginPath(); this._egCtx.moveTo(x, y); },
    egSigMove(e) { if (! this._egDrawing || ! this._egCtx) return; const { x, y } = this._egPos(e); this._egCtx.lineTo(x, y); this._egCtx.stroke(); },
    egSigEnd() { if (! this._egDrawing) return; this._egDrawing = false; const c = this.$refs.egCanvas; if (this.eigenbeleg && c) this.eigenbeleg.signature = c.toDataURL('image/png'); },
    egSigClear() { const c = this.$refs.egCanvas; if (c && this._egCtx) this._egCtx.clearRect(0, 0, c.width, c.height); if (this.eigenbeleg) this.eigenbeleg.signature = ''; },
    get egIsExpense() { return this.eigenbeleg?.grund === 'betriebsausgabe'; },
    egGrundLabel(g) { return labels['eg_grund_' + g] || g; },
    privatLabel(tx) {
        if (! tx || tx.vatCat !== 'private') return '';
        return (Number(tx.amount) || 0) < 0 ? (labels.eg_grund_privatentnahme || 'Privatentnahme') : (labels.eg_grund_privateinlage || 'Privateinlage');
    },
    hasEigenbeleg(tx) { return !! (tx && (tx.receipts || []).some((r) => r && r.kind === 'eigenbeleg')); },
    needsEigenbeleg(tx) { return !! tx && tx.vatCat === 'private' && ! (tx.receipts && tx.receipts.length); },
    get accountPrivateNoEg() { return this.accountTx.filter((tx) => this.needsEigenbeleg(tx)).length; },
    get egNet() { const g = parseFloat(this.eigenbeleg?.gross) || 0; const r = parseFloat(this.eigenbeleg?.vatRate) || 0; return this._round2(g / (1 + r / 100)); },
    get egVat() { return this._round2((parseFloat(this.eigenbeleg?.gross) || 0) - this.egNet); },
    egVatChoices() { const s = new Set([19, 16, 7, 0]); const v = this.eigenbeleg?.vatRate; if (v != null) s.add(Number(v)); return [...s].sort((a, b) => b - a); },
    async saveEigenbeleg() {
        const e = this.eigenbeleg, tx = this._egTx;
        if (! e || ! tx) return;
        if (! (parseFloat(e.gross) > 0) || (this.egIsExpense && (! String(e.recipient || '').trim() || ! String(e.reason || '').trim()))) {
            window.llToast?.(labels.eg_missing || 'Betrag (und bei Betriebsausgabe Empfänger + Begründung) sind Pflicht.');
            return;
        }
        this.egBusy = true;
        try {
            const bytes = await this._renderEigenbelegPdf();
            if (! bytes) throw new Error('render');
            const label = String(e.recipient || '').trim() || this.egGrundLabel(e.grund);
            const base = `Eigenbeleg ${e.date} ${label}`.replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim().slice(0, 120);
            const rc = await this._attachReceipt(tx, bytes, base + '.pdf', 'application/pdf', { kind: 'eigenbeleg' });
            if (! rc) throw new Error('upload');
            rc.eigenbeleg = { ...e, net: this.egNet, vat: this.egVat };
            await this._persistTx(tx);
            this.eigenbeleg = null; this._egTx = null;
            window.llToast?.(labels.eg_done || 'Eigenbeleg erstellt.');
        } catch (err) { window.llToast?.(labels.eg_failed || 'Konnte den Eigenbeleg nicht erstellen.'); }
        finally { this.egBusy = false; }
    },
    async _renderEigenbelegPdf() {
        const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas'), import('jspdf')]);
        await this.$nextTick();
        await new Promise((r) => setTimeout(r, 60));
        const node = document.getElementById('eigenbeleg-print');
        if (! node) return null;
        const canvas = await html2canvas(node, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false });
        const img = canvas.toDataURL('image/jpeg', 0.92);
        const pdf = new jsPDF({ unit: 'pt', format: 'a4' });
        const pw = pdf.internal.pageSize.getWidth();
        const ph = (canvas.height * pw) / canvas.width;
        const pageH = pdf.internal.pageSize.getHeight();
        let y = 0;
        pdf.addImage(img, 'JPEG', 0, 0, pw, ph);
        let remaining = ph - pageH;
        while (remaining > 0) { pdf.addPage(); y -= pageH; pdf.addImage(img, 'JPEG', 0, y, pw, ph); remaining -= pageH; }
        const blob = pdf.output('blob');
        return new Uint8Array(await blob.arrayBuffer());
    },
    _addDays(iso, days) { const d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate() + (days || 0)); return d.toISOString().slice(0, 10); },
    _defaultVat() { if (this.smallBusiness) return 0; const v = parseFloat(this.company.default_vat_rate); return Number.isFinite(v) ? v : 19; },

    // ---- CRUD ----
    async newInvoice() {
        const issue = this._today();
        const draft = {
            number: null,
            status: 'draft',
            issueDate: issue,
            dueDate: this._addDays(issue, parseInt(this.company.payment_terms_days, 10) || 14),
            currency: this.company.currency || 'EUR',
            lang: (document.documentElement.lang || 'de').slice(0, 2) === 'en' ? 'en' : 'de',
            customer: { name: '', attn: '', address: '', email: '', invoiceEmail: '', vatId: '', contactId: null, partnerId: null },
            lines: [{ desc: '', qty: 1, unit: '', unitPrice: 0, vatRate: this._defaultVat() }],
            note: '',
            footer: this.company.footer_text || '',
            imported: false,
            type: 'invoice',
            discountType: null, discountValue: 0,
            skontoPercent: 0, skontoDays: 0,
            versions: [],
        };
        const row = await this._create('invoices', this._toServerInvoice(draft), 'invoice', normInvoice);
        if (! row) return;
        this.invoices.unshift(row);
        this.open(row);
    },
    open(inv) {
        inv.lang ??= 'de';
        inv.currency ??= (this.company.currency || 'EUR');
        inv.type ??= 'invoice';
        inv.discountType ??= null;
        inv.discountValue ??= 0;
        inv.skontoPercent ??= 0;
        inv.skontoDays ??= 0;
        inv.customer ??= { name: '', attn: '', address: '', email: '', vatId: '', contactId: null };
        inv.customer.attn ??= '';
        inv.customer.partnerId ??= null;
        this.current = inv;
        this.dirty = false;
        this.editUnlocked = false;
        this._lockBaseline = this.isLocked(inv) ? JSON.stringify(this._editable(inv)) : null;
        if (inv.imported) { this.view = 'imported'; this._loadInvoicePdf(inv); }
        else this.view = 'edit';
        this._epcQr(inv).then((d) => { this.invoiceQr = d; });
    },

    // ---- Imported invoice: inline PDF + the six key fields ----
    invoicePdf: null, // { pages: [dataURL,...] }
    async _loadInvoicePdf(inv) {
        this._revokeInvoicePdf();
        if (! inv?.pdfPath) return;
        try {
            const res = await fetch(this._invoicePdfUrl(inv), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            const bytes = new Uint8Array(await res.arrayBuffer());
            const pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
            const doc = await pdfjs.getDocument({ data: bytes.slice(0), isEvalSupported: false }).promise;
            const pages = [];
            for (let i = 1; i <= doc.numPages; i++) {
                if (this.current !== inv) return;
                const page = await doc.getPage(i);
                const vp = page.getViewport({ scale: 2 });
                const canvas = document.createElement('canvas');
                canvas.width = vp.width; canvas.height = vp.height;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                pages.push(canvas.toDataURL('image/jpeg', 0.9));
            }
            if (this.current === inv) this.invoicePdf = { pages };
        } catch (e) { this.invoicePdf = null; }
    },
    _revokeInvoicePdf() { this.invoicePdf = null; },
    goToPartner(inv) {
        const id = inv?.customer?.partnerId; if (! id) return;
        const p = (this.partners || []).find((x) => x.id === id); if (! p) return;
        this._revokeInvoicePdf();
        this.view = 'list'; this.current = null;
        this.setSection('partners');
        this.openPartner(p);
    },
    _impLine() { const i = this.current; if (! i) return null; if (! i.lines?.length) i.lines = [{ desc: i.customer?.name || 'Rechnung', qty: 1, unit: '', unitPrice: 0, vatRate: 0 }]; return i.lines[0]; },
    get impGross() { return this._round2(this.computeTotals(this.current).gross); },
    set impGross(v) { const l = this._impLine(); if (! l) return; const rate = parseFloat(l.vatRate) || 0; l.qty = 1; l.unitPrice = this._round2((parseFloat(v) || 0) / (1 + rate / 100)); },
    get impRate() { const l = this.current?.lines?.[0]; return l ? (parseFloat(l.vatRate) || 0) : 0; },
    set impRate(v) { const gross = this.computeTotals(this.current).gross; const l = this._impLine(); if (! l) return; l.vatRate = parseFloat(v) || 0; l.qty = 1; l.unitPrice = this._round2(gross / (1 + l.vatRate / 100)); },
    async requestEdit() {
        if (! this.isLocked(this.current)) { this.editUnlocked = true; return; }
        const ok = await this.$store.confirm.ask(labels.edit_confirm || 'Edit this finalized invoice? Saving records a new version.');
        if (ok) this.editUnlocked = true;
    },
    backToList() {
        if (this.current && this.dirty && this._lockBaseline) {
            Object.assign(this.current, JSON.parse(this._lockBaseline));
        }
        this._revokeInvoicePdf();
        this.view = 'list'; this.current = null; this.dirty = false; this.editUnlocked = false; this._lockBaseline = null;
    },
    saveSoon() {
        const inv = this.current; if (! inv) return;
        inv.updated = new Date().toISOString();
        clearTimeout(this._saveTimer);
        const id = inv.id;
        this._saveTimer = setTimeout(() => { const cur = (this.invoices || []).find((i) => i.id === id); if (cur) this._persistInvoice(cur); }, 700);
    },

    isLocked(inv) { const i = inv || this.current; return !! i && (i.imported || i.status === 'sent' || i.status === 'paid'); },
    onFieldInput() { if (this.isLocked(this.current)) { if (this.editUnlocked) this.dirty = true; } else this.saveSoon(); },
    _editable(inv) {
        return {
            number: inv.number, status: inv.status, issueDate: inv.issueDate, dueDate: inv.dueDate,
            currency: inv.currency, lang: inv.lang, customer: inv.customer, lines: inv.lines,
            note: inv.note, footer: inv.footer,
            discountType: inv.discountType, discountValue: inv.discountValue,
            skontoPercent: inv.skontoPercent, skontoDays: inv.skontoDays,
        };
    },

    // Persist an edit to a locked invoice as a NEW version (label RECHNUNGSNR-NNN).
    async saveVersionedEdit() {
        const inv = this.current;
        if (! inv || ! this.dirty) return;
        const reason = await this.$store.confirm.prompt(
            labels.version_reason_title || 'Reason for change',
            { placeholder: labels.version_reason_ph || 'Why is this invoice being changed?' },
        );
        if (reason === null) return;
        if (! String(reason).trim()) { window.llToast?.(labels.version_reason_required || 'A reason is required.'); return; }
        this.pdfBusy = true;
        try {
            await this._commitVersion(inv, String(reason).trim());
            this.dirty = false;
            this.editUnlocked = false;
            this._lockBaseline = JSON.stringify(this._editable(inv));
            window.llToast?.((labels.version_saved || 'Version :label saved.').replace(':label', inv.versions[inv.versions.length - 1].label));
        } catch (e) { window.llToast?.(labels.version_failed || 'Could not save the version.'); }
        finally { this.pdfBusy = false; }
    },
    async _commitVersion(inv, reason) {
        const seq = (inv.versionSeq || 0) + 1;
        inv.versionSeq = seq;
        const label = `${inv.number || (labels.status_draft || 'ENTWURF')}-${String(seq).padStart(3, '0')}`;
        const snapshot = JSON.parse(JSON.stringify(this._editable(inv)));
        snapshot.totals = this.computeTotals(inv);
        const version = { seq, label, reason, at: new Date().toISOString(), snapshot };
        inv.versions = inv.versions || [];
        inv.versions.push(version);
        // Persist the field edits + the new version entry first so the server has the
        // entry, then — for online invoices — render THIS version's own PDF and attach
        // it to the entry server-side (imported invoices keep only the field snapshot;
        // their original uploaded PDF remains authoritative).
        await this._persistInvoice(inv);
        if (! inv.imported) { await this._renderAndUploadInvoicePdf(inv, label, seq); }
    },
    // Render the invoice's print sheet to a (raster) PDF client-side and upload it. With a
    // versionSeq it is stored on that versions[] entry (per-version PDF); otherwise as the
    // invoice's pdf_path. Lazy-loaded so the deps stay out of the bundle.
    async _renderAndUploadInvoicePdf(inv, label, versionSeq = null) {
        try {
            const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas'), import('jspdf')]);
            this.printQr = await this._epcQr(inv);
            this._printing = inv;
            await this.$nextTick();
            await new Promise((r) => setTimeout(r, 80));
            const node = document.getElementById('invoice-print');
            if (! node) { this._printing = null; return; }
            const canvas = await html2canvas(node, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false });
            this._printing = null;
            const img = canvas.toDataURL('image/jpeg', 0.92);
            const pdf = new jsPDF({ unit: 'pt', format: 'a4' });
            const pw = pdf.internal.pageSize.getWidth();
            const ph = (canvas.height * pw) / canvas.width;
            const pageH = pdf.internal.pageSize.getHeight();
            let y = 0;
            pdf.addImage(img, 'JPEG', 0, 0, pw, ph);
            let remaining = ph - pageH;
            while (remaining > 0) { pdf.addPage(); y -= pageH; pdf.addImage(img, 'JPEG', 0, y, pw, ph); remaining -= pageH; }
            const bytes = new Uint8Array(await pdf.output('blob').arrayBuffer());
            const row = await this._uploadInvoicePdf(inv.id, bytes, `${label || inv.number || 'invoice'}.pdf`, versionSeq);
            if (row) {
                if (Array.isArray(row.versions)) inv.versions = row.versions;
                inv.pdfPath = row.pdfPath ?? inv.pdfPath;
                if (row.version != null) inv.version = row.version;
            }
        } catch (e) { this._printing = null; }
    },
    // Upload a PDF for an invoice (multipart). With versionSeq the server attaches it to
    // that versions[] entry; otherwise it becomes the invoice's pdf_path. Returns the
    // normalized invoice or null.
    async _uploadInvoicePdf(id, bytes, name, versionSeq = null) {
        try {
            const fd = new FormData();
            fd.append('file', new File([bytes], name || 'invoice.pdf', { type: 'application/pdf' }));
            if (versionSeq != null) fd.append('version_seq', String(versionSeq));
            const res = await fetch(`/finance/invoices/${id}/pdf`, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }, body: fd });
            if (! res.ok) return null;
            const d = await res.json();
            return d.invoice ? normInvoice(d.invoice) : null;
        } catch (e) { return null; }
    },
    // Open a version's own stored PDF (each versioned edit keeps its own document).
    openVersionPdf(v) {
        const inv = this.current;
        if (! inv) return;
        if (v && v.pdf && v.seq != null) {
            this.closeReceiptPreview();
            this.receiptPreview = { url: `${this._invoicePdfUrl(inv)}?version_seq=${encodeURIComponent(v.seq)}`, mime: 'application/pdf', name: (v.label || inv.number || 'PDF') + '.pdf' };
            return;
        }
        // Fallback for legacy versions without their own PDF / imported originals.
        this.openOriginalPdf(inv);
    },

    addLine() { const rate = parseFloat(this.current?._defaultRate) || 0; this.current.lines.push({ desc: '', qty: 1, unit: '', unitPrice: rate, vatRate: this._defaultVat() }); this.saveSoon(); },
    removeLine(i) { this.current.lines.splice(i, 1); if (! this.current.lines.length) this.addLine(); else this.saveSoon(); },

    // ---- Clockify CSV import → prefill line items ----
    _parseCsv(text) {
        const rows = []; let row = [], field = '', inQ = false;
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            if (inQ) {
                if (ch === '"') { if (text[i + 1] === '"') { field += '"'; i++; } else inQ = false; }
                else field += ch;
            } else if (ch === '"') { inQ = true; }
            else if (ch === ',') { row.push(field); field = ''; }
            else if (ch === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
            else field += ch;
        }
        if (field.length || row.length) { row.push(field); rows.push(row); }
        return rows.filter((r) => r.some((c) => (c || '').trim() !== ''));
    },
    async importClockify(fileList) {
        const file = fileList && fileList[0];
        if (! file || ! this.current) return;
        try {
            const rows = this._parseCsv(await file.text());
            if (rows.length < 2) return;
            const head = rows[0].map((h) => h.trim().toLowerCase());
            const iDesc = head.indexOf('description');
            const iDur = head.indexOf('duration (decimal)');
            const iDate = head.indexOf('start date');
            if (iDesc < 0 || iDur < 0) { window.llToast?.(labels.csvBadFormat || 'CSV columns not found.'); return; }
            const unit = this.current.lang === 'en' ? 'h' : 'Std';
            const lines = [];
            for (let r = 1; r < rows.length; r++) {
                const desc = (rows[r][iDesc] || '').trim();
                const qty = parseFloat((rows[r][iDur] || '').replace(',', '.')) || 0;
                const date = iDate >= 0 ? (rows[r][iDate] || '').trim() : '';
                if (! desc && ! qty) continue;
                lines.push({ desc: date ? (date + '; ' + desc) : desc, qty, unit, unitPrice: 0, vatRate: this._defaultVat() });
            }
            if (! lines.length) { window.llToast?.(labels.csvBadFormat || 'No rows found.'); return; }
            const cur = this.current.lines;
            const onlyEmpty = cur.length === 1 && ! (cur[0].desc || '').trim() && ! cur[0].unitPrice;
            this.current.lines = onlyEmpty ? lines : [...cur, ...lines];
            this.saveSoon();
            window.llToast?.((labels.csvImported || ':n lines imported.').replace(':n', lines.length));
        } catch (e) { window.llToast?.(labels.csvBadFormat || 'Could not read CSV.'); }
    },

    // ---- Historical PDF invoice import (client-side) ----
    importReview: null, // { items:[draft], total, done, failed, running }

    async importPdfs(fileList) {
        const files = [...(fileList || [])].filter((f) => /\.pdf$/i.test(f.name));
        if (! files.length) return;
        this.importReview = { items: [], total: files.length, done: 0, failed: 0, running: true, idx: 0 };
        let pdfjs;
        try {
            pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
        } catch (e) { this.importReview = null; window.llToast?.(labels.importFailed || 'PDF engine failed to load.'); return; }
        const defaultVat = this._defaultVat();
        for (const file of files) {
            try {
                const bytes = new Uint8Array(await file.arrayBuffer());
                const doc = await pdfjs.getDocument({ data: bytes.slice(0), isEvalSupported: false }).promise;
                let text = '';
                for (let i = 1; i <= doc.numPages; i++) {
                    const page = await doc.getPage(i);
                    const content = await page.getTextContent();
                    let lastY = null, prev = null;
                    for (const it of content.items) {
                        const y = it.transform ? it.transform[5] : null;
                        if (prev && lastY !== null && y !== null && Math.abs(y - lastY) > 3) {
                            text += '\n';
                        } else if (prev && text && ! text.endsWith('\n')) {
                            const fs = it.height || Math.abs(it.transform[3]) || 10;
                            const gap = it.transform[4] - (prev.transform[4] + prev.width);
                            if (gap > fs * 0.28 || /\s$/.test(prev.str) || /^\s/.test(it.str)) text += ' ';
                        }
                        text += it.str;
                        lastY = y;
                        prev = it;
                    }
                    text += '\n';
                }
                const opts = { id: this._newId(), currency: this.company.currency || 'EUR', currentYear: new Date().getFullYear(), defaultVat };
                const sellerBlob = [this.company.name, this.company.address].filter(Boolean).join(' ');
                const draft = buildImportDraft(parseInvoiceFilename(file.name), parseInvoiceText(text, sellerBlob), opts);
                draft._file = file.name;
                draft._pdfBytes = bytes;
                draft._url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
                draft._dupe = !! (draft.number && this.activeInvoices.some((i) => String(i.number || '').trim() === String(draft.number).trim()));
                if (draft._dupe) draft.selected = false;
                this.importReview.items.push(draft);
            } catch (e) { this.importReview.failed++; }
            this.importReview.done++;
        }
        this.importReview.running = false;
        this.importReview.items.sort((a, b) => (a.issueDate || '').localeCompare(b.issueDate || ''));
        this.importReview.idx = 0;
    },

    // ---- Review stepper (one invoice at a time: PDF inline + the six fields) ----
    get importCurrent() { const r = this.importReview; return r && ! r.running && ! r.saving && r.items[r.idx] ? r.items[r.idx] : null; },
    importGoto(i) { const r = this.importReview; if (! r) return; r.idx = Math.max(0, Math.min(r.items.length - 1, i)); },
    importPrev() { this.importGoto((this.importReview?.idx || 0) - 1); },
    importNext() { this.importGoto((this.importReview?.idx || 0) + 1); },
    get partnerNames() { return (this.partners || []).map((p) => p.name).filter(Boolean).sort((a, b) => a.localeCompare(b)); },
    filteredPartnerNames(q) { const s = String(q || '').toLowerCase(); return this.partnerNames.filter((n) => ! s || n.toLowerCase().includes(s)).slice(0, 50); },
    filteredPartnerContacts(name, q) { const s = String(q || '').toLowerCase(); return this.partnerContactsFor(name).filter((c) => ! s || String(c.name || '').toLowerCase().includes(s)).slice(0, 50); },
    importVatChoices() { const s = new Set([19, 16, 7, 0]); const v = this.importCurrent?.vatRate; if (v != null) s.add(Number(v)); return [...s].sort((a, b) => b - a); },
    importNet(row) { const g = parseFloat(row?.gross) || 0; const r = parseFloat(row?.vatRate) || 0; return this._round2(g / (1 + r / 100)); },
    importVat(row) { const g = parseFloat(row?.gross) || 0; return this._round2(g - this.importNet(row)); },
    _closeImport() {
        for (const it of (this.importReview?.items || [])) { if (it._url) { try { URL.revokeObjectURL(it._url); } catch (e) { /* */ } } }
        this.importReview = null;
    },

    downloadZugferd(inv) {
        const i = inv || this.current;
        if (! i) return;
        const xml = buildZugferdXml(i, this.company, this.computeTotals(i));
        saveBlobAs(new Blob([xml], { type: 'application/xml' }), zugferdFilename(i));
    },

    get importSelectedCount() { return (this.importReview?.items || []).filter((i) => i.selected).length; },
    cancelImport() { this._closeImport(); },
    _round2(n) { return Math.round(((Number(n) || 0) + Number.EPSILON) * 100) / 100; },

    // Commit the reviewed drafts as invoice rows (create → upload original PDF).
    async confirmImport() {
        const picked = (this.importReview?.items || []).filter((i) => i.selected);
        if (! picked.length) { this._closeImport(); return; }
        await this._ensureContactsLoaded();
        this.importReview.saving = true;
        this.importReview.saved = 0;
        this.importReview.saveTotal = picked.length;
        let ok = 0;
        for (const draft of picked) {
            try {
                const rate = parseFloat(draft.vatRate) || 0;
                const gross = this._round2(parseFloat(draft.gross) || 0);
                const vat = this._round2(gross * rate / (100 + rate));
                const net = this._round2(gross - vat);
                const name = String(draft.recipient?.name || '').trim();
                let partnerId = null;
                let partnerInvoiceEmail = '';
                const attn = String(draft.contactPerson || '').trim();
                if (name) {
                    const partner = await this._findOrCreatePartner(name);
                    partnerId = partner ? partner.id : null;
                    partnerInvoiceEmail = (partner && partner.invoiceEmail) || '';
                    if (partner && partner.id) {
                        let dirty = false;
                        if (! partner.address && draft.recipient?.address) { partner.address = draft.recipient.address; dirty = true; }
                        if (! partner.vatId && draft.recipient?.vatId) { partner.vatId = draft.recipient.vatId; dirty = true; }
                        if (attn) { partner.contacts ||= []; if (! partner.contacts.some((c) => String(c.name || '').trim().toLowerCase() === attn.toLowerCase())) { partner.contacts.push({ id: this._newId(), name: attn, email: '', phone: '', role: '' }); dirty = true; } }
                        if (dirty) await this._persistPartner(partner);
                    }
                }
                const client = {
                    number: draft.number, status: 'paid',
                    issueDate: draft.issueDate || this._today(),
                    dueDate: draft.dueDate || draft.issueDate || this._today(),
                    currency: draft.currency || 'EUR', lang: 'de',
                    customer: { name, attn, address: draft.recipient?.address || '', email: '', invoiceEmail: partnerInvoiceEmail, vatId: draft.recipient?.vatId || '', contactId: null, partnerId },
                    lines: [{ desc: name || (labels.importSummaryLabel || 'Rechnung'), qty: 1, unit: '', unitPrice: net, vatRate: rate }],
                    gross, vatRate: rate,
                    note: '', footer: '', imported: true, versions: [],
                };
                const row = await this._create('invoices', this._toServerInvoice(client), 'invoice', normInvoice);
                if (! row) { this.importReview.saved++; continue; }
                if (draft._pdfBytes) {
                    const up = await this._uploadInvoicePdf(row.id, draft._pdfBytes, draft._file || (draft.number + '.pdf'));
                    if (up) row.pdfPath = up.pdfPath ?? row.pdfPath;
                }
                this.invoices.unshift(row);
                ok++;
            } catch (e) { /* skip this one, keep going */ }
            this.importReview.saved++;
        }
        window.llToast?.((labels.importDone || ':n invoices imported.').replace(':n', ok));
        this._closeImport();
    },

    async trash(inv) {
        if (! await this.$store.confirm.ask(labels.trashConfirm || 'Move this invoice to the trash?')) return;
        if (! await this._destroy('invoices', inv.id)) return;
        const i = this.invoices.indexOf(inv); if (i >= 0) this.invoices.splice(i, 1);
        if (this.current === inv) this.backToList();
        if (this.showInvTrash) this._loadInvTrash();
    },
    async restore(inv) {
        const num = String(inv.number || '').trim();
        if (num && this.activeInvoices.some((i) => i !== inv && String(i.number || '').trim() === num)) {
            window.llToast?.((labels.restore_dupe || 'An active invoice with number :n already exists.').replace(':n', num));
            return;
        }
        const r = await this._req('POST', `/finance/invoices/${inv.id}/restore`);
        if (r.ok && r.data.invoice) {
            const idx = this.invTrash.indexOf(inv); if (idx >= 0) this.invTrash.splice(idx, 1);
            this.invoices.unshift(normInvoice(r.data.invoice));
        }
    },
    async remove(inv) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm || 'Delete this invoice permanently?')) return;
        await this._destroy('invoices', inv.id + '/force');
        const i = this.invoices.indexOf(inv); if (i >= 0) this.invoices.splice(i, 1);
        if (this.current === inv) this.backToList();
    },

    // ---- Totals (net, VAT grouped by rate, gross) ----
    lineNet(l) { return (parseFloat(l.qty) || 0) * (parseFloat(l.unitPrice) || 0); },
    computeTotals(inv) {
        const t = { net: 0, vatByRate: {}, vat: 0, gross: 0 };
        if (! inv) return t;
        if (inv.imported && Number.isFinite(Number(inv.gross))) {
            const rate = parseFloat(inv.vatRate) || 0;
            t.gross = this._round2(Number(inv.gross));
            t.vat = this._round2(t.gross * rate / (100 + rate));
            t.net = this._round2(t.gross - t.vat);
            t.vatByRate[rate] = t.vat;
            return t;
        }
        // Raw net per rate, then apply the global discount proportionally (mirrors
        // finance-stats.js invoiceTotals — the discount reduces the net taxable base).
        const rawByRate = {}; let grossNet = 0;
        for (const l of inv.lines || []) {
            const net = this.lineNet(l);
            const rate = parseFloat(l.vatRate) || 0;
            grossNet += net;
            rawByRate[rate] = (rawByRate[rate] || 0) + net;
        }
        const discount = discountAmount(inv, grossNet);
        const factor = grossNet !== 0 ? (grossNet - discount) / grossNet : 1;
        for (const r of Object.keys(rawByRate)) {
            const netR = rawByRate[r] * factor;
            const v = netR * Number(r) / 100;
            t.vatByRate[r] = v;
            t.vat += v;
        }
        t.net = grossNet - discount;
        t.discount = discount;
        t.grossNet = grossNet;
        t.gross = t.net + t.vat;
        return t;
    },
    hasDiscount(inv) { const i = inv || this.current; return !! (i && i.discountType && Number(i.discountValue) > 0); },
    // Skonto "pay by" date = issue date + skonto days (for the printed early-payment note).
    skontoDate(inv) {
        const i = inv || this.current;
        if (! i || ! i.skontoDays || ! (Number(i.skontoPercent) > 0)) return '';
        try { return this._addDays(i.issueDate || this._today(), parseInt(i.skontoDays, 10) || 0); } catch (e) { return ''; }
    },
    isCreditNote(inv) { const i = inv || this.current; return !! i && i.type === 'credit_note'; },
    // Derived cancelled state: an ACTIVE credit note references this invoice.
    isCancelled(inv) { const i = inv || this.current; return !! i && (this.invoices || []).some((x) => x.cancelsInvoiceId === i.id); },
    fmtMoney(n, currency, lang) {
        const cur = currency || this.current?.currency || this.company.currency || 'EUR';
        const loc = (lang || this.current?.lang || 'de') === 'en' ? 'en' : 'de';
        try { return new Intl.NumberFormat(loc, { style: 'currency', currency: cur }).format(n || 0); }
        catch (e) { return (n || 0).toFixed(2) + ' ' + cur; }
    },
    pl(key) {
        const lang = this._printing?.lang || 'de';
        const set = this._labelsByLang[lang] || this._labelsByLang.de || {};
        return set[key] || key;
    },
    currencyOptions: ['EUR', 'USD', 'CHF'],
    get tpl() { const t = this.company.template || 'editorial'; return t === 'schlicht' ? 'elegant' : t; },
    // §19 KU invoices carry no VAT rows anywhere (editor totals + all print templates).
    vatRatesOf(inv) { if (this.smallBusiness) return []; return Object.keys(this.computeTotals(inv).vatByRate).map(Number).sort((a, b) => a - b); },
    fmtQty(n, lang) {
        const loc = (lang || this.current?.lang || 'de') === 'en' ? 'en' : 'de';
        try { return new Intl.NumberFormat(loc, { maximumFractionDigits: 2 }).format(parseFloat(n) || 0); }
        catch (e) { return String(n ?? ''); }
    },

    // ---- Customer picker (reads zero-knowledge contacts, if still available) ----
    customerPicker: false,
    custQuery: '',
    async openCustomerPicker() {
        this.customerPicker = true;
        this.custQuery = '';
    },
    closeCustomerPicker() { this.customerPicker = false; },
    _custName(c) { return (c && c.name) || contactDisplayName(c) || ''; },
    // The invoice customer is picked from the business partners (contacts were dropped in the
    // finance-only app). Picking a partner also applies its default hourly rate + currency.
    custSuggestions() {
        const q = this.custQuery.trim().toLowerCase();
        let list = (this.partners || []).filter((p) => ! p.trashed);
        if (q) list = list.filter((p) => (p.name || '').toLowerCase().includes(q) || (p.email || '').toLowerCase().includes(q) || (p.invoiceEmail || '').toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    },
    pickCustomer(p) {
        const attn = (Array.isArray(p.contacts) && p.contacts[0] && p.contacts[0].name) || '';
        this.current.customer = {
            name: p.name || '',
            attn,
            address: p.address || '',
            email: p.invoiceEmail || p.email || '',
            invoiceEmail: p.invoiceEmail || '',
            vatId: p.vatId || '',
            contactId: null,
            partnerId: p.id,
        };
        const rate = parseFloat(p.hourlyRate);
        if (! Number.isNaN(rate) && rate > 0) {
            this.current._defaultRate = rate;
            for (const l of (this.current.lines || [])) { if (! (parseFloat(l.unitPrice) > 0)) l.unitPrice = rate; }
        }
        if (p.currency) this.current.currency = p.currency;
        this.customerPicker = false;
        this.saveSoon();
    },

    // ---- Finalize / status ----
    _formatNumber(fmt, seq, issueDate) {
        const d = issueDate ? new Date(issueDate + 'T00:00:00') : new Date();
        const y = d.getFullYear();
        return (fmt || 'YYYY-NNNN')
            .replace(/YYYY/g, String(y))
            .replace(/YY/g, String(y).slice(-2))
            .replace(/MM/g, String(d.getMonth() + 1).padStart(2, '0'))
            .replace(/DD/g, String(d.getDate()).padStart(2, '0'))
            .replace(/N+/g, (m) => String(seq).padStart(m.length, '0'));
    },
    // GoBD duplicate guard applies to the app's OWN issued series only.
    get duplicateNumbers() { return dupNumbers(this.activeInvoices.filter((i) => ! i.imported)); },
    isInvoiceLinked(inv) {
        if (! inv) return false;
        if (inv.paymentTxId) return true;
        const num = String(inv.number || '').trim();
        return (this.transactions || []).some((t) => t.invoiceId === inv.id || (num && String(t.invoiceNumber || '').trim() === num));
    },
    get gapNumbers() { return gapNumbers(this.activeInvoices).slice(0, 40); },

    // ---- Numbering cycle (per year) ----
    get currentYear() { return String(new Date().getFullYear()); },
    get currentYearInvoices() { return invoicesInYear(this.invoices, this.currentYear); },
    get numberingLocked() { return this.currentYearInvoices.length > 0; },
    get nextNumberPreview() {
        const floor = parseInt(this.company.next_number, 10) || 1;
        const seq = nextSeqForYear(this.invoices, this.currentYear, floor);
        return this._formatNumber(this.company.number_format, seq, this._today());
    },
    // Reset the current year's invoice cycle: DELETE every invoice dated this year.
    async resetYearCycle() {
        const year = this.currentYear;
        const doomed = (this.invoices || []).filter((iv) => invoiceYear(iv) === year);
        if (! doomed.length) { window.llToast?.(labels.cycle_none || 'No invoices for the current year.'); return; }
        const typed = await this.$store.confirm.prompt(
            (labels.cycle_reset_warn || 'This deletes all :n invoices dated :year. Type :year to confirm.')
                .replace(/:n/g, doomed.length).replace(/:year/g, year),
            { placeholder: year, ok: labels.cycle_reset_ok || 'Delete & reset' },
        );
        if (String(typed || '').trim() !== year) return;
        for (const inv of doomed) {
            await this._destroy('invoices', inv.id + '/force');
            const i = this.invoices.indexOf(inv); if (i >= 0) this.invoices.splice(i, 1);
        }
        if (this.current && invoiceYear(this.current) === year) this.backToList();
        window.llToast?.((labels.cycle_reset_done || 'Cycle :year reset.').replace(':year', year));
    },

    // Open a stored invoice PDF (imported original / generated) in the in-app preview modal.
    openInvoicePdfPreview(inv) {
        if (! inv || ! inv.pdfPath) { window.llToast?.(labels.downloadFailed || 'Could not open file.'); return; }
        this.closeReceiptPreview();
        this.receiptPreview = { url: this._invoicePdfUrl(inv), mime: 'application/pdf', name: (inv.number || 'PDF') + '.pdf' };
    },
    openOriginalPdf(inv) { return this.openInvoicePdfPreview(inv); },

    // ---- Email the invoice PDF to the customer ----
    // Enabled once the invoice is finalized (numbered / sent / paid), has a stored
    // PDF, and the customer has an email. The server re-checks all three.
    canEmail(inv) {
        const i = inv || this.current;
        if (! i) return false;
        const finalized = !! (i.imported || i.status === 'sent' || i.status === 'paid' || i.number);
        return finalized && !! i.pdfPath && !! (i.customer && i.customer.email);
    },
    async emailInvoice(inv) {
        const i = inv || this.current;
        if (! i) return;
        if (! i.pdfPath) { window.llToast?.(labels.email_no_pdf || 'No PDF to send.'); return; }
        const prefill = (i.customer && i.customer.email) || '';
        const to = await this.$store.confirm.prompt(labels.email_to || 'Recipient email', { value: prefill, placeholder: prefill });
        if (to === null) return;
        const addr = String(to).trim() || prefill;
        if (! addr) { window.llToast?.(labels.email_no_recipient || 'No recipient email.'); return; }
        try {
            const r = await this._req('POST', `/finance/invoices/${i.id}/email`, { to: addr });
            if (r.ok && r.data.ok) {
                i.sentAt = r.data.sent_at || new Date().toISOString();
                window.llToast?.(labels.email_sent || 'Invoice emailed.');
                return;
            }
            const code = r.data && r.data.error;
            const msg = code === 'no_pdf' ? labels.email_no_pdf
                : code === 'no_recipient' ? labels.email_no_recipient
                    : code === 'no_smtp' ? labels.email_no_smtp
                        : labels.email_failed;
            window.llToast?.(msg || labels.email_failed || 'Could not send the email.');
        } catch (e) { window.llToast?.(labels.email_failed || 'Could not send the email.'); }
    },
    // Cancel a finalized invoice with a credit note (Storno / Gutschrift). Server-created
    // (negated lines + own GoBD number); the original is never touched. Opens the credit note.
    canStorno(inv) {
        const i = inv || this.current;
        if (! i || i.type === 'credit_note') return false;
        const finalized = !! (i.status === 'sent' || i.status === 'paid' || i.number);
        return finalized && ! this.isCancelled(i);
    },
    async storno(inv) {
        const i = inv || this.current;
        if (! i || ! this.canStorno(i)) return;
        if (! await this.$store.confirm.ask(labels.storno_confirm || 'Cancel this invoice with a credit note?')) return;
        try {
            const r = await this._req('POST', `/finance/invoices/${i.id}/storno`);
            if (r.ok && r.data.invoice) {
                const credit = normInvoice(r.data.invoice);
                this.invoices.unshift(credit);
                window.llToast?.((labels.storno_created || 'Credit note :n created.').replace(':n', credit.number || ''));
                this.open(credit);
                return;
            }
            const code = r.data && r.data.error;
            const msg = code === 'not_finalized' ? labels.storno_not_finalized
                : code === 'already_cancelled' ? labels.storno_already
                    : code === 'already_credit_note' ? labels.storno_is_credit
                        : labels.storno_failed;
            window.llToast?.(msg || labels.storno_failed || 'Could not create the credit note.');
        } catch (e) { window.llToast?.(labels.storno_failed || 'Could not create the credit note.'); }
    },
    // A payment reminder (Mahnung) is available for overdue invoices with a PDF + recipient email.
    canDun(inv) {
        const i = inv || this.current;
        return this.isOverdue(i) && !! i.pdfPath && !! (i.customer && i.customer.email);
    },
    async dun(inv) {
        const i = inv || this.current;
        if (! i) return;
        if (! i.pdfPath) { window.llToast?.(labels.email_no_pdf || 'No PDF to send.'); return; }
        const prefill = (i.customer && i.customer.email) || '';
        const to = await this.$store.confirm.prompt(labels.email_to || 'Recipient email', { value: prefill, placeholder: prefill });
        if (to === null) return;
        const addr = String(to).trim() || prefill;
        if (! addr) { window.llToast?.(labels.email_no_recipient || 'No recipient email.'); return; }
        try {
            const r = await this._req('POST', `/finance/invoices/${i.id}/dun`, { to: addr });
            if (r.ok && r.data.ok) {
                i.remindedAt = r.data.reminded_at || new Date().toISOString();
                i.reminderCount = r.data.level || ((i.reminderCount || 0) + 1);
                window.llToast?.((labels.dun_sent || 'Reminder :n sent.').replace(':n', String(i.reminderCount)));
                return;
            }
            const code = r.data && r.data.error;
            const msg = code === 'not_overdue' ? labels.dun_not_overdue
                : code === 'no_pdf' ? labels.email_no_pdf
                    : code === 'no_recipient' ? labels.email_no_recipient
                        : code === 'no_smtp' ? labels.email_no_smtp
                            : labels.dun_failed;
            window.llToast?.(msg || labels.dun_failed || 'Could not send the reminder.');
        } catch (e) { window.llToast?.(labels.dun_failed || 'Could not send the reminder.'); }
    },
    async finalize(inv) {
        let i = inv || this.current;
        if (! i) return;
        if (! i.number) {
            const r = await this._req('POST', `/finance/invoices/${i.id}/finalize`);
            if (r.ok && r.data.invoice) {
                const row = normInvoice(r.data.invoice);
                Object.assign(i, { number: row.number, seq: row.seq, year: row.year, status: row.status, version: row.version });
            }
        }
        if (i.status === 'draft') i.status = 'sent';
        i.totals = this.computeTotals(i);
        await this._lockCommit(i, labels.version_finalized || 'Finalized');
    },
    // Manual status override (e.g. "sent by other means" / "paid" without the finalize
    // or email flow). GoBD: forward to a numbered status assigns the number first (server
    // finalize); a numbered invoice can NEVER go back to draft (client + server guard).
    async setStatus(inv, status) {
        if (! inv || inv.imported || inv.status === status) return;
        if (status === 'draft' && inv.number) { window.llToast?.(labels.status_draft_blocked || labels.statusFinal); return; }
        let i = inv;
        if (status !== 'draft' && ! i.number) {
            const r = await this._req('POST', `/finance/invoices/${i.id}/finalize`);
            if (r.ok && r.data.invoice) {
                const row = normInvoice(r.data.invoice);
                Object.assign(i, { number: row.number, seq: row.seq, year: row.year, status: row.status, version: row.version });
            }
        }
        i.status = status;
        if (status === 'paid') { if (! i.paidAt) i.paidAt = this._today(); }
        else { i.paidAt = null; }
        await this._lockCommit(i, (labels.version_status || 'Status') + ': ' + this.statusLabel(status));
    },
    async _lockCommit(inv, reason) {
        this.pdfBusy = true;
        // _commitVersion persists the field state + version entry itself; if it throws
        // (e.g. mid-render), still make sure the invoice is saved.
        try { await this._commitVersion(inv, reason); } catch (e) { try { await this._persistInvoice(inv); } catch (_e) { /* ignore */ } }
        this.pdfBusy = false;
        if (this.current && this.current.id === inv.id) { this.dirty = false; this._lockBaseline = JSON.stringify(this._editable(inv)); }
    },
    statusLabel(s) { return ({ draft: labels.statusDraft, final: labels.statusFinal, sent: labels.statusSent, paid: labels.statusPaid })[s] || s; },

    // ---- Print / PDF (client-side) ----
    async printInvoice(inv) {
        const i = inv || this.current;
        if (i?.imported && i?.pdfPath) { this.openOriginalPdf(i); return; }
        this.printQr = await this._epcQr(i);
        this._printing = i;
        this.$nextTick(() => { window.print(); });
    },

    // ---- Payment QR (EPC069-12 / GiroCode): SEPA credit transfer to the company IBAN,
    // amount = gross, remittance = invoice number. EUR/SEPA only (canEpcQr gates). Client-side.
    printQr: '',   // QR for the sheet currently being rendered (#invoice-print)
    invoiceQr: '', // QR for the editor preview
    async _epcQr(inv) {
        if (! inv) return '';
        const payload = buildEpcPayload({
            name: this.company.name || '',
            iban: this.company.iban || '',
            bic: this.company.bic || '',
            amount: this.computeTotals(inv).gross,
            currency: inv.currency || 'EUR',
            reference: inv.number ? (inv.lang === 'en' ? 'Invoice ' : 'Rechnung ') + inv.number : '',
        });
        if (! payload) return '';
        try { const mod = await import('qrcode'); const QR = mod.default ?? mod; return await QR.toDataURL(payload, { margin: 0, width: 260 }); } catch (e) { return ''; }
    },
});
