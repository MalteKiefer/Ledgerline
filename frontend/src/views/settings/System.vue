<template>
  <div>
    <!-- Scheduled tasks -->
    <Card class="mb-4" :body-class="'p-0'">
      <template #header>
        <Icon name="refresh" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.system_cron_heading') }}</h2>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
            <tr class="border-b border-[var(--ll-border)]">
              <th class="px-4 py-2.5 font-medium">{{ t('settings.system_task') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.system_schedule') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.system_last_run') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('common.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="task in tasks" :key="task.name" class="border-b border-[var(--ll-border)] last:border-0">
              <td class="px-4 py-2.5">{{ task.name }}</td>
              <td class="px-4 py-2.5"><code class="text-xs text-[var(--ll-muted)]">{{ task.expression }}</code></td>
              <td class="px-4 py-2.5 text-xs text-[var(--ll-muted)]">{{ task.lastAt ? fmtDate(task.lastAt) : t('settings.system_never') }}</td>
              <td class="px-4 py-2.5">
                <Icon
                  :name="task.lastOk ? 'check' : 'close'"
                  :size="18"
                  :class="task.lastOk ? 'text-emerald-500' : (task.lastAt ? 'text-red-500' : 'text-[var(--ll-muted)]')"
                />
              </td>
            </tr>
            <tr v-if="!tasks.length">
              <td colspan="4" class="px-4 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <!-- Status snapshot -->
    <Card class="mb-4">
      <template #header>
        <Icon name="storage" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.system_status_heading') }}</h2>
      </template>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
        <div v-for="tile in statusTiles" :key="tile.label" class="rounded-lg bg-black/[0.03] p-3 dark:bg-white/5">
          <div class="text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ tile.label }}</div>
          <div class="mt-0.5 text-lg font-semibold">{{ tile.value }}</div>
        </div>
        <div v-if="!statusTiles.length" class="col-span-full">
          <span class="text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</span>
        </div>
      </div>

      <div class="mt-4 text-xs text-[var(--ll-muted)]">
        <template v-if="trend && trend.points.length">
          {{ t('settings.system_trend') }}: {{ fmtBytes(trend.deltaBytes) }} {{ t('settings.system_trend_days', { n: String(trend.deltaDays) }) }}
        </template>
        <template v-else>
          {{ t('settings.system_trend_collecting') }}
        </template>
      </div>
    </Card>

    <!-- Open errors -->
    <Card class="mb-4" :body-class="'p-0'">
      <template #header>
        <h2 class="text-sm font-semibold">{{ t('settings.system_errors_heading') }}</h2>
      </template>
      <div v-if="unresolvedErrors.length" class="divide-y divide-[var(--ll-border)]">
        <div v-for="err in unresolvedErrors" :key="err.id" class="flex items-center gap-3 px-5 py-3">
          <div class="min-w-0 flex-1">
            <div class="text-sm">
              <Badge v-if="err.level" tone="error" class="mr-2">{{ err.level }}</Badge>
              {{ err.message || err.exception }}
            </div>
            <div class="mt-0.5 text-xs text-[var(--ll-muted)]">
              <span v-if="err.file">{{ err.file }}<span v-if="err.line">:{{ err.line }}</span> · </span>
              <span v-if="err.count">{{ t('settings.system_error_count', { n: String(err.count) }) }} · </span>
              <span v-if="err.last_seen_at">{{ fmtDate(err.last_seen_at) }}</span>
            </div>
          </div>
          <Btn variant="soft" size="sm" icon="check" :loading="resolving === err.id" @click="resolve(err)">
            {{ t('settings.system_error_resolve') }}
          </Btn>
        </div>
      </div>
      <div v-else class="px-5 py-6 text-sm text-[var(--ll-muted)]">{{ t('settings.system_no_errors') }}</div>
    </Card>

    <!-- Recent audit -->
    <Card :body-class="'p-0'">
      <template #header>
        <Icon name="description" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.system_audit_heading') }}</h2>
      </template>
      <div v-if="audit.length" class="divide-y divide-[var(--ll-border)]">
        <div v-for="row in audit" :key="row.id" class="px-5 py-3">
          <div class="text-sm">{{ actionLabel(row.action) }}</div>
          <div class="mt-0.5 text-xs text-[var(--ll-muted)]">
            <span v-if="row.actor">{{ row.actor }} · </span>
            <span v-if="row.ip">{{ row.ip }} · </span>
            <span>{{ fmtDate(row.created_at) }}</span>
          </div>
        </div>
      </div>
      <div v-else class="px-5 py-6 text-sm text-[var(--ll-muted)]">{{ t('settings.system_no_audit') }}</div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, Badge } from '@spa/ui';

interface Task {
  name: string;
  expression: string;
  lastAt: string | null;
  lastOk: boolean | null;
}

interface Trend {
  points: unknown[];
  deltaBytes: number;
  deltaDays: number;
}

interface ErrorRow {
  id: number | string;
  level: string | null;
  exception: string | null;
  message: string | null;
  file: string | null;
  line: number | null;
  count: number | null;
  first_seen_at: string | null;
  last_seen_at: string | null;
  resolved_at: string | null;
}

interface AuditRow {
  id: number | string;
  action: string;
  actor: string | null;
  ip: string | null;
  meta: unknown;
  created_at: string;
}

interface SystemResponse {
  tasks: Task[];
  status: Record<string, unknown>;
  trend: Trend | null;
  errors: ErrorRow[];
  audit: AuditRow[];
}

const { error } = useToast();

const tasks = ref<Task[]>([]);
const status = ref<Record<string, unknown>>({});
const trend = ref<Trend | null>(null);
const errors = ref<ErrorRow[]>([]);
const audit = ref<AuditRow[]>([]);
const resolving = ref<number | string | null>(null);

const unresolvedErrors = computed(() => errors.value.filter((e) => !e.resolved_at));

// Curated status keys we have labels + formatting for. `bytes` keys are humanised.
const STATUS_TILES: { key: string; labelKey: string; bytes?: boolean }[] = [
  { key: 'queue_pending', labelKey: 'settings.system_queue_pending' },
  { key: 'queue_failed', labelKey: 'settings.system_queue_failed' },
  { key: 'errors_unresolved', labelKey: 'settings.system_errors_unresolved' },
  { key: 'disk_free', labelKey: 'settings.system_disk_free', bytes: true },
  { key: 'storage', labelKey: 'settings.system_storage', bytes: true },
];

function isScalar(v: unknown): v is string | number | boolean {
  return typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean';
}

const statusTiles = computed<{ label: string; value: string }[]>(() => {
  const s = status.value;
  const seen = new Set<string>();
  const tiles: { label: string; value: string }[] = [];

  for (const def of STATUS_TILES) {
    const raw = s[def.key];
    if (raw == null) continue;
    seen.add(def.key);
    let value: string;
    if (def.bytes && typeof raw === 'number') value = fmtBytes(raw);
    else if (isScalar(raw)) value = String(raw);
    else if (raw && typeof raw === 'object' && 'total' in (raw as Record<string, unknown>)) {
      const total = (raw as Record<string, unknown>).total;
      value = typeof total === 'number' ? fmtBytes(total) : String(total);
    } else continue;
    tiles.push({ label: t(def.labelKey), value });
  }

  // Defensively surface any remaining scalar status keys we don't curate.
  for (const [key, raw] of Object.entries(s)) {
    if (seen.has(key) || !isScalar(raw)) continue;
    tiles.push({ label: key, value: String(raw) });
  }

  return tiles;
});

function fmtBytes(n: number): string {
  if (!Number.isFinite(n)) return String(n);
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let v = n;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i++;
  }
  return `${v >= 10 || i === 0 ? Math.round(v) : v.toFixed(1)} ${units[i]}`;
}

function fmtDate(s: string): string {
  const d = new Date(s);
  return Number.isNaN(d.getTime()) ? s : d.toLocaleString();
}

async function resolve(err: ErrorRow) {
  resolving.value = err.id;
  try {
    await api.post<{ ok: boolean }>(`/api/v1/admin/system/errors/${err.id}/resolve`);
    err.resolved_at = new Date().toISOString();
  } catch {
    error(t('common.error'));
  } finally {
    resolving.value = null;
  }
}

/** Humanize a dotted/underscored action code, e.g. `backup.job.created` → "Backup · Job created". */
function humanizeAction(code: string): string {
  const parts = code.split('.').filter(Boolean);
  if (!parts.length) return code;
  const head = parts[0].charAt(0).toUpperCase() + parts[0].slice(1);
  const rest = parts.slice(1).join(' ').replace(/_/g, ' ').trim();
  return rest ? `${head} · ${rest.charAt(0).toUpperCase() + rest.slice(1)}` : head;
}

/** Localized audit-action label; falls back to a humanized string when the `audit.*` key is absent. */
function actionLabel(code: string): string {
  if (!code) return '';
  const key = `audit.${code}`;
  const r = t(key);
  return r === key ? humanizeAction(code) : r;
}

onMounted(async () => {
  try {
    const res = await api.get<SystemResponse>('/api/v1/admin/system');
    tasks.value = res.tasks ?? [];
    status.value = res.status ?? {};
    trend.value = res.trend ?? null;
    errors.value = res.errors ?? [];
    audit.value = res.audit ?? [];
  } catch {
    error(t('common.error'));
  }
});
</script>
