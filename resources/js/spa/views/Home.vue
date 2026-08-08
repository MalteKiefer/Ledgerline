<template>
  <div>
    <h1 class="text-h5 mb-4">{{ t('messages.nav.finance') }}</h1>

    <v-row v-if="kpis">
      <v-col cols="12" sm="4">
        <v-card rounded="xl" border flat class="pa-4">
          <div class="text-caption text-medium-emphasis text-uppercase">{{ t('invoices.revenue') }} {{ kpis.year }}</div>
          <div class="text-h5 mt-1">{{ money(kpis.net) }}</div>
          <div v-if="kpis.growthPct != null" class="text-caption" :class="kpis.growthPct >= 0 ? 'text-success' : 'text-error'">
            {{ kpis.growthPct >= 0 ? '+' : '' }}{{ kpis.growthPct }}%
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4">
        <v-card rounded="xl" border flat class="pa-4">
          <div class="text-caption text-medium-emphasis text-uppercase">{{ t('invoices.status_open') }}</div>
          <div class="text-h5 mt-1 text-warning">{{ money(openGross) }}</div>
          <div class="text-caption text-medium-emphasis">{{ openCount }} {{ t('invoices.tab_invoices') }}</div>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4">
        <v-card rounded="xl" border flat class="pa-4">
          <div class="text-caption text-medium-emphasis text-uppercase">{{ t('invoices.vat_payable') }}</div>
          <div class="text-h5 mt-1 text-primary">{{ money(vatPayable) }}</div>
        </v-card>
      </v-col>
    </v-row>
    <v-skeleton-loader v-else type="card" />

    <h2 class="text-subtitle-1 mt-6 mb-2">{{ t('common.open') }}</h2>
    <v-row>
      <v-col v-for="n in nav" :key="n.to" cols="6" sm="3">
        <v-card rounded="xl" border flat :to="n.to" class="pa-4 d-flex flex-column align-center text-center m3-hover">
          <span class="msym text-primary" style="font-size:32px">{{ n.icon }}</span>
          <span class="text-body-2 mt-2">{{ t(n.label) }}</span>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';

interface Kpis { year: number; net: number; count: number; growthPct: number | null }
interface Aging { openGross: number; openCount: number }
interface Vat { payable?: number }
interface Reports { kpis: Kpis; aging: Aging; currentVat: Vat }

const kpis = ref<Kpis | null>(null);
const openGross = ref(0);
const openCount = ref(0);
const vatPayable = ref(0);

const nav = [
  { to: '/finance', icon: 'receipt_long', label: 'invoices.tab_invoices' },
  { to: '/files', icon: 'folder', label: 'messages.nav.files' },
  { to: '/contacts', icon: 'contacts', label: 'messages.nav.contacts' },
  { to: '/settings', icon: 'settings', label: 'pages.settings.title' },
];

onMounted(async () => {
  try {
    const r = await api.get<Reports>('/api/v1/finance/reports');
    kpis.value = r.kpis;
    openGross.value = r.aging?.openGross ?? 0;
    openCount.value = r.aging?.openCount ?? 0;
    vatPayable.value = r.currentVat?.payable ?? 0;
  } catch { /* finance module may be disabled */ }
});

const fmt = computed(() => new Intl.NumberFormat(document.documentElement.lang || 'de', { style: 'currency', currency: 'EUR' }));
function money(n: number) { return fmt.value.format(n || 0); }
</script>

<style scoped>
.m3-hover { transition: box-shadow 0.15s; }
.m3-hover:hover { box-shadow: 0 2px 8px rgb(0 0 0 / 0.12); }
</style>
