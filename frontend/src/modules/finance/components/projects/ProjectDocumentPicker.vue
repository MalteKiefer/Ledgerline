<template>
  <div class="space-y-2 rounded-lg border border-[var(--ll-border)] p-3">
    <div class="flex flex-wrap items-center gap-2">
      <TextField
        data-field="document-search"
        :model-value="query"
        type="search"
        icon="search"
        :placeholder="t('finance-projects.documents_source_search')"
        class="min-w-48 flex-1"
        @update:model-value="search($event)"
      />
      <label>
        <span class="sr-only">{{ t('finance-projects.documents_role') }}</span>
        <select data-field="document-role" :value="role" class="rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="role = (($event.target as HTMLSelectElement).value as ProjectDocumentRole)">
          <option v-for="candidate in roles" :key="candidate" :value="candidate">{{ t(`finance-projects.documents_role_${candidate}`) }}</option>
        </select>
      </label>
    </div>

    <p v-if="loading" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <p v-else-if="results.length === 0" class="text-sm text-[var(--ll-muted)]">{{ t('finance-projects.documents_source_empty') }}</p>
    <ul v-else class="divide-y divide-[var(--ll-border)]">
      <li v-for="source in results" :key="`${source.source_type}:${source.source_reference}:${source.pinned_revision_id ?? ''}`" class="flex items-center gap-3 py-2" :data-document-source="source.source_reference">
        <span class="flex-1 truncate">{{ source.title ?? source.source_reference }}</span>
        <span class="text-xs text-[var(--ll-muted)]">{{ source.source_type }}</span>
        <Btn size="xs" data-action="document-attach" @click="$emit('attach', { source_type: source.source_type, source_reference: source.source_reference, pinned_revision_id: source.pinned_revision_id, role })">
          {{ t('finance-projects.documents_attach') }}
        </Btn>
      </li>
    </ul>
    <Btn v-if="nextCursor" size="sm" variant="ghost" data-action="document-source-more" :loading="loading" @click="loadMore">{{ t('finance-projects.activity_load_more') }}</Btn>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, TextField } from '@spa/ui';
import { projectApi } from '@spa/modules/finance/api/projectApi';
import type { ProjectDocumentInput, ProjectDocumentMetadata, ProjectDocumentRole } from '@spa/modules/finance/models/projectDocument';

const props = defineProps<{ projectId: string }>();
defineEmits<{ attach: [input: ProjectDocumentInput] }>();

const roles: ProjectDocumentRole[] = ['source_quote', 'quote', 'invoice', 'payment', 'receipt', 'file', 'photo', 'other'];
const query = ref('');
const role = ref<ProjectDocumentRole>('file');
const results = ref<ProjectDocumentMetadata[]>([]);
const nextCursor = ref<string | null>(null);
const loading = ref(false);
let controller: AbortController | null = null;
let timer: ReturnType<typeof setTimeout> | null = null;

function search(value: string): void {
  query.value = value;
  if (timer !== null) clearTimeout(timer);
  timer = setTimeout(() => void run(), 250);
}

async function run(): Promise<void> {
  controller?.abort();
  const active = new AbortController();
  controller = active;
  loading.value = true;
  try {
    const page = await projectApi.searchDocumentSources(props.projectId, { per_page: 20 }, active.signal);
    if (controller !== active) return;
    results.value = page.data;
    nextCursor.value = page.next_cursor;
  } catch {
    // A stale/aborted search leaves the previous truthful results in place.
  } finally {
    if (controller === active) loading.value = false;
  }
}

async function loadMore(): Promise<void> {
  if (!nextCursor.value) return;
  loading.value = true;
  try {
    const page = await projectApi.searchDocumentSources(props.projectId, { cursor: nextCursor.value, per_page: 20 });
    results.value = [...results.value, ...page.data];
    nextCursor.value = page.next_cursor;
  } finally {
    loading.value = false;
  }
}

onMounted(run);
</script>
