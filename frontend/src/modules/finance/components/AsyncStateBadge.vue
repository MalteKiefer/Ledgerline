<template>
  <Badge :tone="tone" :aria-busy="busy">
    <slot />
  </Badge>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@spa/ui';

/**
 * Shows the truth of an async background outcome (invoice delivery,
 * recurring run) as it is actually persisted server-side — never an
 * optimistic "sent" the moment a request is queued. Callers pass the
 * already-resolved tone bucket; this component only owns the visual
 * mapping + busy state so every async status badge in the finance module
 * looks and behaves the same.
 */
const props = withDefaults(defineProps<{
  tone: 'gray' | 'info' | 'success' | 'error' | 'warning' | 'primary';
  pending?: boolean;
}>(), { pending: false });

const busy = computed(() => props.pending);
</script>
