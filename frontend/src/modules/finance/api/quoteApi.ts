import { api, ApiError, VersionConflict } from '@spa/api/client';
import type {
  InvoiceDraftTarget,
  Quote,
  QuoteDecisionInput,
  QuoteDraftInput,
  QuoteDuplicateInput,
  QuoteListFilters,
  QuotePage,
  QuotePreview,
  QuotePublishInput,
  QuoteRevision,
  QuoteSendInput,
  QuoteSendResult,
} from '@spa/modules/finance/models/quote';

const BASE = '/api/v1/finance-v2/quotes';

export const QUOTE_ERROR_CODES = [
  'control_totals_mismatch',
  'idempotency_key_reused',
  'initial_draft_cannot_be_discarded',
  'invalid_customer',
  'invalid_discount',
  'invalid_money',
  'invalid_partner',
  'invalid_product',
  'invalid_quantity',
  'invalid_quote_input',
  'invalid_tax_rate',
  'invalid_transition',
  'invalid_validity_period',
  'no_pdf',
  'no_recipient',
  'no_smtp',
  'operation_in_progress',
  'quote_delivery_in_progress',
  'quote_draft_base_mismatch',
  'quote_draft_missing',
  'quote_draft_pending',
  'quote_expired',
  'quote_locked',
  'quote_not_accepted',
  'quote_not_published',
  'quote_publication_in_progress',
  'quote_publish_not_allowed',
  'quote_revision_base_mismatch',
  'quote_revision_not_published',
  'quote_revision_replaced',
  'quote_revision_stale',
  'quote_version_not_allowed',
  'version_conflict',
] as const;

export type QuoteErrorCode = typeof QUOTE_ERROR_CODES[number];

export function quoteErrorCode(error: unknown): QuoteErrorCode | null {
  if (error instanceof VersionConflict) return 'version_conflict';
  if (! (error instanceof ApiError) || ! error.body || typeof error.body !== 'object' || ! ('error' in error.body)) return null;
  const code = (error.body as { error?: unknown }).error;

  return typeof code === 'string' && (QUOTE_ERROR_CODES as readonly string[]).includes(code)
    ? code as QuoteErrorCode
    : null;
}

function query(filters: QuoteListFilters): string {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.status) params.set('status', filters.status);
  if (filters.effective_status) params.set('effective_status', filters.effective_status);
  if (filters.sort) params.set('sort', filters.sort);
  if (filters.direction) params.set('direction', filters.direction);
  if (filters.page !== undefined) params.set('page', String(filters.page));
  if (filters.per_page !== undefined) params.set('per_page', String(filters.per_page));
  const value = params.toString();

  return value === '' ? '' : `?${value}`;
}

const keyHeader = (key: string): Record<string, string> => ({ 'Idempotency-Key': key });

export const quoteApi = {
  list: (filters: QuoteListFilters = {}, signal?: AbortSignal) =>
    api.get<QuotePage>(`${BASE}${query(filters)}`, undefined, signal),
  show: (id: string, signal?: AbortSignal) => api.get<Quote>(`${BASE}/${id}`, undefined, signal),
  showResponse: (id: string, signal?: AbortSignal) => api.getResponse<Quote>(`${BASE}/${id}`, undefined, signal),
  revisions: (id: string, signal?: AbortSignal) => api.get<QuoteRevision[]>(`${BASE}/${id}/revisions`, undefined, signal),
  preview: (input: QuoteDraftInput, signal?: AbortSignal) => api.post<QuotePreview>(`${BASE}/preview`, input, undefined, signal),
  create: (input: QuoteDraftInput, key: string) => api.post<Quote>(BASE, input, keyHeader(key)),
  updateDraft: (id: string, version: number, input: QuoteDraftInput) =>
    api.put<Quote>(`${BASE}/${id}/draft`, { ...input, version }),
  discardDraft: (id: string, version: number) => api.delete<Quote>(`${BASE}/${id}/draft`, { version }),
  startVersion: (id: string, version: number) => api.post<Quote>(`${BASE}/${id}/versions`, { version }),
  publish: (id: string, input: QuotePublishInput, key: string) =>
    api.post<Quote>(`${BASE}/${id}/publish`, input, keyHeader(key)),
  async send(id: string, input: QuoteSendInput, key: string): Promise<QuoteSendResult> {
    const response = await api.postResponse<Quote>(`${BASE}/${id}/send`, input, keyHeader(key));
    if (response.status !== 200 && response.status !== 202) throw new ApiError(response.status, response.data);

    return { quote: response.data, replayed: response.status === 200, status: response.status, etag: response.etag };
  },
  accept: (id: string, input: QuoteDecisionInput, key: string) =>
    api.post<Quote>(`${BASE}/${id}/accept`, input, keyHeader(key)),
  decline: (id: string, input: QuoteDecisionInput, key: string) =>
    api.post<Quote>(`${BASE}/${id}/decline`, input, keyHeader(key)),
  duplicate: (id: string, input: QuoteDuplicateInput, key: string) =>
    api.post<Quote>(`${BASE}/${id}/duplicate`, input, keyHeader(key)),
  convertToInvoice: (id: string, input: QuoteDecisionInput, key: string) =>
    api.post<InvoiceDraftTarget>(`${BASE}/${id}/conversions/invoice`, input, keyHeader(key)),
  revisionPdfUrl: (id: string, revisionId: number, download = false) =>
    api.streamUrl(`${BASE}/${id}/revisions/${revisionId}/pdf${download ? '?download=1' : ''}`),
};
