<template>
  <div class="flex min-h-[calc(100vh-120px)] flex-col gap-4 md:flex-row">
    <!-- Calendars rail -->
    <Card body-class="p-0" class="w-full shrink-0 self-start md:w-[240px]">
      <div class="p-3">
        <Btn variant="solid" icon="add" block @click="openNewEvent">{{ t('calendar.ui.new_event') }}</Btn>
      </div>
      <nav class="space-y-0.5 px-2 pb-2">
        <div class="flex items-center justify-between px-2 pb-1 pt-2">
          <span class="text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ t('calendar.ui.calendars') }}</span>
          <button class="grid h-6 w-6 place-items-center rounded hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('calendar.ui.new_calendar')" @click="openNewCalendar">
            <Icon name="add" :size="18" />
          </button>
        </div>
        <div v-for="cal in store.calendars" :key="cal.id" class="group flex items-center gap-1 rounded-lg">
          <button
            class="flex flex-1 items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
            @click="toggleCal(cal.id)"
          >
            <span class="h-3.5 w-3.5 shrink-0 rounded-[4px] border" :style="{ backgroundColor: isVisible(cal.id) ? (cal.color || '#6750a4') : 'transparent', borderColor: cal.color || '#6750a4' }" />
            <span class="truncate" :class="isVisible(cal.id) ? '' : 'text-[var(--ll-muted)] opacity-60'">{{ cal.name }}</span>
          </button>
          <!-- Special (system) calendars are managed only in Settings → Calendar;
               here they are view-only (color + visibility toggle above). -->
          <template v-if="cal.kind === 'normal'">
            <button class="hidden h-7 w-7 place-items-center rounded hover:bg-black/[0.05] group-hover:grid dark:hover:bg-white/10" :title="t('calendar.ui.rename_calendar')" @click="openEditCalendar(cal)">
              <Icon name="edit" :size="16" class="text-[var(--ll-muted)]" />
            </button>
            <button class="hidden h-7 w-7 place-items-center rounded text-red-600 hover:bg-red-500/10 group-hover:grid dark:text-red-400" :title="t('calendar.ui.delete_calendar')" @click="removeCalendar(cal)">
              <Icon name="delete" :size="16" />
            </button>
          </template>
        </div>
        <div v-if="!store.calendars.length" class="px-2 py-3 text-xs text-[var(--ll-muted)]">{{ t('calendar.ui.no_events') }}</div>
      </nav>
      <div class="border-t border-[var(--ll-border)] p-3">
        <div class="flex flex-col gap-2">
          <Btn variant="outline" size="sm" icon="upload" class="w-full justify-start" @click="openImport">{{ t('calendar.ui.import') }}</Btn>
          <Btn variant="ghost" size="sm" icon="download" tag="a" class="w-full justify-start" :href="exportHref" :title="t('calendar.ui.export')">{{ t('calendar.ui.export') }}</Btn>
        </div>
      </div>
    </Card>

    <!-- Main -->
    <Card body-class="flex flex-1 flex-col overflow-hidden p-0" class="flex min-w-0 flex-1 flex-col">
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center gap-2 border-b border-[var(--ll-border)] p-2">
        <Btn variant="outline" size="sm" @click="goToday">{{ t('calendar.ui.today') }}</Btn>
        <div class="flex items-center">
          <Btn variant="ghost" size="sm" icon="chevron_left" :title="t('calendar.ui.today')" @click="goPrev" />
          <Btn variant="ghost" size="sm" icon="chevron_right" :title="t('calendar.ui.today')" @click="goNext" />
        </div>
        <h2 class="px-1 text-base font-semibold capitalize">{{ headerLabel }}</h2>
        <div class="ml-auto inline-flex rounded-lg bg-black/[0.04] p-0.5 dark:bg-white/5">
          <button
            v-for="v in views" :key="v"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
            :class="view === v ? 'bg-[var(--ll-surface)] text-primary-600 shadow-sm dark:text-primary-300' : 'text-[var(--ll-muted)] hover:text-[var(--ll-fg)]'"
            @click="setView(v)"
          >{{ t('calendar.ui.view_' + v) }}</button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-1 items-center justify-center py-16">
        <Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" />
      </div>

      <!-- MONTH -->
      <div v-else-if="view === 'month'" class="flex flex-1 flex-col overflow-hidden">
        <div class="grid w-full border-b border-[var(--ll-border)] text-center text-[0.7rem] font-medium uppercase tracking-wide text-[var(--ll-muted)]" :style="monthCols">
          <div class="grid place-items-center py-2 text-[0.6rem]">{{ t('calendar.ui.kw') }}</div>
          <div v-for="w in weekdayLabels" :key="w" class="py-2">{{ w }}</div>
        </div>
        <div class="grid w-full flex-1 overflow-y-auto" :style="monthCols">
          <template v-for="week in monthWeeks" :key="week.key">
            <div class="grid place-items-center border-b border-r border-[var(--ll-border)] bg-black/[0.015] text-[0.7rem] tabular-nums text-[var(--ll-muted)] dark:bg-white/[0.02]">{{ week.kw }}</div>
            <div
              v-for="day in week.days" :key="ymd(day)"
              class="min-h-[96px] cursor-pointer border-b border-r border-[var(--ll-border)] p-1"
              :class="inMonth(day) ? '' : 'bg-black/[0.015] dark:bg-white/[0.02]'"
              @click="openCreate(ymd(day))"
            >
              <div class="mb-1 flex justify-end">
                <span
                  class="grid h-6 w-6 place-items-center rounded-full text-xs font-medium"
                  :class="isToday(day) ? 'bg-primary-500 text-white' : (inMonth(day) ? '' : 'text-[var(--ll-muted)]')"
                >{{ day.getDate() }}</span>
              </div>
              <div class="space-y-0.5">
                <button
                  v-for="o in cellEvents(day).slice(0, 3)" :key="o.id + o.start"
                  class="flex w-full items-center gap-1 truncate rounded px-1 py-0.5 text-left text-[11px] hover:opacity-80"
                  :style="o.all_day ? { backgroundColor: chipBg(o) } : {}"
                  @click.stop="openEdit(o)"
                >
                  <span v-if="!o.all_day" class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: dotColor(o) }" />
                  <span v-if="!o.all_day" class="shrink-0 tabular-nums text-[var(--ll-muted)]">{{ timeLabel(o) }}</span>
                  <span class="truncate">{{ o.summary || '—' }}</span>
                </button>
                <button v-if="cellEvents(day).length > 3" class="pl-1" @click.stop="openDay(ymd(day))">
                  <Badge tone="gray">{{ t('calendar.ui.more', { count: String(cellEvents(day).length - 3) }) }}</Badge>
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- WEEK -->
      <div v-else-if="view === 'week'" class="flex flex-1 flex-col overflow-hidden">
        <!-- One horizontal-scroll container keeps the weekday header, the all-day
             row and the hour grid locked to the SAME 8 tracks (gutter + 7 days). -->
        <div class="flex flex-1 flex-col overflow-x-auto">
          <div class="flex min-w-[720px] flex-1 flex-col">
            <!-- weekday header -->
            <div class="grid w-full shrink-0" :style="weekCols">
              <div class="grid place-items-center border-b border-[var(--ll-border)] text-[0.6rem] uppercase tracking-wide text-[var(--ll-muted)]">{{ t('calendar.ui.kw') }} {{ weekKw }}</div>
              <div
                v-for="day in weekDays" :key="'h' + ymd(day)"
                class="border-b border-l border-[var(--ll-border)] py-2 text-center text-xs"
                :class="isToday(day) ? 'font-semibold text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'"
              >
                <div>{{ day.toLocaleDateString(locale, { weekday: 'short' }) }}</div>
                <div class="text-sm">{{ day.getDate() }}</div>
              </div>
            </div>
            <!-- all-day row -->
            <div class="grid w-full shrink-0 border-b border-[var(--ll-border)]" :style="weekCols">
              <div class="py-1 pr-1 text-right text-[10px] uppercase text-[var(--ll-muted)]">{{ t('calendar.ui.all_day') }}</div>
              <div v-for="day in weekDays" :key="'ad' + ymd(day)" class="min-h-[28px] space-y-0.5 border-l border-[var(--ll-border)] p-0.5">
                <button
                  v-for="o in cellEvents(day).filter((e) => e.all_day)" :key="o.id + o.start"
                  class="w-full truncate rounded px-1 py-0.5 text-left text-[11px]"
                  :style="{ backgroundColor: chipBg(o) }"
                  @click.stop="openEdit(o)"
                >{{ o.summary || '—' }}</button>
              </div>
            </div>
            <!-- hour grid -->
            <div class="flex-1 overflow-y-auto">
              <div class="grid w-full" :style="weekCols">
                <div>
                  <div v-for="h in 24" :key="'hl' + h" class="h-12 pr-1 text-right text-[10px] text-[var(--ll-muted)]">{{ hourLabel(h - 1) }}</div>
                </div>
                <div v-for="day in weekDays" :key="'col' + ymd(day)" class="relative border-l border-[var(--ll-border)]" @click="openCreate(ymd(day) + 'T09:00')">
                  <div v-for="h in 24" :key="'row' + h" class="h-12 border-b border-[var(--ll-border)]" />
                  <button
                    v-for="o in cellEvents(day).filter((e) => !e.all_day)" :key="o.id + o.start"
                    class="absolute inset-x-0.5 overflow-hidden rounded px-1 text-left text-[11px] leading-tight ring-1 ring-inset ring-black/5"
                    :style="{ top: occTop(o) + 'px', height: occHeight(o) + 'px', backgroundColor: chipBg(o) }"
                    @click.stop="openEdit(o)"
                  >
                    <div class="truncate font-medium">{{ o.summary || '—' }}</div>
                    <div class="truncate text-[10px] text-[var(--ll-muted)]">{{ timeLabel(o) }}</div>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- AGENDA -->
      <div v-else class="flex-1 divide-y divide-[var(--ll-border)] overflow-y-auto">
        <div v-for="grp in agendaGroups" :key="grp.key" class="p-3">
          <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ grp.label }}</div>
          <button
            v-for="o in grp.events" :key="o.id + o.start"
            class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-black/[0.03] dark:hover:bg-white/5"
            @click="openEdit(o)"
          >
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: dotColor(o) }" />
            <span class="w-24 shrink-0 text-xs tabular-nums text-[var(--ll-muted)]">{{ o.all_day ? t('calendar.ui.all_day') : timeLabel(o) }}</span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium">{{ o.summary || '—' }}</span>
              <span v-if="o.location" class="block truncate text-xs text-[var(--ll-muted)]">{{ o.location }}</span>
            </span>
          </button>
        </div>
        <div v-if="!agendaGroups.length" class="grid place-items-center p-12 text-center text-sm text-[var(--ll-muted)]">
          <Icon name="event_busy" :size="34" class="mb-2 text-[var(--ll-muted)]" />
          {{ t('calendar.ui.no_events') }}
        </div>
      </div>
    </Card>
  </div>

  <!-- Event editor -->
  <Modal v-model="eventModal" :title="editingId ? t('calendar.ui.edit_event') : t('calendar.ui.new_event')" width="560px">
    <div class="space-y-4">
      <TextField v-model="form.summary" :label="t('calendar.ui.summary')" />
      <Select v-model="form.calendar_id" :label="t('calendar.ui.calendars')" :options="calendarOptions" />
      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.allDay" type="checkbox" class="h-4 w-4 rounded border-[var(--ll-border)] accent-[var(--color-primary-500)]" @change="onAllDayToggle">
        {{ t('calendar.ui.all_day') }}
      </label>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <TextField v-model="form.start" :label="t('calendar.ui.starts')" :type="form.allDay ? 'date' : 'datetime-local'" />
        <TextField v-model="form.end" :label="t('calendar.ui.ends')" :type="form.allDay ? 'date' : 'datetime-local'" />
      </div>
      <LocationField
        v-model="form.location"
        v-model:lat="form.geoLat"
        v-model:lon="form.geoLon"
        :label="t('calendar.ui.location')"
      />
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Select v-model="form.repeat" :label="t('calendar.ui.repeat')" :options="repeatOptions" />
        <Select v-model="form.status" :label="t('calendar.ui.status')" :options="statusOptions" />
        <Select v-model="form.reminder" :label="t('calendar.ui.reminder')" :options="reminderOptions" />
        <Select v-if="editingId && occRecurring" v-model="editScope" :label="t('calendar.ui.scope')" :options="scopeItems" />
      </div>
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('calendar.ui.description') }}</span>
        <textarea
          v-model="form.description" rows="3"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
        ></textarea>
      </label>
    </div>
    <template #footer>
      <Btn v-if="editingId" variant="danger" class="mr-auto" :loading="deleting" @click="onDelete">{{ t('calendar.ui.delete_event') }}</Btn>
      <Btn variant="ghost" @click="eventModal = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="saving" :disabled="!form.calendar_id || !form.start" @click="save">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <!-- Calendar editor -->
  <Modal v-model="calModal" :title="calEditingId ? t('calendar.ui.rename_calendar') : t('calendar.ui.new_calendar')" width="420px">
    <div class="space-y-4">
      <TextField v-model="calForm.name" :label="t('calendar.ui.calendar_name')" />
      <div>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('calendar.ui.color') }}</span>
        <input v-model="calForm.color" type="color" class="h-9 w-16 cursor-pointer rounded border border-[var(--ll-border)] bg-transparent">
      </div>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="calModal = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="calSaving" :disabled="!calForm.name" @click="saveCalendar">{{ t('common.save') }}</Btn>
    </template>
  </Modal>

  <!-- Import -->
  <Modal v-model="importModal" :title="t('calendar.ui.import')" width="460px">
    <div class="space-y-4">
      <Select v-model="importCalId" :label="t('calendar.ui.calendars')" :options="calendarOptions" />
      <label class="block">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('calendar.ui.import') }}</span>
        <input
          type="file" accept=".ics,text/calendar"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm text-[var(--ll-fg)] file:mr-3 file:rounded-md file:border-0 file:bg-primary-500/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-600 dark:file:text-primary-300"
          @change="onImportFile"
        >
      </label>
    </div>
    <template #footer>
      <Btn variant="ghost" @click="importModal = false">{{ t('common.cancel') }}</Btn>
      <Btn variant="solid" :loading="importing" :disabled="!importFile || !importCalId" @click="runImport">{{ t('calendar.ui.import') }}</Btn>
    </template>
  </Modal>

  <!-- Day overflow -->
  <Modal v-model="dayModal" :title="dayModalLabel" width="420px">
    <div class="space-y-1">
      <button
        v-for="o in dayModalEvents" :key="o.id + o.start"
        class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-black/[0.03] dark:hover:bg-white/5"
        @click="openEdit(o); dayModal = false"
      >
        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: dotColor(o) }" />
        <span class="w-20 shrink-0 text-xs tabular-nums text-[var(--ll-muted)]">{{ o.all_day ? t('calendar.ui.all_day') : timeLabel(o) }}</span>
        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ o.summary || '—' }}</span>
      </button>
      <div v-if="!dayModalEvents.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('calendar.ui.no_events') }}</div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField, Select, Badge, Modal } from '@spa/ui';
import { api, ApiError } from '@spa/api/client';
import { useCalendarStore, type CalendarCol, type Occurrence } from '@spa/stores/calendar';
import LocationField from '@spa/components/LocationField.vue';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';

const store = useCalendarStore();
const { success, error } = useToast();
const locale = document.documentElement.lang || 'de';

type View = 'month' | 'week' | 'agenda';
const views: View[] = ['month', 'week', 'agenda'];

const cursor = ref<Date>(startOfDay(new Date()));
const view = ref<View>('month');
const loading = ref(false);
const activeCalendars = ref<Set<string>>(new Set());
const todayKey = ymd(new Date());

// --- date helpers (no lib) --------------------------------------------------
function pad(n: number): string { return String(n).padStart(2, '0'); }
function ymd(d: Date): string { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }
function startOfDay(d: Date): Date { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
function addDays(d: Date, n: number): Date { const r = startOfDay(d); r.setDate(r.getDate() + n); return r; }
function addMonths(d: Date, n: number): Date { return new Date(d.getFullYear(), d.getMonth() + n, 1); }
function startOfMonth(d: Date): Date { return new Date(d.getFullYear(), d.getMonth(), 1); }
function endOfMonth(d: Date): Date { return new Date(d.getFullYear(), d.getMonth() + 1, 0); }
function startOfWeek(d: Date, ws: number): Date { const s = startOfDay(d); const shift = (s.getDay() - ws + 7) % 7; return addDays(s, -shift); }
function endOfWeek(d: Date, ws: number): Date { return addDays(startOfWeek(d, ws), 6); }
function eachDayFrom(a: Date, b: Date): Date[] { const out: Date[] = []; let cur = startOfDay(a); const end = startOfDay(b); while (cur <= end) { out.push(cur); cur = addDays(cur, 1); } return out; }
function parseKey(k: string): Date { const p = k.split('-').map(Number); return new Date(p[0], (p[1] || 1) - 1, p[2] || 1); }
// ISO-8601 week number (weeks start Monday; week 1 holds the first Thursday).
function isoWeek(d: Date): number {
  const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
  const day = t.getUTCDay() || 7; // Sunday → 7
  t.setUTCDate(t.getUTCDate() + 4 - day); // shift to the week's Thursday
  const yearStart = new Date(Date.UTC(t.getUTCFullYear(), 0, 1));
  return Math.ceil(((t.getTime() - yearStart.getTime()) / 86_400_000 + 1) / 7);
}

const weekStart = computed<number>(() => store.settings.week_start);

const monthDays = computed<Date[]>(() => eachDayFrom(startOfWeek(startOfMonth(cursor.value), weekStart.value), endOfWeek(endOfMonth(cursor.value), weekStart.value)));
const weekStartDay = computed<Date>(() => startOfWeek(cursor.value, weekStart.value));
const weekDays = computed<Date[]>(() => eachDayFrom(weekStartDay.value, addDays(weekStartDay.value, 6)));
// Explicit grid templates so month (7 equal cols) and week (time gutter + 7
// equal cols) render correctly regardless of utility-class generation order.
// Month: a leading KW (week-number) gutter + 7 equal day columns. Week: a time
// gutter + 7 equal columns. Explicit so the grid renders regardless of utility
// class-generation order.
const monthCols = { gridTemplateColumns: '3rem repeat(7, minmax(0, 1fr))' };
const weekCols = { gridTemplateColumns: '4rem repeat(7, minmax(0, 1fr))' };

// Index (within a display week row) of the Monday — anchors the ISO week number
// regardless of whether the week starts on Sunday (0) or Monday (1).
const mondayIndex = computed<number>(() => (1 - weekStart.value + 7) % 7);
function rowKw(days: Date[]): number { return isoWeek(days[mondayIndex.value] ?? days[0]); }

// 6 week-rows for the month grid, each carrying its ISO week number for the KW gutter.
const monthWeeks = computed<{ key: string; kw: number; days: Date[] }[]>(() => {
  const out: { key: string; kw: number; days: Date[] }[] = [];
  const all = monthDays.value;
  for (let i = 0; i < all.length; i += 7) {
    const days = all.slice(i, i + 7);
    out.push({ key: ymd(days[0]), kw: rowKw(days), days });
  }
  return out;
});
const weekKw = computed<number>(() => rowKw(weekDays.value));

const weekdayLabels = computed<string[]>(() => {
  const base = new Date(2023, 0, 1); // Sunday
  const out: string[] = [];
  for (let i = 0; i < 7; i++) out.push(addDays(base, weekStart.value + i).toLocaleDateString(locale, { weekday: 'short' }));
  return out;
});

function currentWindow(): { from: Date; to: Date } {
  if (view.value === 'month') return { from: startOfWeek(startOfMonth(cursor.value), weekStart.value), to: addDays(endOfWeek(endOfMonth(cursor.value), weekStart.value), 1) };
  if (view.value === 'week') return { from: weekStartDay.value, to: addDays(weekStartDay.value, 7) };
  const s = startOfDay(cursor.value);
  return { from: s, to: addDays(s, 30) };
}

const headerLabel = computed<string>(() => {
  if (view.value === 'month') return cursor.value.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
  const w = view.value === 'week' ? { s: weekStartDay.value, e: addDays(weekStartDay.value, 6) } : { s: startOfDay(cursor.value), e: addDays(startOfDay(cursor.value), 29) };
  return `${w.s.toLocaleDateString(locale, { day: 'numeric', month: 'short' })} – ${w.e.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' })}`;
});

// --- occurrence indexing ----------------------------------------------------
const visibleEvents = computed<Occurrence[]>(() => store.events.filter((o) => activeCalendars.value.has(o.calendar)));

function occRange(o: Occurrence): { first: string; last: string } {
  if (o.all_day) {
    const first = o.start.slice(0, 10);
    let last = first;
    if (o.end) {
      const ek = o.end.slice(0, 10);
      if (ek > first) { const d = parseKey(ek); d.setDate(d.getDate() - 1); last = ymd(d); if (last < first) last = first; }
    }
    return { first, last };
  }
  const s = new Date(o.start);
  const e = o.end ? new Date(o.end) : s;
  return { first: ymd(s), last: ymd(e) };
}
function sortOcc(a: Occurrence, b: Occurrence): number { if (a.all_day !== b.all_day) return a.all_day ? -1 : 1; return a.start.localeCompare(b.start); }

const eventsByDay = computed<Map<string, Occurrence[]>>(() => {
  const map = new Map<string, Occurrence[]>();
  for (const o of visibleEvents.value) {
    const r = occRange(o);
    let cur = parseKey(r.first);
    const end = parseKey(r.last);
    let guard = 0;
    while (cur <= end && guard < 400) {
      const k = ymd(cur);
      let list = map.get(k);
      if (!list) { list = []; map.set(k, list); }
      list.push(o);
      cur = addDays(cur, 1);
      guard++;
    }
  }
  for (const list of map.values()) list.sort(sortOcc);
  return map;
});
function cellEvents(day: Date): Occurrence[] { return eventsByDay.value.get(ymd(day)) ?? []; }

const agendaGroups = computed<{ key: string; label: string; events: Occurrence[] }[]>(() => {
  const w = currentWindow();
  const out: { key: string; label: string; events: Occurrence[] }[] = [];
  for (const day of eachDayFrom(w.from, addDays(w.to, -1))) {
    const ev = eventsByDay.value.get(ymd(day));
    if (ev && ev.length) out.push({ key: ymd(day), label: day.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' }), events: ev });
  }
  return out;
});

// --- rendering helpers ------------------------------------------------------
function inMonth(day: Date): boolean { return day.getMonth() === cursor.value.getMonth() && day.getFullYear() === cursor.value.getFullYear(); }
function isToday(day: Date): boolean { return ymd(day) === todayKey; }
function timeLabel(o: Occurrence): string { return new Date(o.start).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }); }
function hourLabel(h: number): string { return `${pad(h)}:00`; }
function occTop(o: Occurrence): number { const d = new Date(o.start); return (d.getHours() + d.getMinutes() / 60) * 48; }
function occHeight(o: Occurrence): number { const s = new Date(o.start); const e = o.end ? new Date(o.end) : new Date(s.getTime() + 3_600_000); let h = (e.getTime() - s.getTime()) / 3_600_000; if (h < 0.5) h = 0.5; return h * 48; }
function calColor(id: string): string | null { return store.calendars.find((c) => c.id === id)?.color ?? null; }
function dotColor(o: Occurrence): string { return o.color || calColor(o.calendar) || '#6750a4'; }
function chipBg(o: Occurrence): string { const h = dotColor(o); return h.length === 7 ? h + '26' : h; }

// --- calendar visibility ----------------------------------------------------
function isVisible(id: string): boolean { return activeCalendars.value.has(id); }
function toggleCal(id: string): void { if (activeCalendars.value.has(id)) activeCalendars.value.delete(id); else activeCalendars.value.add(id); }

// --- navigation -------------------------------------------------------------
function goPrev(): void { cursor.value = view.value === 'month' ? addMonths(cursor.value, -1) : view.value === 'week' ? addDays(cursor.value, -7) : addDays(cursor.value, -30); }
function goNext(): void { cursor.value = view.value === 'month' ? addMonths(cursor.value, 1) : view.value === 'week' ? addDays(cursor.value, 7) : addDays(cursor.value, 30); }
function goToday(): void { cursor.value = startOfDay(new Date()); }
function setView(v: View): void { view.value = v; store.settings.default_view = v; store.saveSettings(store.settings).catch(() => { /* non-fatal */ }); }

async function reloadRange(): Promise<void> {
  loading.value = true;
  try {
    const w = currentWindow();
    await store.loadRange(w.from.toISOString(), w.to.toISOString());
  } catch { error(t('common.error')); } finally { loading.value = false; }
}
watch([cursor, view], () => { reloadRange(); });

// --- event editor -----------------------------------------------------------
const eventModal = ref(false);
const editingId = ref<string | null>(null);
const currentEtag = ref<string>('');
// Per-occurrence editing of a recurring series: scope = whole series vs the one
// clicked occurrence (RECURRENCE-ID override / EXDATE on save/delete).
const occRecurring = ref(false);
const occStart = ref(''); // the clicked occurrence's original start (RECURRENCE-ID)
const occEnd = ref('');
const masterStart = ref('');
const masterEnd = ref('');
const editScope = ref<'series' | 'occurrence'>('series');
const scopeItems = computed(() => [
  { title: t('calendar.ui.scope_series'), value: 'series' },
  { title: t('calendar.ui.scope_occurrence'), value: 'occurrence' },
]);
// Swap the shown start/end between the series master and the clicked occurrence.
watch(editScope, (scope) => {
  if (!occRecurring.value) return;
  if (scope === 'occurrence') {
    form.start = toInput(occStart.value, form.allDay);
    form.end = occEnd.value ? (form.allDay ? shiftDay(toInput(occEnd.value, true), -1) : toInput(occEnd.value, false)) : '';
  } else {
    form.start = masterStart.value;
    form.end = masterEnd.value;
  }
});
const saving = ref(false);
const deleting = ref(false);
const form = reactive<{ calendar_id: string; summary: string; description: string; location: string; geoLat: number | null; geoLon: number | null; allDay: boolean; start: string; end: string; repeat: string; rruleRaw: string; status: string; reminder: string }>(
  { calendar_id: '', summary: '', description: '', location: '', geoLat: null, geoLon: null, allDay: false, start: '', end: '', repeat: 'none', rruleRaw: '', status: 'CONFIRMED', reminder: '' },
);
// Reminder presets (minutes before start; '' = none). Shared with Tasks.vue keys.
const reminderOptions = computed(() => [
  { title: t('calendar.ui.reminder_none'), value: '' },
  { title: t('calendar.ui.reminder_at'), value: '0' },
  { title: t('calendar.ui.reminder_5m'), value: '5' },
  { title: t('calendar.ui.reminder_15m'), value: '15' },
  { title: t('calendar.ui.reminder_30m'), value: '30' },
  { title: t('calendar.ui.reminder_1h'), value: '60' },
  { title: t('calendar.ui.reminder_1d'), value: '1440' },
]);
// Only normal calendars can hold user-created events; special ones are generated.
const editableCalendars = computed(() => store.calendars.filter((c) => c.kind === 'normal'));
const calendarOptions = computed(() => editableCalendars.value.map((c) => ({ title: c.name, value: c.id })));
function isSpecialCal(id: string): boolean { return store.calendars.find((c) => c.id === id)?.kind !== 'normal' && store.calendars.some((c) => c.id === id); }
const repeatOptions = computed(() => [
  { title: t('calendar.ui.repeat_none'), value: 'none' },
  { title: t('calendar.ui.repeat_daily'), value: 'daily' },
  { title: t('calendar.ui.repeat_weekly'), value: 'weekly' },
  { title: t('calendar.ui.repeat_monthly'), value: 'monthly' },
  { title: t('calendar.ui.repeat_yearly'), value: 'yearly' },
]);
const statusOptions = computed(() => [
  { title: t('calendar.ui.status_confirmed'), value: 'CONFIRMED' },
  { title: t('calendar.ui.status_tentative'), value: 'TENTATIVE' },
  { title: t('calendar.ui.status_cancelled'), value: 'CANCELLED' },
]);

function presetToRrule(p: string): string | null {
  const m: Record<string, string> = { daily: 'FREQ=DAILY', weekly: 'FREQ=WEEKLY', monthly: 'FREQ=MONTHLY', yearly: 'FREQ=YEARLY' };
  return m[p] ?? null;
}
function rruleToPreset(r: string | null): string {
  if (!r) return 'none';
  const up = r.toUpperCase();
  for (const f of ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']) if (up.includes('FREQ=' + f)) return f.toLowerCase();
  return 'none';
}
function toInput(iso: string, allDay: boolean): string {
  if (allDay) return iso.slice(0, 10);
  const d = new Date(iso);
  return `${ymd(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
// datetime-local values are naive *local* time; the API stores/returns UTC 'Z'.
// Convert on write so the round-trip doesn't shift by the browser's offset.
function localToIso(v: string): string { return new Date(v).toISOString(); }
// All-day DTEND is exclusive (RFC 5545) but the editor shows an inclusive last
// day; shift by ±1 day between the two representations.
function shiftDay(dateStr: string, days: number): string { const d = parseKey(dateStr); d.setDate(d.getDate() + days); return ymd(d); }
function onAllDayToggle(): void {
  if (form.allDay) {
    form.start = form.start.slice(0, 10);
    form.end = form.end ? form.end.slice(0, 10) : form.start;
  } else {
    if (form.start.length === 10) form.start += 'T09:00';
    if (!form.end || form.end.length === 10) form.end = form.start.slice(0, 10) + 'T10:00';
  }
}

function openNewEvent(): void { openCreate(ymd(cursor.value) + 'T09:00'); }
function openCreate(startVal: string): void {
  editingId.value = null;
  currentEtag.value = '';
  const allDay = startVal.length === 10;
  const dayPart = startVal.slice(0, 10);
  Object.assign(form, {
    calendar_id: editableCalendars.value[0]?.id ?? '',
    summary: '', description: '', location: '', geoLat: null, geoLon: null,
    allDay,
    start: startVal,
    end: allDay ? dayPart : dayPart + 'T10:00',
    repeat: 'none', rruleRaw: '', status: 'CONFIRMED', reminder: '',
  });
  eventModal.value = true;
}
async function openEdit(o: Occurrence): Promise<void> {
  // Generated events (holidays/birthdays) are read-only.
  if (isSpecialCal(o.calendar)) { error(t('calendar.ui.special_readonly')); return; }
  try {
    const d = await store.show(o.id);
    editingId.value = d.id;
    currentEtag.value = d.etag;
    Object.assign(form, {
      calendar_id: d.calendar,
      summary: d.summary ?? '', description: d.description ?? '', location: d.location ?? '',
      geoLat: coord(d.geo_lat), geoLon: coord(d.geo_lon),
      allDay: d.all_day,
      start: toInput(d.dtstart, d.all_day),
      // All-day DTEND is exclusive on the wire → show the inclusive last day.
      end: d.dtend ? (d.all_day ? shiftDay(toInput(d.dtend, true), -1) : toInput(d.dtend, false)) : '',
      repeat: rruleToPreset(d.rrule), rruleRaw: d.rrule ?? '', status: d.status ?? 'CONFIRMED',
      reminder: d.alarm_minutes_before != null ? String(d.alarm_minutes_before) : '',
    });
    // Remember both anchors so the scope toggle can swap the shown times.
    masterStart.value = form.start;
    masterEnd.value = form.end;
    occRecurring.value = o.recurring;
    occStart.value = o.start;
    occEnd.value = o.end ?? '';
    editScope.value = 'series';
    eventModal.value = true;
  } catch { error(t('common.error')); }
}
// Decimals may arrive as strings (Laravel decimal cast) — normalise to a
// finite number or null for the editor + the map.
function coord(v: number | string | null): number | null {
  if (v === null || v === '') return null;
  const n = typeof v === 'number' ? v : Number(v);
  return Number.isFinite(n) ? n : null;
}
function buildBody(): Record<string, unknown> {
  const dtstart = form.allDay ? form.start.slice(0, 10) : localToIso(form.start);
  // All-day: editor end is inclusive → store exclusive (+1 day). Timed: local→UTC.
  const dtend = form.end
    ? (form.allDay ? shiftDay(form.end.slice(0, 10), 1) : localToIso(form.end))
    : null;
  // Preserve a full RRULE (INTERVAL/UNTIL/COUNT/BYDAY) when the user didn't
  // change the recurrence dropdown; otherwise emit the chosen bare preset.
  const raw = form.rruleRaw.trim();
  const rrule = raw && rruleToPreset(raw) === form.repeat ? raw : presetToRrule(form.repeat);
  return {
    calendar_id: form.calendar_id,
    summary: form.summary,
    description: form.description,
    location: form.location,
    geo_lat: form.geoLat,
    geo_lon: form.geoLon,
    all_day: form.allDay,
    dtstart,
    dtend,
    rrule,
    status: form.status,
    alarm_minutes_before: form.reminder === '' ? null : Number(form.reminder),
  };
}
async function save(): Promise<void> {
  saving.value = true;
  try {
    const body = buildBody();
    if (editingId.value) {
      if (occRecurring.value && editScope.value === 'occurrence') {
        await store.overrideOccurrence(editingId.value, occStart.value, body);
      } else {
        body.etag = currentEtag.value;
        await store.update(editingId.value, body);
      }
    } else {
      await store.create(body);
    }
    eventModal.value = false;
    await reloadRange();
    success(t('common.saved'));
  } catch (e) {
    if (e instanceof ApiError && e.status === 409 && editingId.value) {
      try { const fresh = await store.show(editingId.value); currentEtag.value = fresh.etag; } catch { /* ignore */ }
      await reloadRange();
    }
    error(t('common.error'));
  } finally { saving.value = false; }
}
async function onDelete(): Promise<void> {
  if (!editingId.value) return;
  const perOcc = occRecurring.value && editScope.value === 'occurrence';
  if (!await confirmAsk(perOcc ? t('calendar.ui.delete_occurrence_confirm') : t('calendar.ui.delete_confirm'), { danger: true })) return;
  deleting.value = true;
  try {
    if (perOcc) await store.excludeOccurrence(editingId.value, occStart.value);
    else await store.destroy(editingId.value);
    eventModal.value = false;
    await reloadRange();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { deleting.value = false; }
}

// --- calendar editor (NORMAL calendars only) --------------------------------
// Special calendars (birthdays/holidays/school holidays) are created from the
// calendar settings page (profile/Calendar.vue), not here.
const calModal = ref(false);
const calEditingId = ref<string | null>(null);
const calSaving = ref(false);
const calForm = reactive<{ name: string; color: string }>({ name: '', color: '#6750a4' });

function openNewCalendar(): void { calEditingId.value = null; calForm.name = ''; calForm.color = '#6750a4'; calModal.value = true; }
function openEditCalendar(c: CalendarCol): void { calEditingId.value = c.id; calForm.name = c.name; calForm.color = c.color || '#6750a4'; calModal.value = true; }
async function saveCalendar(): Promise<void> {
  if (!calForm.name) return;
  calSaving.value = true;
  try {
    if (calEditingId.value) {
      await store.updateCalendar(calEditingId.value, { name: calForm.name, color: calForm.color });
    } else {
      const r = await store.createCalendar(calForm.name, calForm.color);
      if (r?.id) activeCalendars.value.add(r.id);
    }
    calModal.value = false;
    await store.loadData();
    await reloadRange();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { calSaving.value = false; }
}
async function removeCalendar(c: CalendarCol): Promise<void> {
  if (!await confirmAsk(t('calendar.ui.delete_calendar'), { danger: true })) return;
  try {
    await store.deleteCalendar(c.id);
    activeCalendars.value.delete(c.id);
    await store.loadData();
    await reloadRange();
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) error(t('calendar.ui.keep_one_calendar'));
    else error(t('common.error'));
  }
}

// --- import / export --------------------------------------------------------
const importModal = ref(false);
const importCalId = ref('');
const importFile = ref<File | null>(null);
const importing = ref(false);
const exportHref = computed(() => api.streamUrl(store.exportUrl()));
function openImport(): void { importFile.value = null; importCalId.value = store.calendars[0]?.id ?? ''; importModal.value = true; }
function onImportFile(ev: Event): void { const input = ev.target as HTMLInputElement; importFile.value = input.files?.[0] ?? null; }
async function runImport(): Promise<void> {
  if (!importFile.value || !importCalId.value) return;
  importing.value = true;
  try {
    const r = await store.importIcs(importFile.value, importCalId.value);
    importModal.value = false;
    await reloadRange();
    success(t('calendar.ui.import_done', { created: String(r.created), updated: String(r.updated), skipped: String(r.skipped) }));
  } catch { error(t('common.error')); } finally { importing.value = false; }
}

// --- day overflow -----------------------------------------------------------
const dayModal = ref(false);
const dayModalKey = ref('');
const dayModalEvents = computed<Occurrence[]>(() => eventsByDay.value.get(dayModalKey.value) ?? []);
const dayModalLabel = computed<string>(() => (dayModalKey.value ? parseKey(dayModalKey.value).toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' }) : ''));
function openDay(key: string): void { dayModalKey.value = key; dayModal.value = true; }

onMounted(async () => {
  try {
    await store.loadData();
    activeCalendars.value = new Set(store.calendars.map((c) => c.id));
    view.value = store.settings.default_view;
  } catch { /* ignore */ }
  await reloadRange();
});
</script>
