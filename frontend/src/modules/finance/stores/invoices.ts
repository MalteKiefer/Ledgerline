import { defineStore } from 'pinia';
import { ref } from 'vue';
import { ApiError } from '@spa/api/client';
import { invoiceApi, invoiceErrorCode, type InvoiceErrorCode } from '@spa/modules/finance/api/invoices';
import type {
  Invoice,
  InvoiceDelivery,
  InvoiceDraftInput,
  InvoiceListFilters,
  InvoicePage,
  InvoiceRevision,
} from '@spa/modules/finance/models/invoice';

type StoreError = InvoiceErrorCode | 'request_failed';

const emptyPage = (): InvoicePage => ({
  data: [],
  links: { first: '', last: '', prev: null, next: null },
  meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
});

export const useInvoicesStore = defineStore('finance-v2-invoices', () => {
  const items = ref<Invoice[]>([]);
  const page = ref<InvoicePage>(emptyPage());
  const current = ref<Invoice | null>(null);
  const currentEtag = ref<string | null>(null);
  const revisions = ref<InvoiceRevision[]>([]);
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

  function failure(error: unknown): StoreError {
    return invoiceErrorCode(error) ?? 'request_failed';
  }

  function upsert(invoice: Invoice): void {
    const index = items.value.findIndex(({ id }) => id === invoice.id);
    if (index === -1) items.value = [invoice, ...items.value];
    else items.value = items.value.map((item, offset) => offset === index ? invoice : item);
    page.value = { ...page.value, data: items.value };
  }

  function select(invoice: Invoice, etag: string | null = `"${invoice.version}"`): Invoice {
    current.value = invoice;
    currentEtag.value = etag;
    upsert(invoice);

    return invoice;
  }

  async function loadList(filters: InvoiceListFilters): Promise<void> {
    listController?.abort();
    const controller = new AbortController();
    listController = controller;
    const sequence = ++listSequence;
    listLoading.value = true;
    listError.value = null;
    try {
      const result = await invoiceApi.list(filters, controller.signal);
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

  async function loadInvoice(id: string): Promise<Invoice> {
    detailController?.abort();
    const controller = new AbortController();
    detailController = controller;
    const sequence = ++detailSequence;
    detailLoading.value = true;
    detailError.value = null;
    try {
      const response = await invoiceApi.showResponse(id, controller.signal);
      if (sequence === detailSequence) select(response.data, response.etag);

      return response.data;
    } catch (error) {
      if (sequence === detailSequence && ! isAbort(error)) detailError.value = failure(error);
      throw error;
    } finally {
      if (sequence === detailSequence) detailLoading.value = false;
    }
  }

  async function loadRevisions(id: string): Promise<InvoiceRevision[]> {
    const result = await invoiceApi.revisions(id);
    revisions.value = result;

    return result;
  }

  /**
   * A 409 conflict's response body carries the server's actual current
   * aggregate (`{error, current}`) even though the invoice module's error
   * codes are more specific than the generic 'version_conflict' the API
   * client auto-parses into a VersionConflict — so this reads `current`
   * straight from the ApiError body instead. Without this the store would
   * keep showing the stale locally-loaded invoice after a conflict, and an
   * explicit "load server version" action would silently reload the SAME
   * stale data instead of the version that actually won.
   */
  function applyConflict(error: unknown): void {
    if (! (error instanceof ApiError) || ! error.body || typeof error.body !== 'object' || ! ('current' in error.body)) return;
    const current = (error.body as { current?: unknown }).current;
    if (isInvoice(current)) select(current);
  }

  async function act<T>(operation: () => Promise<T>): Promise<T> {
    actionLoading.value = true;
    actionError.value = null;
    try {
      const result = await operation();
      if (isInvoice(result)) select(result);

      return result;
    } catch (error) {
      applyConflict(error);
      actionError.value = failure(error);
      throw error;
    } finally {
      actionLoading.value = false;
    }
  }

  function create(input: InvoiceDraftInput): Promise<Invoice> {
    return act(() => invoiceApi.create(input));
  }

  function update(id: string, version: number, input: InvoiceDraftInput): Promise<Invoice> {
    return act(() => invoiceApi.update(id, version, input));
  }

  function destroy(id: string, version: number): Promise<void> {
    return act(() => invoiceApi.destroy(id, version));
  }

  function finalize(id: string, key: string): Promise<Invoice> {
    return act(() => invoiceApi.finalize(id, key));
  }

  function cancel(id: string, key: string): Promise<Invoice> {
    return act(() => invoiceApi.cancel(id, key));
  }

  function deliver(id: string, recipient: string | null, key: string): Promise<InvoiceDelivery> {
    return act(() => invoiceApi.deliver(id, recipient, key));
  }

  function remind(id: string, level: 1 | 2 | 3, recipient: string | null, key: string): Promise<InvoiceDelivery> {
    return act(() => invoiceApi.remind(id, level, recipient, key));
  }

  return {
    items, page, current, currentEtag, revisions,
    listLoading, detailLoading, actionLoading,
    listError, detailError, actionError,
    loadList, loadInvoice, loadRevisions,
    create, update, destroy, finalize, cancel, deliver, remind,
  };
});

function isAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

function isInvoice(value: unknown): value is Invoice {
  return value !== null
    && typeof value === 'object'
    && 'id' in value
    && typeof (value as { id?: unknown }).id === 'string'
    && 'version' in value
    && typeof (value as { version?: unknown }).version === 'number';
}
