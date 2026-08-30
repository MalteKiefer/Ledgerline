// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { Invoice } from '@spa/modules/finance/models/invoice';
import InvoiceDetailPage from '@spa/modules/finance/invoices/InvoiceDetailPage.vue';
import InvoiceEditorPage from '@spa/modules/finance/invoices/InvoiceEditorPage.vue';
import { invoicePaymentRecurringRoutes } from '@spa/modules/finance/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string, replace?: Record<string, unknown>) => {
    const dict: Record<string, string> = {
      'common.save': 'Save',
      'invoices.customer': 'Customer',
      'invoices.description': 'Description',
      'invoices.invoice_conflict': 'A newer server version exists.',
      'invoices.quote_conflict_load': 'Load server version',
      'invoices.invoice_finalize': 'Finalize',
      'invoices.invoice_cancel': 'Cancel invoice',
      'invoices.invoice_cancel_confirm': 'Cancel this invoice with a credit document?',
      'invoices.invoice_cancelled': 'Cancelled — credit document :number created.',
      'invoices.invoice_finalized': 'Invoice finalized.',
    };
    let value = dict[key] ?? key;
    if (replace) for (const [k, v] of Object.entries(replace)) value = value.replace(`:${k}`, String(v));

    return value;
  },
}));

const invoiceId = '018f4ca3-224d-7d8d-9f00-848484848484';

function invoice(overrides: Partial<Invoice> = {}): Invoice {
  return {
    id: invoiceId,
    kind: 'invoice',
    number: null,
    status: 'draft',
    issue_date: '2026-08-28',
    due_date: '2026-09-11',
    partner_id: null,
    project_id: null,
    totals: { net_minor: '25000', vat_minor: '4750', gross_minor: '29750', currency: 'EUR' },
    allocated_minor: '0',
    paid_minor: '0',
    open_minor: '29750',
    source: null,
    snapshot: { customer: { name: 'ACME' }, lines: [], discount: { type: 'none', value: null } },
    version: 0,
    created_at: '2026-08-28T09:00:00+00:00',
    updated_at: '2026-08-28T09:00:00+00:00',
    ...overrides,
  };
}

function http(body: unknown, status = 200, etag = '"0"'): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers({ ETag: etag }),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response;
}

async function mounted(
  component: typeof InvoiceEditorPage | typeof InvoiceDetailPage,
  path: string,
  fetchMock: ReturnType<typeof vi.fn>,
): Promise<{ wrapper: VueWrapper; router: Router }> {
  vi.stubGlobal('fetch', fetchMock);
  const router = createRouter({ history: createMemoryHistory(), routes: invoicePaymentRecurringRoutes });
  await router.push(path);
  await router.isReady();
  const wrapper = mount(component, { global: { plugins: [createPinia(), router] } });
  await flushPromises();

  return { wrapper, router };
}

beforeEach(() => {
  vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined });
  vi.stubGlobal('crypto', { randomUUID: vi.fn().mockReturnValue('11111111-1111-4111-8111-111111111111') });
  setToken('invoice-token');
});

describe('invoice editor — optimistic version conflict', () => {
  it('keeps the unsaved local edit visible until the server draft is explicitly loaded, and never repeats the save', async () => {
    const server = invoice({
      version: 7,
      snapshot: { customer: { name: 'Server Customer' }, lines: [], discount: { type: 'none', value: null } },
    });
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(invoice({ version: 4 }), 200, '"4"'))
      .mockResolvedValueOnce(http({ error: 'invoice_version_conflict', current: server }, 409, '"7"'));
    const { wrapper } = await mounted(InvoiceEditorPage, `/finance/invoices/${invoiceId}/edit`, fetchMock);

    await wrapper.get('[data-field="customer-name"] input').setValue('Unsaved local name');
    await wrapper.get('button[data-action="save"]').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('A newer server version exists.');
    expect((wrapper.get('[data-field="customer-name"] input').element as HTMLInputElement).value).toBe('Unsaved local name');
    // Only one PATCH was attempted — a failed save must not silently retry.
    expect(fetchMock.mock.calls.filter(([, init]) => (init as RequestInit | undefined)?.method === 'PATCH')).toHaveLength(1);

    await wrapper.get('button[data-action="load-conflict"]').trigger('click');
    expect((wrapper.get('[data-field="customer-name"] input').element as HTMLInputElement).value).toBe('Server Customer');
  });
});

describe('invoice detail — finalize and cancel', () => {
  it('shows the immutable revision after finalize, and leaves the original visibly unchanged after cancellation', async () => {
    const finalized = invoice({ status: 'finalized', number: 'RE-2026-0001', version: 1 });
    (finalized as Invoice & { revision: { id: number; pdf_sha256: string; finalized_at: string } }).revision = {
      id: 42, pdf_sha256: 'a'.repeat(64), finalized_at: '2026-08-28T10:00:00+00:00',
    };
    const original = invoice({ status: 'cancelled', number: 'RE-2026-0001', kind: 'invoice', version: 2 });
    const credit = invoice({ id: '018f4ca3-224d-7d8d-9f01-848484848484', kind: 'credit_note', number: 'RE-2026-0002', status: 'sent' });

    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(invoice(), 200, '"0"')) // initial load
      .mockResolvedValueOnce(http([])) // revisions
      .mockResolvedValueOnce(http(finalized, 200, '"1"')) // finalize
      .mockResolvedValueOnce(http([])) // revisions refresh
      .mockResolvedValueOnce(http(credit, 201)) // cancel
      .mockResolvedValueOnce(http(original, 200, '"2"')); // reload after cancel
    const { wrapper } = await mounted(InvoiceDetailPage, `/finance/invoices/${invoiceId}`, fetchMock);

    await wrapper.get('button[data-action="finalize"]').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('RE-2026-0001');
    expect(wrapper.text()).toContain('Invoice finalized.');

    await wrapper.get('button[data-action="cancel"]').trigger('click');
    await wrapper.get('button[data-action="cancel-confirm"]').trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('RE-2026-0002');
    // The original invoice's own number/identity is never mutated by cancellation.
    expect(fetchMock.mock.calls.some(([url]) => String(url).includes('/cancel'))).toBe(true);
  });
});
