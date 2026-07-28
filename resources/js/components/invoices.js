// invoices component. Extracted from app.js.
import { zkModule, bootStore } from '../shared/zk-module';
import { nextSeqForYear, duplicateNumbers as dupNumbers, invoicesInYear, invoiceYear } from '../shared/invoice-numbering';
import { parseInvoiceFilename, parseInvoiceText, buildImportedInvoice, buildInvoiceFromXml } from '../shared/invoice-pdf-import';
import { parseEInvoiceXml, looksLikeEInvoiceXml } from '../shared/einvoice-xml';
import { contactNameParts, contactDisplayName } from '../shared/contact-utils';
import { jsonHeaders, postForm } from '../shared/api';
import { saveBlobAs } from '../shared/dom';
import { buildZugferdXml, zugferdFilename } from '../shared/zugferd';
import { padBlob } from '../shared/padme';
import { fetchBlobBuffer } from '../shared/blob-io';
import { vatReturn, revenueByCustomer, monthlyRevenue, yearKpis, activeYears, accountVatSummary } from '../shared/finance-stats';
import { matchInvoice } from '../shared/invoice-match';
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
    ...zkModule({ store: 'invoices', instance: () => window.LLInvoicesStore, afterLoad: (self, ms) => migrateInvoicesFromMonolith(ms), map: { invoices: 'invoices', paymentMethods: 'paymentMethods', transactions: 'transactions' }, onLock: (self) => { self.view = 'list'; self.current = null; self.payEditing = null; self.payView = 'list'; self.payAccount = null; self.stmt = null; } }),

    company: config.company || {},
    _labelsByLang: config.labelsByLang || {},
    invoices: [],
    view: 'list',        // 'list' | 'edit'
    current: null,       // the invoice being edited
    filterStatus: '',    // '' | draft | sent | paid
    _printing: null,     // invoice rendered into the hidden print sheet
    // Finance section: the page is a "Finanzen" hub with tabs. Invoices are one tab.
    section: 'dashboard', // 'dashboard' | 'receipts' | 'invoices' | 'payments' | 'stats'

    async init() {
        const h = (location.hash || '').replace('#', '');
        if (['dashboard', 'receipts', 'invoices', 'payments', 'stats'].includes(h)) this.section = h;
        await this._initZk();
        if (this.state === 'ready') { this._ensureReceiptIds(); this.reconcileBlobs(); }
    },

    setSection(s) {
        this.section = s;
        try { history.replaceState(null, '', '#' + s); } catch (e) { /* ignore */ }
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
        this.rematchAll(true); // auto-link payments to invoices on open (silent)
        try { window.scrollTo({ top: 0 }); } catch (e) { /* */ }
    },
    backToPayments() { this.payView = 'list'; this.payAccount = null; },
    // Transactions of the open account, newest first.
    get accountTx() {
        const id = this.payAccount?.id;
        return (this.transactions || []).filter((t) => t.account === id).sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    },
    // ---- Pagination for the account's transaction list (10 / 25 per page) ----
    txPage: 1,
    txPerPage: 25,
    get txPageCount() { return Math.max(1, Math.ceil(this.accountTx.length / this.txPerPage)); },
    get pagedAccountTx() {
        const start = (this.txPage - 1) * this.txPerPage;
        return this.accountTx.slice(start, start + this.txPerPage);
    },
    setTxPerPage(n) { this.txPerPage = n; this.txPage = 1; },
    txGoto(p) { this.txPage = Math.min(this.txPageCount, Math.max(1, p)); },
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
    get unlinkedIncomeCount() { return this.accountTx.filter((t) => t.amount > 0 && ! t.invoiceId).length; },
    // How many documentable bookings (non-private) still have no document attached.
    get missingReceipts() { return this.documentableTx.filter((t) => ! (t.receipts && t.receipts.length)).length; },
    receiptCount(tx) { return (tx.receipts || []).length; },
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
    receiptDoc: null,     // the { r, tx } currently edited in the detail modal
    receiptTagInput: '',
    _receiptContacts: [],
    // Give every stored receipt a stable id (once) so it can be edited/re-linked.
    _ensureReceiptIds() {
        let changed = false;
        for (const tx of (this.transactions || [])) {
            for (const r of (tx.receipts || [])) { if (! r.id) { r.id = window.LLInvoicesStore.newId(); changed = true; } }
        }
        if (changed) this._save();
    },
    // Every receipt with its owning booking, newest booking first.
    get allReceipts() {
        const out = [];
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) out.push({ r, tx });
        return out.sort((a, b) => (b.tx.date || '').localeCompare(a.tx.date || ''));
    },
    get filteredReceipts() {
        const q = this.receiptQuery.trim().toLowerCase();
        if (! q) return this.allReceipts;
        return this.allReceipts.filter(({ r, tx }) =>
            (r.name || '').toLowerCase().includes(q) || (r.note || '').toLowerCase().includes(q) ||
            (r.category || '').toLowerCase().includes(q) || (r.tags || []).join(' ').toLowerCase().includes(q) ||
            (tx.counterparty || '').toLowerCase().includes(q) || (tx.purpose || '').toLowerCase().includes(q));
    },
    async openReceiptDoc(doc) {
        this.receiptDoc = doc;
        this.receiptTagInput = (doc.r.tags || []).join(', ');
        try { if (await bootStore(this.$store, 'contacts')) this._receiptContacts = (window.LLModuleStore.contacts.data.contacts || []).filter((c) => ! c.trashed); }
        catch (e) { /* leave empty */ }
    },
    closeReceiptDoc() { this.receiptDoc = null; },
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
        this.receiptDoc.r.tags = this.receiptTagInput.split(',').map((t) => t.trim()).filter(Boolean);
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
        if (q) list = list.filter((t) => (t.counterparty || '').toLowerCase().includes(q) || (t.purpose || '').toLowerCase().includes(q) || (t.date || '').includes(q));
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
    openReceipts(tx) { this.receiptTx = tx; },
    closeReceipts() { this.receiptTx = null; },
    async uploadReceipts(fileList) {
        const tx = this.receiptTx;
        if (! tx) return;
        const files = [...(fileList || [])];
        if (! files.length) return;
        this.receiptBusy = true;
        tx.receipts = tx.receipts || [];
        let ok = 0;
        for (const file of files) {
            try {
                const bytes = new Uint8Array(await file.arrayBuffer());
                const up = await this._uploadFile(bytes, file.name, file.type || 'application/octet-stream');
                if (up) { tx.receipts.push(up); ok++; }
            } catch (e) { /* skip this file */ }
        }
        this.receiptBusy = false;
        if (ok) { this._save(); this.reconcileBlobs(); }
        else window.llToast?.(labels.receipt_failed || 'Upload failed.');
    },
    // Quick-look a receipt/invoice in a modal (decrypt client-side). App invoices with no
    // stored PDF open the invoice view instead.
    receiptPreview: null, // { url, mime, name }
    async openReceipt(r) {
        if (r.kind === 'invoice' && ! r.blob) return this.openInvoiceById(r.invoiceId);
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
        const i = (tx.receipts || []).indexOf(r);
        if (i >= 0) tx.receipts.splice(i, 1);
        this._save();
        this.reconcileBlobs();
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
            const rec = { kind: 'invoice', invoiceId: inv.id, invoiceNumber: inv.number, name: (labels.invoice_word || 'Invoice') + ' ' + inv.number, locked: true };
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
        let n = 0;
        for (const tx of this.accountTx) {
            if (tx.amount > 0 && ! tx.invoiceId) {
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
    // Open the invoice a locked receipt / booking refers to.
    openInvoiceById(id) {
        const inv = (this.invoices || []).find((i) => i.id === id);
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

    // ---- VAT category per booking (for the USt calculation) ----
    vatCats: VAT_CATS,
    vatCatLabel(cat) { return cat ? (labels['vatcat_' + cat] || cat) : (labels.vatcat_none || '—'); },
    setVatCat(tx, cat) { tx.vatCat = cat; this._save(); },
    // Bookings still needing a VAT category (excludes private/decided ones).
    get accountVat() { return accountVatSummary(this.accountTx); },
    cancelStatement() { this.stmt = null; },

    // ---- Derived ----
    get activeInvoices() { return (this.invoices || []).filter((i) => ! i.trashed); },
    get filtered() {
        const q = this.query.trim().toLowerCase();
        let list = this.activeInvoices;
        if (this.filterStatus) list = list.filter((i) => i.status === this.filterStatus);
        if (q) list = list.filter((i) => (i.number || '').toLowerCase().includes(q) || (i.customer?.name || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => (b.issueDate || '').localeCompare(a.issueDate || '') || (b.number || '').localeCompare(a.number || ''));
    },
    get totals() { return this.computeTotals(this.current); },

    _today() { return new Date().toISOString().slice(0, 10); },
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
        inv.customer.attn ??= '';
        this.current = inv;
        this.view = 'edit';
    },
    backToList() { this.view = 'list'; this.current = null; },
    saveSoon() { if (this.current) this.current.updated = new Date().toISOString(); this._save(); },

    addLine() { this.current.lines.push({ desc: '', qty: 1, unit: '', unitPrice: 0, vatRate: this._defaultVat() }); this.saveSoon(); },
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
        this.importReview = { items: [], total: files.length, done: 0, failed: 0, running: true };
        let pdfjs;
        try {
            pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
        } catch (e) { this.importReview = null; window.llToast?.(labels.importFailed || 'PDF engine failed to load.'); return; }
        const summaryLabel = labels.importSummaryLabel || 'Rechnungsbetrag';
        for (const file of files) {
            try {
                const bytes = new Uint8Array(await file.arrayBuffer());
                const doc = await pdfjs.getDocument({ data: bytes.slice(0), isEvalSupported: false }).promise;
                const opts = { id: window.LLInvoicesStore.newId(), currency: this.company.currency || 'EUR', summaryLabel, currentYear: new Date().getFullYear() };

                // PREFERRED path: modern invoices embed a structured e-invoice XML
                // (ZUGFeRD/Factur-X CII or XRechnung UBL). Reading it is 100% reliable —
                // real line items, buyer, taxes, totals — no text scraping.
                let draft = null;
                try {
                    const att = await doc.getAttachments();
                    for (const key of Object.keys(att || {})) {
                        const xml = new TextDecoder().decode(att[key].content);
                        if (! looksLikeEInvoiceXml(xml)) continue;
                        const parsed = parseEInvoiceXml(xml);
                        if (parsed) { draft = buildInvoiceFromXml(parsed, opts); draft._source = 'xml'; break; }
                    }
                } catch (e) { /* no attachments — fall through to text parsing */ }

                // FALLBACK: scrape the rendered PDF text (line-structure preserved via the
                // items' y-position) for PDFs without an embedded XML.
                if (! draft) {
                    let text = '';
                    for (let i = 1; i <= doc.numPages; i++) {
                        const page = await doc.getPage(i);
                        const content = await page.getTextContent();
                        let lastY = null;
                        for (const it of content.items) {
                            const y = it.transform ? it.transform[5] : null;
                            if (lastY !== null && y !== null && Math.abs(y - lastY) > 3) text += '\n';
                            else if (text && ! text.endsWith('\n')) text += ' ';
                            text += it.str;
                            lastY = y;
                        }
                        text += '\n';
                    }
                    draft = buildImportedInvoice(parseInvoiceFilename(file.name), parseInvoiceText(text), opts);
                    draft._source = 'text';
                }
                draft.selected = true;
                draft._file = file.name;
                draft._pdfBytes = bytes; // the original PDF, stored on import (GoBD)
                this.importReview.items.push(draft);
            } catch (e) { this.importReview.failed++; }
            this.importReview.done++;
        }
        this.importReview.running = false;
        // Sort by issue date so the review reads chronologically.
        this.importReview.items.sort((a, b) => (a.issueDate || '').localeCompare(b.issueDate || ''));
    },

    // ZUGFeRD / Factur-X (EN 16931) XML export — built + downloaded client-side (ZK).
    downloadZugferd(inv) {
        const i = inv || this.current;
        if (! i) return;
        const xml = buildZugferdXml(i, this.company, this.computeTotals(i));
        saveBlobAs(new Blob([xml], { type: 'application/xml' }), zugferdFilename(i));
    },

    get importSelectedCount() { return (this.importReview?.items || []).filter((i) => i.selected).length; },
    cancelImport() { this.importReview = null; },

    // Commit the reviewed drafts as records. Imported invoices keep their ORIGINAL
    // number (historical) — no new number is minted; the duplicate-number banner still
    // guards against a clash with the ongoing series.
    async confirmImport() {
        const picked = (this.importReview?.items || []).filter((i) => i.selected);
        if (! picked.length) { this.importReview = null; return; }
        // Persist each invoice as it lands (upload PDF → add record → flush), so a reload
        // mid-import keeps every finished one and never orphans an uploaded blob. Progress
        // is shown in the modal; the awaits never block the UI thread.
        this.importReview.saving = true;
        this.importReview.saved = 0;
        this.importReview.saveTotal = picked.length;
        let ok = 0;
        for (const draft of picked) {
            try {
                const inv = {
                    id: draft.id, number: draft.number, status: 'paid',
                    issueDate: draft.issueDate || this._today(),
                    dueDate: draft.dueDate || draft.issueDate || this._today(),
                    currency: draft.currency, lang: draft.lang || 'de',
                    customer: draft.customer,
                    lines: draft.lines, note: draft.note || '', footer: draft.footer || '',
                    trashed: false, imported: true, updated: new Date().toISOString(),
                };
                // Carry the current-year sequence so the app's number counter advances.
                if (draft.seq != null) inv.seq = draft.seq;
                // Store the ORIGINAL PDF as a sealed blob (GoBD: the imported document is the
                // authoritative record — the app must show it, not a regenerated one).
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
        this.importReview = null;
    },

    async trash(inv) {
        if (! await this.$store.confirm.ask(labels.trashConfirm || 'Move this invoice to the trash?')) return;
        inv.trashed = new Date().toISOString(); this._save(); if (this.current === inv) this.backToList();
    },
    restore(inv) { inv.trashed = false; this._save(); },
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
        for (const l of inv.lines || []) {
            const net = this.lineNet(l);
            const rate = parseFloat(l.vatRate) || 0;
            t.net += net;
            const v = net * rate / 100;
            t.vatByRate[rate] = (t.vatByRate[rate] || 0) + v;
            t.vat += v;
        }
        t.gross = t.net + t.vat;
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

    // ---- Customer picker (reads zero-knowledge contacts) ----
    customerPicker: false,
    custQuery: '',
    _custContacts: [],
    async openCustomerPicker() {
        this.customerPicker = true;
        this.custQuery = '';
        try { if (await bootStore(this.$store, 'contacts')) this._custContacts = (window.LLModuleStore.contacts.data.contacts || []).filter((c) => ! c.trashed); }
        catch (e) { /* leave empty */ }
    },
    closeCustomerPicker() { this.customerPicker = false; },
    _custName(c) { return contactDisplayName(c) || ''; },
    custSuggestions() {
        const q = this.custQuery.trim().toLowerCase();
        let list = this._custContacts;
        if (q) list = list.filter((c) => this._custName(c).toLowerCase().includes(q) || (c.org || '').toLowerCase().includes(q));
        return [...list].sort((a, b) => this._custName(a).localeCompare(this._custName(b)));
    },
    _custAddress(c) {
        const a = (c.addresses || [])[0];
        if (! a) return '';
        return [a.street, [a.zip, a.city].filter(Boolean).join(' '), a.region, a.country].filter(Boolean).join('\n');
    },
    pickCustomer(c) {
        // Bill a company to its org name with the person as the contact (Attn);
        // a private contact bills to the person directly.
        const parts = contactNameParts(c);
        const person = [parts.first, parts.last].filter(Boolean).join(' ') || this._custName(c);
        const org = (c.org || '').trim();
        this.current.customer = {
            name: org || person,
            attn: org ? person : '',
            address: this._custAddress(c),
            email: (c.emails || [])[0]?.value || '',
            vatId: c.vatId || '',
            contactId: c.id,
        };
        this.customerPicker = false;
        this.saveSoon();
    },
    clearCustomer() { this.current.customer = { name: '', attn: '', address: '', email: '', vatId: '', contactId: null }; this.saveSoon(); },

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
    // Numbers assigned to more than one invoice — a GoBD violation the owner MUST fix
    // (a concurrent finalize on two offline devices is the only way to reach it).
    get duplicateNumbers() { return dupNumbers(this.activeInvoices); },

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
        const blobs = [];
        for (const inv of (this.invoices || [])) if (inv.pdf?.blob) blobs.push(inv.pdf.blob);
        for (const tx of (this.transactions || [])) for (const r of (tx.receipts || [])) if (r.blob) blobs.push(r.blob);
        for (const ref of window.LLInvoicesStore.shardRefs()) blobs.push(ref);
        postForm(config.reconcileUrl, { blobs: [...new Set(blobs)] }).catch(() => {});
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
    async _openBlob(ref) {
        if (! ref?.blob) return;
        try {
            const buf = await fetchBlobBuffer(`${config.rawBase}/${ref.blob}`);
            const plain = window.Vault.decryptFile(buf, ref.key);
            const url = URL.createObjectURL(new Blob([plain], { type: ref.mime || 'application/octet-stream' }));
            window.open(url, '_blank');
            setTimeout(() => URL.revokeObjectURL(url), 60000);
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
        this.saveSoon();
    },
    markPaid(inv) { inv.status = 'paid'; this.saveSoon(); },
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
        this.saveSoon();
    },
    statusLabel(s) { return ({ draft: labels.statusDraft, sent: labels.statusSent, paid: labels.statusPaid })[s] || s; },

    // ---- Print / PDF (client-side, zero-knowledge) ----
    printInvoice(inv) {
        const i = inv || this.current;
        // Imported invoices show their ORIGINAL PDF, never a regenerated sheet (GoBD).
        if (i?.imported && i?.pdf?.blob) { this.openOriginalPdf(i); return; }
        this._printing = i;
        this.$nextTick(() => { window.print(); });
    },
});
