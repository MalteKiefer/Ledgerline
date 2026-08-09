<template>
  <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium" :class="cls">
    <slot />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
const props = withDefaults(defineProps<{ tone?: 'primary' | 'gray' | 'success' | 'warning' | 'error' | 'info' }>(), { tone: 'gray' });
const cls = computed(() => ({
  primary: 'bg-primary-500/12 text-primary-600 dark:text-primary-300',
  gray: 'bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10',
  success: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400',
  warning: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
  error: 'bg-red-500/12 text-red-600 dark:text-red-400',
  info: 'bg-blue-500/12 text-blue-600 dark:text-blue-400',
}[props.tone]));
</script>
