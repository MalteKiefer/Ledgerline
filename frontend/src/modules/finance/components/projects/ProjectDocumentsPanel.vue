<template>
  <Card :title="t('finance-projects.tab_documents')">
    <template #header>
      <Btn size="sm" icon="add" data-action="documents-toggle-picker" @click="picking = !picking">{{ t('finance-projects.documents_attach') }}</Btn>
    </template>

    <ProjectDocumentPicker v-if="picking" :project-id="projectId" class="mb-4" @attach="attach" />

    <p v-if="detail.documents.loading && !detail.documents.data" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <p v-else-if="detail.documents.error" role="alert" class="text-sm text-red-600">{{ detail.documents.error }}</p>
    <p v-else-if="rows.length === 0" class="text-sm text-[var(--ll-muted)]">{{ t('finance-projects.documents_empty') }}</p>
    <ul v-else class="divide-y divide-[var(--ll-border)]">
      <li v-for="row in rows" :key="row.link_id" class="flex items-center gap-3 py-2" :data-document-link="row.link_id">
        <span class="flex-1 truncate">{{ row.current?.title ?? row.snapshot.title ?? row.source.source_reference }}</span>
        <span class="text-xs text-[var(--ll-muted)]">{{ t(`finance-projects.documents_role_${row.role}`) }}</span>
        <Badge :tone="availabilityTone(row.availability)">{{ t(`finance-projects.documents_${row.availability}`) }}</Badge>
        <Btn v-if="!row.detached" size="xs" variant="ghost" icon="link_off" :title="t('finance-projects.documents_detach')" data-action="document-detach" @click="detach(row.link_id)" />
        <span v-else class="text-xs text-[var(--ll-muted)]">{{ t('finance-projects.documents_detached') }}</span>
      </li>
    </ul>

    <Pager :page="meta.current_page" :per-page="meta.per_page" :total="meta.total" @update:page="setPage" />
  </Card>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card, Pager } from '@spa/ui';
import ProjectDocumentPicker from '@spa/modules/finance/components/projects/ProjectDocumentPicker.vue';
import type { ProjectDocument, ProjectDocumentAvailability, ProjectDocumentInput } from '@spa/modules/finance/models/projectDocument';

const props = defineProps<{ detail: { documents: { data: { data: ProjectDocument[]; meta: { current_page: number; per_page: number; total: number } } | null; loading: boolean; error: string | null; query: { page: number; per_page: number } }; loadDocuments: (id: string) => Promise<void>; attachDocument: (input: ProjectDocumentInput) => Promise<ProjectDocument>; detachDocument: (linkId: number) => Promise<ProjectDocument> }; projectId: string }>();

const picking = ref(false);
const rows = computed(() => props.detail.documents.data?.data ?? []);
const meta = computed(() => props.detail.documents.data?.meta ?? { current_page: 1, per_page: 20, total: 0 });

function availabilityTone(availability: ProjectDocumentAvailability): 'success' | 'warning' | 'error' {
  return availability === 'available' ? 'success' : availability === 'missing' ? 'warning' : 'error';
}

async function attach(input: ProjectDocumentInput): Promise<void> {
  await props.detail.attachDocument(input);
  picking.value = false;
}

async function detach(linkId: number): Promise<void> {
  await props.detail.detachDocument(linkId);
}

function setPage(page: number): void {
  props.detail.documents.query.page = page;
  void props.detail.loadDocuments(props.projectId);
}

watch(() => props.projectId, (id) => { if (id) void props.detail.loadDocuments(id); }, { immediate: true });
</script>
