// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { setToken } from '@spa/api/client';
import type { Invoice } from '@spa/modules/finance/models/invoice';
import type { Payment, PaymentSuggestions } from '@spa/modules/finance/models/payment';
import PaymentDetailPage from '@spa/modules/finance/payments/PaymentDetailPage.vue';
import { invoicePaymentRecurringRoutes } from '@spa/modules/finance/routes';

vi.mock('laravel-vue-i18n', () => ({
  trans: (key: string) => ({
    'common.loading': 'Loading…',
    'invoices.payment_allocations': 'Allocations',
    'invoices.allocation_apply': 'Apply allocation',
    'invoices.allocation_add_line': 'Add line',
    'invoices.allocation_invoice_id': 'Invoice',
    'invoices.allocation_amount': 'Amount',
    'invoices.allocation_suggested': 'Suggested match',
    'invoices.allocation_ambiguous': 'Multiple candidates — choose one',
    'invoices.allocation_use_suggestion': 'Use',
    'invoices.allocation_requires_confirmation': 'Confirm before applying.',
    'invoices.open_minor': 'Open',
    'invoices.quote_line_remove': 'Remove',
  }[key] ?? key),
}));

const paymentId = '018f4ca3-224d-7d8d-9f00-111111111111';
const invoiceAId = '018f4ca3-224d-7d8d-9f01-111111111111';
const invoiceBId = '018f4ca3-224d-7d8d-9f02-111111111111';

function payment(overrides: Partial<Payment> = {}): Payment {
  return {
    id: paymentId,
    amount_minor: '25000',
    allocated_minor: '0',
    unapplied_minor: '25000',
    currency: 'EUR',
    received_at: '2026-08-28T10:00:00+00:00',
    reference: 'RE-2026-0001',
    counterparty: 'ACME',
    payment_method_id: null,
    source: null,
    version: 0,
    ...overrides,
  };
}

function invoiceStub(id: string, openMinor: string, status: Invoice['status'] = 'sent'): Invoice {
  return {
    id,
    kind: 'invoice',
    number: 'RE-2026-0001',
    status,
    issue_date: '2026-08-01',
    due_date: '2026-08-15',
    partner_id: null,
    project_id: null,
    totals: { net_minor: '15000', vat_minor: '2850', gross_minor: '17900', currency: 'EUR' },
    allocated_minor: String(17900 - Number(openMinor)),
    paid_minor: String(17900 - Number(openMinor)),
    open_minor: openMinor,
    source: null,
    snapshot: {},
    version: 1,
    created_at: '2026-08-01T09:00:00+00:00',
    updated_at: '2026-08-01T09:00:00+00:00',
  };
}

function http(body: unknown, status = 200): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response;
}

beforeEach(() => {
  vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => undefined, removeItem: () => undefined });
  vi.stubGlobal('crypto', { randomUUID: vi.fn().mockReturnValue('11111111-1111-4111-8111-111111111111') });
  setToken('payment-token');
});

describe('payment allocation', () => {
  it('shows ambiguous suggestions without mutating anything until the user picks one', async () => {
    const suggestions: PaymentSuggestions = {
      status: 'ambiguous',
      requires_confirmation: true,
      candidates: [
        { invoice_id: invoiceAId, number: 'RE-2026-0001', open_minor: '17900', currency: 'EUR', score: 100, reason: 'exact_currency_and_remaining' },
        { invoice_id: invoiceBId, number: 'RE-2026-0002', open_minor: '7100', currency: 'EUR', score: 100, reason: 'exact_currency_and_remaining' },
      ],
    };
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(payment()))
      .mockResolvedValueOnce(http(suggestions));
    vi.stubGlobal('fetch', fetchMock);
    const router = createRouter({ history: createMemoryHistory(), routes: invoicePaymentRecurringRoutes });
    await router.push(`/finance/payments/${paymentId}`);
    await router.isReady();
    const wrapper = mount(PaymentDetailPage, { global: { plugins: [createPinia(), router] } });
    await flushPromises();

    expect(wrapper.text()).toContain('Multiple candidates — choose one');
    const useButtons = wrapper.findAll('button').filter((b) => b.text() === 'Use');
    expect(useButtons).toHaveLength(2);
    // No allocation call happened just from displaying suggestions.
    expect(fetchMock.mock.calls.some(([url]) => String(url).includes('/allocations'))).toBe(false);

    await useButtons[0]!.trigger('click');
    await flushPromises();
    expect((wrapper.get('[data-field="invoice-id"] input').element as HTMLInputElement).value).toBe(invoiceAId);
    expect((wrapper.get('[data-field="amount"] input').element as HTMLInputElement).value).toBe('179.00');
  });

  it('applying a partial allocation updates the visible open amount from the server response, not a local guess', async () => {
    const allocated: Payment = { ...payment(), allocated_minor: '10000', unapplied_minor: '15000', version: 1 };
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(http(payment()))
      .mockResolvedValueOnce(http({ status: 'none', requires_confirmation: true, candidates: [] }))
      .mockResolvedValueOnce(http({ payment: allocated, invoices: [invoiceStub(invoiceAId, '7900', 'partially_paid')] }, 201));
    vi.stubGlobal('fetch', fetchMock);
    const router = createRouter({ history: createMemoryHistory(), routes: invoicePaymentRecurringRoutes });
    await router.push(`/finance/payments/${paymentId}`);
    await router.isReady();
    const wrapper = mount(PaymentDetailPage, { global: { plugins: [createPinia(), router] } });
    await flushPromises();

    await wrapper.get('button[data-action="add-line"]').trigger('click');
    await wrapper.get('[data-field="invoice-id"] input').setValue(invoiceAId);
    await wrapper.get('[data-field="amount"] input').setValue('100.00');
    await wrapper.get('button[data-action="allocate"]').trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('150,00');
    const body = JSON.parse(String((fetchMock.mock.calls[2]![1] as RequestInit).body)) as { lines: unknown[] };
    expect(body.lines).toEqual([{ invoice_id: invoiceAId, amount: '100.00' }]);
  });
});
