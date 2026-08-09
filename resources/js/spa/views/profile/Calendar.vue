<template>
  <Card :title="t('messages.nav.calendar')">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Select v-model="defaultView" :label="t('calendar.ui.default_view')" :options="viewItems" @update:modelValue="onSave" />
      <Select v-model="weekStart" :label="t('calendar.ui.week_start')" :options="weekStartItems" @update:modelValue="onSave" />
    </div>
  </Card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Select } from '@spa/ui';
import { useCalendarStore, type CalSettings } from '@spa/stores/calendar';
import { useToast } from '@spa/composables/useToast';

const store = useCalendarStore();
const { success, error } = useToast();

// String-backed models: the <select> always emits a string.
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

onMounted(async () => {
  try {
    await store.loadData();
    defaultView.value = store.settings.default_view;
    weekStart.value = String(store.settings.week_start) === '0' ? '0' : '1';
  } catch { /* non-fatal — keep defaults */ }
});

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
</script>
