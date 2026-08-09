<template>
  <Card :body-class="'p-0'">
    <template #header>
      <Icon name="security" :size="18" class="text-[var(--ll-muted)]" />
      <h2 class="text-sm font-semibold">{{ t('settings.seclog_title') }}</h2>
    </template>
    <template #actions><Btn tag="a" variant="soft" size="sm" icon="download" href="/api/v1/security-log/export?export=csv">CSV</Btn></template>
    <div v-if="loading" class="h-0.5 w-full overflow-hidden bg-primary-500/15">
      <div class="h-full w-1/3 animate-pulse bg-primary-500" />
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
          <tr class="border-b border-[var(--ll-border)]">
            <th class="px-4 py-2.5 font-medium">{{ t('common.date') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.seclog_col_action') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.seclog_col_user') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.seclog_col_ip') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.seclog_col_meta') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in s.audit" :key="item.id" class="border-b border-[var(--ll-border)] last:border-0">
            <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ fmtDate(item.at) }}</td>
            <td class="px-4 py-2.5"><Badge tone="gray">{{ actionLabel(item.action) }}</Badge></td>
            <td class="px-4 py-2.5">{{ item.actor }}</td>
            <td class="px-4 py-2.5 font-mono text-xs">{{ item.ip }}</td>
            <td class="px-4 py-2.5">
              <code class="text-xs text-[var(--ll-muted)]">{{ item.meta && Object.keys(item.meta).length ? JSON.stringify(item.meta) : '' }}</code>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, Badge } from '@spa/ui';
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

/** Humanize a dotted/underscored action code, e.g. `backup.job.created` → "Backup · Job created". */
function humanizeAction(code: string): string {
  const parts = code.split('.').filter(Boolean);
  if (!parts.length) return code;
  const head = parts[0].charAt(0).toUpperCase() + parts[0].slice(1);
  const rest = parts.slice(1).join(' ').replace(/_/g, ' ').trim();
  return rest ? `${head} · ${rest.charAt(0).toUpperCase() + rest.slice(1)}` : head;
}

/**
 * Localized label for an audit action code. Tries the (not-yet-existing) `audit.*`
 * namespace first; laravel-vue-i18n returns the key unchanged when missing, so we
 * detect that and fall back to a humanized string.
 */
function actionLabel(code: string): string {
  if (!code) return '';
  const key = `audit.${code}`;
  const r = t(key);
  return r === key ? humanizeAction(code) : r;
}
onMounted(async () => { loading.value = true; try { await s.loadAudit(); } finally { loading.value = false; } });
</script>
