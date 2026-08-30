import { defineStore } from 'pinia';
import { ref } from 'vue';
import { VersionConflict } from '@spa/api/client';
import { projectApi, projectErrorCode, type ProjectErrorCode } from '@spa/modules/finance/api/projectApi';
import type { InvoiceDraftTarget, Project, ProjectInput, ProjectListFilters, ProjectPage, ProjectUpdateInput } from '@spa/modules/finance/models/project';
import type { ProjectDocument, ProjectDocumentInput } from '@spa/modules/finance/models/projectDocument';

type StoreError = ProjectErrorCode | 'request_failed';
export type ProjectKeyedAction = 'invoice' | 'attach' | 'detach';
interface ActionKey { key: string; signature: string }
interface ScopedActionState { sequence: number; loading: boolean; error: StoreError | null }

const emptyPage = (): ProjectPage => ({ data: [], links: { first: '', last: '', prev: null, next: null }, meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 } });

export const useProjectsStore = defineStore('finance-v2-projects', () => {
  const items = ref<Project[]>([]);
  const page = ref<ProjectPage>(emptyPage());
  const current = ref<Project | null>(null);
  const currentEtag = ref<string | null>(null);
  const listLoading = ref(false);
  const detailLoading = ref(false);
  const actionLoading = ref(false);
  const listError = ref<StoreError | null>(null);
  const detailError = ref<StoreError | null>(null);
  const actionError = ref<StoreError | null>(null);

  let listController: AbortController | null = null;
  let detailController: AbortController | null = null;
  let listSequence = 0;
  let detailSequence = 0;
  let activeActions = 0;
  const actionKeys = new Map<string, ActionKey>();
  const scopedActions = new Map<string, ScopedActionState>();

  function actionState(action: string, id: string): { loading: boolean; error: StoreError | null } {
    const state = scopedActions.get(scope(action, id));
    return { loading: state?.loading ?? false, error: state?.error ?? null };
  }

  const failure = (error: unknown): StoreError => projectErrorCode(error) ?? 'request_failed';

  function upsert(project: Project): void {
    const index = items.value.findIndex(({ id }) => id === project.id);
    items.value = index < 0 ? [project, ...items.value] : items.value.map((item, position) => position === index ? project : item);
    page.value = { ...page.value, data: items.value };
  }

  function select(project: Project, etag: string | null = `"${project.version}"`): Project {
    current.value = project;
    currentEtag.value = etag;
    upsert(project);
    return project;
  }

  function applyConflict(error: unknown): void {
    if (error instanceof VersionConflict && isProject(error.current)) select(error.current, error.etag);
  }

  async function loadList(filters: ProjectListFilters = {}): Promise<void> {
    listController?.abort();
    const controller = new AbortController();
    const sequence = ++listSequence;
    listController = controller;
    listLoading.value = true;
    listError.value = null;
    try {
      const result = await projectApi.list(filters, controller.signal);
      if (sequence !== listSequence) return;
      const cached = new Map(items.value.map((project) => [project.id, project]));
      if (current.value) cached.set(current.value.id, current.value);
      const merged = result.data.map((project) => {
        const newer = cached.get(project.id);
        return newer && newer.version > project.version ? newer : project;
      });
      items.value = merged;
      page.value = { ...result, data: merged };
    } catch (error) {
      if (sequence !== listSequence || isAbort(error)) return;
      listError.value = failure(error);
      throw error;
    } finally {
      if (sequence === listSequence) listLoading.value = false;
    }
  }

  async function loadProject(id: string): Promise<Project> {
    detailController?.abort();
    const controller = new AbortController();
    const sequence = ++detailSequence;
    detailController = controller;
    if (current.value && current.value.id !== id) {
      current.value = null;
      currentEtag.value = null;
    }
    detailLoading.value = true;
    detailError.value = null;
    try {
      const response = await projectApi.showResponse(id, controller.signal);
      if (sequence === detailSequence) select(response.data, response.etag);
      return response.data;
    } catch (error) {
      if (sequence === detailSequence && ! isAbort(error)) detailError.value = failure(error);
      throw error;
    } finally {
      if (sequence === detailSequence) detailLoading.value = false;
    }
  }

  function scope(action: string, id: string): string { return `${action}:${id}`; }
  function actionKey(action: ProjectKeyedAction, id: string, payload: unknown): string {
    const name = scope(action, id);
    const signature = canonicalSerialize(payload);
    const existing = actionKeys.get(name);
    if (existing?.signature === signature) return existing.key;
    const key = globalThis.crypto.randomUUID();
    actionKeys.set(name, { key, signature });
    return key;
  }

  function cancelAction(action: ProjectKeyedAction, id: string): void {
    actionKeys.delete(scope(action, id));
    actionError.value = null;
  }

  async function act<T>(operation: () => Promise<T>, keyedScope?: string, stateScope?: string): Promise<T> {
    activeActions++;
    actionLoading.value = true;
    actionError.value = null;
    let scopedState: ScopedActionState | null = null;
    let mySequence = 0;
    if (stateScope) {
      scopedState = scopedActions.get(stateScope) ?? { sequence: 0, loading: false, error: null };
      scopedActions.set(stateScope, scopedState);
      mySequence = ++scopedState.sequence;
      scopedState.loading = true;
      scopedState.error = null;
    }
    try {
      const result = await operation();
      if (isProject(result)) select(result);
      if (! scopedState || mySequence === scopedState.sequence) {
        if (keyedScope) actionKeys.delete(keyedScope);
        if (scopedState) { scopedState.loading = false; scopedState.error = null; }
        actionError.value = null;
      }
      return result;
    } catch (error) {
      applyConflict(error);
      const code = failure(error);
      if (! scopedState || mySequence === scopedState.sequence) {
        if (scopedState) { scopedState.loading = false; scopedState.error = code; }
        actionError.value = code;
      }
      throw error;
    } finally {
      activeActions--;
      actionLoading.value = activeActions > 0;
    }
  }

  const create = (input: ProjectInput): Promise<Project> => act(() => projectApi.create(input), undefined, scope('create', 'new'));
  const update = (id: string, input: ProjectUpdateInput): Promise<Project> => act(() => projectApi.update(id, input), undefined, scope('update', id));
  const changeStatus = (id: string, version: number, status: Project['status']): Promise<Project> => act(() => projectApi.changeStatus(id, { version, status }), undefined, scope('changeStatus', id));
  const move = (id: string, version: number, parentId: string | null): Promise<Project> => act(() => projectApi.move(id, { version, parent_id: parentId }), undefined, scope('move', id));
  const archive = (id: string, version: number): Promise<Project> => act(() => projectApi.archive(id, version), undefined, scope('archive', id));
  const restore = (id: string, version: number): Promise<Project> => act(() => projectApi.restore(id, version), undefined, scope('restore', id));

  function createInvoiceDraft(id: string, timeEntryIds: string[]): Promise<InvoiceDraftTarget> {
    const input = { time_entry_ids: timeEntryIds };
    const name = scope('invoice', id);
    return act(() => projectApi.createInvoiceDraft(id, input, actionKey('invoice', id, input)), name, name);
  }
  function attachDocument(id: string, input: ProjectDocumentInput): Promise<ProjectDocument> {
    const name = scope('attach', id);
    return act(() => projectApi.attachDocument(id, input, actionKey('attach', id, input)), name, name);
  }
  function detachDocument(id: string, link: number): Promise<ProjectDocument> {
    const payload = { link };
    const name = scope('detach', id);
    return act(() => projectApi.detachDocument(id, link, actionKey('detach', id, payload)), name, name);
  }

  return {
    items, page, current, currentEtag, listLoading, detailLoading, actionLoading, listError, detailError, actionError,
    loadList, loadProject, create, update, changeStatus, move, archive, restore,
    createInvoiceDraft, attachDocument, detachDocument, cancelAction, actionState,
  };
});

function isAbort(error: unknown): boolean { return error instanceof DOMException && error.name === 'AbortError'; }
function isProject(value: unknown): value is Project {
  return value !== null && typeof value === 'object' && 'id' in value && typeof (value as { id?: unknown }).id === 'string'
    && 'version' in value && typeof (value as { version?: unknown }).version === 'number';
}
function canonicalSerialize(value: unknown): string { return JSON.stringify(canonicalValue(value)); }
function canonicalValue(value: unknown): unknown {
  if (Array.isArray(value)) return value.map((item) => item === undefined ? null : canonicalValue(item));
  if (value === null || typeof value !== 'object') return value;
  return Object.fromEntries(Object.entries(value).filter(([, item]) => item !== undefined)
    .sort(([left], [right]) => left < right ? -1 : left > right ? 1 : 0)
    .map(([key, item]) => [key, canonicalValue(item)]));
}
