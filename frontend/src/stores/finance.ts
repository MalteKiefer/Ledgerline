import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

export interface InvoiceLine { desc: string; qty: number; unitPrice: number; vatRate: number }
export interface Invoice {
  id: number; number: string | null; year: number | null; status: 'draft' | 'final' | 'sent' | 'paid';
  type: 'invoice' | 'credit_note'; issue_date: string | null; due_date: string | null; currency: string;
  vat_rate: number | null; gross: number | null; net: number | null; vat: number | null; imported: boolean;
  partner_id: number | null; customer: Record<string, unknown> | null; lines: InvoiceLine[] | null;
  note: string | null; paid_at: string | null; payment_account: string | null; version: number;
  discount_type: 'percent' | 'amount' | null; discount_value: number | string | null;
  skonto_percent: number | string | null; skonto_days: number | null;
  pdf_path?: string | null;
  created_at?: string | null;
}
export interface PartnerContact { id?: string; name?: string; email?: string; phone?: string; role?: string }
export interface Partner {
  id: number; name: string;
  category: string | null; kind: string | null; url: string | null; logo: string | null;
  note: string | null; address: string | null; email: string | null; invoice_email: string | null;
  phone: string | null; vat_id: string | null; hourly_rate: string | number | null; currency: string | null;
  contacts: PartnerContact[] | null; version: number;
}
export type FinanceScope = 'business' | 'private';

/** One detected recurring charge (subscription, standing order). Read-only. */
export interface RecurringCharge {
  merchant: string;
  scope: FinanceScope;
  cadence: 'weekly' | 'monthly' | 'quarterly' | 'semiannual' | 'annual';
  interval_days: number;
  charges: number;
  amount: number;
  /** Annualised from the cadence, so a monthly and an annual line compare. */
  yearly: number;
  first_at: string;
  last_at: string;
  next_at: string;
  /** The next charge is well overdue — cancelled, or silently stopped. */
  stale: boolean;
  price_change: { from: number; to: number; at: string } | null;
  transaction_ids: number[];
}

export interface PaymentMethod {
  id: number; name: string; type: string; version: number;
  /** An account always states its scope; its bookings inherit it. */
  scope: FinanceScope;
  holder?: string | null; business?: boolean; url?: string | null; note?: string | null;
  iban?: string | null; bic?: string | null; bank?: string | null; account_no?: string | null;
  card_number?: string | null; card_network?: string | null; card_expiry?: string | null; paypal_email?: string | null;
}
export interface Project {
  id: number; name: string; parent_id: number | null; note: string | null; version: number;
  kind: 'business' | 'private';
  // Hand-typed ledger rows (`ProjectExpense` in openapi) — free-form JSON on the
  // wire, normalised through shared/finance-projects before use.
  expenses: unknown;
  created_at?: string | null; deleted_at?: string | null;
}
export interface Receipt {
  id: number; name: string; category: string | null; tags: string[] | null; vat: string | null;
  note: string | null; partner_id: number | null; version: number; mime?: string | null;
  bank_transaction_id: number | null; finance_project_id: number | null;
  /** null = follow the linked booking (which follows its account). */
  scope: FinanceScope | null;
  /** Resolved server-side so no client re-derives the inheritance rule. */
  effective_scope?: FinanceScope;
  sig?: string | null;
  // A split-payment link (one receipt settled by several separate bank charges) —
  // mutually exclusive with bank_transaction_id, see FinanceReceipt on the backend.
  linked_transaction_ids: number[] | null;
  amount: number | string | null; currency: string | null; date: string | null; order_ref: string | null; doc_number: string | null;
  ocr?: string | null;
  created_at?: string | null; file_url?: string | null;
}

export interface BankTransaction {
  id: number; payment_method_id: number | null; date: string | null;
  // Always a real number here — `load()` normalises it. The API sends the
  // decimal:2 cast as a STRING, and this type claiming otherwise is what let a
  // stricter formatter render every row as 0,00 and `amountMatches` call
  // `.toFixed` on a string.
  amount: number;
  vat_cat: string | null;
  /** null = follow the account; an explicit value overrides it. */
  scope: FinanceScope | null;
  /** Resolved server-side (booking -> account). */
  effective_scope?: FinanceScope;
  counterparty: string | null; counterparty_iban: string | null;
  bic: string | null; purpose: string | null; booking_text: string | null; eref: string | null;
  invoice_id: number | null; invoice_number: string | null; finance_project_id: number | null;
  receipts: TxReceipt[] | null; version: number;
}
export interface TxReceipt {
  id: string; name: string; mime: string | null; kind: string | null;
  category?: string | null; tags?: string[] | null; partnerId?: number | null;
  /** Content signature (same one standalone receipts carry) for upload dedup. */
  sig?: string | null;
}
/** A Files-module entry as the project attachment list returns it (metadata only). */
export interface ProjectFile {
  id: number; name: string; mime: string | null; size: number; file_folder_id: number | null;
  version: number; created_at?: string | null; updated_at?: string | null;
}
/** A Gallery photo as the project attachment list returns it. */
export interface ProjectPhoto {
  id: number; name: string; mime: string | null; size: number; width: number | null; height: number | null;
  taken_at: string | null; media_type: string | null; version: number; created_at?: string | null;
}
export interface FinanceCategory { id: number; name: string; color: string | null; icon: string | null; account_no: string | null; version?: number }
export interface FinanceProduct {
  id: number; kind: 'service' | 'hardware'; sku: string | null; name: string; description: string | null;
  unit: string | null; price_net: string | number; purchase_price: string | number | null;
  vat_rate: string | number | null; supplier_id: number | null; category: string | null;
  active: boolean; track_stock: boolean;
  /** Denormalised read of the movement ledger — reporting, never an input. */
  stock_qty: string | number; stock_min: string | number | null;
  note: string | null; version?: number;
}
export interface StockMovement {
  id: number; finance_product_id: number; qty: string | number;
  reason: 'purchase' | 'sale' | 'correction' | 'return' | 'initial';
  ref_type: string | null; ref_id: string | null; note: string | null; occurred_at: string;
}
export interface QuoteLine {
  desc: string; qty: number; unit: string | null; unitPrice: number; vatRate: number | null;
  kind: 'service' | 'hardware' | null;
  /** Which catalogue article this line came from — what lets a finalised invoice move stock. */
  productId: number | null;
}
export interface FinanceQuote {
  id: number; number: string | null; seq: number | null; year: number | null;
  status: 'draft' | 'sent' | 'accepted' | 'declined';
  partner_id: number | null;
  customer: { name?: string; attn?: string; address?: string; email?: string; vatId?: string } | null;
  title: string | null; issue_date: string | null; valid_until: string | null; currency: string;
  lines: QuoteLine[] | null;
  discount_type: 'percent' | 'amount' | null; discount_value: string | number | null;
  net: string | number | null; vat: string | number | null; gross: string | number | null;
  intro_text: string | null; outro_text: string | null; note: string | null;
  sent_at: string | null; accepted_at: string | null; declined_at: string | null;
  converted_invoice_id: number | null; converted_project_id: number | null;
  version?: number;
}
export interface DuplicateGroup { reason: string; key: string; ids: number[] }
export interface NumberGapGroup { group: string; missing: string[]; min: string; max: string; count: number }
export interface ReceiptMatchGroup { transaction_id: number; receipt_ids: number[]; reason: 'order_ref' | 'exact' | 'sum'; total: number }
export interface SplitPaymentGroup { receipt_id: number; transaction_ids: number[]; reason: 'sum'; total: number }
export interface ReceiptDuplicate { receipt_id: number; duplicate_of: number }
export interface CategorySuggestion { tx_id: number; merchant: string; suggested_category: string }

export const useFinanceStore = defineStore('finance', () => {
  const invoices = ref<Invoice[]>([]);
  const partners = ref<Partner[]>([]);
  const paymentMethods = ref<PaymentMethod[]>([]);
  const projects = ref<Project[]>([]);
  const standaloneReceipts = ref<Receipt[]>([]);
  const transactions = ref<BankTransaction[]>([]);
  const financeCategories = ref<FinanceCategory[]>([]);
  const products = ref<FinanceProduct[]>([]);
  const quotes = ref<FinanceQuote[]>([]);
  // X -> EUR, refreshed daily server-side (finance:fetch-fx). Used to match a
  // foreign-currency receipt against euro bookings.
  const fxRates = ref<Record<string, number>>({ EUR: 1 });

  async function load() {
    const r = await api.get<{
      invoices: Invoice[]; partners: Partner[]; paymentMethods: PaymentMethod[];
      projects: Project[]; standaloneReceipts: Receipt[];
      transactions?: BankTransaction[]; financeCategories?: FinanceCategory[];
      products?: FinanceProduct[]; quotes?: FinanceQuote[];
      fxRates?: Record<string, number>;
    }>('/api/v1/finance/data');
    invoices.value = r.invoices; partners.value = r.partners; paymentMethods.value = r.paymentMethods;
    projects.value = r.projects; standaloneReceipts.value = r.standaloneReceipts ?? [];
    // Normalise money at the boundary: Laravel serialises `decimal:2` as a
    // string, and JS only *usually* coerces it (arithmetic and comparisons do,
    // `.toFixed()` throws, `Number.isFinite` says false). One cast here keeps
    // every consumer — sorting, the matchers, the formatter — on real numbers.
    transactions.value = (r.transactions ?? []).map((t) => ({ ...t, amount: Number(t.amount) || 0 }));
    financeCategories.value = r.financeCategories ?? [];
    products.value = r.products ?? [];
    quotes.value = r.quotes ?? [];
    if (r.fxRates && Object.keys(r.fxRates).length) fxRates.value = r.fxRates;
  }

  // ---- Bank transactions ----
  const createTransaction = (body: Record<string, unknown>) => api.post<{ transaction: BankTransaction }>('/api/v1/finance/transactions', body);
  const updateTransaction = (id: number, body: Record<string, unknown>) => api.put<{ transaction: BankTransaction }>(`/api/v1/finance/transactions/${id}`, body);
  const deleteTransaction = (id: number) => api.delete(`/api/v1/finance/transactions/${id}`);
  const restoreTransaction = (id: number) => api.post(`/api/v1/finance/transactions/${id}/restore`);
  const forceTransaction = (id: number) => api.delete(`/api/v1/finance/transactions/${id}/force`);
  const bulkTransactions = (paymentMethodId: number, rows: Record<string, unknown>[]) =>
    api.post<{ created: number; skipped: number }>('/api/v1/finance/transactions/bulk', { payment_method_id: paymentMethodId, transactions: rows });
  // Receipt documents attached to a bank transaction (reconcile).
  const attachTxReceipt = (txId: number, form: FormData) => api.upload<{ transaction: BankTransaction }>(`/api/v1/finance/transactions/${txId}/receipts`, form);
  const deleteTxReceipt = (txId: number, receiptId: string) => api.delete(`/api/v1/finance/transactions/${txId}/receipts/${receiptId}`);
  const txReceiptUrl = (txId: number, receiptId: string) => api.streamUrl(`/api/v1/finance/transactions/${txId}/receipts/${receiptId}/raw`);
  const loadTrash = () => api.get<{ transactions: BankTransaction[]; projects: Project[] }>('/api/v1/finance/trash');

  // ---- Finance categories ----
  const createCategory = (body: Record<string, unknown>) => api.post<{ category: FinanceCategory }>('/api/v1/finance/categories', body);
  const updateCategory = (id: number, body: Record<string, unknown>) => api.put<{ category: FinanceCategory }>(`/api/v1/finance/categories/${id}`, body);
  const deleteCategory = (id: number) => api.delete(`/api/v1/finance/categories/${id}`);

  // ---- Article catalogue + stock ----
  // Stock is not part of the update body on purpose: it moves through
  // adjustStock, which writes a ledger movement, so the figure always has a
  // history that explains it.
  const createProduct = (body: Record<string, unknown>) => api.post<{ product: FinanceProduct }>('/api/v1/finance/products', body);
  const updateProduct = (id: number, body: Record<string, unknown>) => api.put<{ product: FinanceProduct }>(`/api/v1/finance/products/${id}`, body);
  const deleteProduct = (id: number) => api.delete(`/api/v1/finance/products/${id}`);
  const restoreProduct = (id: number) => api.post<{ product: FinanceProduct }>(`/api/v1/finance/products/${id}/restore`, {});
  const forceProduct = (id: number) => api.delete(`/api/v1/finance/products/${id}/force`);
  const adjustStock = (id: number, body: Record<string, unknown>) =>
    api.post<{ movement: StockMovement; product: FinanceProduct }>(`/api/v1/finance/products/${id}/stock`, body);
  const stockMovements = (id: number) => api.get<{ movements: StockMovement[] }>(`/api/v1/finance/products/${id}/movements`);

  // ---- Quotes (Angebote) ----
  // A quote is editable only while it is a draft: once sent, the customer holds
  // a document with that number on it, so a change is a new quote (duplicate).
  const createQuote = (body: Record<string, unknown>) => api.post<{ quote: FinanceQuote }>('/api/v1/finance/quotes', body);
  const updateQuote = (id: number, body: Record<string, unknown>) => api.put<{ quote: FinanceQuote }>(`/api/v1/finance/quotes/${id}`, body);
  const sendQuote = (id: number) => api.post<{ quote: FinanceQuote }>(`/api/v1/finance/quotes/${id}/send`, {});
  const decideQuote = (id: number, decision: 'accepted' | 'declined') =>
    api.post<{ quote: FinanceQuote }>(`/api/v1/finance/quotes/${id}/decide`, { decision });
  const convertQuote = (id: number) =>
    api.post<{ invoice: Invoice; quote: FinanceQuote; already?: boolean }>(`/api/v1/finance/quotes/${id}/convert`, {});
  const duplicateQuote = (id: number) => api.post<{ quote: FinanceQuote }>(`/api/v1/finance/quotes/${id}/duplicate`, {});
  const deleteQuote = (id: number) => api.delete(`/api/v1/finance/quotes/${id}`);
  const restoreQuote = (id: number) => api.post<{ quote: FinanceQuote }>(`/api/v1/finance/quotes/${id}/restore`, {});
  const forceQuote = (id: number) => api.delete(`/api/v1/finance/quotes/${id}/force`);
  // The server decides how an article becomes a line, so the picker and any
  // future import cannot disagree about the shape.
  const productLine = (productId: number) => api.get<{ line: QuoteLine }>(`/api/v1/finance/products/${productId}/line`);

  // ---- Reports (read-only) ----
  const reports = (year?: number) => api.get<Record<string, unknown>>(`/api/v1/finance/reports${year ? `?year=${year}` : ''}`);
  const vatAdvance = (year?: number, quarter?: number) => {
    const p = new URLSearchParams();
    if (year) p.set('year', String(year));
    if (quarter) p.set('quarter', String(quarter));
    const qs = p.toString();
    return api.get<Record<string, unknown>>(`/api/v1/finance/reports/vat-advance${qs ? `?${qs}` : ''}`);
  };
  const euer = (year?: number) => api.get<Record<string, unknown>>(`/api/v1/finance/reports/euer${year ? `?year=${year}` : ''}`);
  const duplicates = () => api.get<{ invoices: DuplicateGroup[]; transactions: DuplicateGroup[] }>('/api/v1/finance/duplicates');
  const recurring = () => api.get<{ recurring: RecurringCharge[] }>('/api/v1/finance/recurring');
  const numberGaps = () => api.get<{ groups: NumberGapGroup[] }>('/api/v1/finance/number-gaps');
  const receiptMatches = () => api.get<{ groups: ReceiptMatchGroup[]; duplicates: ReceiptDuplicate[]; splitPayments: SplitPaymentGroup[] }>('/api/v1/finance/receipt-matches');
  const categorySuggestions = () => api.get<{ suggestions: CategorySuggestion[] }>('/api/v1/finance/category-suggestions');
  const accountVat = (accountId?: number, year?: number) => {
    const p = new URLSearchParams();
    if (accountId) p.set('account_id', String(accountId));
    if (year) p.set('year', String(year));
    const qs = p.toString();
    return api.get<Record<string, unknown>>(`/api/v1/finance/reports/account-vat${qs ? `?${qs}` : ''}`);
  };

  // ---- Invoices ----
  const createInvoice = (body: Record<string, unknown>) => api.post<{ invoice: Invoice }>('/api/v1/finance/invoices', body);
  const updateInvoice = (id: number, body: Record<string, unknown>) => api.put<{ invoice: Invoice }>(`/api/v1/finance/invoices/${id}`, body);
  const deleteInvoice = (id: number) => api.delete(`/api/v1/finance/invoices/${id}`);
  const finalizeInvoice = (id: number) => api.post<{ invoice: Invoice }>(`/api/v1/finance/invoices/${id}/finalize`);
  const stornoInvoice = (id: number) => api.post<{ invoice: Invoice }>(`/api/v1/finance/invoices/${id}/storno`);
  const emailInvoice = (id: number) => api.post<{ ok: boolean }>(`/api/v1/finance/invoices/${id}/email`);
  const dunInvoice = (id: number) => api.post<{ ok: boolean }>(`/api/v1/finance/invoices/${id}/dun`);
  const invoicePdfUrl = (id: number) => api.streamUrl(`/api/v1/finance/invoices/${id}/pdf`);
  // Attach the outgoing invoice document itself (PDF). Optional version_seq pins
  // it to one entry of the GoBD correction trail instead of the shared slot.
  const uploadInvoicePdf = (id: number, form: FormData) => api.upload<{ invoice: Invoice }>(`/api/v1/finance/invoices/${id}/pdf`, form);

  // ---- Partners / payment methods ----
  const savePartner = (p: Partial<Partner>) => (p.id ? api.put(`/api/v1/finance/partners/${p.id}`, p) : api.post('/api/v1/finance/partners', p));
  const deletePartner = (id: number) => api.delete(`/api/v1/finance/partners/${id}`);
  const savePayment = (p: Partial<PaymentMethod>) => (p.id ? api.put(`/api/v1/finance/payment-methods/${p.id}`, p) : api.post('/api/v1/finance/payment-methods', p));
  const deletePayment = (id: number) => api.delete(`/api/v1/finance/payment-methods/${id}`);

  // ---- Receipts (standalone "Fremdbelege") ----
  // duplicate:true means the server matched the sent `sig` against an existing
  // receipt and returned THAT one unchanged instead of creating a new row/blob.
  const createReceipt = (form: FormData) => api.upload<{ receipt: Receipt; duplicate?: boolean }>('/api/v1/finance/receipts', form);
  const updateReceipt = (id: number, body: Record<string, unknown>) => api.put<{ receipt: Receipt }>(`/api/v1/finance/receipts/${id}`, body);
  const deleteReceipt = (id: number) => api.delete(`/api/v1/finance/receipts/${id}`);
  const receiptFileUrl = (id: number) => api.streamUrl(`/api/v1/finance/receipts/${id}/raw`);

  // ---- Projects ----
  const saveProject = (p: Partial<Project>) => (p.id ? api.put<{ project: Project }>(`/api/v1/finance/projects/${p.id}`, p) : api.post<{ project: Project }>('/api/v1/finance/projects', p));
  const deleteProject = (id: number) => api.delete(`/api/v1/finance/projects/${id}`);
  // Reparenting goes through the dedicated endpoint, not a plain update: only
  // this one is cycle-guarded server-side (422 { error: 'cycle' }).
  const moveProject = (id: number, parentId: number | null) => api.post<{ project: Project }>(`/api/v1/finance/projects/${id}/move`, { parent_id: parentId });
  const restoreProject = (id: number) => api.post<{ project: Project }>(`/api/v1/finance/projects/${id}/restore`);
  const forceProject = (id: number) => api.delete(`/api/v1/finance/projects/${id}/force`);
  // Files/photos filed against a project. Read here; the pointer is written
  // through the owning module's update endpoint, where its validation lives.
  const projectAttachments = (id: number) =>
    api.get<{ files: ProjectFile[]; photos: ProjectPhoto[] }>(`/api/v1/finance/projects/${id}/attachments`);
  const linkFileToProject = (fileId: number, projectId: number | null, version?: number) =>
    api.put(`/api/v1/files/entries/${fileId}`, version != null ? { finance_project_id: projectId, version } : { finance_project_id: projectId });
  const linkPhotoToProject = (photoId: number, projectId: number | null, version?: number) =>
    api.put(`/api/v1/gallery/${photoId}`, version != null ? { finance_project_id: projectId, version } : { finance_project_id: projectId });

  return {
    invoices, partners, paymentMethods, projects, standaloneReceipts, transactions, financeCategories, fxRates,
    load, reports, vatAdvance, euer, accountVat, duplicates, recurring, numberGaps, receiptMatches, categorySuggestions,
    createTransaction, updateTransaction, deleteTransaction, restoreTransaction, forceTransaction, bulkTransactions, loadTrash,
    attachTxReceipt, deleteTxReceipt, txReceiptUrl,
    createCategory, updateCategory, deleteCategory,
    products, createProduct, updateProduct, deleteProduct, restoreProduct, forceProduct, adjustStock, stockMovements,
    quotes, createQuote, updateQuote, sendQuote, decideQuote, convertQuote, duplicateQuote, deleteQuote, restoreQuote, forceQuote, productLine,
    createInvoice, updateInvoice, deleteInvoice, finalizeInvoice, stornoInvoice, emailInvoice, dunInvoice, invoicePdfUrl, uploadInvoicePdf,
    savePartner, deletePartner, savePayment, deletePayment,
    createReceipt, updateReceipt, deleteReceipt, receiptFileUrl,
    saveProject, deleteProject, moveProject, restoreProject, forceProject,
    projectAttachments, linkFileToProject, linkPhotoToProject,
  };
});
