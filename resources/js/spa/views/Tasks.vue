<template>
  <div class="flex min-h-[calc(100vh-120px)] flex-col gap-4 md:flex-row">
    <!-- Task lists rail -->
    <Card body-class="p-0" class="w-full shrink-0 self-start md:w-[240px]">
      <div class="p-3">
        <Btn variant="solid" icon="add" block @click="openNewTask">{{ t('calendar.todos.new_task') }}</Btn>
      </div>
      <nav class="space-y-0.5 px-2 pb-2">
        <div class="flex items-center justify-between px-2 pb-1 pt-2">
          <span class="text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('calendar.todos.task_list') }}</span>
          <button class="grid h-6 w-6 place-items-center rounded hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('calendar.todos.new_task_list')" @click="openNewList">
            <Icon name="add" :size="18" />
          </button>
        </div>
        <button
          class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="filters.listId === '' ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300 font-medium' : ''"
          @click="selectList('')"
        >
          <Icon name="checklist" :size="18" :class="filters.listId === '' ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" />
          <span class="flex-1 truncate">{{ t('calendar.todos.title') }}</span>
          <Badge v-if="openCount('') > 0" tone="gray">{{ openCount('') }}</Badge>
        </button>
        <div v-for="list in taskLists" :key="list.id" class="group flex items-center gap-1 rounded-lg">
          <button
            class="flex flex-1 items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
            :class="filters.listId === list.id ? 'bg-primary-500/10 font-medium' : ''"
            @click="selectList(list.id)"
          >
            <span class="h-3.5 w-3.5 shrink-0 rounded-[4px] border" :style="{ backgroundColor: list.color || '#6750a4', borderColor: list.color || '#6750a4' }" />
            <span class="flex-1 truncate" :class="filters.listId === list.id ? 'text-primary-600 dark:text-primary-300' : ''">{{ list.name }}</span>
            <Badge v-if="openCount(list.id) > 0" tone="gray">{{ openCount(list.id) }}</Badge>
          </button>
        </div>
        <div v-if="!taskLists.length" class="px-2 py-3 text-xs text-[var(--ll-muted)]">{{ t('calendar.todos.no_task_lists') }}</div>
      </nav>
      <div class="border-t border-[var(--ll-border)] p-3">
        <div class="flex flex-col gap-2">
          <Btn variant="outline" size="sm" icon="upload" class="w-full justify-start" :disabled="!taskLists.length" @click="openImport">{{ t('calendar.todos.import') }}</Btn>
          <Btn variant="ghost" size="sm" icon="download" tag="a" class="w-full justify-start" :href="exportHref" :title="t('calendar.todos.export')">{{ t('calendar.todos.export') }}</Btn>
        </div>
      </div>
    </Card>

    <!-- Main -->
    <Card body-class="flex flex-1 flex-col overflow-hidden p-0" class="flex min-w-0 flex-1 flex-col">
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] p-2">
        <h2 class="px-1 text-base font-semibold">{{ activeListName }}</h2>
        <div class="ml-auto flex flex-wrap items-center gap-2">
          <div class="w-40"><Select v-model="filters.status" :options="statusFilterOptions" /></div>
          <label class="flex items-center gap-1.5 text-sm text-[var(--ll-muted)]">
            <input v-model="filters.hideCompleted" type="checkbox" class="h-4 w-4 rounded border-[var(--ll-border)] accent-[var(--color-primary-500)]">
            {{ t('calendar.todos.show_completed') }}
          </label>
          <div class="w-36"><Select v-model="filters.sort" :options="sortOptions" /></div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-1 items-center justify-center py-16">
        <Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" />
      </div>

      <!-- List -->
      <div v-else class="flex-1 divide-y divide-[var(--ll-border)] overflow-y-auto">
        <div
          v-for="row in orderedRows" :key="row.task.id"
          class="group flex items-start gap-3 px-3 py-2.5 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]"
          :style="{ paddingLeft: 0.75 + row.depth * 1.5 + 'rem' }"
        >
          <button
            class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full border transition-colors"
            :class="isDone(row.task) ? 'border-primary-500 bg-primary-500 text-white' : 'border-[var(--ll-border)] hover:border-primary-500'"
            :title="isDone(row.task) ? t('calendar.todos.uncomplete') : t('calendar.todos.complete')"
            @click="toggleDone(row.task)"
          >
            <Icon v-if="isDone(row.task)" name="check" :size="14" />
          </button>

          <button class="min-w-0 flex-1 text-left" @click="openEdit(row.task)">
            <div class="flex items-center gap-2">
              <span class="truncate text-sm font-medium" :class="isDone(row.task) ? 'text-[var(--ll-muted)] line-through' : ''">{{ row.task.summary || '—' }}</span>
              <Icon v-if="row.task.rrule" name="repeat" :size="14" class="shrink-0 text-[var(--ll-muted)]" :title="t('calendar.todos.repeat')" />
              <Badge v-if="tierOf(row.task) !== 'none'" :tone="tierTone(row.task)">{{ t('calendar.todos.priority_' + tierOf(row.task)) }}</Badge>
            </div>
            <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
              <span v-if="row.task.due" class="text-xs tabular-nums" :class="dueClass(row.task)">
                <Icon name="event" :size="13" class="mr-0.5 inline align-[-2px]" />{{ formatDue(row.task) }}
              </span>
              <span v-for="c in row.task.categories" :key="c"><Badge tone="gray">{{ c }}</Badge></span>
              <span v-if="row.task.description" class="truncate text-xs text-[var(--ll-muted)]">{{ row.task.description }}</span>
            </div>
            <div v-if="hasProgress(row.task)" class="mt-1.5 h-1 w-40 max-w-full overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
              <div class="h-full rounded-full bg-primary-500" :style="{ width: (row.task.percent_complete || 0) + '%' }" />
            </div>
          </button>

          <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
            <template v-if="filters.sort === 'manual'">
              <button class="grid h-7 w-7 place-items-center rounded hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('calendar.todos.move_up')" @click="moveTask(row.task, -1)"><Icon name="keyboard_arrow_up" :size="16" class="text-[var(--ll-muted)]" /></button>
              <button class="grid h-7 w-7 place-items-center rounded hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('calendar.todos.move_down')" @click="moveTask(row.task, 1)"><Icon name="keyboard_arrow_down" :size="16" class="text-[var(--ll-muted)]" /></button>
            </template>
            <button class="grid h-7 w-7 place-items-center rounded hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('calendar.todos.edit_task')" @click="openEdit(row.task)"><Icon name="edit" :size="16" class="text-[var(--ll-muted)]" /></button>
            <button class="grid h-7 w-7 place-items-center rounded text-red-600 hover:bg-red-500/10 dark:text-red-400" :title="t('calendar.todos.delete_task')" @click="removeTask(row.task)"><Icon name="delete" :size="16" /></button>
          </div>
        </div>

        <div v-if="!orderedRows.length" class="grid place-items-center gap-3 p-12 text-center text-sm text-[var(--ll-muted)]">
          <Icon name="task_alt" :size="34" class="text-[var(--ll-muted)]" />
          <span>{{ taskLists.length ? t('calendar.todos.no_tasks') : t('calendar.todos.no_task_lists') }}</span>
          <Btn v-if="!taskLists.length" variant="solid" icon="add" @click="openNewList">{{ t('calendar.todos.new_task_list') }}</Btn>
        </div>
      </div>
    </Card>
  </div>

  <!-- Task editor -->
  <Modal v-model="taskModal" :title="editingId ? t('calendar.todos.edit_task') : t('calendar.todos.new_task')" width="560px">
    <div class="space-y-4">
      <TextField v-model="form.summary" :label="t('calendar.todos.summary')" />
      <Select v-if="!editingId" v-model="form.calendar_id" :label="t('calendar.todos.task_list')" :options="listOptions" />
      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.allDay" type="checkbox" class="h-4 w-4 rounded border-[var(--ll-border)] accent-[var(--color-primary-500)]" @change="onAllDayToggle">
        {{ t('calendar.todos.all_day') }}
      </label>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <TextField v-model="form.dtstart" :label="t('calendar.todos.starts')" :type="form.allDay ? 'date' : 'datetime-local'" />
        <TextField v-model="form.due" :label="t('calendar.todos.due')" :type="form.allDay ? 'date' : 'datetime-local'" />
      </div>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Select v-model="form.priority" :label="t('calendar.todos.priority')" :options="priorityOptions" />
        <Select v-model="form.status" :label="t('calendar.todos.status')" :options="statusOptions" />
      </div>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Select v-model="form.freq" :label="t('calendar.todos.repeat')" :options="repeatOptions" />
        <TextField v-if="form.freq !== 'none'" v-model="form.interval" :label="t('calendar.todos.interval')" type="number" inputmode="numeric" />
      </div>
      <p v-if="form.freq !== 'none'" class="text-xs text-[var(--ll-muted)]">{{ t('calendar.todos.recurring_hint') }}</p>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('calendar.todos.percent_complete') }} — {{ form.percent }}%</span>
        <input v-model.number="form.percent" type="range" min="0" max="100" step="5" class="w-full accent-[var(--color-primary-500)]">
      </label>
      <Select v-if="parentOptions.length > 1" v-model="form.related_to" :label="t('calendar.todos.subtask_of')" :options="parentOptions" />
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('calendar.todos.categories') }}</span>
        <div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-[var(--ll-border)] p-2">
          <span v-for="(c, i) in form.categories" :key="c + i" class="inline-flex items-center gap-1 rounded-md bg-black/[0.05] px-2 py-0.5 text-xs dark:bg-white/10">
            {{ c }}
            <button class="text-[var(--ll-muted)] hover:text-red-500" @click="removeCat(i)"><Icon name="close" :size="12" /></button>
          </span>
          <input
            v-model="catInput"
            :placeholder="t('common.add')"
            class="min-w-[8rem] flex-1 bg-transparent text-sm text-[var(--ll-fg)] placeholder:text-[var(--ll-muted)] focus:outline-none"
            @keydown.enter.prevent="addCat"
            @keydown="onCatKey"
            @blur="addCat"
          >
        </div>
      </div>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('calendar.todos.description') }}</span>
        <textarea
          v-model="form.description" rows="3"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
        ></textarea>
      </label>
    </div>
    <template #footer>
      <Btn v-if="editingId" variant="danger" class="mr-auto" :loading="deleting" @click="onDelete">{{ t('calendar.todos.delete_task') }}</Btn>
      <Btn variant="ghost" @click="taskModal = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="saving" :disabled="!form.calendar_id" @click="save">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <!-- Task-list editor -->
  <Modal v-model="listModal" :title="t('calendar.todos.new_task_list')" width="420px">
    <div class="space-y-4">
      <TextField v-model="listForm.name" :label="t('calendar.todos.task_list')" />
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('calendar.ui.color') }}</span>
        <input v-model="listForm.color" type="color" class="h-9 w-16 cursor-pointer rounded border border-[var(--ll-border)] bg-transparent">
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="listModal = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="listSaving" :disabled="!listForm.name" @click="saveList">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <!-- Import -->
  <Modal v-model="importModal" :title="t('calendar.todos.import')" width="460px">
    <div class="space-y-4">
      <Select v-model="importListId" :label="t('calendar.todos.task_list')" :options="listOptions" />
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('calendar.todos.import') }}</span>
        <input
          type="file" accept=".ics,text/calendar"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] file:mr-3 file:rounded-md file:border-0 file:bg-primary-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-600 dark:file:text-primary-300"
          @change="onImportFile"
        >
      </label>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="importModal = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="importing" :disabled="!importFile || !importListId" @click="runImport">{{ t('calendar.todos.import') }}</Btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { api, ApiError } from '@spa/api/client';
import { useTasksStore, type CalendarTodo, type TodoStatus, type CalendarTodoInput } from '@spa/stores/tasks';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';

const store = useTasksStore();
const { success, error } = useToast();
const locale = document.documentElement.lang || 'de';

const filters = store.filters;
const taskLists = computed(() => store.taskLists);
const loading = ref(false);
const now = ref(Date.now());

// --- date helpers -----------------------------------------------------------
function pad(n: number): string { return String(n).padStart(2, '0'); }
function ymd(d: Date): string { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }
function toInput(iso: string, allDay: boolean): string {
  if (allDay) return iso.slice(0, 10);
  const d = new Date(iso);
  return `${ymd(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function formatDue(task: CalendarTodo): string {
  if (!task.due) return '';
  const d = new Date(task.due);
  return task.all_day
    ? d.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' })
    : d.toLocaleString(locale, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

// --- priority mapping -------------------------------------------------------
type Tier = 'none' | 'high' | 'medium' | 'low';
function tierFromNumber(p: number | null): Tier {
  if (p == null || p === 0) return 'none';
  if (p <= 4) return 'high';
  if (p === 5) return 'medium';
  return 'low';
}
const TIER_VALUE: Record<Tier, string> = { none: '0', high: '1', medium: '5', low: '9' };
function tierOf(task: CalendarTodo): Tier { return tierFromNumber(task.priority); }
function tierTone(task: CalendarTodo): 'error' | 'warning' | 'info' {
  const tier = tierOf(task);
  return tier === 'high' ? 'error' : tier === 'medium' ? 'warning' : 'info';
}

// --- RRULE builder (FREQ + INTERVAL) ----------------------------------------
function buildRrule(freq: string, interval: number): string | null {
  if (freq === 'none') return null;
  const f = freq.toUpperCase();
  return interval > 1 ? `FREQ=${f};INTERVAL=${interval}` : `FREQ=${f}`;
}
function parseRrule(rrule: string | null): { freq: string; interval: number } {
  if (!rrule) return { freq: 'none', interval: 1 };
  const up = rrule.toUpperCase();
  let freq = 'none';
  for (const f of ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']) if (up.includes('FREQ=' + f)) freq = f.toLowerCase();
  const m = up.match(/INTERVAL=(\d+)/);
  return { freq, interval: m ? Math.max(1, parseInt(m[1], 10)) : 1 };
}

// --- filtering / status -----------------------------------------------------
function isDone(task: CalendarTodo): boolean { return task.status === 'COMPLETED' || task.status === 'CANCELLED'; }
function hasProgress(task: CalendarTodo): boolean {
  const p = task.percent_complete;
  return p != null && p > 0 && p < 100 && !isDone(task);
}
function dueClass(task: CalendarTodo): string {
  if (!task.due || isDone(task)) return 'text-[var(--ll-muted)]';
  const due = new Date(task.due).getTime();
  if (due < now.value) return 'text-red-600 dark:text-red-400 font-medium';
  if (due < now.value + 86_400_000) return 'text-amber-600 dark:text-amber-400';
  return 'text-[var(--ll-muted)]';
}

const visibleTasks = computed<CalendarTodo[]>(() =>
  store.tasks.filter((task) => {
    if (filters.hideCompleted && isDone(task)) return false;
    if (filters.status !== 'all' && task.status !== filters.status) return false;
    return true;
  }),
);

// --- subtask nesting (related_to → parent UID) ------------------------------
function comparator(a: CalendarTodo, b: CalendarTodo): number {
  if (filters.sort === 'manual') return (a.sort_order - b.sort_order) || (a.summary || '').localeCompare(b.summary || '');
  if (filters.sort === 'priority') {
    const order: Record<Tier, number> = { high: 0, medium: 1, low: 2, none: 3 };
    const d = order[tierOf(a)] - order[tierOf(b)];
    if (d !== 0) return d;
  }
  // due (default) + priority tiebreak: dated first, ascending; undated last.
  if (a.due && b.due) return a.due.localeCompare(b.due);
  if (a.due) return -1;
  if (b.due) return 1;
  return (a.summary || '').localeCompare(b.summary || '');
}

interface Row { task: CalendarTodo; depth: number }
const orderedRows = computed<Row[]>(() => {
  const list = visibleTasks.value;
  const byUid = new Map<string, CalendarTodo>();
  for (const task of list) if (task.uid) byUid.set(task.uid, task);
  // A task is a child only when its parent is also visible; otherwise it is a root
  // (so a task is never lost when its parent is filtered out).
  const childrenOf = new Map<string, CalendarTodo[]>();
  const roots: CalendarTodo[] = [];
  for (const task of list) {
    if (task.related_to && byUid.has(task.related_to) && task.related_to !== task.uid) {
      const arr = childrenOf.get(task.related_to) ?? [];
      arr.push(task);
      childrenOf.set(task.related_to, arr);
    } else {
      roots.push(task);
    }
  }
  const rows: Row[] = [];
  const seen = new Set<string>();
  const walk = (task: CalendarTodo, depth: number): void => {
    if (seen.has(task.id)) return; // cycle guard
    seen.add(task.id);
    rows.push({ task, depth });
    const kids = task.uid ? (childrenOf.get(task.uid) ?? []).slice().sort(comparator) : [];
    for (const kid of kids) walk(kid, depth + 1);
  };
  for (const root of roots.slice().sort(comparator)) walk(root, 0);
  return rows;
});

// --- rail counts / list selection -------------------------------------------
function openCount(listId: string): number {
  return store.tasks.filter((task) => (listId === '' || task.calendar === listId) && !isDone(task)).length;
}
const activeListName = computed<string>(() => {
  if (!filters.listId) return t('calendar.todos.title');
  return store.taskLists.find((l) => l.id === filters.listId)?.name ?? t('calendar.todos.title');
});
async function selectList(id: string): Promise<void> { filters.listId = id; await reload(); }

async function reload(): Promise<void> {
  loading.value = true;
  now.value = Date.now();
  try { await store.loadTasks(); } catch { error(t('common.error')); } finally { loading.value = false; }
}

// --- toolbar options --------------------------------------------------------
const statusFilterOptions = computed(() => [
  { title: t('calendar.todos.filter_all'), value: 'all' },
  { title: t('calendar.todos.status_needs_action'), value: 'NEEDS-ACTION' },
  { title: t('calendar.todos.status_in_process'), value: 'IN-PROCESS' },
  { title: t('calendar.todos.status_completed'), value: 'COMPLETED' },
  { title: t('calendar.todos.status_cancelled'), value: 'CANCELLED' },
]);
const sortOptions = computed(() => [
  { title: t('calendar.todos.sort_due'), value: 'due' },
  { title: t('calendar.todos.sort_priority'), value: 'priority' },
  { title: t('calendar.todos.sort_manual'), value: 'manual' },
]);
const listOptions = computed(() => store.taskLists.map((l) => ({ title: l.name, value: l.id })));

// --- task editor ------------------------------------------------------------
const taskModal = ref(false);
const editingId = ref<string | null>(null);
const currentEtag = ref('');
const saving = ref(false);
const deleting = ref(false);
const catInput = ref('');

const form = reactive<{
  calendar_id: string; summary: string; description: string; allDay: boolean;
  dtstart: string; due: string; priority: string; status: TodoStatus; percent: number;
  freq: string; interval: string; categories: string[]; related_to: string;
}>({
  calendar_id: '', summary: '', description: '', allDay: false,
  dtstart: '', due: '', priority: '0', status: 'NEEDS-ACTION', percent: 0,
  freq: 'none', interval: '1', categories: [], related_to: '',
});

const priorityOptions = computed(() => (['none', 'high', 'medium', 'low'] as Tier[]).map((tier) => ({ title: t('calendar.todos.priority_' + tier), value: TIER_VALUE[tier] })));
const statusOptions = computed(() => [
  { title: t('calendar.todos.status_needs_action'), value: 'NEEDS-ACTION' },
  { title: t('calendar.todos.status_in_process'), value: 'IN-PROCESS' },
  { title: t('calendar.todos.status_completed'), value: 'COMPLETED' },
  { title: t('calendar.todos.status_cancelled'), value: 'CANCELLED' },
]);
const repeatOptions = computed(() => [
  { title: t('calendar.todos.repeat_none'), value: 'none' },
  { title: t('calendar.todos.repeat_daily'), value: 'daily' },
  { title: t('calendar.todos.repeat_weekly'), value: 'weekly' },
  { title: t('calendar.todos.repeat_monthly'), value: 'monthly' },
  { title: t('calendar.todos.repeat_yearly'), value: 'yearly' },
]);
// Parent-task picker: other tasks in the same list that are not this task nor one
// of its descendants (cycle-safe). Prepends a "none" option.
const parentOptions = computed(() => {
  const opts = [{ title: t('calendar.todos.priority_none'), value: '' }];
  const descendants = editingId.value ? descendantUids(editingId.value) : new Set<string>();
  for (const task of store.tasks) {
    if (!task.uid) continue;
    if (task.calendar !== form.calendar_id) continue;
    if (task.id === editingId.value || descendants.has(task.uid)) continue;
    opts.push({ title: task.summary || '—', value: task.uid });
  }
  return opts;
});
function descendantUids(taskId: string): Set<string> {
  const out = new Set<string>();
  const byUid = new Map<string, CalendarTodo>();
  for (const task of store.tasks) if (task.uid) byUid.set(task.uid, task);
  const start = store.tasks.find((task) => task.id === taskId);
  const queue: string[] = start?.uid ? [start.uid] : [];
  while (queue.length) {
    const uid = queue.shift()!;
    for (const task of store.tasks) if (task.related_to === uid && task.uid && !out.has(task.uid)) { out.add(task.uid); queue.push(task.uid); }
  }
  return out;
}

function onAllDayToggle(): void {
  if (form.allDay) {
    if (form.dtstart) form.dtstart = form.dtstart.slice(0, 10);
    if (form.due) form.due = form.due.slice(0, 10);
  } else {
    if (form.dtstart.length === 10) form.dtstart += 'T09:00';
    if (form.due.length === 10) form.due += 'T09:00';
  }
}
function addCat(): void {
  const raw = catInput.value.split(',').map((s) => s.trim()).filter(Boolean);
  for (const c of raw) if (!form.categories.includes(c)) form.categories.push(c);
  catInput.value = '';
}
function onCatKey(e: KeyboardEvent): void {
  if (e.key === ',') { e.preventDefault(); addCat(); }
  else if (e.key === 'Backspace' && catInput.value === '' && form.categories.length) form.categories.pop();
}
function removeCat(i: number): void { form.categories.splice(i, 1); }

function openNewTask(): void {
  // No task list yet → a task can't exist without one. Send the user to create
  // their first list instead of leaving a dead/greyed button.
  if (!store.taskLists.length) { openNewList(); return; }
  editingId.value = null;
  currentEtag.value = '';
  catInput.value = '';
  Object.assign(form, {
    calendar_id: filters.listId || store.taskLists[0]?.id || '',
    summary: '', description: '', allDay: false,
    dtstart: '', due: '', priority: '0', status: 'NEEDS-ACTION' as TodoStatus, percent: 0,
    freq: 'none', interval: '1', categories: [], related_to: '',
  });
  taskModal.value = true;
}
function openEdit(task: CalendarTodo): void {
  editingId.value = task.id;
  currentEtag.value = task.etag;
  catInput.value = '';
  const rr = parseRrule(task.rrule);
  Object.assign(form, {
    calendar_id: task.calendar,
    summary: task.summary ?? '',
    description: task.description ?? '',
    allDay: task.all_day,
    dtstart: task.dtstart ? toInput(task.dtstart, task.all_day) : '',
    due: task.due ? toInput(task.due, task.all_day) : '',
    priority: TIER_VALUE[tierFromNumber(task.priority)],
    status: task.status,
    percent: task.percent_complete ?? 0,
    freq: rr.freq,
    interval: String(rr.interval),
    categories: [...(task.categories ?? [])],
    related_to: task.related_to ?? '',
  });
  taskModal.value = true;
}
function buildBody(): CalendarTodoInput {
  addCat(); // fold any un-committed tag text
  return {
    calendar_id: form.calendar_id,
    summary: form.summary,
    description: form.description || null,
    all_day: form.allDay,
    dtstart: form.dtstart || null,
    due: form.due || null,
    status: form.status,
    priority: Number(form.priority),
    percent_complete: form.percent,
    rrule: buildRrule(form.freq, Math.max(1, parseInt(form.interval || '1', 10))),
    categories: form.categories,
    related_to: form.related_to || null,
  };
}
async function save(): Promise<void> {
  if (!form.calendar_id) return;
  saving.value = true;
  try {
    if (editingId.value) await store.updateTask(editingId.value, { ...buildBody(), etag: currentEtag.value });
    else await store.createTask(buildBody());
    taskModal.value = false;
    await reload();
    success(t('common.saved'));
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) await reload();
    error(t('common.error'));
  } finally { saving.value = false; }
}
async function onDelete(): Promise<void> {
  if (!editingId.value || !await confirmAsk(t('calendar.todos.delete_confirm'), { danger: true })) return;
  deleting.value = true;
  try {
    await store.deleteTask(editingId.value);
    taskModal.value = false;
    await reload();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { deleting.value = false; }
}
async function removeTask(task: CalendarTodo): Promise<void> {
  if (!await confirmAsk(t('calendar.todos.delete_confirm'), { danger: true })) return;
  try { await store.deleteTask(task.id); await reload(); } catch { error(t('common.error')); }
}

// --- complete toggle --------------------------------------------------------
async function toggleDone(task: CalendarTodo): Promise<void> {
  try {
    const res = isDone(task) ? await store.uncomplete(task.id) : await store.complete(task.id);
    const idx = store.tasks.findIndex((x) => x.id === task.id);
    if (idx !== -1 && res.todo) store.tasks[idx] = res.todo;
    if ('rolled' in res && (res as { rolled?: boolean }).rolled) success(t('calendar.todos.rolled'));
  } catch { error(t('common.error')); }
}

// --- manual reorder ---------------------------------------------------------
async function moveTask(task: CalendarTodo, dir: -1 | 1): Promise<void> {
  const rows = orderedRows.value;
  const idx = rows.findIndex((r) => r.task.id === task.id);
  const swapWith = rows[idx + dir];
  // Only swap adjacent siblings (same parent + list) to keep nesting coherent.
  if (!swapWith || swapWith.task.related_to !== task.related_to || swapWith.task.calendar !== task.calendar) return;
  const order = rows.map((r) => r.task.id);
  [order[idx], order[idx + dir]] = [order[idx + dir], order[idx]];
  // Optimistic local sort_order so the row visibly moves before the round-trip.
  order.forEach((id, i) => { const found = store.tasks.find((tk) => tk.id === id); if (found) found.sort_order = i; });
  try { await store.reorder(order); } catch { error(t('common.error')); await reload(); }
}

// --- task-list editor -------------------------------------------------------
const listModal = ref(false);
const listSaving = ref(false);
const listForm = reactive<{ name: string; color: string }>({ name: '', color: '#6750a4' });
function openNewList(): void { listForm.name = ''; listForm.color = '#6750a4'; listModal.value = true; }
async function saveList(): Promise<void> {
  if (!listForm.name) return;
  listSaving.value = true;
  try {
    const r = await store.createTaskList(listForm.name, listForm.color);
    listModal.value = false;
    await store.loadTaskLists();
    if (r?.id) { filters.listId = r.id; await reload(); }
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { listSaving.value = false; }
}

// --- import / export --------------------------------------------------------
const importModal = ref(false);
const importListId = ref('');
const importFile = ref<File | null>(null);
const importing = ref(false);
const exportHref = computed(() => api.streamUrl(store.exportUrl(filters.listId || undefined)));
function openImport(): void { importFile.value = null; importListId.value = filters.listId || store.taskLists[0]?.id || ''; importModal.value = true; }
function onImportFile(ev: Event): void { const input = ev.target as HTMLInputElement; importFile.value = input.files?.[0] ?? null; }
async function runImport(): Promise<void> {
  if (!importFile.value || !importListId.value) return;
  importing.value = true;
  try {
    const r = await store.importIcs(importFile.value, importListId.value);
    importModal.value = false;
    await reload();
    success(t('calendar.todos.import_done', { created: String(r.created), updated: String(r.updated), skipped: String(r.skipped) }));
  } catch { error(t('common.error')); } finally { importing.value = false; }
}

// Re-fetch when a server-scoped filter (list / due cap) changes; status, hide and
// sort are purely client-side over the loaded set.
watch(() => filters.dueBefore, () => { reload(); });

// Keep the dtstart/due strings in the shape the active input type expects: a
// `date` input wants `yyyy-MM-dd`, a `datetime-local` input wants `…Thh:mm`.
// Without this, toggling "all day" leaves a date-only value in a datetime-local
// field (browser warns "does not conform to the required format").
watch(() => form.allDay, (allDay) => {
  for (const k of ['dtstart', 'due'] as const) {
    const v = form[k];
    if (!v) continue;
    if (allDay) form[k] = v.slice(0, 10);
    else if (v.length === 10) form[k] = `${v}T09:00`;
  }
});

onMounted(async () => {
  try { await store.loadTaskLists(); } catch { /* ignore */ }
  await reload();
});
</script>
