<template>
  <section class="space-y-4" aria-labelledby="quote-list-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="quote-list-heading" class="text-xl font-bold">{{ t('invoices.tab_quotes') }}</h1>
      <Btn tag="router-link" :to="{ name: 'finance.quotes.new' }" icon="add">
        {{ t('invoices.quote_add') }}
      </Btn>
    </header>

    <Card body-class="p-0">
      <template #header>
        <TextField
          :model-value="filters.q"
          type="search"
          icon="search"
          :placeholder="t('invoices.quote_search')"
          class="w-full sm:w-72"
          @update:model-value="update({ q: $event })"
        />
        <select
          data-filter="status"
          :value="filters.status ?? ''"
          class="rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
          :aria-label="t('invoices.quote_status')"
          @change="update({ status: (($event.target as HTMLSelectElement).value || null) as QuoteStatus | null })"
        >
          <option value="">{{ t('invoices.quote_status_all') }}</option>
          <option v-for="status in statuses" :key="status" :value="status">
            {{ t(`invoices.quote_status_${status}`) }}
          </option>
        </select>
        <select
          data-filter="effective-status"
          :value="filters.effective_status ?? ''"
          class="rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
          :aria-label="t('common.status')"
          @change="update({ effective_status: (($event.target as HTMLSelectElement).value || null) as QuoteEffectiveStatus | null })"
        >
          <option value="">{{ t('invoices.quote_status_all') }}</option>
          <option v-for="status in effectiveStatuses" :key="status" :value="status">
            {{ t(`invoices.quote_status_${status}`) }}
          </option>
        </select>
      </template>

      <p v-if="store.listLoading" class="p-5 text-sm text-[var(--ll-muted)]" role="status">{{ t('common.loading') }}</p>
      <p v-else-if="store.listError" class="p-5 text-sm text-red-600" role="alert">{{ errorLabel(store.listError) }}</p>
      <p v-else-if="store.items.length === 0" class="p-5 text-sm text-[var(--ll-muted)]">{{ t('invoices.quotes_empty') }}</p>
      <div v-else class="divide-y divide-[var(--ll-border)]">
        <RouterLink
          v-for="quote in store.items"
          :key="quote.id"
          :to="{ name: 'finance.quotes.show', params: { quote: quote.id } }"
          class="grid gap-3 p-4 hover:bg-black/[0.02] sm:grid-cols-[minmax(0,1fr)_auto_auto] dark:hover:bg-white/5"
        >
          <div class="min-w-0">
            <p class="truncate font-medium">{{ quote.draft?.title ?? quote.current_revision?.snapshot.title ?? quote.number ?? quote.id }}</p>
            <p class="text-xs text-[var(--ll-muted)]">{{ quote.number ?? t('invoices.quote_status_draft') }}</p>
          </div>
          <QuoteStatusBadge :status="quote.effective_status" class="self-start" />
          <QuoteTotals :totals="quote.totals" class="min-w-64" />
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
import QuoteStatusBadge from '@spa/modules/finance/components/quotes/QuoteStatusBadge.vue';
import QuoteTotals from '@spa/modules/finance/components/quotes/QuoteTotals.vue';
import { useQuoteFilters } from '@spa/modules/finance/composables/useQuoteFilters';
import type { QuoteEffectiveStatus, QuoteStatus } from '@spa/modules/finance/models/quote';
import { useQuotesStore } from '@spa/modules/finance/stores/quotes';

const store = useQuotesStore();
const { filters, update, setPage } = useQuoteFilters();
const statuses: QuoteStatus[] = ['draft', 'sent', 'accepted', 'declined', 'converted'];
const effectiveStatuses: QuoteEffectiveStatus[] = ['draft', 'sent', 'accepted', 'declined', 'converted', 'expired'];

watch(filters, (value) => {
  void store.loadList(value).catch(() => undefined);
}, { deep: true, immediate: true });

function errorLabel(code: string): string {
  return `${t('invoices.quote_error')} (${code})`;
}
</script>
