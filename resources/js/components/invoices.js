// invoices component. Extracted from app.js.
import { zkModule, bootStore } from '../shared/zk-module';
import { nextSeqForYear, duplicateNumbers as dupNumbers, missingNumbers as gapNumbers, invoicesInYear, invoiceYear } from '../shared/invoice-numbering';
import { parseInvoiceFilename, parseInvoiceText, buildImportDraft } from '../shared/invoice-pdf-import';
import { contactDisplayName } from '../shared/contact-utils';
import { jsonHeaders, postForm } from '../shared/api';
import { saveBlobAs, formatDate } from '../shared/dom';
import { buildZugferdXml, zugferdFilename } from '../shared/zugferd';
import { buildEpcPayload } from '../shared/epc-qr';
import { padBlob } from '../shared/padme';
import { fetchBlobBuffer } from '../shared/blob-io';
import { fileSig } from '../shared/file-sig';
import { autoPick, suggestBookings } from '../shared/receipt-match';
import { projectTree as buildProjectTree, rolledTotal as projectRolled, ownTotal as projectOwn, projectReceipts as receiptsForProject } from '../shared/finance-projects';
import { vatReturn, revenueByCustomer, monthlyRevenue, yearKpis, activeYears, accountVatSummary, euerReport } from '../shared/finance-stats';
import { matchInvoice } from '../shared/invoice-match';
import { extractDocText } from '../shared/doc-text';
import { analyzeReceiptText } from '../shared/receipt-ocr';
import { normMerchant, matchPartner, learnedCategoryFor } from '../shared/merchant-learn';
import { buildReceiptName } from '../shared/receipt-name';
import { amountMatches } from '../shared/amount-search';
import { FINANCE_ICON_NAMES, FINANCE_COLORS, DEFAULT_CAT_COLOR, DEFAULT_CAT_ICON, catIconPath as _catIconPath } from '../shared/finance-icons';
import { PAYMENT_TYPES, paymentTint, paymentSubtitle, isValidPaymentMethod, sortedPaymentMethods, blankPaymentMethod, cardNetworkOf } from '../shared/payment-methods';
import { detectFormat, parseMt940, parseCsv, detectCsvMapping, applyCsvMapping, enrichExisting, classifyTxType, guessVatCat, VAT_CATS, txSignature as txSig, TX_FIELDS, TX_REQUIRED } from '../shared/bank-statement';

// One-time dual-read migration from the old single-blob module store (/store/invoices)
// to the sharded store (LLInvoicesStore, spec §3b). Runs only while the sharded store is
// empty; after moving the invoices it clears the old monolith so a later wipe can't
// re-import them. The old scalar `invoiceSeq` (last issued sequence) is preserved by
// anchoring it onto the most recent numbered invoice — legacy invoices may lack a
// per-invoice `seq`, and without this the next number could duplicate a legacy one
// (GoBD). Best-effort: a failure just leaves the invoices in the old store.
async function migrateInvoicesFromMonolith(ms) {
    if ((ms.data.invoices?.length ?? 0) > 0) return; // already sharded
    let d = null;
    try {
        d = await fetch('/store/invoices', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
    } catch (e) { return; }
    if (! d || ! d.ciphertext) return;
    let old = null;
    try { old = window.Vault.openManifest(d.ciphertext); } catch (e) { return; }
    const list = Array.isArray(old.invoices) ? old.invoices : [];
    const seqFloor = Number.isFinite(old.invoiceSeq) ? old.invoiceSeq : 0;
    if (list.length === 0 && seqFloor <= 0) return;
    if (seqFloor > 0) {
        const numbered = list.filter((i) => i.number);
        const covered = numbered.some((i) => Number.isFinite(i.seq) && i.seq >= seqFloor);
        if (! covered && numbered.length) {
            const anchor = numbered.reduce((a, b) => ((b.issueDate || '') > (a.issueDate || '') ? b : a));
            anchor.seq = Math.max(Number.isFinite(anchor.seq) ? anchor.seq : 0, seqFloor);
        }
    }
    ms.data.invoices.push(...list);
    await ms.flush(); // persist into the sharded store first
    try {
        const empty = window.Vault.sealManifest({ v: 3, invoices: [], invoiceSeq: 0 });
        await fetch('/store/invoices', { method: 'PUT', headers: jsonHeaders(), body: JSON.stringify({ ciphertext: empty, version: d.version ?? 0 }) });
    } catch (e) { /* the length guard still prevents re-import this session */ }
}

export default (config = {}, labels = {}) => ({
    ...zkModule({ store: 'invoices', instance: () => window.LLInvoicesStore, afterLoad: (self, ms) => { migrateInvoicesFromMonolith(ms); self._migratePartnerContacts(); self._migrateCategoryIds(); self._seedCategoryStyles(); self._migrateReceiptCategories(); ms._afterRebase = () => self._reresolveOpenRefs(); }, map: { invoices: 'invoices', paymentMethods: 'paymentMethods', transactions: 'transactions', partners: 'partners', financeCategories: 'financeCategories', projects: 'projects' }, onLock: (self) => { self._revokeInvoicePdf?.(); self.view = 'list'; self.current = null; self.payEditing = null; self.payView = 'list'; self.payAccount = null; self.stmt = null; self.openProjectId = null; self.projectEditing = null; self.expenseEditing = null; self.receiptPicker = false; self.partnersView = 'list'; self.openPartnerId = null; self.partnerEditMode = false; self.eigenbeleg = null; self._egTx = null; self.showInvTrash = false; self.showReceiptTrash = false; self.catEditing = null; self.customerPicker = false; } }),

    company: config.company || {},
    _labelsByLang: config.labelsByLang || {},
    invoices: [],
    view: 'list',        // 'list' | 'edit'
    current: null,       // the invoice being edited
    filterStatus: '',    // '' | draft | sent | paid
    _printing: null,     // invoice rendered into the hidden print sheet
    dirty: false,        // a LOCKED invoice has unsaved edits (drafts autosave; locked don't)
    pdfBusy: false,      // a version PDF is being rendered
    editUnlocked: false, // locked invoice: fields stay disabled until "Bearbeiten" + confirm
    _lockBaseline: null, // JSON of a locked invoice as opened, to revert unsaved edits on leave
    // Finance section: the page is a "Finanzen" hub with tabs. Invoices are one tab.
    section: 'dashboard', // 'dashboard' | 'receipts' | 'invoices' | 'payments' | 'projects' | 'stats' | 'settings'

    // Global business/private scope, applied consistently across every finance tab.
    // 'private' means: a transaction booked as a private draw/deposit (vatCat 'private'),
    // a non-business payment method, a private project, or a receipt on any of those.
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
        // Deep link: #section or #section/<id> (open a specific invoice/receipt/account/
        // project/partner directly). Parse the section synchronously; the entity is opened
        // after the sealed store has loaded (below).
        const [sec, deepId] = (location.hash || '').replace('#', '').split('/');
        if (['dashboard', 'receipts', 'invoices', 'payments', 'projects', 'partners', 'stats', 'settings'].includes(sec)) this.section = sec;
        // Keep the URL in sync with any detail that is open, across every tab, so a reload or
        // shared link lands on the exact target.
        for (const prop of ['section', 'current', 'receiptDoc', 'payAccount', 'payView', 'openProjectId', 'openPartnerId', 'partnersView']) {
            this.$watch(prop, () => this._writeHash());
        }
        await this._initZk();
        if (this.state === 'ready') { this._ensureReceiptIds(); this.reconcileBlobs(); this._restoreDeepLink(sec, deepId); }
    },

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
    // Open the entity named by a deep link once the data is loaded.
    _restoreDeepLink(sec, id) {
        if (! id) return;
        if (sec === 'invoices') { const inv = (this.invoices || []).find((i) => i.id === id); if (inv) this.open(inv); }
        else if (sec === 'receipts') { const d = this.allReceipts.find((x) => x.r && x.r.id === id); if (d) this.openReceiptDoc(d); }
        else if (sec === 'payments') { const pm = (this.paymentMethods || []).find((p) => p.id === id); if (pm) this.openAccount(pm); }
        else if (sec === 'projects') { if ((this.projects || []).some((p) => p.id === id)) this.openProjectDetail(id); }
        else if (sec === 'partners') { const p = (this.partners || []).find((x) => x.id === id); if (p) this.openPartner(p); }
    },

    setSection(s) {
        this.section = s; // the $watch above writes the hash
        try { window.scrollTo({ top: 0 }); } catch (e) { /* ignore */ }
    },

    // ---- Finance dashboard: income at a glance (expenses follow with receipts) ----
    get financeStats() {
        const year = new Date().getFullYear();
        let paidYear = 0, outstandingYear = 0, countYear = 0, paidAll = 0;
        for (const inv of (this.invoices || [])) {
            if (inv.trashed) continue;
            const g = this.computeTotals(inv).gross || 0;
            const y = parseInt((inv.issueDate || '').slice(0, 4), 10);
            if (inv.status === 'paid') { paidAll += g; if (y === year) paidYear += g; }
            if (y === year) { countYear++; if (inv.status === 'sent') outstandingYear += g; }
        }
        return { year, paidYear, outstandingYear, countYear, paidAll };
    },

    // ---- VAT advance return (Umsatzsteuer-Voranmeldung), current year ----
    get vatReturn() { return vatReturn(this.invoices, new Date().getFullYear()); },

    // ---- Statistics tab (year-scoped; the year is selectable) ----
    statsYear: new Date().getFullYear(),
    get statsYears() { const ys = activeYears(this.invoices); return ys.length ? ys : [new Date().getFullYear()]; },
    get statsKpis() { return yearKpis(this.invoices, this.statsYear); },
    get statsCustomers() { return revenueByCustomer(this.invoices, this.statsYear); },
    get statsMonths() { return monthlyRevenue(this.invoices, this.statsYear); },
    get statsVat() { return vatReturn(this.invoices, this.statsYear); },
    get euer() { return euerReport(this.invoices, this.transactions, this.projects, this.statsYear); },
    monthShort(m) { try { return new Date(2000, m - 1, 1).toLocaleDateString(document.documentElement.lang || 'de', { month: 'short' }); } catch (e) { return String(m); } },
    // Largest monthly net in the selected year — scales the bar chart.
    get statsMonthPeak() { return Math.max(1, ...this.statsMonths.map((m) => m.net)); },
    monthLabel(m) {
        const loc = document.documentElement.lang || 'de';
        try { return new Intl.DateTimeFormat(loc, { month: 'short' }).format(new Date(2000, (m || 1) - 1, 1)); }
        catch (e) { return String(m); }
    },

    // ---- Payment methods (bank accounts, cards, …) — sealed in the finance store ----
    paymentMethods: [],
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
    // The required fields still missing (for inline highlighting). Bank needs an IBAN or
    // account number; card a number; PayPal an email; every type a label.
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
    // Autofill the card network from the typed number (user can still override).
    payCardInput() { if (this.payEditing?.type === 'card') this.payEditing.cardNetwork = cardNetworkOf(this.payEditing.cardNumber); },
    savePayment() {
        const pm = this.payEditing;
        if (! isValidPaymentMethod(pm)) { this.paySaveAttempted = true; window.llToast?.(labels.pay_invalid || 'Please fill in the required fields.'); return; }
        pm.updated = new Date().toISOString();
        let target = pm;
        if (this.payIsNew) {
            pm.id = window.LLInvoicesStore.newId();
            pm.created = pm.updated;
            this.paymentMethods.push(pm);
        } else {
            const i = this.paymentMethods.findIndex((p) => p.id === pm.id);
            if (i >= 0) { Object.assign(this.paymentMethods[i], pm); target = this.paymentMethods[i]; }
        }
        this._save();
        // Try to fetch the bank's favicon/logo (server-proxied, SSRF-guarded) — best effort.
        if (target.type === 'bank' && target.url) this._fetchBankIcon(target);
        this.payEditing = null;
    },
    // Reuse the SSRF-guarded favicon endpoint; store the returned data URI on the account.
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
            if (icon && pm.icon !== icon) { pm.icon = icon; this._save(); }
        } catch (e) { /* best effort */ }
    },
    // A usable <img> src for a stored bank logo (only data:/http(s) URIs).
    payIconSrc(pm) { const v = pm && pm.icon; return (typeof v === 'string' && /^(data:|https?:)/.test(v)) ? v : ''; },
    async removePayment(pm) {
        if (! await this.$store.confirm.ask(labels.pay_delete_confirm || 'Delete this payment method?')) return;
        const i = this.paymentMethods.indexOf(pm);
        if (i >= 0) this.paymentMethods.splice(i, 1);
        // Also drop that account's imported transactions.
        for (let j = this.transactions.length - 1; j >= 0; j--) if (this.transactions[j].account === pm.id) this.transactions.splice(j, 1);
        this._save();
        if (this.payEditing && this.payEditing.id === pm.id) this.payEditing = null;
        if (this.payAccount && this.payAccount.id === pm.id) this.backToPayments();
    },
    // Mark one account as the business account (Geschäftskonto) — where invoice payments
    // and receipts are reconciled. Exactly one at a time.
    toggleBusiness(pm) {
        const on = ! pm.business;
        for (const p of this.paymentMethods) p.business = false;
        pm.business = on;
        this._save();
    },
    get businessAccount() { return (this.paymentMethods || []).find((p) => p.business) || null; },

    // ---- Account detail + bank-statement import (sealed transactions) ----
    transactions: [],
    payView: 'list',        // 'list' | 'account'
    payAccount: null,       // the payment method whose statement is open
    stmt: null,             // import wizard state
    openAccount(pm) {
        this.payAccount = pm; this.payView = 'account'; this.txPage = 1;
        this.resetTxFilters();
        this.txYear = new Date().getFullYear(); // default to the current year
        this.rematchAll(true); // auto-link payments to invoices on open (silent)
        try { window.scrollTo({ top: 0 }); } catch (e) { /* */ }
    },
    backToPayments() { this.payView = 'list'; this.payAccount = null; },
    // The open account's statement year (defaults to the current year on open).
    txYear: new Date().getFullYear(),
    setTxYear(y) { this.txYear = y; this.txPage = 1; },
    // Years that have transactions for the open account (+ current year), newest first.
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
    // Transactions of the open account for the selected year, newest first.
    // Account transactions before the row filters (account + year + global scope) — the base
    // both the filtered list and the filter-option dropdowns derive from.
    _accountBase() {
        const id = this.payAccount?.id;
        const yr = this.txYear ? String(this.txYear) : null;
        return (this.transactions || []).filter((t) => t.account === id && this._scopeMatch(this._txPrivate(t)) && (! yr || String(t.date || '').startsWith(yr)));
    },
    // Row filters for the account view.
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
    // Payment methods for the Zahlungsmittel tab, filtered by the global scope.
    get scopedPayments() { return this.sortedPayments.filter((pm) => this._scopeMatch(this._pmPrivate(pm))); },
    // ---- Pagination (shared page-size options across all finance tables) ----
    perPageOptions: [5, 10, 15, 20, 25, 50, 100],
    // Account transactions
    txPage: 1,
    txPerPage: 25,
    get txPageCount() { return Math.max(1, Math.ceil(this.accountTx.length / this.txPerPage)); },
    get pagedAccountTx() { const s = (this.txPage - 1) * this.txPerPage; return this.accountTx.slice(s, s + this.txPerPage); },
    setTxPerPage(n) { this.txPerPage = n; this.txPage = 1; },
    txGoto(p) { this.txPage = Math.min(this.txPageCount, Math.max(1, p)); },
    // Invoices list
    invPage: 1,
    invPerPage: 25,
    get invPageCount() { return Math.max(1, Math.ceil(this.filtered.length / this.invPerPage)); },
    get pagedInvoices() { const s = (this.invPage - 1) * this.invPerPage; return this.filtered.slice(s, s + this.invPerPage); },
    setInvPerPage(n) { this.invPerPage = n; this.invPage = 1; },
    invGoto(p) { this.invPage = Math.min(this.invPageCount, Math.max(1, p)); },
    // Receipts list
    recPage: 1,
    recPerPage: 25,
    get recPageCount() { return Math.max(1, Math.ceil(this.filteredReceipts.length / this.recPerPage)); },
    get pagedReceipts() { const s = (this.recPage - 1) * this.recPerPage; return this.filteredReceipts.slice(s, s + this.recPerPage); },
    setRecPerPage(n) { this.recPerPage = n; this.recPage = 1; },
    recGoto(p) { this.recPage = Math.min(this.recPageCount, Math.max(1, p)); },
    // Settings tab: business partners.
    parPage: 1,
    parPerPage: 10,
    get parPageCount() { return Math.max(1, Math.ceil(this.filteredPartners.length / this.parPerPage)); },
    get pagedPartners() { const s = (this.parPage - 1) * this.parPerPage; return this.filteredPartners.slice(s, s + this.parPerPage); },
    setParPerPage(n) { this.parPerPage = n; this.parPage = 1; },
    parGoto(p) { this.parPage = Math.min(this.parPageCount, Math.max(1, p)); },
    // Categories tab: ALL categories (builtin defaults + custom) live in
    // financeCategories as {id,name,color,icon,builtin} and paginate together.
    catPage: 1,
    catPerPage: 10,
    get catPageCount() { return Math.max(1, Math.ceil((this.financeCategories || []).length / this.catPerPage)); },
    get pagedCategories() { const s = (this.catPage - 1) * this.catPerPage; return this.sortedFinanceCategories.slice(s, s + this.catPerPage); },
    setCatPerPage(n) { this.catPerPage = n; this.catPage = 1; },
    catGoto(p) { this.catPage = Math.min(this.catPageCount, Math.max(1, p)); },
    // Category color + icon editor
    catEditing: null,       // the category object being edited (null = closed)
    catIconGrid: false,     // show the icon picker inside the editor
    catIconQuery: '',
    financeIconNames: FINANCE_ICON_NAMES,
    financeColors: FINANCE_COLORS,
    catIconPath(name) { return _catIconPath(name); },
    filteredCatIcons() { const q = this.catIconQuery.trim().toLowerCase(); return q ? this.financeIconNames.filter((n) => n.includes(q)) : this.financeIconNames; },
    catStyle(name) {
        const c = (this.financeCategories || []).find((x) => String(x.name || '').toLowerCase() === String(name || '').toLowerCase());
        return { color: c?.color || DEFAULT_CAT_COLOR, icon: c?.icon || DEFAULT_CAT_ICON };
    },
    catColor(name) { return this.catStyle(name).color; },
    catIcon(name) { return this.catStyle(name).icon; },
    // Autocomplete source: all category names filtered by the typed query (cap 50).
    catFilter(q) {
        const s = String(q || '').trim().toLowerCase();
        const all = this.allCategories;
        return (s ? all.filter((n) => n.toLowerCase().includes(s)) : all).slice(0, 50);
    },
    // A receipt can carry MULTIPLE categories. Read them tolerantly (legacy single
    // `category` string counts as a one-element list) and mutate the array in place.
    catList(r) { return Array.isArray(r?.categories) ? r.categories : (r?.category ? [r.category] : []); },
    addReceiptCat(r, name, commit) {
        const n = String(name || '').trim(); if (! r || ! n) return;
        if (! Array.isArray(r.categories)) r.categories = r.category ? [r.category] : [];
        if (! r.categories.some((c) => String(c).toLowerCase() === n.toLowerCase())) r.categories.push(n);
        delete r.category; // migrated to the array — never keep both
        if (commit) commit();
    },
    removeReceiptCat(r, name, commit) {
        if (! r || ! Array.isArray(r.categories)) return;
        const i = r.categories.findIndex((c) => c === name);
        if (i >= 0) r.categories.splice(i, 1);
        if (commit) commit();
    },
    editCategory(c) { this.catEditing = c; this.catIconGrid = false; this.catIconQuery = ''; },
    closeCatEditor() { this.catEditing = null; this.catIconGrid = false; },
    pickCatColor(hex) { if (this.catEditing) { this.catEditing.color = hex; this._save(); } },
    pickCatIcon(name) { if (this.catEditing) { this.catEditing.icon = name; this.catIconGrid = false; this._save(); } },
    renameCategory(c, name) { const n = String(name || '').trim(); if (! n || c.builtin) return; c.name = n; this._save(); },
    // One-time: lift every receipt's legacy single `category` string into the
    // `categories[]` array (multi-category). Idempotent; runs once on load.
    _migrateReceiptCategories() {
        let changed = false;
        for (const tx of (this.transactions || [])) {
            for (const r of (tx.receipts || [])) {
                if (! Array.isArray(r.categories)) {
                    r.categories = r.category ? [r.category] : [];
                    delete r.category;
                    changed = true;
                }
            }
        }
        if (changed) this._save();
    },
    // Seed a color/icon on every category and ensure a row exists for each builtin
    // default (so builtins are editable + paginate with the rest). Idempotent.
    _seedCategoryStyles() {
        this.financeCategories ||= [];
        let changed = false;
        for (const c of this.financeCategories) {
            if (! c.id) { c.id = window.LLInvoicesStore.newId(); changed = true; }
            if (! c.color) { c.color = DEFAULT_CAT_COLOR; changed = true; }
            if (! c.icon) { c.icon = DEFAULT_CAT_ICON; changed = true; }
        }
        const have = new Set(this.financeCategories.map((c) => String(c.name || '').toLowerCase()));
        for (const name of this.receiptCatSuggestions) {
            if (! have.has(name.toLowerCase())) {
                this.financeCategories.push({ id: window.LLInvoicesStore.newId(), name, color: DEFAULT_CAT_COLOR, icon: DEFAULT_CAT_ICON, builtin: true });
                changed = true;
            }
        }
        if (changed) this._save();
    },
    // Generic paging helpers (used by the settings + project tables).
    _pageSlice(arr, page, per) { const s = (Math.max(1, page) - 1) * per; return (arr || []).slice(s, s + per); },
    _pageCount(len, per) { return Math.max(1, Math.ceil((len || 0) / per)); },
    // Built-in default categories paging.
    accountTxCount(pm) { return (this.transactions || []).filter((t) => t.account === pm.id).length; },
    // Balance = sum of an account's transactions (imported statements are signed).
    accountBalance(pm) { return (this.transactions || []).filter((t) => t.account === pm.id).reduce((s, t) => s + (t.amount || 0), 0); },
    get accountIncome() { return this.accountTx.filter((t) => t.amount > 0).reduce((s, t) => s + t.amount, 0); },
    get accountExpense() { return this.accountTx.filter((t) => t.amount < 0).reduce((s, t) => s + t.amount, 0); },

    // ---- Receipts (Belege) — sealed files attached to outgoing transactions ----
    receiptTx: null,        // the transaction whose receipts panel is open
    receiptBusy: false,
    get outgoingTx() { return this.accountTx.filter((t) => t.amount < 0); },
    // Bookings that need a document: everything except private deposits/withdrawals.
    // Income is documented by its matched invoice (a locked receipt), expenses by a
    // receipt — both stored in tx.receipts, so the check is uniform.
    get documentableTx() { return this.accountTx.filter((t) => t.vatCat !== 'private'); },
    // Income bookings not yet linked to an invoice (drives the "match invoices" action).
    get unlinkedIncomeCount() { const id = this.payAccount?.id; return (this.transactions || []).filter((t) => t.account === id && t.amount > 0 && ! t.invoiceId).length; },
    // How many documentable bookings (non-private) still have no document attached.
    get missingReceipts() { return this.documentableTx.filter((t) => ! (t.receipts && t.receipts.length)).length; },
    receiptCount(tx) { return (tx.receipts || []).filter((r) => ! r.trashed).length; },
    // Per bank account: outgoing bookings + how many still lack a receipt (Belege tab).
    get receiptOverview() {
        return sortedPaymentMethods(this.paymentMethods).filter((p) => p.type === 'bank').map((pm) => {
            const docs = (this.transactions || []).filter((t) => t.account === pm.id && t.vatCat !== 'private');
            return { pm, outgoing: docs.length, missing: docs.filter((t) => ! (t.receipts && t.receipts.length)).length };
        });
    },

    // ---- Belege document manager (flattened receipts across all bookings) ----
    receiptCatSuggestions: ['Geschäftsessen', 'Bewirtung', 'Bürobedarf', 'Reisekosten', 'Fortbildung', 'Software', 'Hardware', 'Marketing', 'Miete', 'Versicherung', 'Kfz', 'Telekommunikation', 'Sonstiges'],
    receiptQuery: '',
    // Upload from the Belege tab: pick a booking, then open its receipts panel.
    receiptAddPick: false,
    addBookingQuery: '',
    get addBookingCandidates() {
        const q = this.addBookingQuery.trim().toLowerCase();
        let list = (this.transactions || []);
        if (q) list = list.filter((t) => (t.counterparty || '').toLowerCase().includes(q) || (t.purpose || '').toLowerCase().includes(q) || (t.date || '').includes(q));
        return list.sort((a, b) => (b.date || '').localeCompare(a.date || '')).slice(0, 25);
    },
    pickBookingForReceipt(tx) { this.receiptAddPick = false; this.openReceipts(tx); },
    // Upload receipts and auto-attach each to a booking whose amount matches (from OCR).
    // Unmatched/ambiguous ones go to a small assignment step.
    receiptAssign: [],   // pending receipts needing manual assignment: [{ up, total, cands }]
    autoUploadBusy: false,
    async uploadReceiptsAuto(fileList) {
        const files = [...(fileList || [])];
        if (! files.length) return;
        this.autoUploadBusy = true;
        await this._ensureContactsLoaded(); // so we can match existing contacts before creating a partner
        const seen = this._existingReceiptSigs();
        let attached = 0, dupes = 0;
        for (const file of files) {
            try {
                const bytes = new Uint8Array(await file.arrayBuffer());
                // Content-hash dedup: skip a file whose bytes already exist as a receipt.
                const sig = await fileSig(bytes.slice(0));
                if (sig && seen.has(sig)) { dupes++; continue; }
                const up = await this._uploadFile(bytes, file.name, file.type || 'application/octet-stream');
                if (! up) continue;
                if (sig) { up.sig = sig; seen.add(sig); }
                up.id = window.LLInvoicesStore.newId();
                // Recognise (text + total + category/tags) up front.
                const text = await extractDocText(bytes.slice(0), up.mime, up.name);
                let total = null;
                if (text && text.replace(/\s+/g, '').length >= 8) {
                    up.ocr = text.slice(0, 200000);
                    const a = analyzeReceiptText(text);
                    this._applyAnalysis(up, a);
                    total = a.total;
                }
                // Auto-attach only an unambiguous exact match; otherwise offer fuzzy
                // suggestions (±3 days, rough EUR/USD conversion) in the assignment dialog.
                const rcpt = { total, date: up.date, currency: up.currency };
                const pick = autoPick(rcpt, this.transactions, 3);
                if (pick) { pick.receipts = pick.receipts || []; pick.receipts.push(up); this._autoPartner(up, pick); this._renameReceipt(up, pick); this._applyReceiptVat(up, pick); attached++; }
                else { this._renameReceipt(up, null); this.receiptAssign.push({ up, total, cands: suggestBookings(rcpt, this.transactions, { rates: config.fxRates, limit: 12 }).map((s) => s.t) }); }
            } catch (e) { /* skip */ }
        }
        this.autoUploadBusy = false;
        this._save(); this.reconcileBlobs();
        if (this.receiptAssign.length) this._loadAssignPreview();
        if (attached) window.llToast?.((labels.receipt_auto_attached || ':n receipts matched by amount.').replace(':n', attached));
        if (dupes) window.llToast?.((labels.receipt_dupes_skipped || ':n duplicate(s) skipped.').replace(':n', dupes));
    },
    // The content-hash signatures of every receipt already stored (dedup on upload).
    _existingReceiptSigs() {
        const set = new Set();
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (r.sig) set.add(r.sig);
        return set;
    },
    // Assign a pending (unmatched) receipt to a chosen booking.
    assignPending(idx, tx) {
        const p = this.receiptAssign[idx]; if (! p || ! tx) return;
        tx.receipts = tx.receipts || []; tx.receipts.push(p.up);
        this._autoPartner(p.up, tx); this._renameReceipt(p.up, tx); this._applyReceiptVat(p.up, tx);
        this.receiptAssign.splice(idx, 1);
        this.assignQuery = '';
        this._save(); this.reconcileBlobs();
        if (this.receiptAssign.length) this._loadAssignPreview(); else this.closeAssignPreview();
    },
    dropPending(idx) {
        const p = this.receiptAssign[idx]; if (! p) return;
        this.receiptAssign.splice(idx, 1);
        this.assignQuery = '';
        this.reconcileBlobs(); // the uploaded-but-unassigned blob is grace-swept
        if (this.receiptAssign.length) this._loadAssignPreview(); else this.closeAssignPreview();
    },
    // Inline preview of the receipt currently being assigned (decrypt client-side, ZK).
    assignPreview: null, // { url, mime, name }
    async _loadAssignPreview() {
        this.closeAssignPreview();
        const up = this.receiptAssign[0]?.up; if (! up || ! up.blob) return;
        try {
            const buf = await fetchBlobBuffer(`${config.rawBase}/${up.blob}`);
            const plain = window.Vault.decryptFile(buf, up.key);
            const url = URL.createObjectURL(new Blob([plain], { type: up.mime || 'application/octet-stream' }));
            this.assignPreview = { url, mime: up.mime || '', name: up.name || '' };
        } catch (e) { /* preview is best-effort */ }
    },
    closeAssignPreview() {
        if (this.assignPreview?.url) { try { URL.revokeObjectURL(this.assignPreview.url); } catch (e) { /* */ } }
        this.assignPreview = null;
    },
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
    // Give every stored receipt a stable id (once) so it can be edited/re-linked.
    _ensureReceiptIds() {
        let changed = false;
        for (const tx of (this.transactions || [])) {
            for (const r of (tx.receipts || [])) { if (! r.id) { r.id = window.LLInvoicesStore.newId(); changed = true; } }
        }
        if (changed) this._save();
    },
    // Every ACTIVE receipt with its owning booking, newest booking first (trashed excluded).
    get allReceipts() {
        const out = [];
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (! r.trashed) out.push({ r, tx });
        return out.sort((a, b) => (b.tx.date || '').localeCompare(a.tx.date || ''));
    },
    // Receipt trash bin.
    showReceiptTrash: false,
    get trashedReceipts() {
        const out = [];
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (r.trashed) out.push({ r, tx });
        return out.sort((a, b) => String(b.r.trashed).localeCompare(String(a.r.trashed)));
    },
    restoreReceipt(d) { if (d?.r) { d.r.trashed = false; this._save(); } },
    async deleteReceiptForever(d) {
        if (! d?.r || ! await this.$store.confirm.ask(labels.receipt_delete_confirm || 'Remove this receipt?')) return;
        const arr = d.tx.receipts || []; const i = arr.indexOf(d.r);
        if (i >= 0) arr.splice(i, 1);
        this._save(); this.reconcileBlobs();
    },
    async emptyReceiptTrash() {
        if (! this.trashedReceipts.length) return;
        if (! await this.$store.confirm.ask(labels.trash_empty_confirm || 'Permanently delete all receipts in the trash?')) return;
        for (const tx of (this.transactions || [])) if (tx.receipts) tx.receipts = tx.receipts.filter((r) => ! r.trashed);
        this._save(); this.reconcileBlobs();
    },
    get filteredReceipts() {
        const q = this.receiptQuery.trim().toLowerCase();
        let list = this.allReceipts.filter((d) => this._scopeMatch(this._receiptPrivate(d)));
        if (q) list = list.filter(({ r, tx }) =>
            (r.name || '').toLowerCase().includes(q) || (r.note || '').toLowerCase().includes(q) ||
            this.catList(r).join(' ').toLowerCase().includes(q) || (r.tags || []).join(' ').toLowerCase().includes(q) ||
            (r.merchant || '').toLowerCase().includes(q) || (r.ocr || '').toLowerCase().includes(q) ||
            (tx.counterparty || '').toLowerCase().includes(q) || (tx.purpose || '').toLowerCase().includes(q));
        return list;
    },
    async openReceiptDoc(doc) {
        this.receiptDoc = doc;
        this.tagsValue = (doc.r.tags || []).join(", ");
        this._loadDocPreview();
        try { if (await bootStore(this.$store, 'contacts')) this._receiptContacts = (window.LLModuleStore.contacts.data.contacts || []).filter((c) => ! c.trashed); }
        catch (e) { /* leave empty */ }
    },
    closeReceiptDoc() { this.closeDocPreview(); this.receiptDoc = null; },
    // Inline preview of the open receipt (decrypt client-side, ZK) shown beside its info.
    docPreview: null, // { url, mime, name }
    async _loadDocPreview() {
        this.closeDocPreview();
        const r = this.receiptDoc?.r; if (! r || ! r.blob) return; // invoice-linked receipts have no stored blob
        try {
            const buf = await fetchBlobBuffer(`${config.rawBase}/${r.blob}`);
            const plain = window.Vault.decryptFile(buf, r.key);
            const url = URL.createObjectURL(new Blob([plain], { type: r.mime || 'application/octet-stream' }));
            this.docPreview = { url, mime: r.mime || '', name: r.name || '' };
        } catch (e) { /* preview is best-effort */ }
    },
    closeDocPreview() {
        if (this.docPreview?.url) { try { URL.revokeObjectURL(this.docPreview.url); } catch (e) { /* */ } }
        this.docPreview = null;
    },
    get docPreviewIsImage() { return /^image\//.test(this.docPreview?.mime || '') || /\.(png|jpe?g|gif|webp|bmp|avif)$/i.test(this.docPreview?.name || ''); },
    get docPreviewIsPdf() { return this.docPreview?.mime === 'application/pdf' || /\.pdf$/i.test(this.docPreview?.name || ''); },
    receiptContacts() {
        const q = (this.receiptDoc?.r.contactQuery || '').trim().toLowerCase();
        let list = this._receiptContacts;
        if (q) list = list.filter((c) => (contactDisplayName(c) || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => (contactDisplayName(a) || '').localeCompare(contactDisplayName(b) || '')).slice(0, 8);
    },
    contactName(id) { const c = (this._receiptContacts || []).find((x) => x.id === id); return c ? contactDisplayName(c) : ''; },
    setReceiptContact(c) { if (this.receiptDoc) { this.receiptDoc.r.contactId = c ? c.id : null; this.receiptDoc.r.contactName = c ? contactDisplayName(c) : ''; this.saveReceiptDoc(); } },
    saveReceiptDoc() {
        if (! this.receiptDoc) return;
        this.receiptDoc.r.tags = (this.tagsValue || '').split(',').map((t) => t.trim()).filter(Boolean);
        this._learnFromReceipt(this.receiptDoc.r, this.receiptDoc.tx);
        this._save();
    },
    // Move a receipt to another booking (re-link).
    relinkReceiptTo(tx) {
        const doc = this.receiptDoc; if (! doc || ! tx || tx.id === doc.tx.id) { this.receiptRelink = false; return; }
        const arr = doc.tx.receipts || []; const i = arr.indexOf(doc.r);
        if (i >= 0) arr.splice(i, 1);
        tx.receipts = tx.receipts || []; tx.receipts.push(doc.r);
        doc.tx = tx; this.receiptRelink = false;
        this._save(); this.reconcileBlobs();
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
        const cur = this.receiptDoc.r.name || '';
        this.$store.confirm.prompt(labels.receipt_rename || 'Rename receipt', { value: cur }).then((v) => {
            if (v != null && v.trim()) { this.receiptDoc.r.name = v.trim(); this._save(); }
        });
    },
    async deleteReceiptDoc() {
        const doc = this.receiptDoc; if (! doc || doc.r.locked) return;
        if (! await this.$store.confirm.ask(labels.receipt_delete_confirm || 'Remove this receipt?')) return;
        const arr = doc.tx.receipts || []; const i = arr.indexOf(doc.r);
        if (i >= 0) arr.splice(i, 1);
        this.receiptDoc = null; this._save(); this.reconcileBlobs();
    },

    // ---- Re-run OCR/recognition on already-uploaded receipts ----
    reanalyzeBusy: false,
    async reanalyzeReceipt(doc, save = true) {
        const r = doc?.r; if (! r || r.kind === 'invoice' || ! r.blob) return false;
        try {
            const buf = await fetchBlobBuffer(`${config.rawBase}/${r.blob}`);
            const plain = window.Vault.decryptFile(buf, r.key);
            const bytes = plain instanceof Uint8Array ? plain : new Uint8Array(plain);
            const text = await extractDocText(bytes, r.mime, r.name);
            if (text && text.replace(/\s+/g, '').length >= 8) {
                r.ocr = text.slice(0, 200000);
                this._applyAnalysis(r, analyzeReceiptText(text));
            }
            await this._ensureContactsLoaded();
            this._autoPartner(r, doc.tx);
            this._renameReceipt(r, doc.tx);
            this._applyReceiptVat(r, doc.tx);
            if (save) { this._save(); if (this.receiptDoc === doc) this.tagsValue = (r.tags || []).join(', '); }
            return true;
        } catch (e) { return false; }
    },
    // Bulk: (re-)recognise every non-invoice receipt that has no OCR text yet.
    reanalyzeTotal: 0,
    reanalyzeProgress: 0,
    // Re-run recognition. force=false → only receipts without OCR yet (backfill);
    // force=true → EVERY non-invoice receipt (the "re-scan all" button).
    async reanalyzeAllReceipts(force = false) {
        if (this.reanalyzeBusy) return;
        const docs = this.allReceipts.filter((d) => d.r.kind !== 'invoice' && (force || ! d.r.ocr));
        if (! docs.length) return;
        this.reanalyzeBusy = true;
        this.reanalyzeTotal = docs.length; this.reanalyzeProgress = 0;
        let n = 0;
        for (const doc of docs) {
            if (await this.reanalyzeReceipt(doc, false)) n++;
            this.reanalyzeProgress++;
        }
        this.reanalyzeBusy = false;
        if (n) this._save();
        window.llToast?.((labels.reanalyze_done || ':n receipts recognised.').replace(':n', n));
    },
    get unrecognisedReceipts() { return this.allReceipts.filter((d) => d.r.kind !== 'invoice' && ! d.r.ocr).length; },

    // ---- Business partners (Geschäftspartner) — standalone, or an existing contact ----
    partners: [],
    financeCategories: [],
    partnerEditing: null,
    newPartner() { this.partnerEditing = { name: '', url: '', address: '', email: '', invoiceEmail: '', phone: '', vatId: '', category: '', note: '', hourlyRate: null, currency: '', contacts: [] }; },
    // Contact persons (Ansprechpartner) — multiple per partner.
    _newContact() { return { id: window.LLInvoicesStore.newId(), name: '', email: '', phone: '', role: '' }; },
    addPartnerContact(p) { if (! p) return; (p.contacts ||= []).push(this._newContact()); },
    removePartnerContact(p, i) { if (p && Array.isArray(p.contacts)) p.contacts.splice(i, 1); },
    // Migrate the legacy single `contact` string into the contacts[] list (one-time, idempotent).
    _migratePartnerContacts() {
        let changed = false;
        for (const p of (this.partners || [])) {
            if (! Array.isArray(p.contacts)) { p.contacts = []; changed = true; }
            if (! p.contacts.length && String(p.contact || '').trim()) { p.contacts.push({ id: window.LLInvoicesStore.newId(), name: String(p.contact).trim(), email: p.email || '', phone: p.phone || '', role: '' }); changed = true; }
        }
        if (changed) this._save();
    },
    // Contact persons of the partner matching a recipient name (for the import picker).
    partnerContactsFor(name) { const p = matchPartner(this.partners, name); return (p && Array.isArray(p.contacts)) ? p.contacts : []; },
    editPartner(p) { this.partnerEditing = JSON.parse(JSON.stringify(p)); this.partnerEditing.id = p.id; },
    cancelPartner() { this.partnerEditing = null; },
    savePartner() {
        const p = this.partnerEditing; if (! p || ! String(p.name || '').trim()) return;
        let saved;
        if (p.id) { const i = this.partners.findIndex((x) => x.id === p.id); if (i >= 0) { Object.assign(this.partners[i], p); saved = this.partners[i]; } }
        else { p.id = window.LLInvoicesStore.newId(); this.partners.push(p); saved = p; }
        this._save(); this.partnerEditing = null;
        // Best-effort: pull the partner's logo from its website (SSRF-guarded proxy).
        if (saved && saved.url) this._fetchPartnerLogo(saved);
    },
    // Fetch a partner logo/favicon from its URL via the same SSRF-guarded proxy as bank
    // icons; store the returned data URI on the partner (non-secret, sealed like the rest).
    async _fetchPartnerLogo(p) {
        const host = this._bankHost(p.url);
        if (! host || ! config.iconUrl) return;
        try {
            const res = await fetch(`${config.iconUrl}?domain=${encodeURIComponent(host)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! res.ok) return;
            const { icon } = await res.json();
            if (icon && p.logo !== icon) { p.logo = icon; this._save(); }
        } catch (e) { /* best effort */ }
    },
    // A usable <img> src for a stored partner logo (only data:/http(s) URIs).
    partnerLogoSrc(p) { const v = p && p.logo; return (typeof v === 'string' && /^(data:|https?:)/.test(v)) ? v : ''; },
    async removePartner(p) {
        if (! await this.$store.confirm.ask(labels.partner_delete_confirm || 'Delete this business partner?')) return;
        const i = this.partners.indexOf(p); if (i >= 0) this.partners.splice(i, 1);
        this._save();
    },
    get sortedPartners() { return [...(this.partners || [])].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''))); },

    // ---- Business-partners tab (own section: list/table ↔ detail) ----
    partnersView: 'list', // 'list' | 'detail'
    openPartnerId: null,
    partnerSearch: '',
    partnerEditMode: false, // detail: read-only until "Bearbeiten"
    get filteredPartners() {
        const q = this.partnerSearch.trim().toLowerCase();
        let list = this.sortedPartners;
        if (q) list = list.filter((p) => [p.name, p.contact, p.email, p.phone, p.vatId, p.address, p.url, p.category, p.note].some((v) => String(v || '').toLowerCase().includes(q)));
        return list;
    },
    openPartner(p) { this.openPartnerId = p.id; this.partnersView = 'detail'; this.partnerEditMode = false; },
    backToPartners() { this.partnersView = 'list'; this.openPartnerId = null; this.partnerEditMode = false; },
    get openPartnerRec() { return (this.partners || []).find((p) => p.id === this.openPartnerId) || null; },
    editOpenPartner() { this.partnerEditMode = true; },
    async deleteOpenPartner() {
        const p = this.openPartnerRec; if (! p) return;
        if (! await this.$store.confirm.ask(labels.partner_delete_confirm || 'Delete this business partner?')) return;
        const i = this.partners.indexOf(p); if (i >= 0) this.partners.splice(i, 1);
        this._save();
        this.backToPartners();
    },
    saveOpenPartner() {
        const p = this.openPartnerRec; if (! p || ! String(p.name || '').trim()) return;
        this.partnerEditMode = false;
        this._save();
        if (p.url) this._fetchPartnerLogo(p);
    },
    // Invoices whose recipient is this partner.
    invoicesForPartner(id) { return (this.invoices || []).filter((i) => ! i.trashed && i.customer && i.customer.partnerId === id).sort((a, b) => (b.issueDate || '').localeCompare(a.issueDate || '')); },
    // Receipts (booking documents) wired to this partner.
    receiptsForPartner(id) { return this.allReceipts.filter((d) => d.r && d.r.partnerId === id); },
    partnerLinkCount(id) { return this.invoicesForPartner(id).length + this.receiptsForPartner(id).length; },

    // Locale for alphabetical sorting (follows the app language).
    _catLocale() { return document.documentElement.lang || undefined; },
    // All category names (builtins are seeded into financeCategories), sorted A→Z per language.
    get allCategories() {
        const loc = this._catLocale();
        return [...new Set([...(this.financeCategories || []).map((c) => c.name), ...this.receiptCatSuggestions])].sort((a, b) => a.localeCompare(b, loc));
    },
    get sortedFinanceCategories() { const loc = this._catLocale(); return [...(this.financeCategories || [])].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), loc)); },
    addFinanceCategory(name) {
        const n = String(name || '').trim(); if (! n) return;
        const exists = (this.financeCategories || []).some((c) => c.name.toLowerCase() === n.toLowerCase());
        if (! exists) { this.financeCategories.push({ id: window.LLInvoicesStore.newId(), name: n, color: DEFAULT_CAT_COLOR, icon: DEFAULT_CAT_ICON }); this._save(); }
        this.newCategoryName = '';
    },
    // Builtins can't be deleted (only their color/icon customised); custom categories can.
    async removeFinanceCategory(c) { if (c.builtin) return; const i = this.financeCategories.indexOf(c); if (i >= 0) this.financeCategories.splice(i, 1); this._save(); },

    // Backfill stable ids on legacy id-less finance categories so the 409 rebase can
    // merge them per-record (id-less arrays collapse to last-writer-wins, silently
    // dropping a category a second client added). Runs once on load.
    _migrateCategoryIds() {
        let changed = false;
        for (const c of (this.financeCategories || [])) {
            if (c && ! c.id) { c.id = window.LLInvoicesStore.newId(); changed = true; }
        }
        // Backfill stable ids on legacy id-less invoice versions so the 409 merge keys
        // them uniquely (not on the non-unique per-invoice `seq`) — otherwise two
        // devices versioning the same invoice can overwrite each other's version.
        for (const inv of (this.invoices || [])) {
            for (const v of (inv.versions || [])) {
                if (v && ! v.id) { v.id = window.LLInvoicesStore.newId(); changed = true; }
            }
        }
        if (changed) this._save();
    },
    newCategoryName: '',

    // ---- Cost projects (nestable): bundle receipts + manual "hand" expenses ----
    projects: [],
    projectEditing: null,   // { id?, name, parentId, note } in the create/edit modal
    openProjectId: null,    // the project whose detail is shown
    expenseEditing: null,   // { projectId, id?, amount, date, note } in the expense modal
    get projectRows() { return buildProjectTree(this.projects); },
    get openProject() { return (this.projects || []).find((p) => p.id === this.openProjectId) || null; },
    projectTotal(id) { return projectRolled(this.projects, id, this.allReceipts); },
    projectOwnTotal(id) { const p = (this.projects || []).find((x) => x.id === id); return p ? Math.round(projectOwn(p, this.allReceipts) * 100) / 100 : 0; },
    projectSubs(id) { return (this.projects || []).filter((p) => (p.parentId || null) === (id || null)); },
    projectReceiptList(id) { return receiptsForProject(this.allReceipts, id); },
    projectName(id) { const p = (this.projects || []).find((x) => x.id === id); return p ? p.name : ''; },
    // Options for a parent/target picker, excluding a subtree (can't nest under itself).
    projectOptions(excludeId) {
        const banned = new Set([excludeId]);
        if (excludeId) { const walk = (pid) => { for (const c of this.projectSubs(pid)) { banned.add(c.id); walk(c.id); } }; walk(excludeId); }
        return buildProjectTree(this.projects).filter((x) => ! banned.has(x.project.id));
    },
    newProject(parentId = null) { const par = parentId ? (this.projects || []).find((x) => x.id === parentId) : null; this.projectEditing = { name: '', parentId: parentId || '', note: '', kind: par ? (par.kind || 'business') : 'business' }; },
    editProject(p) { this.projectEditing = { id: p.id, name: p.name || '', parentId: p.parentId || '', note: p.note || '', kind: p.kind || 'business' }; },
    cancelProject() { this.projectEditing = null; },
    saveProject() {
        const e = this.projectEditing; if (! e || ! String(e.name || '').trim()) return;
        // A sub-project always inherits its parent's kind; only a root project sets it.
        const parent = e.parentId ? this.projects.find((x) => x.id === e.parentId) : null;
        const kind = parent ? this.effectiveKind(parent.id) : (e.kind === 'private' ? 'private' : 'business');
        if (e.id) { const p = this.projects.find((x) => x.id === e.id); if (p) { p.name = e.name.trim(); p.parentId = e.parentId || null; p.note = e.note || ''; p.kind = kind; } }
        else { this.projects.push({ id: window.LLInvoicesStore.newId(), name: e.name.trim(), parentId: e.parentId || null, note: e.note || '', kind, expenses: [], created: new Date().toISOString() }); }
        this._normalizeKinds(); // cascade the (possibly changed) root kind through the tree
        this.projectEditing = null; this._save();
    },
    async removeProject(p) {
        if (! p) return;
        if (! await this.$store.confirm.ask(labels.project_delete_confirm || 'Delete this project and its sub-projects? Bundled receipts are kept but un-assigned.')) return;
        const ids = new Set([p.id]);
        const walk = (pid) => { for (const c of this.projectSubs(pid)) { ids.add(c.id); walk(c.id); } };
        walk(p.id);
        // Un-assign every receipt bundled to a removed project (the receipts themselves stay).
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (ids.has(r.projectId)) r.projectId = null;
        // Splice in place — reassigning would detach this.projects from the sealed
        // store collection (LLInvoicesStore.data.projects), so later writes would seal
        // the stale array and lose the deletion / subsequent edits.
        for (let i = this.projects.length - 1; i >= 0; i--) if (ids.has(this.projects[i].id)) this.projects.splice(i, 1);
        if (ids.has(this.openProjectId)) this.openProjectId = null;
        this._save();
    },
    // Manual expenses (cash / "hand" costs not tied to a bank booking).
    newExpense(projectId) { this.expenseEditing = { projectId, amount: null, date: new Date().toISOString().slice(0, 10), note: '', account: '', category: '' }; },
    editExpense(project, exp) { this.expenseEditing = { projectId: project.id, id: exp.id, amount: exp.amount, date: exp.date || '', note: exp.note || '', account: exp.account || '', category: exp.category || '' }; },
    cancelExpense() { this.expenseEditing = null; },
    saveExpense() {
        const e = this.expenseEditing; if (! e) return;
        const amt = Number(e.amount); if (! Number.isFinite(amt) || amt <= 0) { window.llToast?.(labels.project_expense_invalid || 'Enter an amount.'); return; }
        const p = this.projects.find((x) => x.id === e.projectId); if (! p) return;
        p.expenses = p.expenses || [];
        const fields = { amount: amt, date: e.date || '', note: e.note || '', account: e.account || '', category: e.category || '' };
        if (e.id) { const ex = p.expenses.find((x) => x.id === e.id); if (ex) Object.assign(ex, fields); }
        else { p.expenses.push({ id: window.LLInvoicesStore.newId(), ...fields }); }
        this.expenseEditing = null; this._save();
    },
    // The payment-method label for an expense's account id (managed under Zahlungsmittel).
    expenseAccountName(id) { const pm = (this.paymentMethods || []).find((x) => x.id === id); return pm ? pm.label : ''; },
    async removeExpense(project, exp) {
        if (! project || ! exp) return;
        const p = this.projects.find((x) => x.id === project.id); if (! p) return;
        const i = (p.expenses || []).indexOf(exp); if (i >= 0) { p.expenses.splice(i, 1); this._save(); }
    },
    // Assign / unassign a receipt to a project (from the receipt detail modal).
    setReceiptProject(id) { const r = this.receiptDoc?.r; if (! r) return; r.projectId = id || null; this._save(); },
    // Open a project's detail and reset the per-list pages.
    openProjectDetail(id) { this.openProjectId = id; this.subPage = 1; this.expPage = 1; this.prcPage = 1; },
    // Private vs business split (each project's OWN total, so the tree isn't double-counted).
    get projectKindSummary() {
        let business = 0, priv = 0;
        for (const p of (this.projects || [])) { const t = this.projectOwnTotal(p.id); if (this.effectiveKind(p.id) === 'private') priv += t; else business += t; }
        return { business: Math.round(business * 100) / 100, private: Math.round(priv * 100) / 100 };
    },
    projectKindLabel(kind) { return kind === 'private' ? (labels.project_kind_private || 'Private') : (labels.project_kind_business || 'Business'); },
    // A project's effective kind = its ROOT ancestor's kind: a sub-project ALWAYS shares
    // its parent's business/private type (a child can never differ from its parent).
    effectiveKind(id) {
        const byId = new Map((this.projects || []).map((p) => [p.id, p]));
        let cur = byId.get(id), guard = 0;
        while (cur && cur.parentId && byId.get(cur.parentId) && guard++ < 100) cur = byId.get(cur.parentId);
        return cur && cur.kind === 'private' ? 'private' : 'business';
    },
    // Force every sub-project to its root's kind (repairs edits + legacy data).
    _normalizeKinds() { for (const p of (this.projects || [])) p.kind = this.effectiveKind(p.id); },
    // The scoped project tree uses the GLOBAL finance scope.
    get scopedProjectRows() { return this.projectRows.filter((r) => this._scopeMatch(this.effectiveKind(r.project.id) === 'private')); },
    // Paging — project tree.
    projPage: 1, projPerPage: 15,
    get projPageCount() { return this._pageCount(this.scopedProjectRows.length, this.projPerPage); },
    get pagedProjectRows() { return this._pageSlice(this.scopedProjectRows, this.projPage, this.projPerPage); },
    setProjPerPage(n) { this.projPerPage = n; this.projPage = 1; },
    projGoto(p) { this.projPage = Math.min(this.projPageCount, Math.max(1, p)); },
    // Paging — sub-projects of the open project.
    subPage: 1, subPerPage: 10,
    get subPageCount() { return this._pageCount(this.projectSubs(this.openProjectId).length, this.subPerPage); },
    get pagedSubs() { return this._pageSlice(this.projectSubs(this.openProjectId), this.subPage, this.subPerPage); },
    setSubPerPage(n) { this.subPerPage = n; this.subPage = 1; },
    subGoto(p) { this.subPage = Math.min(this.subPageCount, Math.max(1, p)); },
    // Paging — manual expenses of the open project.
    expPage: 1, expPerPage: 10,
    get expPageCount() { return this._pageCount((this.openProject?.expenses || []).length, this.expPerPage); },
    get pagedExpenses() { return this._pageSlice(this.openProject?.expenses || [], this.expPage, this.expPerPage); },
    setExpPerPage(n) { this.expPerPage = n; this.expPage = 1; },
    expGoto(p) { this.expPage = Math.min(this.expPageCount, Math.max(1, p)); },
    // Paging — bundled receipts of the open project.
    prcPage: 1, prcPerPage: 10,
    get prcPageCount() { return this._pageCount(this.projectReceiptList(this.openProjectId).length, this.prcPerPage); },
    get pagedProjectReceipts() { return this._pageSlice(this.projectReceiptList(this.openProjectId), this.prcPage, this.prcPerPage); },
    setPrcPerPage(n) { this.prcPerPage = n; this.prcPage = 1; },
    prcGoto(p) { this.prcPage = Math.min(this.prcPageCount, Math.max(1, p)); },
    // Receipt picker: bundle existing receipts into the open project.
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
    toggleReceiptToProject(d) {
        if (! d?.r || ! this.openProjectId) return;
        d.r.projectId = d.r.projectId === this.openProjectId ? null : this.openProjectId;
        this._save();
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
    setReceiptPartner(opt) {
        const r = this.receiptDoc?.r; if (! r) return;
        if (! opt) { r.contactId = null; r.partnerId = null; r.partnerName = ''; }
        else if (opt.kind === 'contact') { r.contactId = opt.id; r.partnerId = null; r.partnerName = opt.name; }
        else { r.partnerId = opt.id; r.contactId = null; r.partnerName = opt.name; }
        r.partnerQuery = '';
        // Pre-fill the category this partner is known for (learned rule), if none yet.
        if (! this.catList(r).length && opt && opt.name) { const learned = this._learnedCategory(opt.name); if (learned) this.addReceiptCat(r, learned); }
        this._save();
    },
    receiptPartnerName(r) {
        if (r.partnerName) return r.partnerName;
        if (r.contactId) return this.contactName(r.contactId) || '';
        if (r.partnerId) { const p = (this.partners || []).find((x) => x.id === r.partnerId); return p ? p.name : ''; }
        return '';
    },
    async _ensureContactsLoaded() {
        if ((this._receiptContacts || []).length) return;
        try { if (await bootStore(this.$store, 'contacts')) this._receiptContacts = (window.LLModuleStore.contacts.data.contacts || []).filter((c) => ! c.trashed); }
        catch (e) { /* leave empty */ }
    },
    _normName(s) { return normMerchant(s); },
    _partnerByName(name) { return matchPartner(this.partners, name); },
    _findOrCreatePartner(name) {
        let p = this._partnerByName(name);
        if (! p) { p = { id: window.LLInvoicesStore.newId(), name: String(name).trim() }; this.partners.push(p); }
        return p;
    },
    // The learned category for a merchant (a partner the user has categorised). This is
    // the user-specific "training": once you categorise a receipt, the same merchant is
    // categorised automatically next time.
    _learnedCategory(name) { return learnedCategoryFor(this.partners, name); },
    // Remember a receipt's category on its merchant's partner (rule holder).
    _learnFromReceipt(r, tx) {
        const name = String((tx && tx.counterparty) || r.merchant || '').trim();
        const primary = this.catList(r)[0]; // the partner rule holds one default category
        if (name.length < 2 || ! primary) return;
        const p = this._findOrCreatePartner(name);
        if (p.category !== primary) p.category = primary;
    },
    // Auto-link a receipt to a business partner from the booking's counterparty (reliable)
    // or the recognised merchant: match an existing contact/partner, else create a partner.
    // Also applies a learned category (user-confirmed → wins over the regex guess).
    _autoPartner(r, tx) {
        const name = String((tx && tx.counterparty) || r.merchant || '').trim();
        if (name.length < 2) return;
        const learned = this._learnedCategory(name);
        if (learned && ! this.catList(r).length) this.addReceiptCat(r, learned);
        if (r.contactId || r.partnerId) return;
        const nk = this._normName(name);
        const contact = (this._receiptContacts || []).find((c) => this._normName(contactDisplayName(c)) === nk);
        if (contact) { r.contactId = contact.id; r.partnerName = contactDisplayName(contact); return; }
        const partner = this._findOrCreatePartner(name);
        r.partnerId = partner.id; r.partnerName = partner.name;
    },
    openReceipts(tx) { this.receiptTx = tx; },

    // After a background 409 rebase, the store replaced every record object with a
    // fresh clone (array identity preserved, element identity not). Re-point the
    // long-lived references an open editor holds, by id, so edits made after the
    // rebase land on the LIVE record that gets sealed — not a detached ghost (F2).
    _reresolveOpenRefs() {
        if (this.current && this.current.id) {
            const live = (this.invoices || []).find((i) => i.id === this.current.id);
            if (live) this.current = live;
        }
        if (this.receiptTx && this.receiptTx.id) {
            const t = (this.transactions || []).find((x) => x.id === this.receiptTx.id);
            if (t) this.receiptTx = t;
        }
        if (this.receiptDoc && this.receiptDoc.tx && this.receiptDoc.r) {
            const t = (this.transactions || []).find((x) => x.id === this.receiptDoc.tx.id);
            if (t) {
                this.receiptDoc.tx = t;
                const r = (t.receipts || []).find((x) => x.id && x.id === this.receiptDoc.r.id);
                if (r) this.receiptDoc.r = r;
            }
        }
    },
    closeReceipts() { this.receiptTx = null; },
    async uploadReceipts(fileList) {
        const txId = this.receiptTx?.id;
        if (! txId) return;
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
                const up = await this._uploadFile(bytes, file.name, file.type || 'application/octet-stream');
                // Resolve the LIVE transaction by id after the upload await — a
                // concurrent 409 rebase may have replaced this.transactions elements with
                // clones, so a reference captured before the loop would be a detached
                // ghost and the pushed receipt would never reach the sealed store.
                const tx = (this.transactions || []).find((x) => x.id === txId);
                if (! tx) continue; // booking vanished mid-upload; the orphaned blob is grace-swept
                tx.receipts = tx.receipts || [];
                if (up) { if (sig) { up.sig = sig; seen.add(sig); } up.id = window.LLInvoicesStore.newId(); tx.receipts.push(up); ok++; this._ocrReceipt(bytes.slice(0), up, tx); }
            } catch (e) { /* skip this file */ }
        }
        this.receiptBusy = false;
        if (ok) { this._save(); this.reconcileBlobs(); }
        else if (! dupes) window.llToast?.(labels.receipt_failed || 'Upload failed.');
        if (dupes) window.llToast?.((labels.receipt_dupes_skipped || ':n duplicate(s) skipped.').replace(':n', dupes));
    },
    // Background OCR of a freshly-uploaded receipt: extract text (searchable) and suggest
    // a category + tags (only fill empty fields). Runs client-side (ZK); best effort.
    async _ocrReceipt(bytes, r, tx) {
        try {
            const text = await extractDocText(bytes, r.mime, r.name);
            if (! text || text.replace(/\s+/g, '').length < 8) return;
            r.ocr = text.slice(0, 200000);
            this._applyAnalysis(r, analyzeReceiptText(text));
            await this._ensureContactsLoaded();
            this._autoPartner(r, tx);
            this._renameReceipt(r, tx);
            this._applyReceiptVat(r, tx);
            this._save();
        } catch (e) { /* best effort */ }
    },
    // Apply recognised fields without overwriting anything the user set.
    _applyAnalysis(r, a) {
        if (! this.catList(r).length && a.category) this.addReceiptCat(r, a.category);
        if ((! r.tags || ! r.tags.length) && a.tags.length) r.tags = a.tags;
        if (a.merchant && ! r.merchant) r.merchant = a.merchant;
        if (a.date && ! r.date) r.date = a.date;
        if (a.total != null && r.total == null) r.total = a.total;
        if (a.number && ! r.number) r.number = a.number;
        if (a.vat && ! r.vat) r.vat = a.vat;
        if (a.currency && ! r.currency) r.currency = a.currency;
    },
    // Adopt the receipt's detected VAT rate onto its booking when the booking's rate is
    // still undecided (never overrides a value the import guessed or the user set).
    _applyReceiptVat(r, tx) {
        if (tx && r && r.vat && (tx.vatCat == null || tx.vatCat === '')) tx.vatCat = r.vat;
    },
    // Rename a receipt to "YYYYMMDD; Partner; Beleg / Rechnung <number>.<ext>" from the
    // recognised fields (date/partner/number), keeping the extension. Runs on upload and on
    // rescan ("Neu erkennen") — the fallback path when the original filename is unhelpful.
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
    // Quick-look a receipt/invoice in a modal (decrypt client-side). App invoices with no
    // stored PDF open the invoice view instead.
    receiptPreview: null, // { url, mime, name }
    async openReceipt(r) {
        if (r.kind === 'invoice' && ! r.blob) return this.openInvoiceById(r.invoiceId, r.invoiceNumber);
        try {
            const buf = await fetchBlobBuffer(`${config.rawBase}/${r.blob}`);
            const plain = window.Vault.decryptFile(buf, r.key);
            const url = URL.createObjectURL(new Blob([plain], { type: r.mime || 'application/octet-stream' }));
            this.closeReceiptPreview();
            this.receiptPreview = { url, mime: r.mime || '', name: r.name || '' };
        } catch (e) { window.llToast?.(labels.downloadFailed || 'Could not open file.'); }
    },
    closeReceiptPreview() {
        if (this.receiptPreview?.url) { try { URL.revokeObjectURL(this.receiptPreview.url); } catch (e) { /* */ } }
        this.receiptPreview = null;
    },
    get previewIsImage() { return /^image\//.test(this.receiptPreview?.mime || '') || /\.(png|jpe?g|gif|webp|bmp|avif)$/i.test(this.receiptPreview?.name || ''); },
    get previewIsPdf() { return this.receiptPreview?.mime === 'application/pdf' || /\.pdf$/i.test(this.receiptPreview?.name || ''); },
    openReceiptInTab() { if (this.receiptPreview?.url) window.open(this.receiptPreview.url, '_blank'); },

    // Send a receipt to Paperless — decrypt client-side, pre-fill the transfer modal with
    // the recognised fields (title = merchant, date, correspondent = partner, tag =
    // category) so Paperless gets good metadata. Takes the file OUT of the ZK store.
    async sendReceiptToPaperless(doc) {
        const r = doc?.r; if (! r || ! r.blob) return;
        const store = this.$store.paperless;
        if (! store || ! store.configured) return;
        if (! await this.$store.confirm.ask(labels.paperlessWarn || labels.paperless_warn || '')) return;
        const created = r.date || (r.ocr ? analyzeReceiptText(r.ocr).date : '') || (doc.tx.date || '');
        const title = r.merchant || (r.name || '').replace(/\.[^.]+$/, '') || 'Beleg';
        store.begin(r.name || 'beleg.pdf', { title, created: created || undefined }, { context: { source: 'receipt' } });
        const partner = this.receiptPartnerName(r); if (partner) store.corrQuery = partner;
        const cats = this.catList(r); if (cats.length) store.tagQuery = cats.join(', ');
        try {
            const buf = await fetchBlobBuffer(`${config.rawBase}/${r.blob}`);
            const plain = window.Vault.decryptFile(buf, r.key);
            store.setFile(new Blob([plain], { type: r.mime || 'application/pdf' }));
        } catch (e) { store.fail(labels.downloadFailed || 'Could not open file.'); }
    },
    // ---- Bulk receipt export (ZIP) for the tax advisor ----
    exportBusy: false,
    exportDone: 0,
    exportTotal: 0,
    accountReceiptTotal(pm) { return (this.transactions || []).filter((t) => t.account === pm.id).reduce((n, t) => n + (t.receipts || []).length, 0); },
    _csvCell(v) { const s = String(v ?? ''); return /[",;\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; },
    // Download every receipt of an account as one ZIP (each decrypted client-side) plus a
    // CSV index mapping receipts to bookings. Failures are reported, never silently dropped.
    async downloadAllReceipts(pm) {
        const txs = (this.transactions || []).filter((t) => t.account === pm.id && (t.receipts || []).length);
        const total = txs.reduce((n, t) => n + t.receipts.length, 0);
        if (! total) { window.llToast?.(labels.export_none || 'No receipts to export.'); return; }
        this.exportBusy = true; this.exportDone = 0; this.exportTotal = total;
        const files = {}; const used = new Set(); const errors = [];
        const rows = [['Datum', 'Gegenkonto', 'Betrag', 'Waehrung', 'USt', 'Zweck', 'Datei', 'Status'].map((c) => this._csvCell(c)).join(';')];
        // Filename scheme (tax advisor): "YYYYMMDD; Issuer/Recipient; Invoice number".
        const clean = (s) => String(s ?? '').replace(/[/\\:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim();
        for (const tx of txs) {
            for (const r of tx.receipts) {
                const ymd = (tx.date || '').replace(/-/g, '') || 'ohne-datum';
                const party = clean(tx.counterparty || tx.purpose || 'Buchung').slice(0, 60) || 'Buchung';
                const invNo = clean(tx.invoiceNumber || tx.eref || ''); // best-effort (linked invoice / EREF)
                const ext = ((r.name || '').match(/\.[^.]+$/) || [(r.mime === 'application/pdf' ? '.pdf' : '')])[0] || '';
                let name = [ymd, party, invNo].filter(Boolean).join('; ') + ext;
                let n = name, i = 2;
                while (used.has(n)) { n = name.replace(/(\.[^.]+)?$/, ` (${i++})$1`); }
                used.add(n); name = n;
                let status = 'ok';
                try {
                    const buf = await fetchBlobBuffer(`${config.rawBase}/${r.blob}`);
                    const plain = window.Vault.decryptFile(buf, r.key);
                    files[name] = plain instanceof Uint8Array ? plain : new Uint8Array(plain);
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
        // Soft-trash: keep the sealed blob (reconcile still references it) so it can be restored.
        r.trashed = new Date().toISOString();
        this._save();
    },

    // ---- Invoice ↔ transaction matching (incoming payments) ----
    // Link an income transaction to an issued invoice: mark it paid, remember the account,
    // and attach the invoice to the booking as a LOCKED receipt (can't be removed, only
    // added to). For an imported invoice the stored PDF is used; an app invoice opens the
    // invoice view. Returns true if newly linked.
    // The invoice's VAT category (highest rate present, if a plain 19/16/7/0), for the
    // matched income booking — the invoice knows the tax rate.
    _invoiceVatCat(inv) {
        const rates = Object.keys(this.computeTotals(inv).vatByRate).map((r) => String(parseInt(r, 10)));
        const cand = ['19', '16', '7', '0'].find((c) => rates.includes(c));
        return cand || '';
    },
    _linkInvoice(tx, inv, save = true) {
        if (! tx || ! inv || tx.invoiceId === inv.id) return false;
        tx.invoiceId = inv.id;
        tx.invoiceNumber = inv.number; // also drives the ZIP export filename
        const vc = this._invoiceVatCat(inv); if (vc) tx.vatCat = vc; // adopt the invoice's tax rate
        inv.status = 'paid';
        inv.paidAt = tx.date || this._today();
        inv.paymentAccount = tx.account;
        inv.paymentTxId = tx.id;
        tx.receipts = tx.receipts || [];
        if (! tx.receipts.some((r) => r.kind === 'invoice' && r.invoiceId === inv.id)) {
            const rec = { id: window.LLInvoicesStore.newId(), kind: 'invoice', invoiceId: inv.id, invoiceNumber: inv.number, name: (labels.invoice_word || 'Invoice') + ' ' + inv.number, locked: true };
            if (inv.pdf?.blob) { rec.blob = inv.pdf.blob; rec.key = inv.pdf.key; rec.mime = inv.pdf.mime || 'application/pdf'; }
            tx.receipts.push(rec);
        }
        if (save) { this._save(); this.reconcileBlobs(); }
        return true;
    },
    linkInvoice(tx, inv) { if (this._linkInvoice(tx, inv)) window.llToast?.((labels.match_linked || 'Linked invoice :n.').replace(':n', inv.number)); this.invoicePicker = null; },
    // Auto-match every not-yet-linked income booking of the open account to an invoice.
    // silent = run without a toast (used when opening an account).
    rematchAll(silent = false) {
        // Match across ALL of the account's income bookings (every year), not just the
        // year-filtered view — otherwise a payment dated outside the selected year is skipped.
        const id = this.payAccount?.id;
        let n = 0;
        for (const tx of (this.transactions || [])) {
            if (tx.account === id && tx.amount > 0 && ! tx.invoiceId) {
                const inv = matchInvoice(tx, this.invoices);
                if (inv && this._linkInvoice(tx, inv, false)) n++;
            }
        }
        if (n) { this._save(); this.reconcileBlobs(); }
        if (! silent) window.llToast?.((labels.match_done || ':n invoices matched.').replace(':n', n));
        return n;
    },
    // Manual link picker: open issued invoices to choose from (for an income booking).
    invoicePicker: null,
    openInvoicePicker(tx) { this.invoicePicker = tx; },
    get pickerInvoices() {
        return (this.invoices || []).filter((i) => ! i.trashed && i.number && i.status !== 'draft')
            .sort((a, b) => (b.issueDate || '').localeCompare(a.issueDate || ''));
    },
    // Open the invoice a locked receipt / booking refers to. Falls back to the invoice NUMBER
    // when the id is stale (e.g. the invoice was deleted + re-imported with a fresh id after
    // the link was made) and self-heals the stale link to the current id.
    openInvoiceById(id, number = null) {
        let inv = (this.invoices || []).find((i) => i.id === id);
        if (! inv && number) {
            const n = String(number).trim();
            inv = (this.invoices || []).find((i) => ! i.trashed && String(i.number || '').trim() === n);
            if (inv) { // heal any bookings still pointing at the old id
                for (const tx of (this.transactions || [])) {
                    if (tx.invoiceNumber === inv.number && tx.invoiceId !== inv.id) tx.invoiceId = inv.id;
                    for (const r of (tx.receipts || [])) if (r.kind === 'invoice' && r.invoiceNumber === inv.number) r.invoiceId = inv.id;
                }
                this._save();
            }
        }
        if (! inv) { window.llToast?.(labels.match_gone || 'Invoice not found.'); return; }
        this.receiptTx = null;
        this.setSection('invoices');
        this.open(inv);
    },

    // Read a statement file as text, tolerating Windows-1252 (common for Sparkasse CSVs).
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
                // Unknown CSV → let the user map columns to fields.
                this.stmt = { stage: 'map', name: file.name, header: c.header, rows: c.rows, mapping: this._guessMapping(c.header), transactions: [], fresh: [], dupes: 0 };
            }
        } else {
            window.llToast?.(labels.stmt_unknown || 'Unsupported statement format.');
        }
    },
    // A best-effort initial mapping for the manual step (matches obvious header names).
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
        // Split into genuinely-new rows and known rows that carry new info to merge in.
        const { fresh, updates } = enrichExisting(existing, transactions);
        const dupes = transactions.length - fresh.length - updates.length;
        this.stmt = { stage: 'preview', name: meta.name, format: meta.format, transactions, fresh, updates, dupes };
    },
    confirmStatementImport() {
        if (! this.stmt || ! this.payAccount) return;
        const acct = this.payAccount.id;
        for (const tx of this.stmt.fresh) {
            tx.id = window.LLInvoicesStore.newId();
            tx.account = acct;
            if (tx.vatCat == null) tx.vatCat = guessVatCat(tx); // auto where obvious, else '' (user picks)
            this.transactions.push(tx);
            // Auto-link an incoming payment to a matching issued invoice (mark paid + attach).
            if (tx.amount > 0) { const inv = matchInvoice(tx, this.invoices); if (inv) this._linkInvoice(tx, inv, false); }
        }
        // Enrich existing records with the newly-available fields.
        for (const u of (this.stmt.updates || [])) {
            const target = this.transactions.find((t) => t.account === acct && txSig(t) === u.sig);
            if (target) Object.assign(target, u.patch);
        }
        this._save();
        const n = this.stmt.fresh.length, m = (this.stmt.updates || []).length;
        window.llToast?.((labels.stmt_imported || ':n transactions imported.').replace(':n', n) + (m ? ' · ' + (labels.stmt_enriched || ':n updated').replace(':n', m) : ''));
        this.stmt = null;
    },
    // Localised payment-type label + tint for a transaction.
    txType(tx) { return classifyTxType(tx); },
    txTypeLabel(tx) { return labels['txtype_' + classifyTxType(tx)] || ''; },
    txTypeName(type) { return labels['txtype_' + type] || type; },

    // ---- VAT category per booking (for the USt calculation) ----
    vatCats: VAT_CATS,
    vatCatLabel(cat) { return cat ? (labels['vatcat_' + cat] || cat) : (labels.vatcat_none || '—'); },
    setVatCat(tx, cat) { tx.vatCat = cat; this._save(); },
    // Bookings still needing a VAT category (excludes private/decided ones).
    get accountVat() { return accountVatSummary(this.accountTx); },
    cancelStatement() { this.stmt = null; },

    // ---- Derived ----
    get activeInvoices() { return (this.invoices || []).filter((i) => ! i.trashed); },
    // Invoice trash bin.
    showInvTrash: false,
    get trashedInvoices() { return (this.invoices || []).filter((i) => i.trashed).sort((a, b) => String(b.trashed).localeCompare(String(a.trashed))); },
    async deleteInvoiceForever(inv) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm || 'Delete this invoice permanently?')) return;
        const i = this.invoices.indexOf(inv);
        if (i >= 0) this.invoices.splice(i, 1);
        this._save(); this.reconcileBlobs();
    },
    async emptyInvoiceTrash() {
        if (! this.trashedInvoices.length) return;
        if (! await this.$store.confirm.ask(labels.trash_empty_confirm || 'Permanently delete all invoices in the trash?')) return;
        // Splice in place (do not reassign — that detaches from the sealed store).
        for (let i = this.invoices.length - 1; i >= 0; i--) if (this.invoices[i].trashed) this.invoices.splice(i, 1);
        this._save(); this.reconcileBlobs();
    },
    // Invoice-list filters (on top of search + status).
    invYear: '', invCustomer: '', invLinked: '', // '' | 'linked' | 'open'
    get invFiltersActive() { return !! (this.query.trim() || this.filterStatus || this.invYear || this.invCustomer || this.invLinked); },
    resetInvFilters() { this.query = ''; this.filterStatus = ''; this.invYear = ''; this.invCustomer = ''; this.invLinked = ''; this.invPage = 1; },
    get invoiceYears() { return [...new Set(this.activeInvoices.map((i) => invoiceYear(i)).filter(Boolean))].sort((a, b) => b.localeCompare(a)); },
    get invoiceCustomers() { return [...new Set(this.activeInvoices.map((i) => i.customer?.name).filter(Boolean))].sort((a, b) => a.localeCompare(b)); },
    get filtered() {
        // Invoices are business documents — hidden in the private scope.
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
    // GoBD/Finanzamt 2026 mandatory fields: payee (name + address), expense date, creation
    // date, precise description + business purpose, net/VAT-rate/gross, reason the original
    // is missing, issuer + signature. No input-VAT deduction from a self-receipt. Rendered to
    // a PDF client-side (ZK) and attached to the booking as a receipt (kind 'eigenbeleg').
    eigenbeleg: null, // fields when the modal is open
    _egTx: null,      // the booking it belongs to
    egBusy: false,
    // Beleggrund options mirror the user's long-standing paper form.
    egGrundOptions: ['privatentnahme', 'privateinlage', 'trinkgeld', 'betriebsausgabe', 'sachgeschenk', 'sonstiges'],
    newEigenbeleg(tx) {
        if (! tx) return;
        const amt = Number(tx.amount) || 0;
        // Guess the Beleggrund from the booking: a private VAT-category booking is a
        // private draw (money out) or deposit (money in); otherwise a business expense.
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
            signature: '', // drawn on-device (PNG data URL); embedded into the sealed PDF
        };
    },
    cancelEigenbeleg() { this.eigenbeleg = null; this._egTx = null; this._egCtx = null; this._egDrawing = false; },

    // Signature pad (finger/trackpad). A simple electronic signature is legally sufficient
    // for an Eigenbeleg (no statutory written form); the sealed, immutable blob satisfies GoBD.
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
    // Only a "lost original receipt" business expense needs the strict recipient/net/VAT/
    // reason fields; a Privatentnahme/-einlage etc. is just amount + note (like the paper form).
    get egIsExpense() { return this.eigenbeleg?.grund === 'betriebsausgabe'; },
    egGrundLabel(g) { return labels['eg_grund_' + g] || g; },
    // Label a booking flagged as a private draw/deposit (vatCat 'private'); by sign.
    privatLabel(tx) {
        if (! tx || tx.vatCat !== 'private') return '';
        return (Number(tx.amount) || 0) < 0 ? (labels.eg_grund_privatentnahme || 'Privatentnahme') : (labels.eg_grund_privateinlage || 'Privateinlage');
    },
    // A private draw/deposit needs a self-receipt (Steuerberater/Finanzamt); flag until one exists.
    hasEigenbeleg(tx) { return !! (tx && (tx.receipts || []).some((r) => r && r.kind === 'eigenbeleg')); },
    // Prompt for a self-receipt only when the private booking has NO document at all yet — a
    // real uploaded/imported receipt (or a linked invoice) already documents it.
    needsEigenbeleg(tx) { return !! tx && tx.vatCat === 'private' && ! (tx.receipts && tx.receipts.length); },
    // Count of private bookings on the open account still missing a self-receipt.
    get accountPrivateNoEg() { return this.accountTx.filter((tx) => this.needsEigenbeleg(tx)).length; },
    get egNet() { const g = parseFloat(this.eigenbeleg?.gross) || 0; const r = parseFloat(this.eigenbeleg?.vatRate) || 0; return this._round2(g / (1 + r / 100)); },
    get egVat() { return this._round2((parseFloat(this.eigenbeleg?.gross) || 0) - this.egNet); },
    egVatChoices() { const s = new Set([19, 16, 7, 0]); const v = this.eigenbeleg?.vatRate; if (v != null) s.add(Number(v)); return [...s].sort((a, b) => b - a); },
    async saveEigenbeleg() {
        const e = this.eigenbeleg, txId = this._egTx?.id;
        if (! e || ! txId) return;
        // Amount is always required; a lost-receipt business expense also needs recipient + reason.
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
            const up = await this._uploadFile(bytes, base + '.pdf', 'application/pdf');
            if (! up) throw new Error('upload');
            // Resolve the live booking by id after the render+upload awaits — a
            // concurrent 409 rebase may have detached the _egTx reference; pushing onto
            // the ghost would lose the self-receipt and orphan its PDF.
            const tx = (this.transactions || []).find((x) => x.id === txId);
            if (! tx) throw new Error('booking gone'); // orphan blob grace-swept
            up.id = window.LLInvoicesStore.newId();
            up.kind = 'eigenbeleg';
            up.eigenbeleg = { ...e, net: this.egNet, vat: this.egVat };
            tx.receipts = tx.receipts || [];
            tx.receipts.push(up);
            this._save();
            this.reconcileBlobs();
            this.eigenbeleg = null; this._egTx = null;
            window.llToast?.(labels.eg_done || 'Eigenbeleg erstellt.');
        } catch (err) { window.llToast?.(labels.eg_failed || 'Konnte den Eigenbeleg nicht erstellen.'); }
        finally { this.egBusy = false; }
    },
    // Rasterise the off-screen #eigenbeleg-print node to a single-page A4 PDF (lazy deps, ZK).
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
    _defaultVat() { const v = parseFloat(this.company.default_vat_rate); return Number.isFinite(v) ? v : 19; },

    // ---- CRUD ----
    newInvoice() {
        const issue = this._today();
        const inv = {
            id: window.LLInvoicesStore.newId(),
            number: null,
            status: 'draft',
            issueDate: issue,
            dueDate: this._addDays(issue, parseInt(this.company.payment_terms_days, 10) || 14),
            currency: this.company.currency || 'EUR',
            lang: (document.documentElement.lang || 'de').slice(0, 2) === 'en' ? 'en' : 'de',
            customer: { name: '', attn: '', address: '', email: '', vatId: '', contactId: null },
            lines: [{ desc: '', qty: 1, unit: '', unitPrice: 0, vatRate: this._defaultVat() }],
            note: '',
            footer: this.company.footer_text || '',
            trashed: false,
            updated: new Date().toISOString(),
        };
        this.invoices.unshift(inv);
        this._save();
        this.open(inv);
    },
    open(inv) {
        // Backfill fields added after this invoice was created.
        inv.lang ??= 'de';
        inv.currency ??= (this.company.currency || 'EUR');
        inv.customer ??= { name: '', attn: '', address: '', email: '', vatId: '', contactId: null };
        inv.discount ??= { type: 'percent', value: 0 };
        inv.skonto ??= { percent: 0, days: 0 };
        inv.customer.attn ??= '';
        inv.customer.partnerId ??= null;
        this.current = inv;
        this.dirty = false;
        this.editUnlocked = false; // locked invoices open read-only until explicitly unlocked
        // A locked invoice (imported / finalized) is editable but every save is a versioned
        // correction (GoBD). Snapshot its state so unsaved edits can be reverted on leave.
        this._lockBaseline = this.isLocked(inv) ? JSON.stringify(this._editable(inv)) : null;
        // Imported invoices open in the minimal PDF view (inline document + a bar of the six
        // key fields); online-created invoices use the full editor.
        if (inv.imported) { this.view = 'imported'; this._loadInvoicePdf(inv); }
        else this.view = 'edit';
        this._epcQr(inv).then((d) => { this.invoiceQr = d; });
    },

    // ---- Imported invoice: inline PDF + the six key fields ----
    // Rendered client-side with pdf.js to full-width page images (no browser PDF viewer /
    // zoom chrome) so the document fills the whole field and scrolls cleanly. ZK: the PDF is
    // decrypted in-page, rendered locally, never leaves the browser.
    invoicePdf: null, // { pages: [dataURL,...] }
    async _loadInvoicePdf(inv) {
        this._revokeInvoicePdf();
        const ref = inv?.pdf;
        if (! ref?.blob) return;
        try {
            const buf = await fetchBlobBuffer(`${config.rawBase}/${ref.blob}`);
            const bytes = window.Vault.decryptFile(buf, ref.key);
            const pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
            const doc = await pdfjs.getDocument({ data: bytes.slice(0), isEvalSupported: false }).promise;
            const pages = [];
            for (let i = 1; i <= doc.numPages; i++) {
                if (this.current !== inv) return; // navigated away mid-render
                const page = await doc.getPage(i);
                const vp = page.getViewport({ scale: 2 }); // crisp; displayed at container width
                const canvas = document.createElement('canvas');
                canvas.width = vp.width; canvas.height = vp.height;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                pages.push(canvas.toDataURL('image/jpeg', 0.9));
            }
            if (this.current === inv) this.invoicePdf = { pages };
        } catch (e) { this.invoicePdf = null; }
    },
    _revokeInvoicePdf() { this.invoicePdf = null; },
    // Jump from an invoice's recipient to that business partner's page.
    goToPartner(inv) {
        const id = inv?.customer?.partnerId; if (! id) return;
        const p = (this.partners || []).find((x) => x.id === id); if (! p) return;
        this._revokeInvoicePdf();
        this.view = 'list'; this.current = null;
        this.setSection('partners');
        this.openPartner(p);
    },
    // The single synthetic line carries the money; expose gross + rate as editable props that
    // keep the line in sync (net = gross / (1 + rate/100)).
    _impLine() { const i = this.current; if (! i) return null; if (! i.lines?.length) i.lines = [{ desc: i.customer?.name || 'Rechnung', qty: 1, unit: '', unitPrice: 0, vatRate: 0 }]; return i.lines[0]; },
    get impGross() { return this._round2(this.computeTotals(this.current).gross); },
    set impGross(v) { const l = this._impLine(); if (! l) return; const rate = parseFloat(l.vatRate) || 0; l.qty = 1; l.unitPrice = this._round2((parseFloat(v) || 0) / (1 + rate / 100)); },
    get impRate() { const l = this.current?.lines?.[0]; return l ? (parseFloat(l.vatRate) || 0) : 0; },
    set impRate(v) { const gross = this.computeTotals(this.current).gross; const l = this._impLine(); if (! l) return; l.vatRate = parseFloat(v) || 0; l.qty = 1; l.unitPrice = this._round2(gross / (1 + l.vatRate / 100)); },
    // A locked invoice's fields are disabled until the user explicitly asks to edit it and
    // confirms — a fixed record shouldn't be changed by accident.
    async requestEdit() {
        if (! this.isLocked(this.current)) { this.editUnlocked = true; return; }
        const ok = await this.$store.confirm.ask(labels.edit_confirm || 'Edit this finalized invoice? Saving records a new version.');
        if (ok) this.editUnlocked = true;
    },
    backToList() {
        // Revert un-committed edits to a locked invoice (they were never persisted).
        if (this.current && this.dirty && this._lockBaseline) {
            Object.assign(this.current, JSON.parse(this._lockBaseline));
        }
        this._revokeInvoicePdf();
        this.view = 'list'; this.current = null; this.dirty = false; this.editUnlocked = false; this._lockBaseline = null;
    },
    saveSoon() { if (this.current) this.current.updated = new Date().toISOString(); this._save(); },

    // A finalized (sent/paid) or imported invoice is an immutable record: edits become
    // versioned corrections with a mandatory reason. Drafts stay free-form autosave.
    isLocked(inv) { const i = inv || this.current; return !! i && (i.imported || i.status === 'sent' || i.status === 'paid'); },
    // Field input: drafts autosave live; a locked invoice only marks dirty (persist happens
    // via saveVersionedEdit, which records a reason + a new version).
    onFieldInput() { if (this.isLocked(this.current)) { if (this.editUnlocked) this.dirty = true; } else this.saveSoon(); },
    _editable(inv) {
        return {
            number: inv.number, status: inv.status, issueDate: inv.issueDate, dueDate: inv.dueDate,
            currency: inv.currency, lang: inv.lang, customer: inv.customer, lines: inv.lines,
            note: inv.note, footer: inv.footer,
        };
    },

    // Persist an edit to a locked invoice as a NEW version (label RECHNUNGSNR-NNN, counting
    // up). The reason is mandatory and stored. Imported → the version holds field data only
    // (the original PDF stays authoritative); online → a PDF of the current sheet is rendered
    // and sealed with the version.
    async saveVersionedEdit() {
        const inv = this.current;
        if (! inv || ! this.dirty) return;
        const reason = await this.$store.confirm.prompt(
            labels.version_reason_title || 'Reason for change',
            { placeholder: labels.version_reason_ph || 'Why is this invoice being changed?' },
        );
        if (reason === null) return; // cancelled
        if (! String(reason).trim()) { window.llToast?.(labels.version_reason_required || 'A reason is required.'); return; }
        this.pdfBusy = true;
        try {
            await this._commitVersion(inv, String(reason).trim());
            this._save();
            this.reconcileBlobs();
            this.dirty = false;
            this.editUnlocked = false; // re-lock after a versioned save (re-confirm to edit again)
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
        // Stable random id — NOT seq. seq is a per-invoice counter, so two devices
        // versioning the SAME invoice concurrently both mint seq=N; keying the 409
        // merge on seq (keyOf) would let the loser's rebase overwrite the winner's
        // already-committed version and orphan its sealed GoBD PDF. A random id keys
        // the merge uniquely so both versions survive.
        const version = { id: window.LLInvoicesStore.newId(), seq, label, reason, at: new Date().toISOString(), snapshot };
        // Online-created invoices freeze a generated PDF per version; imported keep fields only.
        if (! inv.imported) {
            const pdf = await this._renderInvoicePdf(inv, label);
            if (pdf) version.pdf = pdf;
        }
        inv.versions = inv.versions || [];
        inv.versions.push(version);
    },
    // Render the current invoice's print sheet to a (raster) PDF client-side and seal it.
    // ZK: nothing leaves the browser — html2canvas rasterises the in-page node, jsPDF wraps
    // it, and _uploadFile encrypts before upload. Lazy-loaded so the deps stay out of the
    // main bundle.
    // Rasterise the hidden #invoice-print sheet for `inv` into a PDF Blob (client-side,
    // zero-knowledge — nothing leaves the browser). Shared by the encrypted version PDF
    // and the local preview. Returns null on failure.
    async _invoicePdfBlob(inv) {
        const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas'), import('jspdf')]);
        this.printQr = await this._epcQr(inv);
        this._printing = inv;
        await this.$nextTick();
        await new Promise((r) => setTimeout(r, 80)); // let the logo/image paint
        const node = document.getElementById('invoice-print');
        if (! node) { this._printing = null; return null; }
        // html2canvas rasterises in screen space and captures BLANK from an element
        // parked at left:-10000px. Bring the sheet to on-screen coords (0,0) just for
        // the capture — it stays occluded behind the preview modal's backdrop, so no
        // visible flash — then clear the inline overrides back to the off-screen rule.
        node.style.left = '0'; node.style.top = '0'; node.style.zIndex = '1';
        await new Promise((r) => setTimeout(r, 60));
        let canvas;
        try {
            canvas = await html2canvas(node, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false });
        } finally {
            node.style.left = ''; node.style.top = ''; node.style.zIndex = '';
            this._printing = null;
        }
        const img = canvas.toDataURL('image/jpeg', 0.92);
        const pdf = new jsPDF({ unit: 'pt', format: 'a4' });
        const pw = pdf.internal.pageSize.getWidth();
        const ph = (canvas.height * pw) / canvas.width;
        const pageH = pdf.internal.pageSize.getHeight();
        let y = 0;
        pdf.addImage(img, 'JPEG', 0, 0, pw, ph); // first page
        let remaining = ph - pageH;
        while (remaining > 0) { pdf.addPage(); y -= pageH; pdf.addImage(img, 'JPEG', 0, y, pw, ph); remaining -= pageH; }
        return pdf.output('blob');
    },
    async _renderInvoicePdf(inv, label) {
        try {
            const blob = await this._invoicePdfBlob(inv);
            if (! blob) return null;
            const bytes = new Uint8Array(await blob.arrayBuffer());
            return await this._uploadFile(bytes, `${label}.pdf`, 'application/pdf');
        } catch (e) { this._printing = null; return null; }
    },
    openVersionPdf(v) { return this._openBlob(v?.pdf ? { ...v.pdf, mime: 'application/pdf' } : null); },

    addLine() { const rate = parseFloat(this.current._defaultRate) || 0; this.current.lines.push({ desc: '', qty: 1, unit: '', unitPrice: rate, vatRate: this._defaultVat() }); this.saveSoon(); },
    removeLine(i) { this.current.lines.splice(i, 1); if (! this.current.lines.length) this.addLine(); else this.saveSoon(); },

    // ---- Clockify CSV import → prefill line items ----
    // RFC 4180 parse (quoted fields, "" escapes, CRLF); returns rows of fields.
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

    // ---- Historical PDF invoice import (zero-knowledge, client-side) ----
    // Reads each PDF's text with pdf.js and turns it into a paid, single-net-line draft
    // (filename → number/date/customer, text → money). Everything stays in the browser;
    // the user reviews the parsed list before anything is saved.
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
                // Scrape the rendered PDF text (line-structure preserved via items' y-position)
                // to PREFILL the six fields. The PDF itself is the record (shown inline in the
                // review); the user confirms/fixes recipient/number/date/gross/rate/currency.
                let text = '';
                for (let i = 1; i <= doc.numPages; i++) {
                    const page = await doc.getPage(i);
                    const content = await page.getTextContent();
                    let lastY = null, prev = null;
                    for (const it of content.items) {
                        const y = it.transform ? it.transform[5] : null;
                        if (prev && lastY !== null && y !== null && Math.abs(y - lastY) > 3) {
                            text += '\n'; // new line (paragraph / table row)
                        } else if (prev && text && ! text.endsWith('\n')) {
                            // Same line: only a REAL horizontal gap yields a space — pdf.js splits a
                            // word into fragments ("W"+"artung"), so gluing every pair mangles words.
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
                const opts = { id: window.LLInvoicesStore.newId(), currency: this.company.currency || 'EUR', currentYear: new Date().getFullYear(), defaultVat };
                const sellerBlob = [this.company.name, this.company.address].filter(Boolean).join(' ');
                const draft = buildImportDraft(parseInvoiceFilename(file.name), parseInvoiceText(text, sellerBlob), opts);
                draft._file = file.name;
                draft._pdfBytes = bytes; // the original PDF, stored on import (GoBD)
                draft._url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' })); // inline preview in the review
                // Flag an invoice that already exists (same number, active) so the review warns
                // instead of silently creating a duplicate.
                draft._dupe = !! (draft.number && this.activeInvoices.some((i) => String(i.number || '').trim() === String(draft.number).trim()));
                if (draft._dupe) draft.selected = false;
                this.importReview.items.push(draft);
            } catch (e) { this.importReview.failed++; }
            this.importReview.done++;
        }
        this.importReview.running = false;
        // Sort by issue date so the review reads chronologically.
        this.importReview.items.sort((a, b) => (a.issueDate || '').localeCompare(b.issueDate || ''));
        this.importReview.idx = 0;
    },

    // ---- Review stepper (one invoice at a time: PDF inline + the six fields) ----
    get importCurrent() { const r = this.importReview; return r && ! r.running && ! r.saving && r.items[r.idx] ? r.items[r.idx] : null; },
    importGoto(i) { const r = this.importReview; if (! r) return; r.idx = Math.max(0, Math.min(r.items.length - 1, i)); },
    importPrev() { this.importGoto((this.importReview?.idx || 0) - 1); },
    importNext() { this.importGoto((this.importReview?.idx || 0) + 1); },
    // Partner name suggestions for the recipient field (datalist).
    get partnerNames() { return (this.partners || []).map((p) => p.name).filter(Boolean).sort((a, b) => a.localeCompare(b)); },
    // Styled-autocomplete option lists for the import review (replaces the native <datalist>).
    filteredPartnerNames(q) { const s = String(q || '').toLowerCase(); return this.partnerNames.filter((n) => ! s || n.toLowerCase().includes(s)).slice(0, 50); },
    filteredPartnerContacts(name, q) { const s = String(q || '').toLowerCase(); return this.partnerContactsFor(name).filter((c) => ! s || String(c.name || '').toLowerCase().includes(s)).slice(0, 50); },
    importVatOptions: [19, 16, 7, 0],
    // VAT-rate choices for the review select — the defaults plus the parsed rate if unusual.
    importVatChoices() { const s = new Set([19, 16, 7, 0]); const v = this.importCurrent?.vatRate; if (v != null) s.add(Number(v)); return [...s].sort((a, b) => b - a); },
    // Derived net/vat preview for the review row (gross + rate).
    importNet(row) { const g = parseFloat(row?.gross) || 0; const r = parseFloat(row?.vatRate) || 0; return this._round2(g / (1 + r / 100)); },
    importVat(row) { const g = parseFloat(row?.gross) || 0; return this._round2(g - this.importNet(row)); },
    _closeImport() {
        for (const it of (this.importReview?.items || [])) { if (it._url) { try { URL.revokeObjectURL(it._url); } catch (e) { /* */ } } }
        this.importReview = null;
    },

    // ZUGFeRD / Factur-X (EN 16931) XML export — built + downloaded client-side (ZK).
    downloadZugferd(inv) {
        const i = inv || this.current;
        if (! i) return;
        const xml = buildZugferdXml(i, this.company, this.computeTotals(i));
        saveBlobAs(new Blob([xml], { type: 'application/xml' }), zugferdFilename(i));
    },

    get importSelectedCount() { return (this.importReview?.items || []).filter((i) => i.selected).length; },
    cancelImport() { this._closeImport(); },
    _round2(n) { return Math.round(((Number(n) || 0) + Number.EPSILON) * 100) / 100; },

    // Commit the reviewed drafts as records. Each imported invoice keeps its ORIGINAL number
    // (historical) and its ORIGINAL PDF (the authoritative record, shown inline). The six
    // confirmed fields become one synthetic net line (net = gross / (1 + rate/100)) so totals,
    // stats and the VAT return work unchanged. The recipient lands in the business-partner DB.
    async confirmImport() {
        const picked = (this.importReview?.items || []).filter((i) => i.selected);
        if (! picked.length) { this._closeImport(); return; }
        await this._ensureContactsLoaded();
        // Persist each invoice as it lands (upload PDF → add record → flush), so a reload
        // mid-import keeps every finished one and never orphans an uploaded blob.
        this.importReview.saving = true;
        this.importReview.saved = 0;
        this.importReview.saveTotal = picked.length;
        let ok = 0;
        for (const draft of picked) {
            try {
                const rate = parseFloat(draft.vatRate) || 0;
                const gross = this._round2(parseFloat(draft.gross) || 0);
                // Derive net by subtracting the VAT OUT of the confirmed gross (net = gross - vat)
                // so the synthetic line reconstructs the exact printed gross, not gross±1 cent.
                const vat = this._round2(gross * rate / (100 + rate));
                const net = this._round2(gross - vat);
                const name = String(draft.recipient?.name || '').trim();
                // Recipient → business partner (find or create); enrich a bare partner.
                let partnerId = null;
                const attn = String(draft.contactPerson || '').trim();
                if (name) {
                    const partner = this._findOrCreatePartner(name);
                    partnerId = partner.id;
                    if (! partner.address && draft.recipient?.address) partner.address = draft.recipient.address;
                    if (! partner.vatId && draft.recipient?.vatId) partner.vatId = draft.recipient.vatId;
                    // Remember a newly-typed contact person on the partner.
                    if (attn) { partner.contacts ||= []; if (! partner.contacts.some((c) => String(c.name || '').trim().toLowerCase() === attn.toLowerCase())) partner.contacts.push({ id: window.LLInvoicesStore.newId(), name: attn, email: '', phone: '', role: '' }); }
                }
                const inv = {
                    id: draft.id, number: draft.number, status: 'paid',
                    issueDate: draft.issueDate || this._today(),
                    dueDate: draft.dueDate || draft.issueDate || this._today(),
                    currency: draft.currency || 'EUR', lang: 'de',
                    customer: { name, attn, address: draft.recipient?.address || '', email: '', vatId: draft.recipient?.vatId || '', contactId: null, partnerId },
                    lines: [{ desc: name || (labels.importSummaryLabel || 'Rechnung'), qty: 1, unit: '', unitPrice: net, vatRate: rate }],
                    gross, vatRate: rate, // the EXACT confirmed gross (computeTotals/invoiceTotals trust it)
                    note: '', footer: '', trashed: false, imported: true, minimal: true, updated: new Date().toISOString(),
                };
                // Carry the current-year sequence so the app's number counter advances.
                if (draft.seq != null) inv.seq = draft.seq;
                // Store the ORIGINAL PDF as a sealed blob (GoBD: the imported document is the
                // authoritative record — the app shows it, not a regenerated one).
                if (draft._pdfBytes) {
                    const pdf = await this._uploadPdf(draft._pdfBytes, draft._file || (draft.number + '.pdf'));
                    if (pdf) inv.pdf = pdf;
                }
                inv.totals = this.computeTotals(inv);
                this.invoices.unshift(inv);
                await window.LLInvoicesStore.flush(); // persist this one before the next
                this.reconcileBlobs(); // keep the just-uploaded pdf blob alive
                ok++;
            } catch (e) { /* skip this one, keep going */ }
            this.importReview.saved++;
        }
        window.llToast?.((labels.importDone || ':n invoices imported.').replace(':n', ok));
        this._closeImport();
    },

    async trash(inv) {
        if (! await this.$store.confirm.ask(labels.trashConfirm || 'Move this invoice to the trash?')) return;
        inv.trashed = new Date().toISOString(); this._save(); if (this.current === inv) this.backToList();
    },
    restore(inv) {
        // Refuse to restore when an ACTIVE invoice already carries this number (would create a
        // duplicate / break the gapless-unique series). Numberless drafts always restore.
        const num = String(inv.number || '').trim();
        if (num && this.activeInvoices.some((i) => i !== inv && String(i.number || '').trim() === num)) {
            window.llToast?.((labels.restore_dupe || 'An active invoice with number :n already exists.').replace(':n', num));
            return;
        }
        inv.trashed = false; this._save();
    },
    async remove(inv) {
        if (! await this.$store.confirm.ask(labels.deleteConfirm || 'Delete this invoice permanently?')) return;
        const i = this.invoices.indexOf(inv);
        if (i >= 0) this.invoices.splice(i, 1);
        this._save();
        if (this.current === inv) this.backToList();
    },

    // ---- Totals (net, VAT grouped by rate, gross) ----
    lineNet(l) { return (parseFloat(l.qty) || 0) * (parseFloat(l.unitPrice) || 0); },
    computeTotals(inv) {
        const t = { net: 0, vatByRate: {}, vat: 0, gross: 0 };
        if (! inv) return t;
        // Imported invoices carry the EXACT printed gross — trust it (derive net/VAT out of it)
        // so the value the user confirmed in the import review is never shifted by a rounding
        // round-trip through the synthetic net line (e.g. 70,93 becoming 70,94).
        if (inv.imported && Number.isFinite(Number(inv.gross))) {
            const rate = parseFloat(inv.vatRate) || 0;
            t.gross = this._round2(Number(inv.gross));
            t.vat = this._round2(t.gross * rate / (100 + rate));
            t.net = this._round2(t.gross - t.vat);
            t.vatByRate[rate] = t.vat;
            return t;
        }
        // Optional invoice-level discount (Rabatt): a percentage or a fixed amount off the
        // net subtotal. Applied as a fraction to EACH line's net so the VAT-per-rate split
        // stays correct. `subtotal` = before discount, `net` = after.
        let subtotal = 0;
        for (const l of inv.lines || []) subtotal += this.lineNet(l);
        const disc = inv.discount || null;
        let frac = 0;
        if (disc && Number(disc.value) > 0 && subtotal > 0) {
            frac = disc.type === 'amount' ? Math.min(1, Number(disc.value) / subtotal) : Math.min(1, Number(disc.value) / 100);
        }
        for (const l of inv.lines || []) {
            const net = this.lineNet(l) * (1 - frac);
            const rate = parseFloat(l.vatRate) || 0;
            t.net += net;
            const v = net * rate / 100;
            t.vatByRate[rate] = (t.vatByRate[rate] || 0) + v;
            t.vat += v;
        }
        t.subtotal = this._round2(subtotal);
        t.discountAmount = this._round2(subtotal * frac);
        t.net = this._round2(t.net);
        t.vat = this._round2(t.vat);
        t.gross = this._round2(t.net + t.vat);
        // Skonto (early-payment discount) — informational: amount deductible + due date.
        const sk = inv.skonto || null;
        if (sk && Number(sk.percent) > 0) {
            t.skontoPercent = Number(sk.percent);
            t.skontoDays = Number(sk.days) || 0;
            t.skontoAmount = this._round2(t.gross * Number(sk.percent) / 100);
            t.skontoNet = this._round2(t.gross - t.skontoAmount);
            t.skontoDate = this._addDays(inv.issueDate || this._today(), t.skontoDays);
        }
        return t;
    },
    fmtMoney(n, currency, lang) {
        const cur = currency || this.current?.currency || this.company.currency || 'EUR';
        const loc = (lang || this.current?.lang || 'de') === 'en' ? 'en' : 'de';
        try { return new Intl.NumberFormat(loc, { style: 'currency', currency: cur }).format(n || 0); }
        catch (e) { return (n || 0).toFixed(2) + ' ' + cur; }
    },
    // Print-sheet label in the invoice's own language (falls back to German).
    pl(key) {
        const lang = this._printing?.lang || 'de';
        const set = this._labelsByLang[lang] || this._labelsByLang.de || {};
        return set[key] || key;
    },
    // Currencies offered per invoice.
    currencyOptions: ['EUR', 'USD', 'CHF'],
    // Chosen print template (modern | elegant | schlicht).
    get tpl() { const t = this.company.template || 'editorial'; return t === 'schlicht' ? 'elegant' : t; },
    vatRatesOf(inv) { return Object.keys(this.computeTotals(inv).vatByRate).map(Number).sort((a, b) => a - b); },
    // Locale-formatted quantity (German uses a decimal comma).
    fmtQty(n, lang) {
        const loc = (lang || this.current?.lang || 'de') === 'en' ? 'en' : 'de';
        try { return new Intl.NumberFormat(loc, { maximumFractionDigits: 2 }).format(parseFloat(n) || 0); }
        catch (e) { return String(n ?? ''); }
    },

    // ---- Customer picker (bills from the zero-knowledge Geschäftspartner) ----
    customerPicker: false,
    custQuery: '',
    openCustomerPicker() {
        this.customerPicker = true;
        this.custQuery = '';
    },
    closeCustomerPicker() { this.customerPicker = false; },
    _custName(p) { return String(p?.name || '').trim(); },
    _custContact(p) { const c = (p?.contacts || [])[0]; return c ? String(c.name || '').trim() : ''; },
    custSuggestions() {
        const q = this.custQuery.trim().toLowerCase();
        let list = (this.partners || []);
        if (q) list = list.filter((p) => this._custName(p).toLowerCase().includes(q) || this._custContact(p).toLowerCase().includes(q) || String(p.email || '').toLowerCase().includes(q) || String(p.category || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => this._custName(a).localeCompare(this._custName(b)));
    },
    pickCustomer(p) {
        // A partner bills to its name; the first contact person becomes the Attn line.
        const attn = this._custContact(p);
        this.current.customer = {
            name: this._custName(p),
            attn,
            address: String(p.address || ''),
            email: String(p.invoiceEmail || p.email || (p.contacts || [])[0]?.email || ''),
            vatId: String(p.vatId || ''),
            contactId: null,
            partnerId: p.id,
        };
        // A partner may carry a default hourly rate + currency. Apply them to the
        // invoice: set the currency, remember the rate for new lines, and back-fill it
        // onto any existing line whose unit price is still empty (0).
        const rate = parseFloat(p.hourlyRate);
        if (Number.isFinite(rate) && rate > 0) {
            this.current._defaultRate = rate;
            for (const l of (this.current.lines || [])) { if (! (parseFloat(l.unitPrice) > 0)) l.unitPrice = rate; }
        }
        if (p.currency) this.current.currency = p.currency;
        this.customerPicker = false;
        this.saveSoon();
    },
    clearCustomer() { this.current.customer = { name: '', attn: '', address: '', email: '', vatId: '', contactId: null, partnerId: null }; this.saveSoon(); },

    // ---- Finalize / status ----
    // Render a number template. YYYY/YY/MM/DD from the issue date, and a run of
    // N's becomes the zero-padded sequence (NNNN → 0042). Longer tokens first.
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
    // Assign a GAPLESS, unique invoice number (GoBD). The seq is stored on the invoice
    // so future numbering derives from real data (shared/invoice-numbering), not just a
    // mergeable scalar; the scalar is kept as a floor hint.
    _assignNumber(inv) {
        const floor = parseInt(this.company.next_number, 10) || 1;
        const year = String(inv.issueDate || this._today()).slice(0, 4);
        const seq = nextSeqForYear(this.invoices, year, floor); // per-year: restarts each year
        inv.seq = seq; // the sequence is stored on the invoice; no mergeable scalar
        inv.number = this._formatNumber(this.company.number_format, seq, inv.issueDate);
    },
    // Pull invoices issued on other devices before assigning a number, so two devices
    // never mint the same number. Best-effort (offline uses the in-memory set); the
    // sharded store rebase-merges records in place so bound refs stay live.
    async _refresh() {
        await window.LLInvoicesStore.refresh();
        this.invoices = window.LLInvoicesStore.data.invoices;
    },
    // Numbers assigned to more than one ACTIVE invoice in the SAME year — a GoBD
    // violation the owner must fix. Keyed by year+number, so the same bare number in
    // two different years never false-alarms. Imported invoices ARE included: a
    // re-import (or a genuinely duplicated number) is exactly the "doppelte Rechnung"
    // to catch; the per-year key keeps legitimate archival numbers from clashing.
    get duplicateNumbers() { return dupNumbers(this.activeInvoices); },
    // An invoice counts as "linked" if it carries a payment link OR any bank transaction points
    // at it (by id or — after a re-import with a fresh id — by number). Robust to stale ids.
    isInvoiceLinked(inv) {
        if (! inv) return false;
        if (inv.paymentTxId) return true;
        const num = String(inv.number || '').trim();
        return (this.transactions || []).some((t) => t.invoiceId === inv.id || (num && String(t.invoiceNumber || '').trim() === num));
    },
    // Gaps in the per-year numbering (GoBD: gapless). Includes imported historical invoices —
    // uploading 8 and 10 flags the missing 9. Display caps at 40 to keep the banner sane.
    get gapNumbers() { return gapNumbers(this.activeInvoices).slice(0, 40); },

    // ---- Numbering cycle (per year) ----
    get currentYear() { return String(new Date().getFullYear()); },
    // Invoices dated in the current year — once any exist, the numbering format/counter
    // is locked (GoBD: no changing the running sequence mid-year) until the cycle is reset.
    get currentYearInvoices() { return invoicesInYear(this.invoices, this.currentYear); },
    get numberingLocked() { return this.currentYearInvoices.length > 0; },
    // The number the next invoice issued this year would receive (preview).
    get nextNumberPreview() {
        const floor = parseInt(this.company.next_number, 10) || 1;
        const seq = nextSeqForYear(this.invoices, this.currentYear, floor);
        return this._formatNumber(this.company.number_format, seq, this._today());
    },
    // Reset the current year's invoice cycle: DELETE every invoice dated this year so the
    // numbering legitimately restarts at 1 (GoBD — you may only restart by removing the
    // records). Irreversible; type-to-confirm the year. Past years are untouched.
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
            const i = this.invoices.indexOf(inv);
            if (i >= 0) this.invoices.splice(i, 1);
        }
        if (this.current && invoiceYear(this.current) === year) this.backToList();
        this._save();
        this.reconcileBlobs(); // release their original-PDF blobs (grace-gated sweep)
        window.llToast?.((labels.cycle_reset_done || 'Cycle :year reset.').replace(':year', year));
    },

    // Keep the imported original-PDF blobs alive against the daily orphan sweep — the
    // live-set is every invoice's pdf blob PLUS the sharded record/collection refs (§11).
    reconcileBlobs() {
        if (! config.reconcileUrl) return;
        // Never reconcile from a DEGRADED (missing-shard) load: the record set is
        // incomplete, so its blob live-set is too — reconciling would prune the PDFs
        // of the records the missing shard held. The store is frozen while degraded;
        // reconcile resumes after a clean reload.
        if (window.LLInvoicesStore.degraded) return;
        const blobs = [];
        for (const inv of (this.invoices || [])) {
            if (inv.pdf?.blob) blobs.push(inv.pdf.blob);
            for (const v of (inv.versions || [])) if (v.pdf?.blob) blobs.push(v.pdf.blob); // version PDFs
        }
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (r.blob) blobs.push(r.blob);
        for (const ref of window.LLInvoicesStore.shardRefs()) blobs.push(ref);
        postForm(config.reconcileUrl, { blobs: [...new Set(blobs)], allow_empty: 1 }).catch(() => {});
    },

    // Encrypt + upload a file as a sealed blob (ZK). Returns { blob, key, name, mime }
    // or null on failure.
    async _uploadFile(bytes, name, mime) {
        try {
            const enc = window.Vault.encryptContent(bytes, { name, mime });
            const cipher = new File([await padBlob(enc.blob)], 'blob.enc', { type: 'application/octet-stream' });
            const fd = new FormData();
            fd.append('file', cipher);
            const res = await fetch(config.uploadUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            if (! res.ok) return null;
            return { blob: (await res.json()).id, key: enc.encFileKey, name, mime };
        } catch (e) { return null; }
    },
    _uploadPdf(bytes, name) { return this._uploadFile(bytes, name, 'application/pdf'); },

    // Open a stored sealed file (invoice original / receipt) — decrypt client-side.
    // Decrypt a stored PDF/image blob client-side and show it in the in-app preview modal
    // (not a new tab). Reuses the receipt Quick-Look modal.
    async _openBlob(ref) {
        if (! ref?.blob) return;
        try {
            const buf = await fetchBlobBuffer(`${config.rawBase}/${ref.blob}`);
            const plain = window.Vault.decryptFile(buf, ref.key);
            const url = URL.createObjectURL(new Blob([plain], { type: ref.mime || 'application/octet-stream' }));
            this.closeReceiptPreview(); // revoke any previous url first
            this.receiptPreview = { url, mime: ref.mime || 'application/pdf', name: ref.name || 'PDF' };
        } catch (e) { window.llToast?.(labels.downloadFailed || 'Could not open file.'); }
    },
    openOriginalPdf(inv) { return this._openBlob(inv?.pdf ? { ...inv.pdf, mime: 'application/pdf' } : null); },
    async finalize(inv) {
        let i = inv || this.current;
        if (! i) return;
        if (! i.number) {
            const id = i.id;
            await this._refresh();            // observe other devices' invoices first
            i = this.invoices.find((x) => x.id === id) || i; // re-find after the in-place merge
            if (this.current && this.current.id === id) this.current = i;
            this._assignNumber(i);
        }
        if (i.status === 'draft') i.status = 'sent';
        i.totals = this.computeTotals(i); // freeze
        await this._lockCommit(i, labels.version_finalized || 'Finalized');
        // Right after finalising, offer to file the invoice in Paperless (opens the
        // same transfer dialog as Files), pre-filled — if Paperless is configured.
        if (this.$store.paperless?.configured) this.sendInvoiceToPaperless(i);
    },
    // Send an invoice to Paperless: generate its PDF client-side (or use the original
    // for imported invoices), open the shared transfer dialog pre-filled (title =
    // number + customer, correspondent = customer, date = issue date). Leaves ZK.
    async sendInvoiceToPaperless(inv) {
        const i = inv || this.current; if (! i) return;
        const store = this.$store.paperless; if (! store || ! store.configured) return;
        const num = i.number || (labels.status_draft || 'Draft');
        const title = (labels.print_title || 'Invoice') + ' ' + num + (i.customer?.name ? ' – ' + i.customer.name : '');
        const fname = (i.number ? String(i.number).replace(/[^\w.-]+/g, '_') : 'rechnung') + '.pdf';
        store.begin(fname, { title, created: i.issueDate || undefined }, { context: { source: 'invoice' } });
        if (i.customer?.name) store.corrQuery = i.customer.name;
        try {
            let blob;
            if (i.imported && i.pdf?.blob) {
                const buf = await fetchBlobBuffer(`${config.rawBase}/${i.pdf.blob}`);
                blob = new Blob([window.Vault.decryptFile(buf, i.pdf.key)], { type: 'application/pdf' });
            } else {
                blob = await this._invoicePdfBlob(i);
            }
            if (! blob) throw new Error('render failed');
            store.setFile(blob);
        } catch (e) { store.fail(labels.downloadFailed || 'Could not open file.'); }
    },

    // ---- Send invoice by e-mail (dedicated invoice SMTP, server-side send) ----
    mailOpen: false, mailBusy: false, mailTo: '', mailSubject: '', mailBody: '', _mailInv: null, _mailMode: 'invoice',
    _mailRecipient(i) {
        if (i.customer?.email) return i.customer.email;
        const p = (this.partners || []).find((x) => x.id === i.customer?.partnerId);
        return p?.invoiceEmail || p?.email || '';
    },
    // Fill the mail placeholders from an invoice: :number :customer :company :date
    // :due :total :currency. Used for both subject (plain) and body (HTML).
    _fillPlaceholders(tpl, i, escape = false) {
        const num = i.number || (labels.status_draft || 'Draft');
        const esc = (v) => (escape ? String(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])) : String(v));
        const map = {
            ':number': esc(num),
            ':customer': esc(i.customer?.name || ''),
            ':company': esc(this.company.name || ''),
            ':date': esc(i.issueDate || ''),
            ':due': esc(i.dueDate || ''),
            ':total': esc(this.fmtMoney(this.computeTotals(i).gross, i.currency, document.documentElement.lang)),
            ':currency': esc(i.currency || this.company.currency || ''),
        };
        return String(tpl || '').replace(/:(number|customer|company|date|due|total|currency)/g, (m) => map[m] ?? m);
    },
    openMailInvoice(inv) {
        const i = inv || this.current; if (! i) return;
        if (! this.company.mail_enabled) { window.llToast(labels.mail_not_configured || 'Configure the invoice mail server in settings.'); return; }
        this._mailInv = i;
        this.mailTo = this._mailRecipient(i);
        this.mailSubject = this._fillPlaceholders(this.company.mail_subject || ((labels.print_title || 'Invoice') + ' :number'), i);
        // Body is the stored HTML template (WYSIWYG) with placeholders filled; the
        // signature is appended server-side. Plain default falls back to a paragraph.
        const bodyTpl = this.company.mail_body || ('<p>' + (labels.mail_body_default || 'Please find attached invoice :number.') + '</p>');
        this.mailBody = this._fillPlaceholders(bodyTpl, i, true);
        this.mailOpen = true;
        this.$nextTick(() => { if (this.$refs.mailBodyEl) this.$refs.mailBodyEl.innerHTML = this.mailBody; });
    },
    // ---- Overdue / aging + dunning (Mahnung) ----
    _daysBetween(a, b) { const d = (new Date(b) - new Date(a)) / 86400000; return Number.isFinite(d) ? Math.floor(d) : 0; },
    isOverdue(inv) { return !! inv && inv.status === 'sent' && ! inv.imported && inv.dueDate && inv.dueDate < this._today(); },
    daysOverdue(inv) { return this.isOverdue(inv) ? this._daysBetween(inv.dueDate, this._today()) : 0; },
    get overdueInvoices() { return this.activeInvoices.filter((i) => this.isOverdue(i)); },
    get overdueTotal() { return this.overdueInvoices.reduce((s, i) => s + (this.computeTotals(i).gross || 0), 0); },
    // Aging buckets over the outstanding (sent, unpaid) invoices.
    get aging() {
        const b = { current: 0, d30: 0, d60: 0, d90: 0, d90p: 0 };
        for (const i of this.activeInvoices) {
            if (i.status !== 'sent' || i.imported) continue;
            const g = this.computeTotals(i).gross || 0;
            const od = this.isOverdue(i) ? this.daysOverdue(i) : 0;
            if (od <= 0) b.current += g; else if (od <= 30) b.d30 += g; else if (od <= 60) b.d60 += g; else if (od <= 90) b.d90 += g; else b.d90p += g;
        }
        return b;
    },
    reminderCount(inv) { return (inv?.reminders || []).length; },
    lastReminderAt(inv) { const r = inv?.reminders || []; return r.length ? r[r.length - 1].at : ''; },
    // Open the mail dialog pre-filled as a payment reminder (dunning) instead of a
    // plain invoice send. Placeholders add :days (days overdue) + :level.
    openReminderMail(inv) {
        const i = inv || this.current; if (! i) return;
        if (! this.company.mail_enabled) { window.llToast(labels.mail_not_configured || 'Configure the invoice mail server in settings.'); return; }
        const level = this.reminderCount(i) + 1;
        const days = this.daysOverdue(i);
        const fill = (t) => this._fillPlaceholders(String(t || '').replace(/:days/g, days).replace(/:level/g, level), i, true);
        this._mailInv = i;
        this._mailMode = 'reminder';
        this.mailTo = this._mailRecipient(i);
        this.mailSubject = this._fillPlaceholders((labels.reminder_subject || 'Payment reminder — invoice :number').replace(/:level/g, level).replace(/:days/g, days), i);
        this.mailBody = fill('<p>' + (labels.reminder_body || 'Invoice :number is :days days overdue. Please arrange payment.') + '</p>');
        this.mailOpen = true;
        this.$nextTick(() => { if (this.$refs.mailBodyEl) this.$refs.mailBodyEl.innerHTML = this.mailBody; });
    },
    closeMailInvoice() { this.mailOpen = false; this._mailInv = null; this._mailMode = 'invoice'; },
    async confirmSendMail() {
        const i = this._mailInv; if (! i || this.mailBusy) return;
        if (! String(this.mailTo || '').includes('@')) { window.llToast(labels.mail_bad_recipient || 'Enter a valid recipient.'); return; }
        this.mailBusy = true;
        try {
            let blob;
            if (i.imported && i.pdf?.blob) {
                const buf = await fetchBlobBuffer(`${config.rawBase}/${i.pdf.blob}`);
                blob = new Blob([window.Vault.decryptFile(buf, i.pdf.key)], { type: 'application/pdf' });
            } else {
                blob = await this._invoicePdfBlob(i);
            }
            if (! blob) throw new Error('render failed');
            const fd = new FormData();
            fd.append('to', this.mailTo.trim());
            fd.append('subject', this.mailSubject || '');
            fd.append('body', this.mailBody || '');
            fd.append('pdf', new File([blob], (i.number ? String(i.number).replace(/[^\w.-]+/g, '_') : 'rechnung') + '.pdf', { type: 'application/pdf' }));
            const res = await fetch(config.sendUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': jsonHeaders()['X-CSRF-TOKEN'] }, body: fd });
            if (res.ok) {
                // Record a sent reminder on the invoice (dunning history), re-resolving
                // the live record by id in case a background 409 rebase detached it.
                if (this._mailMode === 'reminder') {
                    const live = this.invoices.find((x) => x.id === i.id) || i;
                    (live.reminders ||= []).push({ at: this._today(), days: this.daysOverdue(live), level: this.reminderCount(live) + 1 });
                    this._save();
                }
                window.llToast(this._mailMode === 'reminder' ? (labels.reminder_sent || 'Reminder sent.') : (labels.mail_sent || 'Invoice sent.'));
                this.closeMailInvoice();
            }
            else if (res.status === 501) { window.llToast(labels.mail_not_configured || 'Configure the invoice mail server in settings.'); }
            else { window.llToast(labels.mail_failed || 'Could not send the invoice.'); }
        } catch (e) { window.llToast(labels.mail_failed || 'Could not send the invoice.'); }
        this.mailBusy = false;
    },
    // ---- Credit note / Storno (Gutschrift) ----
    isCredit(inv) { return (inv || this.current)?.type === 'credit'; },
    docTitle(inv) { return this.pl((inv || this._printing)?.type === 'credit' ? 'print_title_credit' : 'print_title'); },
    // Create a GoBD-correct reversal of a finalized invoice: a new draft credit note
    // with NEGATED line prices (subtracts from revenue/EÜR), referencing the original.
    // It gets its own number from the same series on finalize.
    createCreditNote(inv) {
        const src = inv || this.current; if (! src) return;
        const c = {
            id: window.LLInvoicesStore.newId(),
            number: null, status: 'draft', type: 'credit',
            creditOf: src.number || '', creditOfId: src.id,
            issueDate: this._today(), dueDate: this._today(),
            currency: src.currency || (this.company.currency || 'EUR'), lang: src.lang || 'de',
            customer: { ...(src.customer || {}) },
            lines: (src.lines || []).map((l) => ({ ...l, unitPrice: -Math.abs(Number(l.unitPrice) || 0) })),
            note: (labels.credit_ref || 'Credit note for invoice :number').replace(':number', src.number || ''),
            footer: src.footer || this.company.footer_text || '',
            trashed: false, updated: new Date().toISOString(),
        };
        this.invoices.unshift(c); this._save(); this.open(c);
    },
    async markPaid(inv) { inv.status = 'paid'; await this._lockCommit(inv, labels.version_paid || 'Marked paid'); },
    async markSent(inv) {
        let i = inv;
        if (! i.number) {
            const id = i.id;
            await this._refresh();
            i = this.invoices.find((x) => x.id === id) || i;
            if (this.current && this.current.id === id) this.current = i;
            this._assignNumber(i);
        }
        i.status = 'sent';
        await this._lockCommit(i, labels.version_sent || 'Sent');
    },
    // A lock-point transition (finalize/sent/paid) freezes the state as a version (online →
    // with a generated PDF) and persists. Reason is automatic (no prompt) for these.
    async _lockCommit(inv, reason) {
        this.pdfBusy = true;
        try { await this._commitVersion(inv, reason); } catch (e) { /* keep going even if PDF fails */ }
        this.pdfBusy = false;
        if (this.current && this.current.id === inv.id) { this.dirty = false; this._lockBaseline = JSON.stringify(this._editable(inv)); }
        this._save();
        this.reconcileBlobs();
    },
    statusLabel(s) { return ({ draft: labels.statusDraft, sent: labels.statusSent, paid: labels.statusPaid })[s] || s; },

    // ---- Print / PDF (client-side, zero-knowledge) ----
    async printInvoice(inv) {
        const i = inv || this.current;
        // Imported invoices show their ORIGINAL PDF, never a regenerated sheet (GoBD).
        if (i?.imported && i?.pdf?.blob) { this.openOriginalPdf(i); return; }
        this.printQr = await this._epcQr(i);
        this._printing = i;
        this.$nextTick(() => { window.print(); });
    },

    // ---- EPC069-12 payment QR (GiroCode) ----
    // GiroCode data-URL for an invoice: SEPA credit transfer to the company IBAN, amount =
    // gross, remittance = invoice number. EUR/SEPA only (canEpcQr gates). Pure client-side.
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
            reference: inv.number ? (inv.lang === 'de' ? 'Rechnung ' : 'Invoice ') + inv.number : '',
        });
        if (! payload) return '';
        try { const mod = await import('qrcode'); const QR = mod.default ?? mod; return await QR.toDataURL(payload, { margin: 0, width: 260 }); } catch (e) { return ''; }
    },
});
