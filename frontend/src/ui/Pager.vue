<template>
  <!-- Renders nothing when everything fits on one page: a pager that only ever
       says "1 / 1" is chrome, not information. -->
  <div v-if="total > options[0]" class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--ll-border)] px-4 py-2.5 text-sm">
    <span class="text-[var(--ll-muted)] tabular-nums">{{ rangeLabel }}</span>
    <div class="flex items-center gap-2">
      <Select
        :model-value="perPage"
        :options="options.map((n) => ({ title: String(n), value: n }))"
        class="w-20"
        @update:model-value="$emit('update:perPage', Number($event))"
      />
      <Btn variant="ghost" size="sm" icon="chevron_left" :disabled="page <= 1" :title="t('common.previous')" @click="$emit('update:page', page - 1)" />
      <span class="tabular-nums">{{ page }} / {{ pageCount }}</span>
      <Btn variant="ghost" size="sm" icon="chevron_right" :disabled="page >= pageCount" :title="t('common.next')" @click="$emit('update:page', page + 1)" />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Table pager. The parent owns `page`/`perPage` (v-model on both) and does the
 * slicing — this only renders the controls and the "x–y of n" read-out, so it
 * works the same over a client-side array and a server-paged list.
 */
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import Btn from './Btn.vue';
import Select from './Select.vue';

const props = withDefaults(defineProps<{
  page: number;
  perPage: number;
  total: number;
  options?: number[];
}>(), { options: () => [10, 25, 50, 100] });

defineEmits<{ 'update:page': [number]; 'update:perPage': [number] }>();

const pageCount = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));
const rangeLabel = computed(() => {
  if (!props.total) return '—';
  const from = (props.page - 1) * props.perPage + 1;
  const to = Math.min(props.total, props.page * props.perPage);
  return `${from}–${to} / ${props.total}`;
});
</script>
