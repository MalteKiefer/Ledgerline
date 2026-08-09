<template>
  <Card :title="t('messages.nav.calendar')">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Select v-model="defaultView" :label="t('calendar.ui.default_view')" :options="viewItems" @update:modelValue="onSave" />
      <Select v-model="weekStart" :label="t('calendar.ui.week_start')" :options="weekStartItems" @update:modelValue="onSave" />
    </div>
  </Card>

  <Card :title="t('calendar.ui.special_calendars')" class="mt-4 w-full">
    <div class="space-y-6">
      <!-- Birthdays: one-click add (predefined name), or regenerate if it exists -->
      <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-[140px] flex-1">
          <div class="text-sm font-medium">{{ t('calendar.ui.type_birthdays') }}</div>
          <div class="text-xs text-[var(--ll-muted)]">{{ t('calendar.ui.name_birthdays') }}</div>
        </div>
        <Btn v-if="!birthdaysCal" variant="solid" size="sm" icon="add" :loading="busy === 'birthdays'" @click="addBirthdays">{{ t('common.add') }}</Btn>
        <Btn v-else variant="outline" size="sm" icon="refresh" :loading="busy === birthdaysCal.id" @click="regenerate(birthdaysCal)">{{ t('calendar.ui.regenerate') }}</Btn>
      </div>

      <!-- Public holidays: country + region + add -->
      <div class="border-t border-[var(--ll-border)] pt-4">
        <div class="mb-2 text-sm font-medium">{{ t('calendar.ui.type_holidays') }}</div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
          <Select v-model="holCountry" :label="t('calendar.ui.country')" :options="countryOptions" />
          <Select v-model="holRegion" :label="t('calendar.ui.region')" :options="regionOptions(holSubs)" />
          <Btn variant="solid" size="sm" icon="add" :loading="busy === 'holidays'" @click="addHolidays">{{ t('common.add') }}</Btn>
        </div>
      </div>

      <!-- School holidays: country + region + add -->
      <div class="border-t border-[var(--ll-border)] pt-4">
        <div class="mb-2 text-sm font-medium">{{ t('calendar.ui.type_school_holidays') }}</div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
          <Select v-model="schCountry" :label="t('calendar.ui.country')" :options="countryOptions" />
          <Select v-model="schRegion" :label="t('calendar.ui.region')" :options="regionOptions(schSubs)" />
          <Btn variant="solid" size="sm" icon="add" :loading="busy === 'school_holidays'" @click="addSchool">{{ t('common.add') }}</Btn>
        </div>
      </div>

      <!-- Existing special calendars: regenerate / delete -->
      <div v-if="specialCalendars.length" class="border-t border-[var(--ll-border)] pt-4">
        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-[var(--ll-muted)]">{{ t('calendar.ui.calendars') }}</div>
        <div v-for="cal in specialCalendars" :key="cal.id" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-black/[0.03] dark:hover:bg-white/5">
          <span class="h-3.5 w-3.5 shrink-0 rounded-[4px]" :style="{ backgroundColor: cal.color || '#6750a4' }" />
          <span class="min-w-0 flex-1 truncate text-sm">{{ cal.name }}</span>
          <Btn variant="ghost" size="sm" icon="refresh" :loading="busy === cal.id" :title="t('calendar.ui.regenerate')" @click="regenerate(cal)" />
          <Btn variant="ghost" size="sm" icon="delete" :title="t('calendar.ui.delete_calendar')" @click="removeSpecial(cal)" />
        </div>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Select, Btn } from '@spa/ui';
import { useCalendarStore, type CalSettings, type CalendarCol, type HolidayCountry, type HolidaySubdivision } from '@spa/stores/calendar';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';

const store = useCalendarStore();
const { success, error } = useToast();

// --- default view / week start (string-backed: the <select> emits a string) --
const defaultView = ref<CalSettings['default_view']>('month');
const weekStart = ref<'0' | '1'>('1');

const viewItems = [
  { title: t('calendar.ui.view_month'), value: 'month' },
  { title: t('calendar.ui.view_week'), value: 'week' },
  { title: t('calendar.ui.view_agenda'), value: 'agenda' },
];
const weekStartItems = [
  { title: t('calendar.ui.week_start_monday'), value: '1' },
  { title: t('calendar.ui.week_start_sunday'), value: '0' },
];

async function onSave() {
  const payload: CalSettings = {
    default_view: defaultView.value,
    week_start: weekStart.value === '0' ? 0 : 1,
  };
  try {
    await store.saveSettings(payload);
    store.settings = payload; // keep the shared store in sync (calendar view reads it)
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  }
}

// --- special calendars ------------------------------------------------------
// Names are PREDEFINED (built here from the localized base + region/country) so
// the user never types a name. createSpecial populates the calendar server-side.
const busy = ref(''); // id or kind of the running action, to disable that button

const specialCalendars = computed<CalendarCol[]>(() => store.calendars.filter((c) => c.kind !== 'normal'));
const birthdaysCal = computed<CalendarCol | null>(() => store.calendars.find((c) => c.kind === 'birthdays') ?? null);

// Shared OpenHolidays country list + per-kind region (subdivision) selection.
const holidayCountries = ref<HolidayCountry[]>([]);
const countryOptions = computed(() => holidayCountries.value.map((c) => ({ title: c.name, value: c.isoCode })));

const holCountry = ref('DE');
const holSubs = ref<HolidaySubdivision[]>([]);
const holRegion = ref('');
const schCountry = ref('DE');
const schSubs = ref<HolidaySubdivision[]>([]);
const schRegion = ref('');

function regionOptions(subs: HolidaySubdivision[]) {
  return [{ title: t('calendar.ui.region_all'), value: '' }, ...subs.map((s) => ({ title: s.name, value: s.code }))];
}
function countryLabel(iso: string): string { return holidayCountries.value.find((c) => c.isoCode === iso)?.name || iso; }
function regionLabel(subs: HolidaySubdivision[], code: string): string { return subs.find((s) => s.code === code)?.name || ''; }

// Predefined name: localized base + " · " + region (or country) for holiday kinds.
function holidayName(baseKey: string, subs: HolidaySubdivision[], country: string, region: string): string {
  const label = regionLabel(subs, region) || countryLabel(country);
  return `${t(baseKey)} · ${label}`;
}

async function ensureCountries(): Promise<void> {
  if (holidayCountries.value.length) return;
  try { holidayCountries.value = await store.loadHolidayCountries(); } catch { /* keep DE default */ }
}
async function loadSubs(country: string): Promise<HolidaySubdivision[]> {
  if (!country) return [];
  try { return await store.loadHolidaySubdivisions(country); } catch { return []; /* region list is optional */ }
}
// Changing a country reloads its regions and resets the selected region to national.
watch(holCountry, async (c) => { holRegion.value = ''; holSubs.value = await loadSubs(c); });
watch(schCountry, async (c) => { schRegion.value = ''; schSubs.value = await loadSubs(c); });

async function addBirthdays(): Promise<void> {
  busy.value = 'birthdays';
  try {
    await store.createSpecial('birthdays', { name: t('calendar.ui.name_birthdays') });
    await store.loadData();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { busy.value = ''; }
}
async function addHolidays(): Promise<void> {
  busy.value = 'holidays';
  try {
    await store.createSpecial('holidays', {
      name: holidayName('calendar.ui.name_holidays', holSubs.value, holCountry.value, holRegion.value),
      country: holCountry.value,
      subdivision: holRegion.value || undefined,
    });
    await store.loadData();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { busy.value = ''; }
}
async function addSchool(): Promise<void> {
  busy.value = 'school_holidays';
  try {
    await store.createSpecial('school_holidays', {
      name: holidayName('calendar.ui.name_school_holidays', schSubs.value, schCountry.value, schRegion.value),
      country: schCountry.value,
      subdivision: schRegion.value || undefined,
    });
    await store.loadData();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { busy.value = ''; }
}
async function regenerate(cal: CalendarCol): Promise<void> {
  busy.value = cal.id;
  try {
    await store.regenerate(cal.id);
    success(t('calendar.ui.regenerate_done'));
  } catch { error(t('common.error')); } finally { busy.value = ''; }
}
async function removeSpecial(cal: CalendarCol): Promise<void> {
  if (!await confirmAsk(t('calendar.ui.delete_calendar'), { danger: true })) return;
  busy.value = cal.id;
  try {
    await store.deleteCalendar(cal.id);
    await store.loadData();
  } catch { error(t('common.error')); } finally { busy.value = ''; }
}

onMounted(async () => {
  try {
    await store.loadData();
    defaultView.value = store.settings.default_view;
    weekStart.value = String(store.settings.week_start) === '0' ? '0' : '1';
  } catch { /* non-fatal — keep defaults */ }
  await ensureCountries();
  holSubs.value = await loadSubs(holCountry.value);
  schSubs.value = await loadSubs(schCountry.value);
});
</script>
