// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { Quote, QuoteDelivery, QuoteRevision } from '@spa/modules/finance/models/quote';
import QuoteDetailPage from '@spa/modules/finance/quotes/QuoteDetailPage.vue';
import { quoteRoutes } from '@spa/modules/finance/quotes/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string) => ({
    'common.loading': 'Loading…',
    'common.download': 'Download',
    'invoices.quote_status_sent': 'Issued',
    'invoices.quote_status_accepted': 'Accepted',
    'invoices.quote_status_expired': 'Expired',
    'invoices.quote_pending_draft': 'A newer draft is pending.',
    'invoices.quote_delivery_queued': 'Queued for delivery',
    'invoices.quote_delivery_failed': 'Delivery failed',
    'invoices.quote_delivery_uncertain': 'Delivery outcome uncertain',
    'invoices.quote_delivery_sent': 'Delivered',
    'invoices.quote_revision_history': 'Revision history',
    'invoices.quote_revision_previous': 'Supersedes revision',
    'invoices.quote_send': 'Send by mail',
    'invoices.quote_send_retry': 'Retry send',
    'invoices.quote_accept': 'Accepted',
    'invoices.quote_decline': 'Declined',
    'invoices.quote_duplicate': 'Edit as a copy',
    'invoices.quote_to_invoice': 'Make an invoice',
    'invoices.quote_error': 'Quote action failed',
  }[key] ?? key),
}));

const quoteId = '018f4ca3-224d-7d8d-9f00-848484848484';
const duplicateId = '018f4ca3-224d-7d8d-9f00-858585858585';

function revision(id = 202, number = 2, previous: number | null = 101): QuoteRevision {
  return {
    id,
    revision_number: number,
    previous_revision_id: previous,
    status: 'published',
    snapshot: {
      schema_version: 1,
      document_type: 'quote',
      series_uuid: quoteId,
      document_number: 'Q-2026-0042',
      revision_number: number,
      revision_label: `Q-2026-0042-v${number}`,
      title: 'Network refresh',
      customer: { name: 'Ada GmbH', email: 'billing@example.com' },
      partner_id: null,
      issue_date: '2026-08-01',
      valid_until: '2026-08-20',
      currency: 'EUR',
      lines: [],
      discount: { type: 'none', value: null, currency: 'EUR' },
      totals: { net_minor: '10000', vat_minor: '1900', gross_minor: '11900', discount_minor: '0', currency: 'EUR', tax_breakdowns: [] },
      intro_text: null,
      outro_text: null,
      customer_note: null,
    },
    totals: { net_minor: '10000', vat_minor: '1900', gross_minor: '11900', currency: 'EUR' },
    pdf_sha256: 'a'.repeat(64),
    pdf_url: `/api/v1/finance-v2/quotes/${quoteId}/revisions/${id}/pdf`,
    pdf_download_url: `/api/v1/finance-v2/quotes/${quoteId}/revisions/${id}/pdf?download=1`,
    published_at: '2026-08-02T10:00:00+00:00',
    created_at: '2026-08-02T09:00:00+00:00',
  };
}

function delivery(state: QuoteDelivery['state']): QuoteDelivery {
  return {
    uuid: '018f4ca3-224d-7d8d-9f00-868686868686',
    revision_id: 202,
    state,
    attempts: state === 'failed' ? 1 : 0,
    last_error_code: state === 'failed' ? 'safe_pre_accept' : null,
    queued_at: '2026-08-02T10:01:00+00:00',
    sent_at: state === 'sent' ? '2026-08-02T10:02:00+00:00' : null,
    failed_at: state === 'failed' ? '2026-08-02T10:02:00+00:00' : null,
  };
}

function quote(overrides: Partial<Quote> = {}): Quote {
  return {
    id: quoteId,
    status: 'sent',
    effective_status: 'sent',
    partner_id: null,
    number: 'Q-2026-0042',
    version: 4,
    has_pending_draft: false,
    current_revision: revision(),
    draft: null,
    totals: { net_minor: '10000', vat_minor: '1900', gross_minor: '11900', currency: 'EUR' },
    conversions: [],
    delivery: null,
    published_at: '2026-08-02T10:00:00+00:00',
    accepted_at: null,
    declined_at: null,
    converted_at: null,
    created_at: '2026-08-01T09:00:00+00:00',
    updated_at: '2026-08-02T10:00:00+00:00',
    ...overrides,
  };
}

function http(body: unknown, status = 200, etag = '"4"'): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers({ ETag: etag }),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response;
}

async function mounted(fetchMock: ReturnType<typeof vi.fn>): Promise<{ wrapper: VueWrapper; router: Router }> {
  vi.stubGlobal('fetch', fetchMock);
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      ...quoteRoutes,
      { path: '/finance/:section?', name: 'finance', component: { template: '<div />' } },
    ],
  });
  await router.push(`/finance/quotes/${quoteId}`);
  await router.isReady();
  const wrapper = mount(QuoteDetailPage, { global: { plugins: [createPinia(), router] } });
  await flushPromises();

  return { wrapper, router };
}

beforeEach(() => {
  vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined });
  vi.stubGlobal('crypto', { randomUUID: vi.fn().mockReturnValue('11111111-1111-4111-8111-111111111111') });
  setToken('quote-token');
});

describe('QuoteDetailPage', () => {
  it('shows pending restrictions, immutable revision links, and only the latest known delivery truth', async () => {
    const pending = quote({ effective_status: 'expired', has_pending_draft: true, delivery: delivery('queued') });
    const { wrapper } = await mounted(vi.fn()
      .mockResolvedValueOnce(http(pending))
      .mockResolvedValueOnce(http([revision(), revision(101, 1, null)])));

    expect(wrapper.text()).toContain('Expired');
    expect(wrapper.text()).toContain('A newer draft is pending.');
    expect(wrapper.text()).toContain('Queued for delivery');
    expect(wrapper.text()).not.toContain('Delivered');
    expect(wrapper.text()).toContain('Q-2026-0042-v2');
    expect(wrapper.get('[data-revision="202"]').text()).toContain('Q-2026-0042');
    expect(wrapper.get('[data-revision="202"]').text()).toContain('Q-2026-0042-v2');
    expect(wrapper.text()).toContain('a'.repeat(64));
    expect(wrapper.text()).toContain('Supersedes revision #101');
    expect(wrapper.get(`a[href$="/revisions/202/pdf"]`).attributes('target')).toBe('_blank');
    expect(wrapper.get('button[data-action="accept"]').attributes('disabled')).toBeDefined();
  });

  it('retries a safe failed delivery with the same key and reports 202 as queued', async () => {
    const failed = quote({ delivery: delivery('failed') });
    const queued = quote({ version: 5, delivery: delivery('queued') });
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(failed))
      .mockResolvedValueOnce(http([revision()]))
      .mockResolvedValueOnce(http({ error: 'request_failed' }, 500))
      .mockResolvedValueOnce(http(queued, 202, '"5"'));
    const { wrapper } = await mounted(fetchMock);

    await wrapper.get('button[data-action="send"]').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('Delivery failed');
    await wrapper.get('button[data-action="send"]').trigger('click');
    await flushPromises();

    const sendCalls = fetchMock.mock.calls.filter(([url]) => String(url).endsWith('/send'));
    const keys = sendCalls.map(([, init]) => ((init as RequestInit).headers as Record<string, string>)['Idempotency-Key']);
    expect(keys).toEqual(['11111111-1111-4111-8111-111111111111', '11111111-1111-4111-8111-111111111111']);
    expect(wrapper.text()).toContain('Queued for delivery');
    expect(wrapper.text()).not.toContain('Delivered');
  });

  it('blocks resend when the transport outcome is uncertain', async () => {
    const uncertain = delivery('failed');
    uncertain.last_error_code = 'delivery_outcome_uncertain';
    const { wrapper } = await mounted(vi.fn()
      .mockResolvedValueOnce(http(quote({ delivery: uncertain })))
      .mockResolvedValueOnce(http([revision()])));

    expect(wrapper.text()).toContain('Delivery outcome uncertain');
    expect(wrapper.get('button[data-action="send"]').attributes('disabled')).toBeDefined();
  });

  it.each([
    ['accept', 'accepted', 'Accepted'],
    ['decline', 'declined', 'Declined'],
  ] as const)('applies %s to the exact current revision and disables repeat decisions', async (action, status, label) => {
    const decided = quote({ status, effective_status: status });
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(quote()))
      .mockResolvedValueOnce(http([revision()]))
      .mockResolvedValueOnce(http(decided, 200, '"5"'));
    const { wrapper } = await mounted(fetchMock);

    await wrapper.get(`button[data-action="${action}"]`).trigger('click');
    await flushPromises();

    expect(fetchMock.mock.calls.some(([url]) => String(url).endsWith(`/${action}`))).toBe(true);
    expect(wrapper.text()).toContain(label);
    expect(wrapper.get('button[data-action="accept"]').attributes('disabled')).toBeDefined();
    expect(wrapper.get('button[data-action="decline"]').attributes('disabled')).toBeDefined();
  });

  it('shows stable typed action errors without exposing a missing translation key', async () => {
    const { wrapper } = await mounted(vi.fn()
      .mockResolvedValueOnce(http(quote()))
      .mockResolvedValueOnce(http([revision()]))
      .mockResolvedValueOnce(http({ error: 'no_smtp' }, 422)));

    await wrapper.get('button[data-action="send"]').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toBe('Quote action failed (no_smtp)');
  });

  it('navigates duplicate and conversion results to their exact targets', async () => {
    const accepted = quote({ status: 'accepted', effective_status: 'accepted', accepted_at: '2026-08-03T10:00:00+00:00' });
    const duplicated = quote({ id: duplicateId, status: 'draft', effective_status: 'draft', number: null, current_revision: null });
    const first = await mounted(vi.fn()
      .mockResolvedValueOnce(http(accepted))
      .mockResolvedValueOnce(http([revision()]))
      .mockResolvedValueOnce(http(duplicated, 201)));
    await first.wrapper.get('button[data-action="duplicate"]').trigger('click');
    await vi.waitFor(() => expect(first.router.currentRoute.value.fullPath).toBe(`/finance/quotes/${duplicateId}/edit`));

    first.wrapper.unmount();
    const second = await mounted(vi.fn()
      .mockResolvedValueOnce(http(accepted))
      .mockResolvedValueOnce(http([revision()]))
      .mockResolvedValueOnce(http({ target_reference: 'legacy-invoice:42', target_id: 42 }, 201)));
    await second.wrapper.get('button[data-action="convert"]').trigger('click');
    await vi.waitFor(() => expect(second.router.currentRoute.value.fullPath).toBe('/finance/invoices?invoice=42'));
  });
});
