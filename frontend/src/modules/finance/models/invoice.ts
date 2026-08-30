import type { CurrencyCode, DecimalIntegerString, DecimalString, MoneyTotals } from './money';

export type InvoiceKind = 'invoice' | 'credit_note';
export type InvoiceStatus = 'draft' | 'finalized' | 'sent' | 'partially_paid' | 'paid' | 'cancelled';
export type InvoiceLineKind = 'service' | 'hardware';
export type InvoiceDiscountType = 'none' | 'percent' | 'fixed';
export type InvoiceDeliveryKind = 'invoice' | 'reminder';
export type InvoiceDeliveryStatus = 'pending' | 'sending' | 'sent' | 'failed' | 'unknown';

export interface InvoiceCustomer extends Record<string, unknown> {
  name: string;
  email?: string | null;
}

export interface InvoiceLineInput {
  description: string;
  quantity: DecimalString;
  unit: string;
  unit_price: DecimalString;
  tax_rate: DecimalString;
  kind: InvoiceLineKind;
  product_id: number | null;
}

export interface InvoiceDraftInput {
  issue_date: string;
  due_date: string;
  currency: CurrencyCode;
  customer: InvoiceCustomer;
  partner_id: number | null;
  project_id: number | null;
  lines: InvoiceLineInput[];
  discount_type: InvoiceDiscountType;
  discount_value: DecimalString | null;
  control_net_minor?: DecimalIntegerString | null;
  control_vat_minor?: DecimalIntegerString | null;
  control_gross_minor?: DecimalIntegerString | null;
}

export interface InvoiceSourceRef {
  type: 'quote_revision' | 'legacy_quote_snapshot' | 'project_time_batch' | 'recurring_run' | 'cancellation' | 'legacy_invoice';
  key: string;
}

export interface Invoice {
  id: string;
  kind: InvoiceKind;
  number: string | null;
  status: InvoiceStatus;
  issue_date: string;
  due_date: string;
  partner_id: number | null;
  project_id: number | null;
  totals: MoneyTotals;
  allocated_minor: DecimalIntegerString;
  paid_minor: DecimalIntegerString;
  open_minor: DecimalIntegerString;
  source: InvoiceSourceRef | null;
  snapshot: Record<string, unknown>;
  version: number;
  created_at: string;
  updated_at: string;
  revision?: { id: number; pdf_sha256: string; finalized_at: string };
}

export interface InvoicePage {
  data: Invoice[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}

export interface InvoiceListFilters {
  q?: string;
  status?: InvoiceStatus | null;
  kind?: InvoiceKind | null;
  overdue?: boolean | null;
  from?: string | null;
  to?: string | null;
  page?: number;
  per_page?: number;
}

export interface InvoiceRevision {
  id: number;
  revision_number: number;
  status: 'draft' | 'published';
  snapshot: Record<string, unknown>;
  net_minor: number;
  vat_minor: number;
  gross_minor: number;
  currency: CurrencyCode;
  pdf_sha256: string | null;
  pdf_url: string;
  published_at: string | null;
}

export interface InvoiceDelivery {
  id: string;
  kind: InvoiceDeliveryKind;
  recipient: string;
  status: InvoiceDeliveryStatus;
  attempts: number;
  last_attempt_at: string | null;
  sent_at: string | null;
  next_retry_at: string | null;
  last_error_code: string | null;
  created_at: string;
  updated_at: string;
}
