<template>
  <div class="mx-auto" style="max-width: 960px">
    <!-- Scheduled tasks -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiClockOutline" size="small" />
        {{ t('settings.system_cron_heading') }}
      </v-card-title>
      <v-divider />
      <v-table density="comfortable">
        <thead>
          <tr>
            <th class="text-left">{{ t('settings.system_task') }}</th>
            <th class="text-left">{{ t('settings.system_schedule') }}</th>
            <th class="text-left">{{ t('settings.system_last_run') }}</th>
            <th class="text-left">{{ t('common.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="task in tasks" :key="task.name">
            <td class="text-body-2">{{ task.name }}</td>
            <td><code class="text-caption">{{ task.expression }}</code></td>
            <td class="text-caption text-medium-emphasis">{{ task.lastAt ? fmtDate(task.lastAt) : t('settings.system_never') }}</td>
            <td>
              <v-icon
                :icon="task.lastOk ? mdiCheckCircle : mdiAlertCircle"
                :color="task.lastOk ? 'success' : (task.lastAt ? 'error' : 'disabled')"
                size="small"
              />
            </td>
          </tr>
          <tr v-if="!tasks.length">
            <td colspan="4" class="text-caption text-disabled">{{ t('common.none') }}</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <!-- Status snapshot -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiGaugeFull" size="small" />
        {{ t('settings.system_status_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <v-row dense>
          <v-col v-for="tile in statusTiles" :key="tile.label" cols="6" sm="4" md="3">
            <div class="pa-3 rounded-lg" style="background: rgba(0,0,0,.03)">
              <div class="text-overline text-medium-emphasis">{{ tile.label }}</div>
              <div class="text-h6">{{ tile.value }}</div>
            </div>
          </v-col>
          <v-col v-if="!statusTiles.length" cols="12">
            <span class="text-caption text-disabled">{{ t('common.none') }}</span>
          </v-col>
        </v-row>

        <div class="mt-4 text-caption text-medium-emphasis">
          <template v-if="trend && trend.points.length">
            {{ t('settings.system_trend') }}: {{ fmtBytes(trend.deltaBytes) }} {{ t('settings.system_trend_days', { n: String(trend.deltaDays) }) }}
          </template>
          <template v-else>
            {{ t('settings.system_trend_collecting') }}
          </template>
        </div>
      </v-card-text>
    </v-card>

    <!-- Open errors -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiAlertOctagonOutline" size="small" />
        {{ t('settings.system_errors_heading') }}
      </v-card-title>
      <v-divider />
      <v-list v-if="unresolvedErrors.length" lines="two">
        <v-list-item v-for="err in unresolvedErrors" :key="err.id">
          <v-list-item-title class="text-body-2">
            <v-chip v-if="err.level" size="x-small" color="error" variant="tonal" class="mr-2">{{ err.level }}</v-chip>
            {{ err.message || err.exception }}
          </v-list-item-title>
          <v-list-item-subtitle class="text-caption">
            <span v-if="err.file">{{ err.file }}<span v-if="err.line">:{{ err.line }}</span> · </span>
            <span v-if="err.count">{{ t('settings.system_error_count', { n: String(err.count) }) }} · </span>
            <span v-if="err.last_seen_at">{{ fmtDate(err.last_seen_at) }}</span>
          </v-list-item-subtitle>
          <template #append>
            <v-btn size="small" variant="tonal" :prepend-icon="mdiCheck" :loading="resolving === err.id" @click="resolve(err)">
              {{ t('settings.system_error_resolve') }}
            </v-btn>
          </template>
        </v-list-item>
      </v-list>
      <v-card-text v-else class="text-caption text-medium-emphasis">{{ t('settings.system_no_errors') }}</v-card-text>
    </v-card>

    <!-- Recent audit -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiFormatListBulletedSquare" size="small" />
        {{ t('settings.system_audit_heading') }}
      </v-card-title>
      <v-divider />
      <v-list v-if="audit.length" lines="two">
        <v-list-item v-for="row in audit" :key="row.id">
          <v-list-item-title class="text-body-2">{{ row.action }}</v-list-item-title>
          <v-list-item-subtitle class="text-caption">
            <span v-if="row.actor">{{ row.actor }} · </span>
            <span v-if="row.ip">{{ row.ip }} · </span>
            <span>{{ fmtDate(row.created_at) }}</span>
          </v-list-item-subtitle>
        </v-list-item>
      </v-list>
      <v-card-text v-else class="text-caption text-medium-emphasis">{{ t('settings.system_no_audit') }}</v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import {
  mdiClockOutline,
  mdiCheckCircle,
  mdiAlertCircle,
  mdiGaugeFull,
  mdiAlertOctagonOutline,
  mdiCheck,
  mdiFormatListBulletedSquare,
} from '@mdi/js';

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
