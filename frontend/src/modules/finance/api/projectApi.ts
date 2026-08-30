import { api, ApiError, VersionConflict } from '@spa/api/client';
import type {
  InvoiceDraftTarget, LedgerEntry, LedgerEntryInput, LedgerFilters, OffsetPage, PageQuery, Project, ProjectInput,
  ProjectListFilters, ProjectMoveInput, ProjectPage, ProjectStatusInput, ProjectTotals, ProjectUpdateInput,
  TimeEntry, TimeEntryInput, WorkItem, WorkItemInput,
} from '@spa/modules/finance/models/project';
import type {
  ProjectDocument, ProjectDocumentFilters, ProjectDocumentInput, ProjectDocumentPage, ProjectDocumentSourceFilters,
  ProjectDocumentSourcePage,
} from '@spa/modules/finance/models/projectDocument';
import type { HistoryCursorPage, HistoryCursorQuery, HistoryItem, HistoryPage, HistoryPageQuery, NoteInput } from '@spa/modules/finance/models/history';

const BASE = '/api/v1/finance-v2/projects';
const SERIES = '/api/v1/finance-v2/document-series';

export const PROJECT_ERROR_CODES = [
  'document_already_attached', 'document_not_attached', 'idempotency_key_reused', 'invalid_project_input',
  'invalid_transition', 'operation_in_progress', 'project_archived', 'time_invoiced', 'version_conflict',
] as const;
export type ProjectErrorCode = typeof PROJECT_ERROR_CODES[number];

export function projectErrorCode(error: unknown): ProjectErrorCode | null {
  if (error instanceof VersionConflict) return 'version_conflict';
  if (! (error instanceof ApiError) || ! error.body || typeof error.body !== 'object' || ! ('error' in error.body)) return null;
  const code = (error.body as { error?: unknown }).error;
  return typeof code === 'string' && (PROJECT_ERROR_CODES as readonly string[]).includes(code) ? code as ProjectErrorCode : null;
}

function withQuery(path: string, values: object): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(values)) {
    if (value !== undefined && value !== null && value !== '') params.set(key, String(value));
  }
  const query = params.toString();
  return query === '' ? path : `${path}?${query}`;
}

const keyHeader = (key: string): Record<string, string> => ({ 'Idempotency-Key': key });

export const projectApi = {
  list: (filters: ProjectListFilters = {}, signal?: AbortSignal) => api.get<ProjectPage>(withQuery(BASE, filters), undefined, signal),
  show: (id: string, signal?: AbortSignal) => api.get<Project>(`${BASE}/${id}`, undefined, signal),
  showResponse: (id: string, signal?: AbortSignal) => api.getResponse<Project>(`${BASE}/${id}`, undefined, signal),
  create: (input: ProjectInput) => api.post<Project>(BASE, input),
  update: (id: string, input: ProjectUpdateInput) => api.put<Project>(`${BASE}/${id}`, input),
  changeStatus: (id: string, input: ProjectStatusInput) => api.post<Project>(`${BASE}/${id}/status`, input),
  move: (id: string, input: ProjectMoveInput) => api.post<Project>(`${BASE}/${id}/move`, input),
  archive: (id: string, version: number) => api.delete<Project>(`${BASE}/${id}`, { version }),
  restore: (id: string, version: number) => api.post<Project>(`${BASE}/${id}/restore`, { version }),

  listWorkItems: (id: string, query: PageQuery = {}, signal?: AbortSignal) => api.get<OffsetPage<WorkItem>>(withQuery(`${BASE}/${id}/work-items`, query), undefined, signal),
  createWorkItem: (id: string, input: WorkItemInput) => api.post<WorkItem>(`${BASE}/${id}/work-items`, input),
  updateWorkItem: (id: string, workItem: string, input: WorkItemInput & { version: number }) => api.put<WorkItem>(`${BASE}/${id}/work-items/${workItem}`, input),
  deleteWorkItem: (id: string, workItem: string, version: number) => api.delete<void>(`${BASE}/${id}/work-items/${workItem}`, { version }),
  reorderWorkItems: (id: string, ids: string[]) => api.post<{ data: WorkItem[] }>(`${BASE}/${id}/work-items/reorder`, { ids }),

  listTimeEntries: (id: string, query: PageQuery = {}, signal?: AbortSignal) => api.get<OffsetPage<TimeEntry>>(withQuery(`${BASE}/${id}/time-entries`, query), undefined, signal),
  createTimeEntry: (id: string, input: TimeEntryInput) => api.post<TimeEntry>(`${BASE}/${id}/time-entries`, input),
  updateTimeEntry: (id: string, entry: string, input: TimeEntryInput & { version: number }) => api.put<TimeEntry>(`${BASE}/${id}/time-entries/${entry}`, input),
  deleteTimeEntry: (id: string, entry: string, version: number) => api.delete<void>(`${BASE}/${id}/time-entries/${entry}`, { version }),
  createInvoiceDraft: (id: string, input: { time_entry_ids: string[] }, key: string) => api.post<InvoiceDraftTarget>(`${BASE}/${id}/invoice-drafts`, input, keyHeader(key)),

  getTotals: (id: string, signal?: AbortSignal) => api.get<ProjectTotals>(`${BASE}/${id}/totals`, undefined, signal),
  listLedger: (id: string, filters: LedgerFilters = {}, signal?: AbortSignal) => api.get<OffsetPage<LedgerEntry>>(withQuery(`${BASE}/${id}/ledger`, filters), undefined, signal),
  createLedgerEntry: (id: string, input: LedgerEntryInput) => api.post<LedgerEntry>(`${BASE}/${id}/ledger`, input),
  updateLedgerEntry: (id: string, entry: string, input: LedgerEntryInput & { version: number }) => api.put<LedgerEntry>(`${BASE}/${id}/ledger/${entry}`, input),
  deleteLedgerEntry: (id: string, entry: string, version: number) => api.delete<void>(`${BASE}/${id}/ledger/${entry}`, { version }),

  listDocuments: (id: string, filters: ProjectDocumentFilters = {}, signal?: AbortSignal) => api.get<ProjectDocumentPage>(withQuery(`${BASE}/${id}/documents`, filters), undefined, signal),
  searchDocumentSources: (id: string, filters: ProjectDocumentSourceFilters = {}, signal?: AbortSignal) => api.get<ProjectDocumentSourcePage>(withQuery(`${BASE}/${id}/document-sources`, filters), undefined, signal),
  attachDocument: (id: string, input: ProjectDocumentInput, key: string) => api.post<ProjectDocument>(`${BASE}/${id}/documents`, input, keyHeader(key)),
  detachDocument: (id: string, link: number, key: string) => api.delete<ProjectDocument>(`${BASE}/${id}/documents/${link}`, undefined, keyHeader(key)),

  listNotes: (id: string, query: HistoryPageQuery = {}, signal?: AbortSignal) => api.get<HistoryPage>(withQuery(`${BASE}/${id}/notes`, query), undefined, signal),
  appendNote: (id: string, input: NoteInput) => api.post<HistoryItem>(`${BASE}/${id}/notes`, input),
  listActivity: (id: string, query: HistoryCursorQuery = {}, signal?: AbortSignal) => api.get<HistoryCursorPage>(withQuery(`${BASE}/${id}/activities`, query), undefined, signal),
  listDocumentNotes: (series: string, query: HistoryPageQuery = {}, signal?: AbortSignal) => api.get<HistoryPage>(withQuery(`${SERIES}/${series}/notes`, query), undefined, signal),
  appendDocumentNote: (series: string, input: NoteInput) => api.post<HistoryItem>(`${SERIES}/${series}/notes`, input),
};
