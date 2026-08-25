<template>
  <div class="space-y-6">
    <!-- Hero -->
    <div class="relative overflow-hidden rounded-2xl border border-primary-500/20 bg-gradient-to-br from-primary-500/12 to-primary-500/[0.03] p-6">
      <div class="relative z-10 max-w-xl">
        <h1 class="text-2xl font-bold">{{ t('pages.dashboard.heading') }}, {{ firstName }} 👋</h1>
        <p class="mt-1 text-sm text-[var(--ll-muted)]">{{ t('pages.dashboard.subtitle') }}</p>
        <Btn v-if="auth.can('finance')" variant="solid" icon="add" class="mt-4" @click="$router.push('/finance/invoices')">{{ t('invoices.new') }}</Btn>
      </div>
      <Icon name="insights" class="pointer-events-none absolute -right-2 top-1/2 hidden -translate-y-1/2 text-primary-500/15 sm:block" :size="150" />
    </div>

    <!-- KPI cards -->
    <div v-if="auth.can('finance')" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card v-for="s in stats" :key="s.key" :body-class="'p-4'">
        <div class="mb-2 flex items-center justify-between">
          <span class="grid h-10 w-10 place-items-center rounded-lg" :class="s.tint"><Icon :name="s.icon" :size="20" /></span>
          <Badge v-if="s.trend != null" :tone="s.trend >= 0 ? 'success' : 'error'">{{ s.trend >= 0 ? '+' : '' }}{{ s.trend }}%</Badge>
        </div>
        <div class="text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t(s.label) }}</div>
        <div class="mt-1 font-mono text-2xl font-bold tabular-nums">{{ s.value }}</div>
      </Card>
    </div>

    <!-- Deadlines found in documents. Unconfirmed ones are suggestions: they say
         where they were read from and ask, rather than asserting a date. -->
    <Card v-if="upcomingDeadlines.length" :title="t('deadlines.title')" :body-class="'p-0'">
      <div class="divide-y divide-[var(--ll-border)]">
        <div v-for="d in upcomingDeadlines" :key="d.id" class="flex items-start gap-3 px-4 py-2.5">
          <span
            class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg"
            :class="daysUntil(d.due_on) <= 7 ? 'bg-red-500/12 text-red-600 dark:text-red-400' : 'bg-amber-500/12 text-amber-600 dark:text-amber-400'"
          ><Icon name="event_busy" :size="18" /></span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-1.5">
              <span class="truncate text-sm font-medium">{{ d.label || t('deadlines.kind_' + d.kind) }}</span>
              <Badge tone="gray">{{ t('deadlines.kind_' + d.kind) }}</Badge>
              <Badge v-if="!d.confirmed_at" tone="warning">{{ t('deadlines.unconfirmed') }}</Badge>
            </div>
            <div class="text-xs text-[var(--ll-muted)]">
              {{ String(d.due_on).slice(0, 10) }} · {{ t('deadlines.in_days', { days: String(daysUntil(d.due_on)) }) }}
            </div>
            <div v-if="d.evidence && !d.confirmed_at" class="mt-0.5 truncate text-xs italic text-[var(--ll-muted)]">„{{ d.evidence }}"</div>
          </div>
          <div v-if="!d.confirmed_at" class="flex shrink-0 gap-1">
            <Btn variant="ghost" size="sm" icon="check" :title="t('deadlines.confirm')" @click="confirmDeadline(d, true)" />
            <Btn variant="ghost" size="sm" icon="close" :title="t('deadlines.dismiss')" @click="confirmDeadline(d, false)" />
          </div>
        </div>
      </div>
    </Card>

    <!-- What the day looks like. Eight modules exist and this page used to know
         two of them; these are the ones with something time-bound to say. -->
    <div v-if="agenda.length || sortedTodos.length || unreadMail || memoryPhotos.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card v-if="auth.can('calendar')" :title="t('dash.today')" :body-class="'p-0'">
        <template #actions><Btn variant="ghost" size="sm" @click="$router.push('/calendar')">{{ t('common.open') }}</Btn></template>
        <div v-if="!agenda.length" class="px-4 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('dash.no_events') }}</div>
        <div v-else class="divide-y divide-[var(--ll-border)]">
          <button v-for="e in agenda.slice(0, 5)" :key="e.id" class="flex w-full items-center gap-2 px-4 py-2 text-left hover:bg-black/[0.02] dark:hover:bg-white/5" @click="$router.push({ path: '/calendar', query: { event: e.id } })">
            <span class="w-11 shrink-0 font-mono text-xs tabular-nums text-[var(--ll-muted)]">{{ e.all_day ? t('dash.all_day') : hhmm(e.start) }}</span>
            <span class="min-w-0 flex-1 truncate text-sm">{{ e.summary || '—' }}</span>
          </button>
        </div>
      </Card>

      <Card v-if="auth.can('calendar')" :title="t('dash.tasks')" :body-class="'p-0'">
        <template #actions><Btn variant="ghost" size="sm" @click="$router.push('/tasks')">{{ t('common.open') }}</Btn></template>
        <div v-if="!sortedTodos.length" class="px-4 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('dash.no_tasks') }}</div>
        <div v-else class="divide-y divide-[var(--ll-border)]">
          <button v-for="todo in sortedTodos" :key="todo.id" class="flex w-full items-center gap-2 px-4 py-2 text-left hover:bg-black/[0.02] dark:hover:bg-white/5" @click="$router.push('/tasks')">
            <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="todoOverdue(todo) ? 'bg-red-500' : 'bg-[var(--ll-border)]'" />
            <span class="min-w-0 flex-1 truncate text-sm">{{ todo.summary || '—' }}</span>
            <span v-if="todo.due" class="shrink-0 text-xs" :class="todoOverdue(todo) ? 'text-red-600 dark:text-red-400' : 'text-[var(--ll-muted)]'">{{ String(todo.due).slice(0, 10) }}</span>
          </button>
        </div>
      </Card>

      <Card v-if="auth.can('mail')" :title="t('messages.nav.mail')">
        <template #actions><Btn variant="ghost" size="sm" @click="$router.push('/mail')">{{ t('common.open') }}</Btn></template>
        <button class="flex w-full items-center gap-3 text-left" @click="$router.push('/mail')">
          <span class="grid h-10 w-10 place-items-center rounded-lg bg-blue-500/12 text-blue-600 dark:text-blue-400"><Icon name="mail" :size="20" /></span>
          <span>
            <span class="block font-mono text-2xl font-bold tabular-nums">{{ unreadMail }}</span>
            <span class="text-xs text-[var(--ll-muted)]">{{ t('dash.unread') }}</span>
          </span>
        </button>
      </Card>

      <!-- "On this day": GalleryMemories already computes it; the landing page is
           where it belongs. -->
      <Card v-if="auth.can('gallery') && memoryPhotos.length" :title="t('dash.memories')" :body-class="'p-3'">
        <template #actions><Btn variant="ghost" size="sm" @click="$router.push('/gallery')">{{ t('common.open') }}</Btn></template>
        <div class="grid grid-cols-4 gap-1">
          <button v-for="p in memoryPhotos" :key="p.id" class="relative aspect-square overflow-hidden rounded-md" :title="t('dash.years_ago', { years: String(p.years) })" @click="$router.push({ path: '/gallery', query: { photo: String(p.id) } })">
            <img :src="photoThumb(p.id)" class="h-full w-full object-cover" alt="" loading="lazy">
          </button>
        </div>
      </Card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Recent invoices -->
      <Card v-if="auth.can('finance')" class="lg:col-span-2" :title="t('invoices.tab_invoices')" :body-class="'p-0'">
        <template #actions><Btn variant="ghost" size="sm" @click="$router.push('/finance/invoices')">{{ t('common.open') }}</Btn></template>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
              <tr class="border-b border-[var(--ll-border)]">
                <th class="px-4 py-2.5 font-medium">{{ t('invoices.col_number') }}</th>
                <th class="px-4 py-2.5 font-medium">{{ t('invoices.col_customer') }}</th>
                <th class="px-4 py-2.5 text-right font-medium">{{ t('invoices.col_total') }}</th>
                <th class="px-4 py-2.5 font-medium">{{ t('common.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in recent" :key="i.id" class="cursor-pointer border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5" @click="$router.push('/finance/invoices')">
                <td class="px-4 py-2.5 font-mono">{{ i.number || '—' }}</td>
                <td class="px-4 py-2.5">{{ custName(i) }}</td>
                <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ money(Number(i.gross ?? 0)) }}</td>
                <td class="px-4 py-2.5"><Badge :tone="statusTone(i.status)">{{ t('invoices.status_' + i.status) }}</Badge></td>
              </tr>
              <tr v-if="!recent.length"><td colspan="4" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
            </tbody>
          </table>
        </div>
      </Card>

      <!-- Servers: the whole point of the tile is the exceptions, so an all-clear
           fleet collapses to one line and anything wrong is named. -->
      <Card v-if="auth.can('servers')" :title="t('servers.title')" :body-class="'p-0'">
        <template #actions><Btn variant="ghost" size="sm" @click="$router.push('/servers')">{{ t('common.open') }}</Btn></template>
        <div v-if="!servers.length" class="px-4 py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('servers.none') }}</div>
        <div v-else>
          <div class="flex items-center gap-4 border-b border-[var(--ll-border)] px-4 py-3 text-sm">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500" />{{ serverCounts.ok }}</span>
            <span v-if="serverCounts.warn" class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-500" />{{ serverCounts.warn }}</span>
            <span v-if="serverCounts.down" class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-red-500" />{{ serverCounts.down }}</span>
            <span v-if="!serverCounts.warn && !serverCounts.down" class="text-[var(--ll-muted)]">{{ t('servers.status_ok') }}</span>
          </div>
          <button
            v-for="srv in serverAttention" :key="srv.id"
            class="flex w-full items-center gap-3 border-b border-[var(--ll-border)] px-4 py-2.5 text-left last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5"
            @click="$router.push('/servers')"
          >
            <span class="h-2 w-2 shrink-0 rounded-full" :class="srv.status && !srv.status.ok ? 'bg-red-500' : 'bg-amber-500'" />
            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ srv.name }}</span>
            <span class="shrink-0 text-xs text-[var(--ll-muted)]">{{ serverIssue(srv) }}</span>
          </button>
        </div>
      </Card>

      <!-- Module shortcuts -->
      <Card :class="auth.can('finance') ? '' : 'lg:col-span-3'" :title="t('settings.personal_heading')">
        <div class="space-y-1">
          <button
            v-for="m in modules" :key="m.to"
            class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left hover:bg-black/[0.04] dark:hover:bg-white/5"
            @click="$router.push(m.to)"
          >
            <span class="grid h-9 w-9 place-items-center rounded-lg" :class="m.tint"><Icon :name="m.icon" :size="20" /></span>
            <span class="flex-1 text-sm font-medium">{{ t(m.label) }}</span>
            <Icon name="chevron_right" :size="18" class="text-[var(--ll-muted)]" />
          </button>
        </div>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, Btn, Badge } from '@spa/ui';
import { fmtMoney } from '@spa/lib/money';
import { api } from '@spa/api/client';
import { useAuthStore } from '@spa/stores/auth';
import { useServersStore, type Server } from '@spa/stores/servers';
import { severity, needsAttention, DISK_WARN_PCT } from '@spa/lib/server-facts';

interface Inv { id: number; number: string | null; gross: number | null; status: string; customer: { name?: string } | null }
const auth = useAuthStore();
const serversStore = useServersStore();
const kpis = ref<{ year: number; net: number; count: number; growthPct: number | null } | null>(null);
const openGross = ref(0);
const vatPayable = ref(0);
const recent = ref<Inv[]>([]);


function money(n: number) { return fmtMoney(n); }
const firstName = computed(() => (auth.user?.name ?? '').split(' ')[0] || '');
function custName(i: Inv) { return i.customer?.name ?? '—'; }
function statusTone(s: string): 'success' | 'info' | 'warning' | 'gray' { return s === 'paid' ? 'success' : s === 'sent' ? 'info' : s === 'final' ? 'warning' : 'gray'; }

const stats = computed(() => [
  { key: 'rev', label: 'invoices.stat_revenue', icon: 'trending_up', tint: 'bg-primary-500/12 text-primary-600 dark:text-primary-300', value: money(kpis.value?.net ?? 0), trend: kpis.value?.growthPct ?? null },
  { key: 'open', label: 'invoices.outstanding_total', icon: 'schedule', tint: 'bg-amber-500/15 text-amber-600 dark:text-amber-400', value: money(openGross.value), trend: null },
  { key: 'vat', label: 'invoices.vat_payable', icon: 'receipt_long', tint: 'bg-blue-500/12 text-blue-600 dark:text-blue-400', value: money(vatPayable.value), trend: null },
  { key: 'cnt', label: 'invoices.invoice_count', icon: 'description', tint: 'bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10', value: String(kpis.value?.count ?? 0), trend: null },
]);

// Servers needing attention: unreachable first, then a filesystem over 90%, a
// failed unit or a pending reboot. A healthy fleet lists nothing.
const servers = computed(() => serversStore.servers);

/** Name the single most pressing thing, in the same order severity() ranks them. */
function serverIssue(srv: Server): string {
  const s = severity(srv);
  if (s === 'down') return t('servers.status_fail');
  if (s === 'unknown') return t('servers.status_unknown');
  const f = srv.facts;
  if (f && (f.disk_max_pct ?? 0) >= DISK_WARN_PCT) return `${f.disk_max_pct}%`;
  if (f && f.failed_units.length) return t('servers.failed_units');
  return t('servers.reboot_required');
}

const serverAttention = computed(() => servers.value.filter(needsAttention).slice(0, 6));

const serverCounts = computed(() => {
  const counts = { ok: 0, warn: 0, down: 0 };
  for (const srv of servers.value) {
    const s = severity(srv);
    // An un-probed server is not healthy; group it with the unreachable ones.
    if (s === 'down' || s === 'unknown') counts.down++;
    else if (s === 'warn') counts.warn++;
    else counts.ok++;
  }
  return counts;
});

const modules = computed(() => {
  const m: { to: string; label: string; icon: string; tint: string }[] = [];
  if (auth.can('finance')) m.push({ to: '/finance/invoices', label: 'invoices.tab_invoices', icon: 'account_balance_wallet', tint: 'bg-primary-500/12 text-primary-600 dark:text-primary-300' });
  if (auth.can('files')) m.push({ to: '/files', label: 'messages.nav.files', icon: 'folder', tint: 'bg-blue-500/12 text-blue-600 dark:text-blue-400' });
  if (auth.can('contacts')) m.push({ to: '/contacts', label: 'messages.nav.contacts', icon: 'contacts', tint: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400' });
  m.push({ to: '/profile', label: 'pages.profile.title', icon: 'account_circle', tint: 'bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10' });
  return m;
});

interface Occurrence { id: string; summary: string | null; start: string; end: string | null; all_day: boolean; location: string | null }
interface Todo { id: string; summary: string | null; due: string | null; status: string | null }
const agenda = ref<Occurrence[]>([]);
const openTodos = ref<Todo[]>([]);
const unreadMail = ref(0);
const memories = ref<{ years_ago: number; photos: { id: number; thumb?: boolean }[] }[]>([]);
function hhmm(iso: string) {
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}
/** Overdue first, then today, then the rest — the order the day is worked in. */
const sortedTodos = computed(() => [...openTodos.value].sort((a, b) => String(a.due ?? '9999').localeCompare(String(b.due ?? '9999'))).slice(0, 6));
function todoOverdue(t: Todo): boolean {
  return t.due != null && new Date(String(t.due).slice(0, 10)) < new Date(new Date().setHours(0, 0, 0, 0));
}
const memoryPhotos = computed(() => memories.value.flatMap((m) => m.photos.slice(0, 4).map((p) => ({ id: p.id, years: m.years_ago }))).slice(0, 8));
function photoThumb(id: number) { return api.streamUrl(`/api/v1/gallery/${id}/thumb`); }

interface Deadline { id: number; due_on: string; kind: string; label: string | null; evidence: string | null; confirmed_at: string | null }
const deadlines = ref<Deadline[]>([]);
/** Only what is actually approaching — a list of everything is not a warning. */
const upcomingDeadlines = computed(() => {
  const horizon = new Date(); horizon.setDate(horizon.getDate() + 60);
  return deadlines.value
    .filter((d) => new Date(String(d.due_on).slice(0, 10)) <= horizon)
    .slice(0, 6);
});
function daysUntil(due: string): number {
  const then = new Date(String(due).slice(0, 10)).getTime();
  return Math.ceil((then - new Date().setHours(0, 0, 0, 0)) / 86400000);
}
async function confirmDeadline(d: Deadline, confirmed: boolean) {
  try {
    await api.put(`/api/v1/deadlines/${d.id}`, confirmed ? { confirmed: true } : { dismissed: true });
    deadlines.value = confirmed
      ? deadlines.value.map((x) => (x.id === d.id ? { ...x, confirmed_at: new Date().toISOString() } : x))
      : deadlines.value.filter((x) => x.id !== d.id);
  } catch { /* the list reloads on the next visit */ }
}

onMounted(async () => {
  // Deadlines are read out of files, mail, gallery and finance alike, so they
  // are not gated on any single module.
  try { deadlines.value = (await api.get<{ deadlines: Deadline[] }>('/api/v1/deadlines')).deadlines ?? []; } catch { /* none yet */ }

  // Each tile is loaded independently and swallows its own failure: a module the
  // user does not have must not take the rest of the page down with it.
  if (auth.can('calendar')) {
    const from = new Date(); from.setHours(0, 0, 0, 0);
    const to = new Date(from); to.setDate(to.getDate() + 1);
    try {
      const qs = new URLSearchParams({ from: from.toISOString(), to: to.toISOString() }).toString();
      agenda.value = (await api.get<{ events: Occurrence[] }>(`/api/v1/calendar/events?${qs}`)).events ?? [];
    } catch { /* calendar disabled */ }
    try {
      const todos = (await api.get<{ todos: Todo[] }>('/api/v1/calendar/todos')).todos ?? [];
      openTodos.value = todos.filter((x) => x.status !== 'COMPLETED' && x.status !== 'CANCELLED');
    } catch { /* no task lists */ }
  }
  if (auth.can('mail')) {
    try { unreadMail.value = (await api.get<{ unread: number }>('/api/v1/mail/folders')).unread ?? 0; } catch { /* mail disabled */ }
  }
  if (auth.can('gallery')) {
    try {
      const m = await api.get<{ on_this_day: { years_ago: number; photos: { id: number }[] }[] }>('/api/v1/gallery/memories');
      memories.value = m.on_this_day ?? [];
    } catch { /* gallery disabled */ }
  }
  // Independent of finance: a servers-only user still gets their tile.
  if (auth.can('servers')) {
    try { await serversStore.load(); } catch { /* module disabled mid-session */ }
  }
  if (!auth.can('finance')) return;
  try {
    const r = await api.get<{ kpis?: typeof kpis.value; aging?: { openGross?: number }; currentVat?: { payable?: number } }>('/api/v1/finance/reports');
    kpis.value = r.kpis ?? null;
    openGross.value = r.aging?.openGross ?? 0;
    vatPayable.value = r.currentVat?.payable ?? 0;
    const d = await api.get<{ invoices: Inv[] }>('/api/v1/finance/data');
    recent.value = (d.invoices ?? []).slice(0, 6);
  } catch { /* finance disabled */ }
});
</script>
