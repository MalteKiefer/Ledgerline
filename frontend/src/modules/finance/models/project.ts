import type { CurrencyCode, DecimalIntegerString } from '@spa/modules/finance/models/money';

export type ProjectKind = 'business' | 'private';
export type ProjectStatus = 'planned' | 'active' | 'on_hold' | 'done' | 'cancelled';
export type WorkItemStatus = 'open' | 'in_progress' | 'done';
export type LedgerDirection = 'in' | 'out';
export type ProjectSort = 'updated_at' | 'name' | 'starts_on' | 'due_on' | 'status';

export interface OffsetMeta { current_page: number; per_page: number; total: number; last_page?: number }
export interface OffsetPage<T> { data: T[]; meta: OffsetMeta }
export interface ProjectPage extends OffsetPage<Project> {
  meta: OffsetMeta & { last_page: number };
  links: { first: string; last: string; prev: string | null; next: string | null };
}

export interface Project {
  id: string;
  parent_id: string | null;
  parent_available: boolean;
  name: string;
  kind: ProjectKind;
  status: ProjectStatus;
  partner_reference: string | null;
  starts_on: string | null;
  due_on: string | null;
  budget_minor: DecimalIntegerString | null;
  currency: CurrencyCode;
  version: number;
  archived: boolean;
  created_at: string;
  updated_at: string;
}

export interface ProjectInput {
  name: string;
  kind: ProjectKind;
  budget_minor?: DecimalIntegerString | null;
  currency: CurrencyCode;
  partner_reference?: string | null;
  parent_id?: string | null;
  starts_on?: string | null;
  due_on?: string | null;
}
export interface ProjectUpdateInput extends ProjectInput { version: number }
export interface ProjectStatusInput { version: number; status: ProjectStatus }
export interface ProjectMoveInput { version: number; parent_id: string | null }

export interface ProjectListFilters {
  q?: string;
  status?: ProjectStatus | null;
  kind?: ProjectKind | null;
  partner_reference?: string | null;
  parent_id?: string | null;
  archived?: boolean;
  starts_from?: string | null;
  starts_to?: string | null;
  due_from?: string | null;
  due_to?: string | null;
  sort?: ProjectSort;
  direction?: 'asc' | 'desc';
  page?: number;
  per_page?: number;
}

export interface WorkItem {
  resource_type: 'work_item'; id: string; title: string; description: string | null; status: WorkItemStatus;
  starts_on: string | null; due_on: string | null; estimate_quantity_scaled: DecimalIntegerString | null;
  is_milestone: boolean; sort: number; product_reference: string | null; version: number;
}
export interface WorkItemInput {
  title: string; description?: string | null; status: WorkItemStatus; starts_on?: string | null; due_on?: string | null;
  estimate_hours?: string | null; is_milestone: boolean; product_reference?: string | null; version?: number;
}

export interface TimeEntry {
  resource_type: 'time_entry'; id: string; work_item_id: string | null; worked_on: string; quantity_scaled: DecimalIntegerString;
  description: string | null; billable: boolean; hourly_rate_minor: DecimalIntegerString | null; currency: CurrencyCode;
  invoice_target_reference: string | null; invoiced_at: string | null; version: number;
}
export interface TimeEntryInput {
  work_item_id?: string | null; worked_on: string; hours: string; description?: string | null; billable: boolean;
  hourly_rate_minor?: DecimalIntegerString | null; currency: CurrencyCode; version?: number;
}

export interface LedgerEntry {
  resource_type: 'ledger_entry'; id: string; direction: LedgerDirection; amount_minor: DecimalIntegerString; currency: CurrencyCode;
  occurred_on: string | null; title: string | null; note: string | null; category_reference: string | null;
  payment_method_reference: string | null; version: number;
}
export interface LedgerEntryInput {
  direction: LedgerDirection; amount_minor: DecimalIntegerString; currency: CurrencyCode; occurred_on?: string | null;
  title?: string | null; note?: string | null; category_reference?: string | null; payment_method_reference?: string | null; version?: number;
}
export interface LedgerFilters { direction?: LedgerDirection | null; from?: string | null; to?: string | null; category_reference?: string | null; page?: number; per_page?: number }
export interface PageQuery { page?: number; per_page?: number }

export interface ProjectTotalsCurrency {
  hours_scaled: DecimalIntegerString;
  time_value_minor: DecimalIntegerString;
  ledger_minor: DecimalIntegerString;
  financial_minor: DecimalIntegerString;
}
export interface ProjectTotals { project_id: string; currencies: Record<CurrencyCode, ProjectTotalsCurrency> }

export interface InvoiceDraftTarget {
  target_reference: string;
  source: { source_type: string; source_reference: string; pinned_revision_id: number | null };
  navigation_url: string | null;
}
