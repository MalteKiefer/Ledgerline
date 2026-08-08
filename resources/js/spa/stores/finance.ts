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
export interface Partner { id: number; name: string; email: string | null; vat_id: string | null; version: number }
export interface PaymentMethod { id: number; name: string; type: string; iban: string | null; version: number }
export interface Project { id: number; name: string; parent_id: number | null; version: number }

export const useFinanceStore = defineStore('finance', () => {
  const invoices = ref<Invoice[]>([]);
  const partners = ref<Partner[]>([]);
  const paymentMethods = ref<PaymentMethod[]>([]);
  const projects = ref<Project[]>([]);

  async function load() {
    const r = await api.get<{ invoices: Invoice[]; partners: Partner[]; paymentMethods: PaymentMethod[]; projects: Project[] }>('/api/v1/finance/data');
    invoices.value = r.invoices; partners.value = r.partners; paymentMethods.value = r.paymentMethods; projects.value = r.projects;
  }
  const reports = (year?: number) => api.get<Record<string, unknown>>(`/api/v1/finance/reports${year ? `?year=${year}` : ''}`);

  const createInvoice = (body: Record<string, unknown>) => api.post<{ invoice: Invoice }>('/api/v1/finance/invoices', body);
  const updateInvoice = (id: number, body: Record<string, unknown>) => api.put<{ invoice: Invoice }>(`/api/v1/finance/invoices/${id}`, body);
  const deleteInvoice = (id: number) => api.delete(`/api/v1/finance/invoices/${id}`);
  const finalizeInvoice = (id: number) => api.post<{ invoice: Invoice }>(`/api/v1/finance/invoices/${id}/finalize`);
  const invoicePdfUrl = (id: number) => `/api/v1/finance/invoices/${id}/pdf`;

  const savePartner = (p: Partial<Partner>) => (p.id ? api.put(`/api/v1/finance/partners/${p.id}`, p) : api.post('/api/v1/finance/partners', p));
  const deletePartner = (id: number) => api.delete(`/api/v1/finance/partners/${id}`);
  const savePayment = (p: Partial<PaymentMethod>) => (p.id ? api.put(`/api/v1/finance/payment-methods/${p.id}`, p) : api.post('/api/v1/finance/payment-methods', p));
  const deletePayment = (id: number) => api.delete(`/api/v1/finance/payment-methods/${id}`);

  return {
    invoices, partners, paymentMethods, projects,
    load, reports, createInvoice, updateInvoice, deleteInvoice, finalizeInvoice, invoicePdfUrl,
    savePartner, deletePartner, savePayment, deletePayment,
  };
});
