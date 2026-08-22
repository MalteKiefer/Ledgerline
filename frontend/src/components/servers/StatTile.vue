<template>
  <Card :body-class="'p-4'">
    <div class="flex items-start justify-between gap-2">
      <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ label }}</div>
      <Icon v-if="icon" :name="icon" :size="16" class="shrink-0 text-[var(--ll-muted)]" />
    </div>

    <div class="mt-1 font-mono text-2xl font-bold tabular-nums" :class="valueClass">{{ value }}</div>

    <!-- A bar only where a percentage means something. A number alone answers
         "what is it"; the bar answers "is that a lot", which is the question
         somebody scanning a page is actually asking. -->
    <div v-if="pct !== null && pct !== undefined" class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
      <div class="h-full rounded-full transition-all" :class="barClass" :style="{ width: `${Math.min(100, Math.max(0, pct))}%` }" />
    </div>

    <div class="mt-1 truncate text-[0.7rem] text-[var(--ll-muted)]" :title="note">{{ note }}</div>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Card, Icon } from '@spa/ui';

const props = defineProps<{
  label: string;
  value: string;
  note?: string;
  icon?: string;
  /** Percentage for the bar, if the figure has one. */
  pct?: number | null;
  /** Above this the figure is worth noticing, above the second one it is a problem. */
  warnAt?: number;
  dangerAt?: number;
}>();

const level = computed(() => {
  if (props.pct === null || props.pct === undefined) return 'none';
  if (props.dangerAt !== undefined && props.pct >= props.dangerAt) return 'danger';
  if (props.warnAt !== undefined && props.pct >= props.warnAt) return 'warn';

  return 'ok';
});

// Colour only where it earns its place: everything amber is the same as
// nothing amber.
const barClass = computed(() => ({
  danger: 'bg-red-500',
  warn: 'bg-amber-500',
  ok: 'bg-emerald-500',
  none: 'bg-[var(--ll-muted)]',
}[level.value]));

const valueClass = computed(() => ({
  danger: 'text-red-600 dark:text-red-400',
  warn: 'text-amber-600 dark:text-amber-400',
  ok: '',
  none: '',
}[level.value]));
</script>
