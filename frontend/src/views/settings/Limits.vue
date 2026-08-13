<template>
  <div>
    <Card v-for="group in groups" :key="group.title" class="mb-4">
      <template #header>
        <Icon :name="group.icon" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t(group.title) }}</h2>
      </template>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField
          v-for="f in group.fields" :key="f.key"
          v-model="form[f.key]"
          :label="t('settings.' + f.key)"
          type="number"
          :placeholder="placeholder(f.key)"
          :hint="t('settings.' + f.key + '_hint')"
        />
      </div>
    </Card>
    <p class="mb-4 text-xs text-[var(--ll-muted)]">{{ t('settings.limits_inherit') }}</p>

    <div class="sticky bottom-3 z-10 flex justify-end rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] px-4 py-3 shadow-sm">
      <Btn variant="solid" :loading="saving" :disabled="loading" @click="save">{{ t('settings.save') }}</Btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, TextField } from '@spa/ui';

type Key =
  | 'sanctum_expiration_minutes' | 'session_lifetime_minutes' | 'device_wipe_grace_minutes' | 'device_idle_days'
  | 'audit_retention_days' | 'access_log_retention_days' | 'request_log_retention_days' | 'backup_stale_hours'
  | 'mail_log_retention_days' | 'mail_blob_orphan_grace_hours' | 'files_quota_mb';

const groups: { title: string; icon: string; fields: { key: Key }[] }[] = [
  { title: 'settings.limits_session', icon: 'schedule', fields: [
    { key: 'sanctum_expiration_minutes' }, { key: 'session_lifetime_minutes' }, { key: 'device_wipe_grace_minutes' }, { key: 'device_idle_days' },
  ] },
  { title: 'settings.limits_retention', icon: 'history', fields: [
    { key: 'audit_retention_days' }, { key: 'access_log_retention_days' }, { key: 'request_log_retention_days' },
    { key: 'backup_stale_hours' }, { key: 'mail_log_retention_days' }, { key: 'mail_blob_orphan_grace_hours' },
  ] },
  { title: 'settings.limits_storage', icon: 'folder', fields: [{ key: 'files_quota_mb' }] },
];

const { success, error } = useToast();
const form = reactive<Record<Key, number | string | null>>({} as Record<Key, number | string | null>);
const effective = ref<Record<string, number | null>>({});
const loading = ref(true);
const saving = ref(false);

function placeholder(k: Key): string {
  const v = effective.value[k];
  return v === null || v === undefined ? '' : String(v);
}

async function load() {
  const r = await api.get<{ settings: Record<string, number | null>; effective: Record<string, number | null> }>('/api/v1/admin/limits');
  effective.value = r.effective;
  for (const g of groups) for (const f of g.fields) form[f.key] = r.settings[f.key] ?? '';
  loading.value = false;
}

async function save() {
  saving.value = true;
  try {
    const body: Record<string, number | null> = {};
    for (const g of groups) for (const f of g.fields) {
      const raw = form[f.key];
      body[f.key] = raw === '' || raw === null ? null : Number(raw);
    }
    await api.put('/api/v1/admin/limits', body);
    success(t('common.saved'));
    await load();
  } catch { error(t('common.error')); } finally { saving.value = false; }
}

onMounted(load);
</script>
