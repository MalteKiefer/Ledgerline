import type { RouteRecordRaw } from 'vue-router';

/**
 * Lazy invoice/payment/recurring module routes. Not yet mounted on the
 * global router — activation is the cutover task (Task 17), matching the
 * same deferred-activation pattern already used by quoteRoutes.
 */
export const invoicePaymentRecurringRoutes: RouteRecordRaw[] = [
  {
    path: '/finance/invoices',
    name: 'finance.invoices.index',
    component: () => import('@spa/modules/finance/invoices/InvoiceListPage.vue'),
  },
  {
    path: '/finance/invoices/new',
    name: 'finance.invoices.new',
    component: () => import('@spa/modules/finance/invoices/InvoiceEditorPage.vue'),
  },
  {
    path: '/finance/invoices/:invoice',
    name: 'finance.invoices.show',
    component: () => import('@spa/modules/finance/invoices/InvoiceDetailPage.vue'),
  },
  {
    path: '/finance/invoices/:invoice/edit',
    name: 'finance.invoices.edit',
    component: () => import('@spa/modules/finance/invoices/InvoiceEditorPage.vue'),
  },
  {
    path: '/finance/payments',
    name: 'finance.payments.index',
    component: () => import('@spa/modules/finance/payments/PaymentListPage.vue'),
  },
  {
    path: '/finance/payments/:payment',
    name: 'finance.payments.show',
    component: () => import('@spa/modules/finance/payments/PaymentDetailPage.vue'),
  },
  {
    path: '/finance/recurring-invoices',
    name: 'finance.recurring-invoices.index',
    component: () => import('@spa/modules/finance/recurring/RecurringInvoiceListPage.vue'),
  },
  {
    path: '/finance/recurring-invoices/new',
    name: 'finance.recurring-invoices.new',
    component: () => import('@spa/modules/finance/recurring/RecurringInvoiceEditorPage.vue'),
  },
  {
    path: '/finance/recurring-invoices/:template/edit',
    name: 'finance.recurring-invoices.edit',
    component: () => import('@spa/modules/finance/recurring/RecurringInvoiceEditorPage.vue'),
  },
  {
    path: '/finance/recurring-invoices/:template/runs',
    name: 'finance.recurring-invoices.runs',
    component: () => import('@spa/modules/finance/recurring/RecurringInvoiceRunsPage.vue'),
  },
];
