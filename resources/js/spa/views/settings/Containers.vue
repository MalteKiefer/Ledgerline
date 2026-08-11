<template>
  <div>
    <Card class="mb-4">
      <template #header>
        <Icon name="dns" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.containers_heading') }}</h2>
      </template>

      <!-- Agent not enabled -->
      <div v-if="data && data.configured === false" class="space-y-3 text-sm">
        <p class="text-[var(--ll-muted)]">{{ t('settings.containers_disabled') }}</p>
        <div class="rounded-lg border border-[var(--ll-border)] bg-[var(--ll-bg)] p-3">
          <div v-for="(cmd, key) in data.operator" :key="key" class="mb-1.5 flex items-center gap-2">
            <span class="w-16 shrink-0 text-xs font-medium text-[var(--ll-muted)]">{{ key }}</span>
            <code class="min-w-0 flex-1 truncate rounded bg-black/[0.06] px-2 py-1 font-mono text-xs dark:bg-white/10">{{ cmd }}</code>
            <button class="rounded p-1 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" @click="copy(cmd)"><Icon name="content_copy" :size="15" /></button>
          </div>
        </div>
      </div>

      <!-- Agent enabled but unreachable -->
      <p v-else-if="data && data.reachable === false" class="text-sm text-amber-600">{{ t('settings.containers_unreachable') }}</p>

      <!-- Service list -->
      <div v-else-if="data && data.services" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-[var(--ll-border)] text-left text-xs text-[var(--ll-muted)]">
              <th class="py-2 pr-3">{{ t('settings.containers_service') }}</th>
              <th class="py-2 pr-3">{{ t('settings.containers_state') }}</th>
              <th class="py-2 pr-3">{{ t('settings.containers_cpu') }}</th>
              <th class="py-2 pr-3">{{ t('settings.containers_mem') }}</th>
              <th class="py-2 pr-3">{{ t('settings.containers_image') }}</th>
              <th class="py-2 text-right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="svc in sortedServices" :key="svc.service" class="border-b border-[var(--ll-border)]/60">
              <td class="py-2 pr-3 font-medium">{{ svc.service }}</td>
              <td class="py-2 pr-3">
                <span class="inline-flex items-center gap-1.5">
                  <span class="inline-flex h-2 w-2 rounded-full" :class="dot(svc.state)" />
                  {{ svc.state || '—' }}
                </span>
              </td>
              <td class="py-2 pr-3">
                <div class="flex items-center gap-2">
                  <div class="h-1.5 w-16 overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
                    <div class="h-full" :class="pct(svc.cpu) > 80 ? 'bg-red-500' : pct(svc.cpu) > 50 ? 'bg-amber-500' : 'bg-primary-500'" :style="{ width: barW(svc.cpu) }" />
                  </div>
                  <span class="tabular-nums text-xs text-[var(--ll-muted)]">{{ svc.cpu || '—' }}</span>
                </div>
              </td>
              <td class="py-2 pr-3">
                <div class="flex items-center gap-2" :title="svc.mem">
                  <div class="h-1.5 w-16 overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
                    <div class="h-full" :class="pct(svc.mem_perc) > 85 ? 'bg-red-500' : pct(svc.mem_perc) > 60 ? 'bg-amber-500' : 'bg-primary-500'" :style="{ width: barW(svc.mem_perc) }" />
                  </div>
                  <span class="tabular-nums text-xs text-[var(--ll-muted)]">{{ (svc.mem || '').split(' / ')[0] || '—' }}</span>
                </div>
              </td>
              <td class="max-w-[16rem] truncate py-2 pr-3 font-mono text-xs text-[var(--ll-muted)]" :title="svc.image">{{ svc.image }}</td>
              <td class="py-2">
                <div class="flex justify-end gap-1">
                  <Btn variant="ghost" size="sm" icon="restart_alt" :loading="busy === svc.service + ':restart'" @click="act(svc.service, 'restart')">{{ t('settings.containers_restart') }}</Btn>
                  <Btn variant="ghost" size="sm" icon="download" :loading="busy === svc.service + ':update'" @click="update(svc.service)">{{ t('settings.containers_update') }}</Btn>
                  <div class="relative">
                    <Btn variant="ghost" size="sm" icon="more_vert" @click="menu = menu === svc.service ? '' : svc.service" />
                    <div v-if="menu === svc.service" class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-elevated)] py-1 shadow-lg">
                      <button class="block w-full px-3 py-1.5 text-left hover:bg-black/[0.05] dark:hover:bg-white/10" @click="act(svc.service, 'stop'); menu = ''">{{ t('settings.containers_stop') }}</button>
                      <button class="block w-full px-3 py-1.5 text-left hover:bg-black/[0.05] dark:hover:bg-white/10" @click="act(svc.service, 'start'); menu = ''">{{ t('settings.containers_start') }}</button>
                      <button class="block w-full px-3 py-1.5 text-left hover:bg-black/[0.05] dark:hover:bg-white/10" @click="act(svc.service, 'recreate'); menu = ''">{{ t('settings.containers_recreate') }}</button>
                      <button class="block w-full px-3 py-1.5 text-left hover:bg-black/[0.05] dark:hover:bg-white/10" @click="showLogs(svc.service); menu = ''">{{ t('settings.containers_logs') }}</button>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Logs modal -->
    <Teleport to="body">
      <div v-if="logs.open" class="fixed inset-0 z-[2200] flex items-center justify-center bg-black/50 p-4" @click.self="logs.open = false">
        <div class="flex max-h-[85vh] w-full max-w-4xl flex-col rounded-xl bg-[var(--ll-elevated)] shadow-xl">
          <div class="flex items-center gap-2 border-b border-[var(--ll-border)] px-5 py-3">
            <h3 class="text-sm font-semibold">{{ logs.service }} · {{ t('settings.containers_logs') }}</h3>
            <div class="relative ml-2 flex-1">
              <Icon name="search" :size="15" class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[var(--ll-muted)]" />
              <input v-model="logs.filter" type="search" :placeholder="t('settings.containers_logs_filter')" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent py-1.5 pl-8 pr-3 text-sm focus:border-primary-500 focus:outline-none">
            </div>
            <span class="shrink-0 text-xs tabular-nums text-[var(--ll-muted)]">{{ logLines.length }}</span>
            <button class="rounded p-1 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('settings.containers_refresh')" @click="showLogs(logs.service)"><Icon name="refresh" :size="16" /></button>
            <label class="flex shrink-0 items-center gap-1 text-xs text-[var(--ll-muted)]"><input v-model="logs.wrap" type="checkbox" class="accent-primary-500"> {{ t('settings.containers_logs_wrap') }}</label>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="logs.open = false"><Icon name="close" :size="18" /></button>
          </div>
          <pre class="min-h-0 flex-1 overflow-auto p-4 font-mono text-xs" :class="logs.wrap ? 'whitespace-pre-wrap break-all' : 'whitespace-pre'">{{ logLines.length ? logLines.join('\n') : (logs.loading ? '…' : t('settings.containers_logs_none')) }}</pre>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { Icon, Btn, Card } from '@spa/ui';

interface Svc { service: string; state: string; status: string; image: string; cpu?: string; mem?: string; mem_perc?: string }
interface Containers { configured: boolean; reachable?: boolean; services?: Svc[]; operator?: Record<string, string> }

const { success, error } = useToast();
const data = ref<Containers | null>(null);
const busy = ref('');
const menu = ref('');
const logs = reactive({ open: false, service: '', text: '', filter: '', wrap: false, loading: false });
const logLines = computed(() => {
  const q = logs.filter.trim().toLowerCase();
  return logs.text.split('\n').filter((l) => l !== '' && (q === '' || l.toLowerCase().includes(q)));
});
let poll: ReturnType<typeof setInterval> | null = null;

// Fixed alphabetical order so rows never jump between polls.
const sortedServices = computed(() => [...(data.value?.services ?? [])].sort((a, b) => a.service.localeCompare(b.service)));
function pct(v?: string): number { const n = parseFloat((v ?? '').replace('%', '')); return Number.isNaN(n) ? 0 : n; }
function barW(v?: string): string { return `${Math.min(100, Math.max(0, pct(v)))}%`; }

function dot(state: string) {
  if (state === 'running') return 'bg-emerald-500';
  if (state === 'restarting' || state === 'created') return 'bg-amber-500';
  return 'bg-neutral-400';
}

async function load() { try { data.value = await api.get<Containers>('/api/v1/admin/docker/containers'); } catch { /* keep */ } }
onMounted(() => { void load(); poll = setInterval(load, 5000); });
onUnmounted(() => { if (poll) clearInterval(poll); });

async function act(service: string, action: 'restart' | 'stop' | 'start' | 'recreate') {
  if ((action === 'stop' || action === 'recreate') && !(await confirmAsk(t('settings.containers_confirm', { s: service })))) return;
  busy.value = `${service}:${action}`;
  try {
    const r = await api.post<{ ok: boolean }>('/api/v1/admin/docker/action', { service, action });
    r.ok ? success(t('common.saved')) : error(t('common.error'));
    await load();
  } catch { error(t('common.error')); } finally { busy.value = ''; }
}

async function update(service: string) {
  if (!(await confirmAsk(t('settings.containers_update_confirm', { s: service })))) return;
  busy.value = `${service}:update`;
  try {
    const pull = await api.post<{ ok: boolean }>('/api/v1/admin/docker/action', { service, action: 'pull' });
    if (!pull.ok) { error(t('common.error')); return; }
    const rec = await api.post<{ ok: boolean }>('/api/v1/admin/docker/action', { service, action: 'recreate' });
    rec.ok ? success(t('common.saved')) : error(t('common.error'));
    await load();
  } catch { error(t('common.error')); } finally { busy.value = ''; }
}

async function showLogs(service: string) {
  logs.open = true; logs.service = service; logs.loading = true;
  try {
    const r = await api.post<{ output: string }>('/api/v1/admin/docker/action', { service, action: 'logs' });
    logs.text = r.output ?? '';
  } catch { logs.text = t('common.error'); } finally { logs.loading = false; }
}

async function copy(text: string) {
  try { await navigator.clipboard.writeText(text); success(t('common.copied')); } catch { /* blocked */ }
}
</script>
