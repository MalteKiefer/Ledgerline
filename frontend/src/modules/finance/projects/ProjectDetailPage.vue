<template>
  <section class="space-y-4" aria-labelledby="project-detail-heading">
    <p v-if="detail.project.loading && !project" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <p v-else-if="!project" role="alert" class="text-sm text-red-600">{{ detail.project.error }}</p>
    <template v-else>
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p v-if="project.parent_id" class="text-xs text-[var(--ll-muted)]">
            <RouterLink :to="{ name: 'finance.projects.show', params: { project: project.parent_id } }" class="underline">
              {{ project.parent_available ? t('finance-projects.parent_link') : t('finance-projects.parent_unavailable') }}
            </RouterLink>
          </p>
          <h1 id="project-detail-heading" class="text-xl font-bold">{{ project.name }}</h1>
        </div>
        <div class="flex items-center gap-2">
          <ProjectStatusBadge :status="project.status" />
          <Badge v-if="project.archived" tone="gray">{{ t('finance-projects.archived') }}</Badge>
        </div>
      </header>

      <div v-if="conflict" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="alert">
        <p>{{ t('finance-projects.version_conflict') }}</p>
        <Btn data-action="reload-conflict" variant="outline" size="sm" class="mt-2" @click="reload">{{ t('finance-projects.version_conflict_reload') }}</Btn>
      </div>
      <p v-else-if="detail.actionError" role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700">
        {{ detail.actionError }}
      </p>

      <div class="flex flex-wrap gap-2">
        <Btn v-for="target in transitions" :key="target" size="sm" variant="outline" :data-action="`status-${target}`" :loading="detail.actionLoading" @click="changeStatus(target)">
          {{ t(`finance-projects.transition_${target}`) }}
        </Btn>
        <Btn tag="router-link" :to="{ name: 'finance.projects.edit', params: { project: project.id } }" size="sm" variant="ghost" icon="edit">{{ t('common.edit') }}</Btn>
        <Btn v-if="!project.archived" size="sm" variant="ghost" icon="archive" data-action="archive" :loading="detail.actionLoading" @click="archive">{{ t('finance-projects.archive') }}</Btn>
        <Btn v-else size="sm" variant="ghost" icon="unarchive" data-action="restore" :loading="detail.actionLoading" @click="restore">{{ t('finance-projects.restore') }}</Btn>
      </div>

      <SectionNav :groups="tabGroups" :active="isActiveTab" @select="selectTab" />

      <div v-if="tab === 'overview'" class="space-y-4">
        <ProjectSummaryCards :totals="detail.totals.data" />
      </div>
      <ProjectWorkPanel v-else-if="tab === 'work'" :detail="detail" :project-id="project.id" />
      <ProjectLedgerPanel v-else-if="tab === 'ledger'" :detail="detail" :project-id="project.id" :currency="project.currency" />
      <ProjectDocumentsPanel v-else-if="tab === 'documents'" :detail="detail" :project-id="project.id" />
      <ProjectNotesPanel v-else-if="tab === 'notes'" :detail="detail" :project-id="project.id" />
      <ProjectActivityTimeline v-else-if="tab === 'activity'" :detail="detail" :project-id="project.id" />
      <ProjectTimePanel v-if="tab === 'work'" :detail="detail" :project-id="project.id" />
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { Badge, Btn, SectionNav, type SectionNavItem } from '@spa/ui';
import ProjectActivityTimeline from '@spa/modules/finance/components/projects/ProjectActivityTimeline.vue';
import ProjectDocumentsPanel from '@spa/modules/finance/components/projects/ProjectDocumentsPanel.vue';
import ProjectLedgerPanel from '@spa/modules/finance/components/projects/ProjectLedgerPanel.vue';
import ProjectNotesPanel from '@spa/modules/finance/components/projects/ProjectNotesPanel.vue';
import ProjectStatusBadge from '@spa/modules/finance/components/projects/ProjectStatusBadge.vue';
import ProjectSummaryCards from '@spa/modules/finance/components/projects/ProjectSummaryCards.vue';
import ProjectTimePanel from '@spa/modules/finance/components/projects/ProjectTimePanel.vue';
import ProjectWorkPanel from '@spa/modules/finance/components/projects/ProjectWorkPanel.vue';
import { useProjectDetail } from '@spa/modules/finance/composables/useProjectDetail';
import type { ProjectStatus } from '@spa/modules/finance/models/project';
import { useProjectsStore } from '@spa/modules/finance/stores/projects';

const allowedTransitions: Record<ProjectStatus, ProjectStatus[]> = {
  planned: ['active', 'cancelled'],
  active: ['on_hold', 'done', 'cancelled'],
  on_hold: ['active', 'cancelled'],
  done: ['active'],
  cancelled: ['planned'],
};

const route = useRoute();
const router = useRouter();
const detail = useProjectDetail();
const store = useProjectsStore();
const id = computed(() => String(route.params.project));
const project = computed(() => detail.project.data);
const conflict = ref(false);
const tab = ref<'overview' | 'work' | 'ledger' | 'documents' | 'notes' | 'activity'>('overview');
const tabs: Array<{ id: typeof tab.value; icon: string }> = [
  { id: 'overview', icon: 'dashboard' },
  { id: 'work', icon: 'checklist' },
  { id: 'ledger', icon: 'payments' },
  { id: 'documents', icon: 'description' },
  { id: 'notes', icon: 'sticky_note_2' },
  { id: 'activity', icon: 'history' },
];
const tabGroups = computed(() => [{ id: 'tabs', items: tabs.map((entry): SectionNavItem => ({ id: entry.id, label: t(`finance-projects.tab_${entry.id}`), icon: entry.icon })) }]);
const transitions = computed(() => project.value ? allowedTransitions[project.value.status] : []);

function isActiveTab(item: SectionNavItem): boolean { return item.id === tab.value; }
function selectTab(item: SectionNavItem): void { tab.value = item.id as typeof tab.value; }

onMounted(() => { void detail.open(id.value); });
watch(id, (next) => { if (next) void detail.open(next); });

async function changeStatus(target: ProjectStatus): Promise<void> {
  conflict.value = false;
  if (!project.value) return;
  try {
    await store.changeStatus(project.value.id, project.value.version, target);
    await detail.refresh('project', 'activity');
  } catch {
    conflict.value = store.actionError === 'version_conflict';
  }
}

async function archive(): Promise<void> {
  if (!project.value) return;
  try {
    await store.archive(project.value.id, project.value.version);
    await detail.refresh('project', 'activity');
  } catch {
    conflict.value = store.actionError === 'version_conflict';
  }
}

async function restore(): Promise<void> {
  if (!project.value) return;
  try {
    await store.restore(project.value.id, project.value.version);
    await detail.refresh('project', 'activity');
  } catch {
    conflict.value = store.actionError === 'version_conflict';
  }
}

async function reload(): Promise<void> {
  conflict.value = false;
  await router.go(0);
}
</script>
