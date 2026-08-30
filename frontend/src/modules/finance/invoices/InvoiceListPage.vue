<template>
  <section class="space-y-4" aria-labelledby="invoice-list-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="invoice-list-heading" class="text-xl font-bold">{{ t('invoices.tab_invoices') }}</h1>
      <Btn tag="router-link" :to="{ name: 'finance.invoices.new' }" icon="add">
        {{ t('invoices.new') }}
      </Btn>
    </header>

    <Card body-class="p-0">
      <template #header>
        <TextField
          :model-value="filters.q"
          type="search"
          icon="search"
          :placeholder="t('invoices.invoice_search')"
          class="w-full sm:w-72"
          @update:model-value="update({ q: $event })"
        />
        <select
          data-filter="status"
          :value="filters.status ?? ''"
          class="rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
          :aria-label="t('common.status')"
          @change="update({ status: (($event.target as HTMLSelectElement).value || null) as InvoiceStatus | null })"
        >
          <option value="">{{ t('invoices.invoice_status_all') }}</option>
          <option v-for="status in statuses" :key="status" :value="status">
            {{ t(`invoices.invoice_status_${status}`) }}
          </option>
        </select>
        <label class="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            data-filter="overdue"
            :checked="filters.overdue === true"
            @change="update({ overdue: ($event.target as HTMLInputElement).checked ? true : null })"
          >
          {{ t('invoices.invoice_overdue_only') }}
        </label>
      </template>

      <p v-if="store.listLoading" class="p-5 text-sm text-[var(--ll-muted)]" role="status">{{ t('common.loading') }}</p>
      <p v-else-if="store.listError" class="p-5 text-sm text-red-600" role="alert">{{ errorLabel(store.listError) }}</p>
      <p v-else-if="store.items.length === 0" class="p-5 text-sm text-[var(--ll-muted)]">{{ t('invoices.invoices_empty') }}</p>
      <div v-else class="divide-y divide-[var(--ll-border)]">
        <RouterLink
          v-for="invoice in store.items"
          :key="invoice.id"
          :to="{ name: 'finance.invoices.show', params: { invoice: invoice.id } }"
          class="grid gap-3 p-4 hover:bg-black/[0.02] sm:grid-cols-[minmax(0,1fr)_auto_auto] dark:hover:bg-white/5"
        >
          <div class="min-w-0">
            <p class="truncate font-medium">{{ invoice.number ?? t('invoices.invoice_status_draft') }}</p>
            <p class="text-xs text-[var(--ll-muted)]">{{ String(invoice.snapshot.customer && (invoice.snapshot.customer as { name?: string }).name || '') }}</p>
          </div>
          <AsyncStateBadge :tone="statusTone(invoice.status)" class="self-start">{{ t(`invoices.invoice_status_${invoice.status}`) }}</AsyncStateBadge>
          <InvoiceTotals :totals="invoice.totals" :open-minor="invoice.open_minor" :allocated-minor="invoice.allocated_minor" class="min-w-64" />
        </RouterLink>
      </div>

      <Pager
        :page="store.page.meta.current_page"
        :per-page="store.page.meta.per_page"
        :total="store.page.meta.total"
        @update:page="setPage"
      />
    </Card>
  </section>
</template>

<script setup lang="ts">
import { watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, Card, Pager, TextField } from '@spa/ui';
import AsyncStateBadge from '@spa/modules/finance/components/AsyncStateBadge.vue';
import InvoiceTotals from '@spa/modules/finance/components/InvoiceTotals.vue';
import { boolFlag, pageNumber, scalar, useFinanceUrlFilters } from '@spa/modules/finance/composables/useFinanceUrlFilters';
import type { InvoiceListFilters, InvoiceStatus } from '@spa/modules/finance/models/invoice';
import { useInvoicesStore } from '@spa/modules/finance/stores/invoices';

const store = useInvoicesStore();
const statuses: InvoiceStatus[] = ['draft', 'finalized', 'sent', 'partially_paid', 'paid', 'cancelled'];

function parse(query: Record<string, unknown>): Required<Pick<InvoiceListFilters, 'q' | 'page'>> & InvoiceListFilters {
  const status = scalar(query.status);

  return {
    q: scalar(query.q) ?? '',
    status: statuses.includes(status as InvoiceStatus) ? status as InvoiceStatus : null,
    overdue: boolFlag(query.overdue),
    page: pageNumber(query.page),
  };
}

function serialize(filters: InvoiceListFilters & { page: number }): Record<string, string> {
  return {
    ...(filters.q ? { q: filters.q } : {}),
    ...(filters.status ? { status: filters.status } : {}),
    ...(filters.overdue === true ? { overdue: '1' } : {}),
    page: String(filters.page),
  };
}

const { filters, update, setPage } = useFinanceUrlFilters(parse, serialize, ['q', 'status', 'overdue', 'page']);

watch(filters, (value) => {
  void store.loadList(value).catch(() => undefined);
}, { deep: true, immediate: true });

function statusTone(status: InvoiceStatus): 'gray' | 'info' | 'success' | 'error' | 'warning' | 'primary' {
  return ({
    draft: 'gray',
    finalized: 'info',
    sent: 'primary',
    partially_paid: 'warning',
    paid: 'success',
    cancelled: 'error',
  } as const)[status];
}

function errorLabel(code: string): string {
  return `${t('invoices.invoice_error')} (${code})`;
}
</script>
