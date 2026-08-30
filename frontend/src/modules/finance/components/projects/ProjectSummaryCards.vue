<template>
  <div v-if="!totals" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</div>
  <div v-else-if="currencies.length === 0" class="text-sm text-[var(--ll-muted)]">{{ t('finance-projects.totals_empty') }}</div>
  <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <Card v-for="entry in currencies" :key="entry.currency" :title="entry.currency">
      <dl class="space-y-1.5 text-sm">
        <div class="flex justify-between gap-4">
          <dt class="text-[var(--ll-muted)]">{{ t('finance-projects.totals_hours') }}</dt>
          <dd class="font-mono tabular-nums">{{ formatScaled(entry.data.hours_scaled) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-[var(--ll-muted)]">{{ t('finance-projects.totals_time_value') }}</dt>
          <dd class="font-mono tabular-nums">{{ formatMinor(entry.data.time_value_minor, entry.currency) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-[var(--ll-muted)]">{{ t('finance-projects.totals_ledger') }}</dt>
          <dd class="font-mono tabular-nums">{{ formatMinor(entry.data.ledger_minor, entry.currency) }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-t border-[var(--ll-border)] pt-1 font-semibold">
          <dt>{{ t('finance-projects.totals_financial') }}</dt>
          <dd class="font-mono tabular-nums">{{ formatMinor(entry.data.financial_minor, entry.currency) }}</dd>
        </div>
      </dl>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card } from '@spa/ui';
import type { ProjectTotals, ProjectTotalsCurrency } from '@spa/modules/finance/models/project';
import { formatMinor, formatScaled } from '@spa/modules/finance/components/projects/format';

const props = defineProps<{ totals: ProjectTotals | null }>();

const currencies = computed(() => {
  if (!props.totals) return [];
  return Object.entries(props.totals.currencies).map(([currency, data]: [string, ProjectTotalsCurrency]) => ({ currency, data }));
});
</script>
