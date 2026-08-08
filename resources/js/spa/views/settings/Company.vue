<template>
  <div class="mx-auto" style="max-width: 960px">
    <!-- Identity -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiOfficeBuilding" size="small" />
        {{ t('settings.company_identity_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <v-row dense>
          <v-col cols="12">
            <v-text-field v-model="form.company_name" :label="t('settings.company_name')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.company_address" :label="t('settings.company_address')" rows="3" auto-grow variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.company_email" :label="t('settings.company_email')" type="email" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.company_phone" :label="t('settings.company_phone')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.company_tax_id" :label="t('settings.company_tax_id')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.company_vat_id" :label="t('settings.company_vat_id')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Bank details -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiBank" size="small" />
        {{ t('settings.company_bank_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="4">
            <v-text-field v-model="form.company_bank_name" :label="t('settings.company_bank_name')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="5">
            <v-text-field v-model="form.company_iban" :label="t('settings.company_iban')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model="form.company_bic" :label="t('settings.company_bic')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Website + contact persons -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiWeb" size="small" />
        {{ t('settings.company_contacts_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <v-row dense>
          <v-col cols="12">
            <v-text-field v-model="form.company_website" :label="t('settings.company_website')" type="url" placeholder="https://…" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
        </v-row>

        <p class="text-caption text-medium-emphasis mt-4 mb-2">{{ t('settings.company_contacts_hint') }}</p>

        <v-row v-for="(row, i) in form.company_contacts" :key="i" dense align="center">
          <v-col cols="12" sm="3">
            <v-text-field v-model="row.name" :label="t('settings.company_contact_name')" variant="outlined" density="compact" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model="row.role" :label="t('settings.company_contact_role')" variant="outlined" density="compact" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model="row.email" :label="t('settings.company_contact_email')" type="email" variant="outlined" density="compact" hide-details="auto" />
          </v-col>
          <v-col cols="10" sm="2">
            <v-text-field v-model="row.phone" :label="t('settings.company_contact_phone')" variant="outlined" density="compact" hide-details="auto" />
          </v-col>
          <v-col cols="2" sm="1" class="text-center">
            <v-btn :icon="mdiDelete" variant="text" density="comfortable" color="error" :aria-label="t('common.delete')" @click="form.company_contacts.splice(i, 1)" />
          </v-col>
        </v-row>

        <v-btn class="mt-2" variant="tonal" size="small" :prepend-icon="mdiPlus" @click="addContact">
          {{ t('settings.company_contact_add') }}
        </v-btn>
      </v-card-text>
    </v-card>

    <!-- Invoice defaults -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiFileDocument" size="small" />
        {{ t('settings.company_invoice_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <p class="text-caption text-medium-emphasis mb-3">{{ t('settings.company_invoice_hint') }}</p>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.invoice_number_format" :label="t('settings.invoice_number_format')" placeholder="YYYY-NNNN" :hint="t('settings.invoice_number_format_hint')" persistent-hint variant="outlined" density="comfortable" />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model.number="form.invoice_next_number" :label="t('settings.invoice_next_number')" type="number" min="1" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model.number="form.invoice_default_vat_rate" :label="t('settings.invoice_default_vat_rate')" type="number" step="0.01" min="0" max="100" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model.number="form.invoice_payment_terms_days" :label="t('settings.invoice_payment_terms_days')" type="number" min="0" max="365" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="6" class="d-flex align-center">
            <v-switch v-model="form.small_business" :label="t('settings.invoice_small_business_label')" color="primary" density="comfortable" :hint="t('settings.invoice_small_business_hint')" persistent-hint hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.invoice_payment_terms_text" :label="t('settings.invoice_payment_terms_text')" rows="2" auto-grow variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.invoice_payment_methods" :label="t('settings.invoice_payment_methods')" rows="2" auto-grow variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.invoice_footer_text" :label="t('settings.invoice_footer_text')" rows="2" auto-grow variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Design -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiPalette" size="small" />
        {{ t('settings.invoice_design_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select v-model="form.invoice_template" :items="templateItems" :label="t('settings.invoice_template')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.invoice_font" :label="t('settings.invoice_font')" :hint="t('settings.invoice_font_hint')" persistent-hint variant="outlined" density="comfortable" />
          </v-col>

          <v-col cols="12" sm="6">
            <div class="d-flex align-center ga-3">
              <input type="color" v-model="form.invoice_accent_color" style="width:44px;height:44px;border:none;background:none;cursor:pointer;border-radius:8px" :aria-label="t('settings.invoice_accent_color')" >
              <v-text-field v-model="form.invoice_accent_color" :label="t('settings.invoice_accent_color')" variant="outlined" density="comfortable" hide-details="auto" />
            </div>
          </v-col>
          <v-col cols="12" sm="6">
            <div class="d-flex align-center ga-3">
              <input type="color" v-model="form.invoice_heading_color" style="width:44px;height:44px;border:none;background:none;cursor:pointer;border-radius:8px" :aria-label="t('settings.invoice_heading_color')" >
              <v-text-field v-model="form.invoice_heading_color" :label="t('settings.invoice_heading_color')" variant="outlined" density="comfortable" hide-details="auto" />
            </div>
          </v-col>

          <v-col cols="12">
            <v-switch v-model="form.invoice_vat_ist" :label="t('settings.invoice_vat_ist')" color="primary" density="comfortable" :hint="t('settings.invoice_vat_ist_hint')" persistent-hint hide-details="auto" />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Logo -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiImage" size="small" />
        {{ t('settings.company_logo_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <div class="d-flex align-center ga-4 flex-wrap">
          <v-img v-if="logoSrc" :src="logoSrc" max-height="72" max-width="200" contain class="rounded border pa-1" style="background:#fff" />
          <v-file-input
            v-model="logoInput"
            :label="t('settings.company_logo_heading')"
            accept="image/png,image/jpeg,image/gif,image/webp"
            :prepend-icon="mdiImage"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            class="flex-grow-1"
          />
        </div>
        <v-checkbox
          v-if="form.has_logo"
          v-model="removeLogo"
          :label="t('settings.company_logo_remove')"
          color="error"
          density="comfortable"
          hide-details="auto"
          class="mt-1"
        />
      </v-card-text>
    </v-card>

    <!-- Sticky save bar -->
    <v-card rounded="xl" border flat color="surface" style="position: sticky; bottom: 12px; z-index: 2">
      <v-card-actions class="px-4 py-3">
        <v-spacer />
        <v-btn color="primary" variant="flat" :prepend-icon="mdiContentSave" :loading="saving" :disabled="loading" @click="save">
          {{ t('settings.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { mdiOfficeBuilding, mdiBank, mdiWeb, mdiFileDocument, mdiPalette, mdiImage, mdiPlus, mdiDelete, mdiContentSave } from '@mdi/js';

interface CompanyContact {
  name: string;
  role: string;
  email: string;
  phone: string;
}

/** Shape returned by GET /api/v1/company (controller present()). */
interface CompanyResponse {
  company_name: string | null;
  company_address: string | null;
  company_email: string | null;
  company_phone: string | null;
  company_tax_id: string | null;
  company_vat_id: string | null;
  company_iban: string | null;
  company_bic: string | null;
  company_bank_name: string | null;
  invoice_number_format: string | null;
  invoice_next_number: number | null;
  invoice_default_vat_rate: number | string | null;
  small_business: boolean;
  invoice_payment_terms_days: number | null;
  invoice_footer_text: string | null;
  invoice_accent_color: string | null;
  invoice_heading_color: string | null;
  invoice_template: string | null;
  invoice_payment_methods: string | null;
  invoice_payment_terms_text: string | null;
  has_logo: boolean;
  // Fields mirrored from the Blade that the API may also return.
  company_website?: string | null;
  company_contacts?: CompanyContact[] | null;
  invoice_font?: string | null;
  invoice_vat_ist?: boolean | null;
}

interface FormState {
  company_name: string;
  company_address: string;
  company_email: string;
  company_phone: string;
  company_tax_id: string;
  company_vat_id: string;
  company_website: string;
  company_bank_name: string;
  company_iban: string;
  company_bic: string;
  company_contacts: CompanyContact[];
  invoice_number_format: string;
  invoice_next_number: number | null;
  invoice_default_vat_rate: number | null;
  invoice_payment_terms_days: number | null;
  invoice_footer_text: string;
  invoice_payment_terms_text: string;
  invoice_payment_methods: string;
  small_business: boolean;
  invoice_accent_color: string;
  invoice_heading_color: string;
  invoice_template: string;
  invoice_font: string;
  invoice_vat_ist: boolean;
  has_logo: boolean;
}

const DEFAULT_ACCENT = '#111827';
const DEFAULT_HEADING = '#6b7280';

const { success, error } = useToast();

const form = reactive<FormState>({
  company_name: '',
  company_address: '',
  company_email: '',
  company_phone: '',
  company_tax_id: '',
  company_vat_id: '',
  company_website: '',
  company_bank_name: '',
  company_iban: '',
  company_bic: '',
  company_contacts: [],
  invoice_number_format: '',
  invoice_next_number: null,
  invoice_default_vat_rate: null,
  invoice_payment_terms_days: null,
  invoice_footer_text: '',
  invoice_payment_terms_text: '',
  invoice_payment_methods: '',
  small_business: false,
  invoice_accent_color: DEFAULT_ACCENT,
  invoice_heading_color: DEFAULT_HEADING,
  invoice_template: 'editorial',
  invoice_font: '',
  invoice_vat_ist: false,
  has_logo: false,
});

const templateItems = computed(() =>
  (['editorial', 'modern', 'elegant', 'klassisch', 'schlicht'] as const).map((v) => ({
    value: v,
    title: t(`settings.invoice_template_${v}`),
  })),
);

const loading = ref(true);
const saving = ref(false);

// Logo state
const logoInput = ref<File | File[] | null>(null);
const logoPreview = ref<string | null>(null);
const removeLogo = ref(false);
const logoVersion = ref(Date.now());

function selectedLogo(): File | null {
  return Array.isArray(logoInput.value) ? (logoInput.value[0] ?? null) : logoInput.value;
}

const logoSrc = computed<string | null>(() => {
  if (logoPreview.value) return logoPreview.value;
  if (form.has_logo && !removeLogo.value) return `/api/v1/company/logo?v=${logoVersion.value}`;
  return null;
});

watch(logoInput, () => {
  if (logoPreview.value) {
    URL.revokeObjectURL(logoPreview.value);
    logoPreview.value = null;
  }
  const file = selectedLogo();
  if (file) {
    logoPreview.value = URL.createObjectURL(file);
    removeLogo.value = false;
  }
});

function addContact() {
  form.company_contacts.push({ name: '', role: '', email: '', phone: '' });
}

function apply(c: CompanyResponse) {
  form.company_name = c.company_name ?? '';
  form.company_address = c.company_address ?? '';
  form.company_email = c.company_email ?? '';
  form.company_phone = c.company_phone ?? '';
  form.company_tax_id = c.company_tax_id ?? '';
  form.company_vat_id = c.company_vat_id ?? '';
  form.company_website = c.company_website ?? '';
  form.company_bank_name = c.company_bank_name ?? '';
  form.company_iban = c.company_iban ?? '';
  form.company_bic = c.company_bic ?? '';
  form.company_contacts = Array.isArray(c.company_contacts)
    ? c.company_contacts.map((r) => ({ name: r.name ?? '', role: r.role ?? '', email: r.email ?? '', phone: r.phone ?? '' }))
    : [];
  form.invoice_number_format = c.invoice_number_format ?? '';
  form.invoice_next_number = c.invoice_next_number ?? null;
  form.invoice_default_vat_rate = c.invoice_default_vat_rate != null ? Number(c.invoice_default_vat_rate) : null;
  form.invoice_payment_terms_days = c.invoice_payment_terms_days ?? null;
  form.invoice_footer_text = c.invoice_footer_text ?? '';
  form.invoice_payment_terms_text = c.invoice_payment_terms_text ?? '';
  form.invoice_payment_methods = c.invoice_payment_methods ?? '';
  form.small_business = !!c.small_business;
  form.invoice_accent_color = c.invoice_accent_color ?? DEFAULT_ACCENT;
  form.invoice_heading_color = c.invoice_heading_color ?? DEFAULT_HEADING;
  form.invoice_template = c.invoice_template ?? 'editorial';
  form.invoice_font = c.invoice_font ?? '';
  form.invoice_vat_ist = !!c.invoice_vat_ist;
  form.has_logo = !!c.has_logo;
}

/** The field payload sent on save (extra Blade fields are harmless if the API ignores them). */
function payload(): Record<string, unknown> {
  return {
    company_name: form.company_name,
    company_address: form.company_address,
    company_email: form.company_email,
    company_phone: form.company_phone,
    company_tax_id: form.company_tax_id,
    company_vat_id: form.company_vat_id,
    company_website: form.company_website,
    company_bank_name: form.company_bank_name,
    company_iban: form.company_iban,
    company_bic: form.company_bic,
    company_contacts: form.company_contacts.filter((c) => c.name || c.role || c.email || c.phone),
    invoice_number_format: form.invoice_number_format,
    invoice_next_number: form.invoice_next_number,
    invoice_default_vat_rate: form.invoice_default_vat_rate,
    invoice_payment_terms_days: form.invoice_payment_terms_days,
    invoice_footer_text: form.invoice_footer_text,
    invoice_payment_terms_text: form.invoice_payment_terms_text,
    invoice_payment_methods: form.invoice_payment_methods,
    small_business: form.small_business,
    invoice_accent_color: form.invoice_accent_color,
    invoice_heading_color: form.invoice_heading_color,
    invoice_template: form.invoice_template,
    invoice_font: form.invoice_font,
    invoice_vat_ist: form.invoice_vat_ist,
  };
}

/** Build a multipart body (method-spoofed PUT) for a logo upload / removal. */
function multipart(): FormData {
  const fd = new FormData();
  fd.append('_method', 'PUT');
  for (const [key, value] of Object.entries(payload())) {
    if (value === null || value === undefined) continue;
    if (key === 'company_contacts' && Array.isArray(value)) {
      (value as CompanyContact[]).forEach((c, i) => {
        fd.append(`company_contacts[${i}][name]`, c.name ?? '');
        fd.append(`company_contacts[${i}][role]`, c.role ?? '');
        fd.append(`company_contacts[${i}][email]`, c.email ?? '');
        fd.append(`company_contacts[${i}][phone]`, c.phone ?? '');
      });
      continue;
    }
    if (typeof value === 'boolean') {
      fd.append(key, value ? '1' : '0');
      continue;
    }
    fd.append(key, String(value));
  }
  if (removeLogo.value) fd.append('remove_logo', '1');
  const file = selectedLogo();
  if (file) fd.append('logo', file);
  return fd;
}

async function save() {
  saving.value = true;
  try {
    const useMultipart = !!selectedLogo() || removeLogo.value;
    const res = useMultipart
      ? await api.upload<{ company: CompanyResponse }>('/api/v1/company', multipart())
      : await api.put<{ company: CompanyResponse }>('/api/v1/company', payload());
    apply(res.company);
    logoInput.value = null;
    removeLogo.value = false;
    if (logoPreview.value) {
      URL.revokeObjectURL(logoPreview.value);
      logoPreview.value = null;
    }
    logoVersion.value = Date.now();
    success(t('settings.company_saved'));
  } catch {
    error(t('common.error'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const res = await api.get<{ company: CompanyResponse }>('/api/v1/company');
    apply(res.company);
  } catch {
    error(t('common.error'));
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
});
</script>
