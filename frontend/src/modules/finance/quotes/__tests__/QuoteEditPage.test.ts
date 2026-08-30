// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { Quote, QuoteDraft, QuotePreview } from '@spa/modules/finance/models/quote';
import QuoteEditPage from '@spa/modules/finance/quotes/QuoteEditPage.vue';
import { quoteRoutes } from '@spa/modules/finance/quotes/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string) => ({
    'common.save': 'Save',
    'invoices.quote_title': 'Subject',
    'invoices.partner': 'Business partner',
    'invoices.description': 'Description',
    'invoices.qty': 'Qty',
    'invoices.unit_price': 'Unit price',
    'invoices.quote_preview': 'Server preview',
    'invoices.quote_totals_stale': 'Totals are stale',
    'invoices.quote_publish': 'Publish',
    'invoices.quote_send': 'Send by mail',
    'invoices.quote_conflict': 'A newer server version exists.',
    'invoices.quote_conflict_load': 'Load server version',
  }[key] ?? key),
}));

const quoteId = '018f4ca3-224d-7d8d-9f00-848484848484';

function draft(title = 'Network refresh'): QuoteDraft {
  return {
    title,
    customer: { name: 'Ada GmbH', email: 'billing@example.com' },
    partner_id: null,
    issue_date: '2026-08-29',
    valid_until: '2026-09-28',
    currency: 'EUR',
    lines: [{
      description: 'Consulting', quantity: '1.0000', unit: 'hour', unit_price: '100.00', tax_rate: '19.00',
      kind: 'service', product_id: null, quantity_scaled: '10000', unit_price_minor: '10000', currency: 'EUR', tax_rate_basis_points: '1900',
    }],
    discount: { type: 'none', value: null, currency: 'EUR' },
    totals: { net_minor: '10000', vat_minor: '1900', gross_minor: '11900', discount_minor: '0', currency: 'EUR', tax_breakdowns: [] },
    intro_text: null,
    outro_text: null,
    internal_note: null,
  };
}

function quote(title = 'Network refresh', version = 4): Quote {
  return {
    id: quoteId,
    status: 'draft',
    effective_status: 'draft',
    partner_id: null,
    number: null,
    version,
    has_pending_draft: true,
    current_revision: null,
    draft: draft(title),
    totals: { net_minor: '10000', vat_minor: '1900', gross_minor: '11900', currency: 'EUR' },
    conversions: [],
    delivery: null,
    published_at: null,
    accepted_at: null,
    declined_at: null,
    converted_at: null,
    created_at: '2026-08-29T09:00:00+00:00',
    updated_at: '2026-08-29T09:00:00+00:00',
  };
}

const preview: QuotePreview = {
  net_minor: '9007199254740993',
  vat_minor: '1711367858400789',
  gross_minor: '10718567113141782',
  discount_minor: '0',
  currency: 'EUR',
  tax_breakdowns: [],
  issue_date: '2026-08-29',
  valid_until: '2026-09-28',
};

function http(body: unknown, status = 200, etag = '"4"'): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers({ ETag: etag }),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response;
}

async function mounted(path: string, fetchMock: ReturnType<typeof vi.fn>): Promise<{ wrapper: VueWrapper; router: Router }> {
  vi.stubGlobal('fetch', fetchMock);
  const router = createRouter({ history: createMemoryHistory(), routes: quoteRoutes });
  await router.push(path);
  await router.isReady();
  const wrapper = mount(QuoteEditPage, { global: { plugins: [createPinia(), router] } });
  await flushPromises();

  return { wrapper, router };
}

beforeEach(() => {
  vi.useFakeTimers();
  vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined });
  vi.stubGlobal('crypto', { randomUUID: vi.fn().mockReturnValue('11111111-1111-4111-8111-111111111111') });
  setToken('quote-token');
});

afterEach(() => vi.useRealTimers());

describe('QuoteEditPage', () => {
  it('keeps the last server preview visible and marks it stale until the debounced response arrives', async () => {
    let resolveSecond!: (response: Response) => void;
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(preview))
      .mockImplementationOnce(() => new Promise<Response>((resolve) => { resolveSecond = resolve; }));
    const { wrapper } = await mounted('/finance/quotes/new', fetchMock);
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    expect(wrapper.text()).toContain('107.185.671.131.417,82 €');

    await wrapper.get('[data-field="title"] input').setValue('Changed title');
    expect(wrapper.text()).toContain('Totals are stale');
    expect(wrapper.text()).toContain('107.185.671.131.417,82 €');

    await vi.advanceTimersByTimeAsync(350);
    resolveSecond(http({ ...preview, gross_minor: '11900' }));
    await flushPromises();
    expect(wrapper.text()).not.toContain('Totals are stale');
    expect(wrapper.text()).toContain('119,00 €');
  });

  it('saves before queuing delivery and never sends client-calculated control totals', async () => {
    const saved = quote('Saved', 5);
    const queued = { ...saved, status: 'sent' as const, effective_status: 'sent' as const, version: 6 };
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(quote()))
      .mockResolvedValueOnce(http(preview))
      .mockResolvedValueOnce(http(saved, 200, '"5"'))
      .mockResolvedValueOnce(http(queued, 202, '"6"'));
    const { wrapper, router } = await mounted(`/finance/quotes/${quoteId}/edit`, fetchMock);
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();

    await wrapper.get('[data-field="intro-text"]').setValue('Exact opening text');
    await wrapper.get('button[data-action="send"]').trigger('click');
    await flushPromises();

    const mutations = fetchMock.mock.calls.filter(([url, init]) => String(url) !== '/api/v1/finance-v2/quotes/preview'
      && ['PUT', 'POST'].includes(String((init as RequestInit).method)));
    expect(mutations.map(([url]) => String(url))).toEqual([
      `/api/v1/finance-v2/quotes/${quoteId}/draft`,
      `/api/v1/finance-v2/quotes/${quoteId}/send`,
    ]);
    const saveBody = JSON.parse(String((mutations[0][1] as RequestInit).body)) as Record<string, unknown>;
    expect(saveBody).not.toHaveProperty('control_net_minor');
    expect(saveBody).not.toHaveProperty('control_vat_minor');
    expect(saveBody).not.toHaveProperty('control_gross_minor');
    expect(saveBody.intro_text).toBe('Exact opening text');
    await vi.waitFor(() => expect(router.currentRoute.value.fullPath).toBe(`/finance/quotes/${quoteId}`));
  });

  it.each(['publish', 'send'] as const)('stops %s after a version conflict until the server draft is explicitly loaded', async (action) => {
    const server = quote('Server title', 7);
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(quote('Local title', 4)))
      .mockResolvedValueOnce(http(preview))
      .mockResolvedValueOnce(http({ error: 'version_conflict', current: server }, 409, '"7"'));
    const { wrapper } = await mounted(`/finance/quotes/${quoteId}/edit`, fetchMock);
    await vi.advanceTimersByTimeAsync(350);
    await flushPromises();
    await wrapper.get('[data-field="title"] input').setValue('Unsaved local title');

    await wrapper.get(`button[data-action="${action}"]`).trigger('click');
    await flushPromises();

    expect(fetchMock.mock.calls.some(([url]) => String(url).endsWith(`/${action}`))).toBe(false);
    expect(wrapper.get('[role="alert"]').text()).toContain('A newer server version exists.');
    expect((wrapper.get('[data-field="title"] input').element as HTMLInputElement).value).toBe('Unsaved local title');
    await wrapper.get('button[data-action="load-conflict"]').trigger('click');
    expect((wrapper.get('[data-field="title"] input').element as HTMLInputElement).value).toBe('Server title');
  });
});
