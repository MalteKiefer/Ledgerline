import { defineStore } from 'pinia';
import { ref } from 'vue';
import { VersionConflict } from '@spa/api/client';
import { quoteApi, quoteErrorCode, type QuoteErrorCode } from '@spa/modules/finance/api/quoteApi';
import type {
  InvoiceDraftTarget,
  Quote,
  QuoteDecisionInput,
  QuoteDraftInput,
  QuoteListFilters,
  QuotePage,
  QuotePreview,
  QuoteRevision,
  QuoteSendResult,
} from '@spa/modules/finance/models/quote';

type StoreError = QuoteErrorCode | 'request_failed';
type KeyedAction = 'create' | 'publish' | 'send' | 'accept' | 'decline' | 'duplicate' | 'convert';
interface ActionKey { key: string; signature: string }

const emptyPage = (): QuotePage => ({
  data: [],
  links: { first: '', last: '', prev: null, next: null },
  meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
});

export const useQuotesStore = defineStore('finance-v2-quotes', () => {
  const items = ref<Quote[]>([]);
  const page = ref<QuotePage>(emptyPage());
  const current = ref<Quote | null>(null);
  const currentEtag = ref<string | null>(null);
  const revisions = ref<QuoteRevision[]>([]);
  const revisionsQuoteId = ref<string | null>(null);
  const listLoading = ref(false);
  const detailLoading = ref(false);
  const actionLoading = ref(false);
  const listError = ref<StoreError | null>(null);
  const detailError = ref<StoreError | null>(null);
  const actionError = ref<StoreError | null>(null);

  let listController: AbortController | null = null;
  let detailController: AbortController | null = null;
  let revisionsController: AbortController | null = null;
  let listSequence = 0;
  let detailSequence = 0;
  let revisionsSequence = 0;
  const actionKeys = new Map<string, ActionKey>();

  function failure(error: unknown): StoreError {
    return quoteErrorCode(error) ?? 'request_failed';
  }

  function upsert(quote: Quote): void {
    const index = items.value.findIndex(({ id }) => id === quote.id);
    if (index === -1) items.value = [quote, ...items.value];
    else items.value = items.value.map((item, offset) => offset === index ? quote : item);
    page.value = { ...page.value, data: items.value };
  }

  function select(quote: Quote, etag: string | null = `"${quote.version}"`): Quote {
    current.value = quote;
    currentEtag.value = etag;
    upsert(quote);

    return quote;
  }

  function applyConflict(error: unknown): void {
    if (error instanceof VersionConflict && isQuote(error.current)) select(error.current, error.etag);
  }

  async function loadList(filters: QuoteListFilters): Promise<void> {
    listController?.abort();
    const controller = new AbortController();
    listController = controller;
    const sequence = ++listSequence;
    listLoading.value = true;
    listError.value = null;
    try {
      const result = await quoteApi.list(filters, controller.signal);
      if (sequence !== listSequence) return;
      const cached = new Map(items.value.map((quote) => [quote.id, quote]));
      if (current.value) {
        const listed = cached.get(current.value.id);
        if (! listed || current.value.version > listed.version) cached.set(current.value.id, current.value);
      }
      const merged = result.data.map((quote) => {
        const newer = cached.get(quote.id);

        return newer && newer.version > quote.version ? newer : quote;
      });
      page.value = { ...result, data: merged };
      items.value = merged;
    } catch (error) {
      if (sequence !== listSequence || isAbort(error)) return;
      listError.value = failure(error);
      throw error;
    } finally {
      if (sequence === listSequence) listLoading.value = false;
    }
  }

  async function loadQuote(id: string): Promise<Quote> {
    detailController?.abort();
    const controller = new AbortController();
    detailController = controller;
    const sequence = ++detailSequence;
    detailLoading.value = true;
    detailError.value = null;
    try {
      const response = await quoteApi.showResponse(id, controller.signal);
      const quote = response.data;
      if (sequence === detailSequence) select(quote, response.etag);

      return quote;
    } catch (error) {
      if (sequence === detailSequence && ! isAbort(error)) detailError.value = failure(error);
      throw error;
    } finally {
      if (sequence === detailSequence) detailLoading.value = false;
    }
  }

  async function loadRevisions(id: string): Promise<QuoteRevision[]> {
    revisionsController?.abort();
    const controller = new AbortController();
    revisionsController = controller;
    const sequence = ++revisionsSequence;
    const result = await quoteApi.revisions(id, controller.signal);
    if (sequence === revisionsSequence) {
      revisions.value = result;
      revisionsQuoteId.value = id;
    }

    return result;
  }

  async function preview(input: QuoteDraftInput, signal?: AbortSignal): Promise<QuotePreview> {
    return quoteApi.preview(input, signal);
  }

  function scope(action: KeyedAction, id = 'new'): string {
    return `${action}:${id}`;
  }

  function actionKey(action: KeyedAction, payload: unknown, id?: string): string {
    const name = scope(action, id);
    const signature = canonicalSerialize(payload);
    const existing = actionKeys.get(name);
    if (existing?.signature === signature) return existing.key;
    const key = globalThis.crypto.randomUUID();
    actionKeys.set(name, { key, signature });

    return key;
  }

  function cancelAction(action: KeyedAction, id?: string): void {
    actionKeys.delete(scope(action, id));
    actionError.value = null;
  }

  async function act<T>(operation: () => Promise<T>, keyedScope?: string): Promise<T> {
    actionLoading.value = true;
    actionError.value = null;
    try {
      const result = await operation();
      if (keyedScope) actionKeys.delete(keyedScope);
      if (isQuote(result)) select(result);

      return result;
    } catch (error) {
      applyConflict(error);
      actionError.value = failure(error);
      throw error;
    } finally {
      actionLoading.value = false;
    }
  }

  function create(input: QuoteDraftInput): Promise<Quote> {
    const name = scope('create');

    return act(() => quoteApi.create(input, actionKey('create', input)), name);
  }

  function updateDraft(id: string, version: number, input: QuoteDraftInput): Promise<Quote> {
    return act(() => quoteApi.updateDraft(id, version, input));
  }

  function discardDraft(id: string, version: number): Promise<Quote> {
    return act(() => quoteApi.discardDraft(id, version));
  }

  function startVersion(id: string, version: number): Promise<Quote> {
    return act(() => quoteApi.startVersion(id, version));
  }

  function publish(id: string, version: number, changeReason: string | null): Promise<Quote> {
    const name = scope('publish', id);
    const input = { version, change_reason: changeReason };

    return act(() => quoteApi.publish(id, input, actionKey('publish', input, id)), name);
  }

  function send(id: string, version: number, recipient: string | null, changeReason: string | null): Promise<QuoteSendResult> {
    const name = scope('send', id);
    const input = { version, recipient, change_reason: changeReason };

    return act(async () => {
      const result = await quoteApi.send(id, input, actionKey('send', input, id));
      select(result.quote);

      return result;
    }, name);
  }

  function decide(action: 'accept' | 'decline', id: string, input: QuoteDecisionInput): Promise<Quote> {
    const name = scope(action, id);

    return act(() => quoteApi[action](id, input, actionKey(action, input, id)), name);
  }

  function duplicate(id: string, version: number, sourceRevisionId: number | null): Promise<Quote> {
    const name = scope('duplicate', id);
    const input = { version, source_revision_id: sourceRevisionId };

    return act(() => quoteApi.duplicate(id, input, actionKey('duplicate', input, id)), name);
  }

  function convertToInvoice(id: string, input: QuoteDecisionInput): Promise<InvoiceDraftTarget> {
    const name = scope('convert', id);

    return act(() => quoteApi.convertToInvoice(id, input, actionKey('convert', input, id)), name);
  }

  return {
    items, page, current, currentEtag, revisions, revisionsQuoteId,
    listLoading, detailLoading, actionLoading,
    listError, detailError, actionError,
    loadList, loadQuote, loadRevisions, preview,
    create, updateDraft, discardDraft, startVersion, publish, send,
    accept: (id: string, input: QuoteDecisionInput) => decide('accept', id, input),
    decline: (id: string, input: QuoteDecisionInput) => decide('decline', id, input),
    duplicate, convertToInvoice, cancelAction,
  };
});

function isAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

function isQuote(value: unknown): value is Quote {
  return value !== null
    && typeof value === 'object'
    && 'id' in value
    && typeof (value as { id?: unknown }).id === 'string'
    && 'version' in value
    && typeof (value as { version?: unknown }).version === 'number';
}

function canonicalSerialize(value: unknown): string {
  return JSON.stringify(canonicalValue(value));
}

function canonicalValue(value: unknown): unknown {
  if (Array.isArray(value)) return value.map((item) => item === undefined ? null : canonicalValue(item));
  if (value === null || typeof value !== 'object') return value;

  return Object.fromEntries(
    Object.entries(value)
      .filter(([, item]) => item !== undefined)
      .sort(([left], [right]) => left < right ? -1 : left > right ? 1 : 0)
      .map(([key, item]) => [key, canonicalValue(item)]),
  );
}
