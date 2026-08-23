<template>
  <div class="space-y-4">
    <Card :body-class="'p-4'">
      <div class="mb-3 flex items-center justify-between gap-2">
        <SectionHead icon="backup" :label="t('servers.backup_title')" />
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="busy" @click="load">{{ t('servers.refresh') }}</Btn>
      </div>

      <p v-if="busy && !data" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

      <template v-else-if="data">
        <!-- Nothing scheduled is the finding, not an empty screen. A machine
             with backup tools installed and no schedule is not backed up. -->
        <p v-if="!data.schedules.length && !data.agents.length" class="rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">
          {{ t('servers.backup_none') }}
        </p>

        <!-- Schedules first: intent plus evidence. Everything else on this tab
             is inventory. -->
        <div v-if="data.schedules.length">
          <SectionHead icon="schedule" :label="t('servers.backup_schedules')" level="h3" class="mb-1.5" />
          <div v-for="(job, i) in data.schedules" :key="i" class="border-b border-[var(--ll-border)] py-1.5 text-xs last:border-0">
            <div class="flex flex-wrap items-center gap-2">
              <Icon :name="job.kind === 'timer' ? 'timer' : 'terminal'" :size="14" class="shrink-0 text-[var(--ll-muted)]" />
              <span class="font-medium">{{ job.name }}</span>
              <span class="ll-mono text-[var(--ll-muted)]">{{ job.schedule }}</span>
              <span v-if="job.last_run" class="ml-auto shrink-0" :class="ageTone(job.last_run)">{{ t('servers.backup_last_run', { when: ago(job.last_run) }) }}</span>
            </div>
            <div class="mt-0.5 truncate font-mono text-[0.65rem] text-[var(--ll-muted)]" :title="job.runs">{{ job.runs }}</div>
            <div v-if="job.log" class="mt-0.5 text-[0.65rem] text-[var(--ll-muted)]">
              {{ job.log }}<span v-if="job.log_size !== null"> · {{ fmtSize(job.log_size) }}</span>
            </div>
          </div>
          <p class="mt-1 text-[0.65rem] text-[var(--ll-muted)]">{{ t('servers.backup_evidence_hint') }}</p>
        </div>

        <div v-if="data.agents.length" class="mt-3">
          <SectionHead icon="dns" :label="t('servers.backup_agents')" level="h3" class="mb-1.5" />
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="a in data.agents" :key="a.unit"
              class="rounded-full px-2 py-0.5 text-[0.7rem]"
              :class="a.active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-black/[0.04] text-[var(--ll-muted)] dark:bg-white/[0.06]'"
              :title="a.state"
            >{{ a.unit }}</span>
          </div>
        </div>

        <div v-if="data.activities.length" class="mt-3">
          <SectionHead icon="history" :label="t('servers.backup_runs')" level="h3" class="mb-1.5" />
          <div v-for="(a, i) in data.activities" :key="i" class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] py-1 text-xs last:border-0">
            <!-- A run still going has no outcome yet, which is not a failure. -->
            <Icon
              :name="a.result === 'Succeeded' ? 'check_circle' : (a.result ? 'error' : 'schedule')" :size="14"
              :class="a.result === 'Succeeded' ? 'text-emerald-600 dark:text-emerald-400' : (a.result ? 'text-red-600 dark:text-red-400' : 'text-[var(--ll-muted)]')"
            />
            <span class="truncate">{{ a.name }}</span>
            <span class="text-[var(--ll-muted)]">{{ a.state }}</span>
            <span class="ml-auto shrink-0 text-[var(--ll-muted)]">{{ a.started }} · {{ a.elapsed }}</span>
          </div>
        </div>

        <div v-if="data.repositories.length" class="mt-3">
          <SectionHead icon="folder_open" :label="t('servers.backup_targets')" level="h3" class="mb-1.5" />
          <div v-for="r in data.repositories" :key="r" class="truncate border-b border-[var(--ll-border)] py-1 font-mono text-xs last:border-0">{{ r }}</div>
        </div>

        <div v-if="data.tools.length" class="mt-3">
          <SectionHead icon="handyman" :label="t('servers.backup_tools')" level="h3" class="mb-1.5" />
          <div class="flex flex-wrap gap-1.5">
            <span v-for="tool in data.tools" :key="tool.name" class="rounded-full bg-black/[0.04] px-2 py-0.5 text-[0.7rem] dark:bg-white/[0.06]" :title="tool.version ?? ''">
              {{ tool.name }}<span v-if="shortVersion(tool.version)" class="text-[var(--ll-muted)]"> {{ shortVersion(tool.version) }}</span>
            </span>
          </div>
          <!-- Said plainly, because the chips look like reassurance and are not. -->
          <p class="mt-1 text-[0.65rem] text-[var(--ll-muted)]">{{ t('servers.backup_tools_hint') }}</p>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card } from '@spa/ui';
import SectionHead from '@spa/components/servers/SectionHead.vue';
import { useServersStore, type BackupStatus } from '@spa/stores/servers';

const props = defineProps<{ serverId: number }>();

const store = useServersStore();

const data = ref<BackupStatus | null>(null);
const busy = ref(false);
const error = ref('');

async function load(): Promise<void> {
  busy.value = true;
  error.value = '';
  try {
    const res = await store.backup(props.serverId);
    if (!res.ok) {
      error.value = t('servers.status_fail');

      return;
    }
    data.value = res;
  } catch {
    error.value = t('servers.status_fail');
  } finally {
    busy.value = false;
  }
}

/** How long ago, in the coarsest unit that still says something. */
function ago(unix: number): string {
  const mins = Math.round((Date.now() / 1000 - unix) / 60);
  if (mins < 60) return `${mins} min`;
  if (mins < 1440) return `${Math.round(mins / 60)} h`;

  return `${Math.round(mins / 1440)} d`;
}

/**
 * A job whose log has not been touched in days is the thing worth noticing on
 * this tab, so the age carries the colour rather than the name.
 */
function ageTone(unix: number): string {
  const hours = (Date.now() / 1000 - unix) / 3600;
  if (hours > 168) return 'text-red-600 dark:text-red-400';
  if (hours > 36) return 'text-amber-600 dark:text-amber-400';

  return 'text-[var(--ll-muted)]';
}

/** `borg 1.2.4` rather than the whole banner some tools print. */
function shortVersion(version: string | null): string {
  if (!version) return '';
  const m = version.match(/\d+\.\d+(\.\d+)?/);

  return m ? m[0] : '';
}

function fmtSize(bytes: number): string {
  if (bytes >= 1_048_576) return `${(bytes / 1_048_576).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} kB`;

  return `${bytes} B`;
}

onMounted(load);
</script>
