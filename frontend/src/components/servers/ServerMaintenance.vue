<template>
  <div class="space-y-6">
    <!-- Updates. The count alone decides nothing: fourteen pending might be a
         kernel and thirteen fonts, or thirteen fonts and one remote hole. -->
    <Card :body-class="'p-4'">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-sm font-semibold">{{ t('servers.updates') }}</h2>
          <p v-if="updates" class="text-[0.7rem] text-[var(--ll-muted)]">
            {{ updates.kind === 'none' ? t('servers.updates_no_manager') : t('servers.updates_count', { n: String(updates.packages.length), s: String(securityCount) }) }}
          </p>
        </div>
        <div class="flex gap-2">
          <Btn variant="ghost" size="sm" icon="refresh" :disabled="busy" @click="loadUpdates">{{ t('servers.refresh') }}</Btn>
          <Btn
            v-if="updates?.packages.length"
            variant="solid"
            size="sm"
            icon="system_update"
            :disabled="applying"
            @click="apply"
          >{{ t('servers.updates_apply') }}</Btn>
        </div>
      </div>

      <p v-if="busy && !updates" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
      <p v-else-if="updates && !updates.ok" class="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">
        {{ t('servers.status_fail') }}
      </p>
      <p v-else-if="updates && !updates.packages.length && updates.kind !== 'none'" class="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400">
        <Icon name="check_circle" :size="16" />
        {{ t('servers.updates_none') }}
      </p>

      <div v-else-if="updates?.packages.length" class="max-h-96 overflow-y-auto">
        <div
          v-for="p in updates.packages"
          :key="p.name"
          class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] py-1.5 last:border-0"
        >
          <Badge v-if="p.security" tone="error">{{ t('servers.updates_security') }}</Badge>
          <span class="font-mono text-xs font-medium">{{ p.name }}</span>
          <span class="font-mono text-[0.7rem] text-[var(--ll-muted)]">
            <template v-if="p.current">{{ p.current }} → </template>{{ p.version }}
          </span>
        </div>
      </div>
    </Card>

    <!-- Scheduled work. Failed *services* are already reported; a failed
         timer is invisible, and that is where backups, certificate renewal and
         cleanup jobs live — the things nobody notices are broken until the
         moment they are needed. -->
    <Card v-if="facts?.timers?.units.length || facts?.backup_tools?.length" :body-class="'p-4'">
      <h2 class="mb-1 text-sm font-semibold">{{ t('servers.scheduled') }}</h2>

      <p v-if="facts?.timers?.failed.length" class="mb-2 flex items-center gap-2 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">
        <Icon name="error" :size="16" />
        {{ t('servers.timers_failed', { list: facts.timers.failed.join(', ') }) }}
      </p>

      <!-- Which backup tool is installed, not whether it ran: the probe can see
           the binary, and claiming more than that would be a guess about
           somebody's data. The timer list below is where the actual runs show. -->
      <p v-if="facts?.backup_tools?.length" class="mb-2 text-xs">
        <span class="text-[var(--ll-muted)]">{{ t('servers.backup_tools') }}:</span>
        <span class="ml-1 font-mono">{{ facts.backup_tools.join(', ') }}</span>
      </p>
      <p v-else-if="facts?.timers?.units.length" class="mb-2 text-xs text-[var(--ll-muted)]">{{ t('servers.backup_none') }}</p>

      <div v-if="facts?.timers?.units.length" class="max-h-72 overflow-y-auto">
        <div v-for="tm in facts.timers.units" :key="tm.unit" class="border-b border-[var(--ll-border)] py-1.5 last:border-0">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-xs" :class="facts.timers.failed.includes(tm.unit) ? 'font-semibold text-red-600 dark:text-red-400' : ''">{{ tm.unit }}</span>
            <span class="text-[0.7rem] text-[var(--ll-muted)]">{{ tm.activates }}</span>
          </div>
          <div class="flex flex-wrap gap-3 text-[0.7rem] text-[var(--ll-muted)]">
            <span>{{ t('servers.timer_last') }}: {{ tm.last || '—' }}</span>
            <!-- An empty next elapse is a real state (the timer will not run
                 again), not a missing value. -->
            <span>{{ t('servers.timer_next') }}: {{ tm.next || t('servers.timer_never') }}</span>
          </div>
        </div>
      </div>
    </Card>

    <!-- Who has been on the machine. fail2ban shows who got banned; somebody
         trying a hundred times without being banned is invisible without this. -->
    <Card v-if="facts?.logins?.length || facts?.failed_logins !== undefined" :body-class="'p-4'">
      <h2 class="mb-1 text-sm font-semibold">{{ t('servers.logins') }}</h2>
      <p class="mb-2 text-[0.7rem] text-[var(--ll-muted)]">
        <template v-if="facts?.failed_logins === null">{{ t('servers.failed_logins_unknown') }}</template>
        <template v-else-if="facts?.failed_logins !== undefined">{{ t('servers.failed_logins', { n: String(facts.failed_logins) }) }}</template>
      </p>
      <div v-for="(l, i) in facts?.logins ?? []" :key="`${l.user}-${l.when}-${i}`" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
        <span class="font-mono font-medium">{{ l.user }}</span>
        <span class="font-mono text-[var(--ll-muted)]">{{ l.from || t('servers.login_local') }}</span>
        <span class="ml-auto text-[0.7rem] text-[var(--ll-muted)]">{{ l.when }}</span>
      </div>
    </Card>

    <!-- Space. "94% full" without "which directory" sends somebody to ssh. -->
    <Card :body-class="'p-4'">
      <div class="mb-3 flex flex-wrap items-end gap-2">
        <div class="flex-1">
          <h2 class="text-sm font-semibold">{{ t('servers.disk_usage') }}</h2>
          <p class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.disk_usage_hint') }}</p>
        </div>
        <label class="w-64">
          <span class="mb-1 block text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.path') }}</span>
          <input
            v-model="path"
            class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 font-mono text-sm"
            @keyup.enter="scan()"
          >
        </label>
        <Btn variant="soft" size="sm" icon="search" :disabled="scanning" @click="scan()">
          {{ scanning ? t('common.loading') : t('servers.disk_usage_scan') }}
        </Btn>
      </div>

      <!-- The mounts the host actually has, so the common case is one click
           rather than typing a path from memory. -->
      <div v-if="mounts.length" class="mb-3 flex flex-wrap gap-1.5">
        <button
          v-for="m in mounts"
          :key="m"
          class="rounded-full border border-[var(--ll-border)] px-2.5 py-0.5 font-mono text-[0.7rem] transition-colors hover:bg-black/[0.04] dark:hover:bg-white/[0.06]"
          :class="path === m ? 'border-[var(--ll-accent)] text-[var(--ll-accent)]' : ''"
          @click="scan(m)"
        >{{ m }}</button>
      </div>

      <p v-if="usage && !usage.ok" class="rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">
        {{ usage.error === 'invalid_path' ? t('servers.files_err_invalid_path') : t('servers.status_fail') }}
      </p>

      <div v-else-if="usage?.entries.length">
        <div v-for="e in usage.entries" :key="e.path" class="border-b border-[var(--ll-border)] py-1.5 last:border-0">
          <div class="flex items-center justify-between gap-3">
            <button class="truncate text-left font-mono text-xs hover:text-[var(--ll-accent)]" :title="e.path" @click="scan(e.path)">
              {{ e.path }}
            </button>
            <span class="shrink-0 font-mono text-xs tabular-nums">{{ formatGib(e.size_kb) }}</span>
          </div>
          <div class="mt-1 h-1 overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
            <div class="h-full rounded-full bg-[var(--ll-accent)]" :style="{ width: `${share(e.size_kb)}%` }" />
          </div>
        </div>
      </div>
      <p v-else-if="usage" class="text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</p>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card, Icon } from '@spa/ui';
import { useServersStore, type DiskUsage, type PendingUpdates, type ServerFacts } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { formatGib } from '@spa/lib/server-facts';

const props = defineProps<{ serverId: number; facts: ServerFacts | null }>();

const store = useServersStore();
const { success, error: fail } = useToast();

const updates = ref<PendingUpdates | null>(null);
const usage = ref<DiskUsage | null>(null);
const busy = ref(false);
const scanning = ref(false);
const applying = ref(false);
const path = ref('/');

const securityCount = computed(() => (updates.value?.packages ?? []).filter((p) => p.security).length);

/** The filesystems the host actually has — no point offering paths it lacks. */
const mounts = computed(() => (props.facts?.disks ?? []).map((d) => d.mount));

/** Bar relative to the largest entry, which is what makes the outlier obvious. */
const share = (kb: number) => {
  const top = usage.value?.entries[0]?.size_kb ?? 0;

  return top > 0 ? Math.round((kb / top) * 100) : 0;
};

const loadUpdates = async () => {
  busy.value = true;
  try {
    updates.value = await store.updates(props.serverId);
  } catch {
    updates.value = { ok: false, kind: 'unknown', packages: [], error: 'unreachable' };
  } finally {
    busy.value = false;
  }
};

const scan = async (target?: string) => {
  if (target) path.value = target;
  scanning.value = true;
  try {
    usage.value = await store.diskUsage(props.serverId, path.value);
  } catch {
    usage.value = { ok: false, path: path.value, entries: [], error: 'unreachable' };
  } finally {
    scanning.value = false;
  }
};

const apply = async () => {
  const n = securityCount.value;
  if (!(await confirmAsk(t(n > 0 ? 'servers.updates_confirm_security' : 'servers.updates_confirm', { n: String(updates.value?.packages.length ?? 0), s: String(n) })))) return;

  applying.value = true;
  try {
    await store.applyUpdates(props.serverId);
    // Queued, not done: the result arrives as a notification minutes later, and
    // saying "updated" here would be a lie for most of that time.
    success(t('servers.updates_queued'));
  } catch {
    fail(t('servers.action_failed'));
  } finally {
    applying.value = false;
  }
};

onMounted(() => {
  void loadUpdates();
  // The fullest filesystem is the one somebody came here about.
  const fullest = [...(props.facts?.disks ?? [])].sort((a, b) => b.used_pct - a.used_pct)[0];
  if (fullest) path.value = fullest.mount;
});
</script>
