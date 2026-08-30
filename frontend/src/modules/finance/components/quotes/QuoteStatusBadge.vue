<template>
  <Badge :tone="tone">{{ t(`invoices.quote_status_${status}`) }}</Badge>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge } from '@spa/ui';
import type { QuoteEffectiveStatus } from '@spa/modules/finance/models/quote';

const props = defineProps<{ status: QuoteEffectiveStatus }>();

const tone = computed(() => ({
  draft: 'gray',
  sent: 'info',
  accepted: 'success',
  declined: 'error',
  converted: 'primary',
  expired: 'warning',
} as const)[props.status]);
</script>
