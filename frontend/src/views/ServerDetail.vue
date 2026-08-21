<template>
  <div v-if="server" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <button class="text-xs text-[var(--ll-muted)] hover:underline" @click="$router.push('/servers')">
          ← {{ t('servers.title') }}
        </button>
        <div class="mt-1 flex items-center gap-2">
          <DistroLogo :id="facts?.os.id" :id-like="facts?.os.id_like" :size="34" :title="facts?.os.name" />
          <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="dotClass(server)" />
          <h1 class="truncate text-xl font-bold">{{ server.name }}</h1>
          <Badge :tone="server.status?.ok ? 'success' : server.status ? 'error' : 'gray'">{{ statusLabel(server) }}</Badge>
          <Badge v-if="server.restricted_key" tone="gray">{{ t('servers.restricted_key_short') }}</Badge>
        </div>
        <p class="mt-0.5 font-mono text-xs text-[var(--ll-muted)]">{{ server.username }}@{{ server.host }}:{{ server.port }}</p>
      </div>
      <div class="flex items-center gap-2">
        <Btn variant="ghost" icon="network_check" :disabled="testing" @click="retest">{{ testing ? t('servers.testing') : t('servers.test') }}</Btn>
        <Btn variant="ghost" icon="refresh" @click="doRefresh">{{ t('servers.refresh') }}</Btn>
        <Btn variant="ghost" icon="edit" @click="$router.push({ path: '/servers', query: { edit: String(server.id) } })">{{ t('servers.edit') }}</Btn>
      </div>
    </div>

    <p v-if="retestResult" class="rounded-lg px-3 py-2 text-sm" :class="retestResult.ok ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'">
      {{ retestResult.ok ? t('servers.test_ok') : errorText(retestResult.error) }}
    </p>
    <p v-else-if="server.status && !server.status.ok" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">
      {{ errorText(server.status.error) }}
    </p>

    <!-- Tabs. The page had grown into one long scroll; logs and the terminal
         are separate concerns and do not belong stacked under the metrics. -->
    <div class="flex gap-1 border-b border-[var(--ll-border)]">
      <button
        v-for="tb in tabs"
        :key="tb.id"
        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors"
        :class="tab === tb.id ? 'border-[var(--ll-accent)] text-[var(--ll-accent)]' : 'border-transparent text-[var(--ll-muted)] hover:text-[var(--ll-text)]'"
        @click="setTab(tb.id)"
      >
        {{ tb.label }}
      </button>
    </div>

    <template v-if="tab === 'overview'">
    <template v-if="facts">
      <!-- Headline figures. These four answer "is anything wrong" at a glance. -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.cpu') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ facts.cpu.used_pct ?? '—' }}%</div>
          <div class="mt-0.5 truncate text-[0.7rem] text-[var(--ll-muted)]">{{ facts.cpu.cores ? t('servers.cores', { n: String(facts.cpu.cores) }) : '—' }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.load') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ facts.load[0]?.toFixed(2) ?? '—' }}</div>
          <div class="mt-0.5 text-[0.7rem] text-[var(--ll-muted)]">{{ loadNote }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.memory') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ facts.mem.used_pct ?? '—' }}%</div>
          <div class="mt-0.5 text-[0.7rem] text-[var(--ll-muted)]">{{ memoryNote(facts) }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.disks') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ facts.disk_max_pct ?? '—' }}%</div>
          <div class="mt-0.5 truncate text-[0.7rem] text-[var(--ll-muted)]">{{ fullestDisk(facts)?.mount ?? '—' }}</div>
        </Card>
        <Card :body-class="'p-4'">
          <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.uptime') }}</div>
          <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ formatUptime(facts.uptime_s) }}</div>
          <div class="mt-0.5 text-[0.7rem] text-[var(--ll-muted)]">{{ facts.boot_at ? fmtDateTime(facts.boot_at) : '' }}</div>
        </Card>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- System -->
        <Card :title="t('servers.section_system')" :body-class="'p-4'">
          <dl class="space-y-1.5 text-xs">
            <Row :label="t('servers.hostname')" :value="facts.hostname" />
            <Row :label="t('servers.os')" :value="facts.os.name" />
            <Row :label="t('servers.kernel')" :value="facts.kernel" />
            <Row :label="t('servers.arch')" :value="facts.arch" />
            <Row :label="t('servers.cpu')" :value="cpuText(facts)" />
            <Row v-if="facts.virt" :label="t('servers.virt')" :value="facts.virt" />
            <Row v-if="facts.temp_c != null" :label="t('servers.temperature')" :value="`${facts.temp_c} °C`" />
            <Row :label="t('servers.updates')" :value="facts.updates === null ? t('servers.updates_unknown') : String(facts.updates)" />
            <Row v-if="facts.reboot_required" :label="t('servers.reboot_required')" :value="t('common.yes')" />
          </dl>
        </Card>

        <!-- Storage + memory meters -->
        <Card class="lg:col-span-2" :title="t('servers.section_capacity')" :body-class="'p-4'">
          <Meter :label="t('servers.memory')" :pct="facts.mem.used_pct" :note="memoryNote(facts)" />
          <Meter v-if="facts.mem.swap_total_kb" class="mt-2.5" :label="t('servers.swap')" :pct="swapPct(facts)" :note="swapNote(facts)" />
          <div v-for="d in facts.disks" :key="d.mount" class="mt-2.5">
            <Meter :label="d.mount" :pct="d.used_pct" :note="diskNote(d)" />
            <p class="mt-0.5 font-mono text-[0.65rem] text-[var(--ll-muted)]">{{ d.fs }}</p>
          </div>
          <p v-if="!facts.disks.length" class="text-xs text-[var(--ll-muted)]">{{ t('common.none') }}</p>
        </Card>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Network -->
        <Card v-if="facts.addresses.length || facts.ports.length" :title="t('servers.section_network')" :body-class="'p-4'">
          <template v-if="facts.addresses.length">
            <h3 class="mb-1 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.addresses') }}</h3>
            <div class="mb-3 space-y-0.5 font-mono text-xs">
              <div v-for="a in facts.addresses" :key="a">{{ a }}</div>
            </div>
          </template>
          <template v-if="facts.ports.length">
            <h3 class="mb-1 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.ports') }}</h3>
            <div class="flex flex-wrap gap-1.5">
              <Badge v-for="p in facts.ports" :key="p" tone="gray">{{ p }}</Badge>
            </div>
          </template>
        </Card>

        <!-- Processes -->
        <Card v-if="facts.processes.length" :title="t('servers.section_processes')" :body-class="'p-4'">
          <div class="space-y-1 text-xs">
            <div v-for="proc in facts.processes" :key="proc.name" class="flex justify-between gap-3">
              <span class="truncate font-mono">{{ proc.name }}</span>
              <span class="shrink-0 tabular-nums text-[var(--ll-muted)]">{{ formatGib(proc.rss_kb) }}</span>
            </div>
          </div>
          <p class="mt-2 text-[0.65rem] text-[var(--ll-muted)]">{{ t('servers.processes_note') }}</p>
        </Card>

        <!-- Services -->
        <Card v-if="facts.failed_units.length" :title="t('servers.failed_units')" :body-class="'p-4'">
          <div class="flex flex-wrap gap-1.5">
            <Badge v-for="u in facts.failed_units" :key="u" tone="error">{{ u }}</Badge>
          </div>
        </Card>

        <!-- Sessions -->
        <Card v-if="facts.sessions.length" :title="t('servers.section_sessions')" :body-class="'p-4'">
          <div class="space-y-0.5 font-mono text-xs">
            <div v-for="line in facts.sessions" :key="line">{{ line }}</div>
          </div>
        </Card>
      </div>

      <!-- Containers -->
      <Card v-if="facts.containers.length" :title="t('servers.containers')" :body-class="'p-0'">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <tbody>
              <tr v-for="c in facts.containers" :key="c.name" class="border-b border-[var(--ll-border)] last:border-0">
                <td class="px-4 py-2 font-mono">{{ c.name }}</td>
                <td class="px-4 py-2 text-right text-[var(--ll-muted)]">
                  <Badge :tone="c.status.startsWith('Up') ? 'success' : 'warning'">{{ c.status }}</Badge>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </template>

    <!-- Reachability: pings and port checks, sampled every few minutes -->
    <Card v-if="checks.length" :body-class="'p-4'">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold">{{ t('servers.reachability') }}</h2>
        <div class="flex items-center gap-1">
          <button
            v-for="h in [6, 24, 168]"
            :key="h"
            class="rounded-md px-2 py-1 text-xs"
            :class="checkHours === h ? 'bg-[var(--ll-accent)] text-white' : 'text-[var(--ll-muted)] hover:bg-[var(--ll-hover)]'"
            @click="setHours(h)"
          >
            {{ h < 24 ? t('servers.window_h', { n: String(h) }) : t('servers.window_d', { n: String(Math.round(h / 24)) }) }}
          </button>
        </div>
      </div>

      <div v-if="latencyPoints.length > 1" class="-ml-1 mb-3">
        <Chart :data="latencyData" :options="latencyOptions" :height="140" />
      </div>

      <div class="divide-y divide-[var(--ll-border)]">
        <div v-for="c in checks" :key="c.kind + ':' + (c.port ?? '-')" class="flex items-center gap-3 py-2">
          <span class="h-2 w-2 shrink-0 rounded-full" :class="c.last?.ok ? 'bg-emerald-500' : 'bg-red-500'" />
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-medium">{{ checkTitle(c) }}</div>
            <div class="text-[0.7rem] text-[var(--ll-muted)]">
              <template v-if="c.last?.ok">{{ c.last.ms !== null ? `${c.last.ms} ms` : '' }}</template>
              <template v-else>{{ errorText(c.last?.error ?? null) }}</template>
              · {{ t('servers.samples_n', { n: String(c.samples) }) }}
            </div>
          </div>
          <div class="shrink-0 text-right">
            <div class="font-mono text-sm tabular-nums" :class="uptimeClass(c.uptime_pct)">{{ c.uptime_pct }}%</div>
            <div class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.uptime_window') }}</div>
          </div>
        </div>
      </div>
    </Card>

    <!-- History -->
    <Card v-if="trend.length > 1" :title="t('servers.history')" :body-class="'p-4'">
      <div class="-ml-1"><Chart :data="chartData" :options="chartOptions" :height="180" /></div>
      <div class="mt-2 flex gap-4 text-[0.7rem] text-[var(--ll-muted)]">
        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :style="{ background: CHART_INK }" />{{ t('servers.memory') }}</span>
        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :style="{ background: CHART_WARN }" />{{ t('servers.disks') }}</span>
      </div>
    </Card>

    <p v-if="server.note" class="whitespace-pre-line rounded-lg border border-[var(--ll-border)] p-3 text-sm">{{ server.note }}</p>

    <!-- Removal. Exactly one path, chosen from what the setup recorded — the
         reader should not have to work out which case they are in. -->
    <Card :title="t('servers.removal_title')" :body-class="'p-4'">
      <p class="text-sm">{{ t('servers.removal_intro') }}</p>
      <p v-if="server.account_created === null" class="mt-2 rounded bg-amber-500/10 px-2.5 py-2 text-xs text-amber-700 dark:text-amber-400">
        {{ t('servers.removal_unknown_case') }}
      </p>
      <pre class="mt-3 overflow-x-auto rounded-lg bg-black/[0.05] p-3 font-mono text-xs dark:bg-white/5">{{ removalCommands }}</pre>
      <div class="mt-2 flex items-center gap-2">
        <Btn variant="ghost" size="sm" icon="content_copy" @click="copyRemoval">{{ t('common.copy') }}</Btn>
        <label class="flex items-center gap-2 text-xs"><input v-model="useSudo" type="checkbox" class="accent-primary-500">{{ t('servers.use_sudo') }}</label>
      </div>
      <p class="mt-2 text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.removal_footprint') }}</p>
      <p v-if="server.host_fingerprint" class="mt-3 break-all font-mono text-[0.7rem] text-[var(--ll-muted)]">
        {{ t('servers.fingerprint') }}: {{ server.host_fingerprint }}
      </p>
    </Card>
    </template>

    <!-- Logs -->
    <template v-else-if="tab === 'logs'">
      <Card :body-class="'p-4'">
        <div class="flex flex-wrap items-end gap-3">
          <label class="min-w-0">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.log_source') }}</span>
            <select v-model="logSource" class="rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm">
              <option v-if="sources?.journal" value="journal">{{ t('servers.log_journal') }}</option>
              <option v-if="sources?.containers.length" value="docker">{{ t('servers.log_docker') }}</option>
              <option v-if="sources?.files.length" value="file">{{ t('servers.log_file') }}</option>
            </select>
          </label>

          <!-- Every option below came from the host itself. The browser picks
               from that answer rather than naming something of its own. -->
          <label v-if="logSource === 'journal' && sources?.units.length" class="min-w-0">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.log_unit') }}</span>
            <select v-model="logUnit" class="max-w-[15rem] rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm">
              <option value="">{{ t('servers.log_all_units') }}</option>
              <option v-for="u in sources.units" :key="u" :value="u">{{ u }}</option>
            </select>
          </label>

          <label v-if="logSource === 'docker'" class="min-w-0">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.containers') }}</span>
            <select v-model="logContainer" class="max-w-[15rem] rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm">
              <option v-for="c in sources?.containers ?? []" :key="c" :value="c">{{ c }}</option>
            </select>
          </label>

          <label v-if="logSource === 'file'" class="min-w-0">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.log_file') }}</span>
            <select v-model="logPath" class="max-w-[18rem] rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 font-mono text-xs">
              <option v-for="f in sources?.files ?? []" :key="f" :value="f">{{ f }}</option>
            </select>
          </label>

          <label class="w-24">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.log_lines') }}</span>
            <input v-model.number="logLines" type="number" min="1" max="2000" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm">
          </label>

          <label v-if="logSource === 'journal'" class="flex items-center gap-2 pb-2 text-sm">
            <input v-model="logErrorsOnly" type="checkbox" class="accent-primary-500">{{ t('servers.log_errors_only') }}
          </label>

          <Btn variant="solid" size="sm" icon="download" :disabled="logBusy" class="mb-0.5" @click="fetchLog">
            {{ logBusy ? t('servers.log_loading') : t('servers.log_fetch') }}
          </Btn>
          <Btn v-if="logText" variant="ghost" size="sm" icon="content_copy" class="mb-0.5" @click="copyLog">{{ t('common.copy') }}</Btn>
        </div>

        <p v-if="sourcesError" class="mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">{{ t('servers.log_sources_failed') }}</p>
        <p v-else-if="!sources" class="mt-3 text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
        <p v-else-if="!hasAnySource" class="mt-3 text-sm text-[var(--ll-muted)]">{{ t('servers.log_none_available') }}</p>

        <p v-if="logError" class="mt-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ logError }}</p>

        <pre
          v-if="logText"
          class="mt-3 max-h-[32rem] overflow-auto rounded-lg bg-black/[0.05] p-3 font-mono text-[0.7rem] leading-relaxed dark:bg-white/5"
        >{{ logText }}</pre>
      </Card>
    </template>

    <!-- Terminal. Mounted only while its tab is open, so leaving the tab ends
         the session rather than leaving a shell waiting on the idle timeout. -->
    <template v-else-if="tab === 'terminal'">
      <Card :body-class="'p-4'">
        <ServerTerminal :server-id="server.id" />
      </Card>
    </template>
  </div>

  <div v-else-if="loading" class="p-10 text-center text-[var(--ll-muted)]">{{ t('common.loading') }}</div>
  <div v-else class="p-10 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</div>
</template>

<script setup lang="ts">
import { computed, h, onMounted, ref, type PropType, type VNode } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import type { AlignedData, Options } from 'uplot';
import { Card, Btn, Badge, Chart, DistroLogo } from '@spa/ui';
import { useServersStore, type Server, type ServerFacts, type ProbeResult, type TrendPoint, type ServerCheckSeries } from '@spa/stores/servers';
import {
  severity, formatUptime, formatGib, memoryNote, swapPct, swapNote, diskNote, fullestDisk,
} from '@spa/lib/server-facts';
import { useToast } from '@spa/composables/useToast';
import { ApiError } from '@spa/api/client';
import ServerTerminal from '@spa/components/ServerTerminal.vue';
import { fmtDate, fmtDateTime, fmtTime } from '@spa/lib/datetime';

const CHART_INK = '#6d4aff';
const CHART_WARN = '#e0a11b';
const CHART_CPU = '#2f9e6e';
const AXIS_INK = '#625d69';
const AXIS_FONT = '600 11px ui-monospace, SFMono-Regular, Menlo, monospace';

const route = useRoute();
const router = useRouter();
const s = useServersStore();
const { success } = useToast();

const server = ref<Server | null>(null);
const history = ref<TrendPoint[]>([]);
const loading = ref(true);
const testing = ref(false);
const retestResult = ref<ProbeResult | null>(null);
const useSudo = ref(true);

const facts = computed(() => server.value?.facts ?? null);

/** A labelled bar; the same one the list view uses. */
const Meter = (props: { label: string; pct: number | null; note?: string }) => h('div', {}, [
  h('div', { class: 'flex items-baseline justify-between gap-2 text-xs' }, [
    h('span', { class: 'truncate' }, props.label),
    h('span', { class: 'shrink-0 font-mono tabular-nums text-[var(--ll-muted)]' }, props.note ?? (props.pct === null ? '—' : `${props.pct}%`)),
  ]),
  h('div', { class: 'mt-1 h-1.5 overflow-hidden rounded-full bg-black/[0.07] dark:bg-white/10' }, [
    h('div', {
      class: ['h-full rounded-full', props.pct === null ? '' : props.pct >= 90 ? 'bg-red-500' : props.pct >= 75 ? 'bg-amber-500' : 'bg-primary-500'],
      style: { width: `${Math.min(100, Math.max(0, props.pct ?? 0))}%` },
    }),
  ]),
]);
Meter.props = { label: String, pct: { type: Number as unknown as PropType<number | null>, default: null }, note: String };

const Row = (props: { label: string; value?: string | null }) => h('div', { class: 'flex justify-between gap-3' }, [
  h('dt', { class: 'shrink-0 text-[var(--ll-muted)]' }, props.label),
  h('dd', { class: 'truncate text-right' }, props.value || '—'),
]);
Row.props = { label: String, value: String };

const DOT: Record<string, string> = {
  unknown: 'bg-black/20 dark:bg-white/25',
  down: 'bg-red-500',
  warn: 'bg-amber-500',
  ok: 'bg-emerald-500',
};
function dotClass(srv: Server): string { return DOT[severity(srv)]; }

function statusLabel(srv: Server): string {
  if (!srv.status) return t('servers.status_unknown');
  return srv.status.ok ? t('servers.status_ok') : t('servers.status_fail');
}

function errorText(code: string | null | undefined): string {
  if (!code) return t('servers.status_fail');
  const key = `servers.error.${code}`;
  const translated = t(key);
  return translated === key ? code : translated;
}

function cpuText(f: ServerFacts): string {
  const cores = f.cpu.cores === null ? '' : t('servers.cores', { n: String(f.cpu.cores) });
  return [f.cpu.model, cores].filter(Boolean).join(' · ');
}

/** Load only means something against the core count. */
const loadNote = computed(() => {
  const f = facts.value;
  if (!f || f.load.length === 0) return '';
  const per = f.cpu.cores ? ` · ${Math.round((f.load[0] / f.cpu.cores) * 100)}%` : '';
  return `${f.load.map((l) => l.toFixed(2)).join('  ')}${per}`;
});

// ---- tabs ----

type Tab = 'overview' | 'logs' | 'terminal';

const tab = ref<Tab>('overview');

const tabs = computed<{ id: Tab; label: string }[]>(() => [
  { id: 'overview', label: t('servers.tab_overview') },
  { id: 'logs', label: t('servers.tab_logs') },
  { id: 'terminal', label: t('servers.tab_terminal') },
]);

function setTab(next: Tab) {
  tab.value = next;
  // The page is already deep-linkable; the tab belongs in the URL for the same
  // reason the id does — so a link lands where the sender was looking.
  void router.replace({ query: { ...route.query, tab: next === 'overview' ? undefined : next } });
  if (next === 'logs' && sources.value === null) void loadSources();
}

// ---- logs ----

const sources = ref<{ journal: boolean; units: string[]; containers: string[]; files: string[] } | null>(null);
const sourcesError = ref(false);
const logSource = ref<'journal' | 'docker' | 'file'>('journal');
const logUnit = ref('');
const logContainer = ref('');
const logPath = ref('');
const logLines = ref(200);
const logErrorsOnly = ref(false);
const logText = ref('');
const logError = ref('');
const logBusy = ref(false);

const hasAnySource = computed(() => {
  const src = sources.value;
  return !!src && (src.journal || src.containers.length > 0 || src.files.length > 0);
});

/**
 * Ask the host what it has before offering anything. This is also the security
 * boundary: the selects below are populated from this answer, so a read names
 * something the host reported rather than something the browser invented.
 */
async function loadSources() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
  try {
    const r = await s.logSources(id);
    sources.value = r;
    sourcesError.value = r.error !== null;
    // Land on something that exists rather than on an empty journal select.
    if (!r.journal && r.containers.length) logSource.value = 'docker';
    else if (!r.journal && r.files.length) logSource.value = 'file';
    logContainer.value = r.containers[0] ?? '';
    logPath.value = r.files[0] ?? '';
  } catch {
    sourcesError.value = true;
  }
}

async function fetchLog() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
  logBusy.value = true;
  logError.value = '';
  try {
    const r = await s.readLog(id, {
      source: logSource.value,
      unit: logSource.value === 'journal' ? logUnit.value : '',
      container: logSource.value === 'docker' ? logContainer.value : '',
      path: logSource.value === 'file' ? logPath.value : '',
      lines: logLines.value,
      errors_only: logErrorsOnly.value,
    });
    // An empty log is an answer, not a failure — say so rather than leaving the
    // previous content on screen as if it were fresh.
    logText.value = r.text.trim() === '' ? t('servers.log_empty') : r.text;
  } catch (e) {
    logText.value = '';
    logError.value = e instanceof ApiError && typeof e.body === 'object' && e.body !== null && 'error' in e.body
      ? errorText(String((e.body as { error: unknown }).error))
      : t('servers.log_failed');
  } finally {
    logBusy.value = false;
  }
}

async function copyLog() {
  await navigator.clipboard.writeText(logText.value);
  success(t('common.copied'));
}

// ---- reachability ----

const checks = ref<ServerCheckSeries[]>([]);
const checkHours = ref(24);

async function loadChecks() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) return;
  try {
    checks.value = (await s.checks(id, checkHours.value)).checks;
  } catch {
    checks.value = [];
  }
}

function setHours(h: number) {
  checkHours.value = h;
  void loadChecks();
}

/**
 * What to call a check. ICMP has no port; the SSH check is named after its role,
 * because "22" alone does not explain why it is always there.
 */
function checkTitle(c: ServerCheckSeries): string {
  if (c.kind === 'icmp') return t('servers.check_icmp');
  const label = c.label ? `${c.label} · ` : '';
  return `${label}${t('servers.check_port', { port: String(c.port ?? '') })}`;
}

function uptimeClass(pct: number): string {
  if (pct >= 99.5) return 'text-emerald-600 dark:text-emerald-400';
  if (pct >= 95) return 'text-amber-600 dark:text-amber-400';
  return 'text-red-600 dark:text-red-400';
}

/**
 * The latency chart draws one series: whichever check is the best measure of
 * "how far away is this host". ICMP if we have it — it is the closest thing to
 * pure round-trip — otherwise the SSH handshake.
 */
const latencySeries = computed<ServerCheckSeries | null>(
  () => checks.value.find((c) => c.kind === 'icmp') ?? checks.value[0] ?? null,
);

const latencyPoints = computed(() => (latencySeries.value?.points ?? []).filter((p) => p.ms !== null));

const latencyData = computed<AlignedData>(() => [
  latencyPoints.value.map((p) => Math.floor(new Date(p.t).getTime() / 1000)),
  latencyPoints.value.map((p) => p.ms as number),
]);

const latencyOptions = computed<Omit<Options, 'width' | 'height'>>(() => ({
  padding: [12, 12, 0, 0],
  legend: { show: false },
  cursor: { drag: { x: false, y: false } },
  series: [{}, { label: 'ms', stroke: CHART_INK, fill: CHART_INK + '1f' }],
  axes: [
    {
      stroke: AXIS_INK,
      font: AXIS_FONT,
      grid: { show: false },
      space: 84,
      values: (_u, splits) => splits.map((ts) => (checkHours.value > 24 ? `${fmtDate(ts * 1000)} ${fmtTime(ts * 1000)}` : fmtTime(ts * 1000))),
    },
    { stroke: AXIS_INK, font: AXIS_FONT, grid: { stroke: 'rgba(128,128,128,.24)' }, size: 44, values: (_u, vals) => vals.map((v) => `${v}`) },
  ],
  scales: { x: { time: true } },
}));

// ---- history ----

const trend = computed(() => [...history.value].reverse().filter((p) => p.ok));

/**
 * A real time scale, not an index. With indices uPlot has no idea what the gaps
 * between points mean, so it spaces ticks evenly and we were left thinning the
 * labels by hand — which is why they still collided. Given seconds and
 * `time: true` it picks tick positions that fit the width itself.
 */
const chartData = computed<AlignedData>(() => [
  trend.value.map((p) => Math.floor(new Date(p.collected_at).getTime() / 1000)),
  trend.value.map((p) => p.cpu_used_pct ?? null),
  trend.value.map((p) => p.mem_used_pct ?? null),
  trend.value.map((p) => p.disk_max_pct ?? null),
]);

/** True once the window spans more than a day, when a bare clock time is ambiguous. */
const trendSpansDays = computed(() => {
  const xs = trend.value;
  if (xs.length < 2) return false;
  const a = new Date(xs[0].collected_at).getTime();
  const b = new Date(xs[xs.length - 1].collected_at).getTime();
  return b - a > 24 * 3600 * 1000;
});

const chartOptions = computed<Omit<Options, 'width' | 'height'>>(() => ({
  padding: [12, 12, 0, 0],
  legend: { show: false },
  cursor: { drag: { x: false, y: false } },
  series: [
    {},
    { label: t('servers.cpu'), stroke: CHART_CPU, width: 1.5 },
    { label: t('servers.memory'), stroke: CHART_INK, fill: CHART_INK + '26' },
    { label: t('servers.disks'), stroke: CHART_WARN },
  ],
  axes: [
    {
      stroke: AXIS_INK,
      font: AXIS_FONT,
      grid: { show: false },
      // Minimum pixels between ticks. uPlot drops ticks that would not fit, so
      // the labels cannot collide however narrow the chart gets.
      space: 84,
      values: (_u, splits) =>
        splits.map((ts) =>
          trendSpansDays.value
            ? `${fmtDate(ts * 1000)} ${fmtTime(ts * 1000)}`
            : fmtTime(ts * 1000),
        ),
    },
    { stroke: AXIS_INK, font: AXIS_FONT, grid: { stroke: 'rgba(128,128,128,.24)' }, size: 44, values: (_u, vals) => vals.map((v) => `${v}%`) },
  ],
  scales: { x: { time: true }, y: { range: [0, 100] } },
}));

// ---- actions ----

async function load() {
  const id = Number(route.params.id);
  if (!Number.isFinite(id)) { loading.value = false; return; }
  try {
    const r = await s.show(id);
    server.value = r.server;
    history.value = r.history;
    void loadChecks();
    if (route.query.tab === 'logs') {
      tab.value = 'logs';
      void loadSources();
    } else if (route.query.tab === 'terminal') {
      tab.value = 'terminal';
    }
  } catch {
    server.value = null;
  } finally {
    loading.value = false;
  }
}

async function doRefresh() {
  if (!server.value) return;
  await s.refresh(server.value.id);
  success(t('servers.refresh_queued'));
  window.setTimeout(() => { void load(); }, 6000);
}

async function retest() {
  if (!server.value) return;
  testing.value = true;
  try {
    retestResult.value = await s.testStored(server.value.id);
  } finally { testing.value = false; }
}

/**
 * One removal path, not two. Which one is not a guess: the setup recorded
 * whether it created the account. Offering `userdel` for an account the operator
 * already had — for root, the account that owns the machine — would be the worst
 * possible default.
 */
const removalCommands = computed(() => {
  const srv = server.value;
  if (!srv) return '';
  const sudo = useSudo.value ? 'sudo ' : '';
  const blob = (srv.public_key ?? '').split(' ')[1] ?? '';
  const match = blob || 'll-facts';
  const lines: string[] = [];

  if (srv.account_created === true) {
    lines.push(
      `# ${t('servers.removal_case_dedicated')}`,
      `# ${t('servers.removal_pkill_note')}`,
      `${sudo}sh -c 'pkill -u "$1" 2>/dev/null; userdel -r "$1"' _ ${srv.username}`,
    );
  } else {
    lines.push(
      `# ${t('servers.removal_case_shared')}`,
      `${sudo}sh -c 'K=$(getent passwd "$1" | cut -d: -f6)/.ssh/authorized_keys; grep -v "$2" "$K" > "$K.tmp"; mv "$K.tmp" "$K"; chmod 600 "$K"' _ ${srv.username} '${match}'`,
    );
  }

  if (srv.restricted_key) lines.push(`${sudo}rm -f /usr/local/bin/ll-facts`);

  return lines.join('\n');
});

async function copyRemoval() {
  await navigator.clipboard.writeText(removalCommands.value);
  success(t('common.copied'));
}

onMounted(load);
void router;
</script>
