import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { setToken } from '@spa/api/client';
import type { Quote, QuoteDraftInput, QuotePage, QuoteRevision } from '@spa/modules/finance/models/quote';
import { useQuotesStore } from '@spa/modules/finance/stores/quotes';

const quoteId = '018f4ca3-224d-7d8d-9f00-848484848484';

function response(status: number, body: unknown, etag = '"0"'): Response {
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: new Headers({ ETag: etag }),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response;
}

function quote(id = quoteId, version = 0, title = 'Quote'): Quote {
  return {
    id,
    status: 'draft',
    effective_status: 'draft',
    partner_id: null,
    number: null,
    version,
    has_pending_draft: true,
    current_revision: null,
    draft: null,
    totals: { net_minor: '10000', vat_minor: '1900', gross_minor: '11900', currency: 'EUR' },
    conversions: [],
    delivery: null,
    published_at: null,
    accepted_at: null,
    declined_at: null,
    converted_at: null,
    created_at: '2026-08-28T10:00:00+00:00',
    updated_at: title,
  };
}

function page(items: Quote[], currentPage = 1): QuotePage {
  return {
    data: items,
    links: { first: '/quotes?page=1', last: `/quotes?page=${currentPage}`, prev: null, next: null },
    meta: { current_page: currentPage, per_page: 20, total: items.length, last_page: currentPage },
  };
}

function draft(): QuoteDraftInput {
  return {
    title: 'Quote', partner_id: null, customer: { name: 'Ada GmbH' }, issue_date: null, valid_until: null,
    currency: 'EUR',
    lines: [{ description: 'Work', quantity: '1.0000', unit: 'hour', unit_price: '100.00', tax_rate: '19.00', kind: 'service', product_id: null }],
    discount_type: 'none', discount_value: null, intro_text: null, outro_text: null, internal_note: null,
  };
}

beforeEach(() => {
  setActivePinia(createPinia());
  const values: Record<string, string> = {};
  vi.stubGlobal('localStorage', {
    getItem: (key: string) => values[key] ?? null,
    setItem: (key: string, value: string) => { values[key] = value; },
    removeItem: (key: string) => { delete values[key]; },
  });
  setToken('quote-token');
});

describe('quotes store', () => {
  it('aborts superseded list requests, ignores stale responses, and replaces page data', async () => {
    let resolveOld!: (value: Response) => void;
    let resolveNew!: (value: Response) => void;
    const fetchMock = vi.fn()
      .mockImplementationOnce(() => new Promise<Response>((resolve) => { resolveOld = resolve; }))
      .mockImplementationOnce(() => new Promise<Response>((resolve) => { resolveNew = resolve; }));
    vi.stubGlobal('fetch', fetchMock);
    const store = useQuotesStore();

    const oldRequest = store.loadList({ q: 'old', page: 1 });
    const newRequest = store.loadList({ q: 'new', page: 2 });
    expect(((fetchMock.mock.calls[0][1] as RequestInit).signal as AbortSignal).aborted).toBe(true);
    resolveNew(response(200, page([quote('new-id', 2, 'new')], 2)));
    await newRequest;
    resolveOld(response(200, page([quote('old-id', 1, 'old')])));
    await oldRequest;

    expect(store.items.map(({ id }) => id)).toEqual(['new-id']);
    expect(store.page.meta.current_page).toBe(2);
    expect(store.listLoading).toBe(false);
    expect(store.listError).toBeNull();
  });

  it('keeps list and detail state separate and upserts the current resource', async () => {
    let resolveList!: (value: Response) => void;
    const fetchMock = vi.fn()
      .mockImplementationOnce(() => new Promise<Response>((resolve) => { resolveList = resolve; }))
      .mockResolvedValueOnce(response(200, quote(quoteId, 4, 'detail'), '"4"'));
    vi.stubGlobal('fetch', fetchMock);
    const store = useQuotesStore();

    const listing = store.loadList({});
    await store.loadQuote(quoteId);
    expect(store.listLoading).toBe(true);
    expect(store.detailLoading).toBe(false);
    expect(store.current?.version).toBe(4);
    expect(store.currentEtag).toBe('"4"');
    expect(store.items).toEqual([quote(quoteId, 4, 'detail')]);
    resolveList(response(200, page([quote(quoteId, 3, 'stale-list')])));
    await listing;
    expect(store.current?.version).toBe(4);
    expect(store.items).toEqual([quote(quoteId, 4, 'detail')]);
    expect(store.page.data).toEqual([quote(quoteId, 4, 'detail')]);
  });

  it('binds revision history to its quote and ignores an older quote response', async () => {
    let resolveA!: (value: Response) => void;
    let resolveB!: (value: Response) => void;
    const revisionA = { id: 101 } as QuoteRevision;
    const revisionB = { id: 202 } as QuoteRevision;
    const fetchMock = vi.fn()
      .mockImplementationOnce(() => new Promise<Response>((resolve) => { resolveA = resolve; }))
      .mockImplementationOnce(() => new Promise<Response>((resolve) => { resolveB = resolve; }));
    vi.stubGlobal('fetch', fetchMock);
    const store = useQuotesStore();

    const requestA = store.loadRevisions('quote-a');
    const requestB = store.loadRevisions('quote-b');
    expect(((fetchMock.mock.calls[0][1] as RequestInit).signal as AbortSignal).aborted).toBe(true);
    resolveB(response(200, [revisionB]));
    await requestB;
    resolveA(response(200, [revisionA]));
    await requestA;

    expect(store.revisionsQuoteId).toBe('quote-b');
    expect(store.revisions).toEqual([revisionB]);
  });

  it('replaces current and list rows from a typed conflict without mixing error channels', async () => {
    const current = quote(quoteId, 7, 'server-current');
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response(409, { error: 'version_conflict', current }, '"7"')));
    const store = useQuotesStore();
    store.items = [quote(quoteId, 1, 'old')];

    await expect(store.updateDraft(quoteId, 1, draft())).rejects.toMatchObject({ version: 7, current });

    expect(store.current).toEqual(current);
    expect(store.currentEtag).toBe('"7"');
    expect(store.items).toEqual([current]);
    expect(store.actionError).toBe('version_conflict');
    expect(store.listError).toBeNull();
    expect(store.detailError).toBeNull();
  });

  it('reuses keys only for the same canonical action payload and releases them on success or cancellation', async () => {
    const randomUUID = vi.fn()
      .mockReturnValueOnce('11111111-1111-4111-8111-111111111111')
      .mockReturnValueOnce('22222222-2222-4222-8222-222222222222')
      .mockReturnValueOnce('33333333-3333-4333-8333-333333333333')
      .mockReturnValueOnce('44444444-4444-4444-8444-444444444444');
    vi.stubGlobal('crypto', { randomUUID });
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(response(500, { error: 'temporary' }))
      .mockResolvedValueOnce(response(500, { error: 'temporary' }))
      .mockResolvedValueOnce(response(200, quote(quoteId, 1)))
      .mockResolvedValueOnce(response(500, { error: 'temporary' }))
      .mockResolvedValueOnce(response(200, quote(quoteId, 2)));
    vi.stubGlobal('fetch', fetchMock);
    const store = useQuotesStore();

    await expect(store.publish(quoteId, 0, null)).rejects.toMatchObject({ status: 500 });
    await expect(store.publish(quoteId, 0, 'Customer correction')).rejects.toMatchObject({ status: 500 });
    await store.publish(quoteId, 0, 'Customer correction');
    await expect(store.publish(quoteId, 1, 'Second correction')).rejects.toMatchObject({ status: 500 });
    store.cancelAction('publish', quoteId);
    await store.publish(quoteId, 1, 'Second correction');

    const keys = fetchMock.mock.calls.map(([, init]) => ((init as RequestInit).headers as Record<string, string>)['Idempotency-Key']);
    expect(keys).toEqual([
      '11111111-1111-4111-8111-111111111111',
      '22222222-2222-4222-8222-222222222222',
      '22222222-2222-4222-8222-222222222222',
      '33333333-3333-4333-8333-333333333333',
      '44444444-4444-4444-8444-444444444444',
    ]);
    expect(randomUUID).toHaveBeenCalledTimes(4);
    expect(store.actionLoading).toBe(false);
    expect(store.actionError).toBeNull();
  });

  it('uses deterministic object-key ordering when identifying an action retry', async () => {
    const randomUUID = vi.fn().mockReturnValue('55555555-5555-4555-8555-555555555555');
    vi.stubGlobal('crypto', { randomUUID });
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(response(500, { error: 'temporary' }))
      .mockResolvedValueOnce(response(201, quote()));
    vi.stubGlobal('fetch', fetchMock);
    const store = useQuotesStore();
    const first = { ...draft(), customer: { name: 'Ada GmbH', email: 'billing@example.com' } };
    const reordered = { ...first, customer: { email: 'billing@example.com', name: 'Ada GmbH' } };

    await expect(store.create(first)).rejects.toMatchObject({ status: 500 });
    await store.create(reordered);

    const keys = fetchMock.mock.calls.map(([, init]) => ((init as RequestInit).headers as Record<string, string>)['Idempotency-Key']);
    expect(keys).toEqual([
      '55555555-5555-4555-8555-555555555555',
      '55555555-5555-4555-8555-555555555555',
    ]);
    expect(randomUUID).toHaveBeenCalledTimes(1);
  });
});
