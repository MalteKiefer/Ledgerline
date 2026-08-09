import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

export interface InvoiceLine { desc: string; qty: number; unitPrice: number; vatRate: number }
export interface Invoice {
  id: number; number: string | null; year: number | null; status: 'draft' | 'final' | 'sent' | 'paid';
  type: 'invoice' | 'credit_note'; issue_date: string | null; due_date: string | null; currency: string;
  vat_rate: number | null; gross: number | null; net: number | null; vat: number | null; imported: boolean;
  partner_id: number | null; customer: Record<string, unknown> | null; lines: InvoiceLine[] | null;
  note: string | null; paid_at: string | null; version: number;
}
export interface PartnerContact { id?: string; name?: string; email?: string; phone?: string; role?: string }
export interface Partner {
  id: number; name: string;
  category: string | null; kind: string | null; url: string | null; logo: string | null;
  note: string | null; address: string | null; email: string | null; invoice_email: string | null;
  phone: string | null; vat_id: string | null; hourly_rate: string | number | null; currency: string | null;
  contacts: PartnerContact[] | null; version: number;
}
export interface PaymentMethod { id: number; name: string; type: string; iban: string | null; version: number }
export interface Project { id: number; name: string; parent_id: number | null; note: string | null; version: number }
export interface Receipt {
  id: number; name: string; category: string | null; tags: string[] | null; vat: string | null;
  note: string | null; partner_id: number | null; version: number;
  // Optional presentation fields (may be absent depending on backend serialization).
  amount?: number | null; date?: string | null; created_at?: string | null; file_url?: string | null;
}

export const useFinanceStore = defineStore('finance', () => {
  const invoices = ref<Invoice[]>([]);
  const partners = ref<Partner[]>([]);
  const paymentMethods = ref<PaymentMethod[]>([]);
  const projects = ref<Project[]>([]);
  const standaloneReceipts = ref<Receipt[]>([]);

  async function load() {
    const r = await api.get<{
      invoices: Invoice[]; partners: Partner[]; paymentMethods: PaymentMethod[];
      projects: Project[]; standaloneReceipts: Receipt[];
    }>('/api/v1/finance/data');
    invoices.value = r.invoices; partners.value = r.partners; paymentMethods.value = r.paymentMethods;
    projects.value = r.projects; standaloneReceipts.value = r.standaloneReceipts ?? [];
  }

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
    invoices, partners, paymentMethods, projects, standaloneReceipts,
    load, reports, vatAdvance, euer, accountVat,
    createInvoice, updateInvoice, deleteInvoice, finalizeInvoice, stornoInvoice, emailInvoice, dunInvoice, invoicePdfUrl,
    savePartner, deletePartner, savePayment, deletePayment,
    createReceipt, updateReceipt, deleteReceipt, receiptFileUrl,
    saveProject, deleteProject,
  };
});
