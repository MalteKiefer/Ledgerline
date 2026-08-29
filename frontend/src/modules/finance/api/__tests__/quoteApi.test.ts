import { beforeEach, describe, expect, expectTypeOf, it, vi } from 'vitest';
import { ApiError, setToken } from '@spa/api/client';
import { quoteApi, quoteErrorCode } from '@spa/modules/finance/api/quoteApi';
import type { Quote, QuoteDraftInput, QuotePage, QuotePreview } from '@spa/modules/finance/models/quote';

const quoteId = '018f4ca3-224d-7d8d-9f00-848484848484';

function response(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: new Headers(headers),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response;
}

function draft(): QuoteDraftInput {
  return {
    title: 'Network refresh',
    partner_id: null,
    customer: { name: 'Ada GmbH', email: 'billing@example.com' },
    issue_date: '2026-08-28',
    valid_until: '2026-09-27',
    currency: 'EUR',
    lines: [{
      description: 'Consulting',
      quantity: '2.5000',
      unit: 'hour',
      unit_price: '100.00',
      tax_rate: '19.00',
      kind: 'service',
      product_id: null,
    }],
    discount_type: 'percent',
    discount_value: '10.00',
    intro_text: null,
    outro_text: null,
    internal_note: null,
    control_net_minor: '9007199254740993',
    control_vat_minor: '1711367858400789',
    control_gross_minor: '10718567113141782',
  };
}

function quote(): Quote {
  return {
    id: quoteId,
    status: 'draft',
    effective_status: 'draft',
    partner_id: null,
    number: null,
    version: 0,
    has_pending_draft: true,
    current_revision: null,
    draft: {
      title: 'Network refresh',
      partner_id: null,
      customer: { name: 'Ada GmbH', email: 'billing@example.com' },
      issue_date: '2026-08-28',
      valid_until: '2026-09-27',
      currency: 'EUR',
      lines: [{
        ...draft().lines[0],
        quantity_scaled: '9007199254740993',
        unit_price_minor: '9007199254740995',
        currency: 'EUR',
        tax_rate_basis_points: '1900',
      }],
      discount: { type: 'fixed', value: '10000000000000.00', minor: '1000000000000000', currency: 'EUR' },
      totals: {
        net_minor: '9007199254740993',
        vat_minor: '1711367858400789',
        gross_minor: '10718567113141782',
        discount_minor: '1000000000000000',
        currency: 'EUR',
        tax_breakdowns: [{
          tax_rate_basis_points: '1900',
          net_minor: '9007199254740993',
          vat_minor: '1711367858400789',
          gross_minor: '10718567113141782',
        }],
      },
      intro_text: null,
      outro_text: null,
      internal_note: null,
    },
    totals: {
      net_minor: '9007199254740993',
      vat_minor: '1711367858400789',
      gross_minor: '10718567113141782',
      currency: 'EUR',
    },
    conversions: [],
    delivery: null,
    published_at: null,
    accepted_at: null,
    declined_at: null,
    converted_at: null,
    created_at: '2026-08-28T10:00:00+00:00',
    updated_at: '2026-08-28T10:00:00+00:00',
  };
}

beforeEach(() => {
  const values: Record<string, string> = {};
  vi.stubGlobal('localStorage', {
    getItem: (key: string) => values[key] ?? null,
    setItem: (key: string, value: string) => { values[key] = value; },
    removeItem: (key: string) => { delete values[key]; },
  });
  setToken('quote-token');
});

describe('quoteApi', () => {
  it('sends exact decimal strings and preserves UUID and integer minor-unit responses', async () => {
    const preview: QuotePreview = {
      net_minor: '9007199254740993',
      vat_minor: '1711367858400789',
      gross_minor: '10718567113141782',
      discount_minor: '1000000000000000',
      currency: 'EUR',
      tax_breakdowns: [{
        tax_rate_basis_points: '1900',
        net_minor: '9007199254740993',
        vat_minor: '1711367858400789',
        gross_minor: '10718567113141782',
      }],
      issue_date: '2026-08-28',
      valid_until: '2026-09-27',
    };
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(response(200, preview))
      .mockResolvedValueOnce(response(201, quote()));
    vi.stubGlobal('fetch', fetchMock);

    expect(await quoteApi.preview(draft())).toEqual(preview);
    expect(await quoteApi.create(draft(), 'create-key')).toEqual(quote());

    const previewBody = JSON.parse(String((fetchMock.mock.calls[0][1] as RequestInit).body)) as QuoteDraftInput;
    const createHeaders = (fetchMock.mock.calls[1][1] as RequestInit).headers as Record<string, string>;
    expect(previewBody.lines[0]).toMatchObject({ quantity: '2.5000', unit_price: '100.00', tax_rate: '19.00' });
    expect(previewBody.control_gross_minor).toBe('10718567113141782');
    expect(typeof previewBody.lines[0].quantity).toBe('string');
    expect(createHeaders['Idempotency-Key']).toBe('create-key');
    expect(quote().id).toBe(quoteId);
    expect(quote().draft?.lines[0].quantity_scaled).toBe('9007199254740993');
    expect(quote().draft?.lines[0].unit_price_minor).toBe('9007199254740995');
    expect(preview.gross_minor).toBe('10718567113141782');
    expectTypeOf(preview.gross_minor).toEqualTypeOf<string>();
  });

  it('targets only the finance-v2 quote surface and forwards caller-owned idempotency keys', async () => {
    const page: QuotePage = {
      data: [quote()],
      links: { first: '/quotes?page=1', last: '/quotes?page=1', prev: null, next: null },
      meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
    };
    const fetchMock = vi.fn().mockImplementation((_url: string, init: RequestInit) => {
      if (init.method === 'POST' && String(_url).endsWith('/send')) return Promise.resolve(response(202, quote(), { ETag: '"0"' }));
      if (String(_url).includes('conversions/invoice')) return Promise.resolve(response(201, { target_reference: 'legacy-invoice:1', target_id: 1 }));
      if (String(_url).includes('?')) return Promise.resolve(response(200, page));
      return Promise.resolve(response(200, quote()));
    });
    vi.stubGlobal('fetch', fetchMock);

    await quoteApi.list({ q: 'router', status: 'sent', effective_status: 'expired', sort: 'published_at', direction: 'desc', page: 2, per_page: 10 });
    await quoteApi.show(quoteId);
    await quoteApi.updateDraft(quoteId, 0, draft());
    await quoteApi.discardDraft(quoteId, 0);
    await quoteApi.startVersion(quoteId, 0);
    await quoteApi.publish(quoteId, { version: 0, change_reason: null }, 'publish-key');
    const sent = await quoteApi.send(quoteId, { version: 0, recipient: null, change_reason: null }, 'send-key');
    await quoteApi.accept(quoteId, { version: 1, expected_revision_id: 7 }, 'accept-key');
    await quoteApi.decline(quoteId, { version: 1, expected_revision_id: 7 }, 'decline-key');
    await quoteApi.duplicate(quoteId, { version: 1, source_revision_id: 7 }, 'duplicate-key');
    await quoteApi.convertToInvoice(quoteId, { version: 2, expected_revision_id: 7 }, 'convert-key');

    expect(sent).toEqual({ quote: quote(), replayed: false, status: 202, etag: '"0"' });
    for (const [url] of fetchMock.mock.calls) {
      expect(String(url)).toMatch(/^\/api\/v1\/finance-v2\/quotes(?:[/?]|$)/);
    }
    const keyedCalls = fetchMock.mock.calls.filter(([, init]) => ((init as RequestInit).headers as Record<string, string>)['Idempotency-Key']);
    expect(keyedCalls.map(([, init]) => ((init as RequestInit).headers as Record<string, string>)['Idempotency-Key']))
      .toEqual(['publish-key', 'send-key', 'accept-key', 'decline-key', 'duplicate-key', 'convert-key']);
    expect(quoteApi.revisionPdfUrl(quoteId, 7, true)).toBe(`/api/v1/finance-v2/quotes/${quoteId}/revisions/7/pdf?download=1&_token=quote-token`);
  });

  it('marks a 200 send as an exact replay and exposes stable machine error codes', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response(200, quote(), { ETag: '"3"' })));

    await expect(quoteApi.send(quoteId, { version: 3, recipient: null, change_reason: null }, 'same-key'))
      .resolves.toEqual({ quote: quote(), replayed: true, status: 200, etag: '"3"' });
    expect(quoteErrorCode(new ApiError(409, { error: 'idempotency_key_reused' }))).toBe('idempotency_key_reused');
    expect(quoteErrorCode(new ApiError(422, { message: 'invalid', errors: {} }))).toBeNull();
  });
});
