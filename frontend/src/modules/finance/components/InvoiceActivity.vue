<template>
  <ol class="space-y-3" :aria-label="t('invoices.revision_history')">
    <li
      v-for="revision in revisions"
      :key="revision.id"
      :data-revision="revision.id"
      class="rounded-lg border border-[var(--ll-border)] p-4"
    >
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p class="text-xs text-[var(--ll-muted)]">{{ t('invoices.col_number') }} #{{ revision.revision_number }}</p>
          <p class="text-xs text-[var(--ll-muted)]">
            {{ publishedLabel(revision.published_at ?? undefined) }}
          </p>
        </div>
        <Badge :tone="revision.status === 'published' ? 'success' : 'gray'">{{ revision.status }}</Badge>
      </div>
      <p v-if="revision.pdf_sha256" class="mt-2 break-all font-mono text-xs text-[var(--ll-muted)]">
        SHA-256 {{ revision.pdf_sha256 }}
      </p>
      <div v-if="revision.status === 'published'" class="mt-3 flex gap-2">
        <Btn tag="a" :href="revision.pdf_url" target="_blank" rel="noopener" variant="outline" size="sm">
          {{ t('invoices.quote_pdf') }}
        </Btn>
      </div>
    </li>
  </ol>

  <ol v-if="deliveries.length > 0" class="mt-4 space-y-2" :aria-label="t('invoices.delivery_history')">
    <li
      v-for="delivery in deliveries"
      :key="delivery.id"
      class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-black/[0.03] px-3 py-2 text-sm dark:bg-white/5"
    >
      <span>{{ t(`invoices.delivery_kind_${delivery.kind}`) }} — {{ delivery.recipient }}</span>
      <span class="flex items-center gap-2">
        <AsyncStateBadge :tone="deliveryTone(delivery.status)">{{ t(`invoices.delivery_status_${delivery.status}`) }}</AsyncStateBadge>
        <span class="text-xs text-[var(--ll-muted)]">{{ t('invoices.quote_delivery_attempts') }}: {{ delivery.attempts }}</span>
        <span v-if="delivery.last_error_code" class="font-mono text-xs text-red-600">{{ delivery.last_error_code }}</span>
      </span>
    </li>
  </ol>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn } from '@spa/ui';
import AsyncStateBadge from '@spa/modules/finance/components/AsyncStateBadge.vue';
import type { InvoiceDelivery, InvoiceDeliveryStatus, InvoiceRevision } from '@spa/modules/finance/models/invoice';

withDefaults(defineProps<{ revisions: InvoiceRevision[]; deliveries?: InvoiceDelivery[] }>(), { deliveries: () => [] });

function publishedLabel(value: string | undefined): string {
  if (! value) return t('invoices.status_draft');

  return new Intl.DateTimeFormat(document.documentElement.lang || 'de-DE', { dateStyle: 'medium', timeStyle: 'short' })
    .format(new Date(value));
}

function deliveryTone(status: InvoiceDeliveryStatus): 'gray' | 'info' | 'success' | 'error' | 'warning' {
  return ({
    pending: 'gray',
    sending: 'info',
    sent: 'success',
    failed: 'error',
    unknown: 'warning',
  } as const)[status];
}
</script>
