<template>
  <div>
    <v-tabs :model-value="tab" @update:model-value="go" color="primary" class="mb-4">
      <v-tab value="dashboard">{{ t('invoices.tab_dashboard') }}</v-tab>
      <v-tab value="invoices">{{ t('invoices.tab_invoices') }}</v-tab>
      <v-tab value="payments">{{ t('invoices.tab_payments') }}</v-tab>
      <v-tab value="receipts">{{ t('invoices.tab_receipts') }}</v-tab>
      <v-tab value="projects">{{ t('invoices.tab_projects') }}</v-tab>
      <v-tab value="partners">{{ t('invoices.tab_partners') }}</v-tab>
      <v-tab value="stats">{{ t('invoices.tab_stats') }}</v-tab>
    </v-tabs>

    <!-- Dashboard -->
    <div v-show="tab === 'dashboard'">
      <v-row v-if="kpis">
        <v-col cols="12" sm="4"><v-card rounded="xl" border flat class="pa-4"><div class="text-caption text-uppercase text-medium-emphasis">{{ t('invoices.stat_revenue') }} {{ kpis.year }}</div><div class="text-h5 mt-1">{{ money(kpis.net) }}</div></v-card></v-col>
        <v-col cols="12" sm="4"><v-card rounded="xl" border flat class="pa-4"><div class="text-caption text-uppercase text-medium-emphasis">{{ t('invoices.outstanding_total') }}</div><div class="text-h5 mt-1 text-warning">{{ money(openGross) }}</div></v-card></v-col>
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
          <v-menu v-if="item.number" location="bottom end">
            <template #activator="{ props }"><v-btn variant="text" size="small" :icon="mdiDotsVertical" v-bind="props" /></template>
            <v-list density="compact">
              <v-list-item :prepend-icon="mdiEmailOutline" :title="t('invoices.email_send')" @click="doEmail(item)" />
              <v-list-item :prepend-icon="mdiGavel" :title="t('invoices.dun_send')" @click="doDun(item)" />
              <v-list-item v-if="item.type !== 'credit_note'" :prepend-icon="mdiCancel" :title="t('invoices.storno')" @click="doStorno(item)" />
            </v-list>
          </v-menu>
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

    <!-- Receipts -->
    <v-card v-show="tab === 'receipts'" rounded="xl" border flat>
      <v-toolbar flat color="surface"><v-toolbar-title>{{ t('invoices.receipts_title') }}</v-toolbar-title><v-spacer /><v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" @click="newReceipt">{{ t('common.add') }}</v-btn></v-toolbar>
      <v-divider />
      <v-data-table :headers="rcptHeaders" :items="f.standaloneReceipts" density="comfortable" :items-per-page="25">
        <template #[`item.name`]="{ item }">
          <div class="d-flex align-center ga-2">
            <v-avatar size="32" variant="tonal" color="primary"><span class="msym" style="font-size:18px">receipt_long</span></v-avatar>
            <span>{{ item.name }}</span>
          </div>
        </template>
        <template #[`item.date`]="{ item }">{{ fmtDate(item.date ?? item.created_at) }}</template>
        <template #[`item.amount`]="{ item }">{{ item.amount != null ? money(Number(item.amount)) : '—' }}</template>
        <template #[`item.category`]="{ item }">
          <v-chip v-if="item.category" size="small" variant="tonal">{{ item.category }}</v-chip>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #[`item.actions`]="{ item }">
          <v-btn variant="text" size="small" :icon="mdiOpenInNew" :href="f.receiptFileUrl(item.id)" target="_blank" />
          <v-btn variant="text" size="small" :icon="mdiPencil" @click="editReceipt(item)" />
          <v-btn variant="text" size="small" color="error" :icon="mdiDelete" @click="delReceipt(item)" />
        </template>
        <template #no-data><div class="pa-6 text-center text-medium-emphasis">{{ t('invoices.receipts_none') }}</div></template>
      </v-data-table>
    </v-card>

    <!-- Projects -->
    <v-card v-show="tab === 'projects'" rounded="xl" border flat>
      <v-toolbar flat color="surface"><v-toolbar-title>{{ t('invoices.tab_projects') }}</v-toolbar-title><v-spacer /><v-btn color="primary" variant="tonal" :prepend-icon="mdiPlus" @click="newProject">{{ t('invoices.project_add') }}</v-btn></v-toolbar>
      <v-divider />
      <v-list>
        <v-list-item v-for="row in projectRows" :key="row.p.id" :title="row.p.name" :subtitle="row.p.note || undefined" :style="{ paddingLeft: (16 + row.depth * 28) + 'px' }">
          <template #prepend><v-avatar size="32" variant="tonal" color="primary"><span class="msym" style="font-size:18px">account_tree</span></v-avatar></template>
          <template #append>
            <v-btn variant="text" size="small" :icon="mdiPencil" @click="editProject(row.p)" />
            <v-btn variant="text" size="small" color="error" :icon="mdiDelete" @click="delProject(row.p)" />
          </template>
        </v-list-item>
        <v-list-item v-if="!f.projects.length" :title="t('invoices.project_empty')" class="text-medium-emphasis" />
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

    <!-- Stats / Reports -->
    <div v-show="tab === 'stats'">
      <v-row class="mb-1" align="center">
        <v-col cols="12" sm="4">
          <v-select v-model="statsYear" :items="years" :label="t('invoices.euer_year')" variant="outlined" density="compact" hide-details @update:model-value="onStatsYear" />
        </v-col>
      </v-row>
      <v-row v-if="kpis">
        <v-col cols="6" sm="3"><v-card rounded="xl" border flat class="pa-4"><div class="text-caption text-uppercase text-medium-emphasis">{{ t('invoices.stat_revenue') }}</div><div class="text-h6 mt-1">{{ money(kpis.net) }}</div></v-card></v-col>
        <v-col cols="6" sm="3"><v-card rounded="xl" border flat class="pa-4"><div class="text-caption text-uppercase text-medium-emphasis">{{ t('invoices.invoice_count') }}</div><div class="text-h6 mt-1">{{ kpis.count }}</div></v-card></v-col>
        <v-col cols="6" sm="3" v-if="kpis.growthPct != null"><div class="pa-4"><div class="text-caption text-medium-emphasis">YoY</div><div class="text-h6">{{ kpis.growthPct }}%</div></div></v-col>
      </v-row>

      <v-row>
        <!-- VAT advance -->
        <v-col cols="12" sm="6">
          <v-card rounded="xl" border flat class="pa-4">
            <div class="text-subtitle-1 mb-2">{{ t('invoices.vat_title') }}</div>
            <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">{{ t('invoices.vat_output') }}</span><span>{{ money(Number(vatAdv?.outputVat ?? 0)) }}</span></div>
            <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">{{ t('invoices.vat_input') }}</span><span>{{ money(Number(vatAdv?.inputVat ?? 0)) }}</span></div>
            <v-divider class="my-1" />
            <div class="d-flex justify-space-between py-1 font-weight-medium"><span>{{ t('invoices.vat_payable') }}</span><span class="text-primary">{{ money(Number(vatAdv?.payable ?? 0)) }}</span></div>
          </v-card>
        </v-col>
        <!-- EÜR -->
        <v-col cols="12" sm="6">
          <v-card rounded="xl" border flat class="pa-4">
            <div class="text-subtitle-1 mb-2">{{ t('invoices.euer_title') }}</div>
            <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">{{ t('invoices.euer_income') }}</span><span>{{ money(Number(euerData?.income?.total ?? 0)) }}</span></div>
            <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">{{ t('invoices.euer_expenses') }}</span><span>{{ money(Number(euerData?.expenses?.total ?? 0)) }}</span></div>
            <v-divider class="my-1" />
            <div class="d-flex justify-space-between py-1 font-weight-medium"><span>{{ t('invoices.euer_profit') }}</span><span>{{ money(Number(euerData?.profit ?? 0)) }}</span></div>
          </v-card>
        </v-col>

        <!-- Aging -->
        <v-col cols="12" sm="6">
          <v-card rounded="xl" border flat class="pa-4">
            <div class="text-subtitle-1 mb-2">{{ t('invoices.aging_title') }}</div>
            <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">{{ t('invoices.aging_current') }}</span><span>{{ money(agingGross('current')) }}</span></div>
            <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">{{ t('invoices.aging_1_30') }}</span><span>{{ money(agingGross('1_30')) }}</span></div>
            <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">{{ t('invoices.aging_31_60') }}</span><span>{{ money(agingGross('31_60')) }}</span></div>
            <div class="d-flex justify-space-between py-1"><span class="text-medium-emphasis">{{ t('invoices.aging_60plus') }}</span><span>{{ money(agingGross('60_plus')) }}</span></div>
            <v-divider class="my-1" />
            <div class="d-flex justify-space-between py-1 font-weight-medium"><span>{{ t('invoices.aging_open_total') }}</span><span class="text-warning">{{ money(openGross) }}</span></div>
          </v-card>
        </v-col>

        <!-- Revenue by customer -->
        <v-col cols="12" sm="6">
          <v-card rounded="xl" border flat class="pa-4">
            <div class="text-subtitle-1 mb-2">{{ t('invoices.stat_by_customer') }}</div>
            <div v-for="c in customers" :key="c.name" class="d-flex justify-space-between py-1">
              <span class="text-truncate" style="max-width:70%">{{ c.name }}</span><span>{{ money(Number(c.net ?? 0)) }}</span>
            </div>
            <div v-if="!customers.length" class="text-medium-emphasis">{{ t('common.none') }}</div>
          </v-card>
        </v-col>

        <!-- Monthly revenue -->
        <v-col cols="12">
          <v-card rounded="xl" border flat class="pa-4">
            <div class="text-subtitle-1 mb-2">{{ t('invoices.stat_monthly') }}</div>
            <div v-for="m in months" :key="m.month" class="d-flex align-center ga-3 py-1">
              <span class="text-medium-emphasis" style="width:44px">{{ monthLabel(m.month) }}</span>
              <div class="flex-grow-1"><v-progress-linear :model-value="monthPct(m.net)" color="primary" height="8" rounded /></div>
              <span style="width:110px" class="text-right">{{ money(Number(m.net ?? 0)) }}</span>
            </div>
          </v-card>
        </v-col>
      </v-row>
    </div>

    <!-- Invoice editor -->
    <v-dialog v-model="invDialog" max-width="820" scrollable>
      <v-card rounded="xl" v-if="draft">
        <v-toolbar flat color="surface">
          <v-toolbar-title>{{ draft.id ? (draft.number || t('invoices.new')) : t('invoices.new') }}</v-toolbar-title>
          <v-spacer />
          <v-menu v-if="draft.id && draft.number" location="bottom end">
            <template #activator="{ props }"><v-btn variant="text" size="small" :icon="mdiDotsVertical" v-bind="props" class="mr-1" /></template>
            <v-list density="compact">
              <v-list-item :prepend-icon="mdiEmailOutline" :title="t('invoices.email_send')" @click="doEmail(draft as Invoice)" />
              <v-list-item :prepend-icon="mdiGavel" :title="t('invoices.dun_send')" @click="doDun(draft as Invoice)" />
              <v-list-item v-if="draft.type !== 'credit_note'" :prepend-icon="mdiCancel" :title="t('invoices.storno')" @click="doStorno(draft as Invoice)" />
            </v-list>
          </v-menu>
          <v-chip v-if="draft.status" size="small" :color="statusColor(draft.status)" class="mr-2">{{ t('invoices.status_' + draft.status) }}</v-chip>
        </v-toolbar>
        <v-divider />
        <v-card-text>
          <v-alert v-if="draft.imported" type="info" variant="tonal" density="compact" class="mb-3" :text="t('invoices.status_final')" />
          <fieldset :disabled="isLocked" style="border:0;padding:0;margin:0">
            <v-row dense>
              <v-col cols="12" sm="6"><v-text-field v-model="custName_" :label="t('invoices.customer')" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" sm="3"><v-text-field v-model="draft.issue_date" :label="t('invoices.issue_date')" type="date" variant="outlined" density="compact" /></v-col>
              <v-col cols="6" sm="3"><v-text-field v-model="draft.due_date" :label="t('invoices.due_date')" type="date" variant="outlined" density="compact" /></v-col>
            </v-row>
            <div class="text-caption text-medium-emphasis mt-2 mb-1">{{ t('invoices.line_desc') }}</div>
            <div v-for="(l, i) in lines" :key="i" class="d-flex ga-2 mb-1">
              <v-text-field v-model="l.desc" :placeholder="t('invoices.line_desc')" variant="outlined" density="compact" hide-details class="flex-grow-1" />
              <v-text-field v-model.number="l.qty" type="number" style="max-width:72px" variant="outlined" density="compact" hide-details />
              <v-text-field v-model.number="l.unitPrice" type="number" style="max-width:110px" variant="outlined" density="compact" hide-details />
              <v-text-field v-model.number="l.vatRate" type="number" style="max-width:80px" suffix="%" variant="outlined" density="compact" hide-details />
              <v-btn variant="text" :icon="mdiClose" @click="lines.splice(i, 1)" />
            </div>
            <v-btn size="small" variant="text" :prepend-icon="mdiPlus" @click="lines.push({ desc: '', qty: 1, unitPrice: 0, vatRate: 19 })">{{ t('invoices.add_line') }}</v-btn>
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
          <v-text-field v-model="pForm.name" :label="t('common.name')" variant="outlined" density="comfortable" />
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

    <!-- Receipt editor -->
    <v-dialog v-model="rDialog" max-width="520">
      <v-card rounded="xl">
        <v-card-title>{{ rForm.id ? t('common.edit') : t('invoices.receipt_standalone') }}</v-card-title>
        <v-card-text>
          <v-file-input v-if="!rForm.id" v-model="rFile" :label="t('invoices.receipt')" variant="outlined" density="compact" :prepend-icon="mdiPaperclip" accept="application/pdf,image/*" />
          <v-text-field v-model="rForm.name" :label="t('invoices.receipt_rename')" variant="outlined" density="compact" />
          <v-text-field v-model="rForm.category" :label="t('invoices.receipt_category')" :placeholder="t('invoices.receipt_category_ph')" variant="outlined" density="compact" />
          <v-combobox v-model="rForm.tags" :label="t('invoices.receipt_tags')" variant="outlined" density="compact" multiple chips closable-chips />
          <v-select v-model="rForm.partner_id" :items="partnerOptions" item-title="name" item-value="id" :label="t('invoices.tab_partners')" variant="outlined" density="compact" clearable />
          <v-text-field v-model="rForm.vat" :label="t('invoices.vat')" variant="outlined" density="compact" />
          <v-textarea v-model="rForm.note" :label="t('invoices.receipt_note')" rows="2" variant="outlined" density="compact" />
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn variant="text" @click="rDialog = false">{{ t('common.cancel') }}</v-btn><v-btn color="primary" :loading="saving" @click="saveReceipt">{{ t('common.save') }}</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Project editor -->
    <v-dialog v-model="prjDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title>{{ prjForm.id ? t('invoices.project_edit') : t('invoices.project_add') }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="prjForm.name" :label="t('invoices.project_name')" variant="outlined" density="comfortable" />
          <v-select v-model="prjForm.parent_id" :items="parentOptions" item-title="name" item-value="id" :label="t('invoices.project_parent')" :placeholder="t('invoices.project_parent_none')" variant="outlined" density="compact" clearable />
          <v-textarea v-model="prjForm.note" :label="t('invoices.project_note')" rows="2" variant="outlined" density="compact" />
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn variant="text" @click="prjDialog = false">{{ t('common.cancel') }}</v-btn><v-btn color="primary" :loading="saving" @click="saveProject">{{ t('common.save') }}</v-btn></v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { mdiPlus, mdiPencil, mdiDelete, mdiMagnify, mdiClose, mdiFilePdfBox, mdiDotsVertical, mdiEmailOutline, mdiGavel, mdiCancel, mdiOpenInNew, mdiPaperclip } from '@mdi/js';
import { useFinanceStore, type Invoice, type InvoiceLine, type Partner, type PaymentMethod, type Project, type Receipt } from '@spa/stores/finance';
import { useToast } from '@spa/composables/useToast';
import { VersionConflict } from '@spa/api/client';

const f = useFinanceStore();
const { success, error } = useToast();
const route = useRoute();
const router = useRouter();
const VALID = ['dashboard', 'invoices', 'payments', 'receipts', 'projects', 'partners', 'stats'];
const tab = computed(() => {
  const s = String(route.params.section || 'dashboard');
  return VALID.includes(s) ? s : 'dashboard';
});
function go(v: unknown) { router.push(`/finance/${String(v)}`); }
const q = ref('');

const kpis = ref<{ year: number; net: number; count: number; growthPct: number | null } | null>(null);
const openGross = ref(0);
const vatPayable = ref(0);

// Report state (stats tab)
const years = ref<number[]>([]);
const statsYear = ref<number>(new Date().getFullYear());
const months = ref<{ month: number; net: number }[]>([]);
const customers = ref<{ name: string; net: number; gross: number; count: number }[]>([]);
const agingBuckets = ref<Record<string, { count: number; gross: number }>>({});
const vatAdv = ref<{ outputVat?: number; inputVat?: number; payable?: number } | null>(null);
const euerData = ref<{ income?: { total?: number }; expenses?: { total?: number }; profit?: number } | null>(null);

const invDialog = ref(false);
const draft = ref<Partial<Invoice> | null>(null);
const lines = ref<InvoiceLine[]>([]);
const custName_ = ref('');
const saving = ref(false);

const pDialog = ref(false);
const pKind = ref<'partner' | 'payment'>('partner');
interface PPForm { id?: number; version?: number; name: string; email?: string | null; vat_id?: string | null; type?: string; iban?: string | null }
const pForm = reactive<PPForm>({ name: '' });

// Receipt form
const rDialog = ref(false);
const rFile = ref<File | File[] | null>(null);
interface RForm { id?: number; version?: number; name: string; category: string; tags: string[]; vat: string; note: string; partner_id: number | null }
const rForm = reactive<RForm>({ name: '', category: '', tags: [], vat: '', note: '', partner_id: null });

// Project form
const prjDialog = ref(false);
interface PrjForm { id?: number; version?: number; name: string; parent_id: number | null; note: string }
const prjForm = reactive<PrjForm>({ name: '', parent_id: null, note: '' });

const invHeaders = [
  { title: t('invoices.col_number'), key: 'number' },
  { title: t('invoices.customer'), key: 'customer', sortable: false },
  { title: t('invoices.issue_date'), key: 'issue_date' },
  { title: t('invoices.gross'), key: 'gross' },
  { title: t('common.status'), key: 'status' },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
];
const rcptHeaders = [
  { title: t('common.name'), key: 'name', sortable: false },
  { title: t('common.date'), key: 'date' },
  { title: t('invoices.gross'), key: 'amount', align: 'end' as const },
  { title: t('invoices.receipt_category'), key: 'category', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
];

onMounted(async () => { await f.load(); await loadReports(); });

async function loadReports(year?: number) {
  try {
    const r = await f.reports(year) as {
      kpis?: typeof kpis.value; aging?: { openGross?: number; buckets?: Record<string, { count: number; gross: number }> };
      currentVat?: { payable?: number }; years?: number[]; year?: number;
      months?: { month: number; net: number }[]; customers?: { name: string; net: number; gross: number; count: number }[];
    };
    kpis.value = r.kpis ?? null;
    openGross.value = r.aging?.openGross ?? 0;
    agingBuckets.value = r.aging?.buckets ?? {};
    years.value = r.years ?? [];
    statsYear.value = r.year ?? statsYear.value;
    months.value = r.months ?? [];
    customers.value = r.customers ?? [];
    const va = await f.vatAdvance(year) as typeof vatAdv.value;
    vatAdv.value = va;
    if (!year) vatPayable.value = va?.payable ?? r.currentVat?.payable ?? 0;
    euerData.value = await f.euer(year) as typeof euerData.value;
  } catch { /* ignore */ }
}
function onStatsYear(v: unknown) { void loadReports(Number(v)); }

const fmt = computed(() => new Intl.NumberFormat(document.documentElement.lang || 'de', { style: 'currency', currency: 'EUR' }));
function money(n: number) { return fmt.value.format(n || 0); }
function fmtDate(s?: string | null) { return s ? String(s).slice(0, 10) : '—'; }
function statusColor(s: string) { return s === 'paid' ? 'success' : s === 'sent' ? 'info' : s === 'final' ? 'warning' : undefined; }
function custName(i: Invoice) { const c = i.customer as { name?: string } | null; return c?.name ?? '—'; }
function agingGross(k: string) { return Number(agingBuckets.value[k]?.gross ?? 0); }
function monthLabel(m: number) { return new Intl.DateTimeFormat(document.documentElement.lang || 'de', { month: 'short' }).format(new Date(2000, m - 1, 1)); }
const monthMax = computed(() => months.value.reduce((mx, m) => Math.max(mx, m.net || 0), 0));
function monthPct(net: number) { return monthMax.value > 0 ? Math.round(((net || 0) / monthMax.value) * 100) : 0; }

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

// Indented project tree (roots + children by parent_id; unknown parents surface as roots).
const projectRows = computed<{ p: Project; depth: number }[]>(() => {
  const all = f.projects;
  const byParent = new Map<number, Project[]>();
  const ids = new Set(all.map((p) => p.id));
  for (const p of all) {
    const key = p.parent_id != null && ids.has(p.parent_id) ? p.parent_id : 0;
    if (!byParent.has(key)) byParent.set(key, []);
    byParent.get(key)!.push(p);
  }
  const out: { p: Project; depth: number }[] = [];
  const walk = (parent: number, depth: number) => {
    for (const p of byParent.get(parent) ?? []) { out.push({ p, depth }); walk(p.id, depth + 1); }
  };
  walk(0, 0);
  return out;
});
const parentOptions = computed(() => f.projects.filter((p) => p.id !== prjForm.id).map((p) => ({ id: p.id, name: p.name })));
const partnerOptions = computed(() => f.partners.map((p) => ({ id: p.id, name: p.name })));

function conflict() { void f.load(); error(t('common.error')); }

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
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function finalize() {
  if (!draft.value?.id) return;
  try { const r = await f.finalizeInvoice(draft.value.id); draft.value = { ...r.invoice }; await f.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}
async function delInvoice(i: Invoice) { if (!confirm(t('common.confirm_delete'))) return; await f.deleteInvoice(i.id); await f.load(); await loadReports(); }

async function doStorno(i: Invoice) {
  try { await f.stornoInvoice(i.id); invDialog.value = false; await f.load(); await loadReports(); success(t('invoices.storno_created')); }
  catch { error(t('invoices.storno_failed')); }
}
async function doEmail(i: Invoice) {
  try { await f.emailInvoice(i.id); success(t('invoices.email_sent')); }
  catch { error(t('invoices.email_failed')); }
}
async function doDun(i: Invoice) {
  try { await f.dunInvoice(i.id); success(t('invoices.dun_sent')); }
  catch { error(t('invoices.dun_failed')); }
}

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
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}

// ---- Receipts ----
function newReceipt() { Object.assign(rForm, { id: undefined, version: undefined, name: '', category: '', tags: [], vat: '', note: '', partner_id: null }); rFile.value = null; rDialog.value = true; }
function editReceipt(r: Receipt) {
  Object.assign(rForm, {
    id: r.id, version: r.version, name: r.name, category: r.category ?? '',
    tags: Array.isArray(r.tags) ? [...r.tags] : [], vat: r.vat ?? '', note: r.note ?? '', partner_id: r.partner_id,
  });
  rFile.value = null; rDialog.value = true;
}
async function saveReceipt() {
  saving.value = true;
  try {
    if (rForm.id) {
      const body: Record<string, unknown> = {
        name: rForm.name, category: rForm.category || null, tags: rForm.tags,
        vat: rForm.vat || null, note: rForm.note || null, partner_id: rForm.partner_id, version: rForm.version,
      };
      await f.updateReceipt(rForm.id, body);
    } else {
      const file = Array.isArray(rFile.value) ? rFile.value[0] : rFile.value;
      if (!file) { error(t('common.error')); saving.value = false; return; }
      const fd = new FormData();
      fd.append('file', file);
      if (rForm.name) fd.append('name', rForm.name);
      if (rForm.category) fd.append('category', rForm.category);
      if (rForm.vat) fd.append('vat', rForm.vat);
      if (rForm.note) fd.append('note', rForm.note);
      if (rForm.partner_id != null) fd.append('partner_id', String(rForm.partner_id));
      for (const tag of rForm.tags) fd.append('tags[]', tag);
      await f.createReceipt(fd);
    }
    rDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delReceipt(r: Receipt) { if (!confirm(t('invoices.receipt_delete_confirm'))) return; await f.deleteReceipt(r.id); await f.load(); }

// ---- Projects ----
function newProject() { Object.assign(prjForm, { id: undefined, version: undefined, name: '', parent_id: null, note: '' }); prjDialog.value = true; }
function editProject(p: Project) { Object.assign(prjForm, { id: p.id, version: p.version, name: p.name, parent_id: p.parent_id, note: p.note ?? '' }); prjDialog.value = true; }
async function saveProject() {
  saving.value = true;
  try {
    const body: Partial<Project> & { version?: number } = { name: prjForm.name, parent_id: prjForm.parent_id, note: prjForm.note || null };
    if (prjForm.id) { body.id = prjForm.id; body.version = prjForm.version; }
    await f.saveProject(body);
    prjDialog.value = false; await f.load(); success(t('common.saved'));
  } catch (e) { if (e instanceof VersionConflict) conflict(); else error(t('common.error')); } finally { saving.value = false; }
}
async function delProject(p: Project) { if (!confirm(t('invoices.project_delete_confirm'))) return; await f.deleteProject(p.id); await f.load(); }

</script>
