<template>
  <Card :body-class="'p-0'">
    <template #header>
      <Icon name="monitoring" :size="18" class="text-[var(--ll-muted)]" />
      <h2 class="text-sm font-semibold">{{ t('settings.request_log_title') }}</h2>
    </template>
    <template #actions>
      <Btn variant="ghost" size="sm" icon="refresh" :loading="loading" @click="reload">{{ t('settings.request_log_refresh') }}</Btn>
      <Btn tag="a" variant="soft" size="sm" icon="download" :href="exportUrl('csv')">CSV</Btn>
      <Btn tag="a" variant="soft" size="sm" icon="download" :href="exportUrl('json')">JSON</Btn>
    </template>

    <!-- Filter toolbar -->
    <div class="grid grid-cols-2 gap-2 border-b border-[var(--ll-border)] px-4 py-3 sm:grid-cols-3 lg:grid-cols-6">
      <TextField v-model="filters.ip" :placeholder="t('settings.request_log_filter_ip')" icon="lan" @enter="reload" />
      <TextField v-model="filters.path" :placeholder="t('settings.request_log_filter_path')" icon="search" @enter="reload" />
      <Select v-model="filters.method" :options="methodOptions" />
      <TextField v-model="filters.status" :placeholder="t('settings.request_log_filter_status')" inputmode="numeric" @enter="reload" />
      <TextField v-model="filters.user_id" :placeholder="t('settings.request_log_filter_user')" inputmode="numeric" @enter="reload" />
      <TextField v-model="filters.since" :placeholder="t('settings.request_log_filter_since')" @enter="reload" />
      <div class="col-span-2 flex items-center gap-2 sm:col-span-3 lg:col-span-6">
        <Btn variant="solid" size="sm" icon="filter_alt" @click="reload">{{ t('common.search') }}</Btn>
        <Btn variant="ghost" size="sm" icon="close" @click="resetFilters">{{ t('settings.request_log_reset') }}</Btn>
      </div>
    </div>

    <div v-if="loading" class="h-0.5 w-full overflow-hidden bg-primary-500/15">
      <div class="h-full w-1/3 animate-pulse bg-primary-500" />
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
          <tr class="border-b border-[var(--ll-border)]">
            <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_time') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_method') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_path') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_status') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_ip') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_user') }}</th>
            <th class="px-4 py-2.5 font-medium">{{ t('settings.request_log_col_ua') }}</th>
            <th class="px-4 py-2.5 font-medium text-right">{{ t('settings.request_log_col_duration') }}</th>
            <th class="px-4 py-2.5 font-medium text-right"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading && !sec.requests.length"><td colspan="9" class="px-4 py-8 text-center text-[var(--ll-muted)]">…</td></tr>
          <template v-else>
            <tr v-for="r in sec.requests" :key="r.id" class="border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5">
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ fmtDate(r.time) }}</td>
              <td class="px-4 py-2.5"><Badge tone="gray">{{ r.method }}</Badge></td>
              <td class="max-w-[22rem] truncate px-4 py-2.5 font-mono text-xs" :title="r.path">{{ r.path }}</td>
              <td class="px-4 py-2.5"><Badge :tone="statusTone(r.status)">{{ r.status }}</Badge></td>
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ r.ip }}</td>
              <td class="px-4 py-2.5">{{ r.user ? r.user.name : '—' }}</td>
              <td class="max-w-[16rem] truncate px-4 py-2.5 text-xs text-[var(--ll-muted)]" :title="r.user_agent || ''">{{ r.user_agent }}</td>
              <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono text-xs">{{ r.duration_ms != null ? r.duration_ms + ' ms' : '' }}</td>
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="r.ip" class="grid h-8 w-8 place-items-center rounded-lg text-red-600 hover:bg-red-500/10"
                    :title="t('settings.request_log_block_ip')" @click="onBlockIp(r)"
                  >
                    <Icon name="block" :size="18" />
                  </button>
                  <button
                    v-if="r.user" class="grid h-8 w-8 place-items-center rounded-lg text-red-600 hover:bg-red-500/10"
                    :title="t('settings.request_log_block_user')" @click="onBlockUser(r)"
                  >
                    <Icon name="person_off" :size="18" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!sec.requests.length"><td colspan="9" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between gap-2 border-t border-[var(--ll-border)] px-4 py-3">
      <span class="text-xs text-[var(--ll-muted)]">
        {{ t('settings.request_log_page', { current: String(sec.requestMeta.current_page), last: String(sec.requestMeta.last_page) }) }} · {{ sec.requestMeta.total }}
      </span>
      <div class="flex items-center gap-2">
        <Btn variant="outline" size="sm" icon="chevron_left" :disabled="sec.requestMeta.current_page <= 1 || loading" @click="go(sec.requestMeta.current_page - 1)">{{ t('settings.request_log_prev') }}</Btn>
        <Btn variant="outline" size="sm" :disabled="sec.requestMeta.current_page >= sec.requestMeta.last_page || loading" @click="go(sec.requestMeta.current_page + 1)">{{ t('settings.request_log_next') }}</Btn>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, Badge, TextField, Select } from '@spa/ui';
import { useSecurityStore, type RequestLogRow, type RequestLogParams } from '@spa/stores/security';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';

const sec = useSecurityStore();
const { success, error } = useToast();
const loading = ref(false);
const page = ref(1);
const perPage = 50;

const filters = reactive({ ip: '', path: '', method: '', status: '', user_id: '', since: '' });

const methodOptions = [
  { title: t('settings.request_log_method_all'), value: '' },
  { title: 'GET', value: 'GET' },
  { title: 'POST', value: 'POST' },
  { title: 'PUT', value: 'PUT' },
  { title: 'PATCH', value: 'PATCH' },
  { title: 'DELETE', value: 'DELETE' },
];

function params(): RequestLogParams {
  return {
    page: page.value, per_page: perPage,
    ip: filters.ip, path: filters.path, method: filters.method,
    status: filters.status, user_id: filters.user_id, since: filters.since,
  };
}
function exportUrl(format: 'csv' | 'json'): string {
  const { page: _p, per_page: _pp, ...f } = params();
  void _p; void _pp;
  return sec.exportRequestLogUrl(format, f);
}
function statusTone(status: number): 'gray' | 'info' | 'warning' | 'error' {
  if (status >= 500) return 'error';
  if (status >= 400) return 'warning';
  if (status >= 300) return 'info';
  return 'gray';
}
function fmtDate(v: string | null): string {
  if (!v) return '';
  const d = new Date(v);
  return isNaN(d.getTime()) ? String(v) : d.toLocaleString(document.documentElement.lang || 'de');
}

async function load() { loading.value = true; try { await sec.loadRequestLog(params()); } catch { error(t('common.error')); } finally { loading.value = false; } }
function reload() { page.value = 1; void load(); }
function go(p: number) { page.value = Math.max(1, p); void load(); }
function resetFilters() { Object.assign(filters, { ip: '', path: '', method: '', status: '', user_id: '', since: '' }); reload(); }

async function onBlockIp(r: RequestLogRow) {
  if (!r.ip) return;
  const reason = await promptAsk(t('settings.request_log_block_ip'), { value: '', placeholder: t('settings.blocks_reason_placeholder') });
  // promptAsk returns null on cancel; empty reason is allowed → confirm explicitly then.
  if (reason === null) {
    if (!await confirmAsk(t('settings.block_ip') + ' ' + r.ip, { danger: true })) return;
  }
  try { await sec.blockIp(r.ip, reason ?? undefined); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function onBlockUser(r: RequestLogRow) {
  if (!r.user) return;
  if (!await confirmAsk(t('settings.users_block_confirm'), { danger: true })) return;
  try { await sec.blockUser(r.user.id); success(t('common.saved')); } catch { error(t('common.error')); }
}

onMounted(() => { void load(); });
</script>
