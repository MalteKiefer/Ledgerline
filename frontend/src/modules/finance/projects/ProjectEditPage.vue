<template>
  <section class="space-y-4" aria-labelledby="project-edit-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="project-edit-heading" class="text-xl font-bold">{{ isNew ? t('finance-projects.add') : t('common.edit') }}</h1>
      <Btn :loading="store.actionLoading" data-action="save" @click="save">{{ t('common.save') }}</Btn>
    </header>

    <div v-if="conflict" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="alert">
      <p>{{ t('finance-projects.version_conflict') }}</p>
      <Btn data-action="load-conflict" variant="outline" size="sm" class="mt-2" @click="loadConflict">{{ t('finance-projects.version_conflict_reload') }}</Btn>
    </div>
    <p v-else-if="store.actionError" role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700">
      {{ store.actionError }}
    </p>

    <Card :title="t('finance-projects.title')">
      <div class="grid gap-3 sm:grid-cols-2">
        <TextField data-field="name" :model-value="form.name" :label="t('finance-projects.name')" required @update:model-value="form.name = $event" />
        <label>
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('finance-projects.kind') }}</span>
          <select data-field="kind" :value="form.kind" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="form.kind = (($event.target as HTMLSelectElement).value as ProjectKind)">
            <option value="business">{{ t('finance-projects.kind_business') }}</option>
            <option value="private">{{ t('finance-projects.kind_private') }}</option>
          </select>
        </label>
        <TextField data-field="currency" :model-value="form.currency" :label="t('finance-projects.currency')" @update:model-value="form.currency = $event.toUpperCase()" />
        <TextField data-field="budget" :model-value="budgetText" inputmode="decimal" :label="t('finance-projects.budget')" @update:model-value="setBudget($event)" />
        <TextField data-field="starts-on" type="date" :model-value="form.starts_on ?? ''" :label="t('finance-projects.starts_on')" @update:model-value="form.starts_on = $event || null" />
        <TextField data-field="due-on" type="date" :model-value="form.due_on ?? ''" :label="t('finance-projects.due_on')" @update:model-value="form.due_on = $event || null" />
        <TextField data-field="partner-reference" :model-value="form.partner_reference ?? ''" :label="t('finance-projects.partner_reference')" @update:model-value="form.partner_reference = $event || null" />
        <TextField data-field="parent-id" :model-value="form.parent_id ?? ''" :label="t('finance-projects.parent')" @update:model-value="form.parent_id = $event || null" />
      </div>
    </Card>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { Btn, Card, TextField } from '@spa/ui';
import type { ProjectInput, ProjectKind } from '@spa/modules/finance/models/project';
import { useProjectsStore } from '@spa/modules/finance/stores/projects';

const route = useRoute();
const router = useRouter();
const store = useProjectsStore();
const id = computed(() => typeof route.params.project === 'string' ? route.params.project : null);
const isNew = computed(() => id.value === null);
const form = ref<ProjectInput>(empty());
const budgetText = ref('');
const conflict = ref(false);

function empty(): ProjectInput {
  return { name: '', kind: 'business', currency: 'EUR', budget_minor: null, partner_reference: null, parent_id: null, starts_on: null, due_on: null };
}

onMounted(async () => {
  if (id.value !== null) {
    const project = await store.loadProject(id.value).catch(() => null);
    if (project) {
      form.value = {
        name: project.name, kind: project.kind, currency: project.currency, budget_minor: project.budget_minor,
        partner_reference: project.partner_reference, parent_id: project.parent_id, starts_on: project.starts_on, due_on: project.due_on,
      };
      budgetText.value = project.budget_minor ?? '';
    }
  }
});

function setBudget(value: string): void {
  budgetText.value = value;
  // The boundary converts decimal text to exact integer minor units; it is
  // never used for authoritative arithmetic — only as the write payload.
  form.value = { ...form.value, budget_minor: value === '' ? null : String(BigInt(Math.round(Number(value) * 100)) || 0n) };
}

async function save(): Promise<void> {
  conflict.value = false;
  try {
    const saved = id.value === null
      ? await store.create(form.value)
      : await store.update(id.value, { ...form.value, version: store.current?.version ?? 0 });
    await router.push({ name: 'finance.projects.show', params: { project: saved.id } });
  } catch {
    conflict.value = store.actionError === 'version_conflict';
  }
}

function loadConflict(): void {
  if (!store.current) return;
  form.value = {
    name: store.current.name, kind: store.current.kind, currency: store.current.currency, budget_minor: store.current.budget_minor,
    partner_reference: store.current.partner_reference, parent_id: store.current.parent_id, starts_on: store.current.starts_on, due_on: store.current.due_on,
  };
  conflict.value = false;
}
</script>
