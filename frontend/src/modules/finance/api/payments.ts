import { api, ApiError } from '@spa/api/client';
import type {
  AllocationLineInput,
  AllocationResult,
  Payment,
  PaymentListFilters,
  PaymentPage,
  PaymentSuggestions,
  RecordPaymentInput,
} from '@spa/modules/finance/models/payment';

const BASE = '/api/v1/finance/payments';
const ALLOCATIONS_BASE = '/api/v1/finance/payment-allocations';

export const PAYMENT_ERROR_CODES = [
  'allocation_already_reversed',
  'allocation_currency_mismatch',
  'allocation_over_limit',
  'allocation_reversal_conflict',
  'allocation_sign_mismatch',
  'allocation_target_cancelled',
  'allocation_target_not_finalized',
  'allocation_zero_amount',
  'idempotency_conflict',
  'idempotency_key_reused',
  'invalid_counterparty',
  'invalid_currency',
  'invalid_money',
  'invalid_payment_input',
  'invalid_reference',
  'invalid_source',
  'invoice_version_conflict',
  'payment_version_conflict',
] as const;

export type PaymentErrorCode = typeof PAYMENT_ERROR_CODES[number];

export function paymentErrorCode(error: unknown): PaymentErrorCode | null {
  if (! (error instanceof ApiError) || ! error.body || typeof error.body !== 'object' || ! ('error' in error.body)) return null;
  const code = (error.body as { error?: unknown }).error;

  return typeof code === 'string' && (PAYMENT_ERROR_CODES as readonly string[]).includes(code)
    ? code as PaymentErrorCode
    : null;
}

function query(filters: PaymentListFilters): string {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.unallocated !== undefined && filters.unallocated !== null) params.set('unallocated', filters.unallocated ? '1' : '0');
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  if (filters.page !== undefined) params.set('page', String(filters.page));
  if (filters.per_page !== undefined) params.set('per_page', String(filters.per_page));
  const value = params.toString();

  return value === '' ? '' : `?${value}`;
}

const keyHeader = (key: string): Record<string, string> => ({ 'Idempotency-Key': key });

export const paymentApi = {
  list: (filters: PaymentListFilters = {}, signal?: AbortSignal) =>
    api.get<PaymentPage>(`${BASE}${query(filters)}`, undefined, signal),
  show: (id: string, signal?: AbortSignal) => api.get<Payment>(`${BASE}/${id}`, undefined, signal),
  showResponse: (id: string, signal?: AbortSignal) => api.getResponse<Payment>(`${BASE}/${id}`, undefined, signal),
  suggestions: (id: string, signal?: AbortSignal) => api.get<PaymentSuggestions>(`${BASE}/${id}/suggestions`, undefined, signal),
  record: (input: RecordPaymentInput, key: string) => api.post<Payment>(BASE, input, keyHeader(key)),
  allocate: (id: string, lines: AllocationLineInput[], expectedVersion: number | null, key: string) =>
    api.post<AllocationResult>(`${BASE}/${id}/allocations`, { lines, expected_version: expectedVersion }, keyHeader(key)),
  reverse: (allocationId: number, expectedPaymentVersion: number | null, key: string) =>
    api.post<AllocationResult>(`${ALLOCATIONS_BASE}/${allocationId}/reverse`, { expected_payment_version: expectedPaymentVersion }, keyHeader(key)),
};
