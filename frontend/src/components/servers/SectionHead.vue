<template>
  <component :is="level" :class="cls">
    <Icon :name="icon" :size="level === 'h2' ? 16 : 14" class="shrink-0 text-[var(--ll-muted)]" />
    <span>{{ label }}</span>
    <slot />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Icon } from '@spa/ui';

/**
 * A section heading with its own glyph.
 *
 * The server tabs stack a dozen sections on one page, and a wall of identical
 * bold text gives the eye nothing to aim at. The icon is the landmark: it is
 * monochrome and inherits the muted colour, so it marks the place without
 * competing with the numbers underneath, which are what somebody came for.
 */
const props = withDefaults(defineProps<{ icon: string; label: string; level?: 'h2' | 'h3' }>(), { level: 'h2' });

const cls = computed(() => props.level === 'h2'
  ? 'flex items-center gap-1.5 text-sm font-semibold'
  : 'flex items-center gap-1.5 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]');

const level = computed(() => props.level);
</script>
