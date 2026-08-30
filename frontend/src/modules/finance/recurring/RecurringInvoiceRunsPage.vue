<template>
  <section class="space-y-4" aria-labelledby="recurring-runs-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="recurring-runs-heading" class="text-xl font-bold">{{ t('invoices.recurring_runs') }}</h1>
      <Btn tag="router-link" :to="{ name: 'finance.recurring-invoices.edit', params: { template: id } }" variant="ghost">
        {{ t('common.back') }}
      </Btn>
    </header>

    <p v-if="store.actionError" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700" role="alert">
      {{ errorLabel(store.actionError) }}
    </p>

    <Card body-class="p-0">
      <p v-if="store.runsLoading" class="p-5 text-sm text-[var(--ll-muted)]" role="status">{{ t('common.loading') }}</p>
      <p v-else-if="store.runs.length === 0" class="p-5 text-sm text-[var(--ll-muted)]">{{ t('invoices.recurring_runs_empty') }}</p>
      <ol v-else class="divide-y divide-[var(--ll-border)]" :aria-label="t('invoices.recurring_runs')">
        <li v-for="run in store.runs" :key="run.id" :data-run="run.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
          <div class="min-w-0">
            <p class="font-medium">{{ new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(run.scheduled_for)) }}</p>
            <p v-if="run.last_error_code" class="font-mono text-xs text-red-600">{{ run.last_error_code }}</p>
          </div>
          <AsyncStateBadge :tone="runTone(run.status)" :pending="pendingStates.includes(run.status)">
            {{ t(`invoices.recurring_run_status_${run.status}`) }}
          </AsyncStateBadge>
          <Btn v-if="run.status === 'failed'" size="sm" variant="outline" data-action="retry" :loading="store.actionLoading" @click="retry(run.id)">
            {{ t('invoices.recurring_run_retry') }}
          </Btn>
        </li>
      </ol>

      <Pager
        :page="store.runsPage.meta.current_page"
        :per-page="store.runsPage.meta.per_page"
        :total="store.runsPage.meta.total"
        @update:page="loadPage"
      />
    </Card>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute } from 'vue-router';
import { Btn, Card, Pager } from '@spa/ui';
import AsyncStateBadge from '@spa/modules/finance/components/AsyncStateBadge.vue';
import type { RecurringRunStatus } from '@spa/modules/finance/models/recurring';
import { useRecurringStore } from '@spa/modules/finance/stores/recurring';

const route = useRoute();
const store = useRecurringStore();
const locale = document.documentElement.lang || 'de-DE';
const id = computed(() => String(route.params.template));
const pendingStates: RecurringRunStatus[] = ['pending', 'creating_draft', 'finalizing', 'sending'];

onMounted(async () => {
  await store.loadRuns(id.value).catch(() => undefined);
});

async function loadPage(page: number): Promise<void> {
  await store.loadRuns(id.value, { page }).catch(() => undefined);
}

async function retry(runId: string): Promise<void> {
  await store.retryRun(id.value, runId).catch(() => undefined);
}

function runTone(status: RecurringRunStatus): 'gray' | 'info' | 'success' | 'error' | 'warning' | 'primary' {
  return ({
    pending: 'gray',
    creating_draft: 'info',
    draft_created: 'primary',
    finalizing: 'info',
    finalized: 'primary',
    sending: 'info',
    sent: 'success',
    failed: 'error',
  } as const)[status];
}

function errorLabel(code: string): string {
  return `${t('invoices.recurring_error')} (${code})`;
}
</script>
