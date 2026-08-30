import type { InvoiceDraftInput } from './invoice';

export type RecurringMode = 'draft' | 'auto_send';
export type RecurringInterval = 'monthly' | 'quarterly' | 'semiannual' | 'annual';
export type RecurringTemplateStatus = 'active' | 'paused' | 'completed';
export type RecurringRunStatus =
  | 'pending'
  | 'creating_draft'
  | 'draft_created'
  | 'finalizing'
  | 'finalized'
  | 'sending'
  | 'sent'
  | 'failed';

export interface RecurringInvoiceTemplateInput {
  mode: RecurringMode;
  interval: RecurringInterval;
  timezone: string;
  start_date: string;
  end_date: string | null;
  run_time: string;
  draft: InvoiceDraftInput;
}

export interface RecurringInvoiceTemplateVersionInput {
  effective_from: string;
  expected_version: number;
  draft: InvoiceDraftInput;
}

export interface RecurringInvoiceTemplateCurrentVersion {
  number: number;
  effective_from: string;
  snapshot_sha256: string;
}

export interface RecurringInvoiceTemplate {
  id: string;
  mode: RecurringMode;
  interval: RecurringInterval;
  timezone: string;
  start_date: string;
  end_date: string | null;
  run_time: string;
  anchor_day: number;
  month_end_anchor: boolean;
  next_run_at: string;
  status: RecurringTemplateStatus;
  paused_at: string | null;
  current_version: RecurringInvoiceTemplateCurrentVersion;
  version: number;
}

export interface RecurringInvoiceTemplatePage {
  data: RecurringInvoiceTemplate[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}

export interface RecurringInvoiceTemplateListFilters {
  status?: RecurringTemplateStatus | null;
  mode?: RecurringMode | null;
  page?: number;
  per_page?: number;
}

export interface RecurringInvoiceRun {
  id: string;
  scheduled_for: string;
  scheduled_local_date: string;
  status: RecurringRunStatus;
  last_completed_step: string | null;
  attempts: number;
  claimed_at: string | null;
  claim_expires_at: string | null;
  next_retry_at: string | null;
  last_error_code: string | null;
  created_at: string;
  updated_at: string;
}

export interface RecurringInvoiceRunPage {
  data: RecurringInvoiceRun[];
  links: { first: string; last: string; prev: string | null; next: string | null };
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}

export interface RecurringInvoiceRunListFilters {
  status?: RecurringRunStatus | null;
  page?: number;
  per_page?: number;
}
