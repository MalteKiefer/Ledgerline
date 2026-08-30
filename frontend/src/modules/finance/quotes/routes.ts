import type { RouteRecordRaw } from 'vue-router';

export const quoteRoutes: RouteRecordRaw[] = [
  {
    path: '/finance/quotes',
    name: 'finance.quotes.index',
    component: () => import('@spa/modules/finance/quotes/QuoteListPage.vue'),
  },
  {
    path: '/finance/quotes/new',
    name: 'finance.quotes.new',
    component: () => import('@spa/modules/finance/quotes/QuoteEditPage.vue'),
  },
  {
    path: '/finance/quotes/:quote',
    name: 'finance.quotes.show',
    component: () => import('@spa/modules/finance/quotes/QuoteDetailPage.vue'),
  },
  {
    path: '/finance/quotes/:quote/edit',
    name: 'finance.quotes.edit',
    component: () => import('@spa/modules/finance/quotes/QuoteEditPage.vue'),
  },
];
