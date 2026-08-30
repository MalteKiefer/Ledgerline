<template>
  <ol class="space-y-3" :aria-label="t('invoices.quote_revision_history')">
    <li
      v-for="revision in revisions"
      :key="revision.id"
      :data-revision="revision.id"
      class="rounded-lg border border-[var(--ll-border)] p-4"
    >
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p class="text-xs text-[var(--ll-muted)]">{{ revision.snapshot.document_number }}</p>
          <p class="font-medium">{{ revision.snapshot.revision_label }}</p>
          <p class="text-xs text-[var(--ll-muted)]">
            {{ publishedLabel(revision.published_at ?? revision.created_at) }}
          </p>
        </div>
        <Badge :tone="revision.status === 'published' ? 'success' : 'gray'">{{ revision.status }}</Badge>
      </div>
      <p v-if="revision.previous_revision_id !== null" class="mt-2 text-xs text-[var(--ll-muted)]">
        {{ t('invoices.quote_revision_previous') }} #{{ revision.previous_revision_id }}
      </p>
      <p v-if="revision.pdf_sha256" class="mt-2 break-all font-mono text-xs text-[var(--ll-muted)]">
        SHA-256 {{ revision.pdf_sha256 }}
      </p>
      <div v-if="revision.pdf_url" class="mt-3 flex gap-2">
        <Btn :tag="'a'" :href="revision.pdf_url" target="_blank" rel="noopener" variant="outline" size="sm">
          {{ t('invoices.quote_pdf') }}
        </Btn>
        <Btn v-if="revision.pdf_download_url" :tag="'a'" :href="revision.pdf_download_url" variant="ghost" size="sm">
          {{ t('common.download') }}
        </Btn>
      </div>
      <div v-if="delivery?.revision_id === revision.id" class="mt-3 rounded-md bg-black/[0.03] px-3 py-2 text-sm dark:bg-white/5">
        <span role="status">{{ deliveryLabel(delivery) }}</span>
        <span class="ml-2 text-xs text-[var(--ll-muted)]">{{ t('invoices.quote_delivery_attempts') }}: {{ delivery.attempts }}</span>
        <span v-if="delivery.last_error_code" class="ml-2 font-mono text-xs text-red-600">{{ delivery.last_error_code }}</span>
      </div>
    </li>
  </ol>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn } from '@spa/ui';
import type { QuoteDelivery, QuoteRevision } from '@spa/modules/finance/models/quote';

defineProps<{ revisions: QuoteRevision[]; delivery: QuoteDelivery | null }>();

function publishedLabel(value: string): string {
  return new Intl.DateTimeFormat(document.documentElement.lang || 'de-DE', { dateStyle: 'medium', timeStyle: 'short' })
    .format(new Date(value));
}

function deliveryLabel(delivery: QuoteDelivery): string {
  return t(delivery.last_error_code === 'delivery_outcome_uncertain'
    ? 'invoices.quote_delivery_uncertain'
    : `invoices.quote_delivery_${delivery.state}`);
}
</script>
