<template>
  <div>
    <v-card rounded="lg" flat class="ll-hero mb-6 pa-6 d-flex align-center">
      <div>
        <div class="text-h5 font-weight-bold mb-1">{{ t('pages.dashboard.heading') }}, {{ firstName }} 👋</div>
        <div class="text-body-2 text-medium-emphasis" style="max-width:520px">{{ t('pages.dashboard.subtitle') }}</div>
        <v-btn v-if="auth.can('finance')" color="primary" class="mt-4" :prepend-icon="mdiPlus" to="/finance/invoices">{{ t('invoices.new') }}</v-btn>
      </div>
      <v-spacer />
      <span class="msym d-none d-sm-block" style="font-size:120px;opacity:.16">insights</span>
    </v-card>

    <v-row v-if="auth.can('finance')">
      <v-col v-for="s in stats" :key="s.key" cols="12" sm="6" lg="3">
        <v-card rounded="lg" border flat class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <v-avatar :color="s.tone" variant="tonal" rounded="lg" size="42"><span class="msym" style="font-size:22px">{{ s.icon }}</span></v-avatar>
            <v-chip v-if="s.trend != null" size="x-small" :color="s.trend >= 0 ? 'success' : 'error'" variant="tonal">{{ s.trend >= 0 ? '+' : '' }}{{ s.trend }}%</v-chip>
          </div>
          <div class="ll-label">{{ t(s.label) }}</div>
          <div class="text-h5 font-weight-bold ll-mono mt-1">{{ s.value }}</div>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-2">
      <v-col cols="12" lg="8" v-if="auth.can('finance')">
        <v-card rounded="lg" border flat>
          <v-toolbar flat color="surface" density="comfortable">
            <v-toolbar-title class="text-subtitle-1 font-weight-medium">{{ t('invoices.tab_invoices') }}</v-toolbar-title>
            <v-spacer />
            <v-btn variant="text" size="small" to="/finance/invoices">{{ t('common.open') }}</v-btn>
          </v-toolbar>
          <v-divider />
          <v-table density="comfortable">
            <thead><tr>
              <th class="text-left">{{ t('invoices.col_number') }}</th><th class="text-left">{{ t('invoices.col_customer') }}</th>
              <th class="text-right">{{ t('invoices.col_total') }}</th><th class="text-left">{{ t('common.status') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="i in recent" :key="i.id" style="cursor:pointer" @click="$router.push('/finance/invoices')">
                <td class="ll-mono">{{ i.number || '—' }}</td>
                <td>{{ custName(i) }}</td>
                <td class="text-right ll-mono">{{ money(Number(i.gross ?? 0)) }}</td>
                <td><v-chip size="x-small" :color="statusColor(i.status)" variant="tonal">{{ t('invoices.status_' + i.status) }}</v-chip></td>
              </tr>
              <tr v-if="!recent.length"><td colspan="4" class="text-center text-medium-emphasis py-6">{{ t('common.none') }}</td></tr>
            </tbody>
          </v-table>
        </v-card>
      </v-col>

      <v-col cols="12" :lg="auth.can('finance') ? 4 : 12">
        <v-card rounded="lg" border flat class="pa-2">
          <v-list density="comfortable" nav>
            <v-list-subheader class="ll-label">{{ t('settings.personal_heading') }}</v-list-subheader>
            <v-list-item v-for="m in modules" :key="m.to" :to="m.to" rounded="lg">
              <template #prepend><v-avatar :color="m.tone" variant="tonal" rounded="lg" size="38"><span class="msym" style="font-size:20px">{{ m.icon }}</span></v-avatar></template>
              <v-list-item-title>{{ t(m.label) }}</v-list-item-title>
              <template #append><span class="msym text-disabled">chevron_right</span></template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiPlus } from '@mdi/js';
import { api } from '@spa/api/client';
import { useAuthStore } from '@spa/stores/auth';

interface Inv { id: number; number: string | null; gross: number | null; status: string; customer: { name?: string } | null }
const auth = useAuthStore();
const kpis = ref<{ year: number; net: number; count: number; growthPct: number | null } | null>(null);
const openGross = ref(0);
const vatPayable = ref(0);
const recent = ref<Inv[]>([]);

const fmt = computed(() => new Intl.NumberFormat(document.documentElement.lang || 'de', { style: 'currency', currency: 'EUR' }));
function money(n: number) { return fmt.value.format(n || 0); }
const firstName = computed(() => (auth.user?.name ?? '').split(' ')[0] || '');
function custName(i: Inv) { return i.customer?.name ?? '—'; }
function statusColor(s: string) { return s === 'paid' ? 'success' : s === 'sent' ? 'info' : s === 'final' ? 'warning' : 'secondary'; }

const stats = computed(() => [
  { key: 'rev', label: 'invoices.stat_revenue', icon: 'trending_up', tone: 'primary', value: money(kpis.value?.net ?? 0), trend: kpis.value?.growthPct ?? null },
  { key: 'open', label: 'invoices.outstanding_total', icon: 'schedule', tone: 'warning', value: money(openGross.value), trend: null },
  { key: 'vat', label: 'invoices.vat_payable', icon: 'receipt_long', tone: 'info', value: money(vatPayable.value), trend: null },
  { key: 'cnt', label: 'invoices.invoice_count', icon: 'description', tone: 'secondary', value: String(kpis.value?.count ?? 0), trend: null },
]);

const modules = computed(() => {
  const m: { to: string; label: string; icon: string; tone: string }[] = [];
  if (auth.can('finance')) m.push({ to: '/finance/invoices', label: 'invoices.tab_invoices', icon: 'account_balance_wallet', tone: 'primary' });
  if (auth.can('files')) m.push({ to: '/files', label: 'messages.nav.files', icon: 'folder', tone: 'info' });
  if (auth.can('contacts')) m.push({ to: '/contacts', label: 'messages.nav.contacts', icon: 'contacts', tone: 'success' });
  m.push({ to: '/profile', label: 'pages.profile.title', icon: 'account_circle', tone: 'secondary' });
  return m;
});

onMounted(async () => {
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

<style scoped>
.ll-hero { background: linear-gradient(120deg, rgba(112,102,245,.16), rgba(158,112,250,.06)); border: 1px solid rgba(167,139,250,.18); }
</style>
