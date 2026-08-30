<template>
  <Card :title="t('finance-projects.tab_activity')">
    <p v-if="detail.activity.loading && !detail.activity.data" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <p v-else-if="detail.activity.error" role="alert" class="text-sm text-red-600">{{ detail.activity.error }}</p>
    <p v-else-if="items.length === 0" class="text-sm text-[var(--ll-muted)]">{{ t('finance-projects.activity_empty') }}</p>
    <ul v-else class="space-y-2">
      <li v-for="event in items" :key="`${event.source_kind}:${event.id}`" class="flex items-start gap-2 text-sm" :data-activity="event.id">
        <Badge :tone="event.source_kind === 'project_activity' ? 'primary' : 'gray'">
          {{ t(event.source_kind === 'project_activity' ? 'finance-projects.activity_source_project' : 'finance-projects.activity_source_document') }}
        </Badge>
        <span class="flex-1">{{ event.type }}</span>
        <span class="text-xs text-[var(--ll-muted)]">{{ event.occurred_at }}</span>
      </li>
    </ul>

    <Btn v-if="detail.activity.nextCursor" size="sm" variant="ghost" class="mt-3" data-action="activity-load-more" :loading="detail.activity.loading" @click="loadMore">
      {{ t('finance-projects.activity_load_more') }}
    </Btn>
  </Card>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card } from '@spa/ui';
import type { HistoryCursorPage, HistoryItem } from '@spa/modules/finance/models/history';

const props = defineProps<{ detail: { activity: { data: HistoryCursorPage | null; loading: boolean; error: string | null; query: { cursor: string | null; per_page: number }; nextCursor?: string | null }; loadActivity: (id: string) => Promise<void> }; projectId: string }>();

const items = computed<HistoryItem[]>(() => props.detail.activity.data?.data ?? []);

async function loadMore(): Promise<void> {
  props.detail.activity.query.cursor = props.detail.activity.nextCursor ?? null;
  await props.detail.loadActivity(props.projectId);
}

watch(() => props.projectId, (id) => { if (id) void props.detail.loadActivity(id); }, { immediate: true });
</script>
