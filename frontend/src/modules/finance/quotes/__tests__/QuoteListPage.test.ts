// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { Quote, QuotePage } from '@spa/modules/finance/models/quote';
import QuoteListPage from '@spa/modules/finance/quotes/QuoteListPage.vue';
import { quoteRoutes } from '@spa/modules/finance/quotes/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string) => ({
    'common.next': 'Next',
    'invoices.quote_add': 'Add quote',
    'invoices.quote_search': 'Search quotes…',
    'invoices.quote_status_all': 'All states',
    'invoices.quote_status_expired': 'Expired',
    'invoices.quotes_empty': 'No quotes yet.',
  }[key] ?? key),
}));

const quoteId = '018f4ca3-224d-7d8d-9f00-848484848484';

function quote(): Quote {
  return {
    id: quoteId,
    status: 'sent',
    effective_status: 'expired',
    partner_id: null,
    number: 'Q-2026-0042',
    version: 4,
    has_pending_draft: false,
    current_revision: null,
    draft: null,
    totals: {
      net_minor: '9007199254740993',
      vat_minor: '1711367858400789',
      gross_minor: '10718567113141782',
      currency: 'EUR',
    },
    conversions: [],
    delivery: null,
    published_at: '2026-08-01T10:00:00+00:00',
    accepted_at: null,
    declined_at: null,
    converted_at: null,
    created_at: '2026-08-01T09:00:00+00:00',
    updated_at: '2026-08-01T10:00:00+00:00',
  };
}

function response(): Response {
  const page: QuotePage = {
    data: [quote()],
    links: { first: '/quotes?page=1', last: '/quotes?page=3', prev: '/quotes?page=1', next: '/quotes?page=3' },
    meta: { current_page: 2, per_page: 20, total: 41, last_page: 3 },
  };

  return {
    ok: true,
    status: 200,
    headers: new Headers(),
    text: () => Promise.resolve(JSON.stringify(page)),
  } as Response;
}

beforeEach(() => {
  document.documentElement.lang = 'de';
  vi.stubGlobal('localStorage', {
    getItem: () => null,
    setItem: () => undefined,
    removeItem: () => undefined,
  });
  setToken('quote-token');
});

describe('QuoteListPage', () => {
  it('owns filters in the URL and renders exact large totals with effective status', async () => {
    const fetchMock = vi.fn().mockResolvedValue(response());
    vi.stubGlobal('fetch', fetchMock);
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        ...quoteRoutes,
        { path: '/fallback', component: { template: '<div />' } },
      ],
    });
    await router.push('/finance/quotes?q=router&status=sent&effective_status=expired&page=2');
    await router.isReady();

    const wrapper = mount(QuoteListPage, { global: { plugins: [createPinia(), router] } });
    await flushPromises();

    expect(wrapper.get('input[type="search"]').element).toMatchObject({ value: 'router' });
    expect((wrapper.get('[data-filter="status"]').element as HTMLSelectElement).value).toBe('sent');
    expect((wrapper.get('[data-filter="effective-status"]').element as HTMLSelectElement).value).toBe('expired');
    expect(wrapper.text()).toContain('Expired');
    expect(wrapper.text()).toContain('107.185.671.131.417,82 €');
    expect(String(fetchMock.mock.calls[0][0])).toContain('effective_status=expired');

    await wrapper.get('input[type="search"]').setValue('changed');
    await flushPromises();
    expect(router.currentRoute.value.query).toMatchObject({ q: 'changed', page: '1' });
  });

  it('exports resolvable quote routes without mounting the global router', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: quoteRoutes });

    expect(router.resolve('/finance/quotes').name).toBe('finance.quotes.index');
    expect(router.resolve('/finance/quotes/new').name).toBe('finance.quotes.new');
    expect(router.resolve(`/finance/quotes/${quoteId}/edit`).name).toBe('finance.quotes.edit');
  });
});
