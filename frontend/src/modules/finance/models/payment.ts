import type { CurrencyCode, DecimalIntegerString, DecimalString } from './money';
import type { Invoice, InvoiceSourceRef } from './invoice';

export interface Payment {
  id: string;
  amount_minor: DecimalIntegerString;
  allocated_minor: DecimalIntegerString;
  unapplied_minor: DecimalIntegerString;
  currency: CurrencyCode;
  received_at: string;
  reference: string | null;
  counterparty: string | null;
  payment_method_id: number | null;
  source: InvoiceSourceRef | null;
  version: number;
}

export interface PaymentPage {
  data: Payment[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}

export interface PaymentListFilters {
  q?: string;
  unallocated?: boolean | null;
  from?: string | null;
  to?: string | null;
  page?: number;
  per_page?: number;
}

export interface RecordPaymentInput {
  amount: DecimalString;
  currency: CurrencyCode;
  received_at: string;
  reference: string | null;
  counterparty: string | null;
  payment_method_id: number | null;
  source_type: string | null;
  source_key: string | null;
}

export interface AllocationLineInput {
  invoice_id: string;
  amount: DecimalString;
}

export interface AllocationResult {
  payment: Payment;
  invoices: Invoice[];
}

export type PaymentSuggestionStatus = 'none' | 'suggested' | 'ambiguous';

export interface PaymentSuggestionCandidate {
  invoice_id: string;
  number: string;
  open_minor: DecimalIntegerString;
  currency: CurrencyCode;
  score: number;
  reason: string;
}

export interface PaymentSuggestions {
  status: PaymentSuggestionStatus;
  requires_confirmation: boolean;
  candidates: PaymentSuggestionCandidate[];
}
