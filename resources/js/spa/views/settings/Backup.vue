<template>
  <v-card rounded="xl" border flat class="mb-4">
    <v-toolbar flat color="surface"><v-toolbar-title>{{ t('settings.backup_jobs_heading') }}</v-toolbar-title></v-toolbar>
    <v-divider />
    <v-list>
      <v-list-item v-for="j in s.jobs" :key="j.id" :title="j.name" :subtitle="(j.sources || []).join(', ') + (j.encrypt ? ' · 🔒' : '')">
        <template #append>
          <v-btn variant="text" size="small" :icon="mdiPlay" :loading="running === j.id" @click="run(j)" />
          <v-btn variant="text" size="small" color="error" :icon="mdiDelete" @click="del(j)" />
        </template>
      </v-list-item>
      <v-list-item v-if="!s.jobs.length" :title="t('common.none')" class="text-medium-emphasis" />
    </v-list>
  </v-card>

  <v-card rounded="xl" border flat>
    <v-toolbar flat color="surface"><v-toolbar-title>{{ t('settings.backup_runs_heading') }}</v-toolbar-title></v-toolbar>
    <v-divider />
    <v-data-table :headers="headers" :items="s.runs" density="compact" :items-per-page="10">
      <template #[`item.status`]="{ item }">
        <v-chip size="small" :color="statusColor(item.status)">{{ item.status }}</v-chip>
      </template>
      <template #[`item.bytes`]="{ item }">{{ item.bytes ? fmt(item.bytes) : '—' }}</template>
    </v-data-table>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiPlay, mdiDelete } from '@mdi/js';
import { useSettingsStore, type BackupJob } from '@spa/stores/settings';
import { useToast } from '@spa/composables/useToast';

const s = useSettingsStore();
const { success, error } = useToast();
const running = ref<number | null>(null);
const headers = [
  { title: t('common.date'), key: 'created_at' },
  { title: t('common.status'), key: 'status' },
  { title: t('common.size'), key: 'bytes' },
];
onMounted(() => s.loadBackup());
function statusColor(st: string) { return st === 'success' ? 'success' : st === 'failed' ? 'error' : st === 'running' ? 'info' : undefined; }
function fmt(b: number) { const u = ['B', 'KB', 'MB', 'GB']; let i = 0; while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; } return `${b.toFixed(1)} ${u[i]}`; }
async function run(j: BackupJob) { running.value = j.id; try { await s.runJob(j.id); success(t('common.saved')); await s.loadBackup(); } catch { error(t('common.error')); } finally { running.value = null; } }
async function del(j: BackupJob) { if (!confirm(t('common.confirm_delete'))) return; await s.deleteJob(j.id); await s.loadBackup(); }
</script>
