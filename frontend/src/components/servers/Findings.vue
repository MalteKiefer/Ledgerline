<template>
  <!-- What needs attention, first, before anything else on the page.
       The old overview was one long scroll of equally-weighted cards: a pending
       reboot and the kernel version looked the same, so neither stood out. -->
  <Card v-if="items.length" :body-class="'p-0'">
    <div class="divide-y divide-[var(--ll-border)]">
      <button
        v-for="f in items"
        :key="f.key"
        class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-black/[0.02] dark:hover:bg-white/[0.03]"
        @click="f.tab && $emit('go', f.tab)"
      >
        <Icon :name="f.icon" :size="18" :class="f.level === 'danger' ? 'text-red-500' : 'text-amber-500'" />
        <div class="min-w-0 flex-1">
          <div class="text-sm font-medium">{{ f.title }}</div>
          <div v-if="f.detail" class="truncate text-[0.7rem] text-[var(--ll-muted)]">{{ f.detail }}</div>
        </div>
        <Icon v-if="f.tab" name="chevron_right" :size="16" class="shrink-0 text-[var(--ll-muted)]" />
      </button>
    </div>
  </Card>

  <!-- Silence is a result too, and worth saying once rather than leaving the
       reader to infer it from an absence. -->
  <div v-else class="flex items-center gap-2 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400">
    <Icon name="check_circle" :size="18" />
    {{ t('servers.findings_none') }}
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Icon } from '@spa/ui';
import type { ServerFacts, ServerCheckSeries } from '@spa/stores/servers';

const props = defineProps<{
  facts: ServerFacts | null;
  checks: ServerCheckSeries[];
}>();

defineEmits<{ go: [tab: string] }>();

interface Finding {
  key: string;
  level: 'danger' | 'warn';
  icon: string;
  title: string;
  detail?: string;
  tab?: string;
}

const items = computed<Finding[]>(() => {
  const f = props.facts;
  const out: Finding[] = [];
  if (!f) return out;

  // Ordered by what would ruin the day soonest, not by where the data happens
  // to live in the payload.
  const down = props.checks.filter((c) => c.last && !c.last.ok);
  if (down.length) {
    out.push({
      key: 'unreachable',
      level: 'danger',
      icon: 'wifi_off',
      title: t('servers.finding_unreachable', { n: String(down.length) }),
      detail: down.map((c) => (c.port ? `${c.kind} ${c.port}` : c.kind)).join(', '),
    });
  }

  const full = f.disks.filter((d) => d.used_pct >= 90);
  if (full.length) {
    out.push({
      key: 'disk',
      level: full.some((d) => d.used_pct >= 95) ? 'danger' : 'warn',
      icon: 'storage',
      title: t('servers.finding_disk', { n: String(full.length) }),
      detail: full.map((d) => `${d.mount} ${d.used_pct}%`).join(', '),
    });
  }

  if (f.failed_units.length) {
    out.push({
      key: 'units',
      level: 'danger',
      icon: 'error',
      title: t('servers.finding_failed_units', { n: String(f.failed_units.length) }),
      detail: f.failed_units.slice(0, 4).join(', '),
      tab: 'services',
    });
  }

  if (f.reboot_required) {
    out.push({
      key: 'reboot',
      level: 'warn',
      icon: 'restart_alt',
      title: t('servers.reboot_required_badge'),
      detail: t('servers.finding_reboot_detail'),
    });
  }

  // A number we do not have is not zero: an unknown update count says nothing
  // and must not be reported as "up to date".
  if (f.updates !== null && f.updates > 0) {
    out.push({
      key: 'updates',
      level: f.updates > 50 ? 'warn' : 'warn',
      icon: 'system_update',
      title: t('servers.finding_updates', { n: String(f.updates) }),
      tab: 'security',
    });
  }

  if (f.mem.used_pct !== null && f.mem.used_pct >= 90) {
    out.push({
      key: 'memory',
      level: 'warn',
      icon: 'memory',
      title: t('servers.finding_memory', { pct: String(f.mem.used_pct) }),
      tab: 'processes',
    });
  }

  return out;
});
</script>
