<template>
  <section class="space-y-4" aria-labelledby="recurring-list-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="recurring-list-heading" class="text-xl font-bold">{{ t('invoices.tab_recurring') }}</h1>
      <Btn tag="router-link" :to="{ name: 'finance.recurring-invoices.new' }" icon="add">
        {{ t('invoices.recurring_add') }}
      </Btn>
    </header>

    <Card body-class="p-0">
      <template #header>
        <select
          data-filter="status"
          :value="filters.status ?? ''"
          class="rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
          :aria-label="t('common.status')"
          @change="update({ status: (($event.target as HTMLSelectElement).value || null) as RecurringTemplateStatus | null })"
        >
          <option value="">{{ t('invoices.invoice_status_all') }}</option>
          <option v-for="status in statuses" :key="status" :value="status">{{ t(`invoices.recurring_status_${status}`) }}</option>
        </select>
        <select
          data-filter="mode"
          :value="filters.mode ?? ''"
          class="rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
          :aria-label="t('invoices.recurring_mode')"
          @change="update({ mode: (($event.target as HTMLSelectElement).value || null) as RecurringMode | null })"
        >
          <option value="">{{ t('invoices.invoice_status_all') }}</option>
          <option v-for="mode in modes" :key="mode" :value="mode">{{ t(`invoices.recurring_mode_${mode}`) }}</option>
        </select>
      </template>

      <p v-if="store.listLoading" class="p-5 text-sm text-[var(--ll-muted)]" role="status">{{ t('common.loading') }}</p>
      <p v-else-if="store.listError" class="p-5 text-sm text-red-600" role="alert">{{ errorLabel(store.listError) }}</p>
      <p v-else-if="store.items.length === 0" class="p-5 text-sm text-[var(--ll-muted)]">{{ t('invoices.recurring_empty') }}</p>
      <div v-else class="divide-y divide-[var(--ll-border)]">
        <RouterLink
          v-for="template in store.items"
          :key="template.id"
          :to="{ name: 'finance.recurring-invoices.edit', params: { template: template.id } }"
          class="grid gap-3 p-4 hover:bg-black/[0.02] sm:grid-cols-[minmax(0,1fr)_auto_auto] dark:hover:bg-white/5"
        >
          <div class="min-w-0">
            <p class="truncate font-medium">{{ t(`invoices.recurring_interval_${template.interval}`) }}</p>
            <p class="text-xs text-[var(--ll-muted)]">{{ t('invoices.recurring_next_run') }}: {{ new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(template.next_run_at)) }}</p>
          </div>
          <AsyncStateBadge :tone="statusTone(template.status)">{{ t(`invoices.recurring_status_${template.status}`) }}</AsyncStateBadge>
          <span class="text-sm text-[var(--ll-muted)]">{{ t(`invoices.recurring_mode_${template.mode}`) }}</span>
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
import { Btn, Card, Pager } from '@spa/ui';
import AsyncStateBadge from '@spa/modules/finance/components/AsyncStateBadge.vue';
import { scalar, pageNumber, useFinanceUrlFilters } from '@spa/modules/finance/composables/useFinanceUrlFilters';
import type { RecurringInvoiceTemplateListFilters, RecurringMode, RecurringTemplateStatus } from '@spa/modules/finance/models/recurring';
import { useRecurringStore } from '@spa/modules/finance/stores/recurring';

const store = useRecurringStore();
const locale = document.documentElement.lang || 'de-DE';
const statuses: RecurringTemplateStatus[] = ['active', 'paused', 'completed'];
const modes: RecurringMode[] = ['draft', 'auto_send'];

function parse(query: Record<string, unknown>): Required<Pick<RecurringInvoiceTemplateListFilters, 'page'>> & RecurringInvoiceTemplateListFilters {
  const status = scalar(query.status);
  const mode = scalar(query.mode);

  return {
    status: statuses.includes(status as RecurringTemplateStatus) ? status as RecurringTemplateStatus : null,
    mode: modes.includes(mode as RecurringMode) ? mode as RecurringMode : null,
    page: pageNumber(query.page),
  };
}

function serialize(filters: RecurringInvoiceTemplateListFilters & { page: number }): Record<string, string> {
  return {
    ...(filters.status ? { status: filters.status } : {}),
    ...(filters.mode ? { mode: filters.mode } : {}),
    page: String(filters.page),
  };
}

const { filters, update, setPage } = useFinanceUrlFilters(parse, serialize, ['status', 'mode', 'page']);

watch(filters, (value) => {
  void store.loadList(value).catch(() => undefined);
}, { deep: true, immediate: true });

function statusTone(status: RecurringTemplateStatus): 'gray' | 'info' | 'success' | 'error' | 'warning' | 'primary' {
  return ({ active: 'success', paused: 'warning', completed: 'gray' } as const)[status];
}

function errorLabel(code: string): string {
  return `${t('invoices.recurring_error')} (${code})`;
}
</script>
