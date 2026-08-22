<template>
  <div class="space-y-6">
    <Card :body-class="'p-4'">
      <div class="flex flex-wrap items-end gap-3">
        <Select v-model="logSource" class="w-44" :label="t('servers.log_source')" :options="sourceOptions" />

        <!-- Every option below came from the host itself. The browser picks
             from that answer rather than naming something of its own. -->
        <Select
          v-if="logSource === 'journal' && sources?.units.length"
          v-model="logUnit"
          class="w-60"
          :label="t('servers.log_unit')"
          :options="[{ title: t('servers.log_all_units'), value: '' }, ...sources.units.map((u) => ({ title: u, value: u }))]"
        />

        <Select
          v-if="logSource === 'docker'"
          v-model="logContainer"
          class="w-60"
          :label="t('servers.containers')"
          :options="(sources?.containers ?? []).map((c) => ({ title: c, value: c }))"
        />

        <Select
          v-if="logSource === 'file'"
          v-model="logPath"
          class="w-72"
          :label="t('servers.log_file')"
          :options="(sources?.files ?? []).map((f) => ({ title: f, value: f }))"
        />

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

      <!-- Filtering happens here, not on the host: the lines are already
           fetched, and a second round trip to grep them would be slower and
           would lose the surrounding context on the next search. -->
      <div v-if="logText" class="mt-3 flex flex-wrap items-center gap-2">
        <input
          v-model="logQuery"
          :placeholder="t('servers.search_placeholder')"
          class="w-72 rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm"
        >
        <label class="flex items-center gap-2 text-xs"><input v-model="logInvert" type="checkbox" class="accent-primary-500">{{ t('servers.log_invert') }}</label>
        <label class="flex items-center gap-2 text-xs"><input v-model="logWrap" type="checkbox" class="accent-primary-500">{{ t('servers.log_wrap') }}</label>
        <span class="text-xs text-[var(--ll-muted)]">{{ t('servers.log_match_count', { shown: String(logShown), total: String(logTotal) }) }}</span>
      </div>

      <pre
        v-if="logText"
        class="mt-2 max-h-[32rem] overflow-auto rounded-lg bg-black/[0.05] p-3 font-mono text-[0.7rem] leading-relaxed dark:bg-white/5"
        :class="logWrap ? 'whitespace-pre-wrap break-all' : ''"
      >{{ filteredLog }}</pre>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, Card, Select } from '@spa/ui';
import { ApiError } from '@spa/api/client';
import { useServersStore } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';

const props = defineProps<{ serverId: number }>();

const s = useServersStore();
const { success, error } = useToast();

/** Translate a code from the host into something a reader can act on. */
function errorText(code: string | null): string {
  if (!code) return '';
  const key = `servers.err_${code}`;
  const text = t(key);

  return text === key ? code : text;
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

const sourceOptions = computed(() => {
  const src = sources.value;
  const out: { title: string; value: string }[] = [];
  if (src?.journal) out.push({ title: t('servers.log_journal'), value: 'journal' });
  if (src?.containers.length) out.push({ title: t('servers.log_docker'), value: 'docker' });
  if (src?.files.length) out.push({ title: t('servers.log_file'), value: 'file' });

  return out;
});

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
  const id = props.serverId;
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
  const id = props.serverId;
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

// ---- log filtering ----

const logQuery = ref('');
const logInvert = ref(false);
const logWrap = ref(false);

const logLinesArr = computed(() => (logText.value ? logText.value.split('\n') : []));

const filteredLogLines = computed(() => {
  const q = logQuery.value.trim().toLowerCase();
  if (!q) return logLinesArr.value;

  return logLinesArr.value.filter((l) => (l.toLowerCase().includes(q) ? !logInvert.value : logInvert.value));
});

const filteredLog = computed(() => filteredLogLines.value.join('\n'));
const logShown = computed(() => filteredLogLines.value.length);
const logTotal = computed(() => logLinesArr.value.length);

onMounted(loadSources);
</script>
