import { defineStore } from 'pinia';
import { ref } from 'vue';
import { ApiError } from '@spa/api/client';
import { recurringApi, recurringErrorCode, type RecurringErrorCode } from '@spa/modules/finance/api/recurring';
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

type StoreError = RecurringErrorCode | 'request_failed';

const emptyTemplatePage = (): RecurringInvoiceTemplatePage => ({
  data: [],
  links: { first: '', last: '', prev: null, next: null },
  meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
});

const emptyRunPage = (): RecurringInvoiceRunPage => ({
  data: [],
  links: { first: '', last: '', prev: null, next: null },
  meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
});

export const useRecurringStore = defineStore('finance-v2-recurring', () => {
  const items = ref<RecurringInvoiceTemplate[]>([]);
  const page = ref<RecurringInvoiceTemplatePage>(emptyTemplatePage());
  const current = ref<RecurringInvoiceTemplate | null>(null);
  const runs = ref<RecurringInvoiceRun[]>([]);
  const runsPage = ref<RecurringInvoiceRunPage>(emptyRunPage());
  const listLoading = ref(false);
  const detailLoading = ref(false);
  const runsLoading = ref(false);
  const actionLoading = ref(false);
  const listError = ref<StoreError | null>(null);
  const detailError = ref<StoreError | null>(null);
  const actionError = ref<StoreError | null>(null);

  let listController: AbortController | null = null;
  let detailController: AbortController | null = null;
  let runsController: AbortController | null = null;
  let listSequence = 0;
  let detailSequence = 0;
  let runsSequence = 0;

  function failure(error: unknown): StoreError {
    return recurringErrorCode(error) ?? 'request_failed';
  }

  function upsert(template: RecurringInvoiceTemplate): void {
    const index = items.value.findIndex(({ id }) => id === template.id);
    if (index === -1) items.value = [template, ...items.value];
    else items.value = items.value.map((item, offset) => offset === index ? template : item);
    page.value = { ...page.value, data: items.value };
  }

  function select(template: RecurringInvoiceTemplate): RecurringInvoiceTemplate {
    current.value = template;
    upsert(template);

    return template;
  }

  /**
   * A 409 conflict's body carries the server's actual current template even
   * though the recurring module's error codes (e.g.
   * 'recurring_template_version_conflict') are more specific than the
   * generic 'version_conflict' the API client auto-parses into a
   * VersionConflict — so this reads `current` straight from the ApiError
   * body instead of relying on that generic parse ever firing.
   */
  function applyConflict(error: unknown): void {
    if (! (error instanceof ApiError) || ! error.body || typeof error.body !== 'object' || ! ('current' in error.body)) return;
    const current = (error.body as { current?: unknown }).current;
    if (isTemplate(current)) select(current);
  }

  async function loadList(filters: RecurringInvoiceTemplateListFilters): Promise<void> {
    listController?.abort();
    const controller = new AbortController();
    listController = controller;
    const sequence = ++listSequence;
    listLoading.value = true;
    listError.value = null;
    try {
      const result = await recurringApi.list(filters, controller.signal);
      if (sequence !== listSequence) return;
      page.value = result;
      items.value = result.data;
    } catch (error) {
      if (sequence !== listSequence || isAbort(error)) return;
      listError.value = failure(error);
      throw error;
    } finally {
      if (sequence === listSequence) listLoading.value = false;
    }
  }

  async function loadTemplate(id: string): Promise<RecurringInvoiceTemplate> {
    detailController?.abort();
    const controller = new AbortController();
    detailController = controller;
    const sequence = ++detailSequence;
    detailLoading.value = true;
    detailError.value = null;
    try {
      const template = await recurringApi.show(id, controller.signal);
      if (sequence === detailSequence) select(template);

      return template;
    } catch (error) {
      if (sequence === detailSequence && ! isAbort(error)) detailError.value = failure(error);
      throw error;
    } finally {
      if (sequence === detailSequence) detailLoading.value = false;
    }
  }

  async function loadRuns(templateId: string, filters: RecurringInvoiceRunListFilters = {}): Promise<void> {
    runsController?.abort();
    const controller = new AbortController();
    runsController = controller;
    const sequence = ++runsSequence;
    runsLoading.value = true;
    try {
      const result = await recurringApi.runs(templateId, filters, controller.signal);
      if (sequence !== runsSequence) return;
      runsPage.value = result;
      runs.value = result.data;
    } finally {
      if (sequence === runsSequence) runsLoading.value = false;
    }
  }

  async function act<T>(operation: () => Promise<T>): Promise<T> {
    actionLoading.value = true;
    actionError.value = null;
    try {
      const result = await operation();
      if (isTemplate(result)) select(result);

      return result;
    } catch (error) {
      applyConflict(error);
      actionError.value = failure(error);
      throw error;
    } finally {
      actionLoading.value = false;
    }
  }

  function create(input: RecurringInvoiceTemplateInput, key: string): Promise<RecurringInvoiceTemplate> {
    return act(() => recurringApi.create(input, key));
  }

  function addVersion(id: string, input: RecurringInvoiceTemplateVersionInput, key: string): Promise<RecurringInvoiceTemplate> {
    return act(() => recurringApi.addVersion(id, input, key));
  }

  function pause(id: string, expectedVersion: number, key: string): Promise<RecurringInvoiceTemplate> {
    return act(() => recurringApi.pause(id, expectedVersion, key));
  }

  function resume(id: string, expectedVersion: number, key: string): Promise<RecurringInvoiceTemplate> {
    return act(() => recurringApi.resume(id, expectedVersion, key));
  }

  async function retryRun(templateId: string, runId: string): Promise<RecurringInvoiceRun> {
    return act(async () => {
      const result = await recurringApi.retryRun(runId);
      await loadRuns(templateId);

      return result;
    });
  }

  return {
    items, page, current, runs, runsPage,
    listLoading, detailLoading, runsLoading, actionLoading,
    listError, detailError, actionError,
    loadList, loadTemplate, loadRuns,
    create, addVersion, pause, resume, retryRun,
  };
});

function isAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

function isTemplate(value: unknown): value is RecurringInvoiceTemplate {
  return value !== null
    && typeof value === 'object'
    && 'id' in value
    && typeof (value as { id?: unknown }).id === 'string'
    && 'version' in value
    && typeof (value as { version?: unknown }).version === 'number';
}
