import { api, ApiError, VersionConflict } from '@spa/api/client';
import type {
  Invoice,
  InvoiceDelivery,
  InvoiceDraftInput,
  InvoiceListFilters,
  InvoicePage,
  InvoiceRevision,
} from '@spa/modules/finance/models/invoice';

const BASE = '/api/v1/finance-v2/invoices';

export const INVOICE_ERROR_CODES = [
  'credit_note_cannot_be_cancelled',
  'delivery_idempotency_conflict',
  'delivery_invoice_not_eligible',
  'delivery_invoice_state_conflict',
  'delivery_kind_invalid',
  'delivery_pdf_unavailable',
  'delivery_recipient_missing',
  'delivery_retry_not_allowed',
  'delivery_revision_stale',
  'delivery_smtp_unavailable',
  'delivery_state_conflict',
  'document_totals_mismatch',
  'idempotency_conflict',
  'idempotency_key_reused',
  'invalid_customer',
  'invalid_discount',
  'invalid_invoice_dates',
  'invalid_invoice_input',
  'invalid_money',
  'invalid_partner',
  'invalid_product',
  'invalid_quantity',
  'invalid_tax_rate',
  'invoice_delete_conflict',
  'invoice_finalization_conflict',
  'invoice_not_cancellable',
  'invoice_not_deletable',
  'invoice_not_editable',
  'invoice_not_finalizable',
  'invoice_not_overdue',
  'invoice_version_conflict',
  'operation_in_progress',
  'source_snapshot_conflict',
  'version_conflict',
] as const;

export type InvoiceErrorCode = typeof INVOICE_ERROR_CODES[number];

export function invoiceErrorCode(error: unknown): InvoiceErrorCode | null {
  if (error instanceof VersionConflict) return 'version_conflict';
  if (! (error instanceof ApiError) || ! error.body || typeof error.body !== 'object' || ! ('error' in error.body)) return null;
  const code = (error.body as { error?: unknown }).error;

  return typeof code === 'string' && (INVOICE_ERROR_CODES as readonly string[]).includes(code)
    ? code as InvoiceErrorCode
    : null;
}

function query(filters: InvoiceListFilters): string {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.status) params.set('status', filters.status);
  if (filters.kind) params.set('kind', filters.kind);
  if (filters.overdue !== undefined && filters.overdue !== null) params.set('overdue', filters.overdue ? '1' : '0');
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  if (filters.page !== undefined) params.set('page', String(filters.page));
  if (filters.per_page !== undefined) params.set('per_page', String(filters.per_page));
  const value = params.toString();

  return value === '' ? '' : `?${value}`;
}

const keyHeader = (key: string): Record<string, string> => ({ 'Idempotency-Key': key });

export const invoiceApi = {
  list: (filters: InvoiceListFilters = {}, signal?: AbortSignal) =>
    api.get<InvoicePage>(`${BASE}${query(filters)}`, undefined, signal),
  show: (id: string, signal?: AbortSignal) => api.get<Invoice>(`${BASE}/${id}`, undefined, signal),
  showResponse: (id: string, signal?: AbortSignal) => api.getResponse<Invoice>(`${BASE}/${id}`, undefined, signal),
  revisions: (id: string, signal?: AbortSignal) => api.get<InvoiceRevision[]>(`${BASE}/${id}/revisions`, undefined, signal),
  create: (input: InvoiceDraftInput) => api.post<Invoice>(BASE, input),
  update: (id: string, version: number, input: InvoiceDraftInput) =>
    api.patch<Invoice>(`${BASE}/${id}`, { ...input, version }),
  destroy: (id: string, version: number) => api.delete<void>(`${BASE}/${id}`, { version }),
  finalize: (id: string, key: string) => api.post<Invoice>(`${BASE}/${id}/finalize`, undefined, keyHeader(key)),
  cancel: (id: string, key: string) => api.post<Invoice>(`${BASE}/${id}/cancel`, undefined, keyHeader(key)),
  deliver: (id: string, recipient: string | null, key: string) =>
    api.post<InvoiceDelivery>(`${BASE}/${id}/deliveries`, { recipient }, keyHeader(key)),
  remind: (id: string, level: 1 | 2 | 3, recipient: string | null, key: string) =>
    api.post<InvoiceDelivery>(`${BASE}/${id}/reminders`, { level, recipient }, keyHeader(key)),
  revisionPdfUrl: (id: string, revisionId: number, download = false) =>
    api.streamUrl(`${BASE}/${id}/revisions/${revisionId}/pdf${download ? '?download=1' : ''}`),
};
