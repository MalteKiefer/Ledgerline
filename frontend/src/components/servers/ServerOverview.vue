<template>
  <div class="space-y-6">
    <!-- What needs attention, before anything that merely describes the host. -->
    <Findings :facts="facts" :checks="checks" @go="$emit('go', $event)" />

    <template v-if="facts">
      <!-- The five figures that answer "is anything wrong" without scrolling.
           Each carries a bar where a percentage means something: the number
           says what it is, the bar says whether that is a lot. -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <StatTile
          :label="t('servers.cpu')"
          icon="memory"
          :value="facts.cpu.used_pct === null ? '—' : `${facts.cpu.used_pct}%`"
          :pct="facts.cpu.used_pct"
          :warn-at="75"
          :danger-at="90"
          :note="facts.cpu.cores ? t('servers.cores', { n: String(facts.cpu.cores) }) : '—'"
        />
        <StatTile
          :label="t('servers.load')"
          icon="speed"
          :value="facts.load[0]?.toFixed(2) ?? '—'"
          :pct="loadPct"
          :warn-at="100"
          :danger-at="200"
          :note="loadNote"
        />
        <StatTile
          :label="t('servers.memory')"
          icon="developer_board"
          :value="facts.mem.used_pct === null ? '—' : `${facts.mem.used_pct}%`"
          :pct="facts.mem.used_pct"
          :warn-at="80"
          :danger-at="92"
          :note="memoryNote(facts)"
        />
        <StatTile
          :label="t('servers.disks')"
          icon="storage"
          :value="facts.disk_max_pct === null ? '—' : `${facts.disk_max_pct}%`"
          :pct="facts.disk_max_pct"
          :warn-at="80"
          :danger-at="90"
          :note="fullestDisk(facts)?.mount ?? '—'"
        />
        <StatTile
          :label="t('servers.uptime')"
          icon="schedule"
          :value="formatUptime(facts.uptime_s)"
          :note="facts.boot_at ? fmtDateTime(facts.boot_at) : ''"
        />
      </div>

      <!-- Identity and capacity: what the machine is, and how full it is. -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card :title="t('servers.section_system')" :body-class="'p-4'">
          <dl class="space-y-1.5 text-xs">
            <Row :label="t('servers.hostname')" :value="facts.hostname" />
            <Row :label="t('servers.os')" :value="facts.os.name" />
            <Row :label="t('servers.kernel')" :value="facts.kernel" />
            <Row :label="t('servers.arch')" :value="facts.arch" />
            <Row :label="t('servers.cpu')" :value="cpuText(facts)" />
            <Row v-if="facts.virt" :label="t('servers.virt')" :value="facts.virt" />
            <Row v-if="facts.temp_c != null" :label="t('servers.temperature')" :value="`${facts.temp_c} °C`" />
            <Row
              :label="t('servers.updates')"
              :value="facts.updates === null ? t('servers.updates_unknown') : String(facts.updates)"
            />
          </dl>
        </Card>

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

      <!-- Network. Routing and resolution first, because they are what you
           check when a host is up but reaching nothing; the interface list
           follows, since on a container host it is mostly bridges. -->
      <Card v-if="hasNetwork" :title="t('servers.section_network')" :body-class="'p-4'">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
          <div>
            <template v-if="facts.network?.gateway || facts.network?.dns?.length">
              <h3 class="mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.routing') }}</h3>
              <dl class="mb-4 space-y-1 text-xs">
                <Row v-if="facts.network.gateway" :label="t('servers.gateway')" :value="facts.network.gateway" mono />
                <Row v-if="facts.network.dns.length" :label="t('servers.dns')" :value="facts.network.dns.join(', ')" mono />
                <Row v-if="facts.network.search" :label="t('servers.dns_search')" :value="facts.network.search" mono />
              </dl>
            </template>

            <template v-if="facts.ports.length">
              <h3 class="mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.ports') }}</h3>
              <div class="flex flex-wrap gap-1.5">
                <Badge v-for="p in facts.ports" :key="p" tone="gray">{{ p }}</Badge>
              </div>
            </template>
          </div>

          <div v-if="facts.network?.interfaces?.length">
            <div class="mb-1.5 flex items-center justify-between">
              <h3 class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('servers.interfaces') }}</h3>
              <!-- A container host has a dozen bridges and one uplink. Showing
                   all of them by default buries the one that matters. -->
              <button
                v-if="facts.network.interfaces.length > primaryInterfaces.length"
                class="text-[0.7rem] text-[var(--ll-accent)] hover:underline"
                @click="allInterfaces = !allInterfaces"
              >
                {{ allInterfaces ? t('servers.if_show_primary') : t('servers.if_show_all', { n: String(facts.network.interfaces.length) }) }}
              </button>
            </div>

            <div class="space-y-2">
              <div v-for="n in shownInterfaces" :key="n.name" class="rounded-lg border border-[var(--ll-border)] p-2">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="n.up === false ? 'bg-[var(--ll-muted)]' : 'bg-emerald-500'" />
                  <span class="font-mono text-xs font-medium">{{ n.name }}</span>
                  <Badge v-if="n.kind" tone="gray">{{ t(`servers.if_${n.kind}`) }}</Badge>
                  <span class="ml-auto font-mono text-[0.7rem] tabular-nums text-[var(--ll-muted)]">
                    ↓ {{ formatGib(n.rx_bytes / 1024) }} · ↑ {{ formatGib(n.tx_bytes / 1024) }}
                  </span>
                </div>
                <div v-if="n.addresses?.length" class="mt-1 font-mono text-[0.7rem]">{{ n.addresses.join(', ') }}</div>
                <div v-if="n.gateway || n.dns?.length || n.mtu" class="mt-0.5 flex flex-wrap gap-3 text-[0.7rem] text-[var(--ll-muted)]">
                  <span v-if="n.gateway">{{ t('servers.gateway') }}: <span class="font-mono">{{ n.gateway }}</span></span>
                  <span v-if="n.dns?.length">DNS: <span class="font-mono">{{ n.dns.join(', ') }}</span></span>
                  <span v-if="n.mtu">MTU {{ n.mtu }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- What is running: containers, the heaviest processes, who is logged in. -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card v-if="facts.containers.length" :title="t('servers.containers')" :body-class="'p-4'">
          <div class="space-y-1">
            <div v-for="c in facts.containers" :key="c.name" class="flex items-center gap-2 text-xs">
              <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="c.status.startsWith('Up') ? 'bg-emerald-500' : 'bg-amber-500'" />
              <span class="truncate font-mono">{{ c.name }}</span>
              <span class="ml-auto shrink-0 text-[0.7rem] text-[var(--ll-muted)]">{{ c.status }}</span>
            </div>
          </div>
        </Card>

        <Card v-if="facts.processes.length" :title="t('servers.section_processes')" :body-class="'p-4'">
          <div class="space-y-1 text-xs">
            <div v-for="proc in facts.processes" :key="proc.name" class="flex justify-between gap-3">
              <span class="truncate font-mono">{{ proc.name }}</span>
              <span class="shrink-0 tabular-nums text-[var(--ll-muted)]">{{ formatGib(proc.rss_kb) }}</span>
            </div>
          </div>
          <p class="mt-2 text-[0.65rem] text-[var(--ll-muted)]">{{ t('servers.processes_note') }}</p>
        </Card>

        <Card v-if="facts.sessions.length" :title="t('servers.section_sessions')" :body-class="'p-4'">
          <div class="space-y-1.5">
            <div v-for="(ses, i) in facts.sessions" :key="i" class="flex items-center gap-2 text-xs">
              <span class="font-mono font-semibold">{{ ses.user }}</span>
              <span class="font-mono text-[var(--ll-muted)]">{{ ses.tty }}</span>
              <span v-if="ses.from" class="truncate font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ ses.from }}</span>
              <!-- Only where there is a terminal to signal: who also lists rows
                   whose "tty" is a service name, and a button that errors on
                   click is worse than no button. -->
              <Btn
                v-if="killable(ses.tty)"
                variant="ghost"
                size="sm"
                icon="logout"
                class="ml-auto"
                :title="t('servers.session_kill')"
                @click="$emit('kill-session', ses)"
              />
            </div>
          </div>
        </Card>
      </div>
    </template>

    <p v-if="note" class="whitespace-pre-line rounded-lg border border-[var(--ll-border)] p-3 text-sm">{{ note }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, h, type PropType, type VNode } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card } from '@spa/ui';
import { fmtDateTime } from '@spa/lib/datetime';
import {
  cpuText, diskNote, formatGib, formatUptime, fullestDisk, memoryNote, swapNote, swapPct,
} from '@spa/lib/server-facts';
import Findings from './Findings.vue';
import StatTile from './StatTile.vue';
import type { ServerFacts, ServerCheckSeries } from '@spa/stores/servers';

const props = defineProps({
  facts: { type: Object as PropType<ServerFacts | null>, default: null },
  checks: { type: Array as PropType<ServerCheckSeries[]>, default: () => [] },
  note: { type: String, default: '' },
});

defineEmits<{ go: [tab: string]; 'kill-session': [ses: { user: string; tty: string }] }>();

const allInterfaces = ref(false);

const hasNetwork = computed(() =>
  !!props.facts && (props.facts.addresses.length > 0 || props.facts.ports.length > 0 || !!props.facts.network?.interfaces?.length));

/**
 * Load as a percentage of the cores, which is what makes it comparable.
 * A load of 4 is idle on a 16-core box and desperate on a single-core one.
 */
const loadPct = computed(() => {
  const f = props.facts;
  if (!f || f.load[0] === undefined || !f.cpu.cores) return null;

  return Math.round((f.load[0] / f.cpu.cores) * 100);
});

const loadNote = computed(() => {
  const f = props.facts;
  if (!f) return '';

  return f.load.map((l) => l.toFixed(2)).join(' · ');
});

/** The uplink and anything carrying an address; bridges and veth pairs hide. */
const primaryInterfaces = computed(() => {
  const all = props.facts?.network?.interfaces ?? [];

  return all.filter((n) => n.gateway || (n.addresses?.length && n.kind !== 'veth' && n.kind !== 'bridge'));
});

const shownInterfaces = computed(() => {
  const all = props.facts?.network?.interfaces ?? [];
  if (allInterfaces.value || primaryInterfaces.value.length === 0) return all;

  return primaryInterfaces.value;
});

/** Only a real terminal can be signalled; `who` also lists service names. */
function killable(tty: string): boolean {
  return /^(pts\/\d{1,4}|tty\d{1,3})$/.test(tty);
}

/** A label/value line, in one place rather than repeated per row. */
const Row = (props: { label: string; value?: string | null; mono?: boolean }): VNode =>
  h('div', { class: 'flex justify-between gap-3' }, [
    h('dt', { class: 'shrink-0 text-[var(--ll-muted)]' }, props.label),
    h('dd', { class: `truncate text-right ${props.mono ? 'font-mono' : ''}`, title: props.value ?? '' }, props.value || '—'),
  ]);

/** A labelled bar. Same shape as the tiles, for a list rather than a grid. */
const Meter = (props: { label: string; pct: number | null; note?: string }): VNode => {
  const pct = props.pct ?? 0;
  const tone = pct >= 90 ? 'bg-red-500' : pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500';

  return h('div', {}, [
    h('div', { class: 'mb-1 flex items-baseline justify-between gap-3 text-xs' }, [
      h('span', { class: 'truncate font-medium', title: props.label }, props.label),
      h('span', { class: 'shrink-0 font-mono tabular-nums text-[var(--ll-muted)]' }, props.pct === null ? '—' : `${props.pct}%`),
    ]),
    h('div', { class: 'h-1.5 overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10' }, [
      h('div', { class: `h-full rounded-full ${tone}`, style: { width: `${Math.min(100, Math.max(0, pct))}%` } }),
    ]),
    props.note ? h('p', { class: 'mt-0.5 text-[0.65rem] text-[var(--ll-muted)]' }, props.note) : null,
  ]);
};
</script>
