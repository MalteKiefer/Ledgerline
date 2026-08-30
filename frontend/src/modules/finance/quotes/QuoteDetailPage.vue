<template>
  <section class="space-y-4" aria-labelledby="quote-detail-heading">
    <p v-if="store.detailLoading || !quote" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <template v-else>
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs text-[var(--ll-muted)]">{{ quote.number ?? t('invoices.quote_status_draft') }}</p>
          <h1 id="quote-detail-heading" class="text-xl font-bold">{{ title }}</h1>
        </div>
        <QuoteStatusBadge :status="quote.effective_status" />
      </header>

      <p v-if="quote.has_pending_draft" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="status">
        {{ t('invoices.quote_pending_draft') }}
      </p>
      <p v-if="outcome" class="rounded-lg border border-blue-500/30 bg-blue-500/10 p-3 text-sm" role="status">{{ outcome }}</p>
      <p v-if="store.actionError" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700" role="alert">
        {{ errorLabel(store.actionError) }}
      </p>

      <QuoteWorkflowActions
        :quote="quote"
        :busy="store.actionLoading"
        @version="startVersion"
        @publish="publish"
        @send="send"
        @accept="decide('accept')"
        @decline="decide('decline')"
        @duplicate="duplicate"
        @convert="convert"
      />

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <Card :title="t('invoices.quote_revision_history')">
          <QuoteRevisionTimeline :revisions="store.revisions" :delivery="quote.delivery" />
        </Card>
        <Card :title="t('invoices.gross')">
          <QuoteTotals :totals="quote.totals" />
        </Card>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { Card } from '@spa/ui';
import QuoteRevisionTimeline from '@spa/modules/finance/components/quotes/QuoteRevisionTimeline.vue';
import QuoteStatusBadge from '@spa/modules/finance/components/quotes/QuoteStatusBadge.vue';
import QuoteTotals from '@spa/modules/finance/components/quotes/QuoteTotals.vue';
import QuoteWorkflowActions from '@spa/modules/finance/components/quotes/QuoteWorkflowActions.vue';
import { useQuotesStore } from '@spa/modules/finance/stores/quotes';

const route = useRoute();
const router = useRouter();
const store = useQuotesStore();
const id = computed(() => String(route.params.quote));
const quote = computed(() => store.current?.id === id.value ? store.current : null);
const title = computed(() => quote.value?.draft?.title ?? quote.value?.current_revision?.snapshot.title ?? quote.value?.number ?? id.value);
const outcome = ref<string | null>(null);

onMounted(async () => {
  await Promise.all([
    store.loadQuote(id.value),
    store.loadRevisions(id.value),
  ]).catch(() => undefined);
});

async function refreshRevisions(): Promise<void> {
  await store.loadRevisions(id.value).catch(() => undefined);
}

async function startVersion(): Promise<void> {
  await guarded(async () => {
    if (!quote.value) return;
    const updated = await store.startVersion(id.value, quote.value.version);
    await router.push({ name: 'finance.quotes.edit', params: { quote: updated.id } });
  });
}

async function publish(): Promise<void> {
  await guarded(async () => {
    if (!quote.value) return;
    await store.publish(id.value, quote.value.version, null);
    outcome.value = t('invoices.quote_published');
    await refreshRevisions();
  });
}

async function send(): Promise<void> {
  await guarded(async () => {
    if (!quote.value) return;
    const result = await store.send(id.value, quote.value.version, null, null);
    outcome.value = t(result.replayed ? 'invoices.quote_send_replayed' : 'invoices.quote_delivery_queued');
    await refreshRevisions();
  });
}

async function decide(action: 'accept' | 'decline'): Promise<void> {
  await guarded(async () => {
    if (!quote.value?.current_revision) return;
    await store[action](id.value, { version: quote.value.version, expected_revision_id: quote.value.current_revision.id });
  });
}

async function duplicate(): Promise<void> {
  await guarded(async () => {
    if (!quote.value) return;
    const copy = await store.duplicate(id.value, quote.value.version, quote.value.current_revision?.id ?? null);
    await router.push({ name: 'finance.quotes.edit', params: { quote: copy.id } });
  });
}

async function convert(): Promise<void> {
  await guarded(async () => {
    if (!quote.value?.current_revision) return;
    const target = await store.convertToInvoice(id.value, { version: quote.value.version, expected_revision_id: quote.value.current_revision.id });
    await router.push({ name: 'finance', params: { section: 'invoices' }, query: target.target_id === null ? { target: target.target_reference } : { invoice: String(target.target_id) } });
  });
}

async function guarded(operation: () => Promise<void>): Promise<void> {
  try {
    await operation();
  } catch {
    // The typed store error is rendered as the workflow result.
  }
}

function errorLabel(code: string): string {
  return `${t('invoices.quote_error')} (${code})`;
}
</script>
