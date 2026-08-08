<template>
  <v-card rounded="xl" border flat>
    <v-toolbar flat color="surface"><v-toolbar-title>{{ t('settings.company_section') }}</v-toolbar-title></v-toolbar>
    <v-divider />
    <v-card-text v-if="c">
      <v-row dense>
        <v-col cols="12" sm="6"><v-text-field v-model="c.company_name" label="Name" variant="outlined" density="comfortable" /></v-col>
        <v-col cols="12" sm="6"><v-text-field v-model="c.company_email" label="E-Mail" variant="outlined" density="comfortable" /></v-col>
        <v-col cols="12"><v-textarea v-model="c.company_address" :label="t('settings.company_address')" rows="2" variant="outlined" density="comfortable" /></v-col>
        <v-col cols="12" sm="4"><v-text-field v-model="c.company_phone" :label="t('settings.company_phone')" variant="outlined" density="compact" /></v-col>
        <v-col cols="12" sm="4"><v-text-field v-model="c.company_tax_id" label="Tax ID" variant="outlined" density="compact" /></v-col>
        <v-col cols="12" sm="4"><v-text-field v-model="c.company_vat_id" label="VAT ID" variant="outlined" density="compact" /></v-col>
        <v-col cols="12" sm="6"><v-text-field v-model="c.company_iban" label="IBAN" variant="outlined" density="compact" /></v-col>
        <v-col cols="12" sm="3"><v-text-field v-model="c.company_bic" label="BIC" variant="outlined" density="compact" /></v-col>
        <v-col cols="12" sm="3"><v-text-field v-model="c.company_bank_name" label="Bank" variant="outlined" density="compact" /></v-col>
        <v-col cols="12" sm="4"><v-text-field v-model="c.invoice_number_format" label="Nr-Format" variant="outlined" density="compact" /></v-col>
        <v-col cols="12" sm="4"><v-text-field v-model.number="c.invoice_default_vat_rate" label="USt %" type="number" variant="outlined" density="compact" /></v-col>
        <v-col cols="12" sm="4"><v-select v-model="c.invoice_template" :items="['editorial','modern','elegant','klassisch']" label="Template" variant="outlined" density="compact" /></v-col>
        <v-col cols="12"><v-switch v-model="c.small_business" label="§19 Kleinunternehmer" color="primary" density="compact" /></v-col>
      </v-row>
    </v-card-text>
    <v-card-actions><v-spacer /><v-btn color="primary" :loading="saving" @click="save">{{ t('common.save') }}</v-btn></v-card-actions>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';

interface Company {
  company_name: string | null;
  company_email: string | null;
  company_address: string | null;
  company_phone: string | null;
  company_tax_id: string | null;
  company_vat_id: string | null;
  company_iban: string | null;
  company_bic: string | null;
  company_bank_name: string | null;
  invoice_number_format: string | null;
  invoice_default_vat_rate: number | null;
  invoice_template: string | null;
  small_business: boolean | null;
  [k: string]: string | number | boolean | null;
}
const c = ref<Company | null>(null);
const saving = ref(false);
const { success, error } = useToast();

onMounted(async () => { c.value = (await api.get<{ company: Company }>('/api/v1/company')).company; });
async function save() {
  if (!c.value) return;
  saving.value = true;
  try { await api.put('/api/v1/company', c.value); success(t('common.saved')); }
  catch { error(t('common.error')); }
  finally { saving.value = false; }
}
</script>
