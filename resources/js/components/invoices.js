// invoices component. Extracted from app.js.
import { zkModule, bootStore } from '../shared/zk-module';
import { nextSeq, duplicateNumbers as dupNumbers } from '../shared/invoice-numbering';
import { parseInvoiceFilename, parseInvoiceText, buildImportedInvoice, buildInvoiceFromXml } from '../shared/invoice-pdf-import';
import { parseEInvoiceXml, looksLikeEInvoiceXml } from '../shared/einvoice-xml';
import { contactNameParts, contactDisplayName } from '../shared/contact-utils';
import { jsonHeaders } from '../shared/api';
import { saveBlobAs } from '../shared/dom';
import { buildZugferdXml, zugferdFilename } from '../shared/zugferd';

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
    ...zkModule({ store: 'invoices', instance: () => window.LLInvoicesStore, afterLoad: (self, ms) => migrateInvoicesFromMonolith(ms), map: { invoices: 'invoices' }, onLock: (self) => { self.view = 'list'; self.current = null; } }),

    company: config.company || {},
    _labelsByLang: config.labelsByLang || {},
    invoices: [],
    view: 'list',        // 'list' | 'edit'
    current: null,       // the invoice being edited
    filterStatus: '',    // '' | draft | sent | paid
    _printing: null,     // invoice rendered into the hidden print sheet
    // Finance section: the page is a "Finanzen" hub with tabs. Invoices are one tab.
    section: 'dashboard', // 'dashboard' | 'receipts' | 'invoices' | 'stats'

    async init() {
        const h = (location.hash || '').replace('#', '');
        if (['dashboard', 'receipts', 'invoices', 'stats'].includes(h)) this.section = h;
        await this._initZk();
    },

    setSection(s) { this.section = s; try { history.replaceState(null, '', '#' + s); } catch (e) { /* ignore */ } },

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
        for (const draft of picked) {
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
            inv.totals = this.computeTotals(inv);
            this.invoices.unshift(inv);
        }
        this._save();
        window.llToast?.((labels.importDone || ':n invoices imported.').replace(':n', picked.length));
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
        const seq = nextSeq(this.invoices, 0, floor);
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
    get duplicateNumbers() { return dupNumbers(this.invoices); },
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
        this._printing = inv || this.current;
        this.$nextTick(() => { window.print(); });
    },
});
