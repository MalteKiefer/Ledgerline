<template>
  <div class="space-y-4">
    <!-- Top status band -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
      <div v-for="s in statCards" :key="s.label" class="rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] p-3">
        <div class="flex items-center gap-1.5 text-xs text-[var(--ll-muted)]"><Icon :name="s.icon" :size="15" />{{ s.label }}</div>
        <div class="mt-1 truncate text-lg font-semibold tabular-nums" :class="s.tone">{{ s.value }}</div>
        <div v-if="s.sub" class="truncate text-xs text-[var(--ll-muted)]">{{ s.sub }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <!-- Server / health -->
      <Card :title="t('dash.server')">
        <div class="space-y-2 text-sm">
          <Row :label="t('dash.version_app')" :value="d?.versions.app || '—'" />
          <Row :label="'PHP'" :value="d?.versions.php || '—'" mono />
          <Row :label="'Laravel'" :value="d?.versions.laravel || '—'" mono />
          <div class="flex items-center justify-between"><span class="text-[var(--ll-muted)]">{{ t('dash.db') }}</span><HealthDot :s="d?.health.database" /></div>
          <div class="flex items-center justify-between"><span class="text-[var(--ll-muted)]">{{ t('dash.cache') }}</span><HealthDot :s="d?.health.cache" /></div>
          <Row :label="t('dash.queue_driver')" :value="d?.health.queue_driver || '—'" mono />
        </div>
      </Card>

      <!-- Resources -->
      <Card :title="t('dash.resources')">
        <div class="space-y-3 text-sm">
          <div>
            <div class="mb-1 flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('dash.disk') }}</span><span class="tabular-nums">{{ bytes(diskUsed) }} / {{ bytes(d?.resources.disk.total) }}</span></div>
            <Bar :pct="diskPct" :tone="diskPct > 90 ? 'bg-red-500' : diskPct > 75 ? 'bg-amber-500' : 'bg-primary-500'" />
          </div>
          <div class="space-y-1.5">
            <div class="text-xs font-medium text-[var(--ll-muted)]">{{ t('dash.storage_by_module') }} · {{ bytes(d?.resources.storage.total) }}</div>
            <StoreRow :label="t('dash.st_files')" :v="d?.resources.storage.files" :total="d?.resources.storage.total" />
            <StoreRow :label="t('dash.st_gallery')" :v="d?.resources.storage.gallery" :total="d?.resources.storage.total" />
            <StoreRow :label="t('dash.st_database')" :v="d?.resources.storage.database" :total="d?.resources.storage.total" />
          </div>
          <div v-if="d?.resources.trend" class="text-xs text-[var(--ll-muted)]">
            {{ t('dash.growth', { d: String(d.resources.trend.deltaDays) }) }}: <span class="font-medium" :class="d.resources.trend.deltaBytes >= 0 ? '' : 'text-emerald-600'">{{ d.resources.trend.deltaBytes >= 0 ? '+' : '' }}{{ bytes(d.resources.trend.deltaBytes) }}</span>
          </div>
        </div>
      </Card>

      <!-- Containers -->
      <Card :title="t('dash.containers')">
        <template #actions><RouterLink :to="{ name: 'settings.containers' }" class="text-xs text-primary-600 hover:underline">{{ t('dash.manage') }}</RouterLink></template>
        <div v-if="containers === null" class="text-sm text-[var(--ll-muted)]">…</div>
        <div v-else-if="!containers.configured || containers.reachable === false" class="text-sm text-[var(--ll-muted)]">{{ t('dash.agent_off') }}</div>
        <table v-else class="w-full text-sm">
          <tbody>
            <tr v-for="c in containers.services" :key="c.service" class="border-b border-[var(--ll-border)]/50 last:border-0">
              <td class="py-1.5"><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :class="stateDot(c.state)" />{{ c.service }}</span></td>
              <td class="py-1.5 text-right tabular-nums text-[var(--ll-muted)]">{{ c.cpu || '' }}</td>
              <td class="py-1.5 pl-3 text-right tabular-nums text-[var(--ll-muted)]">{{ (c.mem || '').split(' / ')[0] }}</td>
            </tr>
          </tbody>
        </table>
      </Card>

      <!-- Scheduler -->
      <Card :title="t('dash.scheduler')">
        <div class="max-h-64 space-y-1 overflow-y-auto text-sm">
          <div v-for="task in d?.scheduler.tasks ?? []" :key="task.name" class="flex items-center gap-2">
            <span class="h-2 w-2 shrink-0 rounded-full" :class="task.lastOk === null ? 'bg-neutral-300' : task.lastOk ? 'bg-emerald-500' : 'bg-red-500'" />
            <span class="min-w-0 flex-1 truncate font-mono text-xs">{{ task.name }}</span>
            <span class="shrink-0 text-xs text-[var(--ll-muted)]">{{ task.lastAt ? ago(task.lastAt) : t('dash.never') }}</span>
          </div>
          <div v-if="!(d?.scheduler.tasks ?? []).length" class="text-[var(--ll-muted)]">—</div>
        </div>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, h, type PropType } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { Card, Icon } from '@spa/ui';

interface Dashboard {
  versions: { app: string; php: string; laravel: string };
  health: { database: string; cache: string; queue_driver: string };
  resources: { disk: { free: number; total: number }; storage: { files: number; gallery: number; database: number; total: number }; trend: { points: { date: string; total: number }[]; deltaBytes: number; deltaDays: number } | null };
  queue: { pending: number; failed: number };
  scheduler: { lastRunAt: string | null; tasks: { name: string; expression: string; lastAt: string | null; lastOk: boolean | null }[] };
  errors: { unresolved: number; total: number; lastAt: string | null };
  backup: { lastSuccessAt: string | null; lastVerifyStatus: string | null; lastVerifyAt: string | null };
  counts: { users: number; admins: number; blocked_users: number; web_sessions: number; device_tokens: number; blocked_ips: number };
}
interface Svc { service: string; state: string; cpu?: string; mem?: string }
interface Containers { configured: boolean; reachable?: boolean; services?: Svc[] }

const d = ref<Dashboard | null>(null);
const containers = ref<Containers | null>(null);
let poll: ReturnType<typeof setInterval> | null = null;

async function load() {
  try { d.value = await api.get<Dashboard>('/api/v1/admin/dashboard'); } catch { /* keep */ }
  try { containers.value = await api.get<Containers>('/api/v1/admin/docker/containers'); } catch { /* keep */ }
}
onMounted(() => { void load(); poll = setInterval(load, 8000); });
onUnmounted(() => { if (poll) clearInterval(poll); });

const diskUsed = computed(() => (d.value ? d.value.resources.disk.total - d.value.resources.disk.free : 0));
const diskPct = computed(() => (d.value && d.value.resources.disk.total > 0 ? Math.round((diskUsed.value / d.value.resources.disk.total) * 100) : 0));

const statCards = computed(() => [
  { label: t('dash.users'), icon: 'group', value: n(d.value?.counts.users), sub: t('dash.admins', { n: String(d.value?.counts.admins ?? 0) }), tone: '' },
  { label: t('dash.sessions'), icon: 'devices', value: n(d.value?.counts.web_sessions), sub: t('dash.devices', { n: String(d.value?.counts.device_tokens ?? 0) }), tone: '' },
  { label: t('dash.queue'), icon: 'conveyor_belt', value: n(d.value?.queue.pending), sub: (d.value?.queue.failed ?? 0) > 0 ? t('dash.failed', { n: String(d.value?.queue.failed) }) : '', tone: (d.value?.queue.pending ?? 0) > 0 ? 'text-primary-600' : '' },
  { label: t('dash.errors'), icon: 'error', value: n(d.value?.errors.unresolved), sub: d.value?.errors.lastAt ? ago(d.value.errors.lastAt) : '', tone: (d.value?.errors.unresolved ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600' },
  { label: t('dash.backup'), icon: 'backup', value: d.value?.backup.lastSuccessAt ? ago(d.value.backup.lastSuccessAt) : t('dash.never'), sub: d.value?.backup.lastVerifyStatus ?? '', tone: '' },
  { label: t('dash.blocked'), icon: 'block', value: n(d.value?.counts.blocked_ips), sub: (d.value?.counts.blocked_users ?? 0) > 0 ? t('dash.blocked_users', { n: String(d.value?.counts.blocked_users) }) : '', tone: '' },
]);

function n(v: number | undefined) { return v === undefined ? '—' : String(v); }
function bytes(v: number | undefined | null): string {
  if (v === undefined || v === null) return '—';
  if (v === 0) return '0 B';
  const u = ['B', 'KB', 'MB', 'GB', 'TB']; const i = Math.min(u.length - 1, Math.floor(Math.log(Math.abs(v)) / Math.log(1024)));
  return `${(v / 1024 ** i).toFixed(i ? 1 : 0)} ${u[i]}`;
}
function ago(iso: string): string {
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return `${s}s`; if (s < 3600) return `${Math.floor(s / 60)}m`; if (s < 86400) return `${Math.floor(s / 3600)}h`;
  return `${Math.floor(s / 86400)}d`;
}
function stateDot(s: string) { return s === 'running' ? 'bg-emerald-500' : s === 'restarting' || s === 'created' ? 'bg-amber-500' : 'bg-neutral-400'; }

// Small inline presentational helpers.
const Row = (props: { label: string; value: string; mono?: boolean }) => h('div', { class: 'flex items-center justify-between' }, [
  h('span', { class: 'text-[var(--ll-muted)]' }, props.label),
  h('span', { class: props.mono ? 'font-mono text-xs' : '' }, props.value),
]);
const HealthDot = (props: { s?: string }) => h('span', { class: 'inline-flex items-center gap-1.5 text-xs' }, [
  h('span', { class: `h-2 w-2 rounded-full ${props.s === 'up' ? 'bg-emerald-500' : 'bg-red-500'}` }),
  props.s === 'up' ? 'OK' : (props.s ?? '—'),
]);
const Bar = (props: { pct: number; tone: string }) => h('div', { class: 'h-2 w-full overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10' }, [
  h('div', { class: `h-full ${props.tone}`, style: { width: `${Math.min(100, Math.max(2, props.pct))}%` } }),
]);
const StoreRow = (props: { label: string; v: PropType<number> | number | undefined; total: number | undefined }) => {
  const v = (props.v as number) ?? 0; const total = props.total || 1;
  return h('div', {}, [
    h('div', { class: 'mb-0.5 flex justify-between text-xs' }, [h('span', { class: 'text-[var(--ll-muted)]' }, props.label as string), h('span', { class: 'tabular-nums' }, bytes(v))]),
    h(Bar, { pct: Math.round((v / total) * 100), tone: 'bg-primary-400' }),
  ]);
};
</script>
