<template>
  <component
    :is="tag"
    :type="tag === 'button' ? type : undefined"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-colors select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50 disabled:pointer-events-none"
    :class="[sizeCls, variantCls, block ? 'w-full' : '']"
  >
    <Icon v-if="loading" name="progress_activity" :size="iconSize" class="animate-spin" />
    <Icon v-else-if="icon" :name="icon" :size="iconSize" />
    <slot />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = withDefaults(defineProps<{
  variant?: 'solid' | 'soft' | 'outline' | 'ghost' | 'danger';
  size?: 'xs' | 'sm' | 'md' | 'lg';
  icon?: string;
  loading?: boolean;
  disabled?: boolean;
  block?: boolean;
  tag?: string;
  type?: 'button' | 'submit';
}>(), { variant: 'solid', size: 'md', tag: 'button', type: 'button' });

const iconSize = computed(() => ({ xs: 15, sm: 16, md: 18, lg: 20 }[props.size]));
const sizeCls = computed(() => ({
  xs: 'text-xs px-2 py-1',
  sm: 'text-sm px-2.5 py-1.5',
  md: 'text-sm px-3.5 py-2',
  lg: 'text-base px-5 py-2.5',
}[props.size]));
const variantCls = computed(() => ({
  solid: 'bg-primary-500 text-white hover:bg-primary-600 shadow-sm',
  soft: 'bg-primary-500/10 text-primary-600 hover:bg-primary-500/15 dark:text-primary-300',
  outline: 'border border-[var(--ll-border)] text-[var(--ll-fg)] hover:bg-black/[0.03] dark:hover:bg-white/5',
  ghost: 'text-[var(--ll-fg)] hover:bg-black/[0.04] dark:hover:bg-white/5',
  danger: 'bg-red-500 text-white hover:bg-red-600 shadow-sm',
}[props.variant]));
</script>
