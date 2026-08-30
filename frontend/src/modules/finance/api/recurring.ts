import { api, ApiError, VersionConflict } from '@spa/api/client';
import type {
  RecurringInvoiceRun,
  RecurringInvoiceRunListFilters,
  RecurringInvoiceRunPage,
  RecurringInvoiceTemplate,
  RecurringInvoiceTemplateInput,
  RecurringInvoiceTemplateListFilters,
  RecurringInvoiceTemplatePage,
  RecurringInvoiceTemplateVersionInput,
} from '@spa/modules/finance/models/recurring';

const BASE = '/api/v1/finance-v2/recurring-invoice-templates';
const RUNS_BASE = '/api/v1/finance-v2/recurring-invoice-runs';

export const RECURRING_ERROR_CODES = [
  'idempotency_conflict',
  'idempotency_key_reused',
  'invalid_customer',
  'invalid_discount',
  'invalid_effective_date',
  'invalid_interval',
  'invalid_invoice_dates',
  'invalid_money',
  'invalid_partner',
  'invalid_product',
  'invalid_quantity',
  'invalid_recurring_mode',
  'invalid_recurring_template_input',
  'invalid_tax_rate',
  'invalid_timezone',
  'operation_in_progress',
  'recurring_template_completed',
  'recurring_template_effective_date_before_start',
  'recurring_template_effective_date_conflict',
  'recurring_template_version_conflict',
  'version_conflict',
] as const;

export type RecurringErrorCode = typeof RECURRING_ERROR_CODES[number];

export function recurringErrorCode(error: unknown): RecurringErrorCode | null {
  if (error instanceof VersionConflict) return 'version_conflict';
  if (! (error instanceof ApiError) || ! error.body || typeof error.body !== 'object' || ! ('error' in error.body)) return null;
  const code = (error.body as { error?: unknown }).error;

  return typeof code === 'string' && (RECURRING_ERROR_CODES as readonly string[]).includes(code)
    ? code as RecurringErrorCode
    : null;
}

function templateQuery(filters: RecurringInvoiceTemplateListFilters): string {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.mode) params.set('mode', filters.mode);
  if (filters.page !== undefined) params.set('page', String(filters.page));
  if (filters.per_page !== undefined) params.set('per_page', String(filters.per_page));
  const value = params.toString();

  return value === '' ? '' : `?${value}`;
}

function runQuery(filters: RecurringInvoiceRunListFilters): string {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.page !== undefined) params.set('page', String(filters.page));
  if (filters.per_page !== undefined) params.set('per_page', String(filters.per_page));
  const value = params.toString();

  return value === '' ? '' : `?${value}`;
}

const keyHeader = (key: string): Record<string, string> => ({ 'Idempotency-Key': key });

export const recurringApi = {
  list: (filters: RecurringInvoiceTemplateListFilters = {}, signal?: AbortSignal) =>
    api.get<RecurringInvoiceTemplatePage>(`${BASE}${templateQuery(filters)}`, undefined, signal),
  show: (id: string, signal?: AbortSignal) => api.get<RecurringInvoiceTemplate>(`${BASE}/${id}`, undefined, signal),
  showResponse: (id: string, signal?: AbortSignal) => api.getResponse<RecurringInvoiceTemplate>(`${BASE}/${id}`, undefined, signal),
  create: (input: RecurringInvoiceTemplateInput, key: string) => api.post<RecurringInvoiceTemplate>(BASE, input, keyHeader(key)),
  addVersion: (id: string, input: RecurringInvoiceTemplateVersionInput, key: string) =>
    api.post<RecurringInvoiceTemplate>(`${BASE}/${id}/versions`, input, keyHeader(key)),
  pause: (id: string, expectedVersion: number, key: string) =>
    api.post<RecurringInvoiceTemplate>(`${BASE}/${id}/pause`, { expected_version: expectedVersion }, keyHeader(key)),
  resume: (id: string, expectedVersion: number, key: string) =>
    api.post<RecurringInvoiceTemplate>(`${BASE}/${id}/resume`, { expected_version: expectedVersion }, keyHeader(key)),
  runs: (templateId: string, filters: RecurringInvoiceRunListFilters = {}, signal?: AbortSignal) =>
    api.get<RecurringInvoiceRunPage>(`${BASE}/${templateId}/runs${runQuery(filters)}`, undefined, signal),
  retryRun: (runId: string) => api.post<RecurringInvoiceRun>(`${RUNS_BASE}/${runId}/retry`),
};
