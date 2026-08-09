<template>
  <div class="space-y-4">
    <Card body-class="p-0">
      <template #header>
        <Icon name="public" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.sessions_web_heading') }}</h2>
      </template>
      <template #actions>
        <Btn variant="ghost" size="sm" icon="refresh" :loading="loading" @click="load">{{ t('settings.request_log_refresh') }}</Btn>
      </template>
      <div v-if="loading" class="h-0.5 w-full overflow-hidden bg-primary-500/15"><div class="h-full w-1/3 animate-pulse bg-primary-500" /></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
            <tr class="border-b border-[var(--ll-border)]">
              <th class="px-4 py-2.5 font-medium">{{ t('settings.sessions_col_user') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_ip') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_ua') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.sessions_col_last_activity') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(w, i) in sec.sessions.web" :key="'w' + i" class="border-b border-[var(--ll-border)] last:border-0">
              <td class="px-4 py-2.5 font-medium">#{{ w.user_id }}</td>
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ w.ip }}</td>
              <td class="max-w-[24rem] truncate px-4 py-2.5 text-xs text-[var(--ll-muted)]" :title="w.user_agent || ''">{{ w.user_agent }}</td>
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ fmtDate(w.last_activity) }}</td>
            </tr>
            <tr v-if="!loading && !sec.sessions.web.length"><td colspan="4" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
          </tbody>
        </table>
      </div>
    </Card>

    <Card body-class="p-0">
      <template #header>
        <Icon name="devices" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.sessions_devices_heading') }}</h2>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
            <tr class="border-b border-[var(--ll-border)]">
              <th class="px-4 py-2.5 font-medium">{{ t('settings.sessions_col_user') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.sessions_col_device') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_ip') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.sessions_col_last_activity') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(d, i) in sec.sessions.devices" :key="'d' + i" class="border-b border-[var(--ll-border)] last:border-0">
              <td class="px-4 py-2.5 font-medium">#{{ d.user_id }}</td>
              <td class="px-4 py-2.5">{{ d.name }}</td>
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ d.ip }}</td>
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ fmtDate(d.last_used_at) }}</td>
            </tr>
            <tr v-if="!loading && !sec.sessions.devices.length"><td colspan="4" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
          </tbody>
        </table>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card } from '@spa/ui';
import { useSecurityStore } from '@spa/stores/security';
import { useToast } from '@spa/composables/useToast';

const sec = useSecurityStore();
const { error } = useToast();
const loading = ref(false);

function fmtDate(v: string | null): string {
  if (!v) return '';
  const d = new Date(v);
  return isNaN(d.getTime()) ? String(v) : d.toLocaleString(document.documentElement.lang || 'de');
}

async function load() { loading.value = true; try { await sec.loadSessions(); } catch { error(t('common.error')); } finally { loading.value = false; } }

onMounted(() => { void load(); });
</script>
