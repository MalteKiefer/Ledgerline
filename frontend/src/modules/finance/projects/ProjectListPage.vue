<template>
  <section class="space-y-4" aria-labelledby="project-list-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="project-list-heading" class="text-xl font-bold">{{ t('finance-projects.title') }}</h1>
      <Btn tag="router-link" :to="{ name: 'finance.projects.new' }" icon="add">{{ t('finance-projects.add') }}</Btn>
    </header>

    <Card body-class="p-0">
      <template #header>
        <TextField
          :model-value="filters.q"
          type="search"
          icon="search"
          :placeholder="t('finance-projects.search')"
          class="w-full sm:w-72"
          @update:model-value="update({ q: $event })"
        />
        <select
          data-filter="status"
          :value="filters.status ?? ''"
          class="rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
          :aria-label="t('common.status')"
          @change="update({ status: (($event.target as HTMLSelectElement).value || null) as ProjectStatus | null })"
        >
          <option value="">{{ t('finance-projects.filter_status_all') }}</option>
          <option v-for="status in statuses" :key="status" :value="status">{{ t(`finance-projects.status_${status}`) }}</option>
        </select>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" data-filter="archived" :checked="filters.archived" @change="update({ archived: ($event.target as HTMLInputElement).checked })">
          {{ t('finance-projects.filter_archived') }}
        </label>
      </template>

      <p v-if="store.listLoading" class="p-5 text-sm text-[var(--ll-muted)]" role="status">{{ t('common.loading') }}</p>
      <p v-else-if="store.listError" class="p-5 text-sm text-red-600" role="alert">{{ store.listError }}</p>
      <p v-else-if="store.items.length === 0" class="p-5 text-sm text-[var(--ll-muted)]">{{ t('finance-projects.empty') }}</p>
      <div v-else class="divide-y divide-[var(--ll-border)]">
        <RouterLink
          v-for="project in store.items"
          :key="project.id"
          :to="{ name: 'finance.projects.show', params: { project: project.id } }"
          class="grid gap-3 p-4 hover:bg-black/[0.02] sm:grid-cols-[minmax(0,1fr)_auto] dark:hover:bg-white/5"
        >
          <div class="min-w-0">
            <p class="truncate font-medium">{{ project.name }}</p>
            <p class="text-xs text-[var(--ll-muted)]">{{ t(`finance-projects.kind_${project.kind}`) }}</p>
          </div>
          <ProjectStatusBadge :status="project.status" class="self-start" />
        </RouterLink>
      </div>

      <Pager :page="store.page.meta.current_page" :per-page="store.page.meta.per_page" :total="store.page.meta.total" @update:page="setPage" />
    </Card>
  </section>
</template>

<script setup lang="ts">
import { watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, Card, Pager, TextField } from '@spa/ui';
import ProjectStatusBadge from '@spa/modules/finance/components/projects/ProjectStatusBadge.vue';
import { useProjectFilters } from '@spa/modules/finance/composables/useProjectFilters';
import type { ProjectStatus } from '@spa/modules/finance/models/project';
import { useProjectsStore } from '@spa/modules/finance/stores/projects';

const store = useProjectsStore();
const { filters, update, setPage } = useProjectFilters();
const statuses: ProjectStatus[] = ['planned', 'active', 'on_hold', 'done', 'cancelled'];

watch(filters, (value) => {
  void store.loadList(value).catch(() => undefined);
}, { deep: true, immediate: true });
</script>
