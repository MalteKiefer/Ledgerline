import { reactive } from 'vue';
import { projectApi, projectErrorCode, type ProjectErrorCode } from '@spa/modules/finance/api/projectApi';
import type {
  LedgerEntry, LedgerEntryInput, LedgerFilters, OffsetPage, Project, ProjectTotals, TimeEntry, TimeEntryInput, WorkItem, WorkItemInput,
} from '@spa/modules/finance/models/project';
import type { HistoryCursorPage, HistoryPage, NoteInput } from '@spa/modules/finance/models/history';
import type { ProjectDocumentFilters, ProjectDocumentInput, ProjectDocumentPage } from '@spa/modules/finance/models/projectDocument';
import { useProjectsStore } from '@spa/modules/finance/stores/projects';

type DetailError = ProjectErrorCode | 'request_failed';
export type ProjectPanelName = 'project' | 'totals' | 'work' | 'time' | 'ledger' | 'documents' | 'notes' | 'activity';
interface Panel<T, Q> { data: T | null; query: Q; loading: boolean; error: DetailError | null; nextCursor?: string | null; etag?: string | null }
interface PageQuery { page: number; per_page: number }
interface ActivityQuery { cursor: string | null; per_page: number }

const allPanels: ProjectPanelName[] = ['project', 'totals', 'work', 'time', 'ledger', 'documents', 'notes', 'activity'];
const pageQuery = (): PageQuery => ({ page: 1, per_page: 20 });

export function useProjectDetail() {
  const projects = useProjectsStore();
  const state = reactive({ projectId: null as string | null, actionLoading: false, actionError: null as DetailError | null });
  const project = panel<Project, Record<string, never>>({});
  const totals = panel<ProjectTotals, Record<string, never>>({});
  const work = panel<OffsetPage<WorkItem>, PageQuery>(pageQuery());
  const time = panel<OffsetPage<TimeEntry>, PageQuery>(pageQuery());
  const ledger = panel<OffsetPage<LedgerEntry>, LedgerFilters & PageQuery>({ ...pageQuery(), direction: null, from: null, to: null, category_reference: null });
  const documents = panel<ProjectDocumentPage, ProjectDocumentFilters & PageQuery>({ ...pageQuery(), state: 'active' });
  const notes = panel<HistoryPage, PageQuery>(pageQuery());
  const activity = panel<HistoryCursorPage, ActivityQuery>({ cursor: null, per_page: 20 });
  activity.nextCursor = null;

  const controllers = new Map<ProjectPanelName, AbortController>();
  const sequences = new Map<ProjectPanelName, number>();
  let actionCount = 0;

  function ensureProject(id: string): void {
    if (state.projectId === null) state.projectId = id;
    else if (state.projectId !== id) switchProject(id);
  }

  function switchProject(id: string): void {
    for (const controller of controllers.values()) controller.abort();
    controllers.clear();
    for (const name of allPanels) sequences.set(name, (sequences.get(name) ?? 0) + 1);
    state.projectId = id;
    reset(project, {}); reset(totals, {}); reset(work, pageQuery()); reset(time, pageQuery());
    reset(ledger, { ...pageQuery(), direction: null, from: null, to: null, category_reference: null });
    reset(documents, { ...pageQuery(), state: 'active' }); reset(notes, pageQuery()); reset(activity, { cursor: null, per_page: 20 });
    activity.nextCursor = null;
  }

  async function load<T, Q>(name: ProjectPanelName, target: Panel<T, Q>, id: string, operation: (signal: AbortSignal) => Promise<T>, onSuccess?: (result: T) => void): Promise<void> {
    ensureProject(id);
    controllers.get(name)?.abort();
    const controller = new AbortController();
    const sequence = (sequences.get(name) ?? 0) + 1;
    sequences.set(name, sequence);
    controllers.set(name, controller);
    target.loading = true;
    target.error = null;
    try {
      const result = await operation(controller.signal);
      if (sequences.get(name) !== sequence || state.projectId !== id) return;
      target.data = result;
      if (name === 'activity') target.nextCursor = (result as HistoryCursorPage).next_cursor;
      onSuccess?.(result);
    } catch (error) {
      if (sequences.get(name) !== sequence || isAbort(error)) return;
      target.error = projectErrorCode(error) ?? 'request_failed';
      throw error;
    } finally {
      if (sequences.get(name) === sequence) target.loading = false;
    }
  }

  const loadProject = (id: string) => {
    let etag: string | null = null;
    return load('project', project, id, async () => {
      const result = await projects.loadProject(id);
      etag = projects.currentEtag;
      return result;
    }, () => { project.etag = etag; });
  };
  const loadTotals = (id: string) => load('totals', totals, id, (signal) => projectApi.getTotals(id, signal));
  const loadWork = (id: string) => load('work', work, id, (signal) => projectApi.listWorkItems(id, { ...work.query }, signal));
  const loadTime = (id: string) => load('time', time, id, (signal) => projectApi.listTimeEntries(id, { ...time.query }, signal));
  const loadLedger = (id: string) => load('ledger', ledger, id, (signal) => projectApi.listLedger(id, { ...ledger.query }, signal));
  const loadDocuments = (id: string) => load('documents', documents, id, (signal) => projectApi.listDocuments(id, { ...documents.query }, signal));
  const loadNotes = (id: string) => load('notes', notes, id, (signal) => projectApi.listNotes(id, { ...notes.query }, signal));
  const loadActivity = (id: string) => load('activity', activity, id, (signal) => projectApi.listActivity(id, { ...activity.query }, signal));

  const loaders: Record<ProjectPanelName, (id: string) => Promise<void>> = { project: loadProject, totals: loadTotals, work: loadWork, time: loadTime, ledger: loadLedger, documents: loadDocuments, notes: loadNotes, activity: loadActivity };

  async function open(id: string, requested: ProjectPanelName[] = allPanels): Promise<void> {
    if (state.projectId !== id) switchProject(id);
    await Promise.allSettled(requested.map((name) => loaders[name](id)));
  }
  async function refresh(...requested: ProjectPanelName[]): Promise<void> {
    if (! state.projectId) return;
    await Promise.allSettled(requested.map((name) => loaders[name](state.projectId as string)));
  }

  async function act<T>(operation: () => Promise<T>, refreshPanels: ProjectPanelName[]): Promise<T> {
    actionCount++;
    state.actionLoading = true;
    state.actionError = null;
    try {
      const result = await operation();
      await refresh(...refreshPanels);
      return result;
    } catch (error) {
      state.actionError = projectErrorCode(error) ?? 'request_failed';
      throw error;
    } finally {
      actionCount--;
      state.actionLoading = actionCount > 0;
    }
  }
  function id(): string { if (! state.projectId) throw new Error('project_not_open'); return state.projectId; }

  return reactive({
    get projectId() { return state.projectId; },
    get actionLoading() { return state.actionLoading; },
    get actionError() { return state.actionError; },
    project, totals, work, time, ledger, documents, notes, activity,
    open, refresh, loadProject, loadTotals, loadWork, loadTime, loadLedger, loadDocuments, loadNotes, loadActivity,
    createWork: (input: WorkItemInput) => act(() => projectApi.createWorkItem(id(), input), ['work', 'activity']),
    updateWork: (workId: string, input: WorkItemInput & { version: number }) => act(() => projectApi.updateWorkItem(id(), workId, input), ['work', 'activity']),
    deleteWork: (workId: string, version: number) => act(() => projectApi.deleteWorkItem(id(), workId, version), ['work', 'activity']),
    reorderWork: (ids: string[]) => act(() => projectApi.reorderWorkItems(id(), ids), ['work', 'activity']),
    createTime: (input: TimeEntryInput) => act(() => projectApi.createTimeEntry(id(), input), ['time', 'totals', 'activity']),
    updateTime: (entryId: string, input: TimeEntryInput & { version: number }) => act(() => projectApi.updateTimeEntry(id(), entryId, input), ['time', 'totals', 'activity']),
    deleteTime: (entryId: string, version: number) => act(() => projectApi.deleteTimeEntry(id(), entryId, version), ['time', 'totals', 'activity']),
    createLedger: (input: LedgerEntryInput) => act(() => projectApi.createLedgerEntry(id(), input), ['ledger', 'totals', 'activity']),
    updateLedger: (entryId: string, input: LedgerEntryInput & { version: number }) => act(() => projectApi.updateLedgerEntry(id(), entryId, input), ['ledger', 'totals', 'activity']),
    deleteLedger: (entryId: string, version: number) => act(() => projectApi.deleteLedgerEntry(id(), entryId, version), ['ledger', 'totals', 'activity']),
    createInvoiceDraft: (timeIds: string[]) => act(() => projects.createInvoiceDraft(id(), timeIds), ['time', 'totals', 'documents', 'activity']),
    attachDocument: (input: ProjectDocumentInput) => act(() => projects.attachDocument(id(), input), ['documents', 'activity']),
    detachDocument: (linkId: number) => act(() => projects.detachDocument(id(), linkId), ['documents', 'activity']),
    appendNote: (input: NoteInput) => act(() => projectApi.appendNote(id(), input), ['notes', 'activity']),
  });
}

function panel<T, Q>(query: Q): Panel<T, Q> { return reactive({ data: null, query, loading: false, error: null }) as Panel<T, Q>; }
function reset<T, Q>(target: Panel<T, Q>, query: Q): void { target.data = null; target.query = query; target.loading = false; target.error = null; target.nextCursor = null; target.etag = null; }
function isAbort(error: unknown): boolean { return error instanceof DOMException && error.name === 'AbortError'; }
