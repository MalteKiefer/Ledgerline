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
export interface PaymentMethod {
  id: number; name: string; type: string; version: number;
  holder?: string | null; business?: boolean; url?: string | null; note?: string | null;
  iban?: string | null; bic?: string | null; bank?: string | null; account_no?: string | null;
  card_number?: string | null; card_network?: string | null; card_expiry?: string | null; paypal_email?: string | null;
}
export interface Project { id: number; name: string; parent_id: number | null; note: string | null; version: number }
export interface Receipt {
  id: number; name: string; category: string | null; tags: string[] | null; vat: string | null;
  note: string | null; partner_id: number | null; version: number; mime?: string | null;
  bank_transaction_id: number | null;
  // A split-payment link (one receipt settled by several separate bank charges) —
  // mutually exclusive with bank_transaction_id, see FinanceReceipt on the backend.
  linked_transaction_ids: number[] | null;
  amount: number | string | null; currency: string | null; date: string | null; order_ref: string | null; doc_number: string | null;
  ocr?: string | null;
  created_at?: string | null; file_url?: string | null;
}

export interface BankTransaction {
  id: number; payment_method_id: number | null; date: string | null; amount: number;
  vat_cat: string | null; counterparty: string | null; counterparty_iban: string | null;
  bic: string | null; purpose: string | null; booking_text: string | null; eref: string | null;
  invoice_id: number | null; invoice_number: string | null; finance_project_id: number | null;
  receipts: TxReceipt[] | null; version: number;
}
export interface TxReceipt {
  id: string; name: string; mime: string | null; kind: string | null;
  category?: string | null; tags?: string[] | null; partnerId?: number | null;
}
export interface FinanceCategory { id: number; name: string; color: string | null; icon: string | null; account_no: string | null; version?: number }
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

  async function load() {
    const r = await api.get<{
      invoices: Invoice[]; partners: Partner[]; paymentMethods: PaymentMethod[];
      projects: Project[]; standaloneReceipts: Receipt[];
      transactions?: BankTransaction[]; financeCategories?: FinanceCategory[];
    }>('/api/v1/finance/data');
    invoices.value = r.invoices; partners.value = r.partners; paymentMethods.value = r.paymentMethods;
    projects.value = r.projects; standaloneReceipts.value = r.standaloneReceipts ?? [];
    transactions.value = r.transactions ?? []; financeCategories.value = r.financeCategories ?? [];
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
  const loadTrash = () => api.get<{ transactions: BankTransaction[] }>('/api/v1/finance/trash');

  // ---- Finance categories ----
  const createCategory = (body: Record<string, unknown>) => api.post<{ category: FinanceCategory }>('/api/v1/finance/categories', body);
  const updateCategory = (id: number, body: Record<string, unknown>) => api.put<{ category: FinanceCategory }>(`/api/v1/finance/categories/${id}`, body);
  const deleteCategory = (id: number) => api.delete(`/api/v1/finance/categories/${id}`);

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

  // ---- Partners / payment methods ----
  const savePartner = (p: Partial<Partner>) => (p.id ? api.put(`/api/v1/finance/partners/${p.id}`, p) : api.post('/api/v1/finance/partners', p));
  const deletePartner = (id: number) => api.delete(`/api/v1/finance/partners/${id}`);
  const savePayment = (p: Partial<PaymentMethod>) => (p.id ? api.put(`/api/v1/finance/payment-methods/${p.id}`, p) : api.post('/api/v1/finance/payment-methods', p));
  const deletePayment = (id: number) => api.delete(`/api/v1/finance/payment-methods/${id}`);

  // ---- Receipts (standalone "Fremdbelege") ----
  const createReceipt = (form: FormData) => api.upload<{ receipt: Receipt }>('/api/v1/finance/receipts', form);
  const updateReceipt = (id: number, body: Record<string, unknown>) => api.put<{ receipt: Receipt }>(`/api/v1/finance/receipts/${id}`, body);
  const deleteReceipt = (id: number) => api.delete(`/api/v1/finance/receipts/${id}`);
  const receiptFileUrl = (id: number) => api.streamUrl(`/api/v1/finance/receipts/${id}/raw`);

  // ---- Projects ----
  const saveProject = (p: Partial<Project>) => (p.id ? api.put<{ project: Project }>(`/api/v1/finance/projects/${p.id}`, p) : api.post<{ project: Project }>('/api/v1/finance/projects', p));
  const deleteProject = (id: number) => api.delete(`/api/v1/finance/projects/${id}`);

  return {
    invoices, partners, paymentMethods, projects, standaloneReceipts, transactions, financeCategories,
    load, reports, vatAdvance, euer, accountVat, duplicates, numberGaps, receiptMatches, categorySuggestions,
    createTransaction, updateTransaction, deleteTransaction, restoreTransaction, forceTransaction, bulkTransactions, loadTrash,
    attachTxReceipt, deleteTxReceipt, txReceiptUrl,
    createCategory, updateCategory, deleteCategory,
    createInvoice, updateInvoice, deleteInvoice, finalizeInvoice, stornoInvoice, emailInvoice, dunInvoice, invoicePdfUrl,
    savePartner, deletePartner, savePayment, deletePayment,
    createReceipt, updateReceipt, deleteReceipt, receiptFileUrl,
    saveProject, deleteProject,
  };
});
