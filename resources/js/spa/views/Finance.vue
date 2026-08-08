<template>
  <div>
    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="dashboard">{{ t('invoices.tab_dashboard') }}</v-tab>
      <v-tab value="invoices">{{ t('invoices.tab_invoices') }}</v-tab>
      <v-tab value="payments">{{ t('invoices.tab_payments') }}</v-tab>
      <v-tab value="partners">{{ t('invoices.tab_partners') }}</v-tab>
      <v-tab value="stats">{{ t('invoices.tab_stats') }}</v-tab>
    </v-tabs>

    <!-- Dashboard -->
    <div v-show="tab === 'dashboard'">
      <v-row v-if="kpis">
        <v-col cols="12" sm="4"><v-card rounded="xl" border flat class="pa-4"><div class="text-caption text-uppercase text-medium-emphasis">{{ t('invoices.revenue') }} {{ kpis.year }}</div><div class="text-h5 mt-1">{{ money(kpis.net) }}</div></v-card></v-col>
        <v-col cols="12" sm="4"><v-card rounded="xl" border flat class="pa-4"><div class="text-caption text-uppercase text-medium-emphasis">{{ t('invoices.status_open') }}</div><div class="text-h5 mt-1 text-warning">{{ money(openGross) }}</div></v-card></v-col>
        <v-col cols="12" sm="4"><v-card rounded="xl" border flat class="pa-4"><div class="text-caption text-uppercase text-medium-emphasis">{{ t('invoices.vat_payable') }}</div><div class="text-h5 mt-1 text-primary">{{ money(vatPayable) }}</div></v-card></v-col>
      </v-row>
    </div>

    <!-- Invoices -->
    <v-card v-show="tab === 'invoices'" rounded="xl" border flat>
      <v-toolbar flat color="surface">
        <v-text-field v-model="q" :placeholder="t('common.search')" :prepend-inner-icon="mdiMagnify" variant="solo-filled" flat density="compact" hide-details single-line style="max-width:280px" class="ml-2" />
        <v-spacer />
        <v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" @click="newInvoice">{{ t('invoices.new') }}</v-btn>
      </v-toolbar>
      <v-divider />
      <v-data-table :headers="invHeaders" :items="filteredInvoices" density="comfortable" :items-per-page="25">
        <template #[`item.number`]="{ item }">{{ item.number || '—' }}</template>
        <template #[`item.customer`]="{ item }">{{ custName(item) }}</template>
        <template #[`item.gross`]="{ item }">{{ money(Number(item.gross ?? 0)) }}</template>
        <template #[`item.status`]="{ item }">
          <v-chip size="small" :color="statusColor(item.status)">{{ t('invoices.status_' + item.status) }}</v-chip>
        </template>
        <template #[`item.actions`]="{ item }">
          <v-btn variant="text" size="small" :icon="mdiPencil" @click="editInvoice(item)" />
          <v-btn v-if="item.number" variant="text" size="small" :icon="mdiFilePdfBox" :href="f.invoicePdfUrl(item.id)" target="_blank" />
          <v-btn variant="text" size="small" color="error" :icon="mdiDelete" @click="delInvoice(item)" />
        </template>
      </v-data-table>
    </v-card>

    <!-- Payments -->
    <v-card v-show="tab === 'payments'" rounded="xl" border flat>
      <v-toolbar flat color="surface"><v-toolbar-title>{{ t('invoices.tab_payments') }}</v-toolbar-title><v-spacer /><v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" @click="newPayment">{{ t('common.add') }}</v-btn></v-toolbar>
      <v-divider />
      <v-list>
        <v-list-item v-for="p in f.paymentMethods" :key="p.id" :title="p.name" :subtitle="p.iban || p.type">
          <template #append><v-btn variant="text" size="small" :icon="mdiPencil" @click="editPayment(p)" /><v-btn variant="text" size="small" color="error" :icon="mdiDelete" @click="f.deletePayment(p.id).then(f.load)" /></template>
        </v-list-item>
        <v-list-item v-if="!f.paymentMethods.length" :title="t('common.none')" class="text-medium-emphasis" />
      </v-list>
    </v-card>

    <!-- Partners -->
    <v-card v-show="tab === 'partners'" rounded="xl" border flat>
      <v-toolbar flat color="surface"><v-toolbar-title>{{ t('invoices.tab_partners') }}</v-toolbar-title><v-spacer /><v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" @click="newPartner">{{ t('common.add') }}</v-btn></v-toolbar>
      <v-divider />
      <v-list>
        <v-list-item v-for="p in f.partners" :key="p.id" :title="p.name" :subtitle="[p.email, p.vat_id].filter(Boolean).join(' · ')">
          <template #append><v-btn variant="text" size="small" :icon="mdiPencil" @click="editPartner(p)" /><v-btn variant="text" size="small" color="error" :icon="mdiDelete" @click="f.deletePartner(p.id).then(f.load)" /></template>
        </v-list-item>
        <v-list-item v-if="!f.partners.length" :title="t('common.none')" class="text-medium-emphasis" />
      </v-list>
    </v-card>

    <!-- Stats -->
    <div v-show="tab === 'stats'">
      <v-card rounded="xl" border flat class="pa-4" v-if="kpis">
        <div class="text-subtitle-1 mb-2">{{ t('invoices.tab_stats') }} {{ kpis.year }}</div>
        <v-row>
          <v-col cols="6" sm="3"><div class="text-caption text-medium-emphasis">{{ t('invoices.revenue') }}</div><div class="text-h6">{{ money(kpis.net) }}</div></v-col>
          <v-col cols="6" sm="3"><div class="text-caption text-medium-emphasis">{{ t('invoices.tab_invoices') }}</div><div class="text-h6">{{ kpis.count }}</div></v-col>
          <v-col cols="6" sm="3" v-if="kpis.growthPct != null"><div class="text-caption text-medium-emphasis">YoY</div><div class="text-h6">{{ kpis.growthPct }}%</div></v-col>
        </v-row>
      </v-card>
    </div>

    <!-- Invoice editor -->
    <v-dialog v-model="invDialog" max-width="820" scrollable>
      <v-card rounded="xl" v-if="draft">
        <v-toolbar flat color="surface">
          <v-toolbar-title>{{ draft.id ? (draft.number || t('invoices.new')) : t('invoices.new') }}</v-toolbar-title>
          <v-spacer />
          <v-chip v-if="draft.status" size="small" :color="statusColor(draft.status)" class="mr-2">{{ t('invoices.status_' + draft.status) }}</v-chip>
        </v-toolbar>
        <v-divider />
        <v-card-text>
          <v-alert v-if="draft.imported" type="info" variant="tonal" density="compact" class="mb-3" :text="t('invoices.imported_readonly') || 'Imported invoice'" />
          <fieldset :disabled="isLocked" style="border:0;padding:0;margin:0">
            <v-row dense>
              <v-col cols="12" sm="6"><v-text-field v-model="custName_" :label="t('invoices.customer')" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" sm="3"><v-text-field v-model="draft.issue_date" :label="t('invoices.issue_date')" type="date" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" sm="3"><v-text-field v-model="draft.due_date" :label="t('invoices.due_date')" type="date" variant="outlined" density="compact" /></v-col>
            </v-row>
            <div class="text-caption text-medium-emphasis mt-2 mb-1">{{ t('invoices.line_items') || 'Positionen' }}</div>
            <div v-for="(l, i) in lines" :key="i" class="d-flex ga-2 mb-1">
              <v-text-field v-model="l.desc" :placeholder="t('invoices.description')" variant="outlined" density="compact" hide-details class="flex-grow-1" />
              <v-text-field v-model.number="l.qty" type="number" style="max-width:72px" variant="outlined" density="compact" hide-details />
              <v-text-field v-model.number="l.unitPrice" type="number" style="max-width:110px" variant="outlined" density="compact" hide-details />
              <v-text-field v-model.number="l.vatRate" type="number" style="max-width:80px" suffix="%" variant="outlined" density="compact" hide-details />
              <v-btn variant="text" :icon="mdiClose" @click="lines.splice(i, 1)" />
            </div>
            <v-btn size="small" variant="text" :prepend-icon="mdiPlus" @click="lines.push({ desc: '', qty: 1, unitPrice: 0, vatRate: 19 })">{{ t('invoices.add_line') || 'Position' }}</v-btn>
            <v-textarea v-model="draft.note" :label="t('invoices.note')" rows="2" variant="outlined" density="compact" class="mt-2" />
          </fieldset>
          <v-divider class="my-3" />
          <div class="d-flex justify-end ga-6">
            <div><span class="text-medium-emphasis">{{ t('invoices.net') }}:</span> {{ money(totals.net) }}</div>
            <div><span class="text-medium-emphasis">{{ t('invoices.vat') }}:</span> {{ money(totals.vat) }}</div>
            <div class="font-weight-medium">{{ t('invoices.gross') }}: {{ money(totals.gross) }}</div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-btn v-if="draft.id && !draft.number" color="primary" variant="tonal" @click="finalize">{{ t('invoices.finalize') }}</v-btn>
          <v-spacer />
          <v-btn variant="text" @click="invDialog = false">{{ t('common.cancel') }}</v-btn>
          <v-btn color="primary" :loading="saving" @click="saveInvoice">{{ t('common.save') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Partner/payment editors -->
    <v-dialog v-model="pDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title>{{ pForm.id ? t('common.edit') : t('common.add') }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="pForm.name" :label="t('account.name')" variant="outlined" density="comfortable" />
          <template v-if="pKind === 'partner'">
            <v-text-field v-model="pForm.email" label="E-Mail" variant="outlined" density="compact" />
            <v-text-field v-model="pForm.vat_id" label="VAT ID" variant="outlined" density="compact" />
          </template>
          <template v-else>
            <v-select v-model="pForm.type" :items="['bank','card','paypal','cash','other']" label="Type" variant="outlined" density="compact" />
            <v-text-field v-model="pForm.iban" label="IBAN" variant="outlined" density="compact" />
          </template>
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn variant="text" @click="pDialog = false">{{ t('common.cancel') }}</v-btn><v-btn color="primary" :loading="saving" @click="savePP">{{ t('common.save') }}</v-btn></v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiPlus, mdiPencil, mdiDelete, mdiMagnify, mdiClose, mdiFilePdfBox } from '@mdi/js';
import { useFinanceStore, type Invoice, type InvoiceLine, type Partner, type PaymentMethod } from '@spa/stores/finance';
import { useToast } from '@spa/composables/useToast';

const f = useFinanceStore();
const { success, error } = useToast();
const tab = ref('dashboard');
const q = ref('');

const kpis = ref<{ year: number; net: number; count: number; growthPct: number | null } | null>(null);
const openGross = ref(0);
const vatPayable = ref(0);

const invDialog = ref(false);
const draft = ref<Partial<Invoice> | null>(null);
const lines = ref<InvoiceLine[]>([]);
const custName_ = ref('');
const saving = ref(false);

const pDialog = ref(false);
const pKind = ref<'partner' | 'payment'>('partner');
interface PPForm { id?: number; version?: number; name: string; email?: string | null; vat_id?: string | null; type?: string; iban?: string | null }
const pForm = reactive<PPForm>({ name: '' });

const invHeaders = [
  { title: t('invoices.number'), key: 'number' },
  { title: t('invoices.customer'), key: 'customer', sortable: false },
  { title: t('invoices.issue_date'), key: 'issue_date' },
  { title: t('invoices.gross'), key: 'gross' },
  { title: t('common.status'), key: 'status' },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
];

onMounted(async () => { await f.load(); await loadReports(); });

async function loadReports() {
  try {
    const r = await f.reports() as { kpis?: typeof kpis.value; aging?: { openGross?: number }; currentVat?: { payable?: number } };
    kpis.value = r.kpis ?? null;
    openGross.value = r.aging?.openGross ?? 0;
    vatPayable.value = r.currentVat?.payable ?? 0;
  } catch { /* ignore */ }
}

const fmt = computed(() => new Intl.NumberFormat(document.documentElement.lang || 'de', { style: 'currency', currency: 'EUR' }));
function money(n: number) { return fmt.value.format(n || 0); }
function statusColor(s: string) { return s === 'paid' ? 'success' : s === 'sent' ? 'info' : s === 'final' ? 'warning' : undefined; }
function custName(i: Invoice) { const c = i.customer as { name?: string } | null; return c?.name ?? '—'; }

const filteredInvoices = computed(() => {
  const s = q.value.trim().toLowerCase();
  if (!s) return f.invoices;
  return f.invoices.filter((i) => (i.number ?? '').toLowerCase().includes(s) || custName(i).toLowerCase().includes(s));
});

const isLocked = computed(() => !!(draft.value?.imported || (draft.value?.number && draft.value?.status !== 'draft')));
const totals = computed(() => {
  let net = 0; let vat = 0;
  for (const l of lines.value) { const ln = (l.qty || 0) * (l.unitPrice || 0); net += ln; vat += ln * ((l.vatRate || 0) / 100); }
  net = Math.round(net * 100) / 100; vat = Math.round(vat * 100) / 100;
  return { net, vat, gross: Math.round((net + vat) * 100) / 100 };
});

function newInvoice() {
  draft.value = { status: 'draft', currency: 'EUR', issue_date: new Date().toISOString().slice(0, 10), customer: {}, lines: [] };
  lines.value = [{ desc: '', qty: 1, unitPrice: 0, vatRate: 19 }];
  custName_.value = '';
  invDialog.value = true;
}
function editInvoice(i: Invoice) {
  draft.value = { ...i };
  lines.value = Array.isArray(i.lines) ? i.lines.map((l) => ({ ...l })) : [];
  custName_.value = custName(i) === '—' ? '' : custName(i);
  invDialog.value = true;
}
async function saveInvoice() {
  if (!draft.value) return;
  saving.value = true;
  const body: Record<string, unknown> = {
    status: draft.value.status, currency: draft.value.currency || 'EUR',
    issue_date: draft.value.issue_date, due_date: draft.value.due_date,
    customer: { ...(draft.value.customer ?? {}), name: custName_.value },
    lines: lines.value, note: draft.value.note,
    net: totals.value.net, vat: totals.value.vat, gross: totals.value.gross,
    vat_rate: lines.value[0]?.vatRate ?? 19,
  };
  if (draft.value.id) body.version = draft.value.version;
  try {
    if (draft.value.id) await f.updateInvoice(draft.value.id, body);
    else await f.createInvoice(body);
    invDialog.value = false; await f.load(); await loadReports(); success(t('common.saved'));
  } catch { error(t('common.error')); } finally { saving.value = false; }
}
async function finalize() {
  if (!draft.value?.id) return;
  try { const r = await f.finalizeInvoice(draft.value.id); draft.value = { ...r.invoice }; await f.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}
async function delInvoice(i: Invoice) { if (!confirm(t('common.confirm_delete'))) return; await f.deleteInvoice(i.id); await f.load(); }

function resetForm(o: PPForm) { Object.assign(pForm, { id: undefined, version: undefined, name: '', email: '', vat_id: '', type: 'bank', iban: '' }, o); }
function newPartner() { pKind.value = 'partner'; resetForm({ name: '' }); pDialog.value = true; }
function editPartner(p: Partner) { pKind.value = 'partner'; resetForm({ id: p.id, name: p.name, email: p.email, vat_id: p.vat_id, version: p.version }); pDialog.value = true; }
function newPayment() { pKind.value = 'payment'; resetForm({ name: '', type: 'bank' }); pDialog.value = true; }
function editPayment(p: PaymentMethod) { pKind.value = 'payment'; resetForm({ id: p.id, name: p.name, type: p.type, iban: p.iban, version: p.version }); pDialog.value = true; }
async function savePP() {
  saving.value = true;
  try {
    if (pKind.value === 'partner') await f.savePartner(pForm as unknown as Partial<Partner>);
    else await f.savePayment(pForm as unknown as Partial<PaymentMethod>);
    pDialog.value = false; await f.load(); success(t('common.saved'));
  } catch { error(t('common.error')); } finally { saving.value = false; }
}

watch(tab, (v) => { if ((v === 'dashboard' || v === 'stats') && !kpis.value) loadReports(); });
</script>
