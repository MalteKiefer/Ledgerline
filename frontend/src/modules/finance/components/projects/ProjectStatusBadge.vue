<template>
  <Badge :tone="tone">{{ t(`finance-projects.status_${status}`) }}</Badge>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge } from '@spa/ui';
import type { ProjectStatus } from '@spa/modules/finance/models/project';

const props = defineProps<{ status: ProjectStatus }>();

const tone = computed(() => ({
  planned: 'gray',
  active: 'success',
  on_hold: 'warning',
  done: 'primary',
  cancelled: 'error',
} as const)[props.status]);
</script>
