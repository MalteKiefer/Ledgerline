<template>
  <v-card rounded="xl" border flat>
    <v-toolbar flat color="surface">
      <v-toolbar-title>{{ t('settings.seclog_title') }}</v-toolbar-title>
      <v-spacer />
      <v-btn variant="tonal" :prepend-icon="mdiDownload" href="/api/v1/security-log/export?export=csv">CSV</v-btn>
    </v-toolbar>
    <v-divider />
    <v-data-table :headers="headers" :items="s.audit" :loading="loading" density="compact" :items-per-page="25">
      <template #[`item.at`]="{ item }"><span class="ll-mono text-caption">{{ fmtDate(item.at) }}</span></template>
      <template #[`item.action`]="{ item }"><v-chip size="x-small" variant="tonal" label>{{ item.action }}</v-chip></template>
      <template #[`item.meta`]="{ item }">
        <code class="text-caption text-medium-emphasis">{{ item.meta && Object.keys(item.meta).length ? JSON.stringify(item.meta) : '' }}</code>
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiDownload } from '@mdi/js';
import { useSettingsStore } from '@spa/stores/settings';

const s = useSettingsStore();
const loading = ref(false);
const headers = [
  { title: t('common.date'), key: 'at' },
  { title: t('settings.seclog_col_action'), key: 'action' },
  { title: t('settings.seclog_col_user'), key: 'actor' },
  { title: t('settings.seclog_col_ip'), key: 'ip' },
  { title: t('settings.seclog_col_meta'), key: 'meta', sortable: false },
];
function fmtDate(v: string | null): string {
  if (!v) return '';
  const d = new Date(v);
  return isNaN(d.getTime()) ? String(v) : d.toLocaleString(document.documentElement.lang || 'de');
}
onMounted(async () => { loading.value = true; try { await s.loadAudit(); } finally { loading.value = false; } });
</script>
